<?php
// Per-school monthly subscriptions. Not a revival of the old, already-dead
// platform-wide includes/subscriptions.php (GO/PRO tiers, one per user) —
// this is a fresh, parallel system: a learner can hold an active
// subscription to several different creators' schools at once (one row per
// learner+creator pair in school_subscriptions).
//
// Mobile money has no saved-token recurring charge — every renewal needs a
// fresh collection prompt the learner approves, exactly like subscribing
// the first time. So status walks ACTIVE -> GRACE (period ended, a short
// window to renew before access is cut) -> EXPIRED, driven by
// cron/track-maintenance.php's reminder + sweep sections, not a silent
// background charge.

const SCHOOL_SUBSCRIPTION_PERIOD_DAYS = 30;
const SCHOOL_SUBSCRIPTION_GRACE_DAYS = 3;

/** True when $learnerId currently has paid, unexpired access to every course $creatorId publishes. */
function learner_has_active_school_subscription(int $learnerId, int $creatorId): bool {
    $row = db_one(
        "SELECT id FROM school_subscriptions
         WHERE learner_id = ? AND creator_id = ? AND status IN ('ACTIVE','GRACE')
           AND (grace_ends_at IS NULL OR grace_ends_at > NOW())
         LIMIT 1",
        [$learnerId, $creatorId]
    );
    return (bool) $row;
}

function get_school_subscription(int $learnerId, int $creatorId): ?array {
    return db_one('SELECT * FROM school_subscriptions WHERE learner_id = ? AND creator_id = ?', [$learnerId, $creatorId]);
}

/** Every school subscription a learner holds, newest-period-first, with the creator's name/school branding joined on. */
function get_school_subscriptions_for_learner(int $learnerId): array {
    return db_all(
        "SELECT ss.*, u.name AS creator_name, u.school_name, u.avatar_url AS creator_avatar_url
         FROM school_subscriptions ss JOIN users u ON u.id = ss.creator_id
         WHERE ss.learner_id = ? ORDER BY ss.current_period_ends_at DESC",
        [$learnerId]
    );
}

function fetch_payment_with_school_subscription(int $paymentId): ?array {
    return db_one(
        "SELECT p.*, creator.name AS creator_name, creator.school_name AS creator_school_name,
                learner.name AS learner_name, learner.email AS learner_email
         FROM payments p
         JOIN users creator ON creator.id = p.school_subscription_creator_id
         LEFT JOIN users learner ON learner.id = p.user_id
         WHERE p.id = ?",
        [$paymentId]
    );
}

/**
 * Starts (or resumes) a mobile-money collection for a school's monthly
 * subscription. Logged-in learners only — unlike a free course, a paid
 * all-access subscription needs an account, so there's no guest flow here.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_school_subscription(int $learnerId, int $creatorId, string $phone): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];
    if ($learnerId === $creatorId) return ['error' => 'You cannot subscribe to your own school.'];

    $creator = db_one("SELECT * FROM users WHERE id = ? AND role IN ('CREATOR','ADMIN')", [$creatorId]);
    if (!$creator) return ['error' => 'School not found.'];
    if ($creator['pricing_model'] !== 'MONTHLY_SUBSCRIPTION' || empty($creator['school_monthly_price']) || (float) $creator['school_monthly_price'] <= 0) {
        return ['error' => 'This school does not offer a monthly subscription.'];
    }

    if (learner_has_active_school_subscription($learnerId, $creatorId)) {
        return ['error' => "You're already subscribed to this school."];
    }

    $existingPending = db_one(
        "SELECT id FROM payments WHERE user_id = ? AND school_subscription_creator_id = ? AND type = 'SCHOOL_SUBSCRIPTION' AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1",
        [$learnerId, $creatorId]
    );
    if ($existingPending) {
        $resolved = resolve_payment_with_iotec(fetch_payment_with_school_subscription((int) $existingPending['id']));
        if ($resolved['status'] === 'SUCCESS') return ['error' => "You're already subscribed to this school."];
        if ($resolved['status'] === 'PENDING') return ['paymentId' => (int) $existingPending['id']];
        // FAILED — fall through and start a fresh collection below.
    }

    $price = (float) $creator['school_monthly_price'];
    $existingSub = get_school_subscription($learnerId, $creatorId); // may be EXPIRED/CANCELED — fine, success renews it

    $paymentId = db_insert(
        "INSERT INTO payments (user_id, school_subscription_id, school_subscription_creator_id, amount, phone, type, status) VALUES (?, ?, ?, ?, ?, 'SCHOOL_SUBSCRIPTION', 'PENDING')",
        [$learnerId, $existingSub ? $existingSub['id'] : null, $creatorId, $price, $phone]
    );

    $schoolLabel = $creator['school_name'] ?: ($creator['name'] . "'s School");
    try {
        $result = iotec_initiate_collection($price, $phone, (string) $paymentId, substr("Obin Academy - {$schoolLabel} Subscription", 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for school subscription payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

/**
 * Creates or renews the school_subscriptions row for a successful
 * SCHOOL_SUBSCRIPTION payment, credits the creator's earnings at the same
 * split every other sale uses, and links the payment back to the
 * subscription row. Called only from resolve_payment_with_iotec().
 */
function apply_school_subscription_payment_success(array $payment): void {
    $learnerId = (int) $payment['user_id'];
    $creatorId = (int) $payment['school_subscription_creator_id'];
    $periodEndsAt = date('Y-m-d H:i:s', strtotime('+' . SCHOOL_SUBSCRIPTION_PERIOD_DAYS . ' days'));

    db()->beginTransaction();
    try {
        $existing = db_one('SELECT id FROM school_subscriptions WHERE learner_id = ? AND creator_id = ?', [$learnerId, $creatorId]);
        if ($existing) {
            $subscriptionId = (int) $existing['id'];
            db_run(
                "UPDATE school_subscriptions SET status='ACTIVE', price=?, phone=?, current_period_ends_at=?, grace_ends_at=NULL, renewal_attempts_made=0, last_charge_attempt_at=NULL, canceled_at=NULL WHERE id=?",
                [$payment['amount'], $payment['phone'], $periodEndsAt, $subscriptionId]
            );
        } else {
            $subscriptionId = db_insert(
                "INSERT INTO school_subscriptions (status, price, phone, current_period_ends_at, learner_id, creator_id) VALUES ('ACTIVE', ?, ?, ?, ?, ?)",
                [$payment['amount'], $payment['phone'], $periodEndsAt, $learnerId, $creatorId]
            );
        }
        db_run('UPDATE payments SET school_subscription_id = ? WHERE id = ?', [$subscriptionId, $payment['id']]);

        $split = split_sale((float) $payment['amount']);
        db_insert(
            'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, NULL, ?, ?, ?)',
            [$creatorId, $split['net'], $split['gross'], $split['fee']]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
