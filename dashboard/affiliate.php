<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/subscriptions.php';
$user = require_affiliate();
$affiliate = $user['affiliate'];
$affiliateId = (int) $affiliate['id'];

$totalEarned = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM affiliate_earnings WHERE affiliate_id = ?', [$affiliateId])['n'] ?? 0);
$pendingWithdrawals = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE affiliate_id = ? AND status = 'PENDING'", [$affiliateId])['n'] ?? 0);
$approvedWithdrawals = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE affiliate_id = ? AND status = 'APPROVED'", [$affiliateId])['n'] ?? 0);
$available = $totalEarned - $pendingWithdrawals - $approvedWithdrawals;

$salesCount = (int) (db_one('SELECT COUNT(*) AS n FROM affiliate_earnings WHERE affiliate_id = ?', [$affiliateId])['n'] ?? 0);
$recentEarnings = get_affiliate_recent_earnings($affiliateId, 20);
$withdrawals = db_all('SELECT * FROM withdrawal_requests WHERE affiliate_id = ? ORDER BY requested_at DESC', [$affiliateId]);

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
        db_insert("INSERT INTO withdrawal_requests (amount, phone, payee_type, affiliate_id) VALUES (?, ?, 'AFFILIATE', ?)", [$amount, $phone, $affiliateId]);
        flash_set('success', 'Withdrawal request submitted. An admin will review it shortly.');
        redirect('/dashboard/affiliate.php');
    }
}

$badgeClass = ['PENDING' => 'badge-pending', 'APPROVED' => 'badge-published', 'REJECTED' => 'badge-rejected'];

$shareUrl = base_url('') . '?aff=' . $affiliate['ref_code'];

$pageTitle = 'Affiliate Dashboard — Obin Academy';
require __DIR__ . '/../includes/dashboard_header.php';
?>
<h1 class="h2">Affiliate Dashboard</h1>
<p class="muted" style="margin-top:6px;">Share your link — earn 2% commission on any course, from any creator, that anyone buys through it.</p>

<div class="card card-pad row between wrap gap-3" style="margin-top:24px; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 14%, var(--dash-panel)), var(--dash-panel)); border-color: var(--dash-border);">
  <div style="min-width:240px; flex:1;">
    <h3 class="small" style="font-weight:700;">Your Affiliate Link</h3>
    <p class="small" style="margin-top:6px; word-break:break-all; font-weight:600;"><?= e($shareUrl) ?></p>
  </div>
  <?php render_share_button($shareUrl, 'Obin Academy', 'Share Your Link', 'light', null, 'Share your affiliate link'); ?>
</div>

<div class="grid md:grid-3" style="margin-top:24px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('banknote'); ?></div>
    <div class="value"><?= e(format_money($totalEarned)) ?></div><div class="label">Total Earned</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#34d399;">
    <div class="icon"><?php dash_icon('check-circle'); ?></div>
    <div class="value"><?= e(format_money($available)) ?></div><div class="label">Available to Withdraw</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#60a5fa;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= $salesCount ?></div><div class="label">Referred Sales</div>
  </div>
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

<h2 class="h3" style="margin-top:36px;">Recent Commissions</h2>
<?php if (!$recentEarnings): ?>
  <div class="card card-pad" style="margin-top:14px; border-style:dashed; text-align:center;">
    <p class="muted">No commissions yet — share your link to get started.</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:14px;">
    <?php foreach ($recentEarnings as $e): ?>
      <div class="list-row">
        <div class="list-row-main"><span style="font-weight:600;"><?= e($e['course_title'] ?? ('Subscription — ' . (SUBSCRIPTION_TIERS[$e['subscription_tier']]['label'] ?? 'Renewal'))) ?></span></div>
        <div class="list-row-meta">
          <span style="font-weight:700;"><?= e(format_money((float) $e['amount'])) ?></span>
          <span class="small muted"><?= e(format_date($e['created_at'])) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="h3" style="margin-top:36px;">Withdrawal History</h2>
<?php if (!$withdrawals): ?>
  <div class="card card-pad" style="margin-top:14px; border-style:dashed; text-align:center;">
    <p class="muted">No withdrawal requests yet.</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:14px;">
    <?php foreach ($withdrawals as $w): ?>
      <div class="list-row">
        <div class="list-row-main">
          <span style="font-weight:600;"><?= e(format_money((float) $w['amount'])) ?></span>
          <span class="small muted"><?= e($w['phone']) ?></span>
        </div>
        <div class="list-row-meta">
          <span class="badge <?= $badgeClass[$w['status']] ?>"><?= e($w['status']) ?></span>
          <span class="small muted"><?= e(format_date($w['requested_at'])) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
