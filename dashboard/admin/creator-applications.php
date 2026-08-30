<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/email.php';
$user = require_role(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) post('applicationId');
    $action = post('_action');
    $app = db_one('SELECT a.*, u.name AS applicant_name, u.email AS applicant_email FROM creator_applications a JOIN users u ON u.id=a.user_id WHERE a.id=?', [$id]);

    if ($app && $app['status'] === 'PENDING' && $action === 'approve') {
        db()->beginTransaction();
        db_run("UPDATE creator_applications SET status='APPROVED', reviewed_at=NOW() WHERE id=?", [$id]);
        db_run("UPDATE users SET role='CREATOR' WHERE id=?", [$app['user_id']]);
        db()->commit();
        log_admin_action((int) $user['id'], $user['name'], 'creator_application.approved', 'User', $app['applicant_name']);
        send_creator_application_approved_email($app['applicant_email'], $app['applicant_name']);
    } elseif ($app && $app['status'] === 'PENDING' && $action === 'reject') {
        db_run("UPDATE creator_applications SET status='REJECTED', reviewed_at=NOW(), rejection_reason=? WHERE id=?", [post('rejectionReason'), $id]);
        log_admin_action((int) $user['id'], $user['name'], 'creator_application.rejected', 'User', $app['applicant_name']);
    }
    redirect('/dashboard/admin/creator-applications.php');
}

$pending = db_all("SELECT a.*, u.name, u.email FROM creator_applications a JOIN users u ON u.id=a.user_id WHERE a.status='PENDING' ORDER BY a.created_at ASC");
$resolved = db_all("SELECT a.*, u.name, u.email FROM creator_applications a JOIN users u ON u.id=a.user_id WHERE a.status!='PENDING' ORDER BY a.reviewed_at DESC LIMIT 30");

$approvedTotal = (int) db_one("SELECT COUNT(*) AS n FROM creator_applications WHERE status='APPROVED'")['n'];
$rejectedTotal = (int) db_one("SELECT COUNT(*) AS n FROM creator_applications WHERE status='REJECTED'")['n'];
$decidedTotal = $approvedTotal + $rejectedTotal;
$approvalRate = $decidedTotal > 0 ? round(($approvedTotal / $decidedTotal) * 100) : null;

$pageTitle = 'Creator Applications — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3" style="align-items:flex-end;">
  <div>
    <h1 class="h2">Creator Applications</h1>
    <p class="muted" style="margin-top:6px;">Review requests from learners who want to start teaching.</p>
  </div>
</div>

<div class="grid md:grid-4" style="margin-top:20px; gap:14px;">
  <div class="mini-stat" style="--tint:#f59e0b;"><span class="mini-stat-value"><?= count($pending) ?></span><span class="mini-stat-label">Pending</span></div>
  <div class="mini-stat" style="--tint:#16a34a;"><span class="mini-stat-value"><?= $approvedTotal ?></span><span class="mini-stat-label">Approved</span></div>
  <div class="mini-stat" style="--tint:#dc2626;"><span class="mini-stat-value"><?= $rejectedTotal ?></span><span class="mini-stat-label">Rejected</span></div>
  <div class="mini-stat" style="--tint:#2563eb;"><span class="mini-stat-value"><?= $approvalRate !== null ? $approvalRate . '%' : '—' ?></span><span class="mini-stat-label">Approval Rate</span></div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Pending Review</h3>
<?php if (!$pending): ?>
  <div class="all-caught-up" style="margin-top:14px;">
    <?php dash_icon('check-circle'); ?>
    <div><strong>Nothing pending.</strong> Every creator application has been reviewed.</div>
  </div>
<?php else: ?>
  <div class="stack gap-3" style="margin-top:14px;">
    <?php foreach ($pending as $a): $daysWaiting = (int) floor((time() - strtotime($a['created_at'])) / 86400); ?>
      <div class="application-card">
        <div class="application-head">
          <div class="row gap-2" style="align-items:center;">
            <div class="row-avatar" style="--tint:#f59e0b; background:color-mix(in srgb, var(--tint) 15%, white); color:var(--tint); width:42px; height:42px; font-size:15px;"><?= e(mb_substr($a['name'], 0, 1)) ?></div>
            <div>
              <div style="font-weight:700;"><?= e($a['name']) ?></div>
              <div class="small muted"><?= e($a['email']) ?></div>
            </div>
          </div>
          <span class="waiting-badge <?= $daysWaiting >= 3 ? 'urgent' : '' ?>"><?php dash_icon('clock'); ?><?= $daysWaiting <= 0 ? 'Today' : $daysWaiting . ' day' . ($daysWaiting === 1 ? '' : 's') . ' waiting' ?></span>
        </div>

        <div class="application-body">
          <div class="application-field"><span class="label">Expertise</span><p><?= e($a['expertise']) ?></p></div>
          <div class="application-field"><span class="label">Motivation</span><p><?= e($a['motivation']) ?></p></div>
        </div>

        <div class="row gap-2" style="margin-top:16px;">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="approve"><input type="hidden" name="applicationId" value="<?= (int) $a['id'] ?>">
            <button class="btn btn-primary btn-sm"><?php dash_icon('check-circle', 'btn-icon'); ?>Approve</button>
          </form>
          <details class="reject-details">
            <summary class="btn btn-outline btn-sm reject-summary"><?php dash_icon('x-circle', 'btn-icon'); ?>Reject</summary>
            <form method="post" class="stack gap-2" style="margin-top:10px;">
              <?= csrf_field() ?><input type="hidden" name="_action" value="reject"><input type="hidden" name="applicationId" value="<?= (int) $a['id'] ?>">
              <textarea name="rejectionReason" rows="2" placeholder="Reason (optional, for your internal records)"></textarea>
              <button class="btn btn-outline btn-sm" style="width:fit-content; color:var(--danger); border-color:var(--danger);">Confirm Rejection</button>
            </form>
          </details>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:36px;">Recent Decisions</h3>
<div class="leaderboard" style="margin-top:14px;">
  <?php foreach ($resolved as $a): $approved = $a['status'] === 'APPROVED'; $tint = $approved ? '#16a34a' : '#dc2626'; ?>
    <div class="leaderboard-row">
      <div class="row-avatar" style="--tint:<?= $tint ?>; background:color-mix(in srgb, var(--tint) 15%, white); color:var(--tint);"><?php dash_icon($approved ? 'check-circle' : 'x-circle'); ?></div>
      <div class="leaderboard-info">
        <div style="font-weight:600;"><?= e($a['name']) ?></div>
        <div class="small muted"><?= e($a['email']) ?></div>
      </div>
      <div style="text-align:right; flex-shrink:0;">
        <span class="badge <?= $approved ? 'badge-published' : 'badge-rejected' ?>"><?= $a['status'] ?></span>
        <div class="small muted" style="margin-top:4px;"><?= e(format_date($a['reviewed_at'])) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$resolved): ?><p class="muted small" style="padding:16px 0;">No decisions made yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
