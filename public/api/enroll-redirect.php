<?php
// Form-POST wrapper around enroll_in_course for free-course enrollment
// (plain <form> submit, not a fetch call — redirects back with a flash message).
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/enrollment.php';
require __DIR__ . '/../../includes/data.php';

$user = require_login();
csrf_verify();

$courseId = (int) post('courseId');
$course = db_one('SELECT slug FROM courses WHERE id = ?', [$courseId]);

try {
    enroll_in_course((int) $user['id'], $courseId);
} catch (Throwable $e) {
    flash_set('error', $e->getMessage());
}

redirect($course ? '/courses/view.php?slug=' . $course['slug'] : '/courses/index.php');
