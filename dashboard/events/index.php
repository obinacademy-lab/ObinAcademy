<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_login();

$events = db_all('
    SELECT c.*, cat.name AS category_name,
      (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS ticket_count,
      (SELECT COUNT(*) FROM course_shares cs WHERE cs.course_id = c.id) AS share_count
    FROM courses c JOIN categories cat ON cat.id = c.category_id
    WHERE c.creator_id = ? AND c.type = \'EVENT\' ORDER BY c.event_starts_at ASC
', [$user['id']]);

$totalEarnings = (float) (db_one("
    SELECT COALESCE(SUM(e.amount),0) AS n FROM earnings e JOIN courses c ON c.id = e.course_id
    WHERE e.creator_id = ? AND c.type = 'EVENT'
", [$user['id']])['n'] ?? 0);
$publishedCount = 0;
$totalTickets = 0;
$totalViews = 0;
foreach ($events as $ev) {
    if ($ev['status'] === 'PUBLISHED') $publishedCount++;
    $totalTickets += (int) $ev['ticket_count'];
    $totalViews += (int) $ev['view_count'];
}

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statusLabel = ['DRAFT' => 'Draft', 'PENDING_REVIEW' => 'Pending Review', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed by Admin'];

$pageTitle = 'My Events — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3 reveal">
  <div>
    <h1 class="h2">My Events</h1>
    <p class="muted" style="margin-top:6px;">Create and manage events, and sell tickets to them.</p>
  </div>
  <a href="<?= e(base_url('dashboard/events/new.php')) ?>" class="btn btn-primary">+ Create Event</a>
</div>

<div class="grid md:grid-2 lg:grid-4" style="margin-top:24px;">
  <div class="stat-card reveal" data-hoverable="true" style="--hover-color:#f5b301;"><div class="icon"><?php dash_icon('banknote'); ?></div><div class="value"><?= e(format_money($totalEarnings)) ?></div><div class="label">Total Earnings</div></div>
  <div class="stat-card reveal reveal-delay-1" data-hoverable="true" style="--hover-color:#60a5fa;"><div class="icon"><?php dash_icon('calendar'); ?></div><div class="value" data-count-up data-count-value="<?= $publishedCount ?>" data-count-suffix="">0</div><div class="label">Published Events</div></div>
  <div class="stat-card reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#34d399;"><div class="icon"><?php dash_icon('graduation-cap'); ?></div><div class="value" data-count-up data-count-value="<?= $totalTickets ?>" data-count-suffix="">0</div><div class="label">Tickets Sold</div></div>
  <div class="stat-card reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#8b5cf6;"><div class="icon"><?php dash_icon('eye'); ?></div><div class="value" data-count-up data-count-value="<?= $totalViews ?>" data-count-suffix="">0</div><div class="label">Total Views</div></div>
</div>

<?php if (!$events): ?>
  <div class="card card-pad reveal" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">You haven't created any events yet.</p>
    <a href="<?= e(base_url('dashboard/events/new.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Create Your First Event</a>
  </div>
<?php else: ?>
  <div class="table-wrap reveal" style="margin-top:24px;">
    <table>
      <thead><tr><th>Event</th><th>When</th><th>Price</th><th>Tickets</th><th>Views</th><th>Shares</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($events as $ev): ?>
          <tr>
            <td><div style="font-weight:600;"><?= e($ev['title']) ?></div><div class="small muted"><?= e($ev['category_name']) ?></div></td>
            <td><?= $ev['event_starts_at'] ? e(format_date($ev['event_starts_at'])) : '—' ?></td>
            <td><?= e(format_money((float) $ev['price'])) ?></td>
            <td><?= (int) $ev['ticket_count'] ?><?= $ev['ticket_capacity'] !== null ? ' / ' . (int) $ev['ticket_capacity'] : '' ?></td>
            <td><?= number_format((int) $ev['view_count']) ?></td>
            <td><?= number_format((int) $ev['share_count']) ?></td>
            <td><span class="badge <?= $badgeClass[$ev['status']] ?>"><?= $statusLabel[$ev['status']] ?></span></td>
            <td><a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $ev['id'])) ?>" class="btn btn-dark btn-sm">Manage</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
