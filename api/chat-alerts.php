<?php
/**
 * Polled by assets/js/chat-alerts.js on every signed-in page: unread class room messages per campus and
 * the newest one from someone else, so the page can play the alert sound and show a short pop-up.
 * GET only reads the person's own counts.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/campuses.php';
require __DIR__ . '/../includes/class_room.php';

$user = api_require_login();
json_response(class_room_alerts($user));
