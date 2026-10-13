<?php
// iotec calls (token + status check) can each take up to 15s; guard our own
// budget explicitly rather than depend on the host's ini default, which on
// Hostinger's PHP-FPM/LSAPI may ignore public/.htaccess's max_execution_time.
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

try {
    json_response(poll_payment_status((int) $user['id'], (int) ($body['paymentId'] ?? 0)));
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}
