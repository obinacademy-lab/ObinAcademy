<?php

/** Returns the logged-in user's row, or null. Cached for the request. */
function current_user(): ?array {
    static $user = null;
    static $loaded = false;
    if ($loaded) return $user;
    $loaded = true;

    if (empty($_SESSION['user_id'])) return null;
    $user = db_one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
    return $user;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect('/login.php?redirect=' . $redirect);
    }
    return $user;
}

/** @param string[] $roles */
function require_role(array $roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        redirect('/dashboard.php');
    }
    return $user;
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    log_login($user);
}

/** Records a sign-in for the admin "Login Activity" dashboard. Device/browser/
 * OS are parsed immediately (cheap, no network call); country/city are left
 * null and resolved later by the same cron geo sweep that backfills
 * visitor_sessions, never on the request path. */
function log_login(array $user): void {
    $ua = parse_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '');
    db_insert(
        'INSERT INTO login_log (user_id, role, device_type, browser, os, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
        [$user['id'], $user['role'], $ua['device_type'], $ua['browser'], $ua['os'], get_client_ip()]
    );
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}
