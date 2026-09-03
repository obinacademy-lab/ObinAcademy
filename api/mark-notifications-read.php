<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/notifications.php';
require_role(['ADMIN']);

csrf_verify();
mark_notifications_read();

$redirect = (string) post('redirect');
redirect($redirect !== '' && str_starts_with($redirect, '/') ? $redirect : '/dashboard/admin/index.php');
