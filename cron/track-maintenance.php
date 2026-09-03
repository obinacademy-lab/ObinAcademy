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

$processed = geo_backfill_sweep(40);
echo "[" . date('Y-m-d H:i:s') . "] geo_backfill_sweep: {$processed} session(s) processed\n";
