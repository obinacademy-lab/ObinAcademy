<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$statusFilter = query_param('status');
$sql = "SELECT c.*, cat.name AS category_name, u.name AS creator_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id) AS student_count
        FROM courses c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.creator_id
        WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= ' AND c.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY c.created_at DESC';
$courses = db_all($sql, $params);

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statuses = ['' => 'All', 'PENDING_REVIEW' => 'Pending Review', 'PUBLISHED' => 'Published', 'DRAFT' => 'Draft', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed'];

$pageTitle = 'Courses — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Courses</h1>

<div class="row gap-2 wrap" style="margin-top:16px;">
  <?php foreach ($statuses as $val => $label): ?>
    <a href="<?= e(base_url('dashboard/admin/courses.php' . ($val ? '?status=' . $val : ''))) ?>" class="btn btn-sm <?= $statusFilter === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$courses): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;">
    <p class="muted">No courses match this filter.</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:20px;">
    <?php foreach ($courses as $c): ?>
      <div class="activity-row list-row">
        <div class="list-row-main">
          <span class="activity-dot" style="background:var(--dash-tint); color:var(--accent); flex-shrink:0;"><?php dash_icon('book-open'); ?></span>
          <div class="activity-body">
            <strong><?= e($c['title']) ?></strong>
            <div class="small muted" style="margin-top:2px;">
              <?= e($c['creator_name']) ?> &middot; <?= e(format_money((float) $c['price'])) ?> &middot; <?= (int) $c['student_count'] ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?>
            </div>
          </div>
        </div>
        <div class="list-row-meta">
          <span class="badge <?= $badgeClass[$c['status']] ?>"><?= e($statuses[$c['status']] ?? $c['status']) ?></span>
          <a href="<?= e(base_url(($c['status'] === 'PENDING_REVIEW' ? 'dashboard/admin/course-review.php' : 'dashboard/creator/course-manage.php') . '?id=' . $c['id'])) ?>" class="btn btn-dark btn-sm">Open</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
