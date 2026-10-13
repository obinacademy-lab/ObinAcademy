<?php
/**
 * Campuses: year-long programmes taught by professors Obin Academy appoints and pays itself.
 *
 * A learner joins a campus by paying for it, monthly (30 days of access) or yearly (12 months).
 * Mobile money can't be charged again without the learner approving a new prompt, so nothing
 * renews by itself: access is simply open while campus_memberships.access_until is in the future,
 * and reminders (email and an in-app notice, from 7 days before) ask the learner to pay again.
 *
 * The year starts the day a learner first joins (started_at). One stage opens per month, so
 * stage N is available from the start of month N; the certificate is issued once every lesson of
 * a fully built twelve-month path is complete.
 *
 * Campus money goes to the platform (no creator earnings rows). What the platform owes each
 * professor is tracked in campus_payouts, for the admin's own records.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/iotec.php';
require_once __DIR__ . '/user_notifications.php';

const CAMPUS_MONTH_DAYS = 30;
const CAMPUS_YEAR_MONTHS = 12;
const CAMPUS_REMINDER_STEPS = [7, 3, 1, 0];

const CAMPUS_ICON_PATHS = [
    'biz' => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/>',
    'crypto' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 6-7"/>',
    'fx' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
    'content' => '<rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 10 5-3v10l-5-3"/>',
    'fit' => '<path d="M6 7v10M18 7v10M3 10v4M21 10v4M6 12h12"/>',
    'ai' => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
    'shop' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.4a1.5 1.5 0 0 0 1.5 1.1h8.8a1.5 1.5 0 0 0 1.5-1.2L21 8H6"/>',
    'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/>',
];

function campus_icon(string $key, float $stroke = 1.6): string {
    $paths = CAMPUS_ICON_PATHS[$key] ?? CAMPUS_ICON_PATHS['book'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
}

/** True once the campus tables exist (migration/add-campuses.sql applied). */
function campuses_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT 1 FROM campuses LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

function get_published_campuses(): array {
    if (!campuses_ready()) return [];
    return db_all("SELECT * FROM campuses WHERE status = 'PUBLISHED' ORDER BY sort_order, name");
}

function get_all_campuses(): array {
    if (!campuses_ready()) return [];
    return db_all('SELECT * FROM campuses ORDER BY sort_order, name');
}

function get_campus(int $id): ?array {
    return db_one('SELECT * FROM campuses WHERE id = ?', [$id]);
}

function get_campus_by_slug(string $slug): ?array {
    if (!campuses_ready()) return null;
    return db_one('SELECT * FROM campuses WHERE slug = ?', [$slug]);
}

/** All twelve months for a campus, filled in with empty titles where the admin hasn't written one yet. */
function get_campus_stages(int $campusId): array {
    $rows = db_all('SELECT * FROM campus_stages WHERE campus_id = ? ORDER BY month_no', [$campusId]);
    $out = [];
    for ($m = 1; $m <= CAMPUS_YEAR_MONTHS; $m++) $out[$m] = ['month_no' => $m, 'title' => '', 'summary' => ''];
    foreach ($rows as $r) $out[(int) $r['month_no']] = $r;
    return $out;
}

function campus_curriculum_written(array $stages): bool {
    foreach ($stages as $s) if (trim((string) $s['title']) === '') return false;
    return true;
}

/** Lessons grouped by month: [month_no => [lesson, ...]]. */
function get_campus_lessons_by_month(int $campusId): array {
    $out = [];
    foreach (db_all('SELECT * FROM campus_lessons WHERE campus_id = ? ORDER BY month_no, sort_order, id', [$campusId]) as $l) {
        $out[(int) $l['month_no']][] = $l;
    }
    return $out;
}

function get_campus_lesson(int $lessonId): ?array {
    return db_one('SELECT l.*, c.slug AS campus_slug, c.name AS campus_name FROM campus_lessons l JOIN campuses c ON c.id = l.campus_id WHERE l.id = ?', [$lessonId]);
}

// ---------------------------------------------------------------- membership + access

function get_campus_membership(int $campusId, int $userId): ?array {
    return db_one('SELECT * FROM campus_memberships WHERE campus_id = ? AND user_id = ?', [$campusId, $userId]);
}

function campus_membership_open(?array $m): bool {
    return $m !== null && strtotime($m['access_until']) > time();
}

/** Month of the learner's year they are in now, 1 to 12. */
function campus_current_month(array $m): int {
    $start = new DateTimeImmutable($m['started_at']);
    $diff = $start->diff(new DateTimeImmutable('now'));
    return max(1, min(CAMPUS_YEAR_MONTHS, $diff->y * 12 + $diff->m + 1));
}

function campus_days_left(array $m): int {
    return (int) ceil((strtotime($m['access_until']) - time()) / 86400);
}

/** Lesson ids this user has completed in a campus. */
function campus_completed_lesson_ids(int $userId, int $campusId): array {
    $rows = db_all('SELECT p.lesson_id FROM campus_lesson_progress p JOIN campus_lessons l ON l.id = p.lesson_id WHERE p.user_id = ? AND l.campus_id = ?', [$userId, $campusId]);
    return array_map('intval', array_column($rows, 'lesson_id'));
}

/** Can this user open this lesson right now? Admins always can (to check uploads). */
function campus_can_open_lesson(?array $user, array $lesson): bool {
    if (!$user) return false;
    if ($user['role'] === 'ADMIN') return true;
    $m = get_campus_membership((int) $lesson['campus_id'], (int) $user['id']);
    return campus_membership_open($m) && (int) $lesson['month_no'] <= campus_current_month($m);
}

function mark_campus_lesson_done(int $userId, int $lessonId): void {
    db_run('INSERT IGNORE INTO campus_lesson_progress (user_id, lesson_id) VALUES (?, ?)', [$userId, $lessonId]);
}

/**
 * Issues the campus certificate once every lesson of a complete path (lessons in all twelve months)
 * is done. Safe to call repeatedly. Returns the code, or null if not yet earned.
 */
function campus_issue_certificate_if_earned(int $campusId, int $userId): ?string {
    $m = get_campus_membership($campusId, $userId);
    if (!$m) return null;
    if (!empty($m['certificate_code'])) return $m['certificate_code'];
    $byMonth = get_campus_lessons_by_month($campusId);
    if (count($byMonth) < CAMPUS_YEAR_MONTHS) return null; // path not fully built yet
    $total = array_sum(array_map('count', $byMonth));
    if ($total === 0 || count(campus_completed_lesson_ids($userId, $campusId)) < $total) return null;

    $code = 'OAC-' . strtoupper(bin2hex(random_bytes(4)));
    $claimed = db_run('UPDATE campus_memberships SET certificate_code = ?, certificate_issued_at = NOW() WHERE id = ? AND certificate_code IS NULL', [$code, $m['id']]);
    if (!$claimed) return get_campus_membership($campusId, $userId)['certificate_code'] ?? null;

    $user = db_one('SELECT name, email FROM users WHERE id = ?', [$userId]);
    $campus = get_campus($campusId);
    $url = base_url('campus-certificate.php?code=' . $code);
    if ($user && $user['email']) {
        resend_send($user['email'], 'Your ' . $campus['name'] . ' Campus certificate', campus_email_html(
            'Congratulations, ' . campus_first_name($user['name']),
            'You have completed the full year of the ' . e($campus['name']) . ' Campus. Your certificate is ready.',
            'View your certificate', $url
        ));
    }
    create_user_notification($userId, null, 'campus_certificate', 'Your ' . $campus['name'] . ' Campus certificate is ready.', $url);
    return $code;
}

function get_campus_certificate(string $code): ?array {
    if (!campuses_ready()) return null;
    return db_one(
        'SELECT m.*, c.name AS campus_name, c.slug AS campus_slug, c.professor_name, u.name AS learner_name
         FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id JOIN users u ON u.id = m.user_id
         WHERE m.certificate_code = ?',
        [$code]
    );
}

// ---------------------------------------------------------------- payment

function campus_plan_price(array $campus, string $plan): float {
    return (float) ($plan === 'YEARLY' ? $campus['yearly_price'] : $campus['monthly_price']);
}

/**
 * Starts a mobile-money collection to join or renew a campus. Logged-in learners only, so the
 * membership, reminders and progress have an account to live on.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_campus_payment(int $userId, int $campusId, string $plan, string $phone): array {
    if (!in_array($plan, ['MONTHLY', 'YEARLY'], true)) return ['error' => 'Choose monthly or yearly.'];
    if (strlen($phone) < 9 || !preg_match('/^[0-9+\s-]+$/', $phone)) return ['error' => 'Enter a valid phone number.'];
    $campus = get_campus($campusId);
    if (!$campus || $campus['status'] !== 'PUBLISHED') return ['error' => 'This campus is not open yet.'];
    $amount = campus_plan_price($campus, $plan);
    if ($amount <= 0) return ['error' => 'This campus has no price set yet.'];

    // A collection already waiting on this phone: check it before starting another.
    $pending = db_one(
        "SELECT id FROM payments WHERE user_id = ? AND campus_id = ? AND type = 'CAMPUS_MEMBERSHIP' AND status = 'PENDING' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY id DESC LIMIT 1",
        [$userId, $campusId]
    );
    if ($pending) {
        $resolved = resolve_payment_with_iotec(fetch_payment_with_campus((int) $pending['id']));
        if ($resolved['status'] === 'PENDING') return ['paymentId' => (int) $pending['id']];
    }

    $paymentId = db_insert(
        "INSERT INTO payments (user_id, campus_id, campus_plan, amount, phone, type, status) VALUES (?, ?, ?, ?, ?, 'CAMPUS_MEMBERSHIP', 'PENDING')",
        [$userId, $campusId, $plan, $amount, $phone]
    );
    try {
        $note = substr('Obin Academy - ' . $campus['name'] . ' Campus (' . ($plan === 'YEARLY' ? '12 months' : '30 days') . ')', 0, 100);
        $result = iotec_initiate_collection($amount, $phone, (string) $paymentId, $note);
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for campus payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }
    return ['paymentId' => $paymentId];
}

function fetch_payment_with_campus(int $paymentId): ?array {
    return db_one(
        'SELECT p.*, c.name AS campus_name, c.slug AS campus_slug, u.name AS learner_name, u.email AS learner_email
         FROM payments p JOIN campuses c ON c.id = p.campus_id LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$paymentId]
    );
}

/**
 * Opens or extends the membership for a successful campus payment. Paying before access runs out
 * adds the new period on top of what is left, so an early renewal never loses days. Called only
 * from resolve_payment_with_iotec(), after it has atomically claimed the payment.
 */
function apply_campus_payment_success(array $payment): void {
    $campusId = (int) $payment['campus_id'];
    $userId = (int) $payment['user_id'];
    $plan = $payment['campus_plan'] === 'YEARLY' ? 'YEARLY' : 'MONTHLY';
    $period = $plan === 'YEARLY' ? '+12 months' : '+' . CAMPUS_MONTH_DAYS . ' days';

    $m = get_campus_membership($campusId, $userId);
    if ($m) {
        $from = max(time(), strtotime($m['access_until']));
        $until = date('Y-m-d H:i:s', strtotime($period, $from));
        db_run(
            'UPDATE campus_memberships SET plan = ?, access_until = ?, last_payment_id = ?, last_reminder_days = NULL, last_reminder_at = NULL, reminders_off = 0 WHERE id = ?',
            [$plan, $until, $payment['id'], $m['id']]
        );
    } else {
        $until = date('Y-m-d H:i:s', strtotime($period));
        db_insert(
            'INSERT INTO campus_memberships (campus_id, user_id, plan, started_at, access_until, last_payment_id) VALUES (?, ?, ?, NOW(), ?, ?)',
            [$campusId, $userId, $plan, $until, $payment['id']]
        );
    }

    $learnUrl = base_url('campus-learn.php?campus=' . $payment['campus_slug']);
    if (!empty($payment['learner_email'])) {
        resend_send($payment['learner_email'], 'Welcome to the ' . $payment['campus_name'] . ' Campus', campus_email_html(
            'You are in, ' . campus_first_name((string) $payment['learner_name']),
            'We received ' . e(format_money((float) $payment['amount'])) . ' for the ' . e($payment['campus_name']) . ' Campus. Your access is open until <strong>'
                . e(date('j M Y', strtotime($until))) . '</strong>.',
            'Go to your campus', $learnUrl
        ));
    }
    create_user_notification($userId, null, 'campus_joined', 'Your ' . $payment['campus_name'] . ' Campus access is open until ' . date('j M Y', strtotime($until)) . '.', $learnUrl);
    try {
        send_admin_sale_notification_email($payment['campus_name'] . ' Campus', $plan === 'YEARLY' ? 'Campus, yearly' : 'Campus, monthly', (string) ($payment['learner_name'] ?: 'A member'), (float) $payment['amount'], 'Obin Academy');
    } catch (Throwable $e) {}
}

// ---------------------------------------------------------------- reminders

/**
 * Reminds members whose access ends soon: 7, 3 and 1 days before and on the day, then once more
 * just after it ends. Each step is sent once per period (reset when they pay again). Email plus an
 * in-app notice. Called from cron/track-maintenance.php; returns how many reminders went out.
 */
function send_due_campus_reminders(): int {
    if (!campuses_ready()) return 0;
    $rows = db_all(
        "SELECT m.*, c.name AS campus_name, c.slug AS campus_slug, c.monthly_price, c.yearly_price, u.name AS user_name, u.email AS user_email
         FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id JOIN users u ON u.id = m.user_id
         WHERE m.reminders_off = 0
           AND m.access_until BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND DATE_ADD(NOW(), INTERVAL 8 DAY)"
    );
    $sent = 0;
    foreach ($rows as $m) {
        $daysLeft = (int) ceil((strtotime($m['access_until']) - time()) / 86400);
        $step = $daysLeft < 0 ? -1 : null;
        if ($step === null) {
            foreach (array_reverse(CAMPUS_REMINDER_STEPS) as $s) { if ($daysLeft <= $s) { $step = $s; break; } }
        }
        if ($step === null) continue;
        if ($m['last_reminder_days'] !== null && (int) $m['last_reminder_days'] <= $step) continue;

        $url = base_url('campus.php?slug=' . $m['campus_slug'] . '#join');
        $first = campus_first_name($m['user_name']);
        $when = date('j M', strtotime($m['access_until']));
        if ($step === -1) {
            $subject = 'Your ' . $m['campus_name'] . ' Campus access has ended';
            $line = 'Your access ended on ' . $when . '. Pay again to reopen your lessons, live classes and community. Your progress is kept.';
        } elseif ($step === 0) {
            $subject = 'Your ' . $m['campus_name'] . ' Campus access ends today';
            $line = 'Your access ends today. Renew now so you don\'t miss this week\'s live class.';
        } else {
            $subject = 'Your ' . $m['campus_name'] . ' Campus access ends in ' . $step . ' day' . ($step === 1 ? '' : 's');
            $line = 'Your access ends on ' . $when . '. Renew before then to keep your lessons, live classes and community.';
        }
        if ($m['user_email']) {
            resend_send($m['user_email'], $subject, campus_email_html('Hi ' . $first, e($line), 'Renew for ' . format_money((float) $m['monthly_price']), $url));
        }
        create_user_notification((int) $m['user_id'], null, 'campus_renewal', $subject . '.', $url);
        db_run('UPDATE campus_memberships SET last_reminder_days = ?, last_reminder_at = NOW() WHERE id = ?', [$step, $m['id']]);
        $sent++;
    }
    return $sent;
}

function campus_first_name(string $name): string {
    return trim(explode(' ', trim($name))[0] ?? '') ?: 'there';
}

/** A plain branded email body. $bodyHtml must already be escaped. */
function campus_email_html(string $heading, string $bodyHtml, string $cta, string $url): string {
    $h = e($heading); $c = e($cta); $u = e($url);
    return <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; color: #0e1124; line-height: 1.55;">
          <h2 style="color: #0a0f2c; font-family: Georgia, serif; font-weight: 500;">{$h}</h2>
          <p>{$bodyHtml}</p>
          <p style="margin-top: 22px;"><a href="{$u}" style="display: inline-block; background: #0a0f2c; color: #fff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 600;">{$c}</a></p>
          <p style="color: #6c7086; font-size: 12px; margin-top: 26px;">Obin Academy Campuses</p>
        </div>
        HTML;
}

// ---------------------------------------------------------------- community

function get_campus_posts(int $campusId, int $limit = 40, int $afterId = 0): array {
    return db_all(
        'SELECT p.id, p.body, p.created_at, p.user_id, u.name, u.role FROM campus_posts p JOIN users u ON u.id = p.user_id
         WHERE p.campus_id = ? AND p.deleted_at IS NULL AND p.id > ? ORDER BY p.id DESC LIMIT ' . (int) $limit,
        [$campusId, $afterId]
    );
}

// ---------------------------------------------------------------- admin numbers

/** Per-campus and overall figures for the admin dashboard. */
function campus_admin_overview(): array {
    $campuses = get_all_campuses();
    $per = [];
    foreach ($campuses as $c) {
        $per[(int) $c['id']] = $c + ['active' => 0, 'monthly' => 0, 'yearly' => 0, 'ending' => 0, 'collected' => 0.0];
    }
    foreach (db_all(
        "SELECT campus_id,
                SUM(access_until > NOW()) AS active,
                SUM(access_until > NOW() AND plan = 'MONTHLY') AS monthly,
                SUM(access_until > NOW() AND plan = 'YEARLY') AS yearly,
                SUM(access_until > NOW() AND access_until <= DATE_ADD(NOW(), INTERVAL 7 DAY)) AS ending
         FROM campus_memberships GROUP BY campus_id"
    ) as $r) {
        if (isset($per[(int) $r['campus_id']])) $per[(int) $r['campus_id']] = array_merge($per[(int) $r['campus_id']], array_map('intval', array_intersect_key($r, array_flip(['active', 'monthly', 'yearly', 'ending']))));
    }
    foreach (db_all("SELECT campus_id, SUM(amount) AS s FROM payments WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY campus_id") as $r) {
        if (isset($per[(int) $r['campus_id']])) $per[(int) $r['campus_id']]['collected'] = (float) $r['s'];
    }
    $prev = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")['s'] ?? 0);
    $new30 = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['n'] ?? 0);
    $certs = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE certificate_code IS NOT NULL')['n'] ?? 0);

    // Collected per calendar month, last 12 months, split by plan.
    $series = [];
    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("first day of -$i month"));
        $series[$key] = ['label' => date('M', strtotime($key . '-01')), 'MONTHLY' => 0.0, 'YEARLY' => 0.0];
    }
    foreach (db_all(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, campus_plan, SUM(amount) AS s FROM payments
         WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 11 MONTH)
         GROUP BY ym, campus_plan"
    ) as $r) {
        if (isset($series[$r['ym']])) $series[$r['ym']][$r['campus_plan'] === 'YEARLY' ? 'YEARLY' : 'MONTHLY'] += (float) $r['s'];
    }

    return [
        'campuses' => array_values($per),
        'collected' => array_sum(array_column($per, 'collected')),
        'collected_prev' => $prev,
        'active' => array_sum(array_column($per, 'active')),
        'ending' => array_sum(array_column($per, 'ending')),
        'new30' => $new30,
        'certificates' => $certs,
        'series' => array_values($series),
    ];
}

/** Members whose access ends within $days (or ended in the last 3 days), soonest first. */
function campus_renewals_due(int $days = 7, int $limit = 50): array {
    return db_all(
        'SELECT m.*, c.name AS campus_name, c.slug AS campus_slug, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
         FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id JOIN users u ON u.id = m.user_id
         WHERE m.access_until BETWEEN DATE_SUB(NOW(), INTERVAL 3 DAY) AND DATE_ADD(NOW(), INTERVAL ? DAY)
         ORDER BY m.access_until ASC LIMIT ' . (int) $limit,
        [$days]
    );
}

function upcoming_campus_live_classes(int $days = 7, ?int $campusId = null): array {
    $sql = 'SELECT l.*, c.name AS campus_name, c.slug AS campus_slug FROM campus_live_classes l JOIN campuses c ON c.id = l.campus_id
            WHERE l.starts_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR) AND l.starts_at <= DATE_ADD(NOW(), INTERVAL ? DAY)';
    $params = [$days];
    if ($campusId) { $sql .= ' AND l.campus_id = ?'; $params[] = $campusId; }
    return db_all($sql . ' ORDER BY l.starts_at ASC', $params);
}
