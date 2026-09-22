<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$filters = ['q' => query_param('q'), 'role' => query_param('role'), 'when' => query_param('when')];
$page = max(1, (int) (query_param('page') ?: 1));
$perPage = 30;
$result = get_login_log($filters, $page, $perPage);
$logins = $result['rows'];
$totalLogins = $result['total'];
$totalPages = max(1, (int) ceil($totalLogins / $perPage));

$statCounts = db_one(
    "SELECT
        SUM(DATE(logged_in_at) = CURDATE()) AS today_count,
        COUNT(DISTINCT CASE WHEN DATE(logged_in_at) = CURDATE() THEN user_id END) AS today_unique,
        SUM(logged_in_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week_count
     FROM login_log"
);

$roleTint = ['ADMIN' => '#f87171', 'CREATOR' => '#fbbf24', 'LEARNER' => '#94a3b8'];
$topLocations = get_top_login_locations(30, 8);

$pageTitle = 'Login Activity — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Login Activity</h1>
<p class="muted" style="margin-top:6px;">Every sign-in to the platform, most recent first.</p>

<div class="grid md:grid-3" style="margin-top:20px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= (int) ($statCounts['today_count'] ?? 0) ?></div><div class="label">Logins Today</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#10b981;">
    <div class="icon"><?php dash_icon('check-circle'); ?></div>
    <div class="value"><?= (int) ($statCounts['today_unique'] ?? 0) ?></div><div class="label">Unique Users Today</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('trending-up'); ?></div>
    <div class="value"><?= (int) ($statCounts['week_count'] ?? 0) ?></div><div class="label">Logins This Week</div>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">Top Login Locations</h3>
<p class="muted small" style="margin-top:4px;">Where sign-ins came from in the last 30 days. Approximate, resolved from IP address — never a precise location, and the address itself isn't kept.</p>
<?php if ($topLocations): ?>
  <div class="activity-feed" style="margin-top:14px;">
    <?php foreach ($topLocations as $c): ?>
      <div class="list-row">
        <div class="list-row-main"><span class="small" style="font-weight:600;"><?= e($c['city']) ?></span><span class="small muted">&middot; <?= e(country_name($c['country'])) ?></span></div>
        <div class="list-row-meta small muted"><?= number_format((int) $c['n']) ?> login<?= (int) $c['n'] === 1 ? '' : 's' ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card" style="margin-top:14px; padding:28px; text-align:center; border-style:dashed; color:var(--muted);">Location data resolves gradually in the background — check back shortly.</div>
<?php endif; ?>

<div class="chart-card" style="margin-top:28px; padding:18px 20px;">
  <form method="get" class="leads-filter-bar">
    <div class="field-icon" style="flex:1 1 220px; max-width:280px; margin:0;">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search by name or email" value="<?= e($filters['q']) ?>">
    </div>
    <select name="role" style="flex:0 1 150px;">
      <option value="">All Roles</option>
      <option value="LEARNER" <?= $filters['role'] === 'LEARNER' ? 'selected' : '' ?>>Learner</option>
      <option value="CREATOR" <?= $filters['role'] === 'CREATOR' ? 'selected' : '' ?>>Creator</option>
      <option value="ADMIN" <?= $filters['role'] === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
    </select>
    <select name="when" style="flex:0 1 150px;">
      <option value="">All Time</option>
      <option value="today" <?= $filters['when'] === 'today' ? 'selected' : '' ?>>Today</option>
      <option value="7d" <?= $filters['when'] === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
      <option value="30d" <?= $filters['when'] === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if (array_filter($filters)): ?><a href="<?= e(base_url('dashboard/admin/logins.php')) ?>" class="small muted">Clear</a><?php endif; ?>
    <span class="muted small" style="margin-left:auto; white-space:nowrap;"><?= number_format($totalLogins) ?> login<?= $totalLogins === 1 ? '' : 's' ?></span>
  </form>
</div>

<?php if ($logins): ?>
  <div class="activity-feed" style="margin-top:14px;">
    <?php foreach ($logins as $l): ?>
      <?php $loc = trim(($l['city'] ?? '') . ($l['city'] && $l['country'] ? ', ' : '') . ($l['country'] ? country_name($l['country']) : ''), ' ,'); ?>
      <div class="list-row">
        <div class="list-row-main">
          <div class="row-avatar" style="--tint:<?= e($roleTint[$l['role']]) ?>; background:color-mix(in srgb, var(--tint) 20%, transparent); color:var(--tint); flex-shrink:0;"><?= e(mb_substr($l['name'], 0, 1)) ?></div>
          <div style="min-width:0;">
            <div style="font-weight:700;"><?= e($l['name']) ?></div>
            <div class="small muted" style="margin-top:2px;">
              <?= e($l['email']) ?> &middot; <?= e(format_date($l['logged_in_at'])) ?> <?= e(date('g:i A', strtotime($l['logged_in_at']))) ?><?= $loc ? ' &middot; ' . e($loc) : '' ?><?= $l['browser'] ? ' &middot; ' . e($l['browser']) : '' ?><?= $l['os'] ? ' &middot; ' . e($l['os']) : '' ?>
            </div>
          </div>
        </div>
        <div class="list-row-meta">
          <span class="role-pill" style="--tint:<?= e($roleTint[$l['role']]) ?>;"><?= e(ucfirst(strtolower($l['role']))) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card" style="margin-top:14px; padding:36px; text-align:center; border-style:dashed; color:var(--muted);">No logins match these filters yet.</div>
<?php endif; ?>

<?php if ($totalPages > 1): ?>
  <div class="row gap-2" style="margin-top:16px; justify-content:center; flex-wrap:wrap; row-gap:8px;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="<?= e(base_url('dashboard/admin/logins.php?' . http_build_query(array_filter($filters) + ['page' => $p]))) ?>"
         class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
