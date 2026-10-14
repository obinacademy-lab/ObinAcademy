<?php
/**
 * Admin Overview: money, members and what needs doing, across every campus.
 *
 * The page computes its figures here and hands them to assets/js/admin-overview.js as JSON, which
 * draws the charts, counts the numbers up and switches the date range (7 days, 30 days, 90 days,
 * 12 months) without reloading. Styles are scoped under .ov in assets/css/admin-overview.css.
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

$pageTitle = 'Overview — Obin Academy Admin';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov" data-ov>
  <section class="ov-head ov-in" style="--i:0">
    <div>
      <h1><?= e($greeting) ?>, <?= e($firstName) ?></h1>
      <p>
        <?php if ($todo): ?>
          <span class="ov-status warn"><?= count($todo) ?> thing<?= count($todo) === 1 ? '' : 's' ?> need<?= count($todo) === 1 ? 's' : '' ?> you</span>
        <?php else: ?>
          <span class="ov-status">All caught up</span>
        <?php endif; ?>
        <span><?= e(date('l, j F Y')) ?></span>
      </p>
    </div>
    <div class="ov-head-actions">
      <div class="ov-seg" role="group" aria-label="Date range">
        <span class="ov-seg-pill" aria-hidden="true"></span>
        <button type="button" data-range="7">7D</button>
        <button type="button" data-range="30" class="on" aria-pressed="true">30D</button>
        <button type="button" data-range="90">90D</button>
        <button type="button" data-range="365">12M</button>
      </div>
      <a class="ov-btn" href="<?= e(base_url('dashboard/admin/revenue.php')) ?>"><?php dash_icon('trending-up'); ?>Revenue</a>
      <a class="ov-btn primary" href="<?= e(base_url('dashboard/admin/campuses.php')) ?>"><?php dash_icon('graduation-cap'); ?>Campuses</a>
    </div>
  </section>

  <section class="ov-grid">
    <article class="ov-card ov-kpi ov-c3 ov-in" style="--i:1">
      <div class="ov-kpi-top"><span class="ov-kpi-label">Collected</span><span class="ov-kpi-ic"><?php dash_icon('trending-up'); ?></span></div>
      <div class="ov-kpi-val"><small>UGX</small><span data-kpi="collected"><?= e(number_format($init['c'])) ?></span></div>
      <div class="ov-kpi-meta" data-kpi-meta="collected"></div>
      <svg class="ov-spark" data-spark="collected" aria-hidden="true"></svg>
    </article>
    <article class="ov-card ov-kpi ov-c3 ov-in" style="--i:2">
      <div class="ov-kpi-top"><span class="ov-kpi-label">Kept after professor pay</span><span class="ov-kpi-ic"><?php dash_icon('wallet'); ?></span></div>
      <div class="ov-kpi-val"><small>UGX</small><span data-kpi="kept"><?= e(number_format($init['k'])) ?></span></div>
      <div class="ov-kpi-meta" data-kpi-meta="kept"></div>
      <svg class="ov-spark" data-spark="kept" aria-hidden="true"></svg>
    </article>
    <article class="ov-card ov-kpi ov-c3 ov-in" style="--i:3">
      <div class="ov-kpi-top"><span class="ov-kpi-label">Active members</span><span class="ov-kpi-ic"><?php dash_icon('users'); ?></span></div>
      <div class="ov-kpi-val"><span data-kpi="members"><?= (int) $overview['active'] ?></span></div>
      <div class="ov-kpi-meta" data-kpi-meta="members"></div>
      <svg class="ov-spark" data-spark="members" aria-hidden="true"></svg>
    </article>
    <article class="ov-card ov-kpi ov-c3 ov-in" style="--i:4">
      <div class="ov-kpi-top"><span class="ov-kpi-label">Renewals due in 7 days</span><span class="ov-kpi-ic"><?php dash_icon('calendar'); ?></span></div>
      <div class="ov-kpi-val"><span data-count="<?= (int) $renewalsDue ?>"><?= (int) $renewalsDue ?></span></div>
      <div class="ov-kpi-meta">
        <?php if ($renewalsDue > 0): ?><span class="ov-chip warn">UGX <?= e(number_format($renewalValue)) ?></span>up for renewal<?php else: ?>No memberships ending this week<?php endif; ?>
      </div>
      <svg class="ov-spark" data-spark="renewals" aria-hidden="true"></svg>
    </article>
  </section>

  <section class="ov-grid">
    <article class="ov-card ov-c8 ov-in" style="--i:5">
      <div class="ov-card-h">
        <div><h2>Collections</h2><p data-chart-note>Campus payments, and what Obin Academy keeps after professor pay</p></div>
        <div class="ov-card-tools">
          <div class="ov-legend"><span><i class="c-brand"></i>Collected</span><span><i class="c-kept"></i>Kept</span></div>
          <div class="ov-seg sm" role="group" aria-label="Chart view">
            <span class="ov-seg-pill" aria-hidden="true"></span>
            <button type="button" data-mode="total" class="on" aria-pressed="true">Running total</button>
            <button type="button" data-mode="day">Per period</button>
          </div>
        </div>
      </div>
      <div class="ov-totals">
        <div><span><i class="c-brand"></i>Collected</span><b data-total="collected">UGX <?= e(number_format($init['c'])) ?></b></div>
        <div><span><i class="c-muted"></i>Professors</span><b data-total="professors">UGX <?= e(number_format($init['p'])) ?></b></div>
        <div><span><i class="c-kept"></i>Kept</span><b data-total="kept">UGX <?= e(number_format($init['k'])) ?></b></div>
        <div><span>Payments</span><b data-total="payments"><?= (int) $init['n'] ?></b></div>
      </div>
      <div class="ov-card-b ov-chart-b"><div class="ov-chart" data-chart="money" style="height:300px"></div></div>
    </article>

    <article class="ov-card ov-c4 ov-in" style="--i:6">
      <div class="ov-card-h"><div><h2>Needs your attention</h2><p><?= $todo ? count($todo) . ' item' . (count($todo) === 1 ? '' : 's') . ' waiting on you' : 'Nothing waiting on you' ?></p></div></div>
      <?php if ($todo): ?>
        <div class="ov-todo">
          <?php foreach ($todo as $k => $t): ?>
            <a href="<?= e(base_url($t['href'])) ?>" style="--j:<?= $k ?>">
              <span class="ov-t-ic <?= e($t['tone']) ?>"><?php dash_icon($t['icon']); ?></span>
              <span class="ov-t-text"><b><?= e($t['title']) ?></b><small><?= e($t['meta']) ?></small></span>
              <span class="ov-t-val"><?= e($t['value']) ?><em><?= e($t['unit']) ?></em></span>
              <svg class="ov-t-go" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="ov-clear">
          <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="20"/><path d="M15 24.5l6 6 12-13"/></svg>
          <b>You're all caught up</b>
          <span>No applications, professor pay, stuck payments or new leads waiting.</span>
        </div>
      <?php endif; ?>
      <div class="ov-todo-foot"><span>Updated <?= e(date('g:i a')) ?></span><a href="<?= e(base_url('dashboard/admin/index.php')) ?>">Refresh</a></div>
    </article>
  </section>

  <section class="ov-grid">
    <article class="ov-card ov-c12 ov-in ov-cc" style="--i:7" data-cc>
      <div class="ov-cc-h">
        <div>
          <h2>Campuses</h2>
          <p>Each campus at a glance: how it's doing once it's open, and what's left before it can open</p>
          <div class="ov-cc-sum">
            <div><b><?= $openCampuses ?> <small>of <?= count($campusCards) ?></small></b><span>open to learners</span></div>
            <div><b><?= number_format((int) $overview['active']) ?></b><span>members</span></div>
            <div><b><small>UGX</small><?= e(number_format((float) ($overview['collected'] ?? 0))) ?></b><span>last 30 days</span></div>
          </div>
        </div>
        <div class="ov-cc-tools">
          <div class="ov-seg sm" role="group" aria-label="Show campuses">
            <span class="ov-seg-pill" aria-hidden="true"></span>
            <button type="button" data-filter="all" class="on" aria-pressed="true">All <em><?= count($campusCards) ?></em></button>
            <button type="button" data-filter="open">Open <em><?= $openCampuses ?></em></button>
            <button type="button" data-filter="draft">Drafts <em><?= count($campusCards) - $openCampuses ?></em></button>
          </div>
          <a class="ov-link" href="<?= e(base_url('dashboard/admin/campuses.php')) ?>">Manage campuses <span>→</span></a>
        </div>
      </div>
      <?php if ($campusCards): ?>
      <div class="ov-cc-grid">
        <?php foreach ($campusCards as $k => $c):
            $cid = (int) $c['id'];
            $isOpen = $c['status'] === 'PUBLISHED';
            $active = (int) $c['active'];
            $edit = fn(string $tab = 'details') => base_url('dashboard/admin/campus-edit.php?id=' . $cid . '&tab=' . $tab);
            $next = null;
            foreach ($c['steps'] as $j => $st) { if (!$st[1]) { $next = $j; break; } }
        ?>
          <article class="ov-cc-card" data-state="<?= $isOpen ? 'open' : 'draft' ?>" style="--k:<?= $k ?>">
            <a class="ov-cc-top" href="<?= e($edit()) ?>">
              <span class="ov-cc-badge" style="background:<?= e($badgeColors[$k % count($badgeColors)]) ?>"><?= campus_icon($c['icon'] ?: 'book', 1.8) ?></span>
              <span class="ov-cc-status <?= $isOpen ? 'open' : '' ?>"><?= $isOpen ? 'Open' : 'Draft' ?></span>
            </a>
            <div class="ov-cc-name">
              <b><?= e($c['name']) ?></b>
              <?php if ($c['professor_name']): ?>
                <span class="ov-cc-prof"><i><?= e($initials($c['professor_name'])) ?></i><?= e($c['professor_name']) ?></span>
              <?php else: ?>
                <span class="ov-cc-prof none"><i>?</i>No professor yet</span>
              <?php endif; ?>
            </div>
            <div class="ov-cc-body">
              <?php if ($isOpen): ?>
                <div class="ov-cc-nums">
                  <div><b data-count="<?= $active ?>"><?= $active ?></b><span>members</span></div>
                  <div><b><small>UGX</small><?= e(number_format((float) $c['collected'])) ?></b><span>last 30 days</span></div>
                </div>
                <div>
                  <div class="ov-cc-mix"><i style="width:<?= $active ? (int) $c['monthly'] / $active * 100 : 0 ?>%"></i><i style="width:<?= $active ? (int) $c['yearly'] / $active * 100 : 0 ?>%"></i></div>
                  <div class="ov-cc-mix-l"><span><?= (int) $c['monthly'] ?> monthly</span><span><?= (int) $c['yearly'] ?> yearly</span></div>
                </div>
                <svg class="ov-cc-curve" data-trend="<?= e(json_encode($c['trend'])) ?>" aria-hidden="true"></svg>
              <?php else: ?>
                <div class="ov-cc-ready"><span>Ready to open</span><b><?= (int) $c['done'] ?> / 5</b></div>
                <div class="ov-cc-segs"><?php foreach ($c['steps'] as $j => $st): ?><i class="<?= $st[1] ? 'd' : '' ?>" style="--d:<?= $j ?>"></i><?php endforeach; ?></div>
                <ul class="ov-cc-steps">
                  <?php foreach ($c['steps'] as $j => $st): $cls = $st[1] ? 'ok' : ($j === $next ? 'nx' : 'td'); ?>
                    <li class="<?= $cls ?>" style="--d:<?= $j ?>">
                      <?php if ($cls === 'ok'): ?><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><?php elseif ($cls === 'nx'): ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M10.5 9l3 3-3 3"/></svg><?php else: ?><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><?php endif; ?>
                      <span><?= e($st[0]) ?></span><em><?= e($st[2]) ?></em>
                    </li>
                  <?php endforeach; ?>
                </ul>
                <?php if ($next !== null): ?>
                  <a class="ov-cc-next" href="<?= e($edit($c['steps'][$next][4])) ?>"><?= e($c['steps'][$next][3]) ?><span>→</span></a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="ov-cc-foot">
              <?php if ($isOpen): ?>
                <span><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= $c['live'] ? '<b>' . e(date('D j M · g:i a', strtotime($c['live']))) . '</b>' : '<span class="warn">No live class booked</span>' ?></span>
                <span title="Memberships ending in the next 7 days"><b><?= (int) $c['ending'] ?></b> ending</span>
              <?php else: ?>
                <span>Not open to learners yet</span>
                <a href="<?= e($edit()) ?>">Edit</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <a class="ov-cc-card ov-cc-add" data-state="draft" href="<?= e(base_url('dashboard/admin/instructor-applications.php')) ?>" style="--k:<?= count($campusCards) ?>">
          <span class="ov-cc-plus"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
          <b>Grow the faculty</b>
          <span>New professors and campuses come from Teach applications</span>
        </a>
      </div>
      <?php else: ?>
        <p class="ov-empty">No campuses yet.</p>
      <?php endif; ?>
    </article>
  </section>

  <section class="ov-grid">
    <article class="ov-card ov-c8 ov-in" style="--i:8">
      <div class="ov-card-h">
        <div><h2>Member growth</h2><p>Members with campus access at the end of each month</p></div>
        <div class="ov-legend"><span><i class="c-brand"></i>All members</span><span><i class="c-kept"></i>Yearly plan</span></div>
      </div>
      <div class="ov-card-b ov-chart-b"><div class="ov-chart" data-chart="members" style="height:250px"></div></div>
    </article>
    <article class="ov-card ov-c4 ov-in" style="--i:9">
      <div class="ov-card-h"><div><h2>Visitor to member</h2><p>Last 30 days</p></div></div>
      <div class="ov-card-b">
        <div class="ov-funnel">
          <?php foreach ($funnel as $k => [$label, $n]): ?>
            <div class="ov-f-row" style="--j:<?= $k ?>">
              <span><?= e($label) ?></span>
              <b><?= e(number_format($n)) ?><?php if ($k > 0): ?><em><?= $funnel[$k - 1][1] > 0 ? e(number_format($n / $funnel[$k - 1][1] * 100, 1)) . '%' : '—' ?></em><?php endif; ?></b>
              <div class="ov-f-bar"><i style="width:<?= max(1.5, round(sqrt($n / $funnelTop) * 100, 1)) ?>%"></i></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="ov-f-foot"><span>Visitor to paid member</span><b><?= $funnel[0][1] > 0 ? e(number_format($funnel[4][1] / $funnel[0][1] * 100, 2)) . '%' : '—' ?></b></div>
      </div>
    </article>
  </section>

  <section class="ov-grid">
    <article class="ov-card ov-c6 ov-in" style="--i:10">
      <div class="ov-card-h"><div><h2>Live classes this week</h2><p><?= $liveClasses ? count($liveClasses) . ' scheduled' : 'None scheduled in the next 7 days' ?></p></div><a class="ov-link" href="<?= e(base_url('dashboard/admin/campuses.php')) ?>">Campuses <span>→</span></a></div>
      <?php if ($liveClasses): ?>
        <div class="ov-list">
          <?php foreach (array_slice($liveClasses, 0, 5) as $k => $l): $hasLink = trim((string) $l['join_url']) !== ''; ?>
            <a class="ov-li" style="--j:<?= $k ?>" href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . (int) $l['campus_id'] . '&tab=live')) ?>">
              <span class="ov-date"><small><?= e(strtoupper(date('D', strtotime($l['starts_at'])))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></span>
              <span class="ov-li-text"><b><?= e($l['title']) ?></b><small><?= e($l['campus_name']) ?> · <?= e(date('g:i a', strtotime($l['starts_at']))) ?></small></span>
              <span class="ov-li-r <?= $hasLink ? 'ok' : 'no' ?>"><?= $hasLink ? 'Link ready' : 'No link yet' ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="ov-empty">Add live classes from each campus's Live classes tab.</p>
      <?php endif; ?>
    </article>
    <article class="ov-card ov-c6 ov-in" style="--i:11">
      <div class="ov-card-h"><div><h2>Recent activity</h2><p>From the audit log</p></div><a class="ov-link" href="<?= e(base_url('dashboard/admin/audit-log.php')) ?>">Audit log <span>→</span></a></div>
      <?php if ($recentActivity): ?>
        <div class="ov-list ov-act">
          <?php foreach ($recentActivity as $k => $log): ?>
            <div class="ov-li" style="--j:<?= $k ?>">
              <span class="ov-av"><?= e($initials($log['actor_name'] ?: '?')) ?></span>
              <span class="ov-li-text"><b><strong><?= e($log['actor_name']) ?></strong> <?= e(str_replace(['.', '_'], ' ', strtolower($log['action']))) ?> <span><?= e($log['target_label']) ?></span></b><small><?= $log['detail'] ? e($log['detail']) : e(format_date($log['created_at'])) ?></small></span>
              <span class="ov-li-r"><?= e(time_ago($log['created_at'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="ov-empty">No activity logged yet.</p>
      <?php endif; ?>
    </article>
  </section>
</div>
<script type="application/json" id="ov-data"><?= json_encode($ovData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(versioned_asset('assets/js/admin-overview.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
