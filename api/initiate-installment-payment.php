<?php
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

if (!$user) {
    json_response(['error' => 'Please create a free account or log in before starting a payment plan.'], 401);
}

$courseId = (int) ($body['courseId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));

$result = initiate_installment_payment((int) $user['id'], $courseId, $phone);
json_response($result, isset($result['error']) ? 400 : 200);
