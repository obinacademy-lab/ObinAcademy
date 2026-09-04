<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/enroll_panel.php';
require __DIR__ . '/includes/enrollment.php';

$user = current_user();
$slug = query_param('slug');
$course = get_course_by_slug($slug);
if (!$course) { http_response_code(404); exit('Course not found'); }

$isOwner = $user && (int) $user['id'] === (int) $course['creator_user_id'];
$enrollment = $user
    ? db_one('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $course['id']])
    : guest_enrollment_for_course((int) $course['id']);

if (!$enrollment && !$isOwner) redirect('/courses/view.php?slug=' . $slug);
$isGuest = !$user && !$isOwner;

$isExpired = !$isOwner && $enrollment && $enrollment['expires_at'] !== null && strtotime($enrollment['expires_at']) < time();

if ($isExpired) {
    $pageTitle = 'Access Expired — Obin Academy';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container" style="max-width:440px; padding: 90px 20px; text-align:center;">
      <div style="font-size:40px;">🔒</div>
      <h1 class="h3" style="margin-top:16px;">Access Expired</h1>
      <p class="muted" style="margin-top:10px;">
        Your access to "<?= e($course['title']) ?>" expired on <?= e(format_date($enrollment['expires_at'])) ?>.
        Purchase the course again to keep learning.
      </p>
      <a href="<?= e(base_url('courses/view.php?slug=' . $slug)) ?>" class="btn btn-primary" style="margin-top:20px;">View Course</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isPremium = $isOwner || ($enrollment && (bool) $enrollment['is_premium']);
// Premium upgrade needs an account today (its payment API is login-gated), so
// hide the upsell for guests rather than show a button that would 401.
$canUpgrade = !$isOwner && !$isGuest && !empty($course['premium_price']) && !$isPremium;
// A free course the creator never set a premium download price on has no
// paywall to protect — let downloads through without requiring a premium
// upgrade that doesn't exist. Any course with a premium price stays gated
// behind $isPremium regardless of whether the course itself is free or paid.
$canDownloadFiles = $isPremium || ((float) $course['price'] <= 0 && empty($course['premium_price']));
$expiresAt = $enrollment['expires_at'] ?? null;
$progress = (float) ($enrollment['progress'] ?? 0);

$allLessons = [];
foreach ($course['modules'] as $module) {
    foreach ($module['lessons'] as $lesson) $allLessons[] = $lesson;
}
$totalLessons = count($allLessons);

$pageTitle = $course['title'] . ' — Learn — Obin Academy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="learn-shell">
  <div class="learn-top">
    <div class="row gap-2">
      <button data-learn-open aria-label="Open lessons" class="learn-mobile-toggle">☰</button>
      <a href="<?= e($user ? base_url('dashboard/learner/index.php') : base_url('courses/view.php?slug=' . $course['slug'])) ?>" class="back">← <?= $user ? 'Dashboard' : 'Course Details' ?></a>
    </div>
    <span class="small" style="font-weight:700;"><?= e($course['title']) ?></span>
    <span class="progress-label" data-progress-label><?= round($progress) ?>% complete</span>
  </div>

  <div class="learn-body">
    <div class="learn-overlay" data-learn-overlay data-learn-close></div>
    <aside class="learn-sidebar" data-learn-sidebar>
      <button class="learn-sidebar-close" data-learn-close aria-label="Close">✕</button>
      <div class="learn-progress-track">
        <div class="progress-track"><div class="progress-fill" data-progress-fill style="width:<?= round($progress) ?>%;"></div></div>
      </div>
      <?php foreach ($course['modules'] as $mi => $module): ?>
        <div class="learn-module-title">Module <?= $mi + 1 ?>: <?= e($module['title']) ?></div>
        <?php foreach ($module['lessons'] as $li => $lesson):
          $globalIndex = array_search($lesson, $allLessons, true); ?>
          <button type="button" class="learn-lesson-btn" data-lesson-btn
            data-lesson-id="<?= (int) $lesson['id'] ?>"
            data-lesson-type="<?= e($lesson['type']) ?>"
            data-lesson-title="<?= e($lesson['title']) ?>"
            data-lesson-index="<?= $globalIndex ?>"
            <?= $globalIndex === 0 ? 'aria-current="true"' : '' ?>>
            <?= $lesson['type'] === 'VIDEO' ? '▶' : '📄' ?> <span><?= e($lesson['title']) ?></span>
          </button>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </aside>

    <main class="learn-main">
      <div class="learn-main-inner">
        <?php if ($expiresAt): ?>
          <div class="expiry-banner">Access expires on <?= e(format_date($expiresAt)) ?></div>
        <?php endif; ?>

        <div class="certificate-banner hidden" data-certificate-banner>
          <span>🎉 Congratulations — you've completed this course!</span>
          <a href="#" data-certificate-link class="btn btn-gold btn-sm" target="_blank" rel="noopener">View Your Certificate →</a>
        </div>

        <div class="learn-video-wrap" data-video-wrap oncontextmenu="return false;"></div>

        <div class="row between wrap gap-3" style="margin-top:24px;">
          <div>
            <span class="eyebrow" data-lesson-counter>Lesson 1 of <?= $totalLessons ?></span>
            <h1 class="h3" style="margin-top:6px;" data-lesson-heading></h1>
          </div>
          <div class="row gap-2">
            <a href="#" class="btn btn-outline hidden" data-download-link>⬇ Download</a>
            <button class="btn btn-primary" data-mark-complete>✓ Mark Complete</button>
          </div>
        </div>

        <?php if ($canUpgrade): ?>
          <div class="card card-pad row between wrap gap-3" style="margin-top:24px; border-color: color-mix(in srgb, var(--accent) 30%, var(--border));">
            <div>
              <h3 class="small" style="font-weight:700;">Want to download this course?</h3>
              <p class="small muted" style="margin-top:4px;">Upgrade to premium for <?= e(format_money((float) $course['premium_price'])) ?> and download every video and PDF.</p>
            </div>
            <div data-payment-widget
                 data-course-id="<?= (int) $course['id'] ?>"
                 data-initiate-url="<?= e(base_url('api/initiate-premium-upgrade.php')) ?>">
              <div data-state="idle"><button class="btn btn-primary" data-action="start">✨ Upgrade to Premium</button></div>
              <div data-state="phone" class="hidden row gap-2">
                <input type="tel" placeholder="e.g. 0772 123 456" data-phone-input style="width:190px;">
                <button class="btn btn-primary btn-sm" data-action="pay">Pay</button>
              </div>
              <div data-state="waiting" class="hidden small muted row gap-2"><span class="spinner" style="width:16px;height:16px;"></span> <span data-status-text></span></div>
              <div data-state="success" class="hidden small" style="color:var(--success); font-weight:700;">✓ Premium unlocked</div>
              <div data-state="failed" class="hidden small" style="color:var(--danger);">
                <span data-fail-text></span> <button data-action="retry" style="text-decoration:underline; background:none; border:none; color:var(--danger); font-weight:700;">Try Again</button>
              </div>
              <p class="error-text hidden" data-error></p>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<script>
  window.OBIN_LESSONS = <?= json_encode(array_map(fn($l) => ['id' => (int) $l['id'], 'type' => $l['type'], 'title' => $l['title']], $allLessons)) ?>;
  window.OBIN_STREAM_BASE = <?= json_encode(base_url('stream.php')) ?>;
  window.OBIN_UPDATE_PROGRESS_URL = <?= json_encode(base_url('api/update-progress.php')) ?>;
  window.OBIN_CERTIFICATE_URL_BASE = <?= json_encode(base_url('certificate.php')) ?>;
  window.OBIN_COURSE_ID = <?= (int) $course['id'] ?>;
  window.OBIN_CAN_DOWNLOAD = <?= $canDownloadFiles ? 'true' : 'false' ?>;
  window.OBIN_INITIAL_PROGRESS = <?= json_encode($progress) ?>;
</script>
<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/learn.js')) ?>"></script>
</body>
</html>
