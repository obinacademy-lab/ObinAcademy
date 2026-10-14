<?php
/**
 * Admin: campuses. Key figures, a board showing the launch stage each campus has reached (needs a
 * professor, building, ready to open, open) with the next step on every card, and one workspace for
 * money, renewals due, this week's live classes and professor pay.
 *
 * Styles: assets/css/admin-campuses.css (scoped under .acx). Charts and switches: assets/js/admin-campuses.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

if (!campuses_ready()) {
    $pageTitle = 'Campuses — Admin — Obin Academy';
    require __DIR__ . '/../../includes/dashboard_header.php';
    echo '<div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;"><p class="muted">Campuses are being set up. Check back in a few minutes.</p></div>';
    require __DIR__ . '/../../includes/dashboard_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('_action');
    if ($action === 'remind') {
        $m = db_one(
            'SELECT m.*, c.name AS campus_name, c.slug AS campus_slug, c.monthly_price, u.name AS user_name, u.email AS user_email
             FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id JOIN users u ON u.id = m.user_id WHERE m.id = ?',
            [(int) post('membership')]
        );
        if ($m && $m['user_email']) {
            $url = base_url('campus.php?slug=' . $m['campus_slug'] . '#join');
            $ended = strtotime($m['access_until']) <= time();
            $line = $ended ? 'Your access ended on ' . date('j M', strtotime($m['access_until'])) . '. Pay again to reopen your lessons, live classes and community. Your progress is kept.'
                           : 'Your access ends on ' . date('j M', strtotime($m['access_until'])) . '. Renew before then to keep your lessons, live classes and community.';
            $okSend = resend_send($m['user_email'], 'Your ' . $m['campus_name'] . ' Campus membership', campus_email_html('Hi ' . campus_first_name($m['user_name']), e($line), 'Renew for ' . format_money((float) $m['monthly_price']), $url), ['bcc' => $user['email'], 'reply_to' => $user['email']]);
            create_user_notification((int) $m['user_id'], null, 'campus_renewal', 'Your ' . $m['campus_name'] . ' Campus access ' . ($ended ? 'has ended' : 'ends on ' . date('j M', strtotime($m['access_until']))) . '.', $url);
            log_admin_action((int) $user['id'], $user['name'], 'campus.reminded', 'Campus member', $m['user_name'], $m['campus_name']);
            flash_set($okSend ? 'success' : 'error', $okSend ? 'Reminder sent to ' . $m['user_email'] . '. A copy is in your inbox.' : 'The email service refused the reminder. The in-app notice was still sent.');
        }
    }
    redirect('/dashboard/admin/campuses.php');
}

$o = campus_admin_overview();
$renewals = campus_renewals_due(7, 30);
$live = upcoming_campus_live_classes(7);
$pay = professor_pay_summary();
$launch = campus_launch_steps($o['campuses']);
$delta = $o['collected_prev'] > 0 ? round(($o['collected'] - $o['collected_prev']) / $o['collected_prev'] * 100, 1) : null;
$owedTotal = array_sum(array_column($pay, 'owed'));
$owedCount = count(array_filter($pay, fn($c) => $c['owed'] > 0));
$noLink = count(array_filter($live, fn($l) => trim((string) $l['join_url']) === ''));

// Money collected per campus per day, last 30 days, for the small curves on open campuses.
$daily = [];
foreach (db_all(
    "SELECT campus_id, DATEDIFF(CURDATE(), DATE(created_at)) AS ago, SUM(amount) AS s FROM payments
     WHERE type = 'CAMPUS_MEMBERSHIP' AND status = 'SUCCESS' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY campus_id, ago"
) as $r) {
    $daily[(int) $r['campus_id']][29 - (int) $r['ago']] = (float) $r['s'];
}

// Last 12 months: totals by plan, and what the professors earned from them.
$total12 = array_sum(array_map(fn($s) => $s['MONTHLY'] + $s['YEARLY'], $o['series']));
$yearly12 = array_sum(array_column($o['series'], 'YEARLY'));
$monthly12 = array_sum(array_column($o['series'], 'MONTHLY'));
$share12 = 0.0;
if (professor_pay_ready()) {
    $share12 = (float) (db_one(
        "SELECT COALESCE(SUM(e.amount),0) AS s FROM campus_professor_earnings e JOIN payments p ON p.id = e.payment_id
         WHERE p.created_at >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 11 MONTH)"
    )['s'] ?? 0);
}

// The board: each campus in the column for the stage it has reached.
$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
$stageInfo = [
    ['Assign who teaches it', '#c42828', '#fdeaea'],
    ['Curriculum and month 1 lessons', '#b45f06', '#fdf1e2'],
    ['Everything learners need is in', '#2b22ff', '#eeedff'],
    ['Learners can join and pay', '#12855a', '#e5f6ee'],
];
$cards = [];
foreach ($o['campuses'] as $k => $c) {
    $cid = (int) $c['id'];
    $l = $launch[$cid];
    $stage = campus_launch_stage($c, $l);
    // The next step: the first of professor, curriculum and month 1 lessons still to do, then publishing.
    $next = 4;
    if ($stage < 2) foreach ([0, 1, 2] as $j) { if (!$l['steps'][$j][1]) { $next = $j; break; } }
    // The one line a draft card shows: whatever its stage is waiting on.
    $show = $stage === 2 ? 2 : ($l['steps'][1][1] ? 2 : 1);
    $trend = array_replace(array_fill(0, 30, 0.0), $daily[$cid] ?? []);
    ksort($trend);
    $cards[] = $c + $l + ['stage' => $stage, 'next' => $next, 'show' => $show, 'color' => $colors[$k % count($colors)], 'trend' => array_values($trend)];
}
$byStage = [[], [], [], []];
foreach ($cards as $c) $byStage[$c['stage']][] = $c;
$short = fn(float $v) => $v >= 1000000 ? rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.') . 'M' : ($v >= 1000 ? round($v / 1000) . 'K' : number_format($v));
$edit = fn(int $id, string $tab = 'details') => base_url('dashboard/admin/campus-edit.php?id=' . $id . '&tab=' . $tab);

$acxData = ['series' => array_map(fn($s, $i) => ['label' => $s['label'], 'm' => $s['MONTHLY'], 'y' => $s['YEARLY']], $o['series'], array_keys($o['series']))];
for ($i = 0; $i < 12; $i++) $acxData['series'][$i]['full'] = date('M Y', strtotime('first day of -' . (11 - $i) . ' month'));

$pageTitle = 'Campuses — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx" data-acx>
  <div class="acx-head acx-rise" style="--i:0">
    <div><h1>Campuses</h1><p>Build each campus, open it to learners, and keep members renewing.</p></div>
    <div class="acx-acts">
      <a class="acx-btn" href="<?= e(base_url('index.php#campuses')) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View public page</a>
      <a class="acx-btn primary" href="<?= e(base_url('dashboard/admin/professor-pay.php')) ?>"><svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>Professor pay</a>
    </div>
  </div>

  <div class="acx-strip acx-rise" style="--i:1">
    <div><span>Collected · 30 days</span><b><small>UGX</small><em data-to="<?= (int) round($o['collected']) ?>"><?= e(number_format($o['collected'])) ?></em></b><i><?= $delta === null ? 'No earlier month to compare' : ($delta >= 0 ? '▲ ' : '▼ ') . abs($delta) . '% on the 30 days before' ?></i></div>
    <div><span>Active members</span><b><em data-to="<?= (int) $o['active'] ?>"><?= number_format($o['active']) ?></em></b><i><?= number_format($o['new30']) ?> joined in the last 30 days</i></div>
    <div class="<?= $o['ending'] ? 'warn' : '' ?>"><span>Ending in 7 days</span><b><em data-to="<?= (int) $o['ending'] ?>"><?= number_format($o['ending']) ?></em></b><i>Reminded by email automatically</i></div>
    <div class="<?= $owedTotal > 0 ? 'warn' : '' ?>"><span>Owed to professors</span><b><small>UGX</small><em data-to="<?= (int) round($owedTotal) ?>"><?= e(number_format($owedTotal)) ?></em></b><i><?= $owedCount ? $owedCount . ' campus' . ($owedCount === 1 ? '' : 'es') . ' waiting to be paid' : 'Nothing owed right now' ?></i></div>
    <div><span>Certificates issued</span><b><em data-to="<?= (int) $o['certificates'] ?>"><?= number_format($o['certificates']) ?></em></b><i>Full 12-month path completed</i></div>
  </div>

  <section class="acx-sec acx-rise" style="--i:2">
    <div class="acx-sec-h">
      <div><h2>Campus board</h2><p>Every campus sits in the stage it has reached. Do the next step on a card to move it right.</p></div>
      <div class="acx-seg" data-seg="view"><span class="acx-pill" aria-hidden="true"></span>
        <button type="button" class="on" data-view="board" aria-pressed="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="5" height="16" rx="1.5"/><rect x="10" y="4" width="5" height="11" rx="1.5"/><rect x="17" y="4" width="4" height="7" rx="1.5"/></svg>Board</button>
        <button type="button" data-view="list" aria-pressed="false"><svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>List</button>
      </div>
    </div>

    <div class="acx-view" data-viewpane="board">
      <div class="acx-board play">
        <?php foreach (CAMPUS_LAUNCH_STAGES as $si => $stageName): [$hint, $col, $bg] = $stageInfo[$si]; ?>
          <div class="acx-col" style="--c:<?= $col ?>;--ci:<?= $si ?>">
            <div class="acx-col-h">
              <div class="t"><i style="background:<?= $bg ?>;color:<?= $col ?>"><?= $si + 1 ?></i><b><?= e($stageName) ?></b><span class="n"><?= count($byStage[$si]) ?></span></div>
              <p><?= e($hint) ?></p>
            </div>
            <div class="acx-col-b">
              <?php if (!$byStage[$si]): ?><div class="acx-empty">Nothing here right now</div><?php endif; ?>
              <?php foreach ($byStage[$si] as $j => $c): $cid = (int) $c['id']; ?>
                <article class="acx-card" style="--j:<?= $j ?>">
                  <a class="acx-c-top" href="<?= e($edit($cid)) ?>">
                    <span class="acx-bdg" style="background:<?= e($c['color']) ?>"><?= campus_icon($c['icon'] ?: 'book', 1.8) ?></span>
                    <span><b><?= e($c['name']) ?></b><small><?= $c['professor_name'] ? e($c['professor_name']) : 'No professor yet' ?></small></span>
                  </a>
                  <?php if ($c['stage'] === 3): ?>
                    <div class="acx-mx">
                      <div><b><?= (int) $c['active'] ?></b><span>members</span></div>
                      <div><b><small>UGX</small><?= e($short((float) $c['collected'])) ?></b><span>30 days</span></div>
                    </div>
                    <svg class="acx-sp" data-trend="<?= e(json_encode($c['trend'])) ?>" aria-hidden="true"></svg>
                    <div class="acx-c-foot">
                      <span class="<?= $c['live'] ? '' : 'w' ?>"><?= $c['live'] ? 'Live ' . e(date('D j · g a', strtotime($c['live']))) : 'No live class booked' ?></span>
                      <span><?= (int) $c['ending'] ?> ending</span>
                    </div>
                  <?php else: ?>
                    <div class="acx-dots"><?php foreach ($c['steps'] as $d => $st): ?><i class="<?= $st[1] ? 'd' : '' ?>" style="--d:<?= $d ?>"></i><?php endforeach; ?></div>
                    <div class="acx-line"><span><?= e($c['steps'][$c['show']][0]) ?></span><b><?= e($c['steps'][$c['show']][2]) ?></b></div>
                    <a class="acx-go <?= $c['next'] === 4 ? 'pub' : '' ?>" href="<?= e($edit($cid, $c['steps'][$c['next']][4])) ?>"><?= e($c['steps'][$c['next']][3]) ?><span>→</span></a>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="acx-view" data-viewpane="list" hidden>
      <div class="acx-tbl"><table>
        <thead><tr><th>Campus</th><th>Stage</th><th>Professor</th><th class="r">Members</th><th>Monthly / yearly</th><th class="r">Collected · 30d</th><th class="r">Ending · 7d</th><th>Next step</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($cards as $j => $c): $cid = (int) $c['id']; ?>
            <tr style="--j:<?= $j ?>">
              <td><a class="acx-c-top" href="<?= e($edit($cid)) ?>"><span class="acx-bdg" style="background:<?= e($c['color']) ?>"><?= campus_icon($c['icon'] ?: 'book', 1.8) ?></span><span><b><?= e($c['name']) ?></b><small><?= e(format_share(campus_professor_share($c))) ?> to the professor</small></span></a></td>
              <td><span class="acx-stage" style="--c:<?= $stageInfo[$c['stage']][1] ?>"><?= e(CAMPUS_LAUNCH_STAGES[$c['stage']]) ?></span></td>
              <td><?= $c['professor_name'] ? e($c['professor_name']) : '<span class="acx-muted">Not assigned</span>' ?></td>
              <td class="r acx-num"><?= (int) $c['active'] ?: '—' ?></td>
              <td class="acx-num"><?= $c['active'] ? (int) $c['monthly'] . ' / ' . (int) $c['yearly'] : '<span class="acx-muted">—</span>' ?></td>
              <td class="r acx-num"><?= $c['collected'] > 0 ? e(number_format($c['collected'])) : '—' ?></td>
              <td class="r acx-num"><?= (int) $c['ending'] ?: '—' ?></td>
              <td><?= $c['stage'] === 3 ? '<span class="acx-muted">—</span>' : '<a class="acx-lnk" href="' . e($edit($cid, $c['steps'][$c['next']][4])) . '">' . e($c['steps'][$c['next']][3]) . ' →</a>' ?></td>
              <td class="r"><a class="acx-lnk" href="<?= e($edit($cid)) ?>">Manage</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </section>

  <section class="acx-sec acx-rise" style="--i:3">
    <div class="acx-tabs" data-tabs><span class="acx-bar" aria-hidden="true"></span>
      <button type="button" class="on" data-tab="money">Money</button>
      <button type="button" data-tab="renew">Renewals due <em class="<?= $renewals ? 'w' : '' ?>"><?= count($renewals) ?></em></button>
      <button type="button" data-tab="live">Live classes this week <em class="<?= $noLink ? 'w' : '' ?>"><?= count($live) ?></em></button>
      <button type="button" data-tab="pay">Professor pay <em class="<?= $owedCount ? 'w' : '' ?>"><?= $owedCount ?></em></button>
    </div>

    <div class="acx-panel on" data-panel="money">
      <div class="acx-money">
        <div>
          <div class="acx-legend"><span><i style="background:var(--ov-brand)"></i>All plans</span><span><i style="background:var(--ov-kept)"></i>Yearly plans</span><span><i style="background:#b9bbd0"></i>Monthly plans</span></div>
          <div class="acx-chart" data-chart></div>
        </div>
        <div class="acx-side">
          <div><span>Collected · last 12 months</span><b><small>UGX</small><?= e(number_format($total12)) ?></b></div>
          <div><span>From yearly plans</span><b><small>UGX</small><?= e(number_format($yearly12)) ?></b></div>
          <div><span>From monthly plans</span><b><small>UGX</small><?= e(number_format($monthly12)) ?></b></div>
          <div><span>Kept after professor pay</span><b><small>UGX</small><?= e(number_format(max(0, $total12 - $share12))) ?></b></div>
        </div>
      </div>
    </div>

    <div class="acx-panel" data-panel="renew">
      <?php if (!$renewals): ?>
        <p class="acx-none">Nobody's access ends in the next 7 days.</p>
      <?php else: ?>
      <div class="acx-tbl"><table>
        <thead><tr><th>Member</th><th>Campus</th><th>Ends</th><th>Reminded</th><th class="r">Contact</th></tr></thead>
        <tbody>
          <?php foreach ($renewals as $j => $r): $ended = strtotime($r['access_until']) <= time(); $dl = (int) floor((strtotime($r['access_until']) - time()) / 86400); $wa = whatsapp_number($r['user_phone']); ?>
            <tr style="--j:<?= $j ?>">
              <td class="acx-who"><b><?= e($r['user_name']) ?></b><small><?= $r['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></td>
              <td><?= e($r['campus_name']) ?></td>
              <td><span class="acx-tag <?= $ended || $dl <= 1 ? 'bad' : 'warn' ?>"><?= $ended ? 'Ended ' . e(date('j M', strtotime($r['access_until']))) : ($dl <= 0 ? 'Today' : ($dl === 1 ? 'Tomorrow' : 'In ' . $dl . ' days')) ?></span></td>
              <td class="acx-muted"><?= $r['last_reminder_at'] ? e(time_ago($r['last_reminder_at'])) : 'Not yet' ?><?= (int) $r['reminders_off'] ? ' · turned off' : '' ?></td>
              <td class="r">
                <?php if ($wa): ?><a class="acx-mini wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . campus_first_name($r['user_name']) . ', this is Obin Academy. Your ' . $r['campus_name'] . ' Campus access ' . ($ended ? 'has ended' : 'ends on ' . date('j M', strtotime($r['access_until']))) . '. Renew here to keep learning: ' . base_url('campus.php?slug=' . $r['campus_slug']))) ?>">WhatsApp</a><?php endif; ?>
                <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_action" value="remind"><input type="hidden" name="membership" value="<?= (int) $r['id'] ?>"><button class="acx-mini" type="submit">Email</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="acx-panel" data-panel="live">
      <?php if (!$live): ?>
        <p class="acx-none">No live classes in the next 7 days. Add them on each campus's Live classes tab.</p>
      <?php else: ?>
      <div class="acx-lv">
        <?php foreach ($live as $j => $l): $hasLink = trim((string) $l['join_url']) !== ''; ?>
          <a style="--j:<?= $j ?>" href="<?= e($edit((int) $l['campus_id'], 'live')) ?>">
            <span class="acx-dt"><small><?= e(strtoupper(date('D', strtotime($l['starts_at'])))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></span>
            <p><?= e($l['title']) ?><small><?= e($l['campus_name']) ?> · <?= e(date('g:i a', strtotime($l['starts_at']))) ?></small></p>
            <span class="acx-tag <?= $hasLink ? 'good' : 'warn' ?>"><?= $hasLink ? 'Link ready' : 'No link' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="acx-panel" data-panel="pay">
      <div class="acx-tbl"><table>
        <thead><tr><th>Campus</th><th>Professor</th><th class="r">Share</th><th class="r">Paid to date</th><th class="r">Owed now</th><th class="r"></th></tr></thead>
        <tbody>
          <?php foreach (array_values($pay) as $j => $c): ?>
            <tr style="--j:<?= $j ?>">
              <td><span class="acx-c-top"><span class="acx-bdg" style="background:<?= e($colors[$j % count($colors)]) ?>"><?= campus_icon($c['icon'] ?: 'book', 1.8) ?></span><span><b><?= e($c['name']) ?></b></span></span></td>
              <td><?= $c['professor_name'] ? e($c['professor_name']) : '<span class="acx-muted">Not assigned</span>' ?></td>
              <td class="r acx-num"><?= e(format_share(campus_professor_share($c))) ?></td>
              <td class="r acx-num"><?= e(number_format($c['paid'])) ?></td>
              <td class="r acx-num"><?= e(number_format($c['owed'])) ?></td>
              <td class="r"><?php if ($c['owed'] > 0): ?><a class="acx-mini pay" href="<?= e(base_url('dashboard/admin/professor-pay.php?campus=' . $c['id'] . '#pay')) ?>">Pay UGX <?= e(number_format($c['owed'])) ?></a><?php else: ?><span class="acx-tag mute"><?= $c['earned'] > 0 ? 'Paid up' : 'Nothing yet' ?></span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </section>
</div>
<script type="application/json" id="acx-data"><?= json_encode($acxData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(versioned_asset('assets/js/admin-campuses.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
