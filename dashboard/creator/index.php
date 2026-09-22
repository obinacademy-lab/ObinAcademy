<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['CREATOR', 'ADMIN']);

$courses = db_all('
    SELECT c.*, cat.name AS category_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
    FROM courses c JOIN categories cat ON cat.id = c.category_id
    WHERE c.creator_id = ? ORDER BY c.created_at DESC
', [$user['id']]);

$totalEarnings = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id = ?', [$user['id']])['n'] ?? 0);
$publishedCount = 0;
$totalEnrollments = 0;
foreach ($courses as $c) {
    if ($c['status'] === 'PUBLISHED') $publishedCount++;
    $totalEnrollments += (int) $c['student_count'];
}

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statusLabel = ['DRAFT' => 'Draft', 'PENDING_REVIEW' => 'Pending Review', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed by Admin'];

$hasSocialLinks = $user['facebook_url'] || $user['instagram_url'] || $user['youtube_url'] || $user['tiktok_url'] || $user['linkedin_url'];

$pageTitle = 'My Courses — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-hero reveal">
  <div>
    <h1 class="h2" style="color:#fff;">Welcome back, <?= e(explode(' ', trim($user['name']))[0]) ?></h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72);">Manage your courses and track how they're performing.</p>
  </div>
  <a href="<?= e(base_url('dashboard/creator/course-new.php')) ?>" class="btn btn-gold" style="border-radius:999px;">+ Create Course</a>
</div>

<?php if (!$hasSocialLinks): ?>
  <div class="card card-pad row between wrap gap-3 reveal" style="margin-top:20px; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 14%, var(--dash-panel)), var(--dash-panel)); border-color: var(--dash-border);">
    <div class="row gap-2" style="align-items:center;">
      <span class="icon-badge" style="--tint:#2563eb;"><?php dash_icon('share'); ?></span>
      <div>
        <h3 class="small" style="font-weight:700;">Connect your social accounts</h3>
        <p class="small muted" style="margin-top:2px;">Add your Facebook, Instagram, YouTube, TikTok, and LinkedIn — they'll show right on your course pages so learners can follow you.</p>
      </div>
    </div>
    <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="btn btn-outline btn-sm" style="white-space:nowrap;">Add Links</a>
  </div>
<?php endif; ?>

<div class="grid md:grid-3" style="margin-top:24px;">
  <div class="stat-card accent-top reveal" data-hoverable="true" style="--hover-color:#f5b301;"><div class="icon"><?php dash_icon('banknote'); ?></div><div class="value"><?= e(format_money($totalEarnings)) ?></div><div class="label">Total Earnings</div></div>
  <div class="stat-card accent-top reveal reveal-delay-1" data-hoverable="true" style="--hover-color:#60a5fa;"><div class="icon"><?php dash_icon('book-open'); ?></div><div class="value" data-count-up data-count-value="<?= $publishedCount ?>" data-count-suffix="">0</div><div class="label">Published Courses</div></div>
  <div class="stat-card accent-top reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#34d399;"><div class="icon"><?php dash_icon('graduation-cap'); ?></div><div class="value" data-count-up data-count-value="<?= $totalEnrollments ?>" data-count-suffix="">0</div><div class="label">Total Enrollments</div></div>
</div>

<?php if (!$courses): ?>
  <div class="card card-pad reveal" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">You haven't created any courses yet.</p>
    <a href="<?= e(base_url('dashboard/creator/course-new.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Create Your First Course</a>
  </div>
<?php else: ?>
  <div class="activity-feed reveal" style="margin-top:24px;">
    <?php foreach ($courses as $c): ?>
      <div class="list-row">
        <div class="list-row-main">
          <span class="activity-dot tone-neutral" style="flex-shrink:0;"><?php dash_icon('book-open'); ?></span>
          <div style="min-width:0;">
            <div style="font-weight:700;"><?= e($c['title']) ?></div>
            <div class="small muted" style="margin-top:2px;"><?= e($c['category_name']) ?> &middot; <?= e(format_money((float) $c['price'])) ?> &middot; <?= (int) $c['student_count'] ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?></div>
          </div>
        </div>
        <div class="list-row-meta">
          <span class="badge <?= $badgeClass[$c['status']] ?>"><?= $statusLabel[$c['status']] ?></span>
          <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $c['id'])) ?>" class="btn btn-dark btn-sm">Manage</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
