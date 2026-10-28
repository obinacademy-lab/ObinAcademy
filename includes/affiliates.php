<?php
/**
 * Student referrals. Every student has a referral link (users.ref_code, assigned the first time they open
 * their "My Referrals" page or the moment their first referral needs crediting). Anyone who creates a brand
 * new account through that link is permanently linked to the referrer (users.referred_by_user_id, set once
 * at signup — see attribute_referral_at_signup(), called from signup.php — and never changed afterwards,
 * including by a later visit through a different link). From then on, every successful campus payment that
 * referred student ever makes, on any campus, earns the referrer AFFILIATE_COMMISSION_PERCENT of it — see
 * record_affiliate_earning(), called from campus_payment_success() the moment a payment lands. This keeps
 * paying out for as long as the referred student keeps paying: there is no expiry window on the
 * relationship itself, unlike the short-lived cookie that only decides attribution at signup time.
 *
 * Payouts mirror campus professor pay exactly (see "payment requests" in includes/campuses.php): earnings
 * build up, the affiliate requests everything owed from their dashboard, the admin sends it to their
 * mobile money and records it on Admin, Affiliate Payouts, which settles the request.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/user_notifications.php';

const AFFILIATE_COMMISSION_PERCENT = 10.0;
const AFFILIATE_REF_COOKIE = 'oa_ref';
const AFFILIATE_PAYOUT_METHODS = ['MOBILE_MONEY' => 'Mobile money', 'BANK' => 'Bank transfer', 'CASH' => 'Cash'];

/** True once migration/add-affiliate-program.sql has been applied. */
function affiliates_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT ref_code, referred_by_user_id FROM users LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/** A short, shareable code — letters and digits only, no 0/O/1/I so a spoken or handwritten code never gets misread. */
function generate_ref_code(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 7; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $code;
}

/** This user's referral code, assigning one on first use if they don't have one yet. */
function ensure_user_ref_code(array $user): string {
    if (!empty($user['ref_code'])) return $user['ref_code'];
    if (!affiliates_ready()) return '';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = generate_ref_code();
        if (db_run('UPDATE users SET ref_code = ? WHERE id = ? AND ref_code IS NULL', [$code, $user['id']])) return $code;
        $existing = db_one('SELECT ref_code FROM users WHERE id = ?', [$user['id']])['ref_code'] ?? null;
        if ($existing) return $existing; // someone else's concurrent request already set one
    }
    return '';
}

function referral_link(string $refCode): string {
    return base_url('index.php?ref=' . $refCode);
}

/**
 * Call on every page load (header.php and auth_header.php). If the visitor arrived with ?ref=CODE and it
 * belongs to a real account, remembers it for 30 days so a signup minutes or days later still attributes
 * correctly — this window only decides whether a SIGNUP gets attributed, not how long the resulting
 * commission lasts (that part is permanent, see the file header above).
 */
function ensure_referral_cookie(): void {
    if (!affiliates_ready()) return;
    $code = strtoupper(trim((string) ($_GET['ref'] ?? '')));
    if ($code === '' || !preg_match('/^[A-Z0-9]{4,12}$/', $code)) return;
    if (!db_one('SELECT id FROM users WHERE ref_code = ?', [$code])) return;
    setcookie(AFFILIATE_REF_COOKIE, $code, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

/**
 * Called once, right after a brand new account is created (signup.php). Permanently links the account to
 * whoever's referral cookie is present, unless that cookie is missing, invalid, or points at the new
 * account itself. Silent no-op otherwise — signup never fails over this.
 */
function attribute_referral_at_signup(int $newUserId): void {
    if (!affiliates_ready()) return;
    $code = strtoupper(trim((string) ($_COOKIE[AFFILIATE_REF_COOKIE] ?? '')));
    if ($code === '') return;
    $referrer = db_one('SELECT id FROM users WHERE ref_code = ?', [$code]);
    if (!$referrer || (int) $referrer['id'] === $newUserId) return;
    db_run('UPDATE users SET referred_by_user_id = ? WHERE id = ? AND referred_by_user_id IS NULL', [$referrer['id'], $newUserId]);
}

/**
 * Records the referrer's 10% of one successful campus payment, once (payment_id is unique), and tells
 * them. Called from campus_payment_success() in includes/campuses.php. A payment from a student nobody
 * referred (the normal case) is a silent no-op.
 */
function record_affiliate_earning(array $payment): void {
    if (!affiliates_ready()) return;
    $buyer = db_one('SELECT id, name, referred_by_user_id FROM users WHERE id = ?', [(int) $payment['user_id']]);
    if (!$buyer || empty($buyer['referred_by_user_id'])) return;
    $affiliateId = (int) $buyer['referred_by_user_id'];

    $gross = (float) $payment['amount'];
    $amount = round($gross * AFFILIATE_COMMISSION_PERCENT / 100, 2);
    $plan = ($payment['campus_plan'] ?? null) === 'YEARLY' ? 'YEARLY' : 'MONTHLY';
    $added = db_run(
        'INSERT IGNORE INTO campus_affiliate_earnings (affiliate_user_id, referred_user_id, payment_id, campus_id, campus_name, plan, gross, share_percent, amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$affiliateId, $buyer['id'], $payment['id'], $payment['campus_id'] ?? null, $payment['campus_name'] ?? null, $plan, $gross, AFFILIATE_COMMISSION_PERCENT, $amount]
    );
    if (!$added || $amount <= 0) return;

    $url = base_url('dashboard/learner/affiliate.php');
    create_user_notification($affiliateId, null, 'affiliate_earned',
        e($buyer['name']) . ' just paid for the ' . e((string) ($payment['campus_name'] ?? 'a')) . ' Campus — you earned ' . format_money($amount) . '.', $url);
    $affiliate = db_one('SELECT name, email FROM users WHERE id = ?', [$affiliateId]);
    if ($affiliate && $affiliate['email']) {
        try {
            resend_send($affiliate['email'], 'You earned ' . format_money($amount) . ' from your referral', campus_email_html(
                'Nice one, ' . campus_first_name($affiliate['name']),
                e($buyer['name']) . ' just paid ' . e(format_money($gross)) . ' for the ' . e((string) ($payment['campus_name'] ?? '')) . ' Campus through your link. '
                    . 'Your ' . e(format_share(AFFILIATE_COMMISSION_PERCENT)) . ' — ' . e(format_money($amount)) . ' — has been added to your balance, and will be again every time they renew.',
                'See your referrals', $url
            ));
        } catch (Throwable $e) {}
    }
}

/** One affiliate's totals: earned (all-time / last 30 days), owed now, paid to date, how many distinct people they've referred. */
function affiliate_pay_summary(int $userId): array {
    $out = ['earned' => 0.0, 'earned30' => 0.0, 'owed' => 0.0, 'owed_count' => 0, 'payments' => 0, 'paid' => 0.0, 'last_paid_at' => null, 'referred_count' => 0, 'referred_paying_count' => 0];
    if (!affiliates_ready()) return $out;
    $e = db_one(
        "SELECT SUM(amount) AS earned, SUM(IF(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY), amount, 0)) AS earned30,
                SUM(IF(payout_id IS NULL, amount, 0)) AS owed, SUM(payout_id IS NULL) AS owed_count, COUNT(*) AS payments,
                COUNT(DISTINCT referred_user_id) AS referred_paying_count
         FROM campus_affiliate_earnings WHERE affiliate_user_id = ?",
        [$userId]
    );
    if ($e && $e['payments']) {
        $out['earned'] = (float) $e['earned']; $out['earned30'] = (float) $e['earned30'];
        $out['owed'] = (float) $e['owed']; $out['owed_count'] = (int) $e['owed_count']; $out['payments'] = (int) $e['payments'];
        $out['referred_paying_count'] = (int) $e['referred_paying_count'];
    }
    $p = db_one('SELECT SUM(amount) AS paid, MAX(paid_at) AS last_paid_at FROM campus_affiliate_payouts WHERE affiliate_user_id = ?', [$userId]);
    if ($p && $p['paid']) { $out['paid'] = (float) $p['paid']; $out['last_paid_at'] = $p['last_paid_at']; }
    $out['referred_count'] = (int) (db_one('SELECT COUNT(*) AS n FROM users WHERE referred_by_user_id = ?', [$userId])['n'] ?? 0);
    return $out;
}

/** One affiliate's earnings ledger, newest first, with who paid and when it was settled. */
function get_affiliate_earnings(int $userId, int $limit = 100): array {
    if (!affiliates_ready()) return [];
    return db_all(
        'SELECT e.*, u.name AS referred_name, po.paid_at AS payout_paid_at
         FROM campus_affiliate_earnings e JOIN users u ON u.id = e.referred_user_id LEFT JOIN campus_affiliate_payouts po ON po.id = e.payout_id
         WHERE e.affiliate_user_id = ? ORDER BY e.created_at DESC, e.id DESC LIMIT ' . (int) $limit,
        [$userId]
    );
}

function get_affiliate_payouts(int $userId, int $limit = 50): array {
    if (!affiliates_ready()) return [];
    return db_all('SELECT * FROM campus_affiliate_payouts WHERE affiliate_user_id = ? ORDER BY paid_at DESC, id DESC LIMIT ' . (int) $limit, [$userId]);
}

/**
 * Records money sent to an affiliate: settles every one of their unpaid earnings in one payout row, inside
 * a locking transaction so two overlapping "Pay" clicks (or one followed by the request-settling call)
 * can never pay the same earning twice. Returns null when there is nothing owed.
 */
function record_affiliate_payout(int $affiliateUserId, string $method, string $sentTo, string $reference, string $note, int $adminId): ?array {
    if (!affiliates_ready()) return null;
    if (!isset(AFFILIATE_PAYOUT_METHODS[$method])) $method = 'MOBILE_MONEY';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $rows = db_all('SELECT id, amount FROM campus_affiliate_earnings WHERE affiliate_user_id = ? AND payout_id IS NULL FOR UPDATE', [$affiliateUserId]);
        $total = round(array_sum(array_map(fn($r) => (float) $r['amount'], $rows)), 2);
        if (!$rows || $total <= 0) { $pdo->rollBack(); return null; }
        $payoutId = db_insert(
            'INSERT INTO campus_affiliate_payouts (affiliate_user_id, amount, method, sent_to, reference, note, paid_by) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$affiliateUserId, $total, $method, mb_substr($sentTo, 0, 120) ?: null, mb_substr($reference, 0, 120) ?: null, mb_substr($note, 0, 255) ?: null, $adminId]
        );
        $ids = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
        db_run("UPDATE campus_affiliate_earnings SET payout_id = ? WHERE id IN ($ids)", [$payoutId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    affiliate_payout_request_mark_paid($affiliateUserId, (int) $payoutId, $adminId);
    $affiliate = db_one('SELECT id, name, email FROM users WHERE id = ?', [$affiliateUserId]);
    if ($affiliate) {
        $url = base_url('dashboard/learner/affiliate.php');
        $line = format_money($total) . ' for ' . count($rows) . ' referral payment' . (count($rows) === 1 ? '' : 's')
            . ' was sent by ' . strtolower(AFFILIATE_PAYOUT_METHODS[$method]) . ($sentTo !== '' ? ' to ' . $sentTo : '') . ($reference !== '' ? ' (reference ' . $reference . ')' : '') . '.';
        create_user_notification((int) $affiliate['id'], null, 'affiliate_paid', 'Obin Academy paid you ' . format_money($total) . ' for your referrals.', $url);
        if ($affiliate['email']) {
            try { resend_send($affiliate['email'], 'Obin Academy paid you ' . format_money($total), campus_email_html('Payment sent, ' . campus_first_name($affiliate['name']), e($line), 'See your referrals', $url)); }
            catch (Throwable $e) {}
        }
    }
    return db_one('SELECT * FROM campus_affiliate_payouts WHERE id = ?', [$payoutId]);
}

// ---------------------------------------------------------------- payment requests from affiliates

/** The open request for this affiliate, if any (at most one at a time). */
function affiliate_payout_request_pending(int $userId): ?array {
    if (!affiliates_ready()) return null;
    return db_one("SELECT * FROM campus_affiliate_payout_requests WHERE affiliate_user_id = ? AND status = 'PENDING' ORDER BY id DESC LIMIT 1", [$userId]);
}

/** This affiliate's requests, newest first. */
function affiliate_payout_requests_for_user(int $userId, int $limit = 20): array {
    if (!affiliates_ready()) return [];
    return db_all('SELECT * FROM campus_affiliate_payout_requests WHERE affiliate_user_id = ? ORDER BY requested_at DESC, id DESC LIMIT ' . (int) $limit, [$userId]);
}

/** Every open request, oldest first, with what is owed to that affiliate right now (for Admin, Affiliate Payouts). */
function affiliate_payout_requests_open(): array {
    if (!affiliates_ready()) return [];
    $rows = db_all(
        "SELECT r.*, u.name AS affiliate_name, u.email AS affiliate_email
         FROM campus_affiliate_payout_requests r JOIN users u ON u.id = r.affiliate_user_id
         WHERE r.status = 'PENDING' ORDER BY r.requested_at ASC"
    );
    foreach ($rows as &$r) $r['owed_now'] = (float) (affiliate_pay_summary((int) $r['affiliate_user_id'])['owed'] ?? 0);
    return $rows;
}

/**
 * The affiliate asks to be paid everything owed to them now. Refused when nothing is owed, when a request
 * is already waiting, or without a usable mobile money number.
 * @throws RuntimeException with a message for the affiliate.
 */
function affiliate_payout_request_create(array $user, string $phone, string $payoutName, string $note): array {
    if (!affiliates_ready()) throw new RuntimeException('Referral payouts are being switched on. Try again shortly.');
    $owed = (float) (affiliate_pay_summary((int) $user['id'])['owed'] ?? 0);
    if ($owed <= 0) throw new RuntimeException('Nothing is owed to you right now. Your commission is added the moment someone you referred pays.');
    if (affiliate_payout_request_pending((int) $user['id'])) throw new RuntimeException('You already have a request waiting. Obin Academy will pay it soon.');
    $phone = trim($phone);
    if (strlen(preg_replace('/\D/', '', $phone)) < 9) throw new RuntimeException('Enter the mobile money number to send your money to, like 0772 123 456.');
    $id = db_insert(
        'INSERT INTO campus_affiliate_payout_requests (affiliate_user_id, amount, phone, payout_name, note) VALUES (?, ?, ?, ?, ?)',
        [$user['id'], $owed, mb_substr($phone, 0, 32), mb_substr(trim($payoutName), 0, 120) ?: null, mb_substr(trim($note), 0, 255) ?: null]
    );
    require_once __DIR__ . '/notifications.php';
    try { create_admin_notification('affiliate_payout_request', $user['name'] . ' asked to be paid ' . format_money($owed) . ' for their referrals.'); } catch (Throwable $e) {}
    return db_one('SELECT * FROM campus_affiliate_payout_requests WHERE id = ?', [$id]);
}

/** The affiliate withdraws their own waiting request. */
function affiliate_payout_request_cancel(int $userId): bool {
    if (!affiliates_ready()) return false;
    return (bool) db_run("UPDATE campus_affiliate_payout_requests SET status = 'CANCELLED', decided_at = NOW() WHERE affiliate_user_id = ? AND status = 'PENDING'", [$userId]);
}

/** The admin turns a request down, with a reason the affiliate sees. */
function affiliate_payout_request_decline(int $requestId, string $reason, int $adminId): ?array {
    if (!affiliates_ready()) return null;
    $r = db_one("SELECT * FROM campus_affiliate_payout_requests WHERE id = ? AND status = 'PENDING'", [$requestId]);
    if (!$r) return null;
    db_run("UPDATE campus_affiliate_payout_requests SET status = 'DECLINED', decline_reason = ?, decided_at = NOW(), decided_by = ? WHERE id = ?", [mb_substr(trim($reason), 0, 255), $adminId, $requestId]);
    create_user_notification((int) $r['affiliate_user_id'], null, 'affiliate_payout_declined',
        'Your payment request of ' . format_money((float) $r['amount']) . ' was not paid: ' . trim($reason), base_url('dashboard/learner/affiliate.php'));
    return $r;
}

/** Marks an affiliate's waiting request paid by a payout (called from record_affiliate_payout()). */
function affiliate_payout_request_mark_paid(int $affiliateUserId, int $payoutId, int $adminId): void {
    if (!affiliates_ready()) return;
    db_run("UPDATE campus_affiliate_payout_requests SET status = 'PAID', payout_id = ?, decided_at = NOW(), decided_by = ? WHERE affiliate_user_id = ? AND status = 'PENDING'", [$payoutId, $adminId, $affiliateUserId]);
}

/** Platform-wide totals for the admin page: open requests, all-time paid out, and the top affiliates by what they're owed now. */
function affiliate_admin_overview(int $limit = 20): array {
    if (!affiliates_ready()) return ['owed' => 0.0, 'paid' => 0.0, 'referrers' => 0, 'paying_referrals' => 0, 'top' => []];
    $e = db_one("SELECT SUM(IF(payout_id IS NULL, amount, 0)) AS owed, COUNT(DISTINCT affiliate_user_id) AS referrers, COUNT(DISTINCT referred_user_id) AS paying_referrals FROM campus_affiliate_earnings");
    $paid = (float) (db_one('SELECT SUM(amount) AS n FROM campus_affiliate_payouts')['n'] ?? 0);
    $top = db_all(
        "SELECT u.id, u.name, u.email, SUM(e.amount) AS earned, SUM(IF(e.payout_id IS NULL, e.amount, 0)) AS owed, COUNT(DISTINCT e.referred_user_id) AS referred_paying_count
         FROM campus_affiliate_earnings e JOIN users u ON u.id = e.affiliate_user_id
         GROUP BY u.id ORDER BY owed DESC, earned DESC LIMIT " . (int) $limit
    );
    return [
        'owed' => (float) ($e['owed'] ?? 0), 'paid' => $paid,
        'referrers' => (int) ($e['referrers'] ?? 0), 'paying_referrals' => (int) ($e['paying_referrals'] ?? 0),
        'top' => $top,
    ];
}
