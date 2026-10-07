<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$courses = db_all('
    SELECT c.*, cat.name AS category_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
    FROM courses c JOIN categories cat ON cat.id = c.category_id
    WHERE c.creator_id = ? ORDER BY c.created_at DESC
', [$user['id']]);

$totalEarnings = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id = ?', [$user['id']])['n'] ?? 0);
$last30 = array_sum(array_column(get_creator_daily_earnings_series((int) $user['id'], 30), 'collected'));

$publishedCount = 0;
$totalEnrollments = 0;
$totalViews = 0;
foreach ($courses as $c) {
    if ($c['status'] === 'PUBLISHED') $publishedCount++;
    $totalEnrollments += (int) $c['student_count'];
    $totalViews += (int) $c['view_count'];
}

$statusLabel = ['DRAFT' => 'Draft', 'PENDING_REVIEW' => 'Pending review', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed by admin'];
$statusTone = ['DRAFT' => 'draft', 'PENDING_REVIEW' => 'warn', 'PUBLISHED' => 'good', 'REJECTED' => 'bad', 'REMOVED' => 'bad'];

$hasSocialLinks = $user['facebook_url'] || $user['instagram_url'] || $user['youtube_url'] || $user['tiktok_url'] || $user['linkedin_url'];
$studioName = trim((string) ($user['school_name'] ?? '')) !== '' ? $user['school_name'] : $user['name'];

$pageTitle = 'Studio — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="st-head reveal">
  <div>
    <h1 class="st-title"><?= e($studioName) ?> Studio</h1>
    <p class="st-sub">Your creator dashboard</p>
  </div>
  <a href="<?= e(base_url('profile.php?id=' . $user['id'])) ?>" class="st-btn st-btn-ghost"><?php dash_icon('globe'); ?>View public school</a>
</div>

<?php if (!$hasSocialLinks): ?>
  <div class="st-nudge reveal">
    <span class="st-nudge-ic"><?php dash_icon('share'); ?></span>
    <p class="st-nudge-tx"><b>Connect your social accounts</b>They show on your course pages so learners can follow you.</p>
    <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="st-btn st-btn-soft">Add links</a>
  </div>
<?php endif; ?>

<section class="st-stats reveal" aria-label="At a glance">
  <div class="st-card st-stat"><span class="st-ic tone-blue"><?php dash_icon('book-open'); ?></span><b><?= count($courses) ?></b><span>Course<?= count($courses) === 1 ? '' : 's' ?></span></div>
  <div class="st-card st-stat"><span class="st-ic tone-good"><?php dash_icon('trending-up'); ?></span><b><?= $publishedCount ?></b><span>Published</span></div>
  <div class="st-card st-stat"><span class="st-ic tone-lime"><?php dash_icon('users'); ?></span><b><?= number_format($totalEnrollments) ?></b><span>Student<?= $totalEnrollments === 1 ? '' : 's' ?></span></div>
  <div class="st-card st-stat"><span class="st-ic tone-warn"><?php dash_icon('eye'); ?></span><b><?= number_format($totalViews) ?></b><span>Course view<?= $totalViews === 1 ? '' : 's' ?></span></div>
</section>

<section class="st-card st-earn reveal" aria-label="Earnings">
  <span class="st-earn-ic"><?php dash_icon('banknote'); ?></span>
  <div><small>My all-time earnings</small><b><?= e(format_money($totalEarnings)) ?></b></div>
  <div class="st-earn-mid"><div><small>Last 30 days</small><b><?= e(format_money($last30)) ?></b></div></div>
  <a class="st-more" href="<?= e(base_url('dashboard/creator/earnings.php')) ?>">View details <?php dash_icon('chevron-right'); ?></a>
</section>

<section class="st-card reveal" aria-labelledby="st-courses">
  <div class="st-sec-h">
    <div><h2 id="st-courses">Your courses</h2><small><?= count($courses) ?> total</small></div>
    <a href="<?= e(base_url('dashboard/creator/course-build.php')) ?>" class="st-btn st-btn-lime"><?php dash_icon('plus-circle'); ?>New course</a>
  </div>
  <?php if (!$courses): ?>
    <div class="st-empty">
      <?php dash_icon('book-open'); ?>
      <p>No courses yet. Create your first course to start earning.</p>
      <a class="st-more" href="<?= e(base_url('dashboard/creator/course-build.php')) ?>">Create your first course <?php dash_icon('arrow-right'); ?></a>
    </div>
  <?php else: ?>
    <?php foreach ($courses as $c): $draft = $c['status'] === 'DRAFT'; ?>
      <div class="st-row">
        <div class="st-th">
          <?php if (!empty($c['thumbnail_url'])): ?>
            <img src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="" loading="lazy">
          <?php else: ?>
            <span><?= e(mb_strimwidth($c['title'], 0, 18, '…')) ?></span>
          <?php endif; ?>
        </div>
        <div class="st-row-main">
          <div class="st-row-n"><?= e($c['title']) ?></div>
          <div class="st-row-m"><?= e($c['category_name']) ?> &middot; <?= (float) $c['price'] > 0 ? e(format_money((float) $c['price'])) : 'Free' ?> &middot; <?= (int) $c['student_count'] ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?> &middot; <?= number_format((int) $c['view_count']) ?> view<?= (int) $c['view_count'] === 1 ? '' : 's' ?></div>
        </div>
        <div class="st-row-act">
          <span class="st-pill tone-<?= e($statusTone[$c['status']] ?? 'draft') ?>"><?= e($statusLabel[$c['status']] ?? $c['status']) ?></span>
          <?php if ($draft): ?>
            <a href="<?= e(base_url('dashboard/creator/course-build.php?id=' . $c['id'])) ?>" class="st-btn st-btn-blue">Continue</a>
          <?php else: ?>
            <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $c['id'])) ?>" class="st-btn st-btn-blue">Manage</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="st-quick reveal" aria-label="Shortcuts">
  <a class="st-card st-q" href="<?= e(base_url('dashboard/creator/course-build.php')) ?>"><span class="st-ic tone-blue"><?php dash_icon('book-open'); ?></span><h3>Create a course</h3><p>Upload your lessons, set a price and publish.</p><span class="st-more">Start building <?php dash_icon('chevron-right'); ?></span></a>
  <a class="st-card st-q" href="<?= e(base_url('dashboard/creator/students.php')) ?>"><span class="st-ic tone-lime"><?php dash_icon('users'); ?></span><h3>Your students</h3><p>See who joined and how far they have got.</p><span class="st-more">View students <?php dash_icon('chevron-right'); ?></span></a>
  <a class="st-card st-q" href="<?= e(base_url('dashboard/creator/viewers.php')) ?>"><span class="st-ic tone-warn"><?php dash_icon('eye'); ?></span><h3>Course viewers</h3><p>Reach out to people who looked at your courses.</p><span class="st-more">See viewers <?php dash_icon('chevron-right'); ?></span></a>
  <a class="st-card st-q" href="<?= e(base_url('dashboard/creator/earnings.php')) ?>"><span class="st-ic tone-good"><?php dash_icon('wallet'); ?></span><h3>Earnings</h3><p>Track sales, growth and your payout history.</p><span class="st-more">View earnings <?php dash_icon('chevron-right'); ?></span></a>
</section>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
