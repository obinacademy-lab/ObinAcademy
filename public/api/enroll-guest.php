<?php
// Form-POST guest enrollment for free courses — no login required. Grants
// immediate access via session (so it works even if outbound email isn't
// configured) and also emails a durable access link for later.
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/enrollment.php';
require __DIR__ . '/../../includes/email.php';
require __DIR__ . '/../../includes/data.php';

csrf_verify();

$courseId = (int) post('courseId');
$name = post('name');
$email = strtolower(post('email'));
$course = db_one('SELECT slug FROM courses WHERE id = ?', [$courseId]);
$fallback = $course ? '/courses/view.php?slug=' . $course['slug'] : '/courses/index.php';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'Enter a valid name and email address.');
    redirect($fallback);
}

try {
    $result = enroll_guest_in_course($name, $email, $courseId);
    send_guest_access_email($email, $name, $result['course_title'], base_url('access.php?token=' . $result['token']));
    $_SESSION['guest_course_tokens'][$courseId] = $result['token'];
    redirect('/learn.php?slug=' . $result['course_slug']);
} catch (Throwable $e) {
    flash_set('error', $e->getMessage());
    redirect($fallback);
}
