<?php
require __DIR__ . '/../../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$logs = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 200');

$pageTitle = 'Audit Log — Admin — Obin Academy';
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<h1 class="h2">Audit Log</h1>
<p class="muted" style="margin-top:6px;">Every admin action taken on the platform, most recent first.</p>

<div class="table-wrap" style="margin-top:20px;">
  <table>
    <thead><tr><th>Actor</th><th>Action</th><th>Target</th><th>Detail</th><th>When</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= e($l['actor_name']) ?></td>
          <td><?= e($l['action']) ?></td>
          <td><?= e($l['target_type']) ?>: <?= e($l['target_label']) ?></td>
          <td class="small muted"><?= e($l['detail'] ?? '') ?></td>
          <td class="small muted"><?= e(format_date($l['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="5" class="muted">No actions logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
