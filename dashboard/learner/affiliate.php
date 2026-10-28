<?php
/**
 * A student's referrals: their link, what they've earned (and from whom), what Obin Academy owes them
 * now and has sent, and a Request payment card — same shape as a campus professor's "My Campus Pay"
 * (dashboard/professor.php), see includes/affiliates.php for the mechanics and why "recurring" means
 * every renewal, not just the first payment.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/campuses.php';
require __DIR__ . '/../../includes/campus_pages.php'; // for the share pop-up's network icons
$user = require_login();

if (!affiliates_ready()) {
    flash_set('error', 'Referrals are being switched on. Check back shortly.');
    redirect('/dashboard.php');
}

$refCode = ensure_user_ref_code($user);
$link = referral_link($refCode);
$sel = affiliate_pay_summary((int) $user['id']);
$ledger = get_affiliate_earnings((int) $user['id'], 200);
$payouts = get_affiliate_payouts((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (post('_action') === 'request') {
            $req = affiliate_payout_request_create($user, post('phone'), post('payout_name'), post('note'));
            flash_set('success', 'Requested ' . format_money((float) $req['amount']) . '. Obin Academy will send it to ' . $req['phone'] . ' and you will get an email once it is sent.');
        } elseif (post('_action') === 'cancel_request') {
            if (affiliate_payout_request_cancel((int) $user['id'])) flash_set('success', 'Request withdrawn.');
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
    }
    redirect('/dashboard/learner/affiliate.php#request');
}
$pending = affiliate_payout_request_pending((int) $user['id']);
$requests = affiliate_payout_requests_for_user((int) $user['id'], 8);
$reqLabels = ['PENDING' => ['Waiting', 'warn'], 'PAID' => ['Paid', 'good'], 'DECLINED' => ['Not paid', 'bad'], 'CANCELLED' => ['Withdrawn', 'mute']];
$shareText = 'Join me on Obin Academy. Learn a skill for a year, taught live, paid monthly or yearly with Mobile Money.';
$sp = fn(string $k) => CAMPUS_PROFESSOR_SOCIALS[$k][1];
$ic = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
$shareIc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>';

$pageTitle = 'My Referrals — Obin Academy';
$pageCss = ['assets/css/campus-page.css', 'assets/css/become-affiliate.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">My Referrals</h1>
    <p class="muted" style="margin-top:6px;">You earn <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?> of every payment a friend you referred makes, on any campus, the moment it lands — and again every time they renew.</p>
  </div>
</div>

<div class="ld-panel ld-strip" style="margin-top:20px;">
  <div class="<?= $sel['owed'] > 0 ? 'warn' : '' ?>"><b><?= e(format_money($sel['owed'])) ?></b><span>Owed to you now</span><small><?= $sel['owed_count'] ? $sel['owed_count'] . ' payment' . ($sel['owed_count'] === 1 ? '' : 's') : 'All settled' ?></small></div>
  <div><b><?= e(format_money($sel['paid'])) ?></b><span>Paid to you</span><small><?= $sel['last_paid_at'] ? 'Last on ' . e(date('j M Y', strtotime($sel['last_paid_at']))) : 'Nothing sent yet' ?></small></div>
  <div><b><?= e(format_money($sel['earned30'])) ?></b><span>Earned, last 30 days</span><small><?= e(format_money($sel['earned'])) ?> since you started</small></div>
  <div><b><?= number_format($sel['referred_paying_count']) ?></b><span>Friends who've paid</span><small><?= number_format($sel['referred_count']) ?> joined with your link</small></div>
</div>

<div class="af-link-card" style="margin-top:20px;">
  <div class="af-link-card-in">
    <span class="af-link-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg></span>
    <div class="af-link-tx"><b>Your referral link</b><span>Anyone who signs up through it is yours, for good.</span></div>
    <div class="af-link-box"><code><?= e(preg_replace('#^https?://#', '', $link)) ?></code></div>
    <button type="button" class="af-share-btn" data-share-open><?= $shareIc ?>Share</button>
  </div>
</div>

<div class="pp-grid" style="margin-top:20px;">
  <div class="ld-panel" style="overflow:hidden;">
    <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Every payment</h2><p class="sub">What your friend paid and your <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?></p></div></div></div>
    <div class="ca-tbl"><table class="ca-table">
      <thead><tr><th>Date</th><th>Friend</th><th>Campus</th><th class="num">Paid</th><th class="num">Yours</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$ledger): ?><tr><td colspan="6" class="muted">No referrals yet. Share your link — this fills in the moment a friend pays.</td></tr><?php endif; ?>
        <?php foreach ($ledger as $l): ?>
          <tr>
            <td style="white-space:nowrap;"><?= e(date('j M Y', strtotime($l['created_at']))) ?></td>
            <td><?= e($l['referred_name']) ?><small><?= $l['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></td>
            <td><?= e($l['campus_name'] ?: '—') ?></td>
            <td class="num"><?= e(format_money((float) $l['gross'])) ?></td>
            <td class="num"><b><?= e(format_money((float) $l['amount'])) ?></b></td>
            <td><?= $l['payout_id'] ? '<span class="ca-pill good">Paid ' . e(date('j M', strtotime($l['payout_paid_at']))) . '</span>' : '<span class="ca-pill warn">Owed</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="pp-side">
    <section class="af-request" id="request">
      <span class="af-req-label">Request payment</span>
      <div class="af-req-amt"><small>UGX</small><?= e(number_format($sel['owed'])) ?></div>
      <?php if ($pending): ?>
        <p class="af-req-state"><span class="af-req-dot"></span>Requested <?= e(time_ago($pending['requested_at'])) ?> &middot; <?= e(format_money((float) $pending['amount'])) ?> to <?= e($pending['phone']) ?></p>
        <p class="af-req-sub">Obin Academy sends it to your mobile money and you'll get an email once it's paid. Anything you earn before then is included too.</p>
        <form method="post" data-confirm="Withdraw your payment request?"><?= csrf_field() ?><input type="hidden" name="_action" value="cancel_request"><button class="af-req-cancel" type="submit">Withdraw request</button></form>
      <?php elseif ($sel['owed'] > 0): ?>
        <p class="af-req-sub">Everything owed to you now, from <?= (int) $sel['owed_count'] ?> payment<?= (int) $sel['owed_count'] === 1 ? '' : 's' ?>, sent to your mobile money.</p>
        <form method="post" class="af-req-form" data-confirm="Request <?= e(format_money($sel['owed'])) ?> to be sent to your mobile money?">
          <?= csrf_field() ?><input type="hidden" name="_action" value="request">
          <label>Mobile money number<input name="phone" type="tel" inputmode="tel" required value="<?= e($user['phone'] ?? '') ?>" placeholder="07XX XXX XXX"></label>
          <label>Name on that number<input name="payout_name" value="<?= e($user['name']) ?>" maxlength="120"></label>
          <label>Note <small>optional</small><input name="note" maxlength="255" placeholder="Anything Obin Academy should know"></label>
          <button type="submit">Request <?= e(format_money($sel['owed'])) ?> <span>→</span></button>
        </form>
      <?php else: ?>
        <p class="af-req-sub">Nothing is owed right now. Your <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?> is added the moment a friend you referred pays, and you can request it here.</p>
      <?php endif; ?>
      <?php if ($requests): ?>
        <div class="af-req-list">
          <?php foreach ($requests as $r): [$lbl, $tone] = $reqLabels[$r['status']] ?? [$r['status'], 'mute']; ?>
            <div><span><b><?= e(format_money((float) $r['amount'])) ?></b><small><?= e(date('j M Y', strtotime($r['requested_at']))) ?><?= $r['status'] === 'DECLINED' && $r['decline_reason'] ? ' &middot; ' . e($r['decline_reason']) : '' ?></small></span><em class="<?= e($tone) ?>"><?= e($lbl) ?></em></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
    <div class="ld-panel ld-card" style="margin-top:16px;">
      <div class="ld-chead"><div><h2>How you are paid</h2></div></div>
      <ul class="pp-how">
        <li>Every monthly or yearly payment a friend you referred makes earns you <b><?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?></b> of it — any campus, every time they renew.</li>
        <li>Your link only credits a brand new account. It stays attached to them for good, from their very first signup.</li>
        <li>Request your money any time with the card above. Obin Academy sends it to your mobile money.</li>
        <li>Questions about a payment? <a href="<?= e(base_url('contact.php')) ?>">Contact us</a>.</li>
      </ul>
    </div>
    <div class="ld-panel" style="overflow:hidden; margin-top:16px;">
      <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Sent to you</h2><p class="sub"><?= e(format_money($sel['paid'])) ?> to date</p></div></div></div>
      <div class="ca-list" style="padding:0 22px 10px;">
        <?php if (!$payouts): ?><p class="muted small" style="padding-bottom:12px;">Nothing sent yet.</p><?php endif; ?>
        <?php foreach ($payouts as $p): ?>
          <div style="grid-template-columns:minmax(0,1fr) auto;">
            <div style="min-width:0;"><b class="t"><?= e(format_money((float) $p['amount'])) ?></b><small><?= e(AFFILIATE_PAYOUT_METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' &middot; ID ' . e($p['reference']) : '' ?></small></div>
            <small style="white-space:nowrap;"><?= e(date('j M Y', strtotime($p['paid_at']))) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="cq-sharescrim" data-share-close hidden></div>
<div class="cq-sharebox" role="dialog" aria-modal="true" aria-labelledby="af-share-h" hidden data-share data-url="<?= e($link) ?>" data-text="<?= e($shareText) ?>" data-title="Join me on Obin Academy">
  <div class="cq-share-h">
    <div><span class="cq-label">Share your link</span><h3 id="af-share-h">Invite a friend</h3><p>Anyone who signs up through it is yours, for good.</p></div>
    <button type="button" class="cq-share-x" data-share-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
  </div>
  <div class="cq-share-link"><input type="text" readonly value="<?= e($link) ?>" aria-label="Your referral link" data-share-input><button type="button" data-share-copy><?= $ic('<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>') ?><span>Copy</span></button></div>
  <div class="cq-share-grid">
    <a class="s whatsapp" data-net="whatsapp" target="_blank" rel="noopener" href="#"><i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $sp('whatsapp') ?>"/></svg></i>WhatsApp</a>
    <a class="s facebook" data-net="facebook" target="_blank" rel="noopener" href="#"><i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $sp('facebook') ?>"/></svg></i>Facebook</a>
    <a class="s x" data-net="x" target="_blank" rel="noopener" href="#"><i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $sp('x') ?>"/></svg></i>X</a>
    <a class="s linkedin" data-net="linkedin" target="_blank" rel="noopener" href="#"><i><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $sp('linkedin') ?>"/></svg></i>LinkedIn</a>
  </div>
  <button type="button" class="cq-share-more" data-share-native hidden><?= $shareIc ?>More ways to share</button>
  <p class="cq-share-note" data-share-note role="status" aria-live="polite"></p>
</div>
<script src="<?= e(versioned_asset('assets/js/share-campus.js')) ?>" defer></script>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
