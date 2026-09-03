<?php
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/data.php';

/** Signed, stateless unsubscribe token — no separate token table needed. */
function unsubscribe_token(int $leadId): string {
    return $leadId . '.' . hash_hmac('sha256', (string) $leadId, APP_SECRET);
}

function unsubscribe_token_lead_id(string $token): ?int {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) return null;
    [$leadId, $signature] = $parts;
    if (!hash_equals(hash_hmac('sha256', $leadId, APP_SECRET), $signature)) return null;
    return (int) $leadId;
}

/**
 * Validates and stores a voluntary lead-capture form submission — never
 * called from tracking data alone. Upserts by email (a repeat submission
 * just refreshes visit info rather than erroring or duplicating).
 * @return array{ok?: bool, leadType?: string, whatsappUrl?: string, error?: string}
 */
function capture_lead(array $data, ?string $visitorId, string $referrerSource): array {
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $phone = trim((string) ($data['phone'] ?? ''));
    $phone = $phone !== '' ? $phone : null;
    $leadType = ($data['leadType'] ?? '') === 'creator' ? 'creator' : 'learner';
    $consent = !empty($data['consent']);

    if ($name === '') return ['error' => 'Enter your name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Enter a valid email address.'];
    if ($leadType === 'creator' && !$phone) return ['error' => 'Enter your phone number.'];
    if (!$consent) return ['error' => 'Please accept to receive emails to continue.'];

    $existing = db_one('SELECT id FROM leads WHERE email = ?', [$email]);
    if ($existing) {
        $leadId = (int) $existing['id'];
        db_run(
            'UPDATE leads SET last_visit_at = NOW(), visit_count = visit_count + 1, phone = COALESCE(?, phone), visitor_id = COALESCE(visitor_id, ?) WHERE id = ?',
            [$phone, $visitorId, $leadId]
        );
        $isNewLead = false;
    } else {
        $leadId = db_insert(
            'INSERT INTO leads (name, email, phone, lead_type, source, visitor_id, consent_marketing) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$name, $email, $phone, $leadType, $referrerSource, $visitorId]
        );
        $isNewLead = true;
    }

    if ($isNewLead) {
        $unsubscribeUrl = base_url('unsubscribe.php?token=' . unsubscribe_token($leadId));
        send_lead_welcome_email($email, $name, get_trending_courses(3), $unsubscribeUrl);
        create_admin_notification('new_lead', "New lead: {$name} ({$email})", $leadId);

        if ($leadType === 'creator') {
            send_lead_creator_invitation_email($email, $name, $unsubscribeUrl);
            create_admin_notification('creator_request', "{$name} wants to become a creator", $leadId);
        }
    }

    return ['ok' => true, 'leadType' => $leadType, 'whatsappUrl' => lead_whatsapp_url($name, $leadType)];
}

const LEAD_STATUSES = ['NEW', 'CONTACTED', 'INTERESTED', 'ENROLLED', 'CREATOR', 'LOST'];

/** New leads captured per day for the last $days days — for the CRM's trend chart. */
function get_leads_daily_series(int $days = 30): array {
    $rows = db_all(
        'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM leads
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at)',
        [$days - 1]
    );
    $byDate = [];
    foreach ($rows as $r) $byDate[$r['d']] = (int) $r['n'];

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $series[] = ['date' => $date, 'count' => $byDate[$date] ?? 0];
    }
    return $series;
}

/** @return array<string,int> counts of every status, in LEAD_STATUSES order, zero-filled */
function get_lead_status_breakdown(): array {
    $rows = db_all('SELECT status, COUNT(*) AS n FROM leads GROUP BY status');
    $counts = array_fill_keys(LEAD_STATUSES, 0);
    foreach ($rows as $r) $counts[$r['status']] = (int) $r['n'];
    return $counts;
}

/** @return array<string,int> counts of every source, zero-filled */
function get_lead_source_breakdown(): array {
    $rows = db_all('SELECT source, COUNT(*) AS n FROM leads GROUP BY source');
    $counts = ['google' => 0, 'social' => 0, 'direct' => 0, 'other' => 0];
    foreach ($rows as $r) $counts[$r['source']] = (int) $r['n'];
    return $counts;
}

/**
 * @param array{q?:string, status?:string, type?:string, source?:string} $filters
 * @return array{rows: array, total: int}
 */
function get_leads(array $filters = [], int $page = 1, int $perPage = 25): array {
    $where = [];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(name LIKE ? OR email LIKE ?)';
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if (!empty($filters['status']) && in_array($filters['status'], LEAD_STATUSES, true)) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['type']) && in_array($filters['type'], ['learner', 'creator'], true)) {
        $where[] = 'lead_type = ?';
        $params[] = $filters['type'];
    }
    if (!empty($filters['source']) && in_array($filters['source'], ['google', 'social', 'direct', 'other'], true)) {
        $where[] = 'source = ?';
        $params[] = $filters['source'];
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $total = (int) db_one("SELECT COUNT(*) AS n FROM leads $whereSql", $params)['n'];

    $perPage = max(1, min(100, $perPage));
    $offset = max(0, ($page - 1) * $perPage);
    $rows = db_all(
        "SELECT * FROM leads $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
        $params
    );

    return ['rows' => $rows, 'total' => $total];
}

function get_lead_by_id(int $id): ?array {
    return db_one('SELECT * FROM leads WHERE id = ?', [$id]);
}

function set_lead_status(int $leadId, string $status): bool {
    if (!in_array($status, LEAD_STATUSES, true)) return false;
    db_run('UPDATE leads SET status = ? WHERE id = ?', [$status, $leadId]);
    return true;
}

function get_lead_notes(int $leadId): array {
    return db_all(
        'SELECT ln.*, u.name AS admin_name FROM lead_notes ln JOIN users u ON u.id = ln.admin_id
         WHERE ln.lead_id = ? ORDER BY ln.created_at DESC',
        [$leadId]
    );
}

function add_lead_note(int $leadId, int $adminId, string $note): void {
    $note = trim($note);
    if ($note === '') return;
    db_insert('INSERT INTO lead_notes (lead_id, admin_id, note) VALUES (?, ?, ?)', [$leadId, $adminId, $note]);
}

/** Every page a visitor has browsed, newest first — the CRM's "browsing history" for one lead. */
function get_lead_page_history(?string $visitorId, int $limit = 50): array {
    if (!$visitorId) return [];
    $limit = max(1, min(200, $limit));
    return db_all(
        "SELECT path, entered_at, time_on_page_seconds, scroll_depth_pct FROM visitor_pageviews
         WHERE visitor_id = ? ORDER BY entered_at DESC LIMIT $limit",
        [$visitorId]
    );
}

/** Distinct courses a visitor has viewed, resolved to real titles — same slug-parsing approach as the admin analytics page. */
function get_lead_courses_viewed(?string $visitorId): array {
    if (!$visitorId) return [];
    $rows = db_all(
        "SELECT DISTINCT path FROM visitor_pageviews WHERE visitor_id = ? AND path LIKE '%/courses/view.php?%'",
        [$visitorId]
    );
    $slugs = [];
    foreach ($rows as $r) {
        $query = parse_url($r['path'], PHP_URL_QUERY) ?: '';
        parse_str($query, $params);
        if (!empty($params['slug'])) $slugs[$params['slug']] = true;
    }
    if (!$slugs) return [];
    $slugList = array_keys($slugs);
    $placeholders = implode(',', array_fill(0, count($slugList), '?'));
    return db_all("SELECT slug, title FROM courses WHERE slug IN ($placeholders)", $slugList);
}

/**
 * Sends whatever drip-sequence step (3/5/7) is due for each eligible lead —
 * range-based ("at least N days old"), not exact-day equality, so a missed
 * cron run never permanently skips a step. lead_sequence_log's UNIQUE
 * constraint is the only double-send guard. Enrolled/lost/unsubscribed
 * leads are skipped. Returns counts sent per step, for the cron log line.
 */
function send_due_sequence_emails(): array {
    return [
        'day3' => send_due_step(3),
        'day5' => send_due_step(5),
        'day7' => send_due_step(7),
    ];
}

function send_due_step(int $step): int {
    $due = db_all(
        "SELECT * FROM leads
         WHERE DATE(created_at) <= CURDATE() - INTERVAL ? DAY
           AND unsubscribed = 0 AND status NOT IN ('ENROLLED', 'LOST')
           AND NOT EXISTS (SELECT 1 FROM lead_sequence_log s WHERE s.lead_id = leads.id AND s.step = ?)",
        [$step, $step]
    );

    foreach ($due as $lead) {
        $unsubscribeUrl = base_url('unsubscribe.php?token=' . unsubscribe_token((int) $lead['id']));
        match ($step) {
            3 => send_lead_day3_email($lead['email'], $lead['name'], $lead['lead_type'], $unsubscribeUrl),
            5 => send_lead_day5_email($lead['email'], $lead['name'], get_trending_courses(3), $unsubscribeUrl),
            7 => send_lead_day7_email($lead['email'], $lead['name'], get_courses_on_sale(3), $unsubscribeUrl),
            default => null,
        };
        // Logged whether or not the send actually succeeded (resend_send()
        // fails soft and logs server-side) — a permanently-failing address
        // must not be retried forever, same philosophy as the geo sweep.
        db_run('INSERT IGNORE INTO lead_sequence_log (lead_id, step) VALUES (?, ?)', [$lead['id'], $step]);
    }
    return count($due);
}

/** Courses genuinely on sale right now — for the day-7 email. Never a fabricated "limited time" claim. */
function get_courses_on_sale(int $limit = 3): array {
    return get_course_cards(
        'c.sale_price IS NOT NULL AND c.sale_price > 0 AND c.sale_price < c.price',
        [], 'c.created_at DESC', $limit
    );
}

function lead_whatsapp_url(string $name, string $leadType): string {
    $message = $leadType === 'creator'
        ? "Hi! I'm {$name} — I just signed up on Obin Academy and I'm interested in becoming a creator."
        : "Hi! I'm {$name} — I just signed up on Obin Academy and I'd love some help getting started.";
    return 'https://wa.me/256775361998?text=' . urlencode($message);
}
