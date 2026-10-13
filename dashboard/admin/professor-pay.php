<?php
/**
 * Admin: professor pay. Every campus payment earns that campus's professor their share (40% by
 * default) the moment it succeeds. This page shows what is owed per campus, the payment-by-payment
 * ledger, and records money sent (mobile money, bank or cash) with its reference.
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
$paidMonth = (float) (db_one("SELECT COALESCE(SUM(amount), 0) AS s FROM campus_professor_payouts WHERE paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")['s'] ?? 0);
$owing = array_filter($summary, fn($c) => $c['owed'] > 0);

$sel = isset($summary[(int) query_param('campus')]) ? $summary[(int) query_param('campus')] : null;
$ledger = $sel ? get_campus_professor_earnings((int) $sel['id'], 200) : [];
$payouts = $sel ? get_campus_professor_payouts((int) $sel['id']) : [];
$wa = $sel ? whatsapp_number($sel['professor_payout_phone'] ?? '') : null;

$pageTitle = 'Professor Pay — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Professor Pay</h1>
    <p class="muted" style="margin-top:6px;">Each professor earns their share of every campus payment the moment it lands. Send the money, then record it here.</p>
  </div>
  <div class="row gap-2 wrap"><a class="ld-btn" href="<?= e(base_url('dashboard/admin/campuses.php')) ?>">Campuses</a></div>
</div>

<div class="ld-panel ld-strip" style="margin-top:20px;">
  <div class="<?= $owedTotal > 0 ? 'warn' : '' ?>"><b><?= e(format_money($owedTotal)) ?></b><span>Owed to professors now</span><small><?= count($owing) ? count($owing) . ' campus' . (count($owing) === 1 ? '' : 'es') . ' waiting to be paid' : 'Everyone is paid up' ?></small></div>
  <div><b><?= e(format_money($paidMonth)) ?></b><span>Paid out in <?= e(date('F')) ?></span><small>Recorded on this page</small></div>
  <div><b><?= e(format_money($share30)) ?></b><span>Professors' share, last 30 days</span><small>From <?= e(format_money($gross30)) ?> collected</small></div>
  <div><b><?= e(format_money($gross30 - $share30)) ?></b><span>Kept by Obin Academy, 30 days</span><small>Before mobile money charges</small></div>
</div>

<div class="ld-sec" style="margin-top:28px;"><h2>By campus</h2><p>Open a campus to pay its professor and see every payment behind the amount</p></div>
<div class="ld-panel" style="overflow:hidden;">
  <div class="ca-tbl"><table class="ca-table">
    <thead><tr><th>Campus</th><th>Professor</th><th class="num">Share</th><th class="num">Members</th><th class="num">Earned (30 d)</th><th class="num">Paid to date</th><th class="num">Owed now</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($summary as $c): $isSel = $sel && (int) $sel['id'] === (int) $c['id']; ?>
        <tr<?= $isSel ? ' class="pp-sel"' : '' ?>>
          <td><a class="ca-name" href="<?= e(base_url('dashboard/admin/professor-pay.php?campus=' . $c['id'])) ?>"><i><?= campus_icon($c['icon']) ?></i><?= e($c['name']) ?></a></td>
          <td><?= $c['professor_name'] ? e($c['professor_name']) : '<span class="muted">Not set</span>' ?><small><?= $c['professor_payout_phone'] ? e($c['professor_payout_phone']) : 'No payout number' ?></small></td>
          <td class="num"><?= e(format_share(campus_professor_share($c))) ?></td>
          <td class="num"><?= number_format($c['members']) ?></td>
          <td class="num"><?= e(format_money($c['earned30'])) ?></td>
          <td class="num"><?= e(format_money($c['paid'])) ?><small><?= $c['last_paid_at'] ? 'Last ' . e(date('j M', strtotime($c['last_paid_at']))) : 'Never' ?></small></td>
          <td class="num"><?= $c['owed'] > 0 ? '<b>' . e(format_money($c['owed'])) . '</b><small>' . $c['owed_count'] . ' payment' . ($c['owed_count'] === 1 ? '' : 's') . '</small>' : '<span class="ca-pill good">Paid up</span>' ?></td>
          <td class="num"><a class="<?= $c['owed'] > 0 ? 'ot-btn' : 'ld-view' ?>" href="<?= e(base_url('dashboard/admin/professor-pay.php?campus=' . $c['id'] . ($c['owed'] > 0 ? '#pay' : '#ledger'))) ?>"><?= $c['owed'] > 0 ? 'Pay' : 'Open' ?></a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php if ($sel): ?>
  <div class="ld-sec" style="margin-top:32px;" id="ledger"><h2><?= e($sel['name']) ?> Campus</h2><p><?= $sel['professor_name'] ? e($sel['professor_name']) . ' · ' : '' ?><?= e(format_share(campus_professor_share($sel))) ?> of every payment · <a href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $sel['id'] . '&tab=professor')) ?>">Change share or payout number</a></p></div>

  <div class="pp-grid">
    <div class="ld-panel" style="overflow:hidden;">
      <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Every payment</h2><p class="sub">What the member paid and the professor's share of it</p></div></div></div>
      <div class="ca-tbl"><table class="ca-table">
        <thead><tr><th>Date</th><th>Member</th><th class="num">Paid</th><th class="num">Professor</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (!$ledger): ?><tr><td colspan="5" class="muted">No payments in this campus yet. Each one appears here the moment it lands.</td></tr><?php endif; ?>
          <?php foreach ($ledger as $l): ?>
            <tr>
              <td style="white-space:nowrap;"><?= e(date('j M Y', strtotime($l['created_at']))) ?><small><?= e(date('g:i a', strtotime($l['created_at']))) ?></small></td>
              <td><?= e($l['learner_name'] ?: 'Deleted account') ?><small><?= $l['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></td>
              <td class="num"><?= e(format_money((float) $l['gross'])) ?></td>
              <td class="num"><b><?= e(format_money((float) $l['amount'])) ?></b><small><?= e(format_share((float) $l['share_percent'])) ?></small></td>
              <td><?= $l['payout_id'] ? '<span class="ca-pill good">Paid ' . e(date('j M', strtotime($l['payout_paid_at']))) . '</span>' : '<span class="ca-pill warn">Owed</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <div class="pp-side">
      <div class="ld-panel ld-card" id="pay">
        <div class="ld-chead"><div><h2>Pay the professor</h2><p class="sub"><?= $sel['owed'] > 0 ? $sel['owed_count'] . ' payment' . ($sel['owed_count'] === 1 ? '' : 's') . ' not yet paid out' : 'Nothing owed right now' ?></p></div></div>
        <div class="pp-owed"><small>Owed now</small><b><?= e(format_money($sel['owed'])) ?></b></div>
        <?php if ($sel['owed'] > 0): ?>
          <ol class="pp-steps">
            <li>Send <b><?= e(format_money($sel['owed'])) ?></b> to <?= $sel['professor_payout_phone'] ? '<b>' . e($sel['professor_payout_phone']) . '</b>' . ($sel['professor_payout_name'] ? ' (' . e($sel['professor_payout_name']) . ')' : '') : 'the professor (no payout number saved yet)' ?>.</li>
            <li>Copy the transaction ID from the confirmation message.</li>
            <li>Record it below. The amount is fixed to what is owed.</li>
          </ol>
          <form method="post" class="ca-form" style="padding:0; margin-top:14px;" data-confirm="Record <?= e(format_money($sel['owed'])) ?> as paid to <?= e($sel['professor_name'] ?: 'the professor') ?>?">
            <?= csrf_field() ?><input type="hidden" name="_action" value="payout"><input type="hidden" name="campus" value="<?= (int) $sel['id'] ?>">
            <label>Sent by<select name="method"><?php foreach (PROFESSOR_PAYOUT_METHODS as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
            <label>Sent to<input name="sent_to" value="<?= e(trim(($sel['professor_payout_phone'] ?? '') . ($sel['professor_payout_name'] ? ' · ' . $sel['professor_payout_name'] : ''))) ?>" maxlength="120"></label>
            <label>Transaction ID or reference<input name="reference" maxlength="120" placeholder="e.g. 12345678901"></label>
            <label>Note <small>Optional</small><input name="note" maxlength="255"></label>
            <button class="btn btn-primary" type="submit">Record <?= e(format_money($sel['owed'])) ?> paid</button>
          </form>
        <?php endif; ?>
        <?php if ($wa): ?>
          <a class="ot-btn wa" style="margin-top:14px; display:inline-flex;" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . campus_first_name((string) $sel['professor_name']) . ', this is Obin Academy. Your ' . format_share(campus_professor_share($sel)) . ' share from the ' . $sel['name'] . ' Campus: ' . format_money($sel['earned']) . ' earned so far, ' . format_money($sel['paid']) . ' paid, ' . format_money($sel['owed']) . ' owed now.')) ?>">Send statement on WhatsApp</a>
        <?php endif; ?>
      </div>

      <div class="ld-panel" style="overflow:hidden; margin-top:16px;">
        <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Paid out</h2><p class="sub"><?= e(format_money($sel['paid'])) ?> to date</p></div></div></div>
        <div class="ca-list" style="padding:0 22px 10px;">
          <?php if (!$payouts): ?><p class="muted small" style="padding-bottom:12px;">No payments sent yet.</p><?php endif; ?>
          <?php foreach ($payouts as $p): ?>
            <div style="grid-template-columns:minmax(0,1fr) auto;">
              <div style="min-width:0;"><b class="t"><?= e(format_money((float) $p['amount'])) ?></b><small><?= e(PROFESSOR_PAYOUT_METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?> · <?= (int) $p['payments'] ?> payment<?= (int) $p['payments'] === 1 ? '' : 's' ?></small></div>
              <small style="white-space:nowrap;"><?= e(date('j M Y', strtotime($p['paid_at']))) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<script>document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
