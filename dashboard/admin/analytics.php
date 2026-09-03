<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['ADMIN']);

$allowedRanges = [7 => 'Last 7 Days', 30 => 'Last 30 Days', 90 => 'Last 90 Days'];
$days = (int) (query_param('days') ?: 30);
if (!isset($allowedRanges[$days])) $days = 30;

$summary = get_visit_summary($days);
$topPages = get_top_landing_pages($days, 8);
$exitPages = get_top_exit_pages($days, 8);
$topCourses = get_most_viewed_courses($days, 8);
$topCities = get_top_cities($days, 8);
$deviceBreakdown = get_breakdown('device_type', $days);
$browserBreakdown = get_breakdown('browser', $days);
$osBreakdown = get_breakdown('os', $days);

$deviceLabels = ['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet'];
$countryNames = ['UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'US' => 'United States', 'GB' => 'United Kingdom'];


$sourceLabels = [
    'google' => ['label' => 'Google / Search', 'color' => '#2563eb'],
    'social' => ['label' => 'Social Media', 'color' => '#f5b301'],
    'direct' => ['label' => 'Direct / Shared Link', 'color' => '#10b981'],
    'other' => ['label' => 'Other Websites', 'color' => '#8b5cf6'],
];
$sourceTotal = array_sum($summary['sources']) ?: 1;

// ---------------------------------------------------------------------
// Daily visits chart — same smoothed-line treatment as the admin Revenue
// tab's collections chart, plotting unique visitors per day.
// ---------------------------------------------------------------------
$visitsSeries = get_daily_visits_series($days);
$dailyUnique = array_column($visitsSeries, 'unique_visitors');
$bestDay = $dailyUnique ? max($dailyUnique) : 0;

$last7 = array_sum(array_slice($dailyUnique, -7));
$prev7 = array_sum(array_slice($dailyUnique, -14, 7));
$trendPct = $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100) : null;

$chartW = 700; $chartH = 220; $padTop = 16; $padBottom = 4;
$n = count($visitsSeries);
$yMax = ($bestDay ?: 1) * 1.15; // headroom so the peak doesn't touch the ceiling
$xStep = $n > 1 ? $chartW / ($n - 1) : 0;
$points = [];
foreach ($visitsSeries as $i => $row) {
    $x = $i * $xStep;
    $y = $padTop + ($chartH - $padTop - $padBottom) * (1 - $row['unique_visitors'] / $yMax);
    $points[] = [$x, $y];
}
$linePath = smooth_svg_path($points);
// Same curve, closed down to the baseline — the gradient-filled area under the line.
$areaPath = $points ? $linePath . sprintf(' L%.2f,%d L0,%d Z', end($points)[0], $chartH, $chartH) : '';
$labelIdxs = $n > 1 ? [0, (int) round(($n - 1) * 0.2), (int) round(($n - 1) * 0.4), (int) round(($n - 1) * 0.6), (int) round(($n - 1) * 0.8), $n - 1] : [0];

$pageTitle = 'Visitors — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Visitors</h1>
    <p class="muted" style="margin-top:6px;">How many people are finding Obin Academy, and where they're coming from.</p>
  </div>
  <form method="get" class="row gap-2">
    <select name="days" onchange="this.form.submit()" style="width:auto;">
      <?php foreach ($allowedRanges as $val => $label): ?>
        <option value="<?= $val ?>" <?= $days === $val ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="grid md:grid-4" style="margin-top:20px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('globe'); ?></div>
    <div class="value"><?= number_format($summary['visits']) ?></div><div class="label">Total Visits</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#10b981;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= number_format($summary['unique_visitors']) ?></div><div class="label">Unique Visitors</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('sparkle'); ?></div>
    <div class="value"><?= number_format($summary['new_visitors']) ?></div><div class="label">New Visitors</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('trending-up'); ?></div>
    <div class="value"><?= number_format($summary['returning_visitors']) ?></div><div class="label">Returning Visitors</div>
  </div>
</div>

<div class="grid md:grid-2" style="margin-top:14px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#06b6d4;">
    <div class="icon"><?php dash_icon('clock'); ?></div>
    <div class="value"><?= e(format_duration_short($summary['avg_session_duration'])) ?></div><div class="label">Avg. Session Duration</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#ec4899;">
    <div class="icon"><?php dash_icon('file-text'); ?></div>
    <div class="value"><?= number_format($summary['avg_pages_per_session'], 1) ?></div><div class="label">Avg. Pages / Session</div>
  </div>
</div>

<div class="growth-layout" style="margin-top:28px;">
  <div class="chart-card">
    <div class="chart-card-head">
      <div>
        <h2 class="h3">Daily Visitors</h2>
        <p class="muted small" style="margin-top:4px;">Unique visitors per day &middot; <?= e($allowedRanges[$days]) ?></p>
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
            data-chart-label="<?= e(format_date($visitsSeries[$i]['date'] . ' 00:00:00')) ?>"
            data-chart-value="<?= (int) $visitsSeries[$i]['unique_visitors'] ?> visitors"></circle>
        <?php endforeach; ?>
        <?php if ($points): [$lx, $ly] = end($points); ?>
          <circle cx="<?= round($lx, 1) ?>" cy="<?= round($ly, 1) ?>" r="5" class="chart-end-dot chart-end-dot-blue" style="pointer-events:none;"></circle>
        <?php endif; ?>
      </svg>
      <div class="chart-x-labels">
        <?php foreach ($labelIdxs as $idx): ?>
          <span><?= e(date('M j', strtotime($visitsSeries[$idx]['date']))) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="growth-side">
    <div class="chart-card">
      <h2 class="h3">Traffic Sources</h2>
      <p class="muted small" style="margin-top:4px;">Where visitors came from &middot; <?= e($allowedRanges[$days]) ?></p>
      <div style="margin-top:18px; display:flex; flex-direction:column; gap:14px;">
        <?php foreach ($sourceLabels as $key => $meta): $count = $summary['sources'][$key]; $pct = round($count / $sourceTotal * 100); ?>
          <div>
            <div class="row between" style="font-size:13px; font-weight:700;">
              <span><?= e($meta['label']) ?></span>
              <span class="muted" style="font-weight:600;"><?= number_format($count) ?> (<?= $pct ?>%)</span>
            </div>
            <div class="progress-track" style="margin-top:6px;">
              <div class="progress-fill" style="width:<?= $pct ?>%; background:<?= e($meta['color']) ?>;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Device &amp; Browser</h3>
<div class="grid md:grid-3" style="margin-top:14px;">
  <div class="chart-card">
    <h2 class="h3">Device Type</h2>
    <?php render_bar_list($deviceBreakdown, $deviceLabels); ?>
  </div>
  <div class="chart-card">
    <h2 class="h3">Browser</h2>
    <?php render_bar_list($browserBreakdown); ?>
  </div>
  <div class="chart-card">
    <h2 class="h3">Operating System</h2>
    <?php render_bar_list($osBreakdown); ?>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Entry &amp; Exit Pages</h3>
<div class="grid md:grid-2" style="margin-top:14px; gap:16px;">
  <div>
    <p class="muted small" style="margin-bottom:8px;">Where sessions started</p>
    <div class="table-wrap">
      <?php if ($topPages): ?>
        <table>
          <thead><tr><th>Page</th><th>Sessions</th></tr></thead>
          <tbody>
            <?php foreach ($topPages as $p): ?>
              <tr><td><?= e($p['landing_path']) ?></td><td><?= number_format((int) $p['visitors']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="card" style="padding:28px; text-align:center; border-style:dashed; color:var(--muted);">No visits recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <p class="muted small" style="margin-bottom:8px;">Where sessions ended</p>
    <div class="table-wrap">
      <?php if ($exitPages): ?>
        <table>
          <thead><tr><th>Page</th><th>Sessions</th></tr></thead>
          <tbody>
            <?php foreach ($exitPages as $p): ?>
              <tr><td><?= e($p['exit_path']) ?></td><td><?= number_format((int) $p['n']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="card" style="padding:28px; text-align:center; border-style:dashed; color:var(--muted);">No visits recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Most-Viewed Courses</h3>
<div class="table-wrap" style="margin-top:14px;">
  <?php if ($topCourses): ?>
    <table>
      <thead><tr><th>Course</th><th>Views</th></tr></thead>
      <tbody>
        <?php foreach ($topCourses as $c): ?>
          <tr><td><?= e($c['title']) ?></td><td><?= number_format($c['views']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="card" style="padding:28px; text-align:center; border-style:dashed; color:var(--muted);">No course views recorded yet in this range.</div>
  <?php endif; ?>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Location</h3>
<p class="muted small" style="margin-top:4px;">Approximate, resolved from IP address — never a precise location, and the address itself isn't kept.</p>
<div class="table-wrap" style="margin-top:14px;">
  <?php if ($topCities): ?>
    <table>
      <thead><tr><th>City</th><th>Country</th><th>Sessions</th></tr></thead>
      <tbody>
        <?php foreach ($topCities as $c): ?>
          <tr>
            <td><?= e($c['city']) ?></td>
            <td><?= e($countryNames[$c['country']] ?? $c['country'] ?? '—') ?></td>
            <td><?= number_format((int) $c['n']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="card" style="padding:28px; text-align:center; border-style:dashed; color:var(--muted);">Location data resolves gradually in the background — check back shortly.</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
