<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/community.php';
$user = require_role(['ADMIN']);

$stats = get_community_module_stats();
$postsSeries = get_community_posts_daily_series(30);
$dailyCounts = array_column($postsSeries, 'count');
$bestDay = $dailyCounts ? max($dailyCounts) : 0;
$last7 = array_sum(array_slice($dailyCounts, -7));
$prev7 = array_sum(array_slice($dailyCounts, -14, 7));
$trendPct = $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100) : null;

$chartW = 700; $chartH = 220; $padTop = 16; $padBottom = 4;
$n = count($postsSeries);
$yMax = ($bestDay ?: 1) * 1.15;
$xStep = $n > 1 ? $chartW / ($n - 1) : 0;
$points = [];
foreach ($postsSeries as $i => $row) {
    $x = $i * $xStep;
    $y = $padTop + ($chartH - $padTop - $padBottom) * (1 - $row['count'] / $yMax);
    $points[] = [$x, $y];
}
$linePath = smooth_svg_path($points);
$areaPath = $points ? $linePath . sprintf(' L%.2f,%d L0,%d Z', end($points)[0], $chartH, $chartH) : '';

$mostActive = get_most_active_communities(8);

$pageTitle = 'Community Stats — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Community</h1>
    <p class="muted" style="margin-top:6px;">How the community module is doing platform-wide.</p>
  </div>
</div>

<div class="grid md:grid-4" style="margin-top:20px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= number_format($stats['communities']) ?></div><div class="label">Communities</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('user-plus'); ?></div>
    <div class="value"><?= number_format($stats['members']) ?></div><div class="label">Unique Members</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#10b981;">
    <div class="icon"><?php dash_icon('file-text'); ?></div>
    <div class="value"><?= number_format($stats['posts']) ?></div><div class="label">Posts All-Time</div>
  </div>
  <a href="<?= e(base_url('dashboard/admin/reports.php')) ?>" class="stat-card-link">
    <div class="stat-card" data-hoverable="true" style="--hover-color:#dc2626;">
      <div class="icon"><?php dash_icon('shield'); ?></div>
      <div class="value"><?= number_format($stats['pending_reports']) ?></div><div class="label">Pending Reports</div>
    </div>
  </a>
</div>

<div class="growth-layout" style="margin-top:20px;">
  <div class="chart-card">
    <div class="chart-card-head">
      <div>
        <h2 class="h3">Posts Created</h2>
        <p class="muted small" style="margin-top:4px;">Community posts per day &middot; last 30 days</p>
      </div>
      <?php if ($trendPct !== null): ?>
        <div class="chart-trend <?= $trendPct >= 0 ? 'up' : 'down' ?>">
          <?php dash_icon('trending-up'); ?><?= $trendPct >= 0 ? '+' : '' ?><?= $trendPct ?>% vs previous week
        </div>
      <?php endif; ?>
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
          <circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" class="chart-point" tabindex="0"
            data-chart-label="<?= e(format_date($postsSeries[$i]['date'] . ' 00:00:00')) ?>"
            data-chart-value="<?= (int) $postsSeries[$i]['count'] ?> post<?= $postsSeries[$i]['count'] === 1 ? '' : 's' ?>"></circle>
        <?php endforeach; ?>
        <?php if ($points): [$lx, $ly] = end($points); ?>
          <circle cx="<?= round($lx, 1) ?>" cy="<?= round($ly, 1) ?>" r="5" class="chart-end-dot chart-end-dot-blue" style="pointer-events:none;"></circle>
        <?php endif; ?>
      </svg>
    </div>
  </div>

  <div class="chart-card">
    <h2 class="h3">Most Active Communities</h2>
    <p class="muted small" style="margin-top:4px;">By post count</p>
    <?php if (!$mostActive): ?>
      <p class="muted small" style="margin-top:16px;">No communities yet.</p>
    <?php else: ?>
      <?php render_bar_list(array_combine(array_column($mostActive, 'slug'), array_column($mostActive, 'post_count')), array_combine(array_column($mostActive, 'slug'), array_column($mostActive, 'name'))); ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
