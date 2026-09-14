<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$course = db_one('SELECT creator_id FROM courses WHERE id = ?', [$courseId]);
if (!$course) json_response(['error' => 'Course not found.'], 404);
if ((int) $course['creator_id'] === (int) $user['id']) json_response(['error' => 'You cannot follow your own course.'], 400);

$enrolled = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId]);
if ($enrolled) json_response(['error' => "You're already enrolled in this course."], 400);

$interested = toggle_course_interest((int) $user['id'], $courseId);
json_response(['interested' => $interested]);
