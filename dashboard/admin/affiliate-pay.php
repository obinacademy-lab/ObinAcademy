<?php
/**
 * Admin: affiliate pay, as a "pay desk" — same shape as Professor Pay (dashboard/admin/professor-pay.php),
 * but by affiliate (a student) instead of by campus. Every campus payment made by someone a student
 * referred earns that student 10% the moment it lands (see includes/affiliates.php). A dark console shows
 * what is owed now and a lane per affiliate; the ledger lists every affiliate with a Pay button; opening
 * one (?affiliate=) shows each referral payment behind the amount and records money sent (mobile money,
 * bank or cash) with its reference. Affiliates can ask to be paid (dashboard/learner/affiliate.php): open
 * requests are listed first, with Pay and Decline.
 *
 * Styles: assets/css/admin-campuses.css (console, ledger) + assets/css/admin-pay.css. Motion: assets/js/admin-pay.js (shared with Professor Pay — same [data-pp]/[data-scramble]/[data-confirm] hooks).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

if (!affiliates_ready()) {
    $pageTitle = 'Affiliate Pay — Admin — Obin Academy';
    require __DIR__ . '/../../includes/dashboard_header.php';
    echo '<div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;"><p class="muted">Referrals are being set up. Check back in a few minutes.</p></div>';
    require __DIR__ . '/../../includes/dashboard_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $affiliateId = (int) post('affiliate');
    if (post('_action') === 'decline_request') {
        if (mb_strlen(trim(post('reason'))) < 4) {
            flash_set('error', 'Write a short reason. The affiliate sees it.');
        } elseif ($r = affiliate_payout_request_decline((int) post('request'), post('reason'), (int) $user['id'])) {
            $declinedName = db_one('SELECT name FROM users WHERE id = ?', [$r['affiliate_user_id']])['name'] ?? 'Affiliate';
            log_admin_action((int) $user['id'], $user['name'], 'affiliate.payout_request_declined', 'User', $declinedName, format_money((float) $r['amount']) . ': ' . trim(post('reason')));
            flash_set('success', 'Request declined. The affiliate was told why.');
        }
        redirect('/dashboard/admin/affiliate-pay.php#requests');
    }
    if (post('_action') === 'payout') {
        $affiliate = db_one('SELECT id, name, email FROM users WHERE id = ?', [$affiliateId]);
        $method = post('method');
        if ($method === 'MOBILE_MONEY' && trim(post('reference')) === '') {
            flash_set('error', 'Add the mobile money transaction ID, so you and the affiliate can match the payment.');
            redirect('/dashboard/admin/affiliate-pay.php?affiliate=' . $affiliateId . '#pay');
        }
        $payout = $affiliate ? record_affiliate_payout($affiliateId, $method, trim(post('sent_to')), trim(post('reference')), trim(post('note')), (int) $user['id']) : null;
        if ($payout) {
            log_admin_action((int) $user['id'], $user['name'], 'affiliate.paid', 'User', $affiliate['name'], format_money((float) $payout['amount']) . ' to ' . $affiliate['name'] . ($payout['reference'] ? ' (ref ' . $payout['reference'] . ')' : ''));
            flash_set('success', 'Recorded ' . format_money((float) $payout['amount']) . ' paid to ' . $affiliate['name'] . '.' . ($affiliate['email'] ? ' They were sent a statement.' : ''));
        } else {
            flash_set('error', 'Nothing is owed to this affiliate right now.');
        }
    }
    redirect('/dashboard/admin/affiliate-pay.php?affiliate=' . $affiliateId);
}

$overview = affiliate_admin_overview(100);
$owedTotal = $overview['owed'];
$paidAll = $overview['paid'];
$paidMonth = (float) (db_one("SELECT COALESCE(SUM(amount), 0) AS s FROM campus_affiliate_payouts WHERE paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['s'] ?? 0);
$earned30 = (float) (db_one("SELECT COALESCE(SUM(amount), 0) AS s FROM campus_affiliate_earnings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['s'] ?? 0);
$owing = array_filter($overview['top'], fn($a) => (float) $a['owed'] > 0);
$owedMax = $owing ? max(array_column($owing, 'owed')) : 0;

$selId = (int) query_param('affiliate');
$sel = $selId ? (db_one('SELECT * FROM users WHERE id = ?', [$selId]) ?: null) : null;
$selSummary = $sel ? affiliate_pay_summary($selId) : null;
$ledger = $sel ? get_affiliate_earnings($selId, 200) : [];
$payouts = $sel ? get_affiliate_payouts($selId) : [];
$selRequest = $sel ? affiliate_payout_request_pending($selId) : null;
$requests = affiliate_payout_requests_open();

$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
$initial = fn(string $name) => mb_strtoupper(mb_substr(trim($name) ?: '?', 0, 1));
$avatar = function (string $name, int $id, string $class) use ($colors, $initial): string {
    return '<span class="' . $class . ' afp-av" style="background:' . $colors[$id % count($colors)] . '">' . e($initial($name)) . '</span>';
};
$affiliateUrl = fn(int $id, string $hash = '') => base_url('dashboard/admin/affiliate-pay.php?affiliate=' . $id) . $hash;

$pageTitle = ($sel ? e($sel['name']) . ' — ' : '') . 'Affiliate Pay — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-pay.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx pp" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Affiliate pay</h1><p>A student earns <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?> of every campus payment made by someone they referred, every time, for as long as that person keeps paying. Send the money, then record it here.</p></div>
    <div class="acx-acts"><a class="acx-btn" href="<?= e(base_url('dashboard/admin/users.php')) ?>"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Users</a></div>
  </div>

  <section class="lc-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $owedTotal > 0 ? '' : 'calm' ?>"><i></i><?= $owedTotal > 0 ? 'Waiting to be paid' : 'All paid up' ?></span>
      <div class="pp-big"><small>UGX</small><b data-scramble="<?= (int) round($owedTotal) ?>"><?= e(number_format($owedTotal)) ?></b></div>
      <p class="lc-copy"><?= $owing ? 'Owed now to ' . count($owing) . ' affiliate' . (count($owing) === 1 ? '' : 's') . '. Open one to send it and record the transaction.' : 'Nothing is owed right now. New referral earnings show up here the moment a payment lands.' ?></p>
      <div class="lc-stats">
        <div><span>Paid out in <?= e(date('F')) ?></span><b><small>UGX</small><span data-scramble="<?= (int) round($paidMonth) ?>"><?= e(number_format($paidMonth)) ?></span></b></div>
        <div><span>Paid to date</span><b><small>UGX</small><span data-scramble="<?= (int) round($paidAll) ?>"><?= e(number_format($paidAll)) ?></span></b></div>
        <div><span>Earned by affiliates · 30 days</span><b><small>UGX</small><span data-scramble="<?= (int) round($earned30) ?>"><?= e(number_format($earned30)) ?></span></b></div>
        <div><span>Students referring</span><b><span data-scramble="<?= (int) $overview['referrers'] ?>"><?= number_format($overview['referrers']) ?></span></b></div>
      </div>
    </div>
    <div class="lc-rx">
      <div class="lc-rx-h"><b>Owed by affiliate</b><span>click a lane to pay</span></div>
      <?php if (!$overview['top']): ?><p class="acx-muted" style="padding:14px 4px;">No referrals yet.</p><?php endif; ?>
      <?php foreach ($overview['top'] as $a): $owed = (float) $a['owed']; $w = $owedMax > 0 ? max(3, round($owed / $owedMax * 100)) : 0; ?>
        <a class="pp-lane <?= $owed > 0 ? 'owed' : '' ?> <?= $sel && (int) $sel['id'] === (int) $a['id'] ? 'on' : '' ?>" href="<?= e($affiliateUrl((int) $a['id'], $owed > 0 ? '#pay' : '#ledger')) ?>" style="--l:<?= $w ?>">
          <span class="nm"><?= $avatar($a['name'], (int) $a['id'], 'pp-dot') ?><?= e($a['name']) ?></span>
          <span class="pp-track"><?php if ($owed > 0): ?><i style="width:<?= $w ?>%"></i><?php endif; ?></span>
          <span class="amt"><?= $owed > 0 ? e(number_format($owed)) : 'Paid up' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($requests): ?>
    <section class="acx-sec acx-slam pp-reqs" style="--i:2" id="requests">
      <div class="lc-sec-h"><div><h2>Payment requests <em class="pp-req-n"><?= count($requests) ?></em></h2><p>Affiliates asking to be paid. Pay opens their page with their number filled in; recording the payout marks the request paid.</p></div></div>
      <?php foreach ($requests as $j => $r): ?>
        <div class="pp-req acx-whip" style="--j:<?= $j ?>">
          <div class="pp-req-who"><b><?= e($r['affiliate_name']) ?></b><small>Referrals · asked <?= e(time_ago($r['requested_at'])) ?></small></div>
          <div class="pp-req-amt"><b><?= e(format_money((float) $r['amount'])) ?></b><small><?= abs($r['owed_now'] - (float) $r['amount']) > 0.5 ? e(format_money($r['owed_now'])) . ' owed now' : 'owed now' ?></small></div>
          <div class="pp-req-to"><b><?= e($r['phone']) ?></b><small><?= e($r['payout_name'] ?: '') ?><?= $r['note'] ? ($r['payout_name'] ? ' · ' : '') . '“' . e($r['note']) . '”' : '' ?></small></div>
          <div class="pp-req-acts">
            <a class="lc-next pub" href="<?= e($affiliateUrl((int) $r['affiliate_user_id'], '#pay')) ?>">Pay<span>→</span></a>
            <details class="pp-decline"><summary>Decline</summary>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="decline_request"><input type="hidden" name="request" value="<?= (int) $r['id'] ?>"><input name="reason" required minlength="4" maxlength="255" placeholder="Why not?"><button type="submit">Send</button></form>
            </details>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="acx-sec acx-slam" style="--i:2">
    <div class="lc-sec-h"><div><h2>By affiliate</h2><p>Open an affiliate to pay them and see every referral payment behind the amount</p></div></div>
    <div class="pp-grid pp-th"><span>Affiliate</span><span class="r">Referrals</span><span class="r">Earned · 30d</span><span class="r">Paid to date</span><span class="r">Owed now</span><span></span></div>
    <?php foreach ($overview['top'] as $j => $a): $isSel = $sel && (int) $sel['id'] === (int) $a['id']; $owed = (float) $a['owed']; ?>
      <div class="pp-grid lc-row acx-whip <?= $isSel ? 'sel' : '' ?>" style="--j:<?= $j ?>">
        <a class="lc-camp" href="<?= e($affiliateUrl((int) $a['id'], '#ledger')) ?>">
          <?= $avatar($a['name'], (int) $a['id'], 'ph') ?>
          <span><b><?= e($a['name']) ?></b><small><?= e($a['email']) ?></small></span>
        </a>
        <span class="lc-num r"><?= number_format((int) $a['referred_paying_count']) ?></span>
        <span class="lc-num r"><?= (float) $a['earned'] > 0 ? e(number_format((float) $a['earned'])) . '<small>UGX</small>' : '—' ?></span>
        <span class="lc-num r"><?= $owed < (float) $a['earned'] ? e(number_format((float) $a['earned'] - $owed)) : '0' ?></span>
        <span class="r"><?= $owed > 0 ? '<span class="pp-owed-n">' . e(number_format($owed)) . '</span>' : '<span class="acx-muted">0</span>' ?></span>
        <a class="lc-next <?= $owed > 0 ? 'pub' : 'quiet' ?>" href="<?= e($affiliateUrl((int) $a['id'], $owed > 0 ? '#pay' : '#ledger')) ?>"><?= $owed > 0 ? 'Pay' : 'Open' ?><span>→</span></a>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($sel && $selSummary): ?>
    <div class="pp-sel-h acx-slam" style="--i:3" id="ledger">
      <?= $avatar($sel['name'], (int) $sel['id'], 'ph') ?>
      <div><h2><?= e($sel['name']) ?></h2><p><?= e($sel['email']) ?> · <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?> of every referral payment</p></div>
    </div>
    <div class="pp-split">
      <section class="acx-sec acx-slam" style="--i:4">
        <div class="lc-sec-h"><div><h2>Every payment</h2><p>Who they referred paid, and this affiliate's <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?></p></div></div>
        <div class="acx-tbl"><table>
          <thead><tr><th>Date</th><th>Friend</th><th>Campus</th><th class="r">Paid</th><th class="r">Affiliate</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!$ledger): ?><tr><td colspan="6" class="acx-muted">No referral payments yet. Each one appears here the moment it lands.</td></tr><?php endif; ?>
            <?php foreach ($ledger as $j => $l): ?>
              <tr class="acx-whip" style="--j:<?= min($j, 12) ?>">
                <td><?= e(date('j M Y', strtotime($l['created_at']))) ?><small class="pp-sub"><?= e(date('g:i a', strtotime($l['created_at']))) ?></small></td>
                <td><?= e($l['referred_name']) ?><small class="pp-sub"><?= $l['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></td>
                <td><?= e($l['campus_name'] ?: '—') ?></td>
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
          <span class="lc-eyebrow <?= $selSummary['owed'] > 0 ? '' : 'calm' ?>"><i></i>Pay the affiliate</span>
          <div class="pp-big sm"><small>UGX</small><b data-scramble="<?= (int) round($selSummary['owed']) ?>"><?= e(number_format($selSummary['owed'])) ?></b></div>
          <p class="pp-note"><?= $selSummary['owed'] > 0 ? (int) $selSummary['owed_count'] . ' payment' . ((int) $selSummary['owed_count'] === 1 ? '' : 's') . ' not yet paid out' : 'Nothing owed right now' ?></p>
          <?php if ($selSummary['owed'] > 0): ?>
            <ol class="pp-steps">
              <li>Send <b><?= e(format_money($selSummary['owed'])) ?></b> to <?= $selRequest ? '<b>' . e($selRequest['phone']) . '</b>' . ($selRequest['payout_name'] ? ' (' . e($selRequest['payout_name']) . ')' : '') . ', the number they requested' : (($sel['phone'] ?? '') ? '<b>' . e($sel['phone']) . '</b>, their account number' : 'the affiliate\'s mobile money number') ?>.</li>
              <li>Copy the transaction ID from the confirmation message.</li>
              <li>Record it below. The amount is fixed to what is owed.</li>
            </ol>
            <form method="post" class="pp-form" data-confirm="Record <?= e(format_money($selSummary['owed'])) ?> as paid to <?= e($sel['name']) ?>?">
              <?= csrf_field() ?><input type="hidden" name="_action" value="payout"><input type="hidden" name="affiliate" value="<?= (int) $sel['id'] ?>">
              <label>Sent by<select name="method"><?php foreach (AFFILIATE_PAYOUT_METHODS as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
              <label>Sent to<?= $selRequest ? ' <small>From their request</small>' : '' ?><input name="sent_to" value="<?= e($selRequest ? trim($selRequest['phone'] . ($selRequest['payout_name'] ? ' · ' . $selRequest['payout_name'] : '')) : ($sel['phone'] ?? '')) ?>" maxlength="120" placeholder="07XX XXX XXX"></label>
              <label>Transaction ID or reference<input name="reference" maxlength="120" placeholder="e.g. 12345678901"></label>
              <label>Note <small>Optional</small><input name="note" maxlength="255"></label>
              <button class="pp-submit" type="submit">Record <?= e(format_money($selSummary['owed'])) ?> paid <span>→</span></button>
            </form>
          <?php endif; ?>
        </section>

        <section class="acx-sec acx-slam" style="--i:6">
          <div class="lc-sec-h"><div><h2>Paid out</h2><p>UGX <?= e(number_format($selSummary['paid'])) ?> to date</p></div></div>
          <div class="pp-tl">
            <?php if (!$payouts): ?><p class="acx-muted">No payments sent yet.</p><?php endif; ?>
            <?php foreach ($payouts as $j => $p): ?>
              <div class="acx-whip" style="--j:<?= $j ?>"><b>UGX <?= e(number_format((float) $p['amount'])) ?></b><small><?= e(AFFILIATE_PAYOUT_METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?> · <?= e(date('j M Y', strtotime($p['paid_at']))) ?></small></div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  <?php endif; ?>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
