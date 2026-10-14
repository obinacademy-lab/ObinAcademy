<?php
/**
 * A campus professor's pay: every payment in their campus, their share of it, what Obin Academy owes
 * them now and what has been sent, and a Request payment card: the professor asks for everything owed
 * to be sent to their mobile money; the admin pays it on Admin, Professor Pay (recording the payout marks
 * the request paid) or declines it with a reason. Shown to any account linked as a campus professor.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/campuses.php';
$user = require_login();

$mine = professor_pay_summary((int) $user['id']);
if (!$mine) {
    flash_set('error', 'You are not teaching a campus yet.');
    redirect('/teach.php');
}
$sel = $mine[(int) query_param('campus')] ?? reset($mine);
$campusId = (int) $sel['id'];
$ledger = get_campus_professor_earnings($campusId, 200);
$payouts = get_campus_professor_payouts($campusId);
$share = campus_professor_share($sel);
$self = '/dashboard/professor.php' . (count($mine) > 1 ? '?campus=' . $campusId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (post('_action') === 'request') {
            $req = payout_request_create($sel, $user, post('phone'), post('payout_name'), post('note'));
            flash_set('success', 'Requested ' . format_money((float) $req['amount']) . '. Obin Academy will send it to ' . $req['phone'] . ' and you will get the transaction ID by email.');
        } elseif (post('_action') === 'cancel_request') {
            if (payout_request_cancel($campusId, (int) $user['id'])) flash_set('success', 'Request withdrawn.');
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
    }
    redirect($self . '#request');
}
$pending = payout_request_pending($campusId);
$requests = payout_requests_for_campus($campusId, 8);
$reqLabels = ['PENDING' => ['Waiting', 'warn'], 'PAID' => ['Paid', 'good'], 'DECLINED' => ['Not paid', 'bad'], 'CANCELLED' => ['Withdrawn', 'mute']];

$pageTitle = 'My Campus Pay — Obin Academy';
require __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">My Campus Pay</h1>
    <p class="muted" style="margin-top:6px;">You earn <?= e(format_share($share)) ?> of every payment in the <?= e($sel['name']) ?> Campus, the moment it lands.</p>
  </div>
  <div class="row gap-2 wrap">
    <?php if (count($mine) > 1): ?>
      <form method="get"><select name="campus" class="sort-select" onchange="this.form.submit()" aria-label="Campus"><?php foreach ($mine as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $campusId ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></form>
    <?php endif; ?>
    <a class="ld-btn" href="<?= e(base_url('campus-learn.php?campus=' . $sel['slug'])) ?>">Open your campus</a>
  </div>
</div>

<div class="ld-panel ld-strip" style="margin-top:20px;">
  <div class="<?= $sel['owed'] > 0 ? 'warn' : '' ?>"><b><?= e(format_money($sel['owed'])) ?></b><span>Owed to you now</span><small><?= $sel['owed_count'] ? $sel['owed_count'] . ' payment' . ($sel['owed_count'] === 1 ? '' : 's') . ' not yet sent' : 'You are paid up' ?></small></div>
  <div><b><?= e(format_money($sel['paid'])) ?></b><span>Paid to you</span><small><?= $sel['last_paid_at'] ? 'Last on ' . e(date('j M Y', strtotime($sel['last_paid_at']))) : 'Nothing sent yet' ?></small></div>
  <div><b><?= e(format_money($sel['earned30'])) ?></b><span>Earned, last 30 days</span><small><?= e(format_money($sel['earned'])) ?> since you started</small></div>
  <div><b><?= number_format($sel['members']) ?></b><span>Active members</span><small>In your campus right now</small></div>
</div>

<div class="pp-grid" style="margin-top:20px;">
  <div class="ld-panel" style="overflow:hidden;">
    <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Every payment</h2><p class="sub">What each member paid and your <?= e(format_share($share)) ?></p></div></div></div>
    <div class="ca-tbl"><table class="ca-table">
      <thead><tr><th>Date</th><th>Member</th><th class="num">Paid</th><th class="num">Yours</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$ledger): ?><tr><td colspan="5" class="muted">No payments yet. Each one appears here the moment a member pays.</td></tr><?php endif; ?>
        <?php foreach ($ledger as $l): ?>
          <tr>
            <td style="white-space:nowrap;"><?= e(date('j M Y', strtotime($l['created_at']))) ?></td>
            <td><?= e($l['learner_name'] ?: 'A member') ?><small><?= $l['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></td>
            <td class="num"><?= e(format_money((float) $l['gross'])) ?></td>
            <td class="num"><b><?= e(format_money((float) $l['amount'])) ?></b></td>
            <td><?= $l['payout_id'] ? '<span class="ca-pill good">Paid ' . e(date('j M', strtotime($l['payout_paid_at']))) . '</span>' : '<span class="ca-pill warn">Owed</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="pp-side">
    <?php if (payout_requests_ready()): ?>
      <section class="pf-request" id="request">
        <span class="pf-req-label">Request payment</span>
        <div class="pf-req-amt"><small>UGX</small><?= e(number_format($sel['owed'])) ?></div>
        <?php if ($pending): ?>
          <p class="pf-req-state"><span class="pf-req-dot"></span>Requested <?= e(time_ago($pending['requested_at'])) ?> · <?= e(format_money((float) $pending['amount'])) ?> to <?= e($pending['phone']) ?></p>
          <p class="pf-req-sub">Obin Academy sends it to your mobile money and emails you the transaction ID. Anything you earn before then is included.</p>
          <form method="post" data-confirm="Withdraw your payment request?"><?= csrf_field() ?><input type="hidden" name="_action" value="cancel_request"><button class="pf-req-cancel" type="submit">Withdraw request</button></form>
        <?php elseif ($sel['owed'] > 0): ?>
          <p class="pf-req-sub">Everything owed to you now, from <?= (int) $sel['owed_count'] ?> payment<?= (int) $sel['owed_count'] === 1 ? '' : 's' ?>, sent to your mobile money.</p>
          <form method="post" class="pf-req-form" data-confirm="Request <?= e(format_money($sel['owed'])) ?> to be sent to your mobile money?">
            <?= csrf_field() ?><input type="hidden" name="_action" value="request">
            <label>Mobile money number<input name="phone" type="tel" inputmode="tel" required value="<?= e($sel['professor_payout_phone'] ?: ($user['phone'] ?? '')) ?>" placeholder="07XX XXX XXX"></label>
            <label>Name on that number<input name="payout_name" value="<?= e($sel['professor_payout_name'] ?: $user['name']) ?>" maxlength="120"></label>
            <label>Note <small>optional</small><input name="note" maxlength="255" placeholder="Anything Obin Academy should know"></label>
            <button type="submit">Request <?= e(format_money($sel['owed'])) ?> <span>→</span></button>
          </form>
        <?php else: ?>
          <p class="pf-req-sub">Nothing is owed right now. Your <?= e(format_share($share)) ?> is added the moment a member pays, and you can request it here.</p>
        <?php endif; ?>
        <?php if ($requests): ?>
          <div class="pf-req-list">
            <?php foreach ($requests as $r): [$lbl, $tone] = $reqLabels[$r['status']] ?? [$r['status'], 'mute']; ?>
              <div><span><b><?= e(format_money((float) $r['amount'])) ?></b><small><?= e(date('j M Y', strtotime($r['requested_at']))) ?><?= $r['status'] === 'DECLINED' && $r['decline_reason'] ? ' · ' . e($r['decline_reason']) : '' ?></small></span><em class="<?= $tone ?>"><?= e($lbl) ?></em></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <div class="ld-panel ld-card">
      <div class="ld-chead"><div><h2>How you are paid</h2></div></div>
      <ul class="pp-how">
        <li>Every monthly or yearly payment in your campus earns you <b><?= e(format_share($share)) ?></b> of it.</li>
        <li>Request your money any time with the card above. Obin Academy sends it to your mobile money and you get an email with the transaction ID.</li>
        <li>Questions about a payment? <a href="<?= e(base_url('contact.php')) ?>">Contact us</a>.</li>
      </ul>
    </div>
    <div class="ld-panel" style="overflow:hidden; margin-top:16px;">
      <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Sent to you</h2><p class="sub"><?= e(format_money($sel['paid'])) ?> to date</p></div></div></div>
      <div class="ca-list" style="padding:0 22px 10px;">
        <?php if (!$payouts): ?><p class="muted small" style="padding-bottom:12px;">Nothing sent yet.</p><?php endif; ?>
        <?php foreach ($payouts as $p): ?>
          <div style="grid-template-columns:minmax(0,1fr) auto;">
            <div style="min-width:0;"><b class="t"><?= e(format_money((float) $p['amount'])) ?></b><small><?= e(PROFESSOR_PAYOUT_METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ID ' . e($p['reference']) : '' ?> · <?= (int) $p['payments'] ?> payment<?= (int) $p['payments'] === 1 ? '' : 's' ?></small></div>
            <small style="white-space:nowrap;"><?= e(date('j M Y', strtotime($p['paid_at']))) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
