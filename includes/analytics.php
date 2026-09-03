<?php
/**
 * Visitor Intelligence — first-party session/pageview tracking, no external
 * service. Two cookies: oa_visitor (long-lived identity, ~2yr) and
 * oa_session (short sliding window, ~30min) — kept separate from PHP's own
 * session (which backs auth/CSRF) so login/logout never disturbs tracking.
 *
 * Geo-IP is intentionally never looked up on the request path — a pageview
 * is recorded with country/city NULL, and cron/track-maintenance.php
 * backfills it from a queue. The raw IP is used only for that transient
 * lookup and is never stored.
 */

const VISITOR_COOKIE = 'oa_visitor';
const SESSION_COOKIE = 'oa_session';
const SESSION_WINDOW_MINUTES = 30;

/** Buckets a referrer URL into a coarse traffic source for reporting. */
function classify_referrer_source(?string $referrer): string {
    if (!$referrer) return 'direct';
    $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    if ($host === '') return 'direct';

    $ownHost = strtolower((string) parse_url(APP_URL, PHP_URL_HOST));
    if ($host === $ownHost || str_ends_with($host, '.' . $ownHost)) return 'direct';

    if (preg_match('/(^|\.)(google|bing|duckduckgo|yahoo)\./', $host)) return 'google';

    $socialHosts = ['facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'tiktok.com', 'whatsapp.com', 'wa.me', 'linkedin.com', 't.me', 'youtube.com'];
    foreach ($socialHosts as $s) {
        if ($host === $s || str_ends_with($host, '.' . $s)) return 'social';
    }
    return 'other';
}

/** Best-effort browser/OS/device-type parse from the User-Agent string — a small hand-rolled parser, not exhaustive. */
function parse_user_agent(string $ua): array {
    $device = 'desktop';
    if (preg_match('/iPad|Tablet(?!.*Mobile)/i', $ua)) $device = 'tablet';
    elseif (preg_match('/Mobi|Android.*Mobile|iPhone|iPod/i', $ua)) $device = 'mobile';

    $browser = 'Other';
    if (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
    elseif (preg_match('/OPR\/|Opera/i', $ua)) $browser = 'Opera';
    elseif (preg_match('/SamsungBrowser/i', $ua)) $browser = 'Samsung Internet';
    elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/CriOS\//i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Chrome\//i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Version\/.*Safari\//i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Safari\//i', $ua)) $browser = 'Safari';

    $os = 'Other';
    if (preg_match('/Windows/i', $ua)) $os = 'Windows';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

    return ['device_type' => $device, 'browser' => $browser, 'os' => $os];
}

/**
 * The app sits behind Hostinger's edge CDN (confirmed via a "Server: hcdn"
 * response header), so REMOTE_ADDR alone is the proxy's own address, not
 * the visitor's. Checked in order of how common each header is across
 * CDN/proxy setups — the first non-empty one wins.
 */
function get_client_ip(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP'] as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value !== '') return trim(explode(',', $value)[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function is_bot_user_agent(string $ua): bool {
    return $ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview|headless/i', $ua) === 1;
}

/** Reads/sets the long-lived visitor identity cookie. */
function ensure_visitor_id(): string {
    $visitorId = $_COOKIE[VISITOR_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
        $visitorId = bin2hex(random_bytes(16));
        setcookie(VISITOR_COOKIE, $visitorId, [
            'expires' => time() + 60 * 60 * 24 * 365 * 2,
            'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    return $visitorId;
}

/**
 * Finds the visitor's still-open session (last activity within the sliding
 * window) or starts a new one. Returns the session row id and whether this
 * is a brand new session (so the caller knows whether to set entry_path).
 */
function get_or_create_session(string $visitorId, string $path, string $referrerSource, array $ua): array {
    $sessionToken = $_COOKIE[SESSION_COOKIE] ?? '';
    if (preg_match('/^[a-f0-9]{32}$/', $sessionToken)) {
        $existing = db_one(
            "SELECT id, is_new_visitor FROM visitor_sessions WHERE session_token = ? AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$sessionToken, SESSION_WINDOW_MINUTES]
        );
        if ($existing) {
            db_run(
                'UPDATE visitor_sessions SET last_seen_at = NOW(), exit_path = ?, pageview_count = pageview_count + 1 WHERE id = ?',
                [$path, $existing['id']]
            );
            refresh_session_cookie($sessionToken);
            return ['id' => (int) $existing['id'], 'is_new' => false, 'is_new_visitor' => (bool) $existing['is_new_visitor']];
        }
    }

    $sessionToken = bin2hex(random_bytes(16));
    refresh_session_cookie($sessionToken);

    $isNewVisitor = !db_one('SELECT id FROM visitor_sessions WHERE visitor_id = ? LIMIT 1', [$visitorId]);

    // ip_address is transient — see the column comment in schema.sql. Stored
    // here only so the cron geo sweep has something to resolve later; that
    // same sweep clears it once done.
    $sessionId = db_insert(
        'INSERT INTO visitor_sessions (visitor_id, session_token, entry_path, exit_path, referrer_source, device_type, browser, os, ip_address, pageview_count, is_new_visitor)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [$visitorId, $sessionToken, $path, $path, $referrerSource, $ua['device_type'], $ua['browser'], $ua['os'], get_client_ip(), $isNewVisitor ? 1 : 0]
    );
    return ['id' => $sessionId, 'is_new' => true, 'is_new_visitor' => $isNewVisitor];
}

function refresh_session_cookie(string $token): void {
    setcookie(SESSION_COOKIE, $token, [
        'expires' => time() + 60 * SESSION_WINDOW_MINUTES,
        'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/** Records one pageview row; returns its id so the client can later report time-on-page/scroll for it. */
function record_pageview(int $sessionId, string $visitorId, string $path): int {
    return db_insert(
        'INSERT INTO visitor_pageviews (session_id, visitor_id, path) VALUES (?, ?, ?)',
        [$sessionId, $visitorId, $path]
    );
}

/** Called from the beacon on unload/navigation — final scroll depth + time on that one pageview. */
function finalize_pageview(int $pageviewId, int $timeOnPageSeconds, int $scrollDepthPct): void {
    $timeOnPageSeconds = max(0, min(1800, $timeOnPageSeconds)); // cap — a backgrounded tab must not report hours
    $scrollDepthPct = max(0, min(100, $scrollDepthPct));

    $pv = db_one('SELECT session_id FROM visitor_pageviews WHERE id = ?', [$pageviewId]);
    if (!$pv) return;

    db_run('UPDATE visitor_pageviews SET time_on_page_seconds = ?, scroll_depth_pct = ? WHERE id = ?', [$timeOnPageSeconds, $scrollDepthPct, $pageviewId]);
    db_run('UPDATE visitor_sessions SET max_scroll_depth = GREATEST(max_scroll_depth, ?) WHERE id = ?', [$scrollDepthPct, $pv['session_id']]);
}

// ---------------------------------------------------------------------
// Admin analytics query helpers
// ---------------------------------------------------------------------

/** Visit + visitor totals for the last $days days, including new-vs-returning, sources, and session-quality averages. */
function get_visit_summary(int $days = 30): array {
    $totals = db_one(
        'SELECT COUNT(*) AS sessions, COUNT(DISTINCT visitor_id) AS unique_visitors,
                COALESCE(AVG(pageview_count),0) AS avg_pages,
                COALESCE(AVG(TIMESTAMPDIFF(SECOND, started_at, last_seen_at)),0) AS avg_duration
         FROM visitor_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)',
        [$days - 1]
    );

    $newVisitors = (int) (db_one(
        'SELECT COUNT(*) AS n FROM visitor_sessions WHERE is_new_visitor = 1 AND started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)',
        [$days - 1]
    )['n'] ?? 0);

    $uniqueVisitors = (int) $totals['unique_visitors'];

    $sourceRows = db_all(
        'SELECT referrer_source, COUNT(DISTINCT visitor_id) AS n
         FROM visitor_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY referrer_source',
        [$days - 1]
    );
    $sources = ['google' => 0, 'social' => 0, 'direct' => 0, 'other' => 0];
    foreach ($sourceRows as $r) $sources[$r['referrer_source']] = (int) $r['n'];

    return [
        'visits' => (int) $totals['sessions'],
        'unique_visitors' => $uniqueVisitors,
        'new_visitors' => $newVisitors,
        'returning_visitors' => max(0, $uniqueVisitors - $newVisitors),
        'avg_pages_per_session' => round((float) $totals['avg_pages'], 1),
        'avg_session_duration' => (int) round((float) $totals['avg_duration']),
        'sources' => $sources,
    ];
}

/** Daily session + unique-visitor counts for the last $days days — for the trend chart. */
function get_daily_visits_series(int $days = 30): array {
    $rows = db_all(
        'SELECT DATE(started_at) AS d, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS unique_visitors
         FROM visitor_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(started_at)',
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

/** Most-visited landing (entry) pages in the last $days days. */
function get_top_landing_pages(int $days = 30, int $limit = 8): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT entry_path AS landing_path, COUNT(*) AS visitors
         FROM visitor_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY entry_path ORDER BY visitors DESC LIMIT $limit",
        [$days - 1]
    );
}

/** Most-common exit pages in the last $days days — where visitors leave from. */
function get_top_exit_pages(int $days = 30, int $limit = 8): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT exit_path, COUNT(*) AS n
         FROM visitor_sessions WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY exit_path ORDER BY n DESC LIMIT $limit",
        [$days - 1]
    );
}

/** @return array<string,int> keyed by device_type/browser/os label */
function get_breakdown(string $column, int $days = 30): array {
    $allowed = ['device_type', 'browser', 'os', 'country'];
    if (!in_array($column, $allowed, true)) throw new InvalidArgumentException('Bad column');
    $rows = db_all(
        "SELECT $column AS k, COUNT(*) AS n FROM visitor_sessions
         WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND $column IS NOT NULL
         GROUP BY $column ORDER BY n DESC",
        [$days - 1]
    );
    $out = [];
    foreach ($rows as $r) $out[(string) $r['k']] = (int) $r['n'];
    return $out;
}

/** Top cities (with country) in the last $days days. */
function get_top_cities(int $days = 30, int $limit = 8): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT city, country, COUNT(*) AS n
         FROM visitor_sessions
         WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND city IS NOT NULL
         GROUP BY city, country ORDER BY n DESC LIMIT $limit",
        [$days - 1]
    );
}

/** Most-viewed course detail pages in the last $days days, resolved to real course titles. */
function get_most_viewed_courses(int $days = 30, int $limit = 8): array {
    $limit = max(1, min(50, $limit));
    $rows = db_all(
        "SELECT path, COUNT(*) AS n FROM visitor_pageviews
         WHERE entered_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND path LIKE '%/courses/view.php?%'
         GROUP BY path ORDER BY n DESC LIMIT 200",
        [$days - 1]
    );

    $bySlug = [];
    foreach ($rows as $r) {
        $query = parse_url($r['path'], PHP_URL_QUERY) ?: '';
        parse_str($query, $params);
        $slug = $params['slug'] ?? null;
        if (!$slug) continue;
        $bySlug[$slug] = ($bySlug[$slug] ?? 0) + (int) $r['n'];
    }
    if (!$bySlug) return [];
    arsort($bySlug);
    $bySlug = array_slice($bySlug, 0, $limit, true);

    $slugs = array_keys($bySlug);
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $courses = db_all("SELECT slug, title FROM courses WHERE slug IN ($placeholders)", $slugs);
    $titleBySlug = [];
    foreach ($courses as $c) $titleBySlug[$c['slug']] = $c['title'];

    $out = [];
    foreach ($bySlug as $slug => $n) {
        $out[] = ['slug' => $slug, 'title' => $titleBySlug[$slug] ?? $slug, 'views' => $n];
    }
    return $out;
}

/**
 * Backfills country/city for recent sessions from their (transiently held)
 * IP, one HTTP lookup per session against ip-api.com's free endpoint — never
 * called on the request path, only from the cron sweep. Clears ip_address
 * whether the lookup succeeds or not, so the transient IP never lingers past
 * one sweep's worth of attempts. Returns how many sessions were processed.
 */
function geo_backfill_sweep(int $limit = 30): int {
    $limit = max(1, min(100, $limit));
    // No age cutoff — an hourly sweep only clears its own small backlog if
    // every session is guaranteed to be looked up eventually. A cutoff here
    // meant any session older than the window when the cron first started
    // running (or after any gap in it running) would be permanently
    // skipped rather than just caught up on the next run.
    $pending = db_all(
        "SELECT id, ip_address FROM visitor_sessions
         WHERE country IS NULL AND ip_address IS NOT NULL
         ORDER BY started_at DESC LIMIT $limit"
    );

    foreach ($pending as $row) {
        $ip = $row['ip_address'];
        $geo = geo_lookup_ip($ip);
        if ($geo) {
            db_run('UPDATE visitor_sessions SET country = ?, city = ?, ip_address = NULL WHERE id = ?', [$geo['country'], $geo['city'], $row['id']]);
        } else {
            db_run('UPDATE visitor_sessions SET ip_address = NULL WHERE id = ?', [$row['id']]);
        }
        usleep(150000); // stay comfortably under ip-api.com's free-tier 45 req/min
    }
    return count($pending);
}

/** @return array{country:?string,city:?string}|null */
function geo_lookup_ip(string $ip): ?array {
    // Private/local addresses (common in dev, and behind some proxies) can't be geolocated.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;

    $ch = curl_init('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,city');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4]);
    $body = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $httpStatus !== 200) return null;
    $data = json_decode($body, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') return null;

    return [
        'country' => isset($data['countryCode']) ? substr((string) $data['countryCode'], 0, 2) : null,
        'city' => isset($data['city']) && $data['city'] !== '' ? substr((string) $data['city'], 0, 100) : null,
    ];
}
