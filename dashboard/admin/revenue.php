<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['ADMIN']);

$today = date('Y-m-d');
$selectedDate = query_param('date') ?: $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || $selectedDate > $today) {
    $selectedDate = $today;
}

$daySummary = get_day_collection_summary($selectedDate);

// ---------------------------------------------------------------------
// Daily collections chart — blue-branded, plots each day's actual amount
// collected (not a cumulative run) so every point on the curve answers
// "how much came in on this date" directly.
// ---------------------------------------------------------------------
$collectionsSeries = get_daily_collections_series(30);
$dailyCollected = array_column($collectionsSeries, 'collected');
$monthTotal = array_sum($dailyCollected);
$bestDay = $dailyCollected ? max($dailyCollected) : 0.0;
$dailyAvg = $collectionsSeries ? $monthTotal / count($collectionsSeries) : 0.0;

$last7 = array_sum(array_slice($dailyCollected, -7));
$prev7 = array_sum(array_slice($dailyCollected, -14, 7));
$trendPct = $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100) : null;

$chartW = 700; $chartH = 220; $padTop = 16; $padBottom = 4;
$n = count($collectionsSeries);
$yMax = $bestDay ?: 1;
$xStep = $n > 1 ? $chartW / ($n - 1) : 0;
$points = [];
foreach ($collectionsSeries as $i => $row) {
    $x = $i * $xStep;
    $y = $padTop + ($chartH - $padTop - $padBottom) * (1 - $row['collected'] / $yMax);
    $points[] = [$x, $y];
}
$linePath = smooth_svg_path($points);
// Same curve, closed down to the baseline — the gradient-filled area under the line.
$areaPath = $points ? $linePath . sprintf(' L%.2f,%d L0,%d Z', end($points)[0], $chartH, $chartH) : '';
$labelIdxs = $n > 1 ? [0, (int) round(($n - 1) * 0.2), (int) round(($n - 1) * 0.4), (int) round(($n - 1) * 0.6), (int) round(($n - 1) * 0.8), $n - 1] : [0];

$pageTitle = 'Revenue — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Revenue</h1>
<p class="muted" style="margin-top:6px;">Look up what the platform collected on a specific day, and track the daily trend.</p>

<div class="card card-pad" style="margin-top:24px;">
  <form method="get" class="day-lookup-form">
    <div class="field">
      <label for="revenue-date">Date</label>
      <input id="revenue-date" type="date" name="date" value="<?= e($selectedDate) ?>" max="<?= e($today) ?>">
    </div>
    <button type="submit" class="btn btn-primary">View Day</button>
    <?php if ($selectedDate !== $today): ?>
      <a href="<?= e(base_url('dashboard/admin/revenue.php')) ?>" class="btn btn-outline">Jump to Today</a>
    <?php endif; ?>
  </form>
</div>

<h3 class="dash-section-label" style="margin-top:28px;"><?= e(format_date($selectedDate . ' 00:00:00')) ?></h3>
<div class="grid md:grid-3" style="margin-top:14px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon">💵</div>
    <div class="value"><?= e(format_money($daySummary['collected'])) ?></div><div class="label">Total Collected</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon">🏦</div>
    <div class="value"><?= e(format_money($daySummary['fee'])) ?></div><div class="label">Platform Fee (10%)</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#10b981;">
    <div class="icon">🧾</div>
    <div class="value"><?= (int) $daySummary['count'] ?></div><div class="label">Sales (<?= e(format_money($daySummary['creator_net'])) ?> to creators)</div>
  </div>
</div>

<?php if ($daySummary['sales']): ?>
  <div class="table-wrap" style="margin-top:20px;">
    <table>
      <thead><tr><th>Time</th><th>Course</th><th>Creator</th><th>Collected</th><th>Platform Fee</th><th>Creator Net</th></tr></thead>
      <tbody>
        <?php foreach ($daySummary['sales'] as $s): ?>
          <tr>
            <td><?= e(date('g:i A', strtotime($s['created_at']))) ?></td>
            <td><?= e($s['course_title']) ?></td>
            <td><?= e($s['creator_name']) ?></td>
            <td><?= e(format_money((float) $s['gross_amount'])) ?></td>
            <td><?= e(format_money((float) $s['platform_fee'])) ?></td>
            <td><?= e(format_money((float) $s['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="card" style="padding:36px; text-align:center; border-style:dashed; color:var(--muted); margin-top:20px;">No sales on this date.</div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:36px;">Daily Trend</h3>
<div class="chart-card" style="margin-top:14px;">
  <div class="chart-card-head">
    <div>
      <h2 class="h3">Platform Collections</h2>
      <p class="muted small" style="margin-top:4px;">Total money collected from learners (before the platform's 10% cut) &middot; last 30 days</p>
    </div>
    <?php if ($trendPct !== null): ?>
      <div class="chart-trend <?= $trendPct >= 0 ? 'up' : 'down' ?>">
        <?php dash_icon('trending-up'); ?><?= $trendPct >= 0 ? '+' : '' ?><?= $trendPct ?>% vs last week
      </div>
    <?php endif; ?>
  </div>

  <div class="chart-stats-row">
    <div><span class="value"><?= e(format_money($monthTotal)) ?></span><span class="label">Total (30d)</span></div>
    <div><span class="value"><?= e(format_money($dailyAvg)) ?></span><span class="label">Daily Average</span></div>
    <div><span class="value"><?= e(format_money($bestDay)) ?></span><span class="label">Best Day</span></div>
  </div>

  <div class="chart-wrap">
    <svg viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none" class="revenue-chart">
      <defs>
        <linearGradient id="dashAreaFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.22"/>
          <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <?php for ($g = 1; $g <= 3; $g++): $gy = $padTop + ($chartH - $padTop - $padBottom) * ($g / 4); ?>
        <line x1="0" y1="<?= round($gy, 1) ?>" x2="<?= $chartW ?>" y2="<?= round($gy, 1) ?>" class="chart-gridline"></line>
      <?php endfor; ?>
      <path d="<?= e($areaPath) ?>" class="chart-area-blue"></path>
      <path d="<?= e($linePath) ?>" class="chart-line chart-line-blue"></path>
      <?php foreach ($points as $i => [$px, $py]): ?>
        <circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" class="chart-point">
          <title><?= e(format_date($collectionsSeries[$i]['date'] . ' 00:00:00')) ?>: <?= e(format_money($collectionsSeries[$i]['collected'])) ?></title>
        </circle>
      <?php endforeach; ?>
    </svg>
    <div class="chart-x-labels">
      <?php foreach ($labelIdxs as $idx): ?>
        <span><?= e(date('M j', strtotime($collectionsSeries[$idx]['date']))) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
