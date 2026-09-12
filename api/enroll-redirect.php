<?php
// Form-POST wrapper around enroll_in_course for free-course enrollment
// (plain <form> submit, not a fetch call — redirects back with a flash message).
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/enrollment.php';
require __DIR__ . '/../includes/data.php';

$user = require_login();
csrf_verify();

$courseId = (int) post('courseId');
$course = db_one('SELECT slug FROM courses WHERE id = ?', [$courseId]);
$quantity = (int) post('quantity', '1');
$extraAttendeeNames = array_map('strval', (array) ($_POST['attendeeNames'] ?? []));

try {
    enroll_in_course((int) $user['id'], $courseId, $quantity, $extraAttendeeNames);
    if ($quantity > 1) flash_set('success', "You're enrolled — extra ticket links were emailed to you.");
} catch (Throwable $e) {
    flash_set('error', $e->getMessage());
}

redirect($course ? '/courses/view.php?slug=' . $course['slug'] : '/courses/index.php');
