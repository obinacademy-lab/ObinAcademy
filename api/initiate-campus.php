<?php
// Join or renew a campus with mobile money. Same poll endpoint as every other payment (api/poll-payment.php).
set_time_limit(45);

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

if (!$user) {
    json_response(['error' => 'Please create a free account or log in to join a campus.'], 401);
}

$phoneDigits = preg_replace('/\D+/', '', (string) ($body['phone'] ?? ''));
if (!rate_limit_take('pay-user', (string) $user['id'], 8, 3600) || ($phoneDigits !== '' && !rate_limit_take('pay-phone', $phoneDigits, 5, 3600))) {
    json_response(['error' => 'Too many payment requests. Please wait a while before trying again.'], 429);
}
$result = initiate_campus_payment((int) $user['id'], (int) ($body['campusId'] ?? 0), strtoupper((string) ($body['plan'] ?? '')), trim((string) ($body['phone'] ?? '')));
json_response($result, isset($result['error']) ? 400 : 200);
