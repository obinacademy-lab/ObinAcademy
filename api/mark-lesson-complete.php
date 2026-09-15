<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/enrollment.php';

// A PDF/text lesson has no natural playback clock, so "marked complete"
// stands in for watch-time — a fixed credit, once per (user, lesson),
// enforced by lesson_text_completions' UNIQUE key so repeat clicks are a
// no-op rather than inflating the payout pool.
const TEXT_LESSON_WATCH_CREDIT_SECONDS = 300;

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$lessonId = (int) ($body['lessonId'] ?? 0);

$lesson = db_one('SELECT l.id, m.course_id, c.creator_id FROM lessons l JOIN modules m ON m.id = l.module_id JOIN courses c ON c.id = m.course_id WHERE l.id = ?', [$lessonId]);
if (!$lesson) json_response(['error' => 'Lesson not found.'], 404);

$isOwner = (int) $lesson['creator_id'] === (int) $user['id'];
$isAdmin = $user['role'] === 'ADMIN';
$enrollment = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $lesson['course_id']]);

if (!$isOwner && !$isAdmin && !$enrollment) {
    json_response(['error' => 'You do not have access to this lesson.'], 403);
}

if ($enrollment) {
    db_run(
        'INSERT IGNORE INTO lesson_text_completions (credit_seconds, user_id, lesson_id) VALUES (?, ?, ?)',
        [TEXT_LESSON_WATCH_CREDIT_SECONDS, $user['id'], $lessonId]
    );
}
json_response(['ok' => true]);
