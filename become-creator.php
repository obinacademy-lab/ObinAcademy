<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$user = current_user();
$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user) {
        redirect('/login.php?redirect=/become-creator.php');
    }
    if (in_array($user['role'], ['CREATOR', 'ADMIN'], true)) {
        $errors[] = 'You already have creator access.';
    } else {
        $expertise = post('expertise');
        $motivation = post('motivation');
        if (strlen($expertise) < 10) $errors[] = "Tell us what you'd like to teach (at least 10 characters).";
        if (strlen($motivation) < 20) $errors[] = 'Tell us a bit more about why you want to teach (at least 20 characters).';

        if (!$errors) {
            $existing = db_one('SELECT id FROM creator_applications WHERE user_id = ?', [$user['id']]);
            if ($existing) {
                db_run("UPDATE creator_applications SET status='PENDING', expertise=?, motivation=?, rejection_reason=NULL, reviewed_at=NULL WHERE id=?", [$expertise, $motivation, $existing['id']]);
            } else {
                db_insert('INSERT INTO creator_applications (user_id, expertise, motivation) VALUES (?, ?, ?)', [$user['id'], $expertise, $motivation]);
            }
            $submitted = true;
        }
    }
}

$myApplication = $user ? db_one('SELECT * FROM creator_applications WHERE user_id = ?', [$user['id']]) : null;

// Real social proof, not a marketing round number — same "actually paid
// out" definition used on the admin financial pages (approved withdrawals,
// not gross accrued earnings).
$paidToCreators = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE status='APPROVED' AND payee_type='CREATOR'")['n'] ?? 0);
$stats = get_platform_stats();

// Same copy as about.php's own "Become a Creator" section — kept in sync
// by hand (this codebase doesn't share content arrays across pages), not
// duplicated by accident.
$creatorBenefits = [
    ['♾️', '#2563eb', 'Unlimited Courses', 'Create as many online courses as you want — there\'s no cap on what you can teach.'],
    ['🎥', '#f5b301', 'Video & Materials', 'Upload video lessons, PDFs, and learning materials your students can revisit anytime.'],
    ['💵', '#10b981', 'Set Your Own Pricing', 'You decide what your expertise is worth — full control over every course price.'],
    ['✨', '#8b5cf6', 'Build Your Brand', 'Grow a personal brand and reputation as a trusted expert in your field.'],
    ['🌍', '#06b6d4', 'Reach Learners Anywhere', 'Publish once and reach learners across Africa and beyond.'],
    ['💰', '#f97316', 'Earn From Every Sale', 'Turn your knowledge into a real, recurring income stream.'],
    ['📊', '#ec4899', 'Track Your Growth', 'Monitor enrollments, sales, and performance from your creator dashboard.'],
    ['🤝', '#6366f1', 'Build a Community', 'Cultivate your own learning community around the skills you teach.'],
];
$creatorSteps = [
    ['Apply to Create Your School', 'Tell us about your expertise and submit your application for review.'],
    ['Build Your First Course', 'Use the Creator Dashboard to add modules, upload video or PDF lessons, and choose your pricing.'],
    ['Publish to Your School', 'Once approved, your course goes live on your own school page for every learner to find.'],
    ['Earn From Every Sale', 'Keep 90% of every sale, paid straight to your mobile money.'],
];

// Illustrative only, not tied to any real creator's numbers — a plain
// example so "keep 90%" means something concrete on the page.
$exampleCoursePrice = 50000.0;
$exampleSalesCount = 50;
$exampleGross = $exampleCoursePrice * $exampleSalesCount;
$examplePlatformFee = $exampleGross * 0.10;
$exampleCreatorKeep = $exampleGross - $examplePlatformFee;

$pageTitle = 'Create Your School — Obin Academy';
$pageDescription = 'Turn your knowledge into income. Create your own school on Obin Academy — publish courses, choose your pricing, and keep 90% of every sale, paid instantly to mobile money.';
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="max-width:900px; text-align:center;">
    <span class="pill">Create Your Own School</span>
    <h1 style="margin-top:14px;">Turn What You Know Into Income</h1>
    <p class="summary" style="margin:14px auto 0;">Start your own school on Obin Academy and share your expertise with thousands of learners across East Africa. Upload video or PDF courses, get paid instantly via mobile money, and keep 90% of every sale.</p>

    <?php if ($paidToCreators > 0): ?>
      <div class="hero-trust-stat">
        <?php dash_icon('banknote'); ?>
        <span data-count-up data-count-value="<?= (int) round($paidToCreators) ?>" data-count-prefix="UGX " data-count-compact data-count-suffix="">UGX 0</span> already paid out to creators
      </div>
    <?php endif; ?>

    <div class="row gap-2" style="justify-content:center; flex-wrap:wrap; margin-top:28px;">
      <a href="#apply" class="btn btn-gold shine">Create Your School <span class="btn-arrow">→</span></a>
      <a href="#how-it-works" class="btn btn-outline">See How It Works</a>
    </div>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<!-- Why teach here -->
<div class="section">
  <div class="container">
    <div class="why-exist-grid">
      <div class="reveal">
        <span class="eyebrow">Why Start a School on Obin Academy</span>
        <h2 class="h2" style="margin-top:14px;">Your Knowledge Deserves to Be Paid Instantly</h2>
        <p class="lede" style="margin-top:16px; max-width:none; font-size:16.5px; line-height:1.75; color:var(--muted);">
          Most platforms make you wait weeks for a payout. On Obin Academy, every sale from your school is settled straight to your MTN or Airtel Mobile Money — no bank account, no delayed payout cycles, no chasing invoices. You choose how to charge — a one-time price per course, or a monthly subscription price per course — and we handle the platform, the payments, and the learners.
        </p>
        <div class="mission-callout" style="margin-top:24px;">
          <span class="tag">The Creator Split</span>
          <p>You keep 90% of every sale. Obin Academy takes a transparent 10% platform fee — nothing hidden, no surprise deductions.</p>
        </div>
      </div>
      <div class="why-exist-photo reveal reveal-delay-2">
        <img src="<?= e(versioned_asset('assets/img/abt-finance-creator.jpg')) ?>" alt="A creator preparing a course to teach on Obin Academy">
      </div>
    </div>
  </div>
</div>

<!-- What you get -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:620px; margin:0 auto 40px;">
      <span class="eyebrow">What You Get</span>
      <h2 class="h2" style="margin-top:10px;">Everything You Need to Run Your Own School</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">From unlimited course uploads to instant mobile money payouts — Obin Academy gives you everything to build a real teaching business.</p>
    </div>
    <div class="center-grid">
      <?php foreach ($creatorBenefits as $i => [$emoji, $tint, $title, $desc]): ?>
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
        <span class="how-panel-tag tag-gold">💰 How It Works</span>
        <div class="stack gap-3" style="margin-top:24px; position:relative; z-index:1;">
          <?php foreach ($creatorSteps as $i => [$title, $desc]): ?>
            <div class="step-row">
              <span class="step-num num-gold"><?= $i + 1 ?></span>
              <div><h4><?= e($title) ?></h4><p><?= e($desc) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="#apply" class="btn btn-gold" style="margin-top:26px; position:relative; z-index:1;">Create Your School <span class="btn-arrow">→</span></a>
      </div>
      <div class="how-panel panel-creators reveal reveal-delay-2">
        <span class="how-panel-tag tag-gold">📊 Your Earnings</span>
        <p class="how-panel-desc" style="margin-top:24px; position:relative; z-index:1;">
          See what the 90/10 split looks like in practice. Say you price your course at <?= e(format_money($exampleCoursePrice)) ?> and 50 learners buy it this month:
        </p>
        <div class="earnings-example">
          <div class="earnings-row"><span>Gross sales (<?= $exampleSalesCount ?> &times; <?= e(format_money($exampleCoursePrice)) ?>)</span><strong><?= e(format_money($exampleGross)) ?></strong></div>
          <div class="earnings-row"><span>Obin Academy fee (10%)</span><strong>&minus; <?= e(format_money($examplePlatformFee)) ?></strong></div>
          <div class="earnings-row earnings-total"><span>You keep</span><strong><?= e(format_money($exampleCreatorKeep)) ?></strong></div>
        </div>
        <p class="small muted" style="margin-top:14px; position:relative; z-index:1;">Paid straight to your MTN or Airtel Mobile Money — no waiting for a payout cycle.</p>
      </div>
    </div>
  </div>
</div>

<!-- Final CTA -->
<section class="feature-cta-section" style="background-image: url('<?= e(versioned_asset('assets/img/abt-creator-earnings.jpg')) ?>');">
  <div class="container">
    <div class="cta-panel-premium reveal">
      <span class="eyebrow" style="background:rgba(255,255,255,0.1); color:var(--gold);">Your Knowledge Has Value</span>
      <h2 class="h2" style="margin-top:14px; color:#fff;">Ready to Turn What You Know Into Income?</h2>
      <p style="margin-top:12px; color:rgba(255,255,255,0.7); max-width:520px; margin-left:auto; margin-right:auto; line-height:1.7;">Join creators across East Africa already earning from their expertise on Obin Academy — no upfront cost, no monthly fees, just a transparent 90/10 split paid instantly.</p>
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
      <h2 class="h2" style="margin-top:10px;">Apply to Create Your School</h2>
    </div>
    <?php if ($user && in_array($user['role'], ['CREATOR', 'ADMIN'], true)): ?>
      <div class="card card-pad" style="text-align:center;">
        <p>You already have creator access.</p>
        <a href="<?= e(base_url('dashboard/creator/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Go to Creator Dashboard</a>
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
            <a href="<?= e(base_url('login.php?redirect=/become-creator.php')) ?>" class="btn btn-outline">Log In</a>
            <a href="<?= e(base_url('signup.php?redirect=/become-creator.php')) ?>" class="btn btn-primary">Sign Up</a>
          </div>
        </div>
      <?php else: ?>
        <form method="post" class="card card-pad">
          <?= csrf_field() ?>
          <div class="field"><label for="expertise">What would you like to teach?</label><textarea id="expertise" name="expertise" rows="3" required placeholder="e.g. Personal finance, digital marketing, farming techniques..."><?= e($myApplication['expertise'] ?? '') ?></textarea></div>
          <div class="field"><label for="motivation">Why do you want to teach on Obin Academy?</label><textarea id="motivation" name="motivation" rows="4" required placeholder="Tell us about your experience and what makes you a great teacher."><?= e($myApplication['motivation'] ?? '') ?></textarea></div>
          <button type="submit" class="btn btn-primary btn-block btn-lg">Submit Application</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
