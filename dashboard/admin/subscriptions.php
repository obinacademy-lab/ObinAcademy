<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/subscriptions.php';
$user = require_role(['ADMIN']);

$countsByTierAndStatus = db_all("SELECT tier, status, COUNT(*) AS n FROM subscriptions GROUP BY tier, status");
$activeByTier = ['GO' => 0, 'PLUS' => 0, 'PRO' => 0];
$graceCount = 0;
foreach ($countsByTierAndStatus as $row) {
    if ($row['status'] === 'ACTIVE') $activeByTier[$row['tier']] = (int) $row['n'];
    if ($row['status'] === 'GRACE') $graceCount += (int) $row['n'];
}
$totalActive = array_sum($activeByTier) + $graceCount;

$thisMonth = date('Y-m-01');
$thisMonthSettlement = db_one(
    'SELECT COALESCE(SUM(pool_amount),0) AS pool, COALESCE(SUM(payout_amount),0) AS paid, COUNT(*) AS n FROM creator_subscription_payouts WHERE period_month = ?',
    [$thisMonth]
);
// pool_amount is the same figure on every row for a given month (the whole
// platform's pool, not a per-creator slice) — SUM() would multiply it by
// the number of creators paid that month, so take it from one row instead.
$thisMonthPool = (float) (db_one('SELECT pool_amount FROM creator_subscription_payouts WHERE period_month = ? LIMIT 1', [$thisMonth])['pool_amount'] ?? 0);

$subscriptions = db_all(
    'SELECT s.*, u.name AS user_name, u.email AS user_email
     FROM subscriptions s JOIN users u ON u.id = s.user_id
     ORDER BY s.created_at DESC LIMIT 100'
);

$statusBadgeClass = ['ACTIVE' => 'badge-published', 'GRACE' => 'badge-pending', 'EXPIRED' => 'badge-rejected', 'CANCELED' => 'badge-rejected'];

$pageTitle = 'Subscriptions — Obin Academy Admin';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2 reveal">Subscriptions</h1>

<div class="grid md:grid-2 lg:grid-4" style="margin-top:24px;">
  <div class="stat-card reveal" data-hoverable="true" style="--hover-color:#8b5cf6;"><div class="icon"><?php dash_icon('crown'); ?></div><div class="value"><?= $totalActive ?></div><div class="label">Active Subscribers</div></div>
  <div class="stat-card reveal reveal-delay-1" data-hoverable="true" style="--hover-color:#fbbf24;"><div class="icon"><?php dash_icon('clock'); ?></div><div class="value"><?= $graceCount ?></div><div class="label">Payment Pending (Grace)</div></div>
  <div class="stat-card reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#f5b301;"><div class="icon"><?php dash_icon('banknote'); ?></div><div class="value"><?= e(format_money($thisMonthPool)) ?></div><div class="label">This Month's Payout Pool</div></div>
  <div class="stat-card reveal reveal-delay-3" data-hoverable="true" style="--hover-color:#34d399;"><div class="icon"><?php dash_icon('check-circle'); ?></div><div class="value"><?= (int) $thisMonthSettlement['n'] ?></div><div class="label">Creators Paid This Month</div></div>
</div>

<div class="row gap-3 wrap reveal" style="margin-top:20px;">
  <?php foreach (SUBSCRIPTION_TIERS as $tierKey => $tierInfo): ?>
    <div class="card card-pad" style="flex:1; min-width:160px; text-align:center;">
      <div style="font-size:22px; font-weight:800;"><?= $activeByTier[$tierKey] ?></div>
      <div class="muted small"><?= e($tierInfo['label']) ?> subscribers</div>
    </div>
  <?php endforeach; ?>
</div>

<h2 class="h3" style="margin-top:36px;">All Subscriptions</h2>
<div class="table-wrap reveal" style="margin-top:14px;">
  <table>
    <thead><tr><th>Learner</th><th>Plan</th><th>Status</th><th>Current Period Ends</th><th>Started</th></tr></thead>
    <tbody>
      <?php foreach ($subscriptions as $s): ?>
        <tr>
          <td><?= e($s['user_name']) ?><div class="muted small"><?= e($s['user_email']) ?></div></td>
          <td><?= e(SUBSCRIPTION_TIERS[$s['tier']]['label']) ?></td>
          <td><span class="badge <?= $statusBadgeClass[$s['status']] ?>"><?= e($s['status']) ?></span></td>
          <td><?= e(format_date($s['current_period_ends_at'])) ?></td>
          <td><?= e(format_date($s['started_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$subscriptions): ?><tr><td colspan="5" class="muted">No subscriptions yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
