<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));
$guestName = $user ? null : trim((string) ($body['name'] ?? ''));
$guestEmail = $user ? null : trim((string) ($body['email'] ?? ''));

$result = initiate_payment($user ? (int) $user['id'] : null, $courseId, $phone, $guestName, $guestEmail);
json_response($result, isset($result['error']) ? 400 : 200);
