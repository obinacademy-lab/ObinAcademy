<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
$user = require_role(['ADMIN']);

// Creator and affiliate withdrawal requests share one table (payee_type
// says which), so one payee_name/payee_email pair covers both — a creator
// row joins straight to users, an affiliate row joins through affiliates
// to reach its user. Never both null: app logic always sets exactly one of
// creator_id/affiliate_id at insert time.
const WITHDRAWAL_PAYEE_SELECT = "
    w.*,
    COALESCE(cu.name, au.name) AS payee_name,
    COALESCE(cu.email, au.email) AS payee_email
    FROM withdrawal_requests w
    LEFT JOIN users cu ON cu.id = w.creator_id
    LEFT JOIN affiliates a ON a.id = w.affiliate_id
    LEFT JOIN users au ON au.id = a.user_id
";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) post('withdrawalId');
    $action = post('_action');
    $w = db_one('SELECT ' . WITHDRAWAL_PAYEE_SELECT . ' WHERE w.id=?', [$id]);

    if ($w && $w['status'] === 'PENDING' && $action === 'approve') {
        db_run("UPDATE withdrawal_requests SET status='APPROVED', resolved_at=NOW() WHERE id=?", [$id]);
        log_admin_action((int) $user['id'], $user['name'], 'withdrawal.approved', 'Withdrawal', $w['payee_name'], format_money((float) $w['amount']));
        send_withdrawal_approved_email($w['payee_email'], (float) $w['amount']);
    } elseif ($w && $w['status'] === 'PENDING' && $action === 'reject') {
        db_run("UPDATE withdrawal_requests SET status='REJECTED', resolved_at=NOW(), note=? WHERE id=?", [post('note'), $id]);
        log_admin_action((int) $user['id'], $user['name'], 'withdrawal.rejected', 'Withdrawal', $w['payee_name']);
    }
    redirect('/dashboard/admin/withdrawals.php');
}

$pending = db_all("SELECT " . WITHDRAWAL_PAYEE_SELECT . " WHERE w.status='PENDING' ORDER BY w.requested_at ASC");
$resolved = db_all("SELECT " . WITHDRAWAL_PAYEE_SELECT . " WHERE w.status!='PENDING' ORDER BY w.resolved_at DESC LIMIT 30");

$badgeClass = ['PENDING' => 'badge-pending', 'APPROVED' => 'badge-published', 'REJECTED' => 'badge-rejected'];
$typeBadgeClass = ['CREATOR' => 'badge-draft', 'AFFILIATE' => 'badge-pending'];

$pageTitle = 'Withdrawals — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Withdrawals</h1>

<h2 class="h3" style="margin-top:24px;">Pending (<?= count($pending) ?>)</h2>
<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>Payee</th><th>Type</th><th>Amount</th><th>Phone</th><th>Requested</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pending as $w): ?>
        <tr>
          <td><?= e($w['payee_name']) ?></td>
          <td><span class="badge <?= $typeBadgeClass[$w['payee_type']] ?>"><?= e(ucfirst(strtolower($w['payee_type']))) ?></span></td>
          <td><?= e(format_money((float) $w['amount'])) ?></td>
          <td><?= e($w['phone']) ?></td>
          <td><?= e(format_date($w['requested_at'])) ?></td>
          <td class="row gap-2">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="approve"><input type="hidden" name="withdrawalId" value="<?= (int) $w['id'] ?>"><button class="btn btn-primary btn-sm">Approve</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="reject"><input type="hidden" name="withdrawalId" value="<?= (int) $w['id'] ?>"><button class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);">Reject</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pending): ?><tr><td colspan="6" class="muted">Nothing pending.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2 class="h3" style="margin-top:36px;">Recent History</h2>
<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>Payee</th><th>Type</th><th>Amount</th><th>Status</th><th>Resolved</th></tr></thead>
    <tbody>
      <?php foreach ($resolved as $w): ?>
        <tr>
          <td><?= e($w['payee_name']) ?></td>
          <td><span class="badge <?= $typeBadgeClass[$w['payee_type']] ?>"><?= e(ucfirst(strtolower($w['payee_type']))) ?></span></td>
          <td><?= e(format_money((float) $w['amount'])) ?></td>
          <td><span class="badge <?= $badgeClass[$w['status']] ?>"><?= $w['status'] ?></span></td>
          <td><?= e(format_date($w['resolved_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
