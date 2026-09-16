<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$user = current_user();
$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user) {
        redirect('/login.php?redirect=/become-affiliate.php');
    }
    $existingAffiliate = get_affiliate_by_user_id((int) $user['id']);
    if ($existingAffiliate && $existingAffiliate['status'] === 'ACTIVE') {
        $errors[] = "You're already an affiliate partner.";
    } else {
        $motivation = post('motivation');
        if (strlen($motivation) < 20) $errors[] = 'Tell us a bit more about how you plan to share Obin Academy (at least 20 characters).';

        if (!$errors) {
            $existing = db_one('SELECT id FROM affiliate_applications WHERE user_id = ?', [$user['id']]);
            if ($existing) {
                db_run("UPDATE affiliate_applications SET status='PENDING', motivation=?, rejection_reason=NULL, reviewed_at=NULL WHERE id=?", [$motivation, $existing['id']]);
            } else {
                db_insert('INSERT INTO affiliate_applications (user_id, motivation) VALUES (?, ?)', [$user['id'], $motivation]);
            }
            $submitted = true;
        }
    }
}

$myAffiliate = $user ? get_affiliate_by_user_id((int) $user['id']) : null;
$myApplication = $user ? db_one('SELECT * FROM affiliate_applications WHERE user_id = ?', [$user['id']]) : null;

// Real social proof, not a marketing round number — same "actually paid
// out" definition used on the admin financial pages (approved withdrawals,
// not gross accrued earnings).
$paidToAffiliates = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE status='APPROVED' AND payee_type='AFFILIATE'")['n'] ?? 0);
$stats = get_platform_stats();

$affiliateBenefits = [
    ['🔗', '#2563eb', 'Your Own Tracked Link', 'Get a unique referral link that credits you automatically for every sale it brings in.'],
    ['🛍️', '#f5b301', 'Every Course, Every Creator', "You're not limited to one course — earn commission across the entire Obin Academy catalog."],
    ['💵', '#10b981', '2% Commission', 'Earn 2% on every course purchase made through your link, no matter the price.'],
    ['⏱️', '#8b5cf6', '30-Day Tracking Window', "Referred someone who buys later? You're still credited if it's within 30 days of their click."],
    ['📱', '#06b6d4', 'Share Anywhere', 'WhatsApp, social media, your blog, your community — share your link wherever your audience already is.'],
    ['📊', '#ec4899', 'Track Every Click and Sale', 'See clicks, conversions, and earnings in real time from your affiliate dashboard.'],
    ['💰', '#f97316', 'Mobile Money Payouts', 'Withdraw your earnings straight to MTN or Airtel Mobile Money whenever you\'re ready.'],
    ['🆓', '#6366f1', 'Free to Join', 'No cost, no inventory, no minimum audience size — just apply and start sharing.'],
];
$affiliateSteps = [
    ['Apply to Become an Affiliate', 'Tell us how you plan to share Obin Academy and submit your application for review.'],
    ['Get Your Affiliate Link', 'Once approved, your unique referral link is ready in your Affiliate Dashboard.'],
    ['Share It With Your Audience', 'Post it on WhatsApp, social media, or anywhere your audience already trusts you.'],
    ['Earn on Every Sale', 'Get 2% commission automatically whenever someone buys through your link, paid to mobile money.'],
];

// Illustrative only, not tied to any real affiliate's numbers — a plain
// example so "2% commission" means something concrete on the page.
$exampleReferredSalePrice = 50000.0;
$exampleReferredSalesCount = 100;
$exampleReferredGross = $exampleReferredSalePrice * $exampleReferredSalesCount;
$exampleAffiliateCommission = $exampleReferredGross * AFFILIATE_COMMISSION_RATE;

$pageTitle = 'Become an Affiliate Partner — Obin Academy';
$pageDescription = 'Earn 2% commission on every course purchase you refer through your own affiliate link, on any course from any creator on Obin Academy.';
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill">Earn by Sharing Obin Academy</span>
    <h1 style="margin-top:14px;">Turn Your Network Into Income</h1>
    <p class="summary" style="margin:14px auto 0;">Apply to become an affiliate partner. Once approved, you get your own link to share — earn 2% commission whenever someone buys any course, from any creator, through your link.</p>

    <?php if ($paidToAffiliates > 0): ?>
      <div class="hero-trust-stat">
        <?php dash_icon('banknote'); ?>
        <span data-count-up data-count-value="<?= (int) round($paidToAffiliates) ?>" data-count-prefix="UGX " data-count-compact data-count-suffix="">UGX 0</span> already paid out to affiliates
      </div>
    <?php endif; ?>

    <div class="row gap-2" style="justify-content:center; flex-wrap:wrap; margin-top:28px;">
      <a href="#apply" class="btn btn-gold shine">Apply to Earn <span class="btn-arrow">→</span></a>
      <a href="#how-it-works" class="btn btn-outline-light">See How It Works</a>
    </div>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<!-- Why share -->
<div class="section">
  <div class="container">
    <div class="why-exist-grid">
      <div class="reveal">
        <span class="eyebrow">Why Share Obin Academy</span>
        <h2 class="h2" style="margin-top:14px;">Turn Every Referral Into Real Income</h2>
        <p class="lede" style="margin-top:16px; max-width:none; font-size:16.5px; line-height:1.75; color:var(--muted);">
          You don't need to create a course to earn on Obin Academy. Get your own tracked link and earn a commission on any course, from any creator, the moment someone buys through it — no inventory, no customer support, no limit on how many people you refer.
        </p>
        <div class="mission-callout" style="margin-top:24px;">
          <span class="tag">The Affiliate Split</span>
          <p>Earn 2% commission on every sale your link brings in — tracked for 30 days after someone clicks, paid straight to your mobile money.</p>
        </div>
      </div>
      <div class="why-exist-photo reveal reveal-delay-2">
        <img src="<?= e(versioned_asset('assets/img/hero-bg-premium.jpg')) ?>" alt="A partner sharing their Obin Academy affiliate link">
      </div>
    </div>
  </div>
</div>

<!-- What you get -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:620px; margin:0 auto 40px;">
      <span class="eyebrow">What You Get</span>
      <h2 class="h2" style="margin-top:10px;">Everything You Need to Earn as an Affiliate</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">A tracked link, real-time reporting, and instant mobile money payouts — sharing Obin Academy pays like a real side income.</p>
    </div>
    <div class="center-grid">
      <?php foreach ($affiliateBenefits as $i => [$emoji, $tint, $title, $desc]): ?>
        <div class="value-card reveal reveal-delay-<?= min($i % 5 + 1, 5) ?>">
          <span class="icon-badge" style="--tint:<?= e($tint) ?>;"><?= $emoji ?></span>
          <h3><?= e($title) ?></h3>
          <p><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- How it works + earnings example -->
<div class="section" id="how-it-works">
  <div class="container">
    <div class="text-center reveal" style="max-width:580px; margin:0 auto 40px;">
      <span class="eyebrow">Simple By Design</span>
      <h2 class="h2" style="margin-top:10px;">How It Works, and What You'll Earn</h2>
    </div>
    <div class="grid lg:grid-2" style="gap:28px;">
      <div class="how-panel panel-creators reveal">
        <span class="how-panel-tag tag-gold">🔗 How It Works</span>
        <div class="stack gap-3" style="margin-top:24px; position:relative; z-index:1;">
          <?php foreach ($affiliateSteps as $i => [$title, $desc]): ?>
            <div class="step-row">
              <span class="step-num num-gold"><?= $i + 1 ?></span>
              <div><h4><?= e($title) ?></h4><p><?= e($desc) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="#apply" class="btn btn-gold" style="margin-top:26px; position:relative; z-index:1;">Become an Affiliate <span class="btn-arrow">→</span></a>
      </div>
      <div class="how-panel panel-creators reveal reveal-delay-2">
        <span class="how-panel-tag tag-gold">📊 Your Earnings</span>
        <p class="how-panel-desc" style="margin-top:24px; position:relative; z-index:1;">
          See what 2% commission looks like in practice. Say your link drives <?= $exampleReferredSalesCount ?> course purchases this month, averaging <?= e(format_money($exampleReferredSalePrice)) ?> each:
        </p>
        <div class="earnings-example">
          <div class="earnings-row"><span>Sales referred (<?= $exampleReferredSalesCount ?> &times; <?= e(format_money($exampleReferredSalePrice)) ?>)</span><strong><?= e(format_money($exampleReferredGross)) ?></strong></div>
          <div class="earnings-row earnings-total"><span>You earn (2% commission)</span><strong><?= e(format_money($exampleAffiliateCommission)) ?></strong></div>
        </div>
        <p class="small muted" style="margin-top:14px; position:relative; z-index:1;">Paid straight to your MTN or Airtel Mobile Money — no waiting for a payout cycle.</p>
      </div>
    </div>
  </div>
</div>

<!-- Final CTA -->
<section class="feature-cta-section" style="background-image: url('<?= e(versioned_asset('assets/img/abt-reading-growth.jpg')) ?>');">
  <div class="container">
    <div class="cta-panel-premium reveal">
      <span class="eyebrow" style="background:rgba(255,255,255,0.1); color:var(--gold);">Your Network Has Value</span>
      <h2 class="h2" style="margin-top:14px; color:#fff;">Ready to Earn by Sharing What You Already Love?</h2>
      <p style="margin-top:12px; color:rgba(255,255,255,0.7); max-width:520px; margin-left:auto; margin-right:auto; line-height:1.7;">Join affiliates across East Africa already earning from their community on Obin Academy — no cost to join, no cap on referrals, just a transparent 2% commission paid instantly.</p>
      <div class="row gap-2" style="justify-content:center; margin-top:28px;">
        <a href="#apply" class="btn btn-gold btn-lg">Apply Now <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </div>
</section>

<!-- Apply -->
<div class="section" id="apply" style="background:var(--surface);">
  <div class="container" style="max-width:560px;">
    <div class="text-center reveal" style="margin:0 auto 28px;">
      <span class="eyebrow">Start Your Application</span>
      <h2 class="h2" style="margin-top:10px;">Apply to Become an Affiliate</h2>
    </div>
    <?php if ($myAffiliate && $myAffiliate['status'] === 'ACTIVE'): ?>
      <div class="card card-pad" style="text-align:center;">
        <p>You're already an affiliate partner.</p>
        <a href="<?= e(base_url('dashboard/affiliate.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Go to Affiliate Dashboard</a>
      </div>
    <?php elseif ($submitted || ($myApplication && $myApplication['status'] === 'PENDING')): ?>
      <div class="alert alert-success">Your application has been submitted and is pending review. We'll email you once it's decided.</div>
    <?php else: ?>
      <?php if ($myApplication && $myApplication['status'] === 'REJECTED'): ?>
        <div class="alert alert-error">Your last application was not approved<?= $myApplication['rejection_reason'] ? ': ' . e($myApplication['rejection_reason']) : '.' ?> You're welcome to apply again below.</div>
      <?php endif; ?>
      <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

      <?php if (!$user): ?>
        <div class="card card-pad" style="text-align:center;">
          <p class="muted">Log in or create an account first to apply.</p>
          <div class="row gap-2 center" style="margin-top:14px;">
            <a href="<?= e(base_url('login.php?redirect=/become-affiliate.php')) ?>" class="btn btn-outline">Log In</a>
            <a href="<?= e(base_url('signup.php?redirect=/become-affiliate.php')) ?>" class="btn btn-primary">Sign Up</a>
          </div>
        </div>
      <?php else: ?>
        <form method="post" class="card card-pad">
          <?= csrf_field() ?>
          <div class="field"><label for="motivation">How do you plan to share Obin Academy?</label><textarea id="motivation" name="motivation" rows="4" required placeholder="e.g. My WhatsApp community, social media following, church group, students I mentor..."><?= e($myApplication['motivation'] ?? '') ?></textarea></div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">Submit Application</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
