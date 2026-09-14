<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$stats = get_platform_stats();
$testimonials = array_slice(get_published_testimonials(), 0, 3);

// A curated highlight, not the full list — keeps the homepage from feeling
// crowded. The complete set lives on skills.php. Slugs point at real
// categories so every tile here actually returns courses (once subscribed).
$industries = [
    ['Finance', 'finance', '💰', 'gold'], ['Business', 'business', '💼', 'blue'], ['Artificial Intelligence', 'artificial-intelligence', '🤖', 'purple'],
    ['Technology', 'technology-software-development', '💻', 'cyan'], ['Marketing', 'marketing-digital-marketing', '📣', 'pink'],
    ['Design', 'design-creative', '🎨', 'orange'], ['Ecommerce', 'ecommerce', '🛒', 'emerald'], ['Education', 'education-teaching', '🎓', 'indigo'],
];

$audiences = [
    ['🎓', '#2563eb', 'Students', "Build a real, practical skill before you graduate — something a certificate alone won't teach you."],
    ['💼', '#f5b301', 'Employees', 'Learn a new skill around your job — finance, marketing, or tech — without quitting to go back to school.'],
    ['🚀', '#10b981', 'Entrepreneurs', 'Run your business better — accounting, ecommerce, and marketing courses built for how you actually sell.'],
    ['🔍', '#8b5cf6', 'Job Seekers', 'Add a skill employers are hiring for, and back it up with a certificate when you finish.'],
];

$included = [
    ['📚', '#2563eb', 'The Full Catalog', 'Every course on Obin Academy, from every creator, in every category — nothing held back for a higher tier.'],
    ['✨', '#f5b301', 'New Courses Included', "When a creator publishes something new, it's already part of your subscription — no upgrade needed."],
    ['📱', '#10b981', 'Mobile Money Checkout', 'Subscribe and renew with MTN or Airtel — no card required, built for how East Africa actually pays.'],
    ['💻', '#06b6d4', 'Learn On Any Device', 'Stream video lessons and read PDFs from your phone, tablet, or laptop — pick up right where you left off.'],
    ['🏆', '#f97316', 'Certificate of Completion', 'Finish a course and get a shareable certificate — real proof of the skill, not just a stamp for finishing.'],
    ['💬', '#8b5cf6', 'Ask Questions, Get Answers', "Comment directly on any lesson — creators and fellow learners weigh in, so you're never stuck alone."],
];

$pageTitle = 'Obin Academy — Learn New Skills, Teach What You Know';
$pageDescription = "One subscription unlocks every course on Obin Academy — Finance, AI, Business, Tech and more — taught by East Africa's best creators. Pay instantly with MTN or Airtel Mobile Money.";
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            'name' => 'Obin Academy',
            'url' => base_url('index.php'),
            'logo' => base_url('apple-touch-icon.png'),
        ],
        [
            '@type' => 'WebSite',
            'name' => 'Obin Academy',
            'url' => base_url('index.php'),
        ],
    ],
];
require __DIR__ . '/includes/header.php';
?>

<section class="course-hero course-hero-centered">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container">
    <span class="pill reveal"><?php dash_icon('sparkle'); ?>Skills That Pay You Back</span>
    <h1 class="reveal reveal-delay-1">Learn What Actually Grows Your Income.</h1>
    <p class="summary reveal reveal-delay-2">One subscription unlocks every course on Obin Academy — Finance, AI, Business, Tech, and more — taught by East Africa's best creators. Pay by MTN or Airtel Mobile Money.</p>
    <div class="row gap-2 wrap reveal reveal-delay-3" style="justify-content:center; margin-top:30px;">
      <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-gold btn-lg">Get Started Now <span class="btn-arrow">→</span></a>
      <a href="#included" class="btn btn-outline-light btn-lg">See What's Included</a>
    </div>
    <p class="small reveal reveal-delay-4" style="margin-top:16px; color:rgba(255,255,255,0.55);">Cancel anytime. No long contracts, no hidden fees.</p>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<section class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:620px; margin:0 auto 12px;">
      <span class="eyebrow">Who It's For</span>
      <h2 class="h2" style="margin-top:10px;">Wherever You're Starting From</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Obin Academy isn't built for one type of learner — it's built for anyone in East Africa ready to build a skill that pays.</p>
    </div>
    <div class="center-grid" style="margin-top:36px;">
      <?php foreach ($audiences as $i => [$emoji, $tint, $title, $desc]): ?>
        <div class="value-card reveal reveal-delay-<?= min($i + 1, 5) ?>">
          <span class="icon-badge" style="--tint:<?= e($tint) ?>;"><?= $emoji ?></span>
          <h3><?= e($title) ?></h3>
          <p><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">Two Paths, One Choice</span>
      <h2 class="h2" style="margin-top:10px;">You Don't Need a Four-Year Detour</h2>
    </div>
    <div class="paths-grid">
      <div class="path-card path-old reveal">
        <div class="path-tag">The Old Way</div>
        <div class="path-amount">Years + Millions <span>in UGX</span></div>
        <p class="path-desc">Tuition, textbooks, and years of your time — for a general degree that may not teach you the specific, practical skill you actually need to earn.</p>
      </div>
      <div class="path-vs">VS</div>
      <div class="path-card path-new reveal reveal-delay-1">
        <div class="path-tag">Obin Academy</div>
        <div class="path-amount">One Subscription <span>/ month</span></div>
        <p class="path-desc">Every practical course on the platform, taught by people already doing the work — start learning today, pay by Mobile Money, cancel whenever you want.</p>
        <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-gold">See Plans <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </div>
</section>

<section class="section" id="included">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">What You Get</span>
      <h2 class="h2" style="margin-top:10px;">Everything Included, One Price</h2>
    </div>
    <div class="center-grid" style="margin-top:36px;">
      <?php foreach ($included as $i => [$emoji, $tint, $title, $desc]): ?>
        <div class="value-card reveal reveal-delay-<?= min($i % 5 + 1, 5) ?>">
          <span class="icon-badge" style="--tint:<?= e($tint) ?>;"><?= $emoji ?></span>
          <h3><?= e($title) ?></h3>
          <p><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">The Catalog</span>
      <h2 class="h2" style="margin-top:10px;">Skills Across Every Category</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Subscribe to unlock the full course list in each category below.</p>
    </div>
    <div class="industry-grid" style="margin-top:36px;">
      <?php foreach ($industries as [$name, $slug, $emoji, $glow]): ?>
        <a href="<?= e(base_url('courses/index.php?category=' . $slug)) ?>" class="industry-item industry-glow industry-glow-<?= e($glow) ?>">
          <span class="icon-wrap"><?= $emoji ?></span>
          <span class="label"><?= e($name) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:40px;">
      <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-primary btn-lg">Subscribe to Unlock the Full Catalog <span class="btn-arrow">→</span></a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">How It Works</span>
      <h2 class="h2" style="margin-top:10px;">Start Learning in Minutes</h2>
    </div>
    <div class="launch-steps">
      <div class="launch-step reveal reveal-delay-1">
        <span class="step-num">1</span>
        <h3>Create a Free Account</h3>
        <p>Sign up with just your name and email — takes less than a minute.</p>
      </div>
      <div class="launch-step reveal reveal-delay-2">
        <span class="step-num">2</span>
        <h3>Choose Go or Pro</h3>
        <p>Pick a plan and pay instantly with MTN or Airtel Mobile Money.</p>
      </div>
      <div class="launch-step reveal reveal-delay-3">
        <span class="step-num">3</span>
        <h3>Unlock the Full Catalog</h3>
        <p>Every course, every category, no extra charge — start immediately.</p>
      </div>
      <div class="launch-step reveal reveal-delay-4">
        <span class="step-num">4</span>
        <h3>Learn, Finish, Get Certified</h3>
        <p>Complete a course at your pace and earn a certificate to show for it.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($testimonials): ?>
  <section class="section testimonials-decor">
    <div class="container">
      <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
        <span class="eyebrow">From Real Learners</span>
        <h2 class="h2" style="margin-top:10px;">What Obin Academy Members Say</h2>
      </div>
      <div class="grid sm:grid-3" style="margin-top:36px; gap:20px;">
        <?php foreach ($testimonials as $i => $t): ?>
          <div class="testimonial-card reveal reveal-delay-<?= min($i + 1, 5) ?>">
            <span class="quote-mark">&ldquo;</span>
            <div class="rating-row">
              <span class="stars"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></span>
              <span class="rating-num"><?= number_format((float) $t['rating'], 1) ?></span>
            </div>
            <p class="quote"><?= e($t['quote']) ?></p>
            <div class="author">
              <div class="avatar"><?= e(mb_substr($t['author_name'], 0, 1)) ?></div>
              <div>
                <div class="name"><?= e($t['author_name']) ?></div>
                <?php if (!empty($t['author_headline'])): ?><div class="role"><?= e($t['author_headline']) ?></div><?php endif; ?>
                <div class="verified">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle></svg>
                  Verified Learner
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section">
  <div class="container cta-panel-premium reveal">
    <span class="eyebrow">Ready When You Are</span>
    <h2 class="h2" style="margin-top:14px;">Your Next Skill Is One Subscription Away.</h2>
    <p class="lede" style="margin:16px auto 0;">Join Obin Academy today and get instant access to every course on the platform.</p>
    <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-primary btn-lg" style="margin-top:26px;">Subscribe Now <span class="btn-arrow">→</span></a>
    <p class="small muted" style="margin-top:14px;">Pay with MTN or Airtel Mobile Money · Cancel anytime</p>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
