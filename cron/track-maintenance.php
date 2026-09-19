<?php
/**
 * Run periodically (hourly is a good default) via a Hostinger hPanel Cron
 * Job — this is a CLI script, not a web page. Each section is independent,
 * safe to run every time, and wrapped in its own try/catch so one section's
 * failure can't stop the rest from running.
 *
 * hPanel command: php /home/<user>/domains/<domain>/cron/track-maintenance.php
 * (path depends on the account — the deploy copy places this at repo root).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script only runs from the command line.');
}

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/leads.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/retention.php';
require_once __DIR__ . '/../includes/interest.php';
require_once __DIR__ . '/../includes/payment_recovery.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/school_subscriptions.php';
require_once __DIR__ . '/../includes/installments.php';

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

cron_section('stale_payment_reconciliation', function () {
    $counts = sweep_stale_pending_payments();
    echo '[' . date('Y-m-d H:i:s') . "] stale payment reconciliation: success={$counts['success']} failed={$counts['failed']} still_pending={$counts['still_pending']}\n";
});

cron_section('payment_recovery_emails', function () {
    $counts = send_due_payment_recovery_emails();
    echo '[' . date('Y-m-d H:i:s') . "] payment recovery emails: course={$counts['course']} bundle={$counts['bundle']}\n";
});

cron_section('learner_retention', function () {
    $retentionCounts = send_due_retention_notifications();
    echo '[' . date('Y-m-d H:i:s') . "] learner retention: 5h={$retentionCounts['5h']} 24h={$retentionCounts['24h']} 72h={$retentionCounts['72h']}\n";
});

cron_section('course_interest_reminders', function () {
    $reminded = send_due_interest_reminders();
    echo '[' . date('Y-m-d H:i:s') . "] course interest reminders: {$reminded} sent\n";
});

cron_section('school_subscription_renewal_reminders', function () {
    $reminded = send_due_school_subscription_renewal_reminders();
    echo '[' . date('Y-m-d H:i:s') . "] school subscription renewal reminders: {$reminded} sent\n";
});

cron_section('school_subscription_expiry_sweep', function () {
    $swept = sweep_school_subscription_expirations();
    echo '[' . date('Y-m-d H:i:s') . "] school subscription expiry sweep: to_grace={$swept['to_grace']} to_expired={$swept['to_expired']}\n";
});

cron_section('installment_reminders', function () {
    $reminded = send_due_installment_reminders();
    echo '[' . date('Y-m-d H:i:s') . "] installment reminders: {$reminded} sent\n";
});

cron_section('installment_expiry_sweep', function () {
    $swept = sweep_installment_expirations();
    echo '[' . date('Y-m-d H:i:s') . "] installment expiry sweep: to_grace={$swept['to_grace']} to_defaulted={$swept['to_defaulted']}\n";
});
