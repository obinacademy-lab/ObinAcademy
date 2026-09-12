<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/comments.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$commentId = (int) ($body['commentId'] ?? 0);
$comment = db_one('SELECT c.id, c.user_id, co.creator_id FROM comments c JOIN courses co ON co.id = c.course_id WHERE c.id = ?', [$commentId]);
if (!$comment) json_response(['error' => 'Comment not found.'], 404);

$isAuthor = (int) $comment['user_id'] === (int) $user['id'];
$isCourseOwner = (int) $comment['creator_id'] === (int) $user['id'];
$isAdmin = $user['role'] === 'ADMIN';

$ok = delete_comment($commentId, $isAuthor || $isCourseOwner || $isAdmin);
json_response(['ok' => $ok], $ok ? 200 : 403);
