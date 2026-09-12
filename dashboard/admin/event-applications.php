<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$statusFilter = query_param('status');
$sql = "SELECT c.*, cat.name AS category_name, u.name AS creator_name,
          (SELECT COUNT(*) FROM enrollments e WHERE e.course_id=c.id) AS ticket_count,
          (SELECT COUNT(*) FROM course_shares cs WHERE cs.course_id=c.id) AS share_count
        FROM courses c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.creator_id
        WHERE c.type = 'EVENT'";
$params = [];
if ($statusFilter) { $sql .= ' AND c.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY c.created_at DESC';
$events = db_all($sql, $params);

$totalTickets = 0;
$totalViews = 0;
$totalShares = 0;
foreach ($events as $ev) {
    $totalTickets += (int) $ev['ticket_count'];
    $totalViews += (int) $ev['view_count'];
    $totalShares += (int) $ev['share_count'];
}

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statuses = ['' => 'All', 'PENDING_REVIEW' => 'Pending Review', 'PUBLISHED' => 'Published', 'DRAFT' => 'Draft', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed'];

$pageTitle = 'Event Applications — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Event Applications</h1>
<p class="muted" style="margin-top:6px;">Events submitted by creators and learners, kept separate from course moderation.</p>

<div class="grid md:grid-3" style="margin-top:20px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#34d399;"><div class="icon"><?php dash_icon('graduation-cap'); ?></div><div class="value"><?= number_format($totalTickets) ?></div><div class="label">Tickets Sold (all events)</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#60a5fa;"><div class="icon"><?php dash_icon('eye'); ?></div><div class="value"><?= number_format($totalViews) ?></div><div class="label">Total Views</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;"><div class="icon"><?php dash_icon('share'); ?></div><div class="value"><?= number_format($totalShares) ?></div><div class="label">Total Shares</div></div>
</div>

<div class="row gap-2 wrap" style="margin-top:16px;">
  <?php foreach ($statuses as $val => $label): ?>
    <a href="<?= e(base_url('dashboard/admin/event-applications.php' . ($val ? '?status=' . $val : ''))) ?>" class="btn btn-sm <?= $statusFilter === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap" style="margin-top:20px;">
  <table>
    <thead><tr><th>Event</th><th>Organizer</th><th>When</th><th>Price</th><th>Tickets Sold</th><th>Views</th><th>Shares</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($events as $ev): ?>
        <tr>
          <td><?= e($ev['title']) ?></td>
          <td><?= e($ev['creator_name']) ?></td>
          <td><?= $ev['event_starts_at'] ? e(format_date($ev['event_starts_at'])) : '—' ?></td>
          <td><?= e(format_money((float) $ev['price'])) ?></td>
          <td><?= (int) $ev['ticket_count'] ?></td>
          <td><?= number_format((int) $ev['view_count']) ?></td>
          <td><?= number_format((int) $ev['share_count']) ?></td>
          <td><span class="badge <?= $badgeClass[$ev['status']] ?>"><?= $ev['status'] ?></span></td>
          <td><a href="<?= e(base_url(($ev['status'] === 'PENDING_REVIEW' ? 'dashboard/admin/course-review.php' : 'dashboard/creator/course-manage.php') . '?id=' . $ev['id'])) ?>" class="btn btn-dark btn-sm">Open</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$events): ?><tr><td colspan="9" class="muted">No events match this filter.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
