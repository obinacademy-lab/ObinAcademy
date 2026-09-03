<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$postId = (int) ($body['postId'] ?? 0);
$post = db_one('SELECT id, community_id FROM community_posts WHERE id = ?', [$postId]);
if (!$post) json_response(['error' => 'Post not found.'], 404);
if (!is_community_member((int) $post['community_id'], (int) $user['id'])) {
    json_response(['error' => 'Join this community to like posts.'], 403);
}

$liked = toggle_post_like($postId, (int) $user['id']);
$likeCount = (int) db_one('SELECT like_count FROM community_posts WHERE id = ?', [$postId])['like_count'];

json_response(['ok' => true, 'liked' => $liked, 'likeCount' => $likeCount]);
