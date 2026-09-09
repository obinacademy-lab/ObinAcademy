<?php
require_once __DIR__ . '/email.php';

const AFFILIATE_COOKIE = 'oa_aff';

/**
 * Affiliate status is independent of role (see schema.sql's comment on
 * `affiliates`) — a learner applies via becoming-affiliate.php, an admin
 * approves/rejects exactly like creator_applications, and approval creates
 * one `affiliates` row immediately, ref_code included, per the program's
 * "link ready immediately" requirement. Nothing here touches users.role.
 */

function get_affiliate_by_user_id(int $userId): ?array {
    return db_one('SELECT * FROM affiliates WHERE user_id = ?', [$userId]);
}

function get_affiliate_by_ref_code(string $refCode): ?array {
    return db_one("SELECT * FROM affiliates WHERE ref_code = ? AND status = 'ACTIVE'", [$refCode]);
}

/** Like require_role(), but for affiliate status rather than users.role — redirects to the application page if the learner isn't an approved, active affiliate. */
function require_affiliate(): array {
    $user = require_login();
    $affiliate = get_affiliate_by_user_id((int) $user['id']);
    if (!$affiliate || $affiliate['status'] !== 'ACTIVE') {
        redirect('/become-affiliate.php');
    }
    $user['affiliate'] = $affiliate;
    return $user;
}

/** 8 uppercase letters/digits, unambiguous set (no 0/O/1/I) — short enough to read out loud, unique per affiliate. */
function generate_affiliate_ref_code(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (db_one('SELECT id FROM affiliates WHERE ref_code = ?', [$code]));
    return $code;
}

/**
 * Approves a pending application: marks it APPROVED and creates the
 * `affiliates` row (with a fresh ref_code) in one transaction, so an
 * application can never end up APPROVED without a working affiliate link,
 * or vice versa. @return array the new affiliate row.
 */
function approve_affiliate_application(int $applicationId): array {
    $app = db_one('SELECT * FROM affiliate_applications WHERE id = ?', [$applicationId]);
    if (!$app || $app['status'] !== 'PENDING') {
        throw new RuntimeException('Application is not pending.');
    }

    $refCode = generate_affiliate_ref_code();
    db()->beginTransaction();
    try {
        db_run("UPDATE affiliate_applications SET status='APPROVED', reviewed_at=NOW() WHERE id=?", [$applicationId]);
        $affiliateId = db_insert('INSERT INTO affiliates (ref_code, user_id) VALUES (?, ?)', [$refCode, $app['user_id']]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    return db_one('SELECT * FROM affiliates WHERE id = ?', [$affiliateId]);
}

function reject_affiliate_application(int $applicationId, ?string $reason): void {
    db_run("UPDATE affiliate_applications SET status='REJECTED', reviewed_at=NOW(), rejection_reason=? WHERE id=? AND status='PENDING'", [$reason, $applicationId]);
}

/**
 * Reads ?aff=<ref_code> off the current request and, if it resolves to an
 * active affiliate, remembers it in a 30-day cookie — the one link an
 * affiliate shares works platform-wide (any page, any course), unlike
 * course_shares' per-course ?ref= tokens. Last-touch attribution: a newer
 * valid ?aff= link always overwrites whatever was stored before. Call once
 * per request, early, before any output — see includes/header.php.
 */
function ensure_affiliate_attribution_cookie(): void {
    $code = query_param('aff');
    if ($code === '') return;

    $affiliate = get_affiliate_by_ref_code($code);
    if (!$affiliate) return;

    setcookie(AFFILIATE_COOKIE, $affiliate['ref_code'], [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/**
 * The affiliate (if any) whose link this browser arrived through, for
 * crediting a purchase — resolved at checkout time in initiate_payment()
 * and stored on the payment row itself, not re-resolved later. Never
 * credits a buyer referring themselves.
 */
function resolve_affiliate_id_from_cookie(?int $buyerUserId): ?int {
    $code = $_COOKIE[AFFILIATE_COOKIE] ?? '';
    if ($code === '') return null;

    $affiliate = get_affiliate_by_ref_code($code);
    if (!$affiliate) return null;
    if ($buyerUserId !== null && (int) $affiliate['user_id'] === $buyerUserId) return null;

    return (int) $affiliate['id'];
}

/** @return array{earned:float, sales_count:int} */
function get_affiliate_summary(int $affiliateId): array {
    $row = db_one('SELECT COALESCE(SUM(amount),0) AS earned, COUNT(*) AS n FROM affiliate_earnings WHERE affiliate_id = ?', [$affiliateId]);
    return ['earned' => (float) ($row['earned'] ?? 0), 'sales_count' => (int) ($row['n'] ?? 0)];
}

function get_affiliate_recent_earnings(int $affiliateId, int $limit = 20): array {
    $limit = max(1, min(100, $limit));
    return db_all(
        "SELECT ae.*, c.title AS course_title
         FROM affiliate_earnings ae
         JOIN courses c ON c.id = ae.course_id
         WHERE ae.affiliate_id = ? ORDER BY ae.created_at DESC LIMIT $limit",
        [$affiliateId]
    );
}
