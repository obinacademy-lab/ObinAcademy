<?php
set_time_limit(45);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/payments.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

if (!$user) {
    json_response(['error' => 'Please create a free account or log in before gifting a course.'], 401);
}

$courseId = (int) ($body['courseId'] ?? 0);
$phone = trim((string) ($body['phone'] ?? ''));
$recipientName = trim((string) ($body['recipientName'] ?? ''));
$recipientEmail = trim((string) ($body['recipientEmail'] ?? ''));
$message = trim((string) ($body['giftMessage'] ?? ''));
$months = isset($body['months']) ? (int) $body['months'] : null;

$result = initiate_course_gift((int) $user['id'], $courseId, $recipientName, $recipientEmail, $message, $phone, $months);
json_response($result, isset($result['error']) ? 400 : 200);
