<?php
/**
 * Campuses: year-long programmes taught by professors Obin Academy appoints and pays itself.
 *
 * A learner joins a campus by paying for it, monthly (30 days of access) or yearly (12 months).
 * Mobile money can't be charged again without the learner approving a new prompt, so nothing
 * renews by itself: access is simply open while campus_memberships.access_until is in the future,
 * and reminders (email and an in-app notice, from 7 days before) ask the learner to pay again.
 *
 * The whole campus moves through the twelve stages together, at the professor's pace: students can
 * open every stage up to the one the class is on (campus_open_month), and the professor marks that
 * stage complete to open the next for everyone (campus_content.php). The certificate is issued once
 * every lesson of a fully built twelve-month path is complete.
 *
 * Campus money goes to the platform (no creator earnings rows). Each campus professor earns a share
 * of every payment (campuses.professor_share_percent, 40% by default), recorded per payment in
 * campus_professor_earnings the moment it succeeds. The admin sends the money by mobile money or bank
 * and records it in campus_professor_payouts, which marks those earnings paid.
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

/** The campus photo URL (assets/img/campuses/{slug}.jpg), or null when that campus has none yet. */
function campus_photo(array $campus): ?string {
    $rel = 'assets/img/campuses/' . $campus['slug'] . '.jpg';
    // public/ locally, the web root itself on the live server.
    foreach ([__DIR__ . '/../public/' . $rel, __DIR__ . '/../' . $rel] as $path) {
        if (is_file($path)) return versioned_asset($rel);
    }
    return null;
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
    for ($m = 1; $m <= CAMPUS_YEAR_MONTHS; $m++) $out[$m] = ['month_no' => $m, 'title' => '', 'summary' => '', 'thumbnail_url' => null, 'completed_at' => null];
    foreach ($rows as $r) $out[(int) $r['month_no']] = $r + ['thumbnail_url' => null, 'completed_at' => null];
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

/** True once migration/add-month-completion.sql has been applied (professor-paced months, month pictures). */
function campus_progress_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT months_completed FROM campuses LIMIT 1'); db_one('SELECT thumbnail_url, completed_at FROM campus_stages LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/**
 * The month a campus's class is on, 1 to 12. The whole campus moves together: the professor marks a
 * month complete and the next one opens for every student (campus_content.php, 'month_complete').
 * Students can open every month up to this one, so someone who joins late can catch up.
 */
function campus_open_month(array $campus): int {
    return max(1, min(CAMPUS_YEAR_MONTHS, (int) ($campus['months_completed'] ?? 0) + 1));
}

/** Months the professor has completed, 0 to 12 (12 = the whole year is done). */
function campus_months_completed(array $campus): int {
    return max(0, min(CAMPUS_YEAR_MONTHS, (int) ($campus['months_completed'] ?? 0)));
}

// ---------------------------------------------------------------- groups by month

/** Minutes between a professor completing a month and it opening for the group (the silent undo window). */
const CAMPUS_MONTH_UNDO_MINUTES = 10;

/**
 * True once migration/add-month-groups.sql has been applied. From then on every student has their own
 * place in the year (campus_memberships.month_open): new students start at month 1, students on the
 * same month form a group, and the professor completes each group's month separately.
 */
function campus_groups_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT month_open, next_month_at FROM campus_memberships LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/**
 * The month a member can open now, 1 to 12. A completed month counts only once its undo window has
 * passed (next_month_at), so a month the professor undoes in time is never seen, and only if the
 * student's subscription was still active at that moment: no active subscription, no next month.
 */
function campus_member_month(array $m): int {
    $open = (int) ($m['month_open'] ?? 1);
    if (!empty($m['next_month_at']) && strtotime($m['next_month_at']) <= time() && (empty($m['access_until']) || strtotime($m['access_until']) >= strtotime($m['next_month_at']))) $open++;
    return max(1, min(CAMPUS_YEAR_MONTHS, $open));
}

/** The month a member can reach now. Takes a membership row; falls back to the whole-campus month before the groups migration. */
function campus_current_month(array $m): int {
    if (campus_groups_ready() && array_key_exists('month_open', $m)) return campus_member_month($m);
    if (campus_groups_ready() && !empty($m['campus_id']) && !empty($m['user_id'])) {
        $row = get_campus_membership((int) $m['campus_id'], (int) $m['user_id']);
        if ($row) return campus_member_month($row);
    }
    if (array_key_exists('months_completed', $m)) return campus_open_month($m);
    $campus = get_campus((int) ($m['campus_id'] ?? 0));
    return $campus ? campus_open_month($campus) : 1;
}

/**
 * Opens the months whose undo window has passed: moves those students on and tells them. Only students
 * whose subscription is active when the month opens move on. Safe to call
 * often (campus pages, the professor's dashboard, the cron job). Returns how many students moved on.
 */
function campus_release_due_months(?int $campusId = null): int {
    if (!campus_groups_ready()) return 0;
    $rows = db_all(
        'SELECT m.id, m.user_id, m.month_open, c.id AS campus_id, c.name, c.slug FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id
         WHERE m.next_month_at IS NOT NULL AND m.next_month_at <= NOW() AND m.access_until >= m.next_month_at' . ($campusId ? ' AND m.campus_id = ?' : ''),
        $campusId ? [$campusId] : []
    );
    // Students whose subscription ran out before their month opened stay where they are, quietly.
    db_run('UPDATE campus_memberships SET next_month_at = NULL WHERE next_month_at IS NOT NULL AND next_month_at <= NOW() AND access_until < next_month_at' . ($campusId ? ' AND campus_id = ?' : ''), $campusId ? [$campusId] : []);
    $moved = 0;
    $titles = [];
    foreach ($rows as $r) {
        // Claim the row so two requests never move a student twice.
        if (!db_run('UPDATE campus_memberships SET month_open = month_open + 1, next_month_at = NULL WHERE id = ? AND next_month_at IS NOT NULL AND next_month_at <= NOW() AND access_until >= next_month_at', [$r['id']])) continue;
        $moved++;
        $next = (int) $r['month_open'] + 1;
        if ($next > CAMPUS_YEAR_MONTHS) {
            create_user_notification((int) $r['user_id'], null, 'campus_year_done', 'You have reached the end of the ' . $r['name'] . ' Campus year. Finish your lessons to earn your certificate.', base_url('campus-learn.php?campus=' . $r['slug']));
            continue;
        }
        $key = $r['campus_id'] . ':' . $next;
        if (!isset($titles[$key])) $titles[$key] = trim((string) (db_one('SELECT title FROM campus_stages WHERE campus_id = ? AND month_no = ?', [$r['campus_id'], $next])['title'] ?? ''));
        create_user_notification((int) $r['user_id'], null, 'campus_month_open', 'Month ' . $next . ($titles[$key] !== '' ? ': ' . $titles[$key] : '') . ' is now open in the ' . $r['name'] . ' Campus.', base_url('campus-learn.php?campus=' . $r['slug'] . '&view=lessons&month=' . $next));
    }
    return $moved;
}

/**
 * A campus's active students grouped by the month they are on: [month => ['n' => students,
 * 'pending' => students waiting to move on, 'opens_at' => when they do, or null]]. Month 13 means the
 * group has finished the year. Sorted by month.
 */
function campus_month_groups(int $campusId): array {
    if (!campus_groups_ready()) return [];
    $out = [];
    foreach (db_all('SELECT month_open, COUNT(*) AS n, SUM(next_month_at IS NOT NULL) AS pending, MIN(next_month_at) AS opens_at FROM campus_memberships WHERE campus_id = ? AND access_until > NOW() GROUP BY month_open ORDER BY month_open', [$campusId]) as $r) {
        $out[(int) $r['month_open']] = ['n' => (int) $r['n'], 'pending' => (int) $r['pending'], 'opens_at' => $r['opens_at']];
    }
    return $out;
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
    $campus = get_campus((int) $lesson['campus_id']);
    if ($campus && campus_is_professor($user, $campus)) return true;
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
        record_campus_professor_earning($payment);
    } catch (Throwable $e) {
        error_log('[campus] professor earning not recorded for payment ' . $payment['id'] . ': ' . $e->getMessage());
    }
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

/** Labels for campus_launch_stage(), in order. */
const CAMPUS_LAUNCH_STAGES = ['Needs a professor', 'Building', 'Ready to open', 'Open'];

/**
 * The five launch steps of each campus, keyed by campus id: professor, curriculum (all twelve months
 * titled), month 1 lessons, an upcoming live class, published. Each step is
 * [label, done, status text, next-step label, campus-edit tab]. Also returns the next live class.
 */
function campus_launch_steps(array $campuses): array {
    if (!$campuses) return [];
    $written = array_column(db_all("SELECT campus_id, COUNT(*) AS n FROM campus_stages WHERE TRIM(title) <> '' GROUP BY campus_id"), 'n', 'campus_id');
    $monthOne = array_column(db_all('SELECT campus_id, COUNT(*) AS n FROM campus_lessons WHERE month_no = 1 GROUP BY campus_id'), 'n', 'campus_id');
    $nextLive = array_column(db_all('SELECT campus_id, MIN(starts_at) AS s FROM campus_live_classes WHERE starts_at >= NOW() GROUP BY campus_id'), 's', 'campus_id');
    $out = [];
    foreach ($campuses as $c) {
        $cid = (int) $c['id'];
        $w = (int) ($written[$cid] ?? 0);
        $l = (int) ($monthOne[$cid] ?? 0);
        $live = $nextLive[$cid] ?? null;
        $open = $c['status'] === 'PUBLISHED';
        $out[$cid] = [
            'steps' => [
                ['Professor', (bool) $c['professor_name'], $c['professor_name'] ?: 'Not assigned', 'Assign a professor', 'professor'],
                ['Curriculum', $w >= CAMPUS_YEAR_MONTHS, $w ? $w . ' of ' . CAMPUS_YEAR_MONTHS . ' months' : 'Not written', 'Write the curriculum', 'curriculum'],
                ['Month 1 lessons', $l > 0, $l ? $l . ' lesson' . ($l === 1 ? '' : 's') : 'None yet', 'Upload month 1 lessons', 'lessons'],
                ['Live class', $live !== null, $live ? date('D j M', strtotime($live)) : 'None booked', 'Book a live class', 'live'],
                ['Publish', $open, $open ? 'Open' : 'Draft', 'Publish the campus', 'details'],
            ],
            'live' => $live,
            'month_one' => $l,
            'written' => $w,
        ];
        $out[$cid]['done'] = count(array_filter(array_column($out[$cid]['steps'], 1)));
    }
    return $out;
}

/**
 * Where a campus is on the way to opening, as an index into CAMPUS_LAUNCH_STAGES. A campus can be
 * published once it has a professor and a month 1 lesson (see campus-edit.php), so that is "ready".
 */
function campus_launch_stage(array $campus, array $launch): int {
    if ($campus['status'] === 'PUBLISHED') return 3;
    if (!$campus['professor_name']) return 0;
    return $launch['month_one'] > 0 ? 2 : 1;
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

// ---------------------------------------------------------------- professor pay

const CAMPUS_PROFESSOR_SHARE_DEFAULT = 40.0;
const PROFESSOR_PAYOUT_METHODS = ['MOBILE_MONEY' => 'Mobile money', 'BANK' => 'Bank transfer', 'CASH' => 'Cash'];

/** True once migration/add-professor-pay.sql has been applied. */
function professor_pay_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT 1 FROM campus_professor_earnings LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/** Is this user the professor of this campus (linked by account)? */
function campus_is_professor(?array $user, array $campus): bool {
    return $user !== null && !empty($campus['professor_user_id']) && (int) $campus['professor_user_id'] === (int) $user['id'];
}

function campus_professor_share(array $campus): float {
    $p = isset($campus['professor_share_percent']) ? (float) $campus['professor_share_percent'] : CAMPUS_PROFESSOR_SHARE_DEFAULT;
    return max(0.0, min(100.0, $p));
}

/** "40%" or "37.5%". */
function format_share(float $pct): string {
    return rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.') . '%';
}

/** Campuses this user teaches. */
function get_professor_campuses(int $userId): array {
    if (!campuses_ready() || !professor_pay_ready()) return [];
    return db_all('SELECT * FROM campuses WHERE professor_user_id = ? ORDER BY sort_order, name', [$userId]);
}

/**
 * Records the professor's share of one successful campus payment, once (payment_id is unique), and
 * tells the professor when they have an account linked. Called from apply_campus_payment_success().
 */
function record_campus_professor_earning(array $payment): void {
    if (!professor_pay_ready()) return;
    $campus = get_campus((int) $payment['campus_id']);
    if (!$campus) return;
    $pct = campus_professor_share($campus);
    $gross = (float) $payment['amount'];
    $amount = round($gross * $pct / 100, 2);
    $plan = $payment['campus_plan'] === 'YEARLY' ? 'YEARLY' : 'MONTHLY';
    $added = db_run(
        'INSERT IGNORE INTO campus_professor_earnings (campus_id, payment_id, learner_id, professor_name, professor_user_id, plan, gross, share_percent, amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$campus['id'], $payment['id'], $payment['user_id'] ?: null, $campus['professor_name'], $campus['professor_user_id'] ?: null, $plan, $gross, $pct, $amount]
    );
    if (!$added || empty($campus['professor_user_id']) || $amount <= 0) return;

    $prof = db_one('SELECT id, name, email FROM users WHERE id = ?', [$campus['professor_user_id']]);
    if (!$prof) return;
    $url = base_url('dashboard/professor.php?campus=' . $campus['id']);
    $line = 'A ' . ($plan === 'YEARLY' ? 'yearly' : 'monthly') . ' payment of ' . format_money($gross) . ' came in for the ' . $campus['name'] . ' Campus. Your '
        . format_share($pct) . ' share is ' . format_money($amount) . '.';
    create_user_notification((int) $prof['id'], null, 'professor_earning', 'You earned ' . format_money($amount) . ' from a ' . $campus['name'] . ' Campus payment.', $url);
    if ($prof['email']) {
        resend_send($prof['email'], 'You earned ' . format_money($amount) . ' · ' . $campus['name'] . ' Campus', campus_email_html(
            'You earned ' . format_money($amount), e($line) . ' It is added to what Obin Academy owes you and is paid out to your mobile money.', 'See your earnings', $url
        ));
    }
}

/** Per campus: earned (all time and last 30 days), paid, owed now and the last payout. */
function professor_pay_summary(?int $professorUserId = null): array {
    $campuses = $professorUserId !== null ? get_professor_campuses($professorUserId) : get_all_campuses();
    $out = [];
    foreach ($campuses as $c) {
        $out[(int) $c['id']] = $c + ['earned' => 0.0, 'earned30' => 0.0, 'gross30' => 0.0, 'owed' => 0.0, 'owed_count' => 0, 'payments' => 0, 'paid' => 0.0, 'last_paid_at' => null, 'members' => 0];
    }
    if (!$out || !professor_pay_ready()) return $out;
    $ids = implode(',', array_map('intval', array_keys($out)));
    foreach (db_all(
        "SELECT campus_id, SUM(amount) AS earned, SUM(IF(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY), amount, 0)) AS earned30,
                SUM(IF(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY), gross, 0)) AS gross30,
                SUM(IF(payout_id IS NULL, amount, 0)) AS owed, SUM(payout_id IS NULL) AS owed_count, COUNT(*) AS payments
         FROM campus_professor_earnings WHERE campus_id IN ($ids) GROUP BY campus_id"
    ) as $r) {
        $k = (int) $r['campus_id'];
        $out[$k]['earned'] = (float) $r['earned']; $out[$k]['earned30'] = (float) $r['earned30']; $out[$k]['gross30'] = (float) $r['gross30'];
        $out[$k]['owed'] = (float) $r['owed']; $out[$k]['owed_count'] = (int) $r['owed_count']; $out[$k]['payments'] = (int) $r['payments'];
    }
    foreach (db_all("SELECT campus_id, SUM(amount) AS paid, MAX(paid_at) AS last_paid_at FROM campus_professor_payouts WHERE campus_id IN ($ids) GROUP BY campus_id") as $r) {
        $out[(int) $r['campus_id']]['paid'] = (float) $r['paid'];
        $out[(int) $r['campus_id']]['last_paid_at'] = $r['last_paid_at'];
    }
    foreach (db_all("SELECT campus_id, COUNT(*) AS n FROM campus_memberships WHERE campus_id IN ($ids) AND access_until > NOW() GROUP BY campus_id") as $r) {
        $out[(int) $r['campus_id']]['members'] = (int) $r['n'];
    }
    return $out;
}

/** One campus's earnings, newest first, with the learner's name and when each was paid out. */
function get_campus_professor_earnings(int $campusId, int $limit = 100): array {
    if (!professor_pay_ready()) return [];
    return db_all(
        'SELECT e.*, u.name AS learner_name, po.paid_at AS payout_paid_at, po.reference AS payout_reference
         FROM campus_professor_earnings e LEFT JOIN users u ON u.id = e.learner_id LEFT JOIN campus_professor_payouts po ON po.id = e.payout_id
         WHERE e.campus_id = ? ORDER BY e.created_at DESC, e.id DESC LIMIT ' . (int) $limit,
        [$campusId]
    );
}

function get_campus_professor_payouts(int $campusId, int $limit = 50): array {
    if (!professor_pay_ready()) return [];
    return db_all(
        'SELECT po.*, (SELECT COUNT(*) FROM campus_professor_earnings e WHERE e.payout_id = po.id) AS payments
         FROM campus_professor_payouts po WHERE po.campus_id = ? ORDER BY po.paid_at DESC, po.id DESC LIMIT ' . (int) $limit,
        [$campusId]
    );
}

/**
 * Records money sent to a campus professor: settles every unpaid earning for that campus in one
 * payout, inside a transaction so a payment landing at the same moment is never half-counted.
 * Emails the professor a statement when an account is linked. Returns the payout row, or null when
 * nothing was owed.
 */
function record_campus_professor_payout(int $campusId, string $method, string $sentTo, string $reference, string $note, int $adminId): ?array {
    $campus = get_campus($campusId);
    if (!$campus || !professor_pay_ready()) return null;
    if (!isset(PROFESSOR_PAYOUT_METHODS[$method])) $method = 'MOBILE_MONEY';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $rows = db_all('SELECT id, amount FROM campus_professor_earnings WHERE campus_id = ? AND payout_id IS NULL FOR UPDATE', [$campusId]);
        $total = round(array_sum(array_map(fn($r) => (float) $r['amount'], $rows)), 2);
        if (!$rows || $total <= 0) { $pdo->rollBack(); return null; }
        $payoutId = db_insert(
            'INSERT INTO campus_professor_payouts (campus_id, professor_name, professor_user_id, amount, method, sent_to, reference, note, paid_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$campusId, $campus['professor_name'], $campus['professor_user_id'] ?: null, $total, $method, mb_substr($sentTo, 0, 120) ?: null, mb_substr($reference, 0, 120) ?: null, mb_substr($note, 0, 255) ?: null, $adminId]
        );
        $ids = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
        db_run("UPDATE campus_professor_earnings SET payout_id = ? WHERE id IN ($ids)", [$payoutId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $payout = db_one('SELECT * FROM campus_professor_payouts WHERE id = ?', [$payoutId]);
    payout_request_mark_paid($campusId, (int) $payoutId, $adminId); // a waiting request from the professor is now paid
    if (!empty($campus['professor_user_id'])) {
        $prof = db_one('SELECT id, name, email FROM users WHERE id = ?', [$campus['professor_user_id']]);
        if ($prof) {
            $url = base_url('dashboard/professor.php?campus=' . $campusId);
            $line = format_money($total) . ' for ' . count($rows) . ' payment' . (count($rows) === 1 ? '' : 's') . ' in the ' . $campus['name'] . ' Campus was sent by '
                . strtolower(PROFESSOR_PAYOUT_METHODS[$method]) . ($sentTo !== '' ? ' to ' . $sentTo : '') . ($reference !== '' ? ' (reference ' . $reference . ')' : '') . '.';
            create_user_notification((int) $prof['id'], null, 'professor_paid', 'Obin Academy paid you ' . format_money($total) . ' for the ' . $campus['name'] . ' Campus.', $url);
            if ($prof['email']) resend_send($prof['email'], 'Obin Academy paid you ' . format_money($total), campus_email_html('Payment sent, ' . campus_first_name($prof['name']), e($line), 'See your statement', $url));
        }
    }
    return $payout;
}

// ---------------------------------------------------------------- payment requests from professors

/** True once migration/add-payout-requests.sql has been applied. */
function payout_requests_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { db_one('SELECT 1 FROM campus_payout_requests LIMIT 1'); return $ready = true; } catch (Throwable $e) { return $ready = false; }
}

/** The open request for a campus, if any (at most one at a time). */
function payout_request_pending(int $campusId): ?array {
    if (!payout_requests_ready()) return null;
    return db_one("SELECT * FROM campus_payout_requests WHERE campus_id = ? AND status = 'PENDING' ORDER BY id DESC LIMIT 1", [$campusId]);
}

/** A campus's requests, newest first. */
function payout_requests_for_campus(int $campusId, int $limit = 20): array {
    if (!payout_requests_ready()) return [];
    return db_all('SELECT * FROM campus_payout_requests WHERE campus_id = ? ORDER BY requested_at DESC, id DESC LIMIT ' . (int) $limit, [$campusId]);
}

/** Every open request, oldest first, with the campus and what is owed on it now (for Admin, Professor Pay). */
function payout_requests_open(): array {
    if (!payout_requests_ready()) return [];
    $rows = db_all(
        "SELECT r.*, c.name AS campus_name, c.professor_name, u.name AS professor_account
         FROM campus_payout_requests r JOIN campuses c ON c.id = r.campus_id LEFT JOIN users u ON u.id = r.professor_user_id
         WHERE r.status = 'PENDING' ORDER BY r.requested_at ASC"
    );
    $owed = professor_pay_summary();
    foreach ($rows as &$r) $r['owed_now'] = (float) ($owed[(int) $r['campus_id']]['owed'] ?? 0);
    return $rows;
}

/**
 * A professor asks to be paid everything owed on their campus now. Refused when nothing is owed, when a
 * request is already waiting, or without a usable mobile money number. Tells the admins.
 * @throws RuntimeException with a message for the professor.
 */
function payout_request_create(array $campus, array $professor, string $phone, string $payoutName, string $note): array {
    if (!payout_requests_ready()) throw new RuntimeException('Payment requests are being switched on. Try again shortly.');
    $owed = (float) (professor_pay_summary((int) $professor['id'])[(int) $campus['id']]['owed'] ?? 0);
    if ($owed <= 0) throw new RuntimeException('Nothing is owed to you right now. Your share is added the moment a member pays.');
    if (payout_request_pending((int) $campus['id'])) throw new RuntimeException('You already have a request waiting. Obin Academy will pay it soon.');
    $phone = trim($phone);
    if (strlen(preg_replace('/\D/', '', $phone)) < 9) throw new RuntimeException('Enter the mobile money number to send your money to, like 0772 123 456.');
    $id = db_insert(
        'INSERT INTO campus_payout_requests (campus_id, professor_user_id, amount, phone, payout_name, note) VALUES (?, ?, ?, ?, ?, ?)',
        [$campus['id'], $professor['id'], $owed, mb_substr($phone, 0, 32), mb_substr(trim($payoutName), 0, 120) ?: null, mb_substr(trim($note), 0, 255) ?: null]
    );
    require_once __DIR__ . '/notifications.php';
    try { create_admin_notification('payout_request', $professor['name'] . ' asked to be paid ' . format_money($owed) . ' for the ' . $campus['name'] . ' Campus.'); } catch (Throwable $e) {}
    return db_one('SELECT * FROM campus_payout_requests WHERE id = ?', [$id]);
}

/** The professor withdraws their own waiting request. */
function payout_request_cancel(int $campusId, int $professorUserId): bool {
    if (!payout_requests_ready()) return false;
    return (bool) db_run("UPDATE campus_payout_requests SET status = 'CANCELLED', decided_at = NOW() WHERE campus_id = ? AND professor_user_id = ? AND status = 'PENDING'", [$campusId, $professorUserId]);
}

/** The admin turns a request down, with a reason the professor sees. */
function payout_request_decline(int $requestId, string $reason, int $adminId): ?array {
    if (!payout_requests_ready()) return null;
    $r = db_one("SELECT r.*, c.name AS campus_name FROM campus_payout_requests r JOIN campuses c ON c.id = r.campus_id WHERE r.id = ? AND r.status = 'PENDING'", [$requestId]);
    if (!$r) return null;
    db_run("UPDATE campus_payout_requests SET status = 'DECLINED', decline_reason = ?, decided_at = NOW(), decided_by = ? WHERE id = ?", [mb_substr(trim($reason), 0, 255), $adminId, $requestId]);
    create_user_notification((int) $r['professor_user_id'], null, 'payout_declined', 'Your payment request of ' . format_money((float) $r['amount']) . ' was not paid: ' . trim($reason), base_url('dashboard/professor.php?campus=' . $r['campus_id']));
    return $r;
}

/** Marks a campus's waiting request paid by a payout (called from record_campus_professor_payout()). */
function payout_request_mark_paid(int $campusId, int $payoutId, int $adminId): void {
    if (!payout_requests_ready()) return;
    db_run("UPDATE campus_payout_requests SET status = 'PAID', payout_id = ?, decided_at = NOW(), decided_by = ? WHERE campus_id = ? AND status = 'PENDING'", [$payoutId, $adminId, $campusId]);
}

function upcoming_campus_live_classes(int $days = 7, ?int $campusId = null): array {
    $sql = 'SELECT l.*, c.name AS campus_name, c.slug AS campus_slug FROM campus_live_classes l JOIN campuses c ON c.id = l.campus_id
            WHERE l.starts_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR) AND l.starts_at <= DATE_ADD(NOW(), INTERVAL ? DAY)';
    $params = [$days];
    if ($campusId) { $sql .= ' AND l.campus_id = ?'; $params[] = $campusId; }
    return db_all($sql . ' ORDER BY l.starts_at ASC', $params);
}
