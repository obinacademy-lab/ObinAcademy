<?php
/**
 * Admin-side payment queries: the all-payments list (filters, search, paging, CSV), the money
 * health numbers shown on the admin Overview, and the payment-plan monitor. Read-only; the one
 * write the Payments page offers (re-check a pending payment with the provider) reuses
 * resolve_payment_with_iotec(), which claims a payment atomically and so can't double-credit.
 */

const ADMIN_PAYMENT_TYPES = [
    'COURSE_PURCHASE' => 'Course',
    'BUNDLE_PURCHASE' => 'Bundle',
    'INSTALLMENT_PAYMENT' => 'Installment',
    'SCHOOL_SUBSCRIPTION' => 'School subscription',
    'COURSE_GIFT' => 'Gift',
    'PREMIUM_UPGRADE' => 'Downloads',
    'SUBSCRIPTION' => 'Old subscription',
];

/** A payment still PENDING after this many minutes is "stuck": the buyer left or the prompt expired. */
const ADMIN_STUCK_PAYMENT_MINUTES = 30;

/** Cleans GET filters for the payments list. */
function admin_payment_filters(array $get): array {
    $status = strtoupper((string) ($get['status'] ?? ''));
    $type = strtoupper((string) ($get['type'] ?? ''));
    $date = fn(string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($get[$k] ?? '')) ? (string) $get[$k] : '';
    return [
        'status' => in_array($status, ['SUCCESS', 'FAILED', 'PENDING', 'STUCK'], true) ? $status : '',
        'type' => isset(ADMIN_PAYMENT_TYPES[$type]) ? $type : '',
        'q' => trim((string) ($get['q'] ?? '')),
        'from' => $date('from'),
        'to' => $date('to'),
    ];
}

/** [whereSql, params] shared by the list, the count and the CSV export. */
function admin_payment_where(array $f): array {
    $where = ['1=1'];
    $params = [];
    if ($f['status'] === 'STUCK') {
        $where[] = "p.status = 'PENDING' AND p.created_at < DATE_SUB(NOW(), INTERVAL " . ADMIN_STUCK_PAYMENT_MINUTES . ' MINUTE)';
    } elseif ($f['status'] !== '') {
        $where[] = 'p.status = ?'; $params[] = $f['status'];
    }
    if ($f['type'] !== '') { $where[] = 'p.type = ?'; $params[] = $f['type']; }
    if ($f['from'] !== '') { $where[] = 'p.created_at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
    if ($f['to'] !== '') { $where[] = 'p.created_at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $where[] = '(p.phone LIKE ? OR p.iotec_transaction_id LIKE ? OR p.guest_name LIKE ? OR p.guest_email LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR c.title LIKE ? OR b.title LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $where), $params];
}

const ADMIN_PAYMENT_FROM = "
    FROM payments p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN courses c ON c.id = p.course_id
    LEFT JOIN bundles b ON b.id = p.bundle_id
    LEFT JOIN users sc ON sc.id = p.school_subscription_creator_id";

function admin_count_payments(array $f): int {
    [$sql, $params] = admin_payment_where($f);
    return (int) db_one('SELECT COUNT(*) AS n ' . ADMIN_PAYMENT_FROM . ' WHERE ' . $sql, $params)['n'];
}

function admin_get_payments(array $f, int $limit, int $offset = 0): array {
    [$sql, $params] = admin_payment_where($f);
    return db_all(
        'SELECT p.*, u.name AS payer_name, u.email AS payer_email, c.title AS course_title, c.slug AS course_slug, b.title AS bundle_title, b.slug AS bundle_slug,
                COALESCE(sc.school_name, sc.name) AS school_label '
        . ADMIN_PAYMENT_FROM . ' WHERE ' . $sql . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
        $params
    );
}

/** One payment with the same joined columns as the list (used by the reminder buttons). */
function admin_get_payment(int $id): ?array {
    return db_one(
        'SELECT p.*, u.name AS payer_name, u.email AS payer_email, c.title AS course_title, c.slug AS course_slug, b.title AS bundle_title, b.slug AS bundle_slug,
                COALESCE(sc.school_name, sc.name) AS school_label '
        . ADMIN_PAYMENT_FROM . ' WHERE p.id = ?',
        [$id]
    );
}

/** What a payment was for, in words. */
function admin_payment_what(array $p): string {
    return match ($p['type']) {
        'BUNDLE_PURCHASE' => 'Bundle: ' . ($p['bundle_title'] ?: 'removed bundle'),
        'SCHOOL_SUBSCRIPTION' => 'School subscription: ' . ($p['school_label'] ?: 'a school'),
        'SUBSCRIPTION' => 'Old platform subscription',
        'COURSE_GIFT' => 'Gift: ' . ($p['course_title'] ?: 'a course'),
        'INSTALLMENT_PAYMENT' => 'Installment: ' . ($p['course_title'] ?: 'a course'),
        'PREMIUM_UPGRADE' => 'Downloads: ' . ($p['course_title'] ?: 'a course'),
        default => $p['course_title'] ?: 'Removed course',
    };
}

/** Figures for the cards on the Payments page (last 30 days) and the Overview alerts. */
function admin_payment_health(): array {
    $stuck = ADMIN_STUCK_PAYMENT_MINUTES;
    $row = db_one(
        "SELECT
           COALESCE(SUM(CASE WHEN status = 'SUCCESS' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount END), 0) AS collected_30d,
           COALESCE(SUM(status = 'SUCCESS' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS success_30d,
           COALESCE(SUM(status = 'FAILED' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS failed_30d,
           COALESCE(SUM(status = 'FAILED' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)), 0) AS failed_24h,
           COALESCE(SUM(status = 'PENDING'), 0) AS pending_now,
           COALESCE(SUM(status = 'PENDING' AND created_at < DATE_SUB(NOW(), INTERVAL $stuck MINUTE)), 0) AS stuck
         FROM payments"
    );
    $row = array_map('floatval', $row);
    $attempts = $row['success_30d'] + $row['failed_30d'];
    $row['success_rate'] = $attempts > 0 ? round($row['success_30d'] / $attempts * 100) : null;
    return $row;
}

/** Plans / subscriptions that need a human: past due, in grace, or already defaulted. */
function admin_plan_health(): array {
    $inst = db_one(
        "SELECT COALESCE(SUM(status IN ('ACTIVE','GRACE') AND next_due_at < NOW()), 0) AS overdue,
                COALESCE(SUM(status = 'DEFAULTED'), 0) AS defaulted,
                COALESCE(SUM(status IN ('ACTIVE','GRACE')), 0) AS open_plans
         FROM installment_plans"
    );
    $subs = db_one("SELECT COALESCE(SUM(status = 'GRACE'), 0) AS in_grace, COALESCE(SUM(status IN ('ACTIVE','GRACE')), 0) AS live FROM school_subscriptions");
    return [
        'overdue' => (int) $inst['overdue'], 'defaulted' => (int) $inst['defaulted'], 'open_plans' => (int) $inst['open_plans'],
        'subs_in_grace' => (int) $subs['in_grace'], 'subs_live' => (int) $subs['live'],
    ];
}

/** Installment plans for the monitor: view = attention | active | completed | all. */
function admin_get_installment_plans(string $view, int $limit = 200): array {
    $where = match ($view) {
        'active' => "ip.status IN ('ACTIVE','GRACE')",
        'completed' => "ip.status = 'COMPLETED'",
        'all' => '1=1',
        default => "(ip.status = 'DEFAULTED' OR (ip.status IN ('ACTIVE','GRACE') AND ip.next_due_at < NOW()) OR ip.status = 'GRACE')",
    };
    return db_all(
        "SELECT ip.*, u.name AS learner_name, u.email AS learner_email, c.title AS course_title,
                COALESCE(cr.school_name, cr.name) AS creator_label
         FROM installment_plans ip
         JOIN users u ON u.id = ip.learner_id
         JOIN courses c ON c.id = ip.course_id
         JOIN users cr ON cr.id = ip.creator_id
         WHERE $where ORDER BY ip.next_due_at ASC LIMIT " . (int) $limit
    );
}

function admin_get_school_subscriptions(string $view, int $limit = 200): array {
    $where = match ($view) {
        'active' => "ss.status IN ('ACTIVE','GRACE')",
        'ended' => "ss.status IN ('EXPIRED','CANCELED')",
        'all' => '1=1',
        default => "ss.status = 'GRACE'",
    };
    return db_all(
        "SELECT ss.*, u.name AS learner_name, u.email AS learner_email, COALESCE(cr.school_name, cr.name) AS creator_label, c.title AS course_title
         FROM school_subscriptions ss
         JOIN users u ON u.id = ss.learner_id
         JOIN users cr ON cr.id = ss.creator_id
         LEFT JOIN courses c ON c.id = ss.course_id
         WHERE $where ORDER BY ss.current_period_ends_at ASC LIMIT " . (int) $limit
    );
}
