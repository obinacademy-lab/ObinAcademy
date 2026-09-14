<?php
/**
 * Run periodically (hourly is a good default) via a Hostinger hPanel Cron
 * Job — this is a CLI script, not a web page. As later phases land, this
 * file grows more sections (lead drip-sequence sends, admin notification
 * sweeps, subscription billing); each section is independent, safe to run
 * every time, and wrapped in its own try/catch so one section's failure
 * can't stop the rest from running — that isolation matters more now that
 * the subscription sections move real money (see includes/subscriptions.php)
 * and must keep running hourly regardless of what happens in, say, the lead
 * email section.
 *
 * hPanel command: php /home/<user>/domains/<domain>/cron/track-maintenance.php
 * (path depends on the account — the deploy copy places this at repo root).
 *
 * Subscription billing (cron/subscriptions.php's old A-E sections) was
 * folded in here rather than kept as its own hPanel cron job because most
 * shared hosting plans cap the number of cron jobs a single account gets —
 * one job running every section beats needing a second slot.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script only runs from the command line.');
}

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/leads.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/retention.php';
require_once __DIR__ . '/../includes/subscriptions.php';

function cron_section(string $label, callable $fn): void {
    try {
        $fn();
    } catch (Throwable $e) {
        error_log("[track-maintenance] section '{$label}' failed: " . $e->getMessage());
        echo '[' . date('Y-m-d H:i:s') . "] {$label}: FAILED — " . $e->getMessage() . "\n";
    }
}

cron_section('geo_backfill_sweep', function () {
    $processed = geo_backfill_sweep(40);
    echo '[' . date('Y-m-d H:i:s') . "] geo_backfill_sweep: {$processed} session(s) processed\n";
});

cron_section('login_log_geo_backfill_sweep', function () {
    $loginProcessed = login_log_geo_backfill_sweep(20);
    echo '[' . date('Y-m-d H:i:s') . "] login_log_geo_backfill_sweep: {$loginProcessed} login(s) processed\n";
});

cron_section('lead_sequence', function () {
    $sequenceCounts = send_due_sequence_emails();
    echo '[' . date('Y-m-d H:i:s') . "] lead sequence: day3={$sequenceCounts['day3']} day5={$sequenceCounts['day5']} day7={$sequenceCounts['day7']}\n";
});

cron_section('notification_sweep', function () {
    $notifCounts = sweep_visitor_notifications();
    echo '[' . date('Y-m-d H:i:s') . "] notification sweep: pricing_revisit={$notifCounts['pricing_revisit']} stale_returning_visitor={$notifCounts['stale_returning_visitor']}\n";
});

cron_section('learner_retention', function () {
    $retentionCounts = send_due_retention_notifications();
    echo '[' . date('Y-m-d H:i:s') . "] learner retention: 5h={$retentionCounts['5h']} 24h={$retentionCounts['24h']} 72h={$retentionCounts['72h']}\n";
});

// --- Subscription billing (formerly cron/subscriptions.php) ---------------
// Mobile money via iotec has no webhooks anywhere in this codebase, so
// renewal billing is fire-then-reconcile-on-a-later-tick, same pattern
// initiate_payment() already uses to reconcile a stale PENDING course
// purchase:
//   A. fire a charge attempt for anything newly due
//   B. resolve every still-PENDING subscription payment against iotec
//   C. retry anything still in GRACE, on schedule, under the attempt cap
//   D. expire anything past its grace window (or a canceled sub past its
//      paid-through date)
//   E. once a month, settle the watch-time payout pool

cron_section('subscriptions_fire', function () {
    $dueForFirstAttempt = db_all(
        "SELECT s.* FROM subscriptions s
         WHERE s.status = 'ACTIVE' AND s.current_period_ends_at <= NOW()
           AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.subscription_id = s.id AND p.status = 'PENDING')"
    );
    foreach ($dueForFirstAttempt as $sub) fire_subscription_charge_attempt($sub);
    echo '[' . date('Y-m-d H:i:s') . '] subscriptions fire: ' . count($dueForFirstAttempt) . " first attempt(s)\n";
});

cron_section('subscriptions_resolve', function () {
    $pendingPaymentIds = db_all("SELECT id FROM payments WHERE type = 'SUBSCRIPTION' AND status = 'PENDING'");
    $resolvedCounts = ['SUCCESS' => 0, 'PENDING' => 0, 'FAILED' => 0];
    foreach ($pendingPaymentIds as $row) {
        $payment = fetch_payment_with_subscription((int) $row['id']);
        if (!$payment) continue;
        $result = resolve_payment_with_iotec($payment);
        $resolvedCounts[$result['status']] = ($resolvedCounts[$result['status']] ?? 0) + 1;
    }
    echo '[' . date('Y-m-d H:i:s') . "] subscriptions resolve: success={$resolvedCounts['SUCCESS']} pending={$resolvedCounts['PENDING']} failed={$resolvedCounts['FAILED']}\n";
});

cron_section('subscriptions_retry', function () {
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
});

cron_section('subscriptions_expire', function () {
    $expiredGrace = db_run(
        "UPDATE subscriptions SET status = 'EXPIRED'
         WHERE status = 'GRACE' AND current_period_ends_at <= DATE_SUB(NOW(), INTERVAL ? DAY)",
        [SUBSCRIPTION_GRACE_WINDOW_DAYS]
    );
    $expiredCanceled = db_run(
        "UPDATE subscriptions SET status = 'EXPIRED' WHERE status = 'CANCELED' AND current_period_ends_at <= NOW()"
    );
    echo '[' . date('Y-m-d H:i:s') . "] subscriptions expire: grace_timed_out={$expiredGrace} canceled_ran_out={$expiredCanceled}\n";
});

cron_section('subscriptions_payout_settlement', function () {
    if ((int) date('j') > 3) return; // first 3 days of the month only
    $previousMonth = date('Y-m-01', strtotime('first day of last month'));
    $settlement = settle_subscription_payouts_for_month($previousMonth);
    echo '[' . date('Y-m-d H:i:s') . "] subscriptions payout settlement ({$previousMonth}): creators_paid={$settlement['creators_paid']} pool=" . number_format($settlement['pool_amount'], 0) . ($settlement['skipped_zero_watch_time'] ? ' (skipped — no watch activity)' : '') . "\n";
});
