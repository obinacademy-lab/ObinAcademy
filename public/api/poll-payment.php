<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

$paymentId = (int) ($body['paymentId'] ?? 0);
$pollToken = $user ? null : trim((string) ($body['pollToken'] ?? ''));

try {
    $result = poll_payment_status($user ? (int) $user['id'] : null, $paymentId, $pollToken);
    json_response($result);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}
