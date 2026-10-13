<?php
/**
 * Admin: campus performance. Money collected, members, renewals due, this week's live classes,
 * each campus at a glance, and what is owed to each professor this month.
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

$period = date('Y-m');
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
$delta = $o['collected_prev'] > 0 ? round(($o['collected'] - $o['collected_prev']) / $o['collected_prev'] * 100, 1) : null;
$fmtM = fn(float $v) => $v >= 1000000 ? 'UGX ' . rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.') . 'M' : format_money($v);

// Chart: collected per month, stacked yearly (bottom) + monthly (top), one scale.
$series = $o['series'];
$maxV = max(1, max(array_map(fn($s) => $s['MONTHLY'] + $s['YEARLY'], $series)));
$step = 10 ** max(0, (int) floor(log10($maxV)));
$top = ceil($maxV / $step) * $step;
if ($top / 4 < $step / 2) $top = $step * 4;
$W = 640; $H = 230; $L = 46; $B = 24; $T = 12;
$yOf = fn(float $v) => $T + ($H - $T - $B) * (1 - $v / $top);
$colW = ($W - $L - 8) / 12; $bw = min(28, $colW * .55);

$pageTitle = 'Campuses — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Campuses</h1>
    <p class="muted" style="margin-top:6px;">Money collected, members and renewals across every campus. Professors are paid by you.</p>
  </div>
  <div class="row gap-2 wrap">
    <a class="ld-btn" href="<?= e(base_url('index.php#campuses')) ?>" target="_blank" rel="noopener">View public page</a>
  </div>
</div>

<div class="ld-panel ld-strip" style="margin-top:20px;">
  <div><b><?= e($fmtM($o['collected'])) ?></b><span>Collected, last 30 days</span><small><?= $delta === null ? 'No earlier month to compare' : ($delta >= 0 ? '+' : '') . $delta . '% on the 30 days before' ?></small></div>
  <div><b><?= number_format($o['active']) ?></b><span>Active members</span><small><?= number_format($o['new30']) ?> joined in the last 30 days</small></div>
  <div class="<?= $o['ending'] ? 'warn' : '' ?>"><b><?= number_format($o['ending']) ?></b><span>Ending in the next 7 days</span><small>Reminded by email automatically</small></div>
  <div><b><?= number_format($o['certificates']) ?></b><span>Certificates issued</span><small>Full twelve-month path completed</small></div>
</div>

<div class="ld-two" style="margin-top:20px;">
  <div class="ld-panel ld-card">
    <div class="ld-chead"><div><h2>Collected per month</h2><p class="sub">Campus payments, last 12 months</p></div></div>
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="ca-chart" role="img" aria-label="Campus money collected per month">
      <?php for ($g = 0; $g <= 4; $g++): $v = $top / 4 * $g; $gy = $yOf($v); ?>
        <line x1="<?= $L ?>" x2="<?= $W ?>" y1="<?= round($gy, 1) ?>" y2="<?= round($gy, 1) ?>" class="ca-grid"></line>
        <text x="<?= $L - 8 ?>" y="<?= round($gy + 4, 1) ?>" text-anchor="end" class="ca-axis"><?= e($v >= 1000000 ? rtrim(rtrim(number_format($v / 1000000, 1), '0'), '.') . 'M' : ($v >= 1000 ? round($v / 1000) . 'k' : (string) (int) $v)) ?></text>
      <?php endfor; ?>
      <?php foreach ($series as $i => $s):
          $x = $L + $i * $colW + ($colW - $bw) / 2; $yY = $yOf($s['YEARLY']); $yM = $yOf($s['YEARLY'] + $s['MONTHLY']); ?>
        <?php if ($s['YEARLY'] > 0): ?><rect x="<?= round($x, 1) ?>" y="<?= round($yY, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round($yOf(0) - $yY, 1) ?>" class="ca-bar-y" rx="2"><title><?= e($s['label']) ?> yearly: <?= e(format_money($s['YEARLY'])) ?></title></rect><?php endif; ?>
        <?php if ($s['MONTHLY'] > 0): ?><rect x="<?= round($x, 1) ?>" y="<?= round($yM, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round(max(0, $yY - $yM - ($s['YEARLY'] > 0 ? 2 : 0)), 1) ?>" class="ca-bar-m" rx="2"><title><?= e($s['label']) ?> monthly: <?= e(format_money($s['MONTHLY'])) ?></title></rect><?php endif; ?>
        <text x="<?= round($x + $bw / 2, 1) ?>" y="<?= $H - 6 ?>" text-anchor="middle" class="ca-axis"><?= e($s['label']) ?></text>
      <?php endforeach; ?>
    </svg>
    <div class="ca-legend"><span><i class="y"></i>Yearly plans</span><span><i class="m"></i>Monthly plans</span></div>
  </div>
  <div class="ld-panel ld-card">
    <div class="ld-chead"><div><h2>Live classes this week</h2><p class="sub">Set them on each campus</p></div></div>
    <div class="ca-list">
      <?php if (!$live): ?><p class="muted small" style="margin-top:14px;">No live classes scheduled in the next 7 days.</p><?php endif; ?>
      <?php foreach ($live as $l): $soon = strtotime($l['starts_at']) - time() < 86400; ?>
        <div><div class="ca-when"><small><?= e(date('D', strtotime($l['starts_at']))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></div>
          <div style="min-width:0;"><b class="t"><?= e($l['campus_name']) ?></b><small><?= e($l['title']) ?> · <?= e(date('g:i a', strtotime($l['starts_at']))) ?></small></div>
          <span class="ld-trend <?= $soon ? 'down' : 'up' ?>" style="font-size:11.5px;"><?= $soon ? 'Within 24 h' : 'Scheduled' ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="ld-sec" style="margin-top:28px;"><h2>Campuses</h2><p><?= count($o['campuses']) ?> campuses · open one to edit its professor, prices, curriculum, lessons and live classes</p></div>
<div class="ld-panel" style="overflow:hidden;">
  <div class="ca-tbl"><table class="ca-table">
    <thead><tr><th>Campus</th><th>Professor</th><th class="num">Members</th><th>Monthly / yearly</th><th class="num">Collected (30 d)</th><th class="num">Ending soon</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($o['campuses'] as $c): $mo = $c['active'] ? round($c['monthly'] / $c['active'] * 100) : 0; ?>
        <tr>
          <td><a class="ca-name" href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $c['id'])) ?>"><i><?= campus_icon($c['icon']) ?></i><?= e($c['name']) ?></a></td>
          <td><?= $c['professor_name'] ? e($c['professor_name']) : '<span class="muted">Not set</span>' ?><small><?= e(format_share(campus_professor_share($c))) ?> of each payment</small></td>
          <td class="num"><?= number_format($c['active']) ?></td>
          <td><div class="ca-split"><span class="bar"><?php if ($c['active']): ?><i class="m" style="width:<?= $mo ?>%"></i><i class="y" style="width:<?= 100 - $mo ?>%"></i><?php endif; ?></span><small><?= (int) $c['monthly'] ?> / <?= (int) $c['yearly'] ?></small></div></td>
          <td class="num"><?= e(format_money($c['collected'])) ?></td>
          <td class="num"><?= $c['ending'] ? '<span class="ca-pill warn">' . (int) $c['ending'] . '</span>' : '0' ?></td>
          <td><?= $c['status'] === 'PUBLISHED' ? '<span class="ca-pill good"><span class="dot"></span>Open</span>' : '<span class="ca-pill mute"><span class="dot"></span>Draft</span>' ?></td>
          <td class="num"><a class="ld-view" href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $c['id'])) ?>">Manage</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="ld-two" style="margin-top:20px;">
  <div class="ld-panel" style="overflow:hidden;">
    <div class="ld-card" style="padding-bottom:14px;"><div class="ld-chead"><div><h2>Renewals due</h2><p class="sub">Access ending in the next 7 days, or ended in the last 3</p></div></div></div>
    <div class="ca-tbl"><table class="ca-table">
      <thead><tr><th>Member</th><th>Campus</th><th>Ends</th><th>Reminded</th><th></th></tr></thead>
      <tbody>
        <?php if (!$renewals): ?><tr><td colspan="5" class="muted">Nobody's access ends in the next 7 days.</td></tr><?php endif; ?>
        <?php foreach ($renewals as $r): $dl = (int) floor((strtotime($r['access_until']) - time()) / 86400); $wa = whatsapp_number($r['user_phone']); ?>
          <tr>
            <td><b><?= e($r['user_name']) ?></b><small><?= e($r['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly') ?></small></td>
            <td><?= e($r['campus_name']) ?></td>
            <td><span class="ca-pill <?= $dl <= 1 ? 'bad' : 'warn' ?>"><?= strtotime($r['access_until']) <= time() ? 'Ended ' . e(date('j M', strtotime($r['access_until']))) : ($dl <= 0 ? 'Today' : ($dl === 1 ? 'Tomorrow' : 'In ' . $dl . ' days')) ?></span></td>
            <td><small style="font-size:13px; margin:0;"><?= $r['last_reminder_at'] ? e(time_ago($r['last_reminder_at'])) : 'Not yet' ?><?= (int) $r['reminders_off'] ? ' · turned off' : '' ?></small></td>
            <td class="num" style="white-space:nowrap;">
              <?php if ($wa): ?><a class="ot-btn wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . campus_first_name($r['user_name']) . ', this is Obin Academy. Your ' . $r['campus_name'] . ' Campus access ' . (strtotime($r['access_until']) <= time() ? 'has ended' : 'ends on ' . date('j M', strtotime($r['access_until']))) . '. Renew here to keep learning: ' . base_url('campus.php?slug=' . $r['campus_slug']))) ?>">WhatsApp</a><?php endif; ?>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_action" value="remind"><input type="hidden" name="membership" value="<?= (int) $r['id'] ?>"><button class="ot-btn" type="submit">Email</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="ld-panel" style="overflow:hidden;">
    <div class="ld-card" style="padding-bottom:14px;"><div class="ld-chead"><div><h2>Professor pay</h2><p class="sub">Their share of every payment, owed until you send it</p></div><a class="ld-view" href="<?= e(base_url('dashboard/admin/professor-pay.php')) ?>">Open</a></div></div>
    <div class="ca-list" style="padding:0 22px 12px;">
      <?php foreach ($pay as $c): ?>
        <div>
          <span class="ca-ico"><?= campus_icon($c['icon']) ?></span>
          <div style="min-width:0;"><b class="t"><?= e($c['name']) ?></b><small><?= $c['professor_name'] ? e($c['professor_name']) . ' · ' : '' ?><?= e(format_share(campus_professor_share($c))) ?> · <?= e(format_money($c['paid'])) ?> paid to date</small></div>
          <?php if ($c['owed'] > 0): ?>
            <a class="ot-btn" href="<?= e(base_url('dashboard/admin/professor-pay.php?campus=' . $c['id'] . '#pay')) ?>">Pay <?= e(format_money($c['owed'])) ?></a>
          <?php else: ?><span class="ca-pill <?= $c['earned'] > 0 ? 'good' : 'mute' ?>"><?= $c['earned'] > 0 ? 'Paid up' : 'Nothing yet' ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
