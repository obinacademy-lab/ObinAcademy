<?php
// iotec calls (token + collection) can each take up to 15s; guard our own
// budget explicitly rather than depend on the host's ini default, which on
// Hostinger's PHP-FPM/LSAPI may ignore public/.htaccess's max_execution_time.
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));

$result = initiate_premium_upgrade((int) $user['id'], $courseId, $phone);
json_response($result, isset($result['error']) ? 400 : 200);
