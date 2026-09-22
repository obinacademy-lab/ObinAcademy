<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$courses = db_all('
    SELECT c.*, cat.name AS category_name, (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
    FROM courses c JOIN categories cat ON cat.id = c.category_id
    WHERE c.creator_id = ? ORDER BY c.created_at DESC
', [$user['id']]);

$totalEarnings = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM earnings WHERE creator_id = ?', [$user['id']])['n'] ?? 0);
$publishedCount = 0;
$totalEnrollments = 0;
$totalViews = 0;
foreach ($courses as $c) {
    if ($c['status'] === 'PUBLISHED') $publishedCount++;
    $totalEnrollments += (int) $c['student_count'];
    $totalViews += (int) $c['view_count'];
}
$conversionRate = $totalViews > 0 ? round($totalEnrollments / $totalViews * 100, 1) : 0.0;

$hasSocialLinks = $user['facebook_url'] || $user['instagram_url'] || $user['youtube_url'] || $user['tiktok_url'] || $user['linkedin_url'];

// Same daily-net-earnings series/path-building math as earnings.php's own
// chart, just a shorter window and smaller viewBox to fit the overview
// page's card instead of a full dedicated chart page.
$revenueSeries = get_creator_daily_earnings_series((int) $user['id'], 30);
$dailyRevenue = array_column($revenueSeries, 'collected');
$monthRevenue = array_sum($dailyRevenue);
$revChartW = 560; $revChartH = 140; $revPadTop = 10; $revPadBottom = 4;
$revN = count($revenueSeries);
$revYMax = ($dailyRevenue ? max($dailyRevenue) : 0) ?: 1;
$revYMax *= 1.15;
$revXStep = $revN > 1 ? $revChartW / ($revN - 1) : 0;
$revPoints = [];
foreach ($revenueSeries as $i => $row) {
    $x = $i * $revXStep;
    $y = $revPadTop + ($revChartH - $revPadTop - $revPadBottom) * (1 - $row['collected'] / $revYMax);
    $revPoints[] = [$x, $y];
}
$revLinePath = smooth_svg_path($revPoints);
$revAreaPath = $revPoints ? $revLinePath . sprintf(' L%.2f,%d L0,%d Z', end($revPoints)[0], $revChartH, $revChartH) : '';

// Donut slices — real share-channel counts, converted to a running
// stroke-dasharray/offset per slice (circumference of r=45 is ~282.7).
$shareChannels = get_creator_share_channels((int) $user['id'], 3);
$shareTotal = array_sum(array_column($shareChannels, 'n'));
$donutColors = ['#ec4899', '#8b5cf6', '#f5b301', '#3b82f6'];
$circumference = 2 * M_PI * 45;
$donutOffset = 0;
$donutSlices = [];
foreach ($shareChannels as $i => $row) {
    $pct = $shareTotal > 0 ? $row['n'] / $shareTotal : 0;
    $len = $pct * $circumference;
    $donutSlices[] = ['color' => $donutColors[$i % count($donutColors)], 'len' => $len, 'offset' => -$donutOffset, 'channel' => $row['channel'], 'pct' => round($pct * 100)];
    $donutOffset += $len;
}

$recentActivity = get_recent_activity_feed(null, 5, (int) $user['id']);
$recentPayments = get_creator_recent_payments((int) $user['id'], 5);

$activityTone = ['enrolled' => ['#ffe9ec', '#db2777', '📘'], 'completed' => ['#f0fdf4', '#16a34a', '✓'], 'reviewed' => ['#fff4d6', '#b45309', '★']];
$paymentStatusStyle = ['SUCCESS' => ['Paid', 'var(--dash-good-bg)', 'var(--dash-good)'], 'PENDING' => ['Pending', 'var(--dash-warn-bg)', 'var(--dash-warn)'], 'FAILED' => ['Failed', 'var(--dash-bad-bg)', 'var(--dash-bad)']];

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$statusLabel = ['DRAFT' => 'Draft', 'PENDING_REVIEW' => 'Pending Review', 'PUBLISHED' => 'Published', 'REJECTED' => 'Rejected', 'REMOVED' => 'Removed by Admin'];

$pageTitle = 'My Courses — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3 reveal">
  <div>
    <h1 class="h2">Welcome back, <?= e(explode(' ', trim($user['name']))[0]) ?></h1>
    <p class="muted" style="margin-top:4px;">Manage your courses and track how they're performing.</p>
  </div>
  <a href="<?= e(base_url('dashboard/creator/course-new.php')) ?>" class="btn btn-gold" style="border-radius:999px;">+ Create Course</a>
</div>

<?php if (!$hasSocialLinks): ?>
  <div class="card card-pad row between wrap gap-3 reveal" style="margin-top:20px; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 14%, var(--dash-panel)), var(--dash-panel)); border-color: var(--dash-border);">
    <div class="row gap-2" style="align-items:center;">
      <span class="icon-badge" style="--tint:#2563eb;"><?php dash_icon('share'); ?></span>
      <div>
        <h3 class="small" style="font-weight:700;">Connect your social accounts</h3>
        <p class="small muted" style="margin-top:2px;">Add your Facebook, Instagram, YouTube, TikTok, and LinkedIn — they'll show right on your course pages so learners can follow you.</p>
      </div>
    </div>
    <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="btn btn-outline btn-sm" style="white-space:nowrap;">Add Links</a>
  </div>
<?php endif; ?>

<div class="dash-split reveal" style="margin-top:22px;">
  <div class="chart-card">
    <div class="chart-card-head">
      <div>
        <h2 class="h3">Revenue Overview</h2>
        <p class="muted small" style="margin-top:4px;">Last 30 days, after the platform's 10% fee</p>
      </div>
      <div style="font-size:22px; font-weight:800;"><?= e(format_money($monthRevenue)) ?></div>
    </div>
    <div class="chart-wrap">
      <svg viewBox="0 0 <?= $revChartW ?> <?= $revChartH ?>" preserveAspectRatio="none" class="revenue-chart" style="height:150px;">
        <defs>
          <linearGradient id="ovwRevFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#ec4899" stop-opacity="0.26"/>
            <stop offset="100%" stop-color="#ec4899" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path d="<?= e($revAreaPath) ?>" fill="url(#ovwRevFill)"></path>
        <path d="<?= e($revLinePath) ?>" fill="none" stroke="#ec4899" stroke-width="3" stroke-linecap="round"></path>
      </svg>
    </div>
    <div class="chart-stats-row" style="grid-template-columns:repeat(3,1fr);">
      <div><span class="value"><?= (int) $totalEnrollments ?></span><span class="label">Total Students</span></div>
      <div><span class="value"><?= (int) $publishedCount ?></span><span class="label">Published</span></div>
      <div><span class="value"><?= (int) $totalViews ?></span><span class="label">Course Views</span></div>
    </div>
  </div>

  <div class="chart-card">
    <h2 class="h3">Shares by Channel</h2>
    <?php if (!$donutSlices): ?>
      <p class="muted small" style="margin-top:14px;">No course shares yet — once learners start sharing your courses, this fills in.</p>
    <?php else: ?>
      <div style="display:flex; justify-content:center; margin-top:10px;">
        <svg viewBox="0 0 120 120" style="width:140px; height:140px;">
          <circle cx="60" cy="60" r="45" fill="none" stroke="var(--dash-border-soft)" stroke-width="18"/>
          <?php foreach ($donutSlices as $slice): ?>
            <circle cx="60" cy="60" r="45" fill="none" stroke="<?= e($slice['color']) ?>" stroke-width="18"
              stroke-dasharray="<?= round($slice['len'], 1) ?> <?= round($circumference, 1) ?>"
              stroke-dashoffset="<?= round($slice['offset'], 1) ?>" transform="rotate(-90 60 60)"></circle>
          <?php endforeach; ?>
        </svg>
      </div>
      <div class="stack gap-2" style="margin-top:14px;">
        <?php foreach ($donutSlices as $slice): ?>
          <div class="row between" style="font-size:12.5px;">
            <span class="row gap-2" style="align-items:center; color:var(--muted);"><span style="width:8px;height:8px;border-radius:50%;background:<?= e($slice['color']) ?>;"></span><?= e(ucfirst($slice['channel'])) ?></span>
            <strong><?= $slice['pct'] ?>%</strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px; gap:16px;">
  <div class="stat-card reveal" style="background:linear-gradient(135deg,#ec4899,#be185d); border:none; color:#fff;"><div class="label" style="color:rgba(255,255,255,0.85);">Total Earnings</div><div class="value" style="color:#fff;"><?= e(format_money($totalEarnings)) ?></div></div>
  <div class="stat-card reveal reveal-delay-1" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9); border:none; color:#fff;"><div class="label" style="color:rgba(255,255,255,0.85);">Total Students</div><div class="value" style="color:#fff;"><?= (int) $totalEnrollments ?></div></div>
  <div class="stat-card reveal reveal-delay-2" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8); border:none; color:#fff;"><div class="label" style="color:rgba(255,255,255,0.85);">Course Views</div><div class="value" style="color:#fff;"><?= (int) $totalViews ?></div></div>
  <div class="stat-card reveal reveal-delay-3" style="background:linear-gradient(135deg,#f5b301,#c98e00); border:none; color:#3d2600;"><div class="label" style="color:#5c3d00;">View &rarr; Enroll</div><div class="value" style="color:#3d2600;"><?= $conversionRate ?>%</div></div>
</div>

<div class="dash-split reveal" style="margin-top:20px;">
  <div class="activity-feed">
    <div class="dash-section-label" style="padding:12px 10px 6px;">Recent Activity</div>
    <?php if (!$recentActivity): ?>
      <p class="muted small" style="padding:10px;">No activity yet.</p>
    <?php else: ?>
      <?php foreach ($recentActivity as $a): [$bg, $fg, $emoji] = $activityTone[$a['type']]; ?>
        <div class="activity-row">
          <span class="activity-dot" style="background:<?= $bg ?>; color:<?= $fg ?>;"><?= $emoji ?></span>
          <div class="activity-body">
            <strong><?= e(display_name_initial($a['learner_name'])) ?></strong> <?= e($a['action']) ?> <?= e($a['course_title']) ?>
            <div class="muted small" style="margin-top:2px;"><?= e(time_ago($a['at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card card-pad">
    <div class="dash-section-label" style="margin-bottom:8px;">Recent Enrollments</div>
    <?php if (!$recentPayments): ?>
      <p class="muted small">No payment attempts yet.</p>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($recentPayments as $p): [$label, $bg, $fg] = $paymentStatusStyle[$p['status']]; ?>
          <div class="list-row">
            <div class="list-row-main">
              <span class="row-avatar"><?= e(mb_substr($p['learner_name'] ?? 'G', 0, 1)) ?></span>
              <div style="min-width:0;">
                <div style="font-weight:700; font-size:13.5px;"><?= e($p['learner_name'] ?? 'Guest') ?></div>
                <div class="small muted" style="margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($p['course_title']) ?></div>
              </div>
            </div>
            <div class="list-row-meta">
              <span class="small" style="font-weight:700;"><?= e(format_money((float) $p['amount'])) ?></span>
              <span style="padding:3px 9px; border-radius:6px; background:<?= $bg ?>; color:<?= $fg ?>; font-size:10.5px; font-weight:700; flex-shrink:0;"><?= $label ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$courses): ?>
  <div class="card card-pad reveal" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">You haven't created any courses yet.</p>
    <a href="<?= e(base_url('dashboard/creator/course-new.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Create Your First Course</a>
  </div>
<?php else: ?>
  <h2 class="h3 reveal" style="margin-top:32px;">Your Courses</h2>
  <div class="activity-feed reveal" style="margin-top:14px;">
    <?php foreach ($courses as $c): ?>
      <div class="list-row">
        <div class="list-row-main">
          <span class="activity-dot tone-neutral" style="flex-shrink:0;"><?php dash_icon('book-open'); ?></span>
          <div style="min-width:0;">
            <div style="font-weight:700;"><?= e($c['title']) ?></div>
            <div class="small muted" style="margin-top:2px;"><?= e($c['category_name']) ?> &middot; <?= e(format_money((float) $c['price'])) ?> &middot; <?= (int) $c['student_count'] ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?></div>
          </div>
        </div>
        <div class="list-row-meta">
          <span class="badge <?= $badgeClass[$c['status']] ?>"><?= $statusLabel[$c['status']] ?></span>
          <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $c['id'])) ?>" class="btn btn-dark btn-sm">Manage</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
