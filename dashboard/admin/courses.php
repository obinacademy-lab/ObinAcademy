<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$statusFilter = query_param('status');
$sql = 'SELECT c.*, cat.name AS category_name, u.name AS creator_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id) AS student_count
        FROM courses c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.creator_id';
$params = [];
if ($statusFilter) { $sql .= ' WHERE c.status = ?'; $params[] = $statusFilter; }
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

<div class="table-wrap" style="margin-top:20px;">
  <table>
    <thead><tr><th>Course</th><th>Creator</th><th>Price</th><th>Students</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($courses as $c): ?>
        <tr>
          <td><?= e($c['title']) ?></td>
          <td><?= e($c['creator_name']) ?></td>
          <td><?= e(format_money((float) $c['price'])) ?></td>
          <td><?= (int) $c['student_count'] ?></td>
          <td><span class="badge <?= $badgeClass[$c['status']] ?>"><?= $c['status'] ?></span></td>
          <td><a href="<?= e(base_url(($c['status'] === 'PENDING_REVIEW' ? 'dashboard/admin/course-review.php' : 'dashboard/creator/course-manage.php') . '?id=' . $c['id'])) ?>" class="btn btn-dark btn-sm">Open</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$courses): ?><tr><td colspan="6" class="muted">No courses match this filter.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
