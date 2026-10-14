<?php
/**
 * Admin: professor pay, as a "pay desk". Every campus payment earns that campus's professor their share
 * (40% by default) the moment it succeeds. A dark console shows what is owed now and a lane per campus;
 * the ledger lists every campus with a Pay button; opening a campus (?campus=) shows each payment behind
 * the amount and records money sent (mobile money, bank or cash) with its reference. Professors can ask
 * to be paid (dashboard/professor.php): open requests are listed first, with Pay and Decline.
 *
 * Styles: assets/css/admin-campuses.css (console, ledger) + assets/css/admin-pay.css. Motion: assets/js/admin-pay.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

if (!campuses_ready() || !professor_pay_ready()) {
    $pageTitle = 'Professor Pay — Admin — Obin Academy';
    require __DIR__ . '/../../includes/dashboard_header.php';
    echo '<div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;"><p class="muted">Professor pay is being set up. Check back in a few minutes.</p></div>';
    require __DIR__ . '/../../includes/dashboard_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $campusId = (int) post('campus');
    if (post('_action') === 'decline_request') {
        if (mb_strlen(trim(post('reason'))) < 4) {
            flash_set('error', 'Write a short reason. The professor sees it.');
        } elseif ($r = payout_request_decline((int) post('request'), post('reason'), (int) $user['id'])) {
            log_admin_action((int) $user['id'], $user['name'], 'campus.payout_request_declined', 'Campus', $r['campus_name'], format_money((float) $r['amount']) . ': ' . trim(post('reason')));
            flash_set('success', 'Request declined. The professor was told why.');
        }
        redirect('/dashboard/admin/professor-pay.php#requests');
    }
    if (post('_action') === 'payout') {
        $campus = get_campus($campusId);
        $method = post('method');
        if ($method === 'MOBILE_MONEY' && trim(post('reference')) === '') {
            flash_set('error', 'Add the mobile money transaction ID, so you and the professor can match the payment.');
            redirect('/dashboard/admin/professor-pay.php?campus=' . $campusId . '#pay');
        }
        $payout = $campus ? record_campus_professor_payout($campusId, $method, trim(post('sent_to')), trim(post('reference')), trim(post('note')), (int) $user['id']) : null;
        if ($payout) {
            log_admin_action((int) $user['id'], $user['name'], 'campus.professor_paid', 'Campus', $campus['name'], format_money((float) $payout['amount']) . ' to ' . ($campus['professor_name'] ?: 'professor') . ($payout['reference'] ? ', ref ' . $payout['reference'] : ''));
            flash_set('success', 'Recorded ' . format_money((float) $payout['amount']) . ' paid to ' . ($campus['professor_name'] ?: 'the professor') . '.' . ($campus['professor_user_id'] ? ' They were sent a statement.' : ''));
        } else {
            flash_set('error', 'Nothing is owed on this campus right now.');
        }
    }
    redirect('/dashboard/admin/professor-pay.php?campus=' . $campusId);
}

$summary = professor_pay_summary();
$owedTotal = array_sum(array_column($summary, 'owed'));
$share30 = array_sum(array_column($summary, 'earned30'));
$gross30 = array_sum(array_column($summary, 'gross30'));
$paidAll = array_sum(array_column($summary, 'paid'));
$paidMonth = (float) (db_one("SELECT COALESCE(SUM(amount), 0) AS s FROM campus_professor_payouts WHERE paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['s'] ?? 0);
$owing = array_filter($summary, fn($c) => $c['owed'] > 0);
$owedMax = $owing ? max(array_column($owing, 'owed')) : 0;

$sel = isset($summary[(int) query_param('campus')]) ? $summary[(int) query_param('campus')] : null;
$ledger = $sel ? get_campus_professor_earnings((int) $sel['id'], 200) : [];
$payouts = $sel ? get_campus_professor_payouts((int) $sel['id']) : [];
$wa = $sel ? whatsapp_number($sel['professor_payout_phone'] ?? '') : null;
$requests = payout_requests_open();
$selRequest = $sel ? payout_request_pending((int) $sel['id']) : null;

$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
$look = [];
foreach (array_values($summary) as $k => $c) $look[(int) $c['id']] = ['photo' => campus_photo($c), 'color' => $colors[$k % count($colors)]];
$campusUrl = fn(int $id, string $hash = '') => base_url('dashboard/admin/professor-pay.php?campus=' . $id) . $hash;
$thumb = function (array $c, string $class) use ($look): string {
    $l = $look[(int) $c['id']];
    return $l['photo']
        ? '<span class="' . $class . '" style="background-image:url(\'' . e($l['photo']) . '\')"></span>'
        : '<span class="' . $class . ' badge" style="background:' . e($l['color']) . '">' . campus_icon($c['icon'] ?: 'book', 1.8) . '</span>';
};

$pageTitle = ($sel ? e($sel['name']) . ' — ' : '') . 'Professor Pay — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-pay.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx pp" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Professor pay</h1><p>Each professor earns their share of every campus payment the moment it lands. Send the money, then record it here.</p></div>
    <div class="acx-acts"><a class="acx-btn" href="<?= e(base_url('dashboard/admin/campuses.php')) ?>"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg>Campuses</a></div>
  </div>

  <section class="lc-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $owedTotal > 0 ? '' : 'calm' ?>"><i></i><?= $owedTotal > 0 ? 'Waiting to be paid' : 'All paid up' ?></span>
      <div class="pp-big"><small>UGX</small><b data-scramble="<?= (int) round($owedTotal) ?>"><?= e(number_format($owedTotal)) ?></b></div>
      <p class="lc-copy"><?= $owing ? 'Owed now to ' . count($owing) . ' campus professor' . (count($owing) === 1 ? '' : 's') . '. Open a campus to send it and record the transaction.' : 'Nothing is owed right now. New shares appear here the moment a member pays.' ?></p>
      <div class="lc-stats">
        <div><span>Paid out in <?= e(date('F')) ?></span><b><small>UGX</small><span data-scramble="<?= (int) round($paidMonth) ?>"><?= e(number_format($paidMonth)) ?></span></b></div>
        <div><span>Paid to date</span><b><small>UGX</small><span data-scramble="<?= (int) round($paidAll) ?>"><?= e(number_format($paidAll)) ?></span></b></div>
        <div><span>Professors' share · 30 days</span><b><small>UGX</small><span data-scramble="<?= (int) round($share30) ?>"><?= e(number_format($share30)) ?></span></b></div>
        <div><span>Kept by Obin Academy · 30 days</span><b><small>UGX</small><span data-scramble="<?= (int) round($gross30 - $share30) ?>"><?= e(number_format($gross30 - $share30)) ?></span></b></div>
      </div>
    </div>
    <div class="lc-rx">
      <div class="lc-rx-h"><b>Owed by campus</b><span>click a lane to pay</span></div>
      <?php foreach (array_values($summary) as $li => $c): $w = $owedMax > 0 ? max(3, round($c['owed'] / $owedMax * 100)) : 0; ?>
        <a class="pp-lane <?= $c['owed'] > 0 ? 'owed' : '' ?> <?= $sel && (int) $sel['id'] === (int) $c['id'] ? 'on' : '' ?>" href="<?= e($campusUrl((int) $c['id'], $c['owed'] > 0 ? '#pay' : '#ledger')) ?>" style="--l:<?= $li ?>">
          <span class="nm"><?= $thumb($c, 'pp-dot') ?><?= e($c['name']) ?></span>
          <span class="pp-track"><?php if ($c['owed'] > 0): ?><i style="width:<?= $w ?>%"></i><?php endif; ?></span>
          <span class="amt"><?= $c['owed'] > 0 ? e(number_format($c['owed'])) : 'Paid up' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($requests): ?>
    <section class="acx-sec acx-slam pp-reqs" style="--i:2" id="requests">
      <div class="lc-sec-h"><div><h2>Payment requests <em class="pp-req-n"><?= count($requests) ?></em></h2><p>Professors asking to be paid. Pay opens the campus with their number filled in; recording the payout marks the request paid.</p></div></div>
      <?php foreach ($requests as $j => $r): ?>
        <div class="pp-req acx-whip" style="--j:<?= $j ?>">
          <div class="pp-req-who"><b><?= e($r['professor_name'] ?: ($r['professor_account'] ?: 'Professor')) ?></b><small><?= e($r['campus_name']) ?> Campus · asked <?= e(time_ago($r['requested_at'])) ?></small></div>
          <div class="pp-req-amt"><b><?= e(format_money((float) $r['amount'])) ?></b><small><?= abs($r['owed_now'] - (float) $r['amount']) > 0.5 ? e(format_money($r['owed_now'])) . ' owed now' : 'owed now' ?></small></div>
          <div class="pp-req-to"><b><?= e($r['phone']) ?></b><small><?= e($r['payout_name'] ?: '') ?><?= $r['note'] ? ($r['payout_name'] ? ' · ' : '') . '“' . e($r['note']) . '”' : '' ?></small></div>
          <div class="pp-req-acts">
            <a class="lc-next pub" href="<?= e($campusUrl((int) $r['campus_id'], '#pay')) ?>">Pay<span>→</span></a>
            <details class="pp-decline"><summary>Decline</summary>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="decline_request"><input type="hidden" name="request" value="<?= (int) $r['id'] ?>"><input name="reason" required minlength="4" maxlength="255" placeholder="Why, e.g. the number is not registered"><button type="submit">Decline</button></form>
            </details>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
  <section class="acx-sec acx-slam" style="--i:2">
    <div class="lc-sec-h"><div><h2>By campus</h2><p>Open a campus to pay its professor and see every payment behind the amount</p></div></div>
    <div class="pp-grid pp-th"><span>Campus</span><span class="r">Share</span><span class="r">Members</span><span class="r">Earned · 30d</span><span class="r">Paid to date</span><span class="r">Owed now</span><span></span></div>
    <?php foreach (array_values($summary) as $j => $c): $isSel = $sel && (int) $sel['id'] === (int) $c['id']; ?>
      <div class="pp-grid lc-row acx-whip <?= $isSel ? 'sel' : '' ?>" style="--j:<?= $j ?>">
        <a class="lc-camp" href="<?= e($campusUrl((int) $c['id'], '#ledger')) ?>">
          <?= $thumb($c, 'ph') ?>
          <span><b><?= e($c['name']) ?></b><small class="<?= $c['professor_name'] ? '' : 'none' ?>"><?= $c['professor_name'] ? e($c['professor_name']) . ($c['professor_payout_phone'] ? ' · ' . e($c['professor_payout_phone']) : ' · no payout number') : 'No professor yet' ?></small></span>
        </a>
        <span class="lc-num r"><?= e(format_share(campus_professor_share($c))) ?></span>
        <span class="lc-num r"><?= number_format($c['members']) ?></span>
        <span class="lc-num r"><?= $c['earned30'] > 0 ? e(number_format($c['earned30'])) . '<small>UGX</small>' : '—' ?></span>
        <span class="lc-num r"><?= $c['paid'] > 0 ? e(number_format($c['paid'])) : '0' ?><small><?= $c['last_paid_at'] ? 'Last ' . e(date('j M', strtotime($c['last_paid_at']))) : 'Never paid' ?></small></span>
        <span class="r"><?= $c['owed'] > 0 ? '<span class="pp-owed-n">' . e(number_format($c['owed'])) . '<small>' . (int) $c['owed_count'] . ' payment' . ((int) $c['owed_count'] === 1 ? '' : 's') . '</small></span>' : '<span class="lc-stage s3">Paid up</span>' ?></span>
        <a class="lc-next <?= $c['owed'] > 0 ? 'pub' : 'quiet' ?>" href="<?= e($campusUrl((int) $c['id'], $c['owed'] > 0 ? '#pay' : '#ledger')) ?>"><?= $c['owed'] > 0 ? 'Pay' : 'Open' ?><span>→</span></a>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($sel): ?>
    <div class="pp-sel-h acx-slam" style="--i:3" id="ledger">
      <?= $thumb($sel, 'ph') ?>
      <div><h2><?= e($sel['name']) ?> Campus</h2><p><?= $sel['professor_name'] ? e($sel['professor_name']) . ' · ' : '' ?><?= e(format_share(campus_professor_share($sel))) ?> of every payment · <a href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $sel['id'] . '&tab=professor')) ?>">Change share or payout number</a></p></div>
    </div>
    <div class="pp-split">
      <section class="acx-sec acx-slam" style="--i:4">
        <div class="lc-sec-h"><div><h2>Every payment</h2><p>What the member paid and the professor's share of it</p></div></div>
        <div class="acx-tbl"><table>
          <thead><tr><th>Date</th><th>Member</th><th class="r">Paid</th><th class="r">Professor</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$ledger): ?><tr><td colspan="5" class="acx-muted">No payments in this campus yet. Each one appears here the moment it lands.</td></tr><?php endif; ?>
            <?php foreach ($ledger as $j => $l): ?>
              <tr class="acx-whip" style="--j:<?= min($j, 12) ?>">
                <td><?= e(date('j M Y', strtotime($l['created_at']))) ?><small class="pp-sub"><?= e(date('g:i a', strtotime($l['created_at']))) ?></small></td>
                <td><?= e($l['learner_name'] ?: 'Deleted account') ?><small class="pp-sub"><?= $l['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></td>
                <td class="r acx-num"><?= e(number_format((float) $l['gross'])) ?></td>
                <td class="r acx-num"><b><?= e(number_format((float) $l['amount'])) ?></b><small class="pp-sub"><?= e(format_share((float) $l['share_percent'])) ?></small></td>
                <td><?= $l['payout_id'] ? '<span class="acx-tag good">Paid ' . e(date('j M', strtotime($l['payout_paid_at']))) . '</span>' : '<span class="acx-tag warn">Owed</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </section>

      <div class="pp-side">
        <section class="pp-pay acx-slam" style="--i:5" id="pay">
          <span class="lc-eyebrow <?= $sel['owed'] > 0 ? '' : 'calm' ?>"><i></i>Pay the professor</span>
          <div class="pp-big sm"><small>UGX</small><b data-scramble="<?= (int) round($sel['owed']) ?>"><?= e(number_format($sel['owed'])) ?></b></div>
          <p class="pp-note"><?= $sel['owed'] > 0 ? (int) $sel['owed_count'] . ' payment' . ((int) $sel['owed_count'] === 1 ? '' : 's') . ' not yet paid out' : 'Nothing owed right now' ?></p>
          <?php if ($sel['owed'] > 0): ?>
            <ol class="pp-steps">
              <li>Send <b><?= e(format_money($sel['owed'])) ?></b> to <?= $selRequest ? '<b>' . e($selRequest['phone']) . '</b>' . ($selRequest['payout_name'] ? ' (' . e($selRequest['payout_name']) . ')' : '') . ', the number in their request' : ($sel['professor_payout_phone'] ? '<b>' . e($sel['professor_payout_phone']) . '</b>' . ($sel['professor_payout_name'] ? ' (' . e($sel['professor_payout_name']) . ')' : '') : 'the professor (no payout number saved yet)') ?>.</li>
              <li>Copy the transaction ID from the confirmation message.</li>
              <li>Record it below. The amount is fixed to what is owed.</li>
            </ol>
            <form method="post" class="pp-form" data-confirm="Record <?= e(format_money($sel['owed'])) ?> as paid to <?= e($sel['professor_name'] ?: 'the professor') ?>?">
              <?= csrf_field() ?><input type="hidden" name="_action" value="payout"><input type="hidden" name="campus" value="<?= (int) $sel['id'] ?>">
              <label>Sent by<select name="method"><?php foreach (PROFESSOR_PAYOUT_METHODS as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
              <label>Sent to<?= $selRequest ? ' <small>From their request</small>' : '' ?><input name="sent_to" value="<?= e($selRequest ? trim($selRequest['phone'] . ($selRequest['payout_name'] ? ' · ' . $selRequest['payout_name'] : '')) : trim(($sel['professor_payout_phone'] ?? '') . ($sel['professor_payout_name'] ? ' · ' . $sel['professor_payout_name'] : ''))) ?>" maxlength="120"></label>
              <label>Transaction ID or reference<input name="reference" maxlength="120" placeholder="e.g. 12345678901"></label>
              <label>Note <small>Optional</small><input name="note" maxlength="255"></label>
              <button class="pp-submit" type="submit">Record <?= e(format_money($sel['owed'])) ?> paid <span>→</span></button>
            </form>
          <?php endif; ?>
          <?php if ($wa): ?>
            <a class="pp-wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . campus_first_name((string) $sel['professor_name']) . ', this is Obin Academy. Your ' . format_share(campus_professor_share($sel)) . ' share from the ' . $sel['name'] . ' Campus: ' . format_money($sel['earned']) . ' earned so far, ' . format_money($sel['paid']) . ' paid, ' . format_money($sel['owed']) . ' owed now.')) ?>">Send statement on WhatsApp</a>
          <?php endif; ?>
        </section>

        <section class="acx-sec acx-slam" style="--i:6">
          <div class="lc-sec-h"><div><h2>Paid out</h2><p>UGX <?= e(number_format($sel['paid'])) ?> to date</p></div></div>
          <div class="pp-tl">
            <?php if (!$payouts): ?><p class="acx-muted">No payments sent yet.</p><?php endif; ?>
            <?php foreach ($payouts as $j => $p): ?>
              <div class="acx-whip" style="--j:<?= $j ?>"><b>UGX <?= e(number_format((float) $p['amount'])) ?></b><small><?= e(PROFESSOR_PAYOUT_METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?> · <?= (int) $p['payments'] ?> payment<?= (int) $p['payments'] === 1 ? '' : 's' ?></small><em><?= e(date('j M Y', strtotime($p['paid_at']))) ?></em></div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
