<?php
/**
 * Admin notifications — created on real events (a new lead, a creator
 * request — see includes/leads.php) plus two background signals swept
 * periodically by cron/track-maintenance.php: a visitor circling a course's
 * page more than once, and someone who keeps coming back without ever
 * leaving their details.
 */

function create_admin_notification(string $type, string $message, ?int $relatedLeadId = null): void {
    db_insert(
        'INSERT INTO admin_notifications (type, message, related_lead_id) VALUES (?, ?, ?)',
        [$type, substr($message, 0, 500), $relatedLeadId]
    );
}

function get_admin_notifications(int $limit = 20): array {
    return db_all("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT " . max(1, min(100, $limit)));
}

function get_unread_notification_count(): int {
    return (int) db_one("SELECT COUNT(*) AS n FROM admin_notifications WHERE is_read = 0")['n'];
}

function mark_notifications_read(): void {
    db_run('UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0');
}

/**
 * Background signals that don't come from a single event — run from the
 * cron sweep, not the request path. Dedupes by not re-flagging the same
 * visitor within a 7-day window (checked via the message text, which
 * embeds the visitor id — simple and avoids a whole separate tracking
 * table for what's fundamentally a rate-limit on a notice).
 */
function sweep_visitor_notifications(): array {
    $counts = ['pricing_revisit' => 0, 'stale_returning_visitor' => 0];

    // Someone viewed 2+ course detail pages (in the same or different
    // sessions) in the last 24h and still isn't a lead.
    $revisitors = db_all(
        "SELECT vp.visitor_id, COUNT(*) AS views
         FROM visitor_pageviews vp
         WHERE vp.entered_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND vp.path LIKE '%/courses/view.php?%'
         GROUP BY vp.visitor_id HAVING views >= 2"
    );
    foreach ($revisitors as $r) {
        $hasLead = db_one('SELECT id FROM leads WHERE visitor_id = ?', [$r['visitor_id']]);
        if ($hasLead) continue;
        $alreadyNotified = db_one(
            "SELECT id FROM admin_notifications WHERE type = 'pricing_revisit' AND message LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            ['%' . $r['visitor_id'] . '%']
        );
        if ($alreadyNotified) continue;
        create_admin_notification('pricing_revisit', "A visitor (id {$r['visitor_id']}) viewed {$r['views']} course pages in the last day without leaving their details.");
        $counts['pricing_revisit']++;
    }

    // Someone with 3+ sessions ever, active in the last 7 days, still no lead.
    $stale = db_all(
        "SELECT visitor_id, COUNT(*) AS sessions, MAX(started_at) AS last_seen
         FROM visitor_sessions
         GROUP BY visitor_id HAVING sessions >= 3 AND last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    foreach ($stale as $s) {
        $hasLead = db_one('SELECT id FROM leads WHERE visitor_id = ?', [$s['visitor_id']]);
        if ($hasLead) continue;
        $alreadyNotified = db_one(
            "SELECT id FROM admin_notifications WHERE type = 'stale_returning_visitor' AND message LIKE ? AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)",
            ['%' . $s['visitor_id'] . '%']
        );
        if ($alreadyNotified) continue;
        create_admin_notification('stale_returning_visitor', "A visitor (id {$s['visitor_id']}) has returned {$s['sessions']} times but still hasn't registered.");
        $counts['stale_returning_visitor']++;
    }

    return $counts;
}
