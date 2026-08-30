<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/data.php';
$user = require_role(['ADMIN']);

$userCount = (int) db_one('SELECT COUNT(*) AS n FROM users')['n'];
$learnerCount = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='LEARNER'")['n'];
$creatorCount = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='CREATOR'")['n'];
$categoryCount = (int) db_one('SELECT COUNT(*) AS n FROM categories')['n'];
$courseCount = (int) db_one('SELECT COUNT(*) AS n FROM courses')['n'];
$publishedCount = (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE status='PUBLISHED'")['n'];
$pendingCount = (int) db_one("SELECT COUNT(*) AS n FROM courses WHERE status='PENDING_REVIEW'")['n'];
$enrollmentCount = (int) db_one('SELECT COUNT(*) AS n FROM enrollments')['n'];
$platformRevenue = (float) (db_one('SELECT COALESCE(SUM(platform_fee),0) AS n FROM earnings')['n'] ?? 0);
$pendingWithdrawalCount = (int) db_one("SELECT COUNT(*) AS n FROM withdrawal_requests WHERE status='PENDING'")['n'];
$pendingApplicationCount = (int) db_one("SELECT COUNT(*) AS n FROM creator_applications WHERE status='PENDING'")['n'];

$recentUsers = db_all('SELECT * FROM users ORDER BY created_at DESC LIMIT 5');
$recentCourses = db_all('SELECT c.*, cat.name AS category_name, u.name AS creator_name FROM courses c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.creator_id ORDER BY c.created_at DESC LIMIT 5');
$pendingCourses = db_all("SELECT c.*, u.name AS creator_name FROM courses c JOIN users u ON u.id=c.creator_id WHERE c.status='PENDING_REVIEW' ORDER BY c.submitted_at ASC");

$recentActivity = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 8');

$topCreators = db_all("
    SELECT u.id, u.name, u.avatar_url,
      COALESCE(SUM(e.amount), 0) AS total_earned,
      COUNT(DISTINCT e.course_id) AS course_count
    FROM users u
    LEFT JOIN earnings e ON e.creator_id = u.id
    WHERE u.role = 'CREATOR'
    GROUP BY u.id, u.name, u.avatar_url
    ORDER BY total_earned DESC, u.name ASC
    LIMIT 5
");

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

$badgeClass = ['DRAFT' => 'badge-draft', 'PENDING_REVIEW' => 'badge-pending', 'PUBLISHED' => 'badge-published', 'REJECTED' => 'badge-rejected', 'REMOVED' => 'badge-rejected'];
$roleTint = ['ADMIN' => '#dc2626', 'CREATOR' => '#b45309', 'LEARNER' => '#64748b'];

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
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Welcome back, <?= e(explode(' ', trim($user['name']))[0]) ?> 👋</h1>
    <p class="muted" style="margin-top:6px;">A bird's-eye view of everything happening on Obin Academy.</p>
  </div>
  <div class="status-pill"><span class="status-dot"></span>All systems operational</div>
</div>

<?php $totalPending = $pendingCount + $pendingWithdrawalCount + $pendingApplicationCount; ?>
<?php if ($totalPending > 0): ?>
  <div class="quick-actions">
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
  <div class="all-caught-up">
    <?php dash_icon('check-circle'); ?>
    <div><strong>You're all caught up.</strong> No pending reviews, withdrawals, or applications right now.</div>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:32px;">Needs Your Attention</h3>
<div class="grid md:grid-3" style="margin-top:14px;">
  <div class="stat-card <?= $pendingCount > 0 ? 'highlight' : '' ?>" data-hoverable="true" style="--hover-color:#f59e0b;">
    <div class="icon"><?php dash_icon('clipboard-check'); ?></div>
    <div class="value"><?= $pendingCount ?></div><div class="label">Pending Review</div>
  </div>
  <a href="<?= e(base_url('dashboard/admin/withdrawals.php')) ?>" class="stat-card-link">
    <div class="stat-card <?= $pendingWithdrawalCount > 0 ? 'highlight' : '' ?>" data-hoverable="true" style="--hover-color:#f97316;">
      <div class="icon"><?php dash_icon('banknote'); ?></div>
      <div class="value"><?= $pendingWithdrawalCount ?></div><div class="label">Pending Withdrawals</div>
    </div>
  </a>
  <a href="<?= e(base_url('dashboard/admin/creator-applications.php')) ?>" class="stat-card-link">
    <div class="stat-card <?= $pendingApplicationCount > 0 ? 'highlight' : '' ?>" data-hoverable="true" style="--hover-color:#ec4899;">
      <div class="icon"><?php dash_icon('user-plus'); ?></div>
      <div class="value"><?= $pendingApplicationCount ?></div><div class="label">Creator Applications</div>
    </div>
  </a>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Platform Growth</h3>
<div class="growth-layout" style="margin-top:14px;">
  <div class="chart-card">
    <div class="chart-card-head">
      <div>
        <h2 class="h3">Platform Revenue Growth</h2>
        <p class="muted small" style="margin-top:4px;">Cumulative 10% platform fee income &middot; last 30 days</p>
      </div>
      <?php if ($trendPct !== null): ?>
        <div class="chart-trend <?= $trendPct >= 0 ? 'up' : 'down' ?>">
          <?php dash_icon('trending-up'); ?><?= $trendPct >= 0 ? '+' : '' ?><?= $trendPct ?>% vs last week
        </div>
      <?php endif; ?>
    </div>

    <div class="chart-stats-row">
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
        <?php if ($points): [$lx, $ly] = end($points); ?>
          <circle cx="<?= round($lx, 1) ?>" cy="<?= round($ly, 1) ?>" r="5" class="chart-end-dot">
            <title><?= e(format_money($revenueTotal)) ?> total as of today</title>
          </circle>
        <?php endif; ?>
      </svg>
      <div class="chart-x-labels">
        <?php foreach ($labelIdxs as $idx): ?>
          <span><?= e(date('M j', strtotime($revenueSeries[$idx]['date']))) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="growth-side">
    <div class="stat-card" data-hoverable="true" style="--hover-color:#3b82f6;">
      <div class="icon"><?php dash_icon('book-open'); ?></div>
      <div class="value"><?= $publishedCount ?>/<?= $courseCount ?></div><div class="label">Courses</div><div class="sub">published / total</div>
    </div>
    <div class="stat-card" data-hoverable="true" style="--hover-color:#06b6d4;">
      <div class="icon"><?php dash_icon('graduation-cap'); ?></div>
      <div class="value"><?= $enrollmentCount ?></div><div class="label">Enrollments</div>
    </div>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Community</h3>
<div class="grid md:grid-3 lg:grid-4" style="margin-top:14px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#8b5cf6;"><div class="icon"><?php dash_icon('users'); ?></div><div class="value"><?= $userCount ?></div><div class="label">Total Users</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#ec4899;"><div class="icon"><?php dash_icon('graduation-cap'); ?></div><div class="value"><?= $learnerCount ?></div><div class="label">Learners</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#10b981;"><div class="icon"><?php dash_icon('sparkle'); ?></div><div class="value"><?= $creatorCount ?></div><div class="label">Creators</div></div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#6366f1;"><div class="icon"><?php dash_icon('tag'); ?></div><div class="value"><?= $categoryCount ?></div><div class="label">Categories</div></div>
</div>

<?php if ($pendingCourses): ?>
  <h2 class="h3" style="margin-top:36px;">Awaiting Your Review</h2>
  <div class="leaderboard" style="margin-top:14px;">
    <?php foreach ($pendingCourses as $c): ?>
      <div class="leaderboard-row">
        <div class="row-avatar" style="--tint:#f59e0b; background:color-mix(in srgb, var(--tint) 15%, white); color:var(--tint);"><?php dash_icon('clipboard-check'); ?></div>
        <div class="leaderboard-info">
          <div style="font-weight:600;"><?= e($c['title']) ?></div>
          <div class="small muted"><?= e($c['creator_name']) ?> &middot; submitted <?= e(format_date($c['submitted_at'] ?? $c['created_at'])) ?></div>
        </div>
        <a href="<?= e(base_url('dashboard/admin/course-review.php?id=' . $c['id'])) ?>" class="btn btn-dark btn-sm">Review</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="grid lg:grid-2" style="margin-top:36px; gap:32px;">
  <div>
    <div class="row between" style="align-items:center;">
      <h2 class="h3">Recent Activity</h2>
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

  <div>
    <h2 class="h3">Top Creators</h2>
    <div class="leaderboard" style="margin-top:14px;">
      <?php if (!$topCreators): ?>
        <p class="muted small" style="padding:16px 0;">No creators yet.</p>
      <?php endif; ?>
      <?php foreach ($topCreators as $rank => $creator): ?>
        <div class="leaderboard-row">
          <span class="rank rank-<?= $rank + 1 <= 3 ? $rank + 1 : 'plain' ?>"><?= $rank === 0 ? '' : $rank + 1 ?><?php if ($rank === 0) dash_icon('crown'); ?></span>
          <div class="row-avatar"><?= e(mb_substr($creator['name'], 0, 1)) ?></div>
          <div class="leaderboard-info">
            <div style="font-weight:600;"><?= e($creator['name']) ?></div>
            <div class="small muted"><?= (int) $creator['course_count'] ?> course<?= (int) $creator['course_count'] === 1 ? '' : 's' ?> sold</div>
          </div>
          <div class="leaderboard-value"><?= e(format_money((float) $creator['total_earned'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid lg:grid-2" style="margin-top:36px; gap:32px;">
  <div>
    <div class="row between" style="align-items:center;">
      <h2 class="h3">Newest Users</h2>
      <a href="<?= e(base_url('dashboard/admin/users.php')) ?>" class="small" style="color:var(--accent); font-weight:700;">View all →</a>
    </div>
    <div class="leaderboard" style="margin-top:14px;">
      <?php foreach ($recentUsers as $u): $tint = $roleTint[$u['role']] ?? '#64748b'; ?>
        <div class="leaderboard-row">
          <div class="row-avatar" style="--tint:<?= e($tint) ?>; background:color-mix(in srgb, var(--tint) 15%, white); color:var(--tint);"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
          <div class="leaderboard-info">
            <div style="font-weight:600;"><?= e($u['name']) ?></div>
            <div class="small muted"><?= e($u['email']) ?></div>
          </div>
          <span class="role-pill" style="--tint:<?= e($tint) ?>;"><?= e($u['role']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div>
    <div class="row between" style="align-items:center;">
      <h2 class="h3">Newest Courses</h2>
      <a href="<?= e(base_url('dashboard/admin/courses.php')) ?>" class="small" style="color:var(--accent); font-weight:700;">View all →</a>
    </div>
    <div class="leaderboard" style="margin-top:14px;">
      <?php foreach ($recentCourses as $c): ?>
        <div class="leaderboard-row">
          <div class="row-avatar" style="background:var(--surface); color:var(--brand-800);"><?php dash_icon('book-open'); ?></div>
          <div class="leaderboard-info">
            <div style="font-weight:600;"><?= e($c['title']) ?></div>
            <div class="small muted"><?= e($c['creator_name']) ?> &middot; <?= e($c['category_name']) ?></div>
          </div>
          <span class="badge <?= $badgeClass[$c['status']] ?>"><?= $c['status'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
