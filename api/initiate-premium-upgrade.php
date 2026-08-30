<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));

$result = initiate_premium_upgrade((int) $user['id'], $courseId, $phone);
json_response($result, isset($result['error']) ? 400 : 200);
