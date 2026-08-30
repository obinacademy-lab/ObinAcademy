<?php
// Landing page for a guest's emailed access link. No account, no password —
// the token itself (hashed before lookup, plaintext never stored) is proof
// of purchase. Valid tokens get stashed in-session so the rest of the site
// (learn.php, stream.php, courses/view.php) can recognize this guest without
// repeating the token on every request.
require __DIR__ . '/includes/bootstrap.php';

$token = query_param('token');
if ($token === '') {
    redirect('/courses/index.php');
}

$enrollment = db_one(
    'SELECT e.course_id, c.slug FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.access_token_hash = ? AND e.user_id IS NULL',
    [hash('sha256', $token)]
);

if (!$enrollment) {
    $pageTitle = 'Invalid Access Link — Obin Academy';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="max-width:440px; padding: 90px 20px; text-align:center;">
      <div style="font-size:40px;">🔒</div>
      <h1 class="h3" style="margin-top:16px;">Invalid or Expired Link</h1>
      <p class="muted" style="margin-top:10px;">
        This access link isn't valid. If you completed a purchase, check your email
        for the correct link, or reach out to us for help.
      </p>
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:20px;">Explore Courses</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$_SESSION['guest_course_tokens'][(int) $enrollment['course_id']] = $token;
redirect('/learn.php?slug=' . $enrollment['slug']);
