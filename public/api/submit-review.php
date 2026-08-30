<?php
require __DIR__ . '/../../includes/bootstrap.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$rating = (int) ($body['rating'] ?? 0);
$comment = trim((string) ($body['comment'] ?? ''));

if ($rating < 1 || $rating > 5) json_response(['error' => 'Rating must be between 1 and 5.'], 400);
if ($comment === '' || strlen($comment) < 5) json_response(['error' => 'Write a short comment about your experience.'], 400);

$enrollment = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $courseId]);
if (!$enrollment) json_response(['error' => 'Enroll in this course before leaving a review.'], 403);

$existing = db_one('SELECT id FROM reviews WHERE course_id = ? AND author_id = ?', [$courseId, $user['id']]);
if ($existing) {
    db_run('UPDATE reviews SET rating = ?, comment = ? WHERE id = ?', [$rating, $comment, $existing['id']]);
} else {
    db_insert('INSERT INTO reviews (course_id, author_id, rating, comment) VALUES (?, ?, ?, ?)', [$courseId, $user['id'], $rating, $comment]);
}

json_response(['ok' => true]);
