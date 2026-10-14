<?php
/**
 * Admin: login activity. A dark console with today's sign-ins and an hour-by-hour pulse, a day-by-hour
 * heatmap for the last 30 days, the top places and devices, and every sign-in as a timeline grouped by
 * day, with admin sign-ins marked in red so an unexpected one stands out.
 *
 * Styles: assets/css/admin-logins.css (scoped under .gx). Counters, heatmap tips and motion: assets/js/admin-logins.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$roleFilter = in_array(query_param('role'), ['LEARNER', 'ADMIN'], true) ? query_param('role') : '';
$whenFilter = in_array(query_param('when'), ['today', '7d', '30d'], true) ? query_param('when') : '';
$filters = ['q' => trim((string) query_param('q')), 'role' => $roleFilter, 'when' => $whenFilter];
$page = max(1, (int) (query_param('page') ?: 1));
$perPage = 30;
$result = get_login_log($filters, $page, $perPage);
$totalLogins = $result['total'];
$totalPages = max(1, (int) ceil($totalLogins / $perPage));
if ($page > $totalPages) { $page = $totalPages; $result = get_login_log($filters, $page, $perPage); }
$logins = $result['rows'];
$dayCounts = get_login_day_counts($filters, array_unique(array_map(fn($l) => date('Y-m-d', strtotime($l['logged_in_at'])), $logins)));

$stats = db_one(
    "SELECT SUM(DATE(logged_in_at) = CURDATE()) AS today_count,
            COUNT(DISTINCT CASE WHEN DATE(logged_in_at) = CURDATE() THEN user_id END) AS today_unique,
            SUM(logged_in_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week_count,
            SUM(logged_in_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND role = 'ADMIN') AS admin_week
     FROM login_log"
);
$todayCount = (int) ($stats['today_count'] ?? 0);
$todayUnique = (int) ($stats['today_unique'] ?? 0);
$weekCount = (int) ($stats['week_count'] ?? 0);
$adminWeek = (int) ($stats['admin_week'] ?? 0);

// Today, hour by hour (East Africa Time, like every date on the site).
$hourly = array_fill(0, 24, 0);
foreach (db_all('SELECT HOUR(logged_in_at) AS h, COUNT(*) AS n FROM login_log WHERE DATE(logged_in_at) = CURDATE() GROUP BY HOUR(logged_in_at)') as $r) $hourly[(int) $r['h']] = (int) $r['n'];
$nowHour = (int) date('G');
$hourMax = max(1, max($hourly));

// Last 30 days by weekday (Mon first) and hour, in five shades relative to the busiest slot.
$heat = array_fill(0, 7, array_fill(0, 24, 0));
foreach (db_all('SELECT WEEKDAY(logged_in_at) AS d, HOUR(logged_in_at) AS h, COUNT(*) AS n FROM login_log WHERE logged_in_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY d, h') as $r) $heat[(int) $r['d']][(int) $r['h']] = (int) $r['n'];
$heatMax = max(1, max(array_map('max', $heat)));
$shade = fn(int $n) => $n === 0 ? 0 : max(1, (int) ceil($n / $heatMax * 4));
$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

$places = get_top_login_locations(30, 5);
$placeMax = $places ? (int) $places[0]['n'] : 1;
$devices = array_map('intval', array_column(db_all("SELECT device_type AS k, COUNT(*) AS n FROM login_log WHERE logged_in_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY device_type"), 'n', 'k'));
$deviceTotal = array_sum($devices);
$handheld = $deviceTotal ? (int) round((($devices['mobile'] ?? 0) + ($devices['tablet'] ?? 0)) / $deviceTotal * 100) : 0;

$loginsUrl = fn(array $over = []): string => base_url('dashboard/admin/logins.php' . (($p = array_filter(array_merge($filters, ['page' => ''], $over), fn($v) => $v !== '' && $v !== null)) ? '?' . http_build_query($p) : ''));
$dayLabel = function (string $d): string {
    if ($d === date('Y-m-d')) return 'Today';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return date('l, j M', strtotime($d));
};
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2))));
$phoneIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>';
$deskIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>';
$shieldIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/></svg>';

$pageTitle = 'Login Activity — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-logins.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov gx" data-gx>
  <div class="gx-head gx-slam" style="--i:0">
    <div><h1>Login activity</h1><p>Every sign-in to Obin Academy: who, when, from what device and where.</p></div>
    <span class="gx-live"><i></i>Watching sign-ins</span>
  </div>

  <section class="gx-console gx-slam" style="--i:1">
    <span class="gx-glow" aria-hidden="true"></span><span class="gx-scan" aria-hidden="true"></span>
    <div>
      <span class="gx-eyebrow">Today</span>
      <div class="gx-big"><b data-scramble="<?= $todayCount ?>"><?= number_format($todayCount) ?></b><span>sign-in<?= $todayCount === 1 ? '' : 's' ?><small><b data-scramble="<?= $todayUnique ?>"><?= number_format($todayUnique) ?></b> different <?= $todayUnique === 1 ? 'person' : 'people' ?></small></span></div>
      <div class="gx-cstats">
        <div><span>This week</span><b data-scramble="<?= $weekCount ?>"><?= number_format($weekCount) ?></b></div>
        <div><span>A day, on average</span><b data-scramble="<?= (int) round($weekCount / 7) ?>"><?= number_format((int) round($weekCount / 7)) ?></b></div>
        <div><span>Admin sign-ins · 7 days</span><b class="red" data-scramble="<?= $adminWeek ?>"><?= number_format($adminWeek) ?></b></div>
      </div>
    </div>
    <div>
      <div class="gx-pulse-h"><b>Today, hour by hour</b><span>East Africa Time</span></div>
      <div class="gx-pulse" role="img" aria-label="Sign-ins today by hour">
        <?php foreach ($hourly as $h => $n): $future = $h > $nowHour; ?>
          <i class="<?= $h === $nowHour ? 'now' : '' ?> <?= $future || !$n ? 'zero' : '' ?>" style="height:<?= $future || !$n ? 3 : max(4, round($n / $hourMax * 100)) ?>%;--h:<?= $h ?>" data-t="<?= sprintf('%02d:00', $h) ?> · <?= $future ? 'not yet' : $n . ' sign-in' . ($n === 1 ? '' : 's') ?>"></i>
        <?php endforeach; ?>
      </div>
      <?php if (!$todayCount): ?><p class="gx-quiet">No sign-ins yet today</p><?php endif; ?>
      <div class="gx-hours" aria-hidden="true"><?php for ($h = 0; $h < 24; $h++): ?><span><?= $h % 3 === 0 ? sprintf('%02d', $h) : '' ?></span><?php endfor; ?></div>
    </div>
  </section>

  <div class="gx-two">
    <section class="gx-sec gx-slam" style="--i:2">
      <div class="gx-sec-h"><div><h2>When people sign in</h2><p>Sign-ins by weekday and hour, last 30 days</p></div></div>
      <div class="gx-heat">
        <div class="gx-hm" data-heat>
          <?php foreach ($weekdays as $d => $label): ?>
            <span><?= $label ?></span>
            <?php for ($h = 0; $h < 24; $h++): $n = $heat[$d][$h]; $lv = $shade($n); ?><i class="l<?= $lv ?>" style="--d:<?= $d ?>;--h:<?= $h ?>" data-t="<?= $label ?> <?= sprintf('%02d:00', $h) ?> · <?= $n ?> sign-in<?= $n === 1 ? '' : 's' ?>"></i><?php endfor; ?>
          <?php endforeach; ?>
        </div>
        <div class="gx-hm-x" aria-hidden="true"><span></span><?php for ($h = 0; $h < 24; $h++): ?><span><?= $h % 6 === 0 ? sprintf('%02d', $h) : '' ?></span><?php endfor; ?></div>
        <div class="gx-scale">Fewer <i class="l0"></i><i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i> More</div>
      </div>
    </section>
    <section class="gx-sec gx-slam" style="--i:3">
      <div class="gx-sec-h"><div><h2>Where from</h2><p>Top places, last 30 days</p></div></div>
      <div class="gx-places">
        <?php if (!$places): ?><p class="gx-none">Locations fill in over the next few hours as people sign in.</p><?php endif; ?>
        <?php foreach ($places as $j => $p): $n = (int) $p['n']; ?>
          <div class="gx-pl gx-whip" style="--j:<?= $j ?>"><span><em title="<?= e(country_name($p['country'])) ?>"><?= e($p['country'] ?: '—') ?></em><?= e($p['city']) ?></span><b><?= number_format($n) ?></b><div class="bar"><i class="gx-snap" style="width:<?= max(3, round($n / $placeMax * 100)) ?>%;--j:<?= $j ?>"></i></div></div>
        <?php endforeach; ?>
        <div class="gx-devs">
          <div class="gx-dv"><?= $phoneIcon ?><div><b><?= $deviceTotal ? $handheld . '%' : '—' ?></b><small>on a phone or tablet</small></div></div>
          <div class="gx-dv"><?= $deskIcon ?><div><b><?= $deviceTotal ? (100 - $handheld) . '%' : '—' ?></b><small>on a computer</small></div></div>
        </div>
        <p class="gx-privacy">Approximate, from the internet address. The address itself is not kept.</p>
      </div>
    </section>
  </div>

  <section class="gx-sec gx-slam" style="--i:4" id="all">
    <div class="gx-sec-h" style="padding-bottom:12px"><div><h2>All sign-ins</h2><p>Newest first. Admin sign-ins are marked in red so an unexpected one stands out.</p></div></div>
    <form method="get" class="gx-tools" action="<?= e(base_url('dashboard/admin/logins.php')) ?>#all">
      <?php if ($roleFilter): ?><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><?php endif; ?>
      <?php if ($whenFilter): ?><input type="hidden" name="when" value="<?= e($whenFilter) ?>"><?php endif; ?>
      <label class="gx-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" name="q" placeholder="Search by name or email" aria-label="Search sign-ins" value="<?= e($filters['q']) ?>"></label>
      <div class="gx-chips">
        <?php foreach (['' => 'Everyone', 'LEARNER' => 'Learners', 'ADMIN' => 'Admins'] as $val => $label): ?>
          <a href="<?= e($loginsUrl(['role' => $val])) ?>#all" class="<?= $roleFilter === $val ? 'on' : '' ?> <?= $val === 'ADMIN' ? 'red' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
      <span class="gx-sep" aria-hidden="true"></span>
      <div class="gx-chips">
        <?php foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '' => 'All'] as $val => $label): ?>
          <a href="<?= e($loginsUrl(['when' => $val])) ?>#all" class="<?= $whenFilter === $val ? 'on' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <?php if ($logins): $lastDay = ''; $j = 0; ?>
      <?php foreach ($logins as $l):
          $day = date('Y-m-d', strtotime($l['logged_in_at']));
          $isAdmin = $l['role'] === 'ADMIN';
          $isPhone = in_array($l['device_type'] ?? '', ['mobile', 'tablet'], true) || in_array($l['os'] ?? '', ['Android', 'iOS'], true);
          $fresh = time() - strtotime($l['logged_in_at']) < 300;
      ?>
        <?php if ($day !== $lastDay): $lastDay = $day; $n = $dayCounts[$day] ?? 0; ?>
          <div class="gx-day"><?= e($dayLabel($day)) ?><em><?= number_format($n) ?> sign-in<?= $n === 1 ? '' : 's' ?></em></div>
        <?php endif; ?>
        <div class="gx-fr gx-whip <?= $isAdmin ? 'admin' : '' ?>" style="--j:<?= $j++ ?>">
          <div class="gx-tm"><?= e(date('H:i', strtotime($l['logged_in_at']))) ?><small><?= $fresh ? '<span class="gx-fresh"><i></i>Just now</span>' : e(time_ago($l['logged_in_at'])) ?></small></div>
          <div class="gx-who"><span class="gx-av"><?= e($initials($l['name'])) ?></span><span><b><?= e($l['name']) ?></b><small><?= e($l['email']) ?></small></span></div>
          <span class="gx-role <?= $isAdmin ? 'admin' : '' ?>"><?= $isAdmin ? $shieldIcon : '' ?><?= e(ucfirst(strtolower($l['role']))) ?></span>
          <div class="gx-dev"><?= $isPhone ? $phoneIcon : $deskIcon ?><span><?= e($l['browser'] ?: 'Unknown browser') ?><small><?= e($l['os'] ?: '') ?></small></span></div>
          <div class="gx-place"><?php if ($l['city'] || $l['country']): ?><em title="<?= e(country_name($l['country'])) ?>"><?= e($l['country'] ?: '—') ?></em><?= e($l['city'] ?: country_name($l['country'])) ?><?php else: ?><span class="gx-muted"><?= time() - strtotime($l['logged_in_at']) < 86400 ? 'Locating…' : 'Unknown' ?></span><?php endif; ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="gx-none" style="padding:34px 22px; text-align:center;">No sign-ins match these filters yet.</div>
    <?php endif; ?>

    <div class="gx-foot">
      <span><?= $totalLogins ? 'Showing ' . number_format(($page - 1) * $perPage + 1) . ' to ' . number_format(min($totalLogins, $page * $perPage)) . ' of ' . number_format($totalLogins) : 'No sign-ins to show' ?></span>
      <?php if ($totalPages > 1):
          $shown = array_unique(array_filter([1, $page - 1, $page, $page + 1, $totalPages], fn($p) => $p >= 1 && $p <= $totalPages));
          sort($shown); $prevN = 0; ?>
        <nav class="gx-pager" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= e($loginsUrl(['page' => $page - 1])) ?>#all" aria-label="Previous">‹</a><?php endif; ?>
          <?php foreach ($shown as $p): if ($prevN && $p - $prevN > 1): ?><span>…</span><?php endif; $prevN = $p; ?>
            <a href="<?= e($loginsUrl(['page' => $p])) ?>#all" class="<?= $p === $page ? 'on' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
          <?php endforeach; ?>
          <?php if ($page < $totalPages): ?><a href="<?= e($loginsUrl(['page' => $page + 1])) ?>#all" aria-label="Next">›</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </section>
</div>
<div class="gx-tip" data-tip aria-hidden="true"></div>
<script src="<?= e(versioned_asset('assets/js/admin-logins.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
