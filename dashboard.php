<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

redirect($user['role'] === 'ADMIN' ? '/dashboard/admin/index.php' : '/dashboard/learner/index.php');
