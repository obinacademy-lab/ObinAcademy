<?php
/**
 * Shared read queries for public pages and the admin money views. Money is campus payments
 * (payments.type CAMPUS_MEMBERSHIP); the professors' share of each lives in campus_professor_earnings.
 */

/** Headline numbers for the public stat strip. */
function get_platform_stats(): array {
    $campuses = 0; $members = 0;
    try {
        $campuses = (int) db_one('SELECT COUNT(*) AS n FROM campuses')['n'];
        $members = (int) db_one('SELECT COUNT(DISTINCT user_id) AS n FROM campus_memberships WHERE access_until > NOW()')['n'];
    } catch (Throwable $e) {}
    return [
        'campus_count' => $campuses,
        'member_count' => $members,
        'learner_count' => (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role <> 'ADMIN'")['n'],
    ];
}

function get_published_testimonials(): array {
    return db_all("
        SELECT t.*, u.name AS author_name, u.avatar_url AS author_avatar_url, u.headline AS author_headline
        FROM testimonials t JOIN users u ON u.id = t.author_id
        WHERE t.status = 'PUBLISHED' ORDER BY t.reviewed_at DESC
    ");
}

/** Professors' share of the campus payments made on each day, keyed by Y-m-d. Empty before professor pay exists. */
function campus_professor_share_by_day(string $fromDate, ?string $toDate = null): array {
    try {
        $rows = db_all(
            'SELECT DATE(p.created_at) AS d, SUM(e.amount) AS s FROM campus_professor_earnings e JOIN payments p ON p.id = e.payment_id
             WHERE p.created_at >= ? AND p.created_at <= ? GROUP BY DATE(p.created_at)',
            [$fromDate . ' 00:00:00', ($toDate ?? date('Y-m-d')) . ' 23:59:59']
        );
    } catch (Throwable $e) {
        return [];
    }
    return array_column(array_map(fn($r) => ['d' => $r['d'], 's' => (float) $r['s']], $rows), 's', 'd');
}

/**
 * Money collected from campus payments per day for the last $days days, zero-filled, with a running
 * total: the admin Revenue chart.
 */
function get_daily_collections_series(int $days = 30): array {
    $rows = db_all(
        "SELECT DATE(created_at) AS d, SUM(amount) AS collected FROM payments
         WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)",
        [$days - 1]
    );
    $byDate = array_column(array_map(fn($r) => ['d' => $r['d'], 'v' => (float) $r['collected']], $rows), 'v', 'd');
    $series = [];
    $cumulative = 0.0;
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $collected = $byDate[$date] ?? 0.0;
        $cumulative += $collected;
        $series[] = ['date' => $date, 'collected' => $collected, 'cumulative' => $cumulative];
    }
    return $series;
}

/**
 * What Obin Academy kept each day (campus payments minus the professors' share) for the last $days days,
 * zero-filled, with a running total: the admin Overview growth curve. 'fee' is the day's kept amount.
 */
function get_daily_revenue_series(int $days = 30): array {
    $share = campus_professor_share_by_day(date('Y-m-d', strtotime('-' . ($days - 1) . ' days')));
    $series = [];
    $cumulative = 0.0;
    foreach (get_daily_collections_series($days) as $row) {
        $kept = max(0.0, $row['collected'] - ($share[$row['date']] ?? 0.0));
        $cumulative += $kept;
        $series[] = ['date' => $row['date'], 'fee' => $kept, 'cumulative' => $cumulative];
    }
    return $series;
}

/** Totals and the individual campus payments for one calendar day: the admin Revenue day lookup. */
function get_day_collection_summary(string $date): array {
    $payments = db_all(
        "SELECT p.id, p.amount, p.campus_plan, p.created_at, c.name AS campus_name, u.name AS learner_name
         FROM payments p LEFT JOIN campuses c ON c.id = p.campus_id LEFT JOIN users u ON u.id = p.user_id
         WHERE p.type = 'CAMPUS_MEMBERSHIP' AND p.status = 'SUCCESS' AND DATE(p.created_at) = ?
         ORDER BY p.created_at DESC",
        [$date]
    );
    $collected = array_sum(array_map(fn($p) => (float) $p['amount'], $payments));
    $share = campus_professor_share_by_day($date, $date)[$date] ?? 0.0;
    return [
        'collected' => $collected,
        'professors' => $share,
        'kept' => max(0.0, $collected - $share),
        'count' => count($payments),
        'payments' => $payments,
    ];
}
