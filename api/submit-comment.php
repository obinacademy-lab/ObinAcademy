<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/comments.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

$courseId = (int) ($body['courseId'] ?? 0);
$text = (string) ($body['body'] ?? '');
$parentId = isset($body['parentId']) && $body['parentId'] !== null ? (int) $body['parentId'] : null;

$result = add_comment((int) $user['id'], $courseId, $text, $parentId);
json_response($result, isset($result['error']) ? 400 : 200);
