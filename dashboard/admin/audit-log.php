<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$logs = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 200');

/** Buckets an audit_log action string into a visual tone for the activity feed. */
function activity_tone(string $action): string {
    if (str_contains($action, 'REJECTED')) return 'danger';
    if (str_contains($action, 'APPROVED') || str_contains($action, 'PUBLISHED')) return 'success';
    return 'neutral';
}
function activity_icon(string $action): string {
    if (str_contains($action, 'REJECTED')) return 'x-circle';
    if (str_contains($action, 'APPROVED') || str_contains($action, 'PUBLISHED')) return 'check-circle';
    return 'clock';
}

$pageTitle = 'Audit Log — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Audit Log</h1>
<p class="muted" style="margin-top:6px;">Every admin action taken on the platform, most recent first.</p>

<?php if (!$logs): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;">
    <p class="muted">No actions logged yet.</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:20px;">
    <?php foreach ($logs as $l): $tone = activity_tone($l['action']); ?>
      <div class="activity-row">
        <span class="activity-dot tone-<?= $tone ?>"><?php dash_icon(activity_icon($l['action'])); ?></span>
        <div class="activity-body">
          <div><strong><?= e($l['actor_name']) ?></strong> <?= e(strtolower(str_replace('_', ' ', $l['action']))) ?> <span class="muted"><?= e($l['target_type']) ?>: <?= e($l['target_label']) ?></span></div>
          <div class="small muted"><?= e(format_date($l['created_at'])) ?><?= $l['detail'] ? ' &middot; ' . e($l['detail']) : '' ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
