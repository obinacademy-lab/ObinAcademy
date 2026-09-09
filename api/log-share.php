<?php
require __DIR__ . '/../includes/bootstrap.php';

$allowedChannels = ['copy_link', 'whatsapp', 'facebook', 'twitter', 'linkedin', 'instagram', 'tiktok'];

$body = json_body();
$courseId = (int) ($body['course_id'] ?? 0);
$channel = (string) ($body['channel'] ?? '');
$token = (string) ($body['token'] ?? '');

if (!$courseId || !in_array($channel, $allowedChannels, true) || !preg_match('/^[a-f0-9]{12}$/', $token)) {
    json_response(['logged' => false], 400);
}

$course = db_one("SELECT id FROM courses WHERE id = ? AND status = 'PUBLISHED'", [$courseId]);
if (!$course) {
    json_response(['logged' => false], 404);
}

$user = current_user();
db_run(
    'INSERT IGNORE INTO course_shares (course_id, channel, share_token, sharer_id) VALUES (?, ?, ?, ?)',
    [$courseId, $channel, $token, $user['id'] ?? null]
);

json_response(['logged' => true]);
