<?php
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

if (!$user) {
    json_response(['error' => 'Please create a free account or log in before buying a bundle.'], 401);
}

$bundleId = (int) ($body['bundleId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));

$result = initiate_bundle_purchase((int) $user['id'], $bundleId, $phone);
json_response($result, isset($result['error']) ? 400 : 200);
