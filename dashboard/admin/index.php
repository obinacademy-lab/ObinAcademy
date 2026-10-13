<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/admin_payments.php';
$user = require_role(['ADMIN']);

$userCount = (int) db_one('SELECT COUNT(*) AS n FROM users')['n'];
$campusCount = 0; $openCampusCount = 0; $activeMembers = 0; $newApplications = 0; $owedProfessors = 0.0; $endingSoon = 0;
try {
    $campusCount = (int) db_one('SELECT COUNT(*) AS n FROM campuses')['n'];
    $openCampusCount = (int) db_one("SELECT COUNT(*) AS n FROM campuses WHERE status='PUBLISHED'")['n'];
    $activeMembers = (int) db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE access_until > NOW()')['n'];
    $endingSoon = (int) db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE access_until > NOW() AND access_until <= DATE_ADD(NOW(), INTERVAL 7 DAY)')['n'];
    $newApplications = (int) db_one("SELECT COUNT(*) AS n FROM instructor_applications WHERE status='NEW'")['n'];
    $owedProfessors = (float) db_one('SELECT COALESCE(SUM(amount),0) AS n FROM campus_professor_earnings WHERE payout_id IS NULL')['n'];
} catch (Throwable $e) {}

$recentActivity = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8');

// Money that needs a human: failed or stuck payments.
$payHealth = admin_payment_health();
$failedPayments24h = (int) $payHealth['failed_24h'];
$stuckPayments = (int) $payHealth['stuck'];

/** Buckets an audit_log action string into a visual tone for the activity feed. */
function activity_tone(string $action): string {
    $a = strtolower($action); // audit actions are stored lower-case ("withdrawal.approved")
    if (str_contains($a, 'reject') || str_contains($a, 'deleted') || str_contains($a, 'removed')) return 'danger';
    if (str_contains($a, 'approved') || str_contains($a, 'published') || str_contains($a, 'granted')) return 'success';
    return 'neutral';
}
function activity_icon(string $action): string {
    $tone = activity_tone($action);
    return $tone === 'danger' ? 'x-circle' : ($tone === 'success' ? 'check-circle' : 'clock');
}

// ---------------------------------------------------------------------
// Revenue growth chart data — cumulative money kept by Obin Academy (campus payments minus professors' share), last 30 days
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
<?php $totalPending = $newApplications + ($owedProfessors > 0 ? 1 : 0) + ($failedPayments24h > 0 ? 1 : 0) + ($stuckPayments > 0 ? 1 : 0); ?>
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
  <div class="stat-card accent-top" data-hoverable="true" style="--hover-color:#3b33ff;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= $userCount ?></div><div class="label">Total Users</div>
  </div>
  <div class="stat-card accent-top" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('graduation-cap'); ?></div>
    <div class="value"><?= $activeMembers ?></div><div class="label">Campus members</div><div class="sub"><?= $openCampusCount ?> of <?= $campusCount ?> campuses open · <?= $endingSoon ?> ending in 7 days</div>
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
        <?php if ($newApplications > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/instructor-applications.php')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#ec4899;"><?php dash_icon('user-plus'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= $newApplications ?></span> new application<?= $newApplications === 1 ? '' : 's' ?> to teach</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
        <?php if ($owedProfessors > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/professor-pay.php')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#f97316;"><?php dash_icon('banknote'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= e(format_money($owedProfessors)) ?></span> owed to professors</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
        <?php if ($stuckPayments > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/payments.php?status=STUCK')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#ef4444;"><?php dash_icon('wallet'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= $stuckPayments ?></span> payment<?= $stuckPayments === 1 ? '' : 's' ?> stuck, someone may be waiting for campus access</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
        <?php if ($failedPayments24h > 0): ?>
          <a href="<?= e(base_url('dashboard/admin/payments.php?status=FAILED')) ?>" class="quick-action">
            <span class="qa-icon" style="--tint:#f43f5e;"><?php dash_icon('x-circle'); ?></span>
            <span class="qa-text"><span class="qa-count"><?= $failedPayments24h ?></span> payment<?= $failedPayments24h === 1 ? '' : 's' ?> failed in the last 24 hours</span>
            <?php dash_icon('arrow-right', 'qa-arrow'); ?>
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="all-caught-up" style="margin-top:0;">
        <?php dash_icon('check-circle'); ?>
        <div><strong>You're all caught up.</strong> No new teach applications, professor pay owed, or failed or stuck payments right now.</div>
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
