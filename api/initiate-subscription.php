<?php
// iotec calls (token + collection) can each take up to 15s; guard our own
// budget explicitly rather than depend on the host's ini default.
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/subscriptions.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$tier = strtoupper(trim((string) ($body['tier'] ?? '')));
$phone = trim((string) ($body['phone'] ?? ''));

$result = initiate_subscription((int) $user['id'], $tier, $phone);
json_response($result, isset($result['error']) ? 400 : 200);
