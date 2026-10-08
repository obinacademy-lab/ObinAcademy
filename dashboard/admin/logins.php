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
if ($page > $totalPages) { $page = $totalPages; $result = get_login_log($filters, $page, $perPage); $logins = $result["rows"]; }

$statCounts = db_one(
    "SELECT
        SUM(DATE(logged_in_at) = CURDATE()) AS today_count,
        COUNT(DISTINCT CASE WHEN DATE(logged_in_at) = CURDATE() THEN user_id END) AS today_unique,
        SUM(logged_in_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week_count
     FROM login_log"
);
$todayCount = (int) ($statCounts['today_count'] ?? 0);
$todayUnique = (int) ($statCounts['today_unique'] ?? 0);
$weekCount = (int) ($statCounts['week_count'] ?? 0);

$roleTint = ['ADMIN' => '#dc2626', 'CREATOR' => '#d97706', 'LEARNER' => '#5b6b85'];
$topLocations = get_top_login_locations(30, 8);
$locMax = $topLocations ? max(array_map(fn($l) => (int) $l['n'], $topLocations)) : 1;

$loginsUrl = fn(array $over = []): string => base_url('dashboard/admin/logins.php' . (($p = array_filter(array_merge($filters, ['page' => ''], $over), fn($v) => $v !== '' && $v !== null)) ? '?' . http_build_query($p) : ''));
$dayLabel = function (string $ts): string {
    $d = date('Y-m-d', strtotime($ts));
    if ($d === date('Y-m-d')) return 'Today, ' . date('M j', strtotime($ts));
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday, ' . date('M j', strtotime($ts));
    return date('l, M j', strtotime($ts));
};
$phoneIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>';
$deskIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>';

$pageTitle = 'Login Activity — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Login Activity</h1>
    <p class="muted" style="margin-top:6px;">Every sign-in to the platform, most recent first.</p>
  </div>
</div>

<div class="ld-panel ld-strip cols3" style="margin-top:20px;">
  <div><b><?= number_format($todayCount) ?></b><span>Logins today</span></div>
  <div><b><?= number_format($todayUnique) ?></b><span>Unique users today</span></div>
  <div><b><?= number_format($weekCount) ?></b><span>Logins this week</span><?php if ($weekCount > 6): ?><small>about <?= number_format((int) round($weekCount / 7)) ?> a day</small><?php endif; ?></div>
</div>

<div class="st-sec" style="margin-top:32px;">
  <h2>Top login locations</h2>
  <p>Where sign-ins came from in the last 30 days. Approximate, taken from the IP address. The address itself is not kept.</p>
</div>
<?php if ($topLocations): ?>
  <div class="st-panel st-where">
    <?php foreach ($topLocations as $c): $n = (int) $c['n']; ?>
      <div class="st-loc">
        <div><strong><?= e($c['city']) ?></strong><small><?= e(country_name($c['country'])) ?></small></div>
        <div class="st-bar"><i style="width:<?= max(4, (int) round($n / $locMax * 100)) ?>%"></i></div>
        <div class="st-n"><?= number_format($n) ?> <small>login<?= $n === 1 ? '' : 's' ?></small></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="st-empty">
    <?php dash_icon('map-pin'); ?>
    <div><strong>Locations are still being worked out.</strong><br>They fill in over the next few hours as people sign in. Check back later.</div>
  </div>
<?php endif; ?>

<div class="ld-sec" style="margin-top:32px;"><h2>All sign-ins</h2></div>
<div class="ld-panel" style="overflow:hidden;">
  <form method="get" class="ld-tools">
    <label class="ld-field ld-grow"><?php dash_icon('search'); ?><input type="search" name="q" placeholder="Search by name or email" aria-label="Search sign-ins" value="<?= e($filters['q']) ?>"></label>
    <label class="ld-field"><select name="role" aria-label="Role" onchange="this.form.submit()">
      <option value="">All roles</option>
      <option value="LEARNER" <?= $filters['role'] === 'LEARNER' ? 'selected' : '' ?>>Learner</option>
      <option value="CREATOR" <?= $filters['role'] === 'CREATOR' ? 'selected' : '' ?>>Creator</option>
      <option value="ADMIN" <?= $filters['role'] === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
    </select></label>
    <label class="ld-field"><select name="when" aria-label="When" onchange="this.form.submit()">
      <option value="">All time</option>
      <option value="today" <?= $filters['when'] === 'today' ? 'selected' : '' ?>>Today</option>
      <option value="7d" <?= $filters['when'] === '7d' ? 'selected' : '' ?>>Last 7 days</option>
      <option value="30d" <?= $filters['when'] === '30d' ? 'selected' : '' ?>>Last 30 days</option>
    </select></label>
    <?php if (array_filter($filters)): ?><a href="<?= e(base_url('dashboard/admin/logins.php')) ?>" class="small muted">Clear</a><?php endif; ?>
    <span class="ld-count"><?= number_format($totalLogins) ?> login<?= $totalLogins === 1 ? '' : 's' ?></span>
  </form>

  <?php if ($logins): $lastDay = ''; ?>
    <div class="lg-grid lg-th"><span>Person</span><span>Role</span><span>When</span><span>Device</span><span>Location</span></div>
    <?php foreach ($logins as $l):
        $loc = trim(($l['city'] ?? '') . ($l['city'] && $l['country'] ? ', ' : '') . ($l['country'] ? country_name($l['country']) : ''), ' ,');
        $day = date('Y-m-d', strtotime($l['logged_in_at']));
        $isPhone = in_array($l['os'] ?? '', ['Android', 'iOS'], true);
        $c = $roleTint[$l['role']] ?? '#5b6b85';
    ?>
      <?php if ($day !== $lastDay): $lastDay = $day; ?><div class="lg-day"><?= e($dayLabel($l['logged_in_at'])) ?></div><?php endif; ?>
      <div class="lg-grid lg-row" style="--c:<?= e($c) ?>;">
        <div class="ld-who">
          <div class="ld-av"><?= e(mb_strtoupper(mb_substr($l['name'], 0, 1))) ?></div>
          <div style="min-width:0;"><span class="nm" style="display:block; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($l['name']) ?></span><small><?= e($l['email']) ?></small></div>
        </div>
        <span class="ld-pill"><i></i><?= e(ucfirst(strtolower($l['role']))) ?></span>
        <div class="lg-time"><?= e(date('g:i A', strtotime($l['logged_in_at']))) ?><small><?= e(format_date($l['logged_in_at'])) ?></small></div>
        <div class="lg-dev"><?= $isPhone ? $phoneIcon : $deskIcon ?><span><?= e($l['browser'] ?: 'Unknown') ?><small><?= e($l['os'] ?: '') ?></small></span></div>
        <span class="lg-place"><?= $loc ? e($loc) : '—' ?></span>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="ld-none">No sign-ins match these filters yet.</div>
  <?php endif; ?>

  <div class="ld-foot">
    <span><?= $totalLogins ? 'Showing ' . number_format(($page - 1) * $perPage + 1) . ' to ' . number_format(min($totalLogins, $page * $perPage)) . ' of ' . number_format($totalLogins) : 'No sign-ins to show' ?></span>
    <?php if ($totalPages > 1):
        $shown = array_unique(array_filter([1, $page - 1, $page, $page + 1, $totalPages], fn($p) => $p >= 1 && $p <= $totalPages));
        sort($shown); $prevN = 0; ?>
      <nav class="ld-pager" aria-label="Pages">
        <?php if ($page > 1): ?><a href="<?= e($loginsUrl(['page' => $page - 1])) ?>">Previous</a><?php else: ?><a class="dis" aria-disabled="true">Previous</a><?php endif; ?>
        <?php foreach ($shown as $p): if ($prevN && $p - $prevN > 1): ?><span class="gap">&hellip;</span><?php endif; $prevN = $p; ?>
          <a href="<?= e($loginsUrl(['page' => $p])) ?>" class="<?= $p === $page ? 'on' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?><a href="<?= e($loginsUrl(['page' => $page + 1])) ?>">Next</a><?php else: ?><a class="dis" aria-disabled="true">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
