<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$commentId = (int) ($body['commentId'] ?? 0);
$comment = db_one(
    'SELECT c.id, p.community_id FROM community_comments c JOIN community_posts p ON p.id = c.post_id WHERE c.id = ?',
    [$commentId]
);
if (!$comment) json_response(['error' => 'Comment not found.'], 404);
if (!is_community_member((int) $comment['community_id'], (int) $user['id'])) {
    json_response(['error' => 'Join this community to like comments.'], 403);
}

$liked = toggle_comment_like($commentId, (int) $user['id']);
$likeCount = (int) db_one('SELECT like_count FROM community_comments WHERE id = ?', [$commentId])['like_count'];

json_response(['ok' => true, 'liked' => $liked, 'likeCount' => $likeCount]);
