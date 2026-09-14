<?php
/**
 * Run hourly via its own Hostinger hPanel Cron Job — deliberately a
 * separate job from cron/track-maintenance.php, not a section bolted onto
 * it, since this one moves real money and shouldn't be able to abort or be
 * aborted by an unrelated geo-backfill/lead-email failure.
 *
 * Mobile money via iotec has no webhooks anywhere in this codebase, so
 * renewal billing is fire-then-reconcile-on-a-later-tick, same pattern
 * initiate_payment() already uses to reconcile a stale PENDING course
 * purchase:
 *   A. fire a charge attempt for anything newly due
 *   B. resolve every still-PENDING subscription payment against iotec
 *   C. retry anything still in GRACE, on schedule, under the attempt cap
 *   D. expire anything past its grace window (or a canceled sub past its
 *      paid-through date)
 *   E. once a month, settle the watch-time payout pool
 *
 * hPanel command: php /home/<user>/domains/<domain>/cron/subscriptions.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script only runs from the command line.');
}

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/subscriptions.php';

// --- A: fire first charge attempts for newly-due ACTIVE subscriptions -----
$dueForFirstAttempt = db_all(
    "SELECT s.* FROM subscriptions s
     WHERE s.status = 'ACTIVE' AND s.current_period_ends_at <= NOW()
       AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.subscription_id = s.id AND p.status = 'PENDING')"
);
foreach ($dueForFirstAttempt as $sub) fire_subscription_charge_attempt($sub);
echo '[' . date('Y-m-d H:i:s') . '] subscriptions fire: ' . count($dueForFirstAttempt) . " first attempt(s)\n";

// --- B: resolve every pending subscription payment against iotec ----------
$pendingPaymentIds = db_all("SELECT id FROM payments WHERE type = 'SUBSCRIPTION' AND status = 'PENDING'");
$resolvedCounts = ['SUCCESS' => 0, 'PENDING' => 0, 'FAILED' => 0];
foreach ($pendingPaymentIds as $row) {
    $payment = fetch_payment_with_subscription((int) $row['id']);
    if (!$payment) continue;
    $result = resolve_payment_with_iotec($payment);
    $resolvedCounts[$result['status']] = ($resolvedCounts[$result['status']] ?? 0) + 1;
}
echo '[' . date('Y-m-d H:i:s') . "] subscriptions resolve: success={$resolvedCounts['SUCCESS']} pending={$resolvedCounts['PENDING']} failed={$resolvedCounts['FAILED']}\n";

// --- C: retry GRACE subscriptions on schedule, under the attempt cap ------
$dueForRetry = db_all(
    "SELECT s.* FROM subscriptions s
     WHERE s.status = 'GRACE'
       AND s.grace_attempts_made < ?
       AND s.last_charge_attempt_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
       AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.subscription_id = s.id AND p.status = 'PENDING')",
    [SUBSCRIPTION_MAX_RETRY_ATTEMPTS, SUBSCRIPTION_RETRY_INTERVAL_DAYS]
);
foreach ($dueForRetry as $sub) fire_subscription_charge_attempt($sub);
echo '[' . date('Y-m-d H:i:s') . '] subscriptions retry: ' . count($dueForRetry) . " attempt(s)\n";

// --- D: expire whatever's past saving ---------------------------------------
$expiredGrace = db_run(
    "UPDATE subscriptions SET status = 'EXPIRED'
     WHERE status = 'GRACE' AND current_period_ends_at <= DATE_SUB(NOW(), INTERVAL ? DAY)",
    [SUBSCRIPTION_GRACE_WINDOW_DAYS]
);
$expiredCanceled = db_run(
    "UPDATE subscriptions SET status = 'EXPIRED' WHERE status = 'CANCELED' AND current_period_ends_at <= NOW()"
);
echo '[' . date('Y-m-d H:i:s') . "] subscriptions expire: grace_timed_out={$expiredGrace} canceled_ran_out={$expiredCanceled}\n";

// --- E: monthly payout settlement, first 3 days of the month only ---------
if ((int) date('j') <= 3) {
    $previousMonth = date('Y-m-01', strtotime('first day of last month'));
    $settlement = settle_subscription_payouts_for_month($previousMonth);
    echo '[' . date('Y-m-d H:i:s') . "] subscriptions payout settlement ({$previousMonth}): creators_paid={$settlement['creators_paid']} pool=" . number_format($settlement['pool_amount'], 0) . ($settlement['skipped_zero_watch_time'] ? ' (skipped — no watch activity)' : '') . "\n";
}
