<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = api_require_login();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$body = $isPost ? json_body() : [];
$conversationId = $isPost ? (int) ($body['conversationId'] ?? 0) : (int) ($_GET['conversationId'] ?? 0);

if (!is_conversation_participant($conversationId, (int) $user['id'])) {
    json_response(['error' => 'Conversation not found.'], 404);
}

if ($isPost) {
    api_csrf_verify($body);
    $text = trim((string) ($body['body'] ?? ''));
    if ($text === '') json_response(['error' => 'Message cannot be empty.'], 400);
    if (mb_strlen($text) > 2000) json_response(['error' => 'Message is too long.'], 400);

    $messageId = send_message($conversationId, (int) $user['id'], $text);
    json_response(['ok' => true, 'messages' => [get_message_by_id($messageId)]]);
}

$afterId = isset($_GET['after']) ? (int) $_GET['after'] : null;
$messages = get_conversation_messages($conversationId, $afterId);
mark_conversation_read($conversationId, (int) $user['id']);
json_response(['ok' => true, 'messages' => $messages]);
