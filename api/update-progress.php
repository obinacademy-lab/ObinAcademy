<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/enrollment.php';

$user = current_user();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$progress = (float) ($body['progress'] ?? 0);
$guestToken = $user ? null : ($_SESSION['guest_course_tokens'][$courseId] ?? null);

if (!$user && !$guestToken) json_response(['error' => 'Not authorized.'], 401);

$certificate = update_lesson_progress($user ? (int) $user['id'] : null, $courseId, $progress, $guestToken);
json_response(['ok' => true, 'certificateCode' => $certificate['code'] ?? null]);
