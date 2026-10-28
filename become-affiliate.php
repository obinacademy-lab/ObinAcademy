<?php
/**
 * Become an Affiliate: the public explainer for the student referral program (see includes/affiliates.php
 * for the mechanics). No application — every student already has a referral link, on dashboard/learner/
 * affiliate.php. This page is pure explainer + a CTA there (or to sign up first, for a guest).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

$viewer = current_user();
$published = get_published_campuses();
$campusCount = max(count($published), count(get_all_campuses()));
$examplePrice = $published ? (float) $published[0]['monthly_price'] : 100000.0;
$exampleCommission = round($examplePrice * AFFILIATE_COMMISSION_PERCENT / 100);
$ctaUrl = $viewer ? base_url('dashboard/learner/affiliate.php') : base_url('signup.php?redirect=' . urlencode('/dashboard/learner/affiliate.php'));
$linkIc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>';

$pageTitle = 'Become an Affiliate — Earn 10% Recurring Commission — Obin Academy';
$pageDescription = 'Share your Obin Academy referral link. Every time a friend you referred pays for any campus, you earn ' . AFFILIATE_COMMISSION_PERCENT . '% — and you keep earning it every time they renew.';
$extraGoogleFont = 'Geist+Mono:wght@500;600;700';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/become-affiliate.css')) ?>">

<header class="af-hero">
  <div class="container af-hero-in">
    <span class="af-pill"><b><?= (int) AFFILIATE_COMMISSION_PERCENT ?>%</b>recurring commission</span>
    <h1>Earn every time your friends <em>keep learning.</em></h1>
    <p class="af-lede">Share your link. When a friend joins any campus, you earn <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% of what they pay — and you keep earning it every month they stay, for as long as they do. Not just the first payment.</p>
    <div class="af-hero-cta">
      <a class="btn btn-gold btn-lg shine" href="<?= e($ctaUrl) ?>">Get my referral link <span class="btn-arrow">→</span></a>
      <a class="af-btn-ghost" href="#how">See how it works</a>
    </div>
    <div class="af-hero-trust">
      <div><b><?= (int) AFFILIATE_COMMISSION_PERCENT ?>%</b><span>of every payment</span></div>
      <div><b>Forever</b><span>while they stay</span></div>
      <div><b>Any campus</b><span>all <?= (int) $campusCount ?>, no limit</span></div>
    </div>
  </div>
</header>

<?php if ($viewer): $myCode = ensure_user_ref_code($viewer); if ($myCode): ?>
<div class="af-link-card">
  <div class="af-link-card-in">
    <span class="af-link-ic"><?= $linkIc ?></span>
    <div class="af-link-tx"><b>Your referral link</b><span>Already live. Share it anywhere.</span></div>
    <div class="af-link-box"><code><?= e(preg_replace('#^https?://#', '', referral_link($myCode))) ?></code>
      <a href="<?= e(base_url('dashboard/learner/affiliate.php')) ?>">Open &amp; copy</a>
    </div>
  </div>
</div>
<?php endif; endif; ?>

<section id="how" class="container af-sec">
  <div class="af-sec-h">
    <span class="af-eyebrow">How it works</span>
    <h2>Three steps. No applications, no waiting.</h2>
    <p>Every Obin Academy student already has a referral link — you don't apply or get approved. Open your dashboard, copy your link, and start sharing.</p>
  </div>
  <div class="af-steps">
    <div class="af-step"><div class="af-step-n">01</div><h3>Share your link</h3><p>Send it to friends, post it, drop it in a WhatsApp group — anywhere people who want to learn will see it.</p></div>
    <div class="af-step"><div class="af-step-n">02</div><h3>A friend joins a campus</h3><p>They sign up through your link and pay to join any of the <?= (int) $campusCount ?> campuses, monthly or for the year.</p></div>
    <div class="af-step"><div class="af-step-n">03</div><h3>You earn <?= (int) AFFILIATE_COMMISSION_PERCENT ?>%, automatically</h3><p>The moment their payment lands, your <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% is added to your balance. No action needed from you.</p></div>
  </div>
</section>

<section class="container af-sec">
  <div class="af-recur">
    <div class="af-recur-tx">
      <span class="af-eyebrow af-eyebrow-lime">Why "recurring" matters</span>
      <h2>One friend who stays a year pays you five times.</h2>
      <p>Campuses don't auto-renew — your friend chooses to pay again each month. Every time they do, for as long as they keep learning, you earn <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% again. One good referral can pay you for months.</p>
      <div class="af-recur-foot">
        <div><b><?= e(format_money($examplePrice)) ?></b><span>a friend pays, this campus</span></div>
        <div><b><?= e(format_money($exampleCommission)) ?></b><span>you earn, every time</span></div>
      </div>
    </div>
    <div class="af-ledger">
      <?php for ($m = 1; $m <= 5; $m++): ?>
        <div class="af-ledger-row<?= $m === 1 ? ' hl' : '' ?>">
          <span class="m">MONTH <?= $m ?></span>
          <span class="d"><?= $m === 1 ? 'Your friend joins' : 'Your friend renews' ?></span>
          <span class="a">+<?= number_format($exampleCommission) ?></span>
        </div>
      <?php endfor; ?>
      <div class="af-ledger-row sum"><span></span><span class="d">Earned so far</span><span class="a"><?= e(format_money($exampleCommission * 5)) ?></span></div>
    </div>
  </div>
</section>

<section class="container af-sec">
  <div class="af-sec-h">
    <span class="af-eyebrow">Questions</span>
    <h2>Before you share your link</h2>
  </div>
  <div class="af-faq">
    <details class="af-faq-item" open>
      <summary>Do I need to apply or get approved?<span class="x">+</span></summary>
      <div class="b">No. Every student already has a referral link, ready to share from their dashboard. There's no application and no waiting.</div>
    </details>
    <details class="af-faq-item">
      <summary>Does the commission ever stop?<span class="x">+</span></summary>
      <div class="b">Only if your friend stops paying. As long as they keep renewing their campus — monthly or yearly — you keep earning <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% of every payment, with no end date.</div>
    </details>
    <details class="af-faq-item">
      <summary>Does it work for every campus?<span class="x">+</span></summary>
      <div class="b">Yes. Your <?= (int) AFFILIATE_COMMISSION_PERCENT ?>% applies no matter which campus your friend joins, and if they later join a second campus, you earn on that too.</div>
    </details>
    <details class="af-faq-item">
      <summary>How do I get paid?<span class="x">+</span></summary>
      <div class="b">Your earnings build up in your dashboard. Request a payout any time and Obin Academy sends it straight to your mobile money.</div>
    </details>
    <details class="af-faq-item">
      <summary>What if my friend already has an account?<span class="x">+</span></summary>
      <div class="b">Your link only credits you for someone creating a brand new Obin Academy account. It can't be attached to an existing account.</div>
    </details>
  </div>
</section>

<section class="container af-sec">
  <div class="af-cta-band">
    <h2>Your link is already waiting in your dashboard.</h2>
    <a class="af-btn-dark" href="<?= e($ctaUrl) ?>">Get my referral link <span>→</span></a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
