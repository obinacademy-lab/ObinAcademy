<?php
/**
 * Run periodically (hourly is a good default) via a Hostinger hPanel Cron
 * Job — this is a CLI script, not a web page. As later phases land, this
 * file grows more sections (lead drip-sequence sends, admin notification
 * sweeps); each section is independent and safe to run every time.
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

$processed = geo_backfill_sweep(40);
echo "[" . date('Y-m-d H:i:s') . "] geo_backfill_sweep: {$processed} session(s) processed\n";

$sequenceCounts = send_due_sequence_emails();
echo "[" . date('Y-m-d H:i:s') . "] lead sequence: day3={$sequenceCounts['day3']} day5={$sequenceCounts['day5']} day7={$sequenceCounts['day7']}\n";

$notifCounts = sweep_visitor_notifications();
echo "[" . date('Y-m-d H:i:s') . "] notification sweep: pricing_revisit={$notifCounts['pricing_revisit']} stale_returning_visitor={$notifCounts['stale_returning_visitor']}\n";
