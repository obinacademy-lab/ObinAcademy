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

/**
 * True when $learnerId currently has paid, unexpired access to every course
 * $creatorId publishes. Computes the grace deadline directly from
 * current_period_ends_at + SCHOOL_SUBSCRIPTION_GRACE_DAYS rather than
 * trusting the stored status/grace_ends_at columns — access control stays
 * correct even if cron/track-maintenance.php's sweep hasn't run recently;
 * that sweep only keeps status current for display (the learner's
 * subscriptions list, admin reporting), it isn't load-bearing for access.
 */
function learner_has_active_school_subscription(int $learnerId, int $creatorId): bool {
    $row = db_one(
        "SELECT current_period_ends_at FROM school_subscriptions
         WHERE learner_id = ? AND creator_id = ? AND status IN ('ACTIVE','GRACE') LIMIT 1",
        [$learnerId, $creatorId]
    );
    if (!$row) return false;
    $graceDeadline = strtotime($row['current_period_ends_at']) + SCHOOL_SUBSCRIPTION_GRACE_DAYS * 86400;
    return time() < $graceDeadline;
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

    $schoolLabel = $creator['school_name'] ?: $creator['name'];
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
                "UPDATE school_subscriptions SET status='ACTIVE', price=?, phone=?, current_period_ends_at=?, grace_ends_at=NULL, renewal_attempts_made=0, last_charge_attempt_at=NULL, reminder_sent_at=NULL, canceled_at=NULL WHERE id=?",
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

const SCHOOL_SUBSCRIPTION_REMINDER_WINDOW_DAYS = 3;

/**
 * Emails a "renew now" reminder for every ACTIVE subscription ending within
 * SCHOOL_SUBSCRIPTION_REMINDER_WINDOW_DAYS that hasn't been reminded yet for
 * this period (reminder_sent_at is cleared on every successful renewal, so
 * this fires again next period). Called from
 * cron/track-maintenance.php — returns how many reminders went out.
 */
function send_due_school_subscription_renewal_reminders(): int {
    $due = db_all(
        "SELECT ss.id, ss.creator_id, u.name AS creator_name, u.school_name,
                learner.name AS learner_name, learner.email AS learner_email
         FROM school_subscriptions ss
         JOIN users u ON u.id = ss.creator_id
         JOIN users learner ON learner.id = ss.learner_id
         WHERE ss.status = 'ACTIVE' AND ss.reminder_sent_at IS NULL
           AND ss.current_period_ends_at <= DATE_ADD(NOW(), INTERVAL ? DAY)",
        [SCHOOL_SUBSCRIPTION_REMINDER_WINDOW_DAYS]
    );
    foreach ($due as $sub) {
        if (!$sub['learner_email']) continue;
        $schoolLabel = $sub['school_name'] ?: $sub['creator_name'];
        $renewUrl = base_url('dashboard/learner/subscriptions.php');
        send_school_subscription_renewal_email($sub['learner_email'], $sub['learner_name'], $schoolLabel, $renewUrl);
        db_run('UPDATE school_subscriptions SET reminder_sent_at = NOW() WHERE id = ?', [$sub['id']]);
    }
    return count($due);
}

/**
 * Walks subscription status forward for display purposes: ACTIVE rows past
 * their period end move to GRACE, GRACE rows past their own grace window
 * move to EXPIRED. Not load-bearing for access control (see the live
 * computation in learner_has_active_school_subscription()) — this keeps
 * status current for the learner's subscriptions list and admin reporting
 * even if cron runs late. Called from cron/track-maintenance.php.
 * @return array{to_grace: int, to_expired: int}
 */
function sweep_school_subscription_expirations(): array {
    $toGrace = db_all("SELECT id FROM school_subscriptions WHERE status = 'ACTIVE' AND current_period_ends_at <= NOW()");
    foreach ($toGrace as $row) {
        $graceEndsAt = date('Y-m-d H:i:s', strtotime('+' . SCHOOL_SUBSCRIPTION_GRACE_DAYS . ' days'));
        db_run("UPDATE school_subscriptions SET status = 'GRACE', grace_ends_at = ? WHERE id = ?", [$graceEndsAt, $row['id']]);
    }
    $toExpired = db_all("SELECT id FROM school_subscriptions WHERE status = 'GRACE' AND grace_ends_at <= NOW()");
    foreach ($toExpired as $row) {
        db_run("UPDATE school_subscriptions SET status = 'EXPIRED' WHERE id = ?", [$row['id']]);
    }
    return ['to_grace' => count($toGrace), 'to_expired' => count($toExpired)];
}
