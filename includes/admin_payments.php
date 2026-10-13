<?php
/**
 * Admin-side payment queries: the all-payments list (filters, search, paging, CSV) and the money
 * health numbers shown on the admin Overview. Read-only; the one write the Payments page offers
 * (re-check a pending payment with the provider) reuses resolve_payment_with_iotec(), which claims a
 * payment atomically and so can't double-credit. Payments are campus memberships; the odd row left
 * from the old per-course platform is listed as history.
 */

const ADMIN_PAYMENT_TYPES = [
    'CAMPUS_MEMBERSHIP' => 'Campus',
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
        $where[] = '(p.phone LIKE ? OR p.iotec_transaction_id LIKE ? OR p.guest_name LIKE ? OR p.guest_email LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR ca.name LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $where), $params];
}

const ADMIN_PAYMENT_FROM = "
    FROM payments p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN campuses ca ON ca.id = p.campus_id";

function admin_count_payments(array $f): int {
    [$sql, $params] = admin_payment_where($f);
    return (int) db_one('SELECT COUNT(*) AS n ' . ADMIN_PAYMENT_FROM . ' WHERE ' . $sql, $params)['n'];
}

function admin_get_payments(array $f, int $limit, int $offset = 0): array {
    [$sql, $params] = admin_payment_where($f);
    return db_all(
        'SELECT p.*, u.name AS payer_name, u.email AS payer_email, ca.name AS campus_name, ca.slug AS campus_slug '
        . ADMIN_PAYMENT_FROM . ' WHERE ' . $sql . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
        $params
    );
}

/** One payment with the same joined columns as the list (used by the reminder buttons). */
function admin_get_payment(int $id): ?array {
    return db_one('SELECT p.*, u.name AS payer_name, u.email AS payer_email, ca.name AS campus_name, ca.slug AS campus_slug ' . ADMIN_PAYMENT_FROM . ' WHERE p.id = ?', [$id]);
}

/** What a payment was for, in words: "Campus: Crypto Trading (yearly)". */
function admin_payment_what(array $p): string {
    if ($p['type'] !== 'CAMPUS_MEMBERSHIP') return 'Old platform payment';
    return 'Campus: ' . ($p['campus_name'] ?? 'a campus') . (!empty($p['campus_plan']) ? ' (' . strtolower($p['campus_plan']) . ')' : '');
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
