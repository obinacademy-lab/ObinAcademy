<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/professor.php';
$user = require_login();

// Admins, professors (linked to a campus) and members each have their own dashboard.
redirect($user['role'] === 'ADMIN' ? '/dashboard/admin/index.php' : (is_professor($user) ? '/dashboard/professor/index.php' : '/dashboard/learner/index.php'));
