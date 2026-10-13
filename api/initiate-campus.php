<?php
// Join or renew a campus with mobile money. Same poll endpoint as every other payment (api/poll-payment.php).
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

if (!$user) {
    json_response(['error' => 'Please create a free account or log in to join a campus.'], 401);
}

$result = initiate_campus_payment((int) $user['id'], (int) ($body['campusId'] ?? 0), strtoupper((string) ($body['plan'] ?? '')), trim((string) ($body['phone'] ?? '')));
json_response($result, isset($result['error']) ? 400 : 200);
