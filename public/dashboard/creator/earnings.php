<?php
require __DIR__ . '/../../../includes/bootstrap.php';
$user = require_role(['CREATOR', 'ADMIN']);

$totalEarnings = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id = ?', [$user['id']])['n'] ?? 0);
$pendingWithdrawals = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE creator_id = ? AND status = 'PENDING'", [$user['id']])['n'] ?? 0);
$approvedWithdrawals = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE creator_id = ? AND status = 'APPROVED'", [$user['id']])['n'] ?? 0);
$available = $totalEarnings - $pendingWithdrawals - $approvedWithdrawals;

$recentEarnings = db_all('
    SELECT e.*, c.title FROM earnings e JOIN courses c ON c.id = e.course_id
    WHERE e.creator_id = ? ORDER BY e.created_at DESC LIMIT 20
', [$user['id']]);

$withdrawals = db_all('SELECT * FROM withdrawal_requests WHERE creator_id = ? ORDER BY requested_at DESC', [$user['id']]);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $amount = (float) post('amount');
    $phone = post('phone');

    if ($amount < MIN_WITHDRAWAL_UGX) {
        $errors[] = 'Minimum withdrawal is ' . format_money(MIN_WITHDRAWAL_UGX) . '.';
    } elseif ($amount > $available) {
        $errors[] = 'You cannot withdraw more than your available balance.';
    } elseif (!preg_match('/^[0-9+\s-]{9,}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    } else {
        db_insert('INSERT INTO withdrawal_requests (amount, phone, creator_id) VALUES (?, ?, ?)', [$amount, $phone, $user['id']]);
        flash_set('success', 'Withdrawal request submitted. An admin will review it shortly.');
        redirect('/dashboard/creator/earnings.php');
    }
}

$badgeClass = ['PENDING' => 'badge-pending', 'APPROVED' => 'badge-published', 'REJECTED' => 'badge-rejected'];

$pageTitle = 'Earnings — Obin Academy';
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<h1 class="h2">Earnings</h1>

<div class="grid md:grid-3" style="margin-top:24px;">
  <div class="stat-card"><div class="icon">💰</div><div class="value"><?= e(format_money($totalEarnings)) ?></div><div class="label">Total Earned (after 10% platform fee)</div></div>
  <div class="stat-card"><div class="icon">✅</div><div class="value"><?= e(format_money($available)) ?></div><div class="label">Available to Withdraw</div></div>
  <div class="stat-card"><div class="icon">⏳</div><div class="value"><?= e(format_money($pendingWithdrawals)) ?></div><div class="label">Pending Withdrawals</div></div>
</div>

<?php if ($errors): ?><div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<div class="card card-pad" style="margin-top:24px; max-width:420px;">
  <h3 style="font-size:15px; font-weight:700;">Request a Withdrawal</h3>
  <form method="post" class="stack gap-2" style="margin-top:14px;">
    <?= csrf_field() ?>
    <div class="field"><label>Amount (UGX)</label><input name="amount" type="number" min="<?= MIN_WITHDRAWAL_UGX ?>" step="1" required></div>
    <div class="field"><label>Mobile Money Phone Number</label><input name="phone" type="tel" placeholder="e.g. 0772 123 456" required></div>
    <p class="help">Minimum withdrawal: <?= e(format_money(MIN_WITHDRAWAL_UGX)) ?></p>
    <button type="submit" class="btn btn-primary">Request Withdrawal</button>
  </form>
</div>

<h2 class="h3" style="margin-top:36px;">Recent Earnings</h2>
<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>Course</th><th>Gross</th><th>Platform Fee</th><th>Net</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($recentEarnings as $e): ?>
        <tr>
          <td><?= e($e['title']) ?></td>
          <td><?= e(format_money((float) $e['gross_amount'])) ?></td>
          <td><?= e(format_money((float) $e['platform_fee'])) ?></td>
          <td style="font-weight:700;"><?= e(format_money((float) $e['amount'])) ?></td>
          <td><?= e(format_date($e['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentEarnings): ?><tr><td colspan="5" class="muted">No earnings yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2 class="h3" style="margin-top:36px;">Withdrawal History</h2>
<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>Amount</th><th>Phone</th><th>Status</th><th>Requested</th></tr></thead>
    <tbody>
      <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td><?= e(format_money((float) $w['amount'])) ?></td>
          <td><?= e($w['phone']) ?></td>
          <td><span class="badge <?= $badgeClass[$w['status']] ?>"><?= e($w['status']) ?></span></td>
          <td><?= e(format_date($w['requested_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$withdrawals): ?><tr><td colspan="4" class="muted">No withdrawal requests yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
