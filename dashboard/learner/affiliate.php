<?php
/**
 * A student's referrals, as a "desk" — the same premium system as the learner home page (dashboard/
 * learner/index.php): a deep-navy curtain-opening hero with count-up numbers, a sticky dark "Request
 * Payment" card, and white blocks for the link, the friends list and payout history. See includes/
 * affiliates.php for the mechanics and why "recurring" means every renewal, not just the first payment.
 * Styles: admin-overview.css + admin-desk.css (shared desk + shell) + affiliate-desk.css (page-specific:
 * the amount/form in the dark card, the link widgets, the friends list, the tips). Motion: admin-desk.js
 * (ripple, shared) + affiliate-desk.js (count-up, copy button).
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
$friends = get_affiliate_friends((int) $user['id']);
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

// Hero: a real headline and a real "just happened" line, not a template with the life drained out of it.
$firstName = explode(' ', trim($user['name']))[0];
$mostRecent = $ledger[0] ?? null;
if ($mostRecent) {
    $lede = e($mostRecent['referred_name']) . ' just paid for the <b>' . e($mostRecent['campus_name'] ? $mostRecent['campus_name'] . ' Campus' : 'a campus') . '</b> — your '
        . e(format_share(AFFILIATE_COMMISSION_PERCENT)) . ' was added to your balance instantly.';
} else {
    $lede = 'Share your link and start earning the moment a friend joins a campus.';
}
$h1Line2 = $sel['earned'] > 0
    ? e(format_money($sel['earned'])) . ' is already <em>yours.</em>'
    : 'Nobody has joined yet — <em>be the first to share.</em>';

// A simple month-over-month trend for the "earned, 30 days" tile (previous 30-day window, same shape
// as the trend chips on the admin Overview, just computed here rather than client-side from a series).
$earnedPrev30 = (float) (db_one(
    "SELECT COALESCE(SUM(amount), 0) AS n FROM campus_affiliate_earnings
     WHERE affiliate_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
    [$user['id']]
)['n'] ?? 0);
$trendPct = $earnedPrev30 > 0 ? round(($sel['earned30'] - $earnedPrev30) / $earnedPrev30 * 100) : null;

$pageTitle = 'My Referrals — Obin Academy';
$dashClass = 'dash-ov ovd-shell';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-desk.css', 'assets/css/affiliate-desk.css', 'assets/css/campus-page.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ovd">
  <section class="dk-hero">
    <span class="dk-aurora" aria-hidden="true"><i></i><i></i><i></i></span>
    <span class="dk-chip"><i></i>Referral program &middot; <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% recurring</span>
    <h1>
      <span class="dk-line"><span>Welcome back, <?= e($firstName) ?>.</span></span>
      <span class="dk-line"><span><?= $h1Line2 ?></span></span>
    </h1>
    <p class="dk-lede"><?= $lede ?></p>
    <div class="dk-kpis">
      <div class="dk-kpi" style="--k:0"><small>Owed to you now</small><b><span>UGX</span><i data-count="<?= (int) round($sel['owed']) ?>">0</i></b>
        <span class="dk-meta"><?= $sel['owed_count'] ? (int) $sel['owed_count'] . ' payment' . ($sel['owed_count'] === 1 ? '' : 's') : 'All settled' ?></span></div>
      <div class="dk-kpi" style="--k:1"><small>Paid to you</small><b><span>UGX</span><i data-count="<?= (int) round($sel['paid']) ?>">0</i></b>
        <span class="dk-meta"><?= $sel['last_paid_at'] ? 'Last on ' . e(date('j M', strtotime($sel['last_paid_at']))) : 'Nothing sent yet' ?></span></div>
      <div class="dk-kpi" style="--k:2"><small>Earned &middot; 30 days</small><b><span>UGX</span><i data-count="<?= (int) round($sel['earned30']) ?>">0</i></b>
        <span class="dk-meta"><?php if ($trendPct !== null): ?><span class="ov-chip <?= $trendPct >= 0 ? 'up' : 'down' ?>"><?= $trendPct >= 0 ? '▲' : '▼' ?> <?= abs($trendPct) ?>%</span>vs last month<?php else: ?>Since you started<?php endif; ?></span></div>
      <div class="dk-kpi" style="--k:3"><small>Friends who've paid</small><b><i data-count="<?= (int) $sel['referred_paying_count'] ?>">0</i></b>
        <span class="dk-meta"><?= number_format($sel['referred_count']) ?> joined with your link</span></div>
    </div>
  </section>

  <aside class="dk-side">
    <div class="dk-dark" id="request">
      <span class="dk-eb">Request payment</span>
      <div class="dk-amt"><span>UGX</span><i data-count="<?= (int) round($sel['owed']) ?>">0</i></div>
      <?php if ($pending): ?>
        <p class="dk-sub2">Requested <?= e(time_ago($pending['requested_at'])) ?> &middot; <?= e(format_money((float) $pending['amount'])) ?> to <?= e($pending['phone']) ?>. Obin Academy sends it to your mobile money and you'll get an email once it's paid.</p>
        <form method="post" data-confirm="Withdraw your payment request?"><?= csrf_field() ?><input type="hidden" name="_action" value="cancel_request"><button class="dk-cancel" type="submit">Withdraw request</button></form>
      <?php elseif ($sel['owed'] > 0): ?>
        <p class="dk-sub2">Everything owed to you now, from <?= (int) $sel['owed_count'] ?> payment<?= (int) $sel['owed_count'] === 1 ? '' : 's' ?>, sent to your mobile money.</p>
        <form method="post" class="dk-field" data-confirm="Request <?= e(format_money($sel['owed'])) ?> to be sent to your mobile money?">
          <?= csrf_field() ?><input type="hidden" name="_action" value="request">
          <label>Mobile money number<input name="phone" type="tel" inputmode="tel" required value="<?= e($user['phone'] ?? '') ?>" placeholder="07XX XXX XXX"></label>
          <label>Name on that number<input name="payout_name" value="<?= e($user['name']) ?>" maxlength="120"></label>
          <label>Note <small>optional</small><input name="note" maxlength="255" placeholder="Anything Obin Academy should know"></label>
          <button class="dk-btn lime full" type="submit">Request <?= e(format_money($sel['owed'])) ?><svg class="r" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></button>
        </form>
      <?php else: ?>
        <p class="dk-sub2">Nothing is owed right now. Your <?= e(format_share(AFFILIATE_COMMISSION_PERCENT)) ?> is added the moment a friend you referred pays, and you can request it here.</p>
      <?php endif; ?>
      <?php if ($requests): ?>
        <div class="dk-reqlist">
          <?php foreach ($requests as $r): [$lbl, $tone] = $reqLabels[$r['status']] ?? [$r['status'], 'mute']; ?>
            <div><span><b><?= e(format_money((float) $r['amount'])) ?></b><small><?= e(date('j M Y', strtotime($r['requested_at']))) ?><?= $r['status'] === 'DECLINED' && $r['decline_reason'] ? ' &middot; ' . e($r['decline_reason']) : '' ?></small></span><em class="<?= e($tone) ?>"><?= e($lbl) ?></em></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="dk-white">
      <h4>Your link</h4>
      <div class="dk-link-box"><code><?= e(preg_replace('#^https?://#', '', $link)) ?></code>
        <button class="dk-copy" type="button" data-copy-link="<?= e($link) ?>">
          <svg class="cp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
          <svg class="ck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          <span>Copy</span>
        </button>
      </div>
      <button class="dk-share2" type="button" data-share-open><?= $shareIc ?>Share your link</button>
    </div>
  </aside>

  <div class="dk-col">
    <section class="dk-block" style="--b:0">
      <div class="dk-block-h"><h2>Your <em>referral</em> link</h2><span>Anyone who signs up through it is yours, for good</span></div>
      <div class="dk-linkhero">
        <span class="dk-linkhero-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg></span>
        <div class="dk-linkhero-tx">
          <b>Share it anywhere — WhatsApp, Instagram, a classroom chat</b>
          <span>Every payment they ever make, on any campus, earns you <?= (int) AFFILIATE_COMMISSION_PERCENT ?>%.</span>
          <code><?= e($link) ?></code>
        </div>
        <a class="dk-btn lime" href="#" data-share-open><?= $shareIc ?>Share</a>
      </div>
    </section>

    <section class="dk-block" style="--b:1">
      <div class="dk-block-h"><h2>Your <em>friends</em></h2><span><?= (int) $sel['referred_paying_count'] ?> of <?= number_format($sel['referred_count']) ?> are paying members</span></div>
      <?php if (!$friends): ?>
        <p class="dk-empty">No one yet. Once a friend signs up through your link, they'll show up here — even before their first payment.</p>
      <?php else: ?>
        <div class="dk-friends">
          <?php $colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d']; ?>
          <?php foreach ($friends as $k => $f): $paying = (float) $f['earned'] > 0; $initial = mb_strtoupper(mb_substr(trim($f['name']) ?: '?', 0, 1)); ?>
            <div class="dk-fr<?= $paying ? '' : ' wait' ?>" style="--i:<?= $k ?>">
              <span class="dk-fr-av" style="background:<?= e($colors[$k % count($colors)]) ?>"><?= e($initial) ?></span>
              <div class="dk-fr-tx">
                <b><?= e($f['name']) ?></b>
                <span><?php if ($paying): ?><?= e($f['campus_name'] ?: 'Obin Academy') ?> &middot; joined <?= e(date('j M', strtotime($f['joined_at']))) ?> &middot; last paid <?= e(date('j M', strtotime($f['last_paid_at']))) ?>
                  <?php else: ?>Joined <?= e(date('j M Y', strtotime($f['joined_at']))) ?> &middot; hasn't joined a campus yet<?php endif; ?></span>
              </div>
              <div class="dk-fr-amt<?= $paying ? '' : ' wait' ?>">
                <b><?= $paying ? '+' . e(format_money((float) $f['earned'])) : '—' ?></b>
                <span><?= $paying ? (int) $f['payments'] . ' payment' . ((int) $f['payments'] === 1 ? '' : 's') : 'Waiting' ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <div class="dk-two">
      <section class="dk-block" style="--b:2">
        <div class="dk-block-h"><h2>Paid <em>to you</em></h2><span><?= e(format_money($sel['paid'])) ?> to date</span></div>
        <?php if (!$payouts): ?>
          <p class="dk-empty">Nothing sent yet.</p>
        <?php else: ?>
          <div>
            <?php foreach ($payouts as $p): ?>
              <div class="dk-mini"><span><?= e(AFFILIATE_PAYOUT_METHODS[$p['method']] ?? $p['method']) ?> &middot; <?= e(date('j M', strtotime($p['paid_at']))) ?></span><b><?= e(format_money((float) $p['amount'])) ?></b></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
      <section class="dk-block" style="--b:3">
        <div class="dk-block-h"><h2>How you <em>earn</em></h2></div>
        <div class="dk-tips">
          <div class="dk-tip"><span class="dk-tip-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><p><b>Every payment, forever.</b> Not just their first — every renewal earns you <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% again.</p></div>
          <div class="dk-tip"><span class="dk-tip-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1"/></svg></span><p><b>Any campus counts.</b> It doesn't matter which one a friend joins.</p></div>
          <div class="dk-tip"><span class="dk-tip-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg></span><p><b>Cash out any time.</b> Request what's owed above and it lands on your mobile money.</p></div>
          <div class="dk-tip"><span class="dk-tip-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-4"/><path d="m16 11 5 1-5 1"/></svg></span><p>Questions about a payment? <a href="<?= e(base_url('contact.php')) ?>"><b>Contact us</b></a>.</p></div>
        </div>
      </section>
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
<script src="<?= e(versioned_asset('assets/js/admin-desk.js')) ?>" defer></script>
<script src="<?= e(versioned_asset('assets/js/affiliate-desk.js')) ?>" defer></script>

<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
