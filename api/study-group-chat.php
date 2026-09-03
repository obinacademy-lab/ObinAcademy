<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = api_require_login();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$body = $isPost ? json_body() : [];
$groupId = $isPost ? (int) ($body['groupId'] ?? 0) : (int) ($_GET['groupId'] ?? 0);

$group = db_one('SELECT id FROM study_groups WHERE id = ?', [$groupId]);
if (!$group) json_response(['error' => 'Study group not found.'], 404);
if (!is_study_group_member($groupId, (int) $user['id'])) json_response(['error' => 'Join this study group to see its chat.'], 403);

if ($isPost) {
    api_csrf_verify($body);
    $text = trim((string) ($body['body'] ?? ''));
    if ($text === '') json_response(['error' => 'Message cannot be empty.'], 400);
    if (mb_strlen($text) > 2000) json_response(['error' => 'Message is too long.'], 400);

    $messageId = create_study_group_message($groupId, (int) $user['id'], $text);
    $message = db_one(
        'SELECT m.*, u.name AS author_name, u.avatar_url AS author_avatar_url FROM study_group_messages m JOIN users u ON u.id = m.author_id WHERE m.id = ?',
        [$messageId]
    );
    json_response(['ok' => true, 'messages' => [$message]]);
}

$afterId = isset($_GET['after']) ? (int) $_GET['after'] : null;
$messages = get_study_group_messages($groupId, $afterId);
json_response(['ok' => true, 'messages' => $messages]);
