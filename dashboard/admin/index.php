<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['ADMIN']);

$userCount = (int) db_one('SELECT COUNT(*) AS n FROM users')['n'];
$courseCount = (int) db_one('SELECT COUNT(*) AS n FROM courses')['n'];
$publishedCount = (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE status='PUBLISHED'")['n'];
$pendingCount = (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE status='PENDING_REVIEW'")['n'];
$pendingWithdrawalCount = (int) db_one("SELECT COUNT(*) AS n FROM withdrawal_requests WHERE status='PENDING'")['n'];
$pendingApplicationCount = (int) db_one("SELECT COUNT(*) AS n FROM creator_applications WHERE status='PENDING'")['n'];

$recentActivity = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8');

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

// ---------------------------------------------------------------------
// Revenue growth chart data — cumulative platform fee income, last 30 days
// ---------------------------------------------------------------------
$revenueSeries = get_daily_revenue_series(30);
$revenueTotal = end($revenueSeries)['cumulative'] ?? 0.0;
$dailyFees = array_column($revenueSeries, 'fee');
$bestDay = $dailyFees ? max($dailyFees) : 0.0;
$dailyAvg = $revenueSeries ? array_sum($dailyFees) / count($revenueSeries) : 0.0;

$last7 = array_sum(array_slice($dailyFees, -7));
$prev7 = array_sum(array_slice($dailyFees, -14, 7));
$trendPct = $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100) : null;

$chartW = 700; $chartH = 220; $padTop = 16; $padBottom = 4;
$n = count($revenueSeries);
$yMax = max(array_column($revenueSeries, 'cumulative')) ?: 1;
$xStep = $n > 1 ? $chartW / ($n - 1) : 0;
$points = [];
foreach ($revenueSeries as $i => $row) {
    $x = $i * $xStep;
    $y = $padTop + ($chartH - $padTop - $padBottom) * (1 - $row['cumulative'] / $yMax);
    $points[] = [$x, $y];
}
$linePath = smooth_svg_path($points);
$areaPath = $linePath . sprintf(' L%.2f,%d L0,%d Z', $chartW, $chartH, $chartH);
$labelIdxs = $n > 1 ? [0, (int) round(($n - 1) * 0.2), (int) round(($n - 1) * 0.4), (int) round(($n - 1) * 0.6), (int) round(($n - 1) * 0.8), $n - 1] : [0];

$pageTitle = 'Admin Overview — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<?php $totalPending = $pendingCount + $pendingWithdrawalCount + $pendingApplicationCount; ?>
<div class="dash-hero">
  <div>
    <h1 class="h2" style="color:#fff;">Welcome back, <?= e(explode(' ', trim($user['name']))[0]) ?></h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72);">Here's what's happening on Obin Academy today.</p>
  </div>
  <?php if ($totalPending > 0): ?>
    <div class="status-pill warn"><span class="status-dot warn"></span><?= $totalPending ?> item<?= $totalPending === 1 ? '' : 's' ?> need<?= $totalPending === 1 ? 's' : '' ?> your attention</div>
  <?php else: ?>
    <div class="status-pill"><span class="status-dot"></span>All systems operational</div>
  <?php endif; ?>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:24px;">
  <div class="stat-card accent-top" data-hoverable="true" style="--hover-color:#3b82f6;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= $userCount ?></div><div class="label">Total Users</div>
  </div>
  <div class="stat-card accent-top" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('book-open'); ?></div>
    <div class="value"><?= $publishedCount ?>/<?= $courseCount ?></div><div class="label">Courses</div><div class="sub">published / total</div>
  </div>
  <div class="stat-card accent-top" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('banknote'); ?></div>
    <div class="value"><?= e(format_money($revenueTotal)) ?></div><div class="label">Revenue (30d)</div>
  </div>
  <div class="stat-card accent-top <?= $totalPending > 0 ? 'highlight' : '' ?>" data-hoverable="true" style="--hover-color:#f59e0b;">
    <div class="icon"><?php dash_icon('clipboard-check'); ?></div>
    <div class="value"><?= $totalPending ?></div><div class="label">Need Your Action</div>
  </div>
</div>

<div class="dash-split" style="margin-top:32px;">
  <div>
    <h3 class="dash-section-label" style="margin-bottom:14px;">Needs Your Attention</h3>
    <?php if ($totalPending > 0): ?>
      <div class="quick-actions" style="margin-top:0;">
        <?php if ($pendingCount > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/courses.php')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#f59e0b;"><?php dash_icon('clipboard-check'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= $pendingCount ?></span> course<?= $pendingCount === 1 ? '' : 's' ?> awaiting review</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
        <?php if ($pendingWithdrawalCount > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/withdrawals.php')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#f97316;"><?php dash_icon('banknote'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= $pendingWithdrawalCount ?></span> withdrawal<?= $pendingWithdrawalCount === 1 ? '' : 's' ?> to process</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
        <?php if ($pendingApplicationCount > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/creator-applications.php')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#ec4899;"><?php dash_icon('user-plus'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= $pendingApplicationCount ?></span> creator application<?= $pendingApplicationCount === 1 ? '' : 's' ?> to review</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="all-caught-up" style="margin-top:0;">
        <?php dash_icon('check-circle'); ?>
        <div><strong>You're all caught up.</strong> No pending reviews, withdrawals, or applications right now.</div>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="row between" style="align-items:center; margin-bottom:14px;">
      <h3 class="dash-section-label" style="margin-bottom:0;">Revenue, Last 30 Days</h3>
      <?php if ($trendPct !== null): ?>
        <div class="chart-trend <?= $trendPct >= 0 ? 'up' : 'down' ?>">
          <?php dash_icon('trending-up'); ?><?= $trendPct >= 0 ? '+' : '' ?><?= $trendPct ?>% vs last week
        </div>
      <?php endif; ?>
    </div>
    <div class="chart-card">
      <div class="chart-stats-row" style="margin-top:0; padding-top:0; border-top:none;">
        <div><span class="value"><?= e(format_money($revenueTotal)) ?></span><span class="label">Total (30d)</span></div>
        <div><span class="value"><?= e(format_money($dailyAvg)) ?></span><span class="label">Daily Average</span></div>
        <div><span class="value"><?= e(format_money($bestDay)) ?></span><span class="label">Best Day</span></div>
      </div>

      <div class="chart-wrap">
        <svg viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none" class="revenue-chart">
          <defs>
            <linearGradient id="revFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#f5b301" stop-opacity="0.35"></stop>
              <stop offset="100%" stop-color="#f5b301" stop-opacity="0"></stop>
            </linearGradient>
          </defs>
          <?php for ($g = 1; $g <= 3; $g++): $gy = $padTop + ($chartH - $padTop - $padBottom) * ($g / 4); ?>
            <line x1="0" y1="<?= round($gy, 1) ?>" x2="<?= $chartW ?>" y2="<?= round($gy, 1) ?>" class="chart-gridline"></line>
          <?php endfor; ?>
          <path d="<?= e($areaPath) ?>" class="chart-area"></path>
          <path d="<?= e($linePath) ?>" class="chart-line"></path>
          <?php foreach ($points as $i => [$px, $py]): ?>
            <circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" class="chart-point-gold" tabindex="0"
              data-chart-label="<?= e(format_date($revenueSeries[$i]['date'] . ' 00:00:00')) ?>"
              data-chart-value="<?= e(format_money($revenueSeries[$i]['cumulative'])) ?>"></circle>
          <?php endforeach; ?>
          <?php if ($points): [$lx, $ly] = end($points); ?>
            <circle cx="<?= round($lx, 1) ?>" cy="<?= round($ly, 1) ?>" r="5" class="chart-end-dot" style="pointer-events:none;"></circle>
          <?php endif; ?>
        </svg>
        <div class="chart-x-labels">
          <?php foreach ($labelIdxs as $idx): ?>
            <span><?= e(date('M j', strtotime($revenueSeries[$idx]['date']))) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div style="margin-top:32px;">
  <div class="row between" style="align-items:center;">
    <h3 class="dash-section-label" style="margin-bottom:0;">Recent Activity</h3>
    <a href="<?= e(base_url('dashboard/admin/audit-log.php')) ?>" class="small" style="color:var(--accent); font-weight:700;">View all →</a>
  </div>
  <div class="activity-feed" style="margin-top:14px;">
    <?php if (!$recentActivity): ?>
      <p class="muted small" style="padding:16px 0;">No activity logged yet.</p>
    <?php endif; ?>
    <?php foreach ($recentActivity as $log): $tone = activity_tone($log['action']); ?>
      <div class="activity-row">
        <span class="activity-dot tone-<?= $tone ?>"><?php dash_icon(activity_icon($log['action'])); ?></span>
        <div class="activity-body">
          <div><strong><?= e($log['actor_name']) ?></strong> <?= e(strtolower(str_replace('_', ' ', $log['action']))) ?> <span class="muted"><?= e($log['target_label']) ?></span></div>
          <div class="small muted"><?= e(format_date($log['created_at'])) ?><?= $log['detail'] ? ' &middot; ' . e($log['detail']) : '' ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
