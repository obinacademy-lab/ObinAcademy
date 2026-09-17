<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/bundles.php';

$slug = query_param('slug');
$bundle = get_bundle_by_slug($slug);
if (!$bundle) { http_response_code(404); exit('Bundle not found'); }

$user = current_user();
$isOwner = $user && (int) $user['id'] === (int) $bundle['creator_user_id'];
$courses = get_bundle_courses((int) $bundle['id']);

$combinedPrice = array_sum(array_column($courses, 'price'));
$savings = $combinedPrice - (float) $bundle['price'];

$ownedCourseIds = [];
if ($user) {
    $rows = db_all('SELECT course_id FROM enrollments WHERE user_id = ?', [$user['id']]);
    $ownedCourseIds = array_column($rows, 'course_id');
}
$ownedCount = count(array_intersect(array_column($courses, 'id'), $ownedCourseIds));
$fullyOwned = $ownedCount === count($courses);

$schoolLabel = $bundle['creator_school_name'] ?: $bundle['creator_name'];
$pageTitle = $bundle['title'] . ' — Obin Academy';
$pageDescription = $bundle['description'] ?: ('A bundle of ' . count($courses) . ' courses from ' . $schoolLabel . ' on Obin Academy.');
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill"><?php dash_icon('layout-dashboard'); ?> Bundle · <?= count($courses) ?> Courses</span>
    <h1 style="margin-top:14px;"><?= e($bundle['title']) ?></h1>
    <?php if ($bundle['description']): ?><p class="summary"><?= e($bundle['description']) ?></p><?php endif; ?>
    <a href="<?= e(base_url('profile.php?id=' . $bundle['creator_user_id'])) ?>" class="hero-subline" style="display:inline-block; font-size:15px; margin-top:10px;">by <?= e($schoolLabel) ?></a>
  </div>
</section>

<div class="container" style="max-width:700px; padding-top:40px; padding-bottom:80px;">
  <h2 class="h3">What's Included</h2>
  <div class="stack gap-2" style="margin-top:16px;">
    <?php foreach ($courses as $c):
      $owned = in_array($c['id'], $ownedCourseIds, true);
    ?>
      <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" class="card card-pad row gap-3" style="align-items:center; text-decoration:none;">
        <div class="course-flat-thumb" style="width:80px; height:50px; flex-shrink:0; margin-top:0;">
          <?php if ($c['thumbnail_url']): ?><img src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="">
          <?php else: ?><div class="placeholder" style="font-size:10px;">Obin Academy</div><?php endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700; color:var(--ink);"><?= e($c['title']) ?></div>
          <div class="small muted"><?= e(format_money((float) $c['price'])) ?><?php if ($owned): ?> &middot; <span style="color:var(--success);">You own this</span><?php endif; ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="enroll-panel" style="margin-top:32px; max-width:none;">
    <div class="pad">
      <div class="price-row">
        <div class="price">
          <?php if ($savings > 0): ?><span class="price-strike"><?= e(format_money($combinedPrice)) ?></span><?php endif; ?>
          <span><?= e(format_money((float) $bundle['price'])) ?></span>
        </div>
        <?php if ($savings > 0): ?><span class="price-note">save <?= e(format_money($savings)) ?></span><?php endif; ?>
      </div>
      <div class="access-note">
        <?php dash_icon('book-open'); ?>
        Instant access to all <?= count($courses) ?> courses
      </div>

      <?php if ($isOwner): ?>
        <a href="<?= e(base_url('dashboard/creator/bundles.php')) ?>" class="btn btn-dark btn-block btn-lg" style="margin-top:20px;">Manage Bundles</a>
      <?php elseif ($fullyOwned): ?>
        <div class="alert alert-success" style="margin-top:20px; text-align:center;">You already own every course in this bundle.</div>
      <?php elseif (!$user): ?>
        <a href="<?= e(base_url('signup.php?redirect=' . urlencode('/bundle.php?slug=' . $slug))) ?>" class="btn btn-gold btn-block btn-lg shine" style="margin-top:20px;">Sign Up to Buy This Bundle</a>
        <p class="guest-note">A bundle purchase needs a free account first. <a href="<?= e(base_url('login.php?redirect=' . urlencode('/bundle.php?slug=' . $slug))) ?>">Already have an account? Log in</a></p>
      <?php else: ?>
        <div style="margin-top:20px;" data-payment-widget
             data-bundle-id="<?= (int) $bundle['id'] ?>"
             data-initiate-url="<?= e(base_url('api/initiate-bundle-purchase.php')) ?>"
             data-success-redirect="<?= e(base_url('bundle.php?slug=' . $slug)) ?>">
          <div data-state="idle">
            <button class="btn btn-gold btn-block btn-lg shine" data-action="start">
              <span class="pay-logo-pair">
                <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN"></span>
                <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel"></span>
              </span>
              Buy This Bundle
            </button>
          </div>
          <div data-state="phone" class="hidden guest-form">
            <div class="field-icon">
              <?php dash_icon('wallet'); ?>
              <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
            </div>
            <button class="btn btn-primary btn-block" data-action="pay">Pay <?= e(format_money((float) $bundle['price'])) ?></button>
          </div>
          <div data-state="waiting" class="hidden pay-waiting">
            <div class="spinner"></div>
            <p style="font-weight:700;">Waiting for approval...</p>
            <p class="small muted" data-status-text></p>
          </div>
          <div data-state="success" class="hidden pay-success">
            <p style="font-weight:700;">✓ Purchased!</p>
          </div>
          <div data-state="failed" class="hidden pay-failed">
            <p style="font-weight:700;">Payment not completed</p>
            <p class="small muted" data-fail-text></p>
            <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
          </div>
          <p class="error-text hidden" data-error></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
