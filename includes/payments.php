<?php
/**
 * Mobile-money payments. Obin Academy sells one thing: campus memberships (see includes/campuses.php
 * for starting a payment and what a successful one grants). This file resolves a payment against
 * iotec and lets the browser poll it. Payments left from the old per-course platform are history only.
 */
require_once __DIR__ . '/iotec.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/campuses.php';

function validate_phone(string $phone): bool {
    return strlen($phone) >= 9 && preg_match('/^[0-9+\s-]+$/', $phone);
}

/** A payment with what it was for, by id; null if there is no such payment. */
function fetch_payment_by_id(int $paymentId): ?array {
    $type = db_one('SELECT type FROM payments WHERE id = ?', [$paymentId]);
    if (!$type) return null;
    if ($type['type'] === 'CAMPUS_MEMBERSHIP') return fetch_payment_with_campus($paymentId);
    return db_one('SELECT p.*, u.name AS learner_name, u.email AS learner_email FROM payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$paymentId]);
}

/**
 * Checks a PENDING payment's real status with iotec and applies the result. Called by the browser's
 * live polling, by initiate_campus_payment() before reusing a pending row, and by the cron sweep.
 * @return array{status: string, statusMessage?: string}
 */
function resolve_payment_with_iotec(array $payment): array {
    $paymentId = (int) $payment['id'];

    if ($payment['status'] !== 'PENDING') {
        return ['status' => $payment['status'], 'statusMessage' => $payment['status_message']];
    }
    if (!$payment['iotec_transaction_id']) {
        return ['status' => 'PENDING'];
    }

    try {
        $result = iotec_check_collection_status($payment['iotec_transaction_id']);
    } catch (Throwable $e) {
        error_log('[iotec] checkCollectionStatus failed for payment ' . $paymentId . ': ' . $e->getMessage());
        return ['status' => 'PENDING'];
    }

    if ($result['status'] === 'Success') {
        // Atomic claim: only the caller whose UPDATE matches a still-PENDING row applies the success
        // side effects, so two overlapping resolves (two tabs, a retry, the cron sweep) never grant
        // access or credit the professor twice.
        $claimed = db_run("UPDATE payments SET status = 'SUCCESS', status_message = ? WHERE id = ? AND status = 'PENDING'", [$result['statusMessage'], $paymentId]);
        if ($claimed && $payment['type'] === 'CAMPUS_MEMBERSHIP') {
            apply_campus_payment_success($payment);
        }
        return ['status' => 'SUCCESS'];
    }

    if ($result['status'] === 'Pending' || $result['status'] === 'SentToVendor') {
        return ['status' => 'PENDING', 'statusMessage' => $result['statusMessage']];
    }

    $msg = $result['statusMessage'] ?? $result['status'];
    db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$msg, $paymentId]);
    return ['status' => 'FAILED', 'statusMessage' => $msg];
}

/**
 * Settles payments left PENDING because the payer closed the page before the prompt resolved: asks
 * iotec about each one older than 5 minutes (and younger than 3 days) and applies the result.
 * Called from cron/track-maintenance.php.
 * @return array{success: int, failed: int, still_pending: int}
 */
function sweep_stale_pending_payments(int $limit = 30): array {
    $counts = ['success' => 0, 'failed' => 0, 'still_pending' => 0];
    $rows = db_all(
        "SELECT id FROM payments WHERE status = 'PENDING' AND iotec_transaction_id IS NOT NULL
           AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
         ORDER BY created_at ASC LIMIT " . (int) $limit
    );
    foreach ($rows as $r) {
        $payment = fetch_payment_by_id((int) $r['id']);
        if (!$payment) continue;
        $status = resolve_payment_with_iotec($payment)['status'];
        $counts[$status === 'SUCCESS' ? 'success' : ($status === 'FAILED' ? 'failed' : 'still_pending')]++;
    }
    return $counts;
}

/**
 * The logged-in payer checks their own payment.
 * @return array{status: string, statusMessage?: string}
 */
function poll_payment_status(int $userId, int $paymentId): array {
    $payment = fetch_payment_by_id($paymentId);
    if (!$payment) throw new RuntimeException('Payment not found.');
    if ((int) $payment['user_id'] !== $userId) throw new RuntimeException('Not authorized.');
    return resolve_payment_with_iotec($payment);
}
