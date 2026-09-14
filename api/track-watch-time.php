<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/enrollment.php';
require __DIR__ . '/../includes/subscriptions.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$lessonId = (int) ($body['lessonId'] ?? 0);
// Small server-side sanity clamp on top of learn.js's own per-tick clamp —
// never trust a client-reported duration outright.
$seconds = max(1, min(30, (int) ($body['seconds'] ?? 0)));

$lesson = db_one('SELECT l.id, m.course_id, c.creator_id FROM lessons l JOIN modules m ON m.id = l.module_id JOIN courses c ON c.id = m.course_id WHERE l.id = ?', [$lessonId]);
if (!$lesson) json_response(['error' => 'Lesson not found.'], 404);

$isOwner = (int) $lesson['creator_id'] === (int) $user['id'];
$isAdmin = $user['role'] === 'ADMIN';
$enrollment = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $lesson['course_id']]);
$hasSubAccess = !$enrollment && user_has_active_subscription((int) $user['id']);

if (!$isOwner && !$isAdmin && !$enrollment && !$hasSubAccess) {
    json_response(['error' => 'You do not have access to this lesson.'], 403);
}

// Only a genuine learner's watch-time counts toward the creator payout
// pool — a creator previewing their own course, or an admin reviewing it,
// must never be able to inflate their own (or anyone's) payout this way.
if ($enrollment || $hasSubAccess) {
    db_insert('INSERT INTO lesson_watch_events (seconds_watched, user_id, lesson_id) VALUES (?, ?, ?)', [$seconds, $user['id'], $lessonId]);
}
json_response(['ok' => true]);
