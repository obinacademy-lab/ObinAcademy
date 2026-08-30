<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/enrollment.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);

try {
    enroll_in_course((int) $user['id'], $courseId);
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}
