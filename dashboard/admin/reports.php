<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/community.php';
require __DIR__ . '/../../includes/audit.php';
$user = require_role(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $reportId = (int) post('reportId');
    $action = post('_action');

    if ($action === 'dismiss') {
        if (dismiss_report($reportId)) {
            log_admin_action((int) $user['id'], $user['name'], 'community_report.dismissed', 'Report', "#$reportId");
        }
    } elseif ($action === 'remove') {
        if (resolve_report_remove_content($reportId, (int) $user['id'])) {
            log_admin_action((int) $user['id'], $user['name'], 'community_report.content_removed', 'Report', "#$reportId");
        } else {
            flash_set('error', 'Could not remove that content — it may already be gone. Refresh and check the report again.');
        }
    }
    redirect('/dashboard/admin/reports.php');
}

$reports = get_pending_reports(50);
$reportRows = [];
foreach ($reports as $r) {
    $target = get_report_target($r);
    $reportRows[] = ['report' => $r, 'target' => $target];
}

$resolvedCount = (int) db_one("SELECT COUNT(*) AS n FROM community_reports WHERE status != 'pending'")['n'];

$pageTitle = 'Reports — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3" style="align-items:flex-end;">
  <div>
    <h1 class="h2">Content Reports</h1>
    <p class="muted" style="margin-top:6px;"><?= count($reports) ?> pending · <?= $resolvedCount ?> resolved to date</p>
  </div>
</div>

<?php if (!$reportRows): ?>
  <div class="card card-pad" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">No pending reports. All clear 🎉</p>
  </div>
<?php else: ?>
  <div class="stack gap-3" style="margin-top:24px;">
    <?php foreach ($reportRows as $row): $r = $row['report']; $t = $row['target']; ?>
      <div class="card card-pad">
        <div class="row between wrap gap-2" style="align-items:flex-start;">
          <div>
            <span class="role-badge"><?= $r['reportable_type'] === 'post' ? 'Post' : 'Comment' ?></span>
            <span class="small muted" style="margin-left:8px;">Reported by <?= e($r['reporter_name']) ?> · <?= e(time_ago($r['created_at'])) ?></span>
          </div>
        </div>

        <div class="small" style="margin-top:10px;"><strong>Reason:</strong> <?= e($r['reason']) ?></div>

        <?php if ($t): ?>
          <div class="card card-pad" style="margin-top:12px; background:var(--dash-tint, var(--surface));">
            <div class="small muted">By <?= e($t['author_name']) ?></div>
            <p style="margin-top:4px; font-size:13.5px; line-height:1.6;"><?= e(mb_strimwidth($t['body'], 0, 240, '…')) ?></p>
            <a href="<?= e(base_url(ltrim($t['link'], '/'))) ?>" target="_blank" rel="noopener noreferrer" class="small" style="color:var(--accent); font-weight:600;">View in context →</a>
          </div>
        <?php else: ?>
          <p class="small muted" style="margin-top:10px;">This content has already been deleted.</p>
        <?php endif; ?>

        <div class="row gap-2" style="margin-top:14px;">
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="reportId" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="_action" value="dismiss">
            <button type="submit" class="btn btn-outline btn-sm">Dismiss</button>
          </form>
          <?php if ($t): ?>
            <form method="post" onsubmit="return confirm('Remove this content? This cannot be undone.');"><?= csrf_field() ?>
              <input type="hidden" name="reportId" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="_action" value="remove">
              <button type="submit" class="btn btn-sm" style="background:#dc2626; color:#fff;">Remove Content</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
