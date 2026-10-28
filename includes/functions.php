<?php

// The platform-wide WhatsApp community. Linked from the footer.
const WHATSAPP_COMMUNITY_URL = 'https://chat.whatsapp.com/F4eY0Gff1Oh5DTlUiLs7Kt';

/**
 * Every dashboard theme color available to a user (Settings → Dashboard
 * Theme), each a fully-designed "dark shell + one accent" pairing — never a
 * raw color picker, so no combination a learner/creator/admin can choose
 * ever ends up low-contrast or off-brand. `swatch` is just for rendering
 * the picker UI; the real values live in the matching .theme-<key> CSS
 * class in dashboard.css (kept in sync by hand — there's no other source
 * of truth to generate this from).
 */
const DASHBOARD_THEMES = [
    'light'  => ['label' => 'Light',  'swatch' => '#e8e8f0'],
    'blue'   => ['label' => 'Blue',   'swatch' => '#0b00ff'],
    'purple' => ['label' => 'Purple', 'swatch' => '#8b5cf6'],
    'gold'   => ['label' => 'Gold',   'swatch' => '#f5b301'],
    'red'    => ['label' => 'Red',    'swatch' => '#dc2626'],
    'green'  => ['label' => 'Green',  'swatch' => '#3d8f5f'],
];

/**
 * The theme every role opens with before the learner/creator/admin ever picks
 * one for themselves: the platform's own brand blue, the same for students,
 * creators and admins. Anyone who explicitly chose Red, Gold, Purple or Green
 * keeps it; a user with nothing stored (or a retired theme) gets this default
 * via dashboard_theme_for_user(), so no data migration is needed.
 */
function dashboard_theme_default(string $role): string {
    return 'blue';
}

/** @return string a valid DASHBOARD_THEMES key — never trusts a stored value blindly, in case it predates a theme being renamed/removed. */
function dashboard_theme_for_user(array $user): string {
    $stored = $user['dashboard_theme_color'] ?? null;
    if ($stored && isset(DASHBOARD_THEMES[$stored])) return $stored;
    return dashboard_theme_default($user['role']);
}

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

function format_date(string $datetime): string {
    return date('M j, Y', strtotime($datetime));
}

/** A UG-style local number ("0772 123 456") into the digits-only international form wa.me needs. Null if it doesn't look like a real number. */
function whatsapp_number(?string $phone): ?string {
    $digits = preg_replace('/\D/', '', (string) $phone);
    if ($digits === '') return null;
    if (strlen($digits) === 10 && $digits[0] === '0') return '256' . substr($digits, 1);
    if (strlen($digits) === 12 && str_starts_with($digits, '256')) return $digits;
    if (strlen($digits) === 9) return '256' . $digits;
    return null;
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
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'clipboard-check' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'banknote' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        'wallet' => '<path d="M21 12.5V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2.5h-4.5a2 2 0 1 1 0-4.5H21Z"/>',
        'book-open' => '<path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2Z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7Z"/>',
        'graduation-cap' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'tag' => '<path d="M12.6 2.6 20 10v9a1 1 0 0 1-1 1h-9L2.6 12.6a2 2 0 0 1 0-2.83l7.17-7.17a2 2 0 0 1 2.83 0Z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v9H5v-9"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
        'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M5 5l2.5 2.5M16.5 16.5 19 19M3 12h4M17 12h4M5 19l2.5-2.5M16.5 7.5 19 5"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M8.7 13.6 7 22l5-3 5 3-1.7-8.4"/>',
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'quote' => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.75-2-2-2H4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h1c0 2.5-1 3-3 3z"/><path d="M14 21c3 0 7-1 7-8V5c0-1.25-.75-2-2-2h-4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h1c0 2.5-1 3-3 3z"/>',
        'scroll-text' => '<path d="M15 12h-5"/><path d="M15 8h-5"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
        'upload' => '<path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
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
        'eye' => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
        'share' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'calendar' => '<path d="M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
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
 * The site-wide blue trust strip (campuses open, learners, the year's shape) from a
 * get_platform_stats() row. The count-up animation is data-count-up in main.js.
 */
function render_stat_strip(array $stats): void {
    ?>
    <section class="stat-strip">
      <div class="container stat-strip-grid">
        <div class="stat-strip-item">
          <div class="value" data-count-up data-count-value="<?= (int) $stats['campus_count'] ?>" data-count-suffix="">0</div>
          <div class="label">Campuses</div>
        </div>
        <div class="stat-strip-item">
          <div class="value" data-count-up data-count-value="<?= (int) $stats['learner_count'] ?>" data-count-suffix="+">0+</div>
          <div class="label">Learners</div>
        </div>
        <div class="stat-strip-item">
          <div class="value" data-count-up data-count-value="12" data-count-suffix="">0</div>
          <div class="label">Months per campus</div>
        </div>
        <div class="stat-strip-item">
          <div class="value" data-count-up data-count-value="52" data-count-suffix="">0</div>
          <div class="label">Live classes a year</div>
        </div>
      </div>
    </section>
    <?php
}

function redirect(string $path): never {
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

/**
 * Validates a ?redirect= value that's meant to stay on this site (post-login
 * bounce-back, etc.) before it's ever passed to redirect() — a bare
 * str_starts_with($path, '/') check still lets a protocol-relative URL
 * ("//evil.example/phish", which browsers resolve as https://evil.example/…)
 * through, so this also rejects a second leading slash.
 */
function safe_local_redirect_path(string $path, string $fallback): string {
    if ($path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
        return $path;
    }
    return $fallback;
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
    // A session with no token yet never matches, even an empty submitted one.
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    if ($expected === '' || !hash_equals($expected, (string) $token)) {
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
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    if ($expected === '' || !hash_equals($expected, (string) ($body['csrf_token'] ?? ''))) {
        json_response(['error' => 'Invalid or expired session. Please refresh the page and try again.'], 403);
    }
}

/** Requires login for a JSON API endpoint; sends a 401 JSON error otherwise. */
function api_require_login(): array {
    $user = current_user();
    if (!$user) json_response(['error' => 'You must be logged in.'], 401);
    return $user;
}

// ---------------------------------------------------------------------
// Rate limiting. A small file-based limiter (no database table), kept under private-uploads/rate-limit/ — a
// folder the web server never serves. It slows password guessing, sign-up and reset-email floods, lead-form
// spam and payment-prompt abuse. If the folder can't be written, it fails open (nobody is locked out).
// A bucket + key pair (e.g. 'login-email' + the address) keeps a list of hit times inside its window.
// ---------------------------------------------------------------------
function rate_limit_file(string $bucket, string $key): string {
    return __DIR__ . '/../private-uploads/rate-limit/' . sha1($bucket . '|' . $key) . '.json';
}

/** @return int[] timestamps of this bucket+key inside the last $windowSeconds */
function rate_limit_hits(string $bucket, string $key, int $windowSeconds): array {
    $file = rate_limit_file($bucket, $key);
    if (!is_file($file)) return [];
    $list = json_decode((string) @file_get_contents($file), true);
    if (!is_array($list)) return [];
    $cut = time() - $windowSeconds;
    return array_values(array_filter($list, fn($t) => is_int($t) && $t > $cut));
}

/** True once this bucket+key has already used up $max hits inside the window. Does not count a hit. */
function rate_limited(string $bucket, string $key, int $max, int $windowSeconds): bool {
    return count(rate_limit_hits($bucket, $key, $windowSeconds)) >= $max;
}

/** Counts one hit against this bucket+key. */
function rate_limit_record(string $bucket, string $key, int $windowSeconds): void {
    $file = rate_limit_file($bucket, $key);
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return;
    $fp = @fopen($file, 'c+');
    if (!$fp) return;
    if (flock($fp, LOCK_EX)) {
        $list = json_decode((string) stream_get_contents($fp), true);
        $cut = time() - $windowSeconds;
        $list = array_values(array_filter(is_array($list) ? $list : [], fn($t) => is_int($t) && $t > $cut));
        $list[] = time();
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($list));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    if (random_int(1, 300) === 1) { // now and then, drop files nobody has touched for two days
        foreach (glob($dir . '/*.json') ?: [] as $old) if (filemtime($old) < time() - 172800) @unlink($old);
    }
}

/** Counts a hit and returns true while the bucket+key is still inside its limit; false once it is over. */
function rate_limit_take(string $bucket, string $key, int $max, int $windowSeconds): bool {
    if (rate_limited($bucket, $key, $max, $windowSeconds)) return false;
    rate_limit_record($bucket, $key, $windowSeconds);
    return true;
}
