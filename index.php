<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$testimonials = array_slice(get_published_testimonials(), 0, 3);
$catalogPreview = get_featured_courses(6);

$audiences = [
    ['🎓', '#2563eb', 'Students', "Build a real, practical skill before you graduate — something a certificate alone won't teach you."],
    ['💼', '#f5b301', 'Employees', 'Learn a new skill around your job — finance, marketing, or tech — without quitting to go back to school.'],
    ['🚀', '#10b981', 'Entrepreneurs', 'Run your business better — accounting, ecommerce, and marketing courses built for how you actually sell.'],
    ['🔍', '#8b5cf6', 'Job Seekers', 'Add a skill employers are hiring for, and back it up with a certificate when you finish.'],
];

$included = [
    ['📚', '#2563eb', 'A Real Catalog to Choose From', 'Courses across Finance, AI, Business, Tech, and more, from real African creators — pick the one that actually matches what you need.'],
    ['💰', '#f5b301', 'Pay Once, Own It', 'No recurring charges — pay for a course once and keep access for as long as the creator set, some even for life.'],
    ['📱', '#10b981', 'Mobile Money Checkout', 'Pay instantly with MTN or Airtel — no card required, built for how East Africa actually pays.'],
    ['💻', '#06b6d4', 'Learn On Any Device', 'Stream video lessons and read PDFs from your phone, tablet, or laptop — pick up right where you left off.'],
    ['🏆', '#f97316', 'Certificate of Completion', 'Finish a course and get a shareable certificate — real proof of the skill, not just a stamp for finishing.'],
    ['💬', '#8b5cf6', 'Ask Questions, Get Answers', "Comment directly on any lesson — creators and fellow learners weigh in, so you're never stuck alone."],
];

$pageTitle = 'Obin Academy — Learn New Skills, Teach What You Know';
$pageDescription = "Buy only the courses you want on Obin Academy — Finance, AI, Business, Tech and more — taught by East Africa's best creators. Pay instantly with MTN or Airtel Mobile Money.";
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

<section class="course-hero home-hero home-hero-split">
  <div class="home-hero-grid" aria-hidden="true"></div>
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="home-hero-glow-2" aria-hidden="true"></div>
  <div class="container">
    <div class="home-hero-split-grid">
      <div class="founder-welcome-photo reveal">
        <div class="founder-photo-frame" aria-hidden="true"></div>
        <div class="founder-photo-glow" aria-hidden="true"></div>
        <div class="founder-photo-inner">
          <img src="<?= e(versioned_asset('assets/img/faceofbrand.jpeg')) ?>" alt="Obin Ivan, Founder &amp; CEO of Obin Academy">
          <div class="founder-photo-vignette" aria-hidden="true"></div>
          <div class="founder-photo-shine" aria-hidden="true"></div>
        </div>
        <span class="founder-badge">
          <span class="founder-badge-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span>
          Obin Ivan, Founder &amp; CEO
        </span>
      </div>
      <div class="home-hero-copy">
        <p class="founder-welcome-line reveal">Hi, I'm Obin Ivan — welcome to Obin Academy, the platform I built to help you learn a real skill and start earning, right from your phone.</p>
        <span class="pill reveal reveal-delay-1"><?php dash_icon('sparkle'); ?>Skills That Pay You Back</span>
        <div class="hero-quote reveal reveal-delay-2">
          <span class="hero-quote-mark-open" aria-hidden="true">
            <svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 36V22.7C0 9.1 7.9 1.1 19.9 0l1.6 5.4C13.7 7.4 9.6 12.6 9.1 19.6h10.4V36H0Z" fill="url(#heroQuoteGradOpen)"></path>
              <path d="M26.6 36V22.7C26.6 9.1 34.5 1.1 46.5 0l1.6 5.4c-7.8 2-11.9 7.2-12.4 14.2h10.4V36H26.6Z" fill="url(#heroQuoteGradOpen)"></path>
              <defs><linearGradient id="heroQuoteGradOpen" x1="0" y1="0" x2="48" y2="36" gradientUnits="userSpaceOnUse"><stop stop-color="#ffe49a"></stop><stop offset="1" stop-color="#f5b301"></stop></linearGradient></defs>
            </svg>
          </span>
          <h1 class="hero-quote-headline">The System Taught You to Chase Opportunities. We Teach You to Create Them.</h1>
          <p class="hero-quote-body">Millions spend years earning certificates, yet still struggle because they were never taught the skills today's economy rewards. Obin Academy equips you with practical, in-demand skills in AI, Business, Finance, Technology, Digital Marketing, Content Creation, Web Development, and more—taught by experienced African creators. Stop waiting for opportunity. Start building the skills that create it.</p>
          <span class="hero-quote-mark-close" aria-hidden="true">
            <svg viewBox="0 0 48 36" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0 36V22.7C0 9.1 7.9 1.1 19.9 0l1.6 5.4C13.7 7.4 9.6 12.6 9.1 19.6h10.4V36H0Z" fill="url(#heroQuoteGradClose)"></path>
              <path d="M26.6 36V22.7C26.6 9.1 34.5 1.1 46.5 0l1.6 5.4c-7.8 2-11.9 7.2-12.4 14.2h10.4V36H26.6Z" fill="url(#heroQuoteGradClose)"></path>
              <defs><linearGradient id="heroQuoteGradClose" x1="0" y1="0" x2="48" y2="36" gradientUnits="userSpaceOnUse"><stop stop-color="#ffe49a"></stop><stop offset="1" stop-color="#f5b301"></stop></linearGradient></defs>
            </svg>
          </span>
        </div>
        <div class="row gap-2 wrap reveal reveal-delay-4" style="margin-top:30px;">
          <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-gold btn-lg">Browse Courses <span class="btn-arrow">→</span></a>
          <a href="#included" class="btn btn-outline-light btn-lg">See What's Included</a>
        </div>
        <p class="small reveal reveal-delay-5" style="margin-top:16px; color:rgba(255,255,255,0.55);">Pay only for the course you want. No subscriptions, no hidden fees.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($catalogPreview): ?>
<section class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">The Catalog</span>
      <h2 class="h2" style="margin-top:10px;">See What's Waiting Inside</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">A preview of real courses on Obin Academy — browse the full catalog free, then pay only for the one you want.</p>
    </div>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:36px;">
      <?php foreach ($catalogPreview as $c): render_course_card($c); endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:40px;">
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-lg">Browse Courses <span class="btn-arrow">→</span></a>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:620px; margin:0 auto 12px;">
      <span class="eyebrow">Who It's For</span>
      <h2 class="h2" style="margin-top:10px;">Wherever You're Starting From</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Obin Academy isn't built for one type of learner — it's built for anyone in East Africa ready to build a skill that pays.</p>
    </div>
    <div class="audience-grid" style="margin-top:36px;">
      <?php foreach ($audiences as $i => [$emoji, $tint, $title, $desc]): ?>
        <div class="audience-card reveal reveal-delay-<?= min($i + 1, 5) ?>">
          <span class="audience-icon-circle"><?= $emoji ?></span>
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
        <div class="path-amount">Years + Millions <span>in Traditional Education</span></div>
        <p class="path-desc">Tuition, textbooks, and years of your time — for a general degree that may not teach you the specific, practical skill you actually need to earn.</p>
        <span class="path-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m4.9 4.9 14.2 14.2"></path></svg>Stuck With the Same Outcome</span>
      </div>
      <div class="path-vs">VS</div>
      <div class="path-card path-new reveal reveal-delay-1">
        <div class="path-tag">Obin Academy</div>
        <div class="path-amount">One Course <span>at a time</span></div>
        <p class="path-desc">Pay once for the specific skill you actually need, taught by people already doing the work — start learning today by Mobile Money, own it for good.</p>
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-gold btn-block btn-lg">Browse Courses <span class="btn-arrow">→</span></a>
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
          <?php if ($emoji === '📱'): ?>
            <span class="icon-badge icon-badge-logos" style="--tint:<?= e($tint) ?>;">
              <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN"></span>
              <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel"></span>
            </span>
          <?php else: ?>
            <span class="icon-badge" style="--tint:<?= e($tint) ?>;"><?= $emoji ?></span>
          <?php endif; ?>
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
        <h3>Pick a Course</h3>
        <p>Find the one skill you actually need and pay for just that course.</p>
      </div>
      <div class="launch-step reveal reveal-delay-3">
        <span class="step-num">3</span>
        <h3>Pay by Mobile Money</h3>
        <p>MTN or Airtel, approved on your phone in seconds — start immediately.</p>
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
      <div class="stories-slider reveal" style="margin-top:36px;" data-stories-slider>
        <div class="stories-track" data-stories-track>
          <?php foreach ($testimonials as $t): ?>
            <div class="testimonial-card">
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
        <?php if (count($testimonials) > 1): ?>
          <button type="button" class="stories-nav prev" data-stories-prev aria-label="Previous story">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
          </button>
          <button type="button" class="stories-nav next" data-stories-next aria-label="Next story">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
          </button>
          <div class="stories-dots" data-stories-dots></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="home-final-cta reveal">
      <div class="home-final-cta-grid" aria-hidden="true"></div>
      <div class="home-final-cta-glow" aria-hidden="true"></div>
      <div class="home-final-cta-inner">
        <div class="home-final-cta-rule" aria-hidden="true"></div>
        <span class="home-final-cta-eyebrow">Ready When You Are</span>
        <h2>Your Next Skill Is One Course Away.</h2>
        <p class="home-final-cta-lede">Join Obin Academy today and buy the exact course you need to get started.</p>
        <div class="home-final-cta-btn-wrap">
          <a href="<?= e(base_url('courses/index.php')) ?>" class="home-final-cta-btn">Browse Courses <span class="btn-arrow">→</span></a>
        </div>
        <div class="home-final-cta-trust">
          <div class="home-final-cta-trust-logos">
            <span class="home-final-cta-trust-logo"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN"></span>
            <span class="home-final-cta-trust-logo"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel"></span>
          </div>
          <span class="home-final-cta-trust-sep"></span>
          <span class="txt">Pay with MTN or Airtel Mobile Money · Own it for good</span>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
