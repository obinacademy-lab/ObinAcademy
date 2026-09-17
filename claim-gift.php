<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/gifts.php';

$token = query_param('token');
$gift = $token !== '' ? get_gift_by_token($token) : null;

if (!$gift) {
    $pageTitle = 'Invalid Gift Link — Obin Academy';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="max-width:440px; padding: 90px 20px; text-align:center;">
      <div style="font-size:40px;">🎁</div>
      <h1 class="h3" style="margin-top:16px;">Invalid or Expired Link</h1>
      <p class="muted" style="margin-top:10px;">This gift link isn't valid. If someone told you they gifted you a course, check your email for the correct link.</p>
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:20px;">Explore Courses</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$user = current_user();
$redirectTo = '/claim-gift.php?token=' . urlencode($token);

if ($gift['status'] === 'CLAIMED' && (!$user || (int) $gift['claimed_by_user_id'] !== (int) $user['id'])) {
    $pageTitle = 'Gift Already Claimed — Obin Academy';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="max-width:440px; padding: 90px 20px; text-align:center;">
      <div style="font-size:40px;">🎁</div>
      <h1 class="h3" style="margin-top:16px;">Already Claimed</h1>
      <p class="muted" style="margin-top:10px;">This gift for "<?= e($gift['course_title']) ?>" has already been claimed.</p>
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:20px;">Explore Courses</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($user && $gift['status'] === 'PENDING') {
    claim_course_gift((int) $user['id'], (int) $gift['id']);
    redirect('/learn.php?slug=' . $gift['course_slug']);
}

// Already claimed by this same logged-in user (e.g. they hit the link
// again later) — just take them straight to the course.
if ($user && $gift['status'] === 'CLAIMED') {
    redirect('/learn.php?slug=' . $gift['course_slug']);
}

$pageTitle = 'Claim Your Gift — Obin Academy';
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:480px; padding: 70px 20px; text-align:center;">
  <div style="font-size:40px;">🎁</div>
  <h1 class="h3" style="margin-top:16px;"><?= e($gift['buyer_name']) ?> gifted you a course!</h1>
  <p class="muted" style="margin-top:10px;">
    <strong><?= e($gift['course_title']) ?></strong> is waiting for you. Create a free account or log in to claim it — that's where your access, progress, and certificate will live.
  </p>
  <?php if ($gift['message']): ?>
    <div class="card card-pad" style="margin-top:20px; text-align:left; font-style:italic; color:var(--muted);">"<?= e($gift['message']) ?>"</div>
  <?php endif; ?>
  <a href="<?= e(base_url('signup.php?redirect=' . urlencode($redirectTo))) ?>" class="btn btn-gold btn-block btn-lg shine" style="margin-top:24px;">Sign Up to Claim</a>
  <p class="guest-note">Already have an account? <a href="<?= e(base_url('login.php?redirect=' . urlencode($redirectTo))) ?>">Log In to Claim</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
