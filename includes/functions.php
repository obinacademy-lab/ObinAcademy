<?php

const PLATFORM_FEE_RATE = 0.10;
const MIN_WITHDRAWAL_UGX = 75000;
const MAX_DAILY_WITHDRAWAL_UGX = 3000000;

const ACCESS_DURATION_OPTIONS = [
    ['label' => '30 days', 'days' => 30],
    ['label' => '90 days', 'days' => 90],
    ['label' => '180 days', 'days' => 180],
    ['label' => '365 days', 'days' => 365],
    ['label' => 'Lifetime access', 'days' => null],
];

/** Escape for safe HTML output. */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** UGX has no minor unit — always round to whole shillings. */
function format_money(float $amount): string {
    return 'UGX ' . number_format(round($amount), 0);
}

function slugify(string $text): string {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

/** Splits a sale into gross/fee/net using the 10% platform commission. */
function split_sale(float $price): array {
    $gross = round($price);
    $fee = round($gross * PLATFORM_FEE_RATE);
    return ['gross' => $gross, 'fee' => $fee, 'net' => $gross - $fee];
}

function get_profile(int $userId): ?array {
    return db_one('SELECT * FROM users WHERE id = ?', [$userId]);
}

/** A learner's finished courses — enrollments.progress is a 0-100 percentage, same convention as the learner dashboard. */
function get_courses_completed_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM enrollments WHERE user_id = ? AND progress >= 100', [$userId])['n'];
}

function get_courses_teaching(int $userId, int $limit = 20): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT c.id, c.title, c.slug, c.thumbnail_url,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
         FROM courses c
         WHERE c.creator_id = ? AND c.status = 'PUBLISHED'
         ORDER BY c.created_at DESC LIMIT $limit",
        [$userId]
    );
}

/**
 * Generates a guest access token. Only the hash is ever stored (same pattern
 * as password_reset_tokens) — the plaintext is returned once, to embed in an
 * emailed link or hand back to the browser for a follow-up API call.
 * @return array{0: string, 1: string} [plaintext token, sha256 hash]
 */
function make_access_token(): array {
    $token = bin2hex(random_bytes(32));
    return [$token, hash('sha256', $token)];
}

/** null days = lifetime access, no expiry. */
function compute_expires_at(?int $accessDurationDays): ?string {
    if (!$accessDurationDays) return null;
    return date('Y-m-d H:i:s', time() + $accessDurationDays * 86400);
}

function format_date(string $datetime): string {
    return date('M j, Y', strtotime($datetime));
}

/** Relative time ("3m ago", "2h ago"); falls back to a plain date past 7 days. */
function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 7 * 86400) return floor($diff / 86400) . 'd ago';
    return format_date($datetime);
}

function base_url(string $path = ''): string {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Current request path relative to APP_URL's subpath (e.g. "/index.php"),
 * for comparing against nav link keys regardless of the app's deploy subpath. */
function current_path(): string {
    $appBasePath = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
    $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($appBasePath !== '' && str_starts_with($requestPath, $appBasePath)) {
        $requestPath = substr($requestPath, strlen($appBasePath));
    }
    return $requestPath === '' ? '/' : $requestPath;
}

/** Resolves a stored image path (root-relative, e.g. "/uploads/thumbnails/x.png") to a
 * full URL under APP_URL's subpath. Passes external http(s) URLs through unchanged. */
function asset_src(string $path): string {
    return preg_match('#^https?://#', $path) ? $path : base_url($path);
}

/** base_url() for a public/ asset, with a ?v=<mtime> cache-buster so browsers
 * pick up CSS/JS changes immediately instead of serving a stale cached copy. */
function versioned_asset(string $path): string {
    $fsPath = __DIR__ . '/../public/' . ltrim($path, '/');
    $version = is_file($fsPath) ? filemtime($fsPath) : time();
    return base_url($path) . '?v=' . $version;
}

/** Renders the Obin Academy logo mark + wordmark. Pass true on light backgrounds. */
function render_logo(bool $dark = false, string $extraClass = ''): void {
    $classes = trim('logo ' . ($dark ? 'dark ' : '') . $extraClass);
    ?>
    <a href="<?= e(base_url('index.php')) ?>" class="<?= e($classes) ?>">
      <span class="logo-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>
      </span>
      <span class="logo-text">Obin <span class="accent">Academy</span></span>
    </a>
    <?php
}

/** A small fixed set of line-icon glyphs (24x24, stroke=currentColor) used across the dashboards. */
function dash_icon(string $name, string $class = ''): void {
    $paths = [
        'clipboard-check' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'banknote' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'wallet' => '<path d="M21 12.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2.5h-4.5a2 2 0 1 1 0-4.5H21Z"/>',
        'book-open' => '<path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7Z"/>',
        'graduation-cap' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'tag' => '<path d="M12.6 2.6 20 10v9a1 1 0 0 1-1 1h-9L2.6 12.6a2 2 0 0 1 0-2.83l7.17-7.17a2 2 0 0 1 2.83 0Z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M5 5l2.5 2.5M16.5 16.5 19 19M3 12h4M17 12h4M5 19l2.5-2.5M16.5 7.5 19 5"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M8.7 13.6 7 22l5-3 5 3-1.7-8.4"/>',
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'quote' => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.75-2-2-2H4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h1c0 2.5-1 3-3 3z"/><path d="M14 21c3 0 7-1 7-8V5c0-1.25-.75-2-2-2h-4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h1c0 2.5-1 3-3 3z"/>',
        'scroll-text' => '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'crown' => '<path d="m2 18 2-11 5 4 3-6 3 6 5-4 2 11Z"/><path d="M2 22h20"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
        'file-text' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10Z"/>',
    ];
    if (!isset($paths[$name])) return;
    echo '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>';
}

/** ISO country code -> display name, for the handful of countries this platform's traffic realistically comes from. Falls back to the raw code for anything else. */
function country_name(?string $code): string {
    if (!$code) return '—';
    $names = ['UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'US' => 'United States', 'GB' => 'United Kingdom'];
    return $names[$code] ?? $code;
}

function format_duration_short(int $seconds): string {
    if ($seconds < 60) return $seconds . 's';
    return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
}

/** Renders a labeled horizontal bar-list — used by every admin breakdown panel (traffic sources, device/browser/OS, lead status/source, etc). */
function render_bar_list(array $counts, array $labels = [], int $limit = 6): void {
    $total = array_sum($counts) ?: 1;
    arsort($counts);
    $counts = array_slice($counts, 0, $limit, true);
    if (!$counts) {
        echo '<p class="muted small" style="margin-top:14px;">No data yet in this range.</p>';
        return;
    }
    echo '<div style="margin-top:16px; display:flex; flex-direction:column; gap:12px;">';
    foreach ($counts as $key => $count) {
        $pct = round($count / $total * 100);
        $label = $labels[$key] ?? $key;
        echo '<div><div class="row between" style="font-size:12.5px; font-weight:700;"><span>' . e($label) . '</span>'
           . '<span class="muted" style="font-weight:600;">' . number_format($count) . ' (' . $pct . '%)</span></div>'
           . '<div class="progress-track" style="margin-top:5px;"><div class="progress-fill" style="width:' . $pct . '%;"></div></div></div>';
    }
    echo '</div>';
}

/**
 * Builds a smooth SVG path ('d' attribute) through a series of [x, y] points using
 * Catmull-Rom-to-Bezier interpolation, so charts render as a curve rather than
 * sharp straight-line segments between data points.
 */
function smooth_svg_path(array $points): string {
    $n = count($points);
    if ($n === 0) return '';
    if ($n === 1) return sprintf('M%.2f,%.2f', $points[0][0], $points[0][1]);

    $d = sprintf('M%.2f,%.2f', $points[0][0], $points[0][1]);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $points[$i - 1] ?? $points[$i];
        $p1 = $points[$i];
        $p2 = $points[$i + 1];
        $p3 = $points[$i + 2] ?? $p2;

        $cp1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
        $cp1y = $p1[1] + ($p2[1] - $p0[1]) / 6;
        $cp2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
        $cp2y = $p2[1] - ($p3[1] - $p1[1]) / 6;

        $d .= sprintf(' C%.2f,%.2f %.2f,%.2f %.2f,%.2f', $cp1x, $cp1y, $cp2x, $cp2y, $p2[0], $p2[1]);
    }
    return $d;
}

/**
 * Row of social icons (Facebook/Instagram/YouTube/TikTok/LinkedIn) — shared
 * by the course sidebar's instructor card and public profile pages. Always
 * renders all five, in the same fixed order, on every page — a platform the
 * creator hasn't linked shows as a muted, non-clickable placeholder rather
 * than being dropped, so the row lines up the same way from creator to
 * creator instead of some pages showing 5 icons and others 1 or 2.
 */
function render_social_links(array $socials): void {
    $iconPaths = [
        'facebook' => '<path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/>',
        'instagram' => '<path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/>',
        'youtube' => '<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.5V8.5l6.3 3.5-6.3 3.5Z"/>',
        'tiktok' => '<path d="M16.5 2h-3v13.5a2.5 2.5 0 1 1-2.5-2.5c.2 0 .4 0 .6.05V9.9a5.6 5.6 0 0 0-.6 0 5.6 5.6 0 1 0 5.6 5.6V8.4a7.4 7.4 0 0 0 4.4 1.4V6.7a4.4 4.4 0 0 1-4.5-4.4Z"/>',
        'linkedin' => '<path d="M6.9 8.4H3.6V20h3.3V8.4zM5.3 3.4a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 20h-3.3v-6.1c0-1.5-.5-2.5-1.9-2.5-1 0-1.6.7-1.9 1.4-.1.2-.1.6-.1.9V20H10s0-10.6 0-11.6h3.3v1.6c.4-.7 1.2-1.7 3-1.7 2.2 0 3.8 1.4 3.8 4.5V20z"/>',
    ];
    echo '<div class="creator-social">';
    foreach ($iconPaths as $network => $path) {
        $url = trim((string) ($socials[$network] ?? ''));
        if ($url !== '') {
            echo '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . e(ucfirst($network)) . '"><svg viewBox="0 0 24 24" fill="currentColor">' . $path . '</svg></a>';
        } else {
            echo '<span class="is-empty" aria-label="' . e(ucfirst($network)) . ' not linked" title="Not linked yet"><svg viewBox="0 0 24 24" fill="currentColor">' . $path . '</svg></span>';
        }
    }
    echo '</div>';
}

/**
 * "Share Course" button + popover: WhatsApp/Facebook/X/LinkedIn open a real
 * share-intent URL in a new tab. Instagram and TikTok have no public web
 * share-intent for an arbitrary link (both are app-only for that), so those
 * two copy the link instead and show a hint to paste it in manually — an
 * honest fallback rather than a broken-looking "share" that goes nowhere.
 * @param string $theme 'dark' for a translucent pill on a dark hero (the
 *   default course-hero background), 'light' for a plain-card/dashboard context.
 */
function render_share_button(string $url, string $title, string $label = 'Share Course', string $theme = 'dark'): void {
    $encodedUrl = urlencode($url);
    $encodedTitle = urlencode($title);
    $waText = urlencode("$title\n$url");
    $linkIcon = '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>';
    ?>
    <div class="share-menu-wrap" data-share-wrap>
      <button type="button" class="btn-share<?= $theme === 'light' ? ' btn-share-light' : '' ?>" data-share-toggle aria-haspopup="true" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $linkIcon ?></svg>
        <?= e($label) ?>
      </button>
      <div class="share-menu" data-share-menu hidden>
        <div class="share-menu-head">Share this course</div>

        <button type="button" class="share-row" data-share-copy="<?= e($url) ?>" data-share-hint="Link copied to your clipboard.">
          <span class="share-row-icon" style="background:#5b6670;"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $linkIcon ?></svg></span>
          Copy Link
        </button>
        <a class="share-row" href="https://wa.me/?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer">
          <span class="share-row-icon" style="background:#25D366;"><svg viewBox="0 0 24 24" fill="#fff"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"/></svg></span>
          WhatsApp
        </a>
        <a class="share-row" href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer">
          <span class="share-row-icon" style="background:#1877F2;"><svg viewBox="0 0 24 24" fill="#fff"><path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/></svg></span>
          Facebook
        </a>
        <a class="share-row" href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&text=<?= $encodedTitle ?>" target="_blank" rel="noopener noreferrer">
          <span class="share-row-icon" style="background:#000;"><svg viewBox="0 0 24 24" fill="#fff"><path d="M18.9 2.3h3.2l-7 8 8.2 10.8h-6.4l-5-6.6-5.8 6.6H2.9l7.5-8.6L2.5 2.3h6.6l4.6 6.1 5.2-6.1zm-1.1 17h1.8L7.3 4.1H5.4l12.4 15.2z"/></svg></span>
          X (Twitter)
        </a>
        <a class="share-row" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $encodedUrl ?>" target="_blank" rel="noopener noreferrer">
          <span class="share-row-icon" style="background:#0A66C2;"><svg viewBox="0 0 24 24" fill="#fff"><path d="M6.9 8.4H3.6V20h3.3V8.4zM5.3 3.4a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 20h-3.3v-6.1c0-1.5-.5-2.5-1.9-2.5-1 0-1.6.7-1.9 1.4-.1.2-.1.6-.1.9V20H10s0-10.6 0-11.6h3.3v1.6c.4-.7 1.2-1.7 3-1.7 2.2 0 3.8 1.4 3.8 4.5V20z"/></svg></span>
          LinkedIn
        </a>
        <button type="button" class="share-row" data-share-copy="<?= e($url) ?>" data-share-hint="Link copied — paste it in your Instagram bio, story, or DM.">
          <span class="share-row-icon" style="background:radial-gradient(circle at 30% 110%, #fdf497, #fd5949 45%, #d6249f 60%, #285AEB 90%);"><svg viewBox="0 0 24 24" fill="#fff"><path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/></svg></span>
          Instagram
        </button>
        <button type="button" class="share-row" data-share-copy="<?= e($url) ?>" data-share-hint="Link copied — paste it in your TikTok bio or video caption.">
          <span class="share-row-icon" style="background:#000;"><svg viewBox="0 0 24 24" fill="#fff"><path d="M16.5 2h-3v13.5a2.5 2.5 0 1 1-2.5-2.5c.2 0 .4 0 .6.05V9.9a5.6 5.6 0 0 0-.6 0 5.6 5.6 0 1 0 5.6 5.6V8.4a7.4 7.4 0 0 0 4.4 1.4V6.7a4.4 4.4 0 0 1-4.5-4.4Z"/></svg></span>
          TikTok
        </button>
      </div>
    </div>
    <?php
}

function redirect(string $path): never {
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

function flash_set(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

function post(string $key, string $default = ''): string {
    return trim((string) ($_POST[$key] ?? $default));
}

function query_param(string $key, string $default = ''): string {
    return trim((string) ($_GET[$key] ?? $default));
}

/** Reads a JSON request body as an associative array. */
function json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Sends a JSON response and exits. */
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** CSRF check for JSON API endpoints (token passed in the JSON body). */
function api_csrf_verify(array $body): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $body['csrf_token'] ?? '')) {
        json_response(['error' => 'Invalid or expired session. Please refresh the page and try again.'], 403);
    }
}

/** Requires login for a JSON API endpoint; sends a 401 JSON error otherwise. */
function api_require_login(): array {
    $user = current_user();
    if (!$user) json_response(['error' => 'You must be logged in.'], 401);
    return $user;
}
