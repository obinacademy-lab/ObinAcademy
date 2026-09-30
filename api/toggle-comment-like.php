<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/comments.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$commentId = (int) ($body['commentId'] ?? 0);
$result = toggle_comment_like((int) $user['id'], $commentId);
json_response($result);
