<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';
$user = require_login();

csrf_verify();
mark_all_notifications_read((int) $user['id']);

$redirect = (string) post('redirect');
redirect($redirect !== '' && str_starts_with($redirect, '/') ? $redirect : '/index.php');
