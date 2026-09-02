<?php
/**
 * Lightweight first-party visitor tracking — no external service. One row
 * per visitor per calendar day (see schema.sql), identified by a long-lived
 * cookie. track_visit() is called once per public pageview from header.php;
 * the get_* functions below power the admin "Visitors" tab.
 */

const VISITOR_COOKIE = 'oa_visitor';

/** Buckets a referrer URL into a coarse traffic source for reporting. */
function classify_referrer_source(?string $referrer): string {
    if (!$referrer) return 'direct';
    $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    if ($host === '') return 'direct';

    // A referrer from our own domain is internal navigation, not incoming traffic.
    $ownHost = strtolower((string) parse_url(APP_URL, PHP_URL_HOST));
    if ($host === $ownHost || str_ends_with($host, '.' . $ownHost)) return 'direct';

    if (preg_match('/(^|\.)(google|bing|duckduckgo|yahoo)\./', $host)) return 'google';

    $socialHosts = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'tiktok.com', 'whatsapp.com', 'wa.me', 'linkedin.com', 't.me', 'youtube.com'];
    foreach ($socialHosts as $s) {
        if ($host === $s || str_ends_with($host, '.' . $s)) return 'social';
    }
    return 'other';
}

/** Records this pageview as today's visit for this visitor. Skips known bots. */
function track_visit(): void {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|headless/i', $ua)) return;

    $visitorId = $_COOKIE[VISITOR_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
        $visitorId = bin2hex(random_bytes(16));
        setcookie(VISITOR_COOKIE, $visitorId, [
            'expires' => time() + 60 * 60 * 24 * 365 * 2,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $source = classify_referrer_source($_SERVER['HTTP_REFERER'] ?? null);
    $path = substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), 0, 500);

    db_run(
        'INSERT INTO page_visits (visitor_id, visit_date, referrer_source, landing_path) VALUES (?, CURDATE(), ?, ?)
         ON DUPLICATE KEY UPDATE id = id',
        [$visitorId, $source, $path]
    );
}

/** Visit + visitor totals for the last $days days, including new-vs-returning and traffic sources. */
function get_visit_summary(int $days = 30): array {
    $totals = db_one(
        'SELECT COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS unique_visitors
         FROM page_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)',
        [$days - 1]
    );

    // "New" in this window means their very first-ever visit falls inside it.
    $newVisitors = (int) (db_one(
        "SELECT COUNT(*) AS n FROM (
            SELECT visitor_id, MIN(visit_date) AS first_seen FROM page_visits GROUP BY visitor_id
         ) f WHERE f.first_seen >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
        [$days - 1]
    )['n'] ?? 0);

    $uniqueVisitors = (int) $totals['unique_visitors'];

    $sourceRows = db_all(
        'SELECT referrer_source, COUNT(DISTINCT visitor_id) AS n
         FROM page_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY referrer_source',
        [$days - 1]
    );
    $sources = ['google' => 0, 'social' => 0, 'direct' => 0, 'other' => 0];
    foreach ($sourceRows as $r) $sources[$r['referrer_source']] = (int) $r['n'];

    return [
        'visits' => (int) $totals['visits'],
        'unique_visitors' => $uniqueVisitors,
        'new_visitors' => $newVisitors,
        'returning_visitors' => max(0, $uniqueVisitors - $newVisitors),
        'sources' => $sources,
    ];
}

/** Daily visit + unique-visitor counts for the last $days days — for the trend chart. */
function get_daily_visits_series(int $days = 30): array {
    $rows = db_all(
        'SELECT visit_date AS d, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS unique_visitors
         FROM page_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY visit_date',
        [$days - 1]
    );
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = ['visits' => (int) $r['visits'], 'unique_visitors' => (int) $r['unique_visitors']];

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $series[] = ['date' => $date] + ($byDate[$date] ?? ['visits' => 0, 'unique_visitors' => 0]);
    }
    return $series;
}

/** Most-visited landing pages in the last $days days. */
function get_top_landing_pages(int $days = 30, int $limit = 8): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT landing_path, COUNT(DISTINCT visitor_id) AS visitors
         FROM page_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY landing_path ORDER BY visitors DESC LIMIT $limit",
        [$days - 1]
    );
}
