<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

switch ($user['role']) {
    case 'ADMIN':
        redirect('/dashboard/admin/index.php');
    case 'CREATOR':
        redirect('/dashboard/creator/index.php');
    default:
        redirect('/dashboard/learner/index.php');
}
