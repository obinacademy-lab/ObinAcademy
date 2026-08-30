<?php
require __DIR__ . '/../../../includes/bootstrap.php';
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
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3">
  <div>
    <h1 class="h2">My Courses</h1>
    <p class="muted" style="margin-top:6px;">Manage your courses and track performance.</p>
  </div>
  <a href="<?= e(base_url('dashboard/creator/course-new.php')) ?>" class="btn btn-primary">+ Create Course</a>
</div>

<?php if (!$hasSocialLinks): ?>
  <div class="card card-pad row between wrap gap-3" style="margin-top:20px; background: color-mix(in srgb, var(--accent) 5%, white); border-color: color-mix(in srgb, var(--accent) 20%, var(--border));">
    <div class="row gap-2" style="align-items:center;">
      <span class="icon-badge" style="--tint:#2563eb; font-size:20px;">🔗</span>
      <div>
        <h3 class="small" style="font-weight:700;">Connect your social accounts</h3>
        <p class="small muted" style="margin-top:2px;">Add your Facebook, Instagram, YouTube, TikTok, and LinkedIn — they'll show right on your course pages so learners can follow you.</p>
      </div>
    </div>
    <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="btn btn-outline btn-sm" style="white-space:nowrap;">Add Links</a>
  </div>
<?php endif; ?>

<div class="grid md:grid-3" style="margin-top:24px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;"><div class="icon">💰</div><div class="value"><?= e(format_money($totalEarnings)) ?></div><div class="label">Total Earnings</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#3b82f6;"><div class="icon">📚</div><div class="value"><?= $publishedCount ?></div><div class="label">Published Courses</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#06b6d4;"><div class="icon">🎓</div><div class="value"><?= $totalEnrollments ?></div><div class="label">Total Enrollments</div></div>
</div>

<?php if (!$courses): ?>
  <div class="card card-pad" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">You haven't created any courses yet.</p>
    <a href="<?= e(base_url('dashboard/creator/course-new.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Create Your First Course</a>
  </div>
<?php else: ?>
  <div class="table-wrap" style="margin-top:24px;">
    <table>
      <thead><tr><th>Course</th><th>Price</th><th>Students</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td><div style="font-weight:600;"><?= e($c['title']) ?></div><div class="small muted"><?= e($c['category_name']) ?></div></td>
            <td><?= e(format_money((float) $c['price'])) ?></td>
            <td><?= (int) $c['student_count'] ?></td>
            <td><span class="badge <?= $badgeClass[$c['status']] ?>"><?= $statusLabel[$c['status']] ?></span></td>
            <td><a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $c['id'])) ?>" class="btn btn-dark btn-sm">Manage</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
