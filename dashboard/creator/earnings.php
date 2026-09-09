<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$totalEarnings = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id = ?', [$user['id']])['n'] ?? 0);
$pendingWithdrawals = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE creator_id = ? AND status = 'PENDING'", [$user['id']])['n'] ?? 0);
$approvedWithdrawals = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE creator_id = ? AND status = 'APPROVED'", [$user['id']])['n'] ?? 0);
$available = $totalEarnings - $pendingWithdrawals - $approvedWithdrawals;

// ---------------------------------------------------------------------
// Daily revenue growth chart — this creator's own net earnings (after the
// 10% platform fee) per calendar day, so every point answers "how much did
// I earn on this date" directly, same convention as the admin collections
// chart it's modeled on.
// ---------------------------------------------------------------------
$revenueSeries = get_creator_daily_earnings_series((int) $user['id'], 30);
$dailyRevenue = array_column($revenueSeries, 'collected');
$monthRevenue = array_sum($dailyRevenue);
$bestRevenueDay = $dailyRevenue ? max($dailyRevenue) : 0.0;
$dailyRevenueAvg = $revenueSeries ? $monthRevenue / count($revenueSeries) : 0.0;

$revLast7 = array_sum(array_slice($dailyRevenue, -7));
$revPrev7 = array_sum(array_slice($dailyRevenue, -14, 7));
$revTrendPct = $revPrev7 > 0 ? round((($revLast7 - $revPrev7) / $revPrev7) * 100) : null;

$revChartW = 700; $revChartH = 220; $revPadTop = 16; $revPadBottom = 4;
$revN = count($revenueSeries);
$revYMax = ($bestRevenueDay ?: 1) * 1.15;
$revXStep = $revN > 1 ? $revChartW / ($revN - 1) : 0;
$revPoints = [];
foreach ($revenueSeries as $i => $row) {
    $x = $i * $revXStep;
    $y = $revPadTop + ($revChartH - $revPadTop - $revPadBottom) * (1 - $row['collected'] / $revYMax);
    $revPoints[] = [$x, $y];
}
$revLinePath = smooth_svg_path($revPoints);
$revAreaPath = $revPoints ? $revLinePath . sprintf(' L%.2f,%d L0,%d Z', end($revPoints)[0], $revChartH, $revChartH) : '';
$revLabelIdxs = $revN > 1 ? [0, (int) round(($revN - 1) * 0.2), (int) round(($revN - 1) * 0.4), (int) round(($revN - 1) * 0.6), (int) round(($revN - 1) * 0.8), $revN - 1] : [0];

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
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Earnings</h1>

<div class="grid md:grid-3" style="margin-top:24px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;"><div class="icon"><?php dash_icon('banknote'); ?></div><div class="value"><?= e(format_money($totalEarnings)) ?></div><div class="label">Total Earned (after 10% platform fee)</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#34d399;"><div class="icon"><?php dash_icon('check-circle'); ?></div><div class="value"><?= e(format_money($available)) ?></div><div class="label">Available to Withdraw</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#fbbf24;"><div class="icon"><?php dash_icon('clock'); ?></div><div class="value"><?= e(format_money($pendingWithdrawals)) ?></div><div class="label">Pending Withdrawals</div></div>
</div>

<?php if ($errors): ?><div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<h3 class="dash-section-label" style="margin-top:32px;">Revenue Growth</h3>
<div class="chart-card" style="margin-top:14px;">
  <div class="chart-card-head">
    <div>
      <h2 class="h3">Your Daily Revenue</h2>
      <p class="muted small" style="margin-top:4px;">What you earned each day (after the platform's 10% fee) &middot; last 30 days</p>
    </div>
    <?php if ($revTrendPct !== null): ?>
      <div class="chart-trend <?= $revTrendPct >= 0 ? 'up' : 'down' ?>">
        <?php dash_icon('trending-up'); ?><?= $revTrendPct >= 0 ? '+' : '' ?><?= $revTrendPct ?>% vs last week
      </div>
    <?php endif; ?>
  </div>

  <div class="chart-stats-row">
    <div><span class="value"><?= e(format_money($monthRevenue)) ?></span><span class="label">Total (30d)</span></div>
    <div><span class="value"><?= e(format_money($dailyRevenueAvg)) ?></span><span class="label">Daily Average</span></div>
    <div><span class="value"><?= e(format_money($bestRevenueDay)) ?></span><span class="label">Best Day</span></div>
  </div>

  <div class="chart-wrap">
    <svg viewBox="0 0 <?= $revChartW ?> <?= $revChartH ?>" preserveAspectRatio="none" class="revenue-chart">
      <defs>
        <linearGradient id="creatorRevFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--dash-good)" stop-opacity="0.24"/>
          <stop offset="100%" stop-color="var(--dash-good)" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <?php for ($g = 1; $g <= 3; $g++): $gy = $revPadTop + ($revChartH - $revPadTop - $revPadBottom) * ($g / 4); ?>
        <line x1="0" y1="<?= round($gy, 1) ?>" x2="<?= $revChartW ?>" y2="<?= round($gy, 1) ?>" class="chart-gridline"></line>
      <?php endfor; ?>
      <path d="<?= e($revAreaPath) ?>" class="chart-area-green"></path>
      <path d="<?= e($revLinePath) ?>" class="chart-line chart-line-green"></path>
      <?php foreach ($revPoints as $i => [$px, $py]): ?>
        <circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" class="chart-point-green" tabindex="0"
          data-chart-label="<?= e(format_date($revenueSeries[$i]['date'] . ' 00:00:00')) ?>"
          data-chart-value="<?= e(format_money($revenueSeries[$i]['collected'])) ?>"></circle>
      <?php endforeach; ?>
      <?php if ($revPoints): [$rlx, $rly] = end($revPoints); ?>
        <circle cx="<?= round($rlx, 1) ?>" cy="<?= round($rly, 1) ?>" r="5" class="chart-end-dot-green" style="pointer-events:none;"></circle>
      <?php endif; ?>
    </svg>
    <div class="chart-x-labels">
      <?php foreach ($revLabelIdxs as $idx): ?>
        <span><?= e(date('M j', strtotime($revenueSeries[$idx]['date']))) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

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
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
