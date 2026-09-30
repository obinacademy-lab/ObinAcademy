<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/user_notifications.php';

$user = api_require_login();
$body = json_body();
api_csrf_verify($body);

mark_user_notifications_read((int) $user['id']);
json_response(['ok' => true]);
