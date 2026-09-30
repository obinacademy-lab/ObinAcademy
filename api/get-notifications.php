<?php
// Polled by assets/js/notifications.js every ~20s — no WebSocket server on
// this host, same polling approach as chat-poll.js. GET, not POST: this
// only reads the current user's own notifications, nothing to CSRF-protect.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user_notifications.php';

$user = api_require_login();

$notifications = array_map(function (array $n): array {
    return [
        'id' => (int) $n['id'],
        'message' => $n['message'],
        'linkUrl' => $n['link_url'],
        'isRead' => (bool) $n['is_read'],
        'timeAgo' => time_ago($n['created_at']),
    ];
}, get_user_notifications((int) $user['id'], 10));

json_response([
    'count' => get_unread_user_notification_count((int) $user['id']),
    'notifications' => $notifications,
]);
