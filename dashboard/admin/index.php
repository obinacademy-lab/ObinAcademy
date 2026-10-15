<?php
/**
 * Admin Overview: money, members and what needs doing, across every campus.
 *
 * Laid out as the "Overview Desk" in the public campus page look: a deep blue hero (greeting, four
 * numbers, campus photos), a dark "needs you" card that stays in view, and white blocks (money, campuses,
 * growth, funnel, live classes, activity). The page computes its figures here and hands them to
 * assets/js/admin-overview.js as JSON, which draws the charts, counts the numbers up and switches the
 * date range without reloading. Chart styles: admin-overview.css; the desk: admin-desk.css + admin-desk.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/admin_payments.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

$historyDays = 730; // two years, so every range can be compared with the one before it
$from = date('Y-m-d', strtotime('-' . ($historyDays - 1) . ' days'));

// Campus money per day: [collected, professors' share, payments].
$money = [];
foreach (db_all(
    "SELECT DATE(created_at) AS d, SUM(amount) AS s, COUNT(*) AS n FROM payments
     WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= ? GROUP BY DATE(created_at)",
    [$from . ' 00:00:00']
) as $r) {
    $money[$r['d']] = [(float) $r['s'], 0.0, (int) $r['n']];
}
foreach (campus_professor_share_by_day($from) as $d => $share) {
    if (isset($money[$d])) $money[$d][1] = (float) $share;
}

// Every membership as [joined, access until, yearly?] timestamps: enough to work out who had access on any day.
$memberships = [];
$overview = ['campuses' => [], 'active' => 0, 'ending' => 0];
$campusDaily = [];
$nextLive = [];
$liveClasses = [];
if (campuses_ready()) {
    foreach (db_all('SELECT plan, started_at, access_until FROM campus_memberships') as $m) {
        $memberships[] = [strtotime($m['started_at']), strtotime($m['access_until']), $m['plan'] === 'YEARLY' ? 1 : 0];
    }
    $overview = campus_admin_overview();
    foreach (db_all(
        "SELECT campus_id, DATEDIFF(CURDATE(), DATE(created_at)) AS ago, SUM(amount) AS s FROM payments
         WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
         GROUP BY campus_id, ago"
    ) as $r) {
        $campusDaily[(int) $r['campus_id']][29 - (int) $r['ago']] = (float) $r['s'];
    }
    foreach (db_all('SELECT campus_id, MIN(starts_at) AS s FROM campus_live_classes WHERE starts_at >= NOW() GROUP BY campus_id') as $r) {
        $nextLive[(int) $r['campus_id']] = $r['s'];
    }
    $liveClasses = upcoming_campus_live_classes(7);
}

// Launch steps for each campus card: what is done, and where to go for the next one.
$campusCards = [];
$launch = campus_launch_steps($overview['campuses']);
foreach ($overview['campuses'] as $c) {
    $cid = (int) $c['id'];
    $trend = array_replace(array_fill(0, 30, 0.0), $campusDaily[$cid] ?? []);
    ksort($trend);
    $campusCards[] = $c + $launch[$cid] + ['trend' => array_values($trend)];
}
$openCampuses = count(array_filter($campusCards, fn($c) => $c['status'] === 'PUBLISHED'));

// What needs a person.
$todo = [];
try {
    $apps = db_one("SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM instructor_applications WHERE status = 'NEW'");
    if ((int) $apps['n'] > 0) {
        $todo[] = ['tone' => 'b', 'icon' => 'user-plus', 'title' => 'Teach applications', 'meta' => 'Oldest sent ' . time_ago($apps['oldest']),
            'value' => (string) (int) $apps['n'], 'unit' => 'new', 'href' => 'dashboard/admin/instructor-applications.php'];
    }
} catch (Throwable $e) {}
try {
    $owed = db_one('SELECT COALESCE(SUM(amount),0) AS s, COUNT(DISTINCT campus_id) AS c FROM campus_professor_earnings WHERE payout_id IS NULL');
    if ((float) $owed['s'] > 0) {
        $todo[] = ['tone' => 'w', 'icon' => 'banknote', 'title' => 'Professor pay owed', 'meta' => (int) $owed['c'] . ' campus' . ((int) $owed['c'] === 1 ? '' : 'es') . ', not yet sent',
            'value' => number_format((float) $owed['s']), 'unit' => 'UGX', 'href' => 'dashboard/admin/professor-pay.php'];
    }
} catch (Throwable $e) {}
$payHealth = admin_payment_health();
if ((int) $payHealth['stuck'] > 0) {
    $todo[] = ['tone' => 'r', 'icon' => 'clock', 'title' => 'Payments stuck in pending', 'meta' => 'Waiting over ' . ADMIN_STUCK_PAYMENT_MINUTES . ' minutes, someone may be waiting for access',
        'value' => (string) (int) $payHealth['stuck'], 'unit' => 'payments', 'href' => 'dashboard/admin/payments.php?status=STUCK'];
}
if ((int) $payHealth['failed_24h'] > 0) {
    $todo[] = ['tone' => 'r', 'icon' => 'x-circle', 'title' => 'Failed payments', 'meta' => 'In the last 24 hours',
        'value' => (string) (int) $payHealth['failed_24h'], 'unit' => 'payments', 'href' => 'dashboard/admin/payments.php?status=FAILED'];
}
$newLeads = (int) db_one("SELECT COUNT(*) AS n FROM leads WHERE status = 'NEW'")['n'];
if ($newLeads > 0) {
    $todo[] = ['tone' => 'g', 'icon' => 'sparkle', 'title' => 'New leads', 'meta' => 'Not contacted yet',
        'value' => (string) $newLeads, 'unit' => $newLeads === 1 ? 'lead' : 'leads', 'href' => 'dashboard/admin/leads.php'];
}
$noLink = array_values(array_filter($liveClasses, fn($l) => trim((string) $l['join_url']) === '' && strtotime($l['starts_at']) > time()));
if ($noLink) {
    $todo[] = ['tone' => 'b', 'icon' => 'calendar', 'title' => 'Live class without a link', 'meta' => $noLink[0]['campus_name'] . ' · ' . date('D j M, g:i a', strtotime($noLink[0]['starts_at'])),
        'value' => (string) count($noLink), 'unit' => count($noLink) === 1 ? 'class' : 'classes', 'href' => 'dashboard/admin/campus-edit.php?id=' . (int) $noLink[0]['campus_id'] . '&tab=live'];
}
$drafts = count(array_filter($overview['campuses'], fn($c) => $c['status'] !== 'PUBLISHED'));
if ($drafts > 0) {
    $todo[] = ['tone' => 'b', 'icon' => 'graduation-cap', 'title' => 'Campuses still in draft', 'meta' => 'Not open to learners yet',
        'value' => (string) $drafts, 'unit' => $drafts === 1 ? 'campus' : 'campuses', 'href' => 'dashboard/admin/campuses.php'];
}

// Visitor to member, last 30 days.
$since = date('Y-m-d 00:00:00', strtotime('-29 days'));
$funnel = [
    ['Visited the site', (int) db_one('SELECT COUNT(DISTINCT visitor_id) AS n FROM visitor_sessions WHERE started_at >= ?', [$since])['n']],
    ['Viewed a campus', (int) db_one("SELECT COUNT(DISTINCT visitor_id) AS n FROM visitor_pageviews WHERE entered_at >= ? AND path LIKE '%campus.php?%'", [$since])['n']],
    ['Left their contact', (int) db_one('SELECT COUNT(*) AS n FROM leads WHERE created_at >= ?', [$since])['n']],
    ['Created an account', (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role <> 'ADMIN' AND created_at >= ?", [$since])['n']],
    ['Paid for a campus', (int) db_one("SELECT COUNT(DISTINCT user_id) AS n FROM payments WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= ?", [$since])['n']],
];
$funnelTop = max(1, $funnel[0][1]);

$recentActivity = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 6');

$renewalsDue = $overview['ending'];
$renewalValue = 0.0;
foreach (campus_renewals_due(7) as $m) {
    if (strtotime($m['access_until']) < time()) continue;
    $c = get_campus((int) $m['campus_id']);
    if ($c) $renewalValue += campus_plan_price($c, $m['plan']);
}

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($user['name']))[0];
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2))));
$badgeColors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];

// The last 30 days, printed into the page so the figures are there before the script animates them.
$cut = date('Y-m-d', strtotime('-29 days'));
$init = ['c' => 0.0, 'p' => 0.0, 'n' => 0];
foreach ($money as $d => [$c, $p, $n]) {
    if ($d >= $cut) { $init['c'] += $c; $init['p'] += $p; $init['n'] += $n; }
}
$init['k'] = max(0.0, $init['c'] - $init['p']);

$ovData = [
    'today' => date('Y-m-d'),
    'from' => $from,
    'money' => $money,
    'members' => $memberships,
    'activeNow' => (int) $overview['active'],
];

// ---- the desk layout (approved "Admin Overview Desk" mockup) ----------------------------------
// The most urgent things first: red (payments), amber (money owed), blue (setup), lime (leads).
$toneOrder = ['r' => 0, 'w' => 1, 'b' => 2, 'g' => 3];
usort($todo, fn($a, $b) => $toneOrder[$a['tone']] <=> $toneOrder[$b['tone']]);
$dkShort = function (float $n): string {
    if ($n >= 1e6) return rtrim(rtrim(number_format($n / 1e6, $n >= 1e7 ? 1 : 2), '0'), '.') . 'M';
    if ($n >= 1e3) return rtrim(rtrim(number_format($n / 1e3, $n >= 1e4 ? 0 : 1), '0'), '.') . 'K';
    return (string) (int) $n;
};
$dkBadge = fn(array $t) => $t['unit'] === 'UGX' ? $dkShort((float) str_replace(',', '', $t['value'])) : $t['value'];

// This month, for the small card under the dark one.
$monthStart = date('Y-m-01 00:00:00');
$monthMoney = (float) db_one("SELECT COALESCE(SUM(amount), 0) AS s FROM payments WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= ?", [$monthStart])['s'];
$monthSignups = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role <> 'ADMIN' AND created_at >= ?", [$monthStart])['n'];
$monthVisitors = (int) db_one('SELECT COUNT(DISTINCT visitor_id) AS n FROM visitor_sessions WHERE started_at >= ?', [$monthStart])['n'];
$owedNow = 0.0;
try { $owedNow = (float) db_one('SELECT COALESCE(SUM(amount), 0) AS s FROM campus_professor_earnings WHERE payout_id IS NULL')['s']; } catch (Throwable $e) {}

// Campus photos: open campuses first in the hero mosaic.
$withPhoto = [];
foreach ($campusCards as $c) { $p = campus_photo($c); if ($p) $withPhoto[] = $c + ['photo' => $p]; }
usort($withPhoto, fn($a, $b) => ($b['status'] === 'PUBLISHED') <=> ($a['status'] === 'PUBLISHED'));
$dkMosaic = array_slice($withPhoto, 0, 4);
$dkPhoto = [];
foreach ($withPhoto as $c) $dkPhoto[(int) $c['id']] = $c['photo'];
$openCards = array_values(array_filter($campusCards, fn($c) => $c['status'] === 'PUBLISHED'));
$draftCards = array_values(array_filter($campusCards, fn($c) => $c['status'] !== 'PUBLISHED'));
$dkEdit = fn(int $cid, string $tab = 'details') => base_url('dashboard/admin/campus-edit.php?id=' . $cid . '&tab=' . $tab);
$dkArrow = '<svg class="r" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$dkChev = '<svg class="go" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';

$pageTitle = 'Overview — Obin Academy Admin';
$dashClass = 'dash-ov ovd-shell';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-desk.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov ovd" data-ov>
  <section class="dk-hero">
    <span class="dk-aurora" aria-hidden="true"><i></i><i></i><i></i></span>
    <div class="dk-copy">
      <span class="dk-chip"><i></i><?= e(date('l j F')) ?> · last 30 days</span>
      <h1>
        <span class="dk-line"><span style="--k:0"><?= e($greeting) ?>, <?= e($firstName) ?>.</span></span>
        <span class="dk-line"><span style="--k:1"><?php if ($todo): ?><em><?= count($todo) ?> thing<?= count($todo) === 1 ? '' : 's' ?></em> need<?= count($todo) === 1 ? 's' : '' ?> you.<?php else: ?>You're <em>all caught up.</em><?php endif; ?></span></span>
      </h1>
      <p class="dk-lede">The academy collected <b>UGX <?= e(number_format($init['c'])) ?></b> in the last 30 days, with <?= number_format((int) $overview['active']) ?> member<?= (int) $overview['active'] === 1 ? '' : 's' ?> learning across <?= $openCampuses ?> open campus<?= $openCampuses === 1 ? '' : 'es' ?>.</p>
      <div class="dk-kpis">
        <div class="dk-kpi" style="--k:0"><small>Collected</small><b><span>UGX</span><i data-kpi="collected"><?= e(number_format($init['c'])) ?></i></b><span class="dk-meta" data-kpi-meta="collected"></span></div>
        <div class="dk-kpi" style="--k:1"><small>Kept after professor pay</small><b><span>UGX</span><i data-kpi="kept"><?= e(number_format($init['k'])) ?></i></b><span class="dk-meta" data-kpi-meta="kept"></span></div>
        <div class="dk-kpi" style="--k:2"><small>Active members</small><b><i data-kpi="members"><?= (int) $overview['active'] ?></i></b><span class="dk-meta" data-kpi-meta="members"></span></div>
        <div class="dk-kpi" style="--k:3"><small>Renewals due in 7 days</small><b><i data-count="<?= (int) $renewalsDue ?>"><?= (int) $renewalsDue ?></i></b><span class="dk-meta"><?= $renewalsDue > 0 ? '<span class="ov-chip warn">UGX ' . e(number_format($renewalValue)) . '</span>up for renewal' : 'None ending this week' ?></span></div>
      </div>
    </div>
    <?php if ($dkMosaic): ?>
      <div class="dk-mosaic n<?= count($dkMosaic) ?>">
        <?php foreach ($dkMosaic as $k => $c): ?><a href="<?= e($dkEdit((int) $c['id'])) ?>" style="--k:<?= $k ?>" data-l="<?= e($c['name']) ?>"><img src="<?= e($c['photo']) ?>" alt=""></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <aside class="dk-side">
    <div class="dk-dark">
      <span class="dk-eb">Needs you today</span>
      <h3><?= $todo ? count($todo) . ' thing' . (count($todo) === 1 ? '' : 's') . ' waiting' : 'Nothing waiting' ?></h3>
      <?php if ($todo): ?>
        <div class="dk-todo">
          <?php foreach ($todo as $k => $t): ?>
            <a href="<?= e(base_url($t['href'])) ?>" style="--j:<?= $k ?>"><span class="n <?= e($t['tone']) ?>"><?= e($dkBadge($t)) ?></span><span class="tx"><b><?= e($t['title']) ?></b><small><?= e($t['meta']) ?></small></span><?= $dkChev ?></a>
          <?php endforeach; ?>
        </div>
        <a class="dk-btn lime full" href="<?= e(base_url($todo[0]['href'])) ?>">Start with <?= e(mb_strtolower($todo[0]['title'])) ?><?= $dkArrow ?></a>
      <?php else: ?>
        <p class="dk-clear">No applications, professor pay, stuck payments or new leads waiting on you.</p>
      <?php endif; ?>
      <div class="dk-pair"><a class="dk-btn ghost" href="<?= e(base_url('dashboard/admin/revenue.php')) ?>">Revenue</a><a class="dk-btn ghost" href="<?= e(base_url('dashboard/admin/payments.php')) ?>">Payments</a></div>
      <span class="dk-upd">Updated <?= e(date('g:i a')) ?> · <a href="<?= e(base_url('dashboard/admin/index.php')) ?>">Refresh</a></span>
    </div>
    <div class="dk-white">
      <h4>This <em>month</em></h4>
      <div class="dk-mini"><span>Campus payments</span><b>UGX <?= e(number_format($monthMoney)) ?></b></div>
      <div class="dk-mini"><span>New sign-ups</span><b><?= number_format($monthSignups) ?></b></div>
      <div class="dk-mini"><span>Owed to professors</span><b>UGX <?= e(number_format($owedNow)) ?></b></div>
      <div class="dk-mini"><span>Visitors</span><b><?= number_format($monthVisitors) ?></b></div>
    </div>
  </aside>

  <div class="dk-col">
    <section class="dk-block" style="--b:0">
      <div class="dk-block-h">
        <h2>Money <em>collected</em></h2>
        <div class="dk-tools">
          <div class="ov-seg sm" role="group" aria-label="Chart view">
            <span class="ov-seg-pill" aria-hidden="true"></span>
            <button type="button" data-mode="total" class="on" aria-pressed="true">Running total</button>
            <button type="button" data-mode="day">Per period</button>
          </div>
          <div class="ov-seg" role="group" aria-label="Date range">
            <span class="ov-seg-pill" aria-hidden="true"></span>
            <button type="button" data-range="7">7D</button>
            <button type="button" data-range="30" class="on" aria-pressed="true">30D</button>
            <button type="button" data-range="90">90D</button>
            <button type="button" data-range="365">12M</button>
          </div>
        </div>
      </div>
      <div class="dk-stats">
        <div><small><i style="background:#2b22ff"></i>Collected</small><b data-total="collected">UGX <?= e(number_format($init['c'])) ?></b></div>
        <div><small><i style="background:#8b8fa9"></i>Professors</small><b data-total="professors">UGX <?= e(number_format($init['p'])) ?></b></div>
        <div><small><i style="background:#6aa81e"></i>Kept</small><b data-total="kept">UGX <?= e(number_format($init['k'])) ?></b></div>
        <div><small>Payments</small><b data-total="payments"><?= (int) $init['n'] ?></b></div>
      </div>
      <p class="dk-note" data-chart-note>Running total over the period</p>
      <div class="ov-chart" data-chart="money" style="height:280px"></div>
    </section>

    <section class="dk-block" style="--b:1">
      <div class="dk-block-h"><h2>Your <em>campuses</em></h2><span><?= $openCampuses ?> open · <?= count($draftCards) ?> draft<?= count($draftCards) === 1 ? '' : 's' ?> · <a href="<?= e(base_url('dashboard/admin/campuses.php')) ?>">Manage campuses →</a></span></div>
      <?php if (!$campusCards): ?><p class="dk-empty">No campuses yet.</p><?php endif; ?>
      <?php if ($openCards): ?>
        <span class="dk-sub">Open</span>
        <div class="dk-camps">
          <?php foreach ($openCards as $k => $c): $cid = (int) $c['id']; $active = (int) $c['active']; ?>
            <a class="dk-cc" href="<?= e($dkEdit($cid)) ?>" style="--i:<?= $k ?>">
              <span class="ph"><?php if (isset($dkPhoto[$cid])): ?><img src="<?= e($dkPhoto[$cid]) ?>" alt=""><?php else: ?><span class="ic"><?= campus_icon($c['icon'] ?: 'book', 1.8) ?></span><?php endif; ?><span class="st">Open</span></span>
              <span class="bd">
                <b class="t"><?= e($c['name']) ?></b>
                <span class="pf"><?= $c['professor_name'] ? 'Prof. ' . e($c['professor_name']) : 'No professor yet' ?></span>
                <span class="nums"><span><small>Members</small><b data-count="<?= $active ?>"><?= $active ?></b></span><span><small>30 days</small><b>UGX <?= e($dkShort((float) $c['collected'])) ?></b></span></span>
                <svg class="curve" data-trend="<?= e(json_encode($c['trend'])) ?>" aria-hidden="true"></svg>
                <span class="ft"><?= $c['live'] ? 'Live ' . e(date('D j M · g:i a', strtotime($c['live']))) : '<span class="warn">No live class booked</span>' ?><span><?= (int) $c['ending'] ?> ending</span></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($draftCards): ?>
        <span class="dk-sub">Drafts · getting ready</span>
        <div class="dk-drafts">
          <?php foreach ($draftCards as $k => $c): $cid = (int) $c['id']; $dkNext = null; foreach ($c['steps'] as $j => $st) { if (!$st[1]) { $dkNext = $j; break; } } ?>
            <div class="dk-dr" style="--i:<?= $k ?>">
              <a class="ph" href="<?= e($dkEdit($cid)) ?>"><?php if (isset($dkPhoto[$cid])): ?><img src="<?= e($dkPhoto[$cid]) ?>" alt=""><?php else: ?><span class="ic"><?= campus_icon($c['icon'] ?: 'book', 1.8) ?></span><?php endif; ?></a>
              <span class="tx"><a href="<?= e($dkEdit($cid)) ?>"><b><?= e($c['name']) ?></b></a><small><?= (int) $c['done'] ?> of 5 ready · <?= $c['professor_name'] ? 'Prof. ' . e($c['professor_name']) : 'no professor yet' ?></small></span>
              <span class="steps" title="<?= e(implode(' · ', array_map(fn($st) => $st[0] . ($st[1] ? ' ✓' : ''), $c['steps']))) ?>"><?php foreach ($c['steps'] as $st): ?><i class="<?= $st[1] ? 'on' : '' ?>"></i><?php endforeach; ?></span>
              <?php if ($dkNext !== null): ?><a class="nx" href="<?= e($dkEdit($cid, $c['steps'][$dkNext][4])) ?>"><?= e($c['steps'][$dkNext][3]) ?> →</a><?php else: ?><a class="nx" href="<?= e($dkEdit($cid)) ?>">Ready to publish →</a><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <a class="dk-more" href="<?= e(base_url('dashboard/admin/instructor-applications.php')) ?>">Grow the faculty: Teach applications →</a>
    </section>

    <div class="dk-two">
      <section class="dk-block" style="--b:2">
        <div class="dk-block-h"><h2>Member <em>growth</em></h2><span>Members with access, by month</span></div>
        <div class="dk-legend"><span><i style="background:#2b22ff"></i>All members</span><span><i style="background:#6aa81e"></i>Yearly plan</span></div>
        <div class="ov-chart" data-chart="members" style="height:230px"></div>
      </section>
      <section class="dk-block" style="--b:3">
        <div class="dk-block-h"><h2>Visitor <em>to member</em></h2><span>30 days</span></div>
        <div class="dk-funnel">
          <?php foreach ($funnel as $k => [$label, $n]): ?>
            <div class="fr" style="--i:<?= $k ?>">
              <span class="tp"><?= e($label) ?><b><?= e(number_format($n)) ?><?php if ($k > 0): ?><em><?= $funnel[$k - 1][1] > 0 ? e(number_format($n / $funnel[$k - 1][1] * 100, 1)) . '%' : '—' ?></em><?php endif; ?></b></span>
              <span class="bar"><i style="width:<?= max(1.5, round(sqrt($n / $funnelTop) * 100, 1)) ?>%"></i></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="dk-mini last"><span>Visitor to paid member</span><b><?= $funnel[0][1] > 0 ? e(number_format($funnel[4][1] / $funnel[0][1] * 100, 2)) . '%' : '—' ?></b></div>
      </section>
    </div>

    <div class="dk-two even">
      <section class="dk-block" style="--b:4">
        <div class="dk-block-h"><h2>Live <em>this week</em></h2><a href="<?= e(base_url('dashboard/admin/campuses.php')) ?>">All campuses →</a></div>
        <?php if ($liveClasses): ?>
          <div class="dk-rows">
            <?php foreach (array_slice($liveClasses, 0, 5) as $l): $hasLink = trim((string) $l['join_url']) !== ''; $at = strtotime($l['starts_at']); ?>
              <a class="dk-row" href="<?= e($dkEdit((int) $l['campus_id'], 'live')) ?>"><span class="date"><small><?= e(date('D', $at)) ?></small><b><?= e(date('j', $at)) ?></b></span><span class="tx"><b><?= e($l['title']) ?></b><small><?= e($l['campus_name']) ?> · <?= e(date('g:i a', $at)) ?></small></span><span class="pill <?= $hasLink ? 'g' : 'w' ?>"><?= $hasLink ? 'Link ready' : 'No link yet' ?></span></a>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="dk-empty">None in the next 7 days. Add live classes from each campus's Live classes tab.</p><?php endif; ?>
      </section>
      <section class="dk-block" style="--b:5">
        <div class="dk-block-h"><h2>Recent <em>activity</em></h2><a href="<?= e(base_url('dashboard/admin/audit-log.php')) ?>">Audit log →</a></div>
        <?php if ($recentActivity): ?>
          <div class="dk-rows">
            <?php foreach (array_slice($recentActivity, 0, 5) as $k => $log): ?>
              <div class="dk-row"><span class="av" style="background:<?= e($badgeColors[abs(crc32((string) $log['actor_name'])) % count($badgeColors)]) ?>"><?= e($initials($log['actor_name'] ?: '?')) ?></span><span class="tx"><b><?= e($log['actor_name']) ?> <span><?= e(str_replace(['.', '_'], ' ', strtolower($log['action']))) ?></span></b><small><?= e($log['target_label']) ?><?= $log['detail'] ? ' · ' . e(mb_strimwidth($log['detail'], 0, 70, '…')) : '' ?></small></span><small class="ago"><?= e(time_ago($log['created_at'])) ?></small></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="dk-empty">No activity logged yet.</p><?php endif; ?>
      </section>
    </div>
  </div>
</div>
<script type="application/json" id="ov-data"><?= json_encode($ovData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(versioned_asset('assets/js/admin-overview.js')) ?>" defer></script>
<script src="<?= e(versioned_asset('assets/js/admin-desk.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
