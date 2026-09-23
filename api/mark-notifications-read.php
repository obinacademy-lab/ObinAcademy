<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/notifications.php';
require_role(['ADMIN']);

csrf_verify();
mark_notifications_read();

redirect(safe_local_redirect_path((string) post('redirect'), '/dashboard/admin/index.php'));
