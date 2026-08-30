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
    ];
    if (!isset($paths[$name])) return;
    echo '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>';
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
