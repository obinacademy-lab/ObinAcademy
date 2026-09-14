<?php
require_once __DIR__ . '/payments.php';

/**
 * Platform-wide subscription model (Go/Pro) — the only way to actually
 * learn a course (stream its lessons); one-time per-course purchases have
 * been retired. Browsing the catalog itself is public. A subscription
 * payment reuses the
 * `payments` table and resolve_payment_with_iotec() (see the SUBSCRIPTION
 * branch added there), since mobile money via iotec has no webhooks
 * anywhere in this codebase — renewals are fired and resolved by
 * cron/track-maintenance.php on the same fire-then-reconcile-on-a-later-tick
 * pattern the (now-removed) one-time purchase flow used to reconcile a
 * stale PENDING payment.
 */

// Placeholder UGX prices — same access for every tier today (just price
// points), so this only needs updating here before launch, nowhere else.
const SUBSCRIPTION_TIERS = [
    'GO'  => ['label' => 'Go',  'price' => 15000, 'tagline' => 'Full catalog access'],
    'PRO' => ['label' => 'Pro', 'price' => 60000, 'tagline' => 'Full catalog access'],
];

// Distinct from the 2% AFFILIATE_COMMISSION_RATE course purchases pay
// (functions.php) — subscriptions pay a higher, recurring rate.
const SUBSCRIPTION_AFFILIATE_COMMISSION_RATE = 0.05;

const SUBSCRIPTION_PERIOD_DAYS = 30;
const SUBSCRIPTION_GRACE_WINDOW_DAYS = 5;
const SUBSCRIPTION_RETRY_INTERVAL_DAYS = 2;
const SUBSCRIPTION_MAX_RETRY_ATTEMPTS = 3;

function fetch_payment_with_subscription(int $paymentId): ?array {
    return db_one(
        // s.tier is only for a renewal (payments.subscription_tier is NULL
        // by then) — see resolve_tier_label() below for picking the right one.
        'SELECT p.*, s.current_period_ends_at, s.tier AS existing_subscription_tier,
                u.name AS learner_name, u.email AS learner_email
         FROM payments p
         LEFT JOIN subscriptions s ON s.id = p.subscription_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$paymentId]
    );
}

/** payments.subscription_tier is set only on a first-ever payment; a renewal payment has it NULL and needs the joined subscriptions.tier instead. */
function resolve_tier_label(array $payment): string {
    $tier = $payment['subscription_tier'] ?? $payment['existing_subscription_tier'] ?? null;
    return $tier !== null ? SUBSCRIPTION_TIERS[$tier]['label'] : 'Subscription';
}

/** ACTIVE or GRACE — GRACE still has access, it's mid charge-retry. */
function user_has_active_subscription(int $userId): ?array {
    return db_one("SELECT * FROM subscriptions WHERE user_id = ? AND status IN ('ACTIVE','GRACE')", [$userId]);
}

function get_subscription_for_user(int $userId): ?array {
    return db_one('SELECT * FROM subscriptions WHERE user_id = ?', [$userId]);
}

/** Cancel-at-period-end: access continues until current_period_ends_at, no further charge attempts fire, then it expires normally. */
function cancel_subscription(int $userId): void {
    db_run("UPDATE subscriptions SET status = 'CANCELED', canceled_at = NOW() WHERE user_id = ? AND status IN ('ACTIVE','GRACE')", [$userId]);
}

/**
 * Starts a mobile-money collection for a NEW subscription (first payment
 * only — renewals are created directly by cron/subscriptions.php, which has
 * no browser session to call this from). Subscriptions always require an
 * account, unlike course purchases' guest-checkout branch.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_subscription(int $userId, string $tier, string $phone): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];
    if (!isset(SUBSCRIPTION_TIERS[$tier])) return ['error' => 'Invalid subscription plan.'];

    $existing = get_subscription_for_user($userId);
    if ($existing && in_array($existing['status'], ['ACTIVE', 'GRACE'], true)) {
        return ['error' => "You're already subscribed."];
    }

    $existingPending = db_one(
        "SELECT id FROM payments WHERE user_id = ? AND type = 'SUBSCRIPTION' AND status = 'PENDING' ORDER BY created_at DESC LIMIT 1",
        [$userId]
    );
    if ($existingPending) {
        // Reconcile against iotec's real status before reusing this row —
        // same reasoning as initiate_payment()'s identical check.
        $resolved = resolve_payment_with_iotec(fetch_payment_with_subscription((int) $existingPending['id']));
        if ($resolved['status'] === 'SUCCESS') return ['error' => "You're already subscribed."];
        if ($resolved['status'] === 'PENDING') return ['paymentId' => (int) $existingPending['id']];
        // FAILED — fall through and start a fresh collection below.
    }

    $price = (float) SUBSCRIPTION_TIERS[$tier]['price'];
    $affiliateId = resolve_affiliate_id_from_cookie($userId);

    $paymentId = db_insert(
        "INSERT INTO payments (user_id, subscription_tier, affiliate_id, amount, phone, type, status) VALUES (?, ?, ?, ?, ?, 'SUBSCRIPTION', 'PENDING')",
        [$userId, $tier, $affiliateId, $price, $phone]
    );

    try {
        $result = iotec_initiate_collection($price, $phone, (string) $paymentId, substr('Obin Academy - ' . SUBSCRIPTION_TIERS[$tier]['label'] . ' Subscription', 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for subscription payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

/**
 * Applies a SUCCESS subscription payment — called from
 * resolve_payment_with_iotec()'s SUBSCRIPTION branch, never directly.
 * First-ever payment ($payment['subscription_id'] is null): creates the
 * subscriptions row. Renewal: extends current_period_ends_at by 30 days
 * from the PREVIOUS current_period_ends_at (not from now()), so a renewal
 * that resolves a day or two late inside the grace window doesn't quietly
 * shift the subscriber's billing anchor date. Either way, credits affiliate
 * commission off payments.affiliate_id — for a renewal, cron/subscriptions.php
 * already stamped that column from subscriptions.referred_by_affiliate_id
 * when it created the renewal payment row, so this function itself never
 * needs to distinguish "first payment's cookie" from "renewal's persisted
 * link" — it's already the same column by the time this runs.
 */
function apply_subscription_payment_success(array $payment, ?string $statusMessage): void {
    $paymentId = (int) $payment['id'];
    $userId = (int) $payment['user_id'];
    $isRenewal = $payment['subscription_id'] !== null;

    db()->beginTransaction();
    try {
        db_run("UPDATE payments SET status = 'SUCCESS', status_message = ? WHERE id = ?", [$statusMessage, $paymentId]);

        if (!$isRenewal) {
            $periodEndsAt = date('Y-m-d H:i:s', time() + SUBSCRIPTION_PERIOD_DAYS * 86400);
            $subscriptionId = db_insert(
                'INSERT INTO subscriptions (tier, price, phone, current_period_ends_at, referred_by_affiliate_id, user_id) VALUES (?, ?, ?, ?, ?, ?)',
                [$payment['subscription_tier'], $payment['amount'], $payment['phone'], $periodEndsAt, $payment['affiliate_id'], $userId]
            );
            db_run('UPDATE payments SET subscription_id = ? WHERE id = ?', [$subscriptionId, $paymentId]);
        } else {
            $subscriptionId = (int) $payment['subscription_id'];
            $newPeriodEndsAt = date('Y-m-d H:i:s', strtotime($payment['current_period_ends_at']) + SUBSCRIPTION_PERIOD_DAYS * 86400);
            db_run(
                "UPDATE subscriptions SET status = 'ACTIVE', current_period_ends_at = ?, grace_attempts_made = 0, last_charge_attempt_at = NULL WHERE id = ?",
                [$newPeriodEndsAt, $subscriptionId]
            );
        }

        if ($payment['affiliate_id'] !== null) {
            $affiliate = db_one('SELECT status FROM affiliates WHERE id = ?', [$payment['affiliate_id']]);
            if ($affiliate && $affiliate['status'] === 'ACTIVE') {
                $commission = round((float) $payment['amount'] * SUBSCRIPTION_AFFILIATE_COMMISSION_RATE);
                db_insert(
                    'INSERT INTO affiliate_earnings (amount, affiliate_id, course_id, payment_id) VALUES (?, ?, NULL, ?)',
                    [$commission, $payment['affiliate_id'], $paymentId]
                );
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/**
 * Fires one renewal charge attempt for a subscription that's ACTIVE-and-due
 * or already in GRACE and due for a retry — called by cron/subscriptions.php
 * only, never from a request. Creates a new PENDING payments row and asks
 * iotec to send the subscriber's phone a collection prompt; resolving it
 * (Success/Failed) happens on a later cron tick via resolve_payment_with_iotec(),
 * exactly like a stale course-purchase payment gets reconciled — there is no
 * webhook to resolve it immediately.
 */
function fire_subscription_charge_attempt(array $subscription): void {
    $paymentId = db_insert(
        "INSERT INTO payments (user_id, subscription_id, affiliate_id, amount, phone, type, status) VALUES (?, ?, ?, ?, ?, 'SUBSCRIPTION', 'PENDING')",
        [$subscription['user_id'], $subscription['id'], $subscription['referred_by_affiliate_id'], $subscription['price'], $subscription['phone']]
    );

    try {
        $result = iotec_initiate_collection((float) $subscription['price'], $subscription['phone'], (string) $paymentId, 'Obin Academy - Subscription Renewal');
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[subscriptions cron] initiateCollection failed for renewal payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        // Our own request to iotec failed outright — the subscriber never
        // saw a real charge attempt, so leave the subscription's state and
        // attempt count untouched and let the next cron tick try again.
        return;
    }

    db_run(
        "UPDATE subscriptions SET status = 'GRACE', grace_attempts_made = grace_attempts_made + 1, last_charge_attempt_at = NOW() WHERE id = ?",
        [$subscription['id']]
    );
}

/**
 * Settles one calendar month's blended subscription payout pool across
 * creators, by watch-time share. Idempotent via creator_subscription_payouts'
 * UNIQUE(period_month, creator_id) + INSERT IGNORE, so a partial re-run
 * (e.g. the cron died halfway through) just skips creators already settled.
 * @param string $periodMonth 'Y-m-01' — the first day of the month being settled.
 * @return array{creators_paid: int, pool_amount: float, skipped_zero_watch_time: bool}
 */
function settle_subscription_payouts_for_month(string $periodMonth): array {
    $monthStart = $periodMonth;
    $monthEnd = date('Y-m-d', strtotime($periodMonth . ' +1 month'));

    $revenueRow = db_one(
        "SELECT COALESCE(SUM(amount),0) AS gross FROM payments WHERE type = 'SUBSCRIPTION' AND status = 'SUCCESS' AND created_at >= ? AND created_at < ?",
        [$monthStart, $monthEnd]
    );
    $gross = (float) $revenueRow['gross'];
    if ($gross <= 0) return ['creators_paid' => 0, 'pool_amount' => 0.0, 'skipped_zero_watch_time' => false];

    $affiliateRow = db_one(
        "SELECT COALESCE(SUM(ae.amount),0) AS total FROM affiliate_earnings ae
         JOIN payments p ON p.id = ae.payment_id
         WHERE p.type = 'SUBSCRIPTION' AND p.created_at >= ? AND p.created_at < ?",
        [$monthStart, $monthEnd]
    );
    $poolAmount = $gross - round($gross * PLATFORM_FEE_RATE) - (float) $affiliateRow['total'];
    if ($poolAmount <= 0) return ['creators_paid' => 0, 'pool_amount' => 0.0, 'skipped_zero_watch_time' => false];

    $watchByCreator = db_all(
        "SELECT c.creator_id, COALESCE(SUM(w.seconds), 0) AS watch_seconds FROM (
            SELECT lesson_id, seconds_watched AS seconds, created_at FROM lesson_watch_events
            UNION ALL
            SELECT lesson_id, credit_seconds AS seconds, created_at FROM lesson_text_completions
         ) w
         JOIN lessons l ON l.id = w.lesson_id
         JOIN modules m ON m.id = l.module_id
         JOIN courses c ON c.id = m.course_id
         WHERE w.created_at >= ? AND w.created_at < ?
         GROUP BY c.creator_id",
        [$monthStart, $monthEnd]
    );

    $platformTotal = 0;
    foreach ($watchByCreator as $row) $platformTotal += (int) $row['watch_seconds'];
    // No watch activity at all that month — nothing to split the pool by, so
    // skip payout computation entirely rather than pick an arbitrary split.
    if ($platformTotal <= 0) return ['creators_paid' => 0, 'pool_amount' => $poolAmount, 'skipped_zero_watch_time' => true];

    $paidCount = 0;
    foreach ($watchByCreator as $row) {
        $creatorWatchSeconds = (int) $row['watch_seconds'];
        $payoutAmount = round($poolAmount * $creatorWatchSeconds / $platformTotal);
        db_run(
            'INSERT IGNORE INTO creator_subscription_payouts (period_month, watch_seconds, platform_total_watch_seconds, pool_amount, payout_amount, creator_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$monthStart, $creatorWatchSeconds, $platformTotal, $poolAmount, $payoutAmount, $row['creator_id']]
        );
        $paidCount++;
    }

    return ['creators_paid' => $paidCount, 'pool_amount' => $poolAmount, 'skipped_zero_watch_time' => false];
}
