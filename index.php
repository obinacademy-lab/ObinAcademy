<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$courses = get_featured_courses(6);
$stats = get_platform_stats();
$rating = get_platform_rating();
$testimonials = array_slice(get_published_testimonials(), 0, 3);
$spotlightQuote = $testimonials[0] ?? null;

$GLOW_COLORS = ['blue', 'cyan', 'purple', 'pink', 'gold', 'emerald', 'orange', 'indigo'];

$heroSlides = [
    ['hero-couch-learner.jpg', 'A learner studying online from her couch on a laptop'],
    ['hero-slide-1.jpg', 'A creator recording a video lesson for Obin Academy'],
    ['hero-slide-2.jpg', 'A learner working through a course on her laptop'],
    ['hero-slide-3.jpg', 'A learner practicing new skills from home'],
    ['hero-slide-4.jpg', 'A professional analyzing course data on multiple screens'],
    ['hero-slide-5.jpg', 'A learner studying outdoors on her laptop'],
];

// A curated highlight, not the full list — keeps the homepage from feeling
// crowded. The complete set lives on skills.php. Slugs point at real
// categories so every tile here actually returns courses.
$industries = [
    ['Finance', 'finance', '💰'], ['Business', 'business', '💼'], ['Artificial Intelligence', 'artificial-intelligence', '🤖'],
    ['Technology', 'technology-software-development', '💻'], ['Marketing', 'marketing-digital-marketing', '📣'],
    ['Design', 'design-creative', '🎨'], ['Ecommerce', 'ecommerce', '🛒'], ['Education', 'education-teaching', '🎓'],
];

$features = [
    ['📚', 'Real-World Skills', 'Access high-quality courses across every industry, taught by working professionals with practical experience.'],
    ['👥', 'Learn On Your Terms', 'Study at your own pace through video lessons and resources you can revisit any time.'],
    ['🏆', 'Certificates', 'Earn a certificate of completion for every course to showcase your new skills and advance your career.'],
];

$learnerSteps = [
    ['Browse Courses', 'Explore courses across Finance, Tech, Business, and more — filter by category, price, or rating to find the right fit.'],
    ['Enroll & Pay Instantly', 'Pay securely with MTN or Airtel Mobile Money and get instant access — no card, no waiting.'],
    ['Learn at Your Own Pace', 'Watch video lessons and download materials whenever it suits you, on any device.'],
    ['Earn Your Certificate', 'Complete the course and receive a Certificate of Completion to showcase your new skill.'],
];
$creatorSteps = [
    ['Apply to Become a Creator', 'Tell us about your expertise and submit your application for review.'],
    ['Build Your Course', 'Use the Creator Dashboard to add modules, upload video or PDF lessons, and set your price.'],
    ['Publish to the Marketplace', 'Once approved, your course goes live to every learner on Obin Academy.'],
    ['Earn From Every Sale', 'Keep 90% of every sale, paid straight to your mobile money.'],
];

// A short, homepage-specific subset — the full FAQ list lives on contact.php.
$homeFaqs = [
    ['How fast can I start learning?', 'Enroll and pay with MTN or Airtel Mobile Money, and you get instant access to the course — no waiting, no card required.'],
    ['Do I need a laptop to learn?', "No. Obin Academy works in any phone, tablet, or laptop browser, so you can learn from wherever you already are."],
    ['How much can creators earn?', 'Creators set their own price and keep 90% of every sale. Obin Academy takes a transparent 10% platform fee — nothing hidden.'],
    ['Will I get a certificate?', 'Yes — completing all lessons in a course automatically unlocks a Certificate of Completion in your learner dashboard.'],
];

$pageTitle = 'Obin Academy — Learn New Skills, Teach What You Know';
$pageDescription = "Learn practical skills in Finance, Tech, Business and more from Africa's best creators — or turn your own expertise into courses. Pay instantly with MTN or Airtel Mobile Money.";
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            'name' => 'Obin Academy',
            'url' => base_url('index.php'),
            'logo' => base_url('assets/img/hero-couch-learner.jpg'),
        ],
        [
            '@type' => 'WebSite',
            'name' => 'Obin Academy',
            'url' => base_url('index.php'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => base_url('courses/index.php') . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ],
];
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <div class="hero-row">
      <div class="hero-inner">
        <span class="hero-badge animate-blink-badge">The Knowledge Marketplace For Everyone</span>
        <h1 class="hero-headline">Learn From Africa's <span class="lock animate-blink-text">Best Creators</span>.</h1>
        <p class="tag">Finance, Tech, Business Classes etc &mdash; Paid Instantly With <span class="money">MTN</span> or <span class="money airtel">Airtel Money</span></p>
        <p class="desc">Discover practical knowledge from creators around the world&mdash;or turn your own expertise into courses and build your learning community on Obin Academy.</p>
        <div class="actions">
          <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-lg">Start Learning →</a>
          <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-gold btn-lg">▶ Become a Creator</a>
        </div>
      </div>

      <div class="hero-visual">
        <div class="hero-photo" data-hero-slides data-interval="5000">
          <?php foreach ($heroSlides as $i => [$file, $alt]): ?>
            <img src="<?= e(base_url('assets/img/' . $file)) ?>" alt="<?= e($alt) ?>" class="<?= $i === 0 ? 'active' : '' ?>" <?= $i === 0 ? '' : 'loading="lazy"' ?>>
          <?php endforeach; ?>
        </div>

        <div class="hero-card">
          <span class="hero-card-title"><span class="live-dot"></span>Platform Snapshot</span>
          <div class="hero-card-stats">
            <div class="hc-stat"><div class="hc-value" data-count-up data-count-value="<?= (int) $stats['course_count'] ?>">0+</div><div class="hc-label">Courses</div></div>
            <div class="hc-stat"><div class="hc-value" data-count-up data-count-value="<?= (int) $stats['learner_count'] ?>">0+</div><div class="hc-label">Learners</div></div>
            <div class="hc-stat"><div class="hc-value" data-count-up data-count-value="<?= (int) $stats['creator_count'] ?>">0+</div><div class="hc-label">Creators</div></div>
          </div>
          <hr>
          <?php if ($rating['count'] > 0): ?>
            <div class="hero-card-rating">
              <span class="stars"><?= str_repeat('★', (int) round($rating['avg'])) . str_repeat('☆', 5 - (int) round($rating['avg'])) ?></span>
              <?= number_format($rating['avg'], 1) ?>/5 average rating
            </div>
          <?php endif; ?>
          <?php if ($spotlightQuote): ?>
            <div class="hero-card-quote">
              <p>&ldquo;<?= e(mb_strimwidth($spotlightQuote['quote'], 0, 110, '…')) ?>&rdquo;</p>
              <span class="who"><?= e($spotlightQuote['author_name']) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="trust-bar">
  <div class="container trust-bar-inner">
    <div class="trust-item" style="--tint:#10b981;">
      <span class="icon-badge">🔒</span>
      <span class="underline"></span>
      <span class="label">Secure Mobile Money Payments</span>
    </div>
    <div class="trust-item" style="--tint:#f5b301;">
      <span class="icon-badge">🎓</span>
      <span class="underline"></span>
      <span class="label">Certificate of Completion</span>
    </div>
    <div class="trust-item" style="--tint:#f97316;">
      <span class="icon-badge">⚡</span>
      <span class="underline"></span>
      <span class="label">Instant Access After Payment</span>
    </div>
    <div class="trust-item" style="--tint:#3b82f6;">
      <span class="icon-badge">🌍</span>
      <span class="underline"></span>
      <span class="label">Built for Africa</span>
    </div>
  </div>
</section>

<section class="section" style="padding-bottom:56px; border-bottom: 1px solid var(--border);">
  <div class="container">
    <div class="text-center" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">Skills Across Every Industry</span>
      <h2 class="h2" style="margin-top:10px;">Find Your Field</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">A few of the industries growing fastest on Obin Academy right now.</p>
    </div>
    <div class="industry-grid">
      <?php foreach ($industries as $i => [$name, $slug, $emoji]): ?>
        <a href="<?= e(base_url('courses/index.php?category=' . $slug)) ?>" class="industry-item industry-glow industry-glow-<?= $GLOW_COLORS[$i % count($GLOW_COLORS)] ?>">
          <span class="icon-wrap"><?= $emoji ?></span>
          <span class="label"><?= e($name) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:28px;">
      <a href="<?= e(base_url('skills.php')) ?>" class="chip" style="padding:11px 22px; font-size:13px;">View All 26 Skills <span class="btn-arrow">→</span></a>
    </div>
  </div>
</section>

<section class="section" style="background: var(--surface);">
  <div class="container">
    <div class="row between wrap gap-3" style="align-items:flex-end; margin-bottom: 36px;">
      <div>
        <div class="row gap-2 wrap" style="align-items:center;">
          <span class="eyebrow">Top Categories</span>
          <?php if ((int) $stats['course_count'] > 0): ?>
            <span class="live-pill"><span class="live-dot"></span><?= (int) $stats['course_count'] ?> course<?= (int) $stats['course_count'] === 1 ? '' : 's' ?> live now</span>
          <?php endif; ?>
        </div>
        <h2 class="h2" style="margin-top:10px;">Popular Courses</h2>
        <p class="lede" style="margin-top:10px;">Explore courses in Finance, AI, Business, Health, Agriculture, Ecommerce, and more — taught by creators with real industry experience.</p>
      </div>
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary">Explore Courses <span class="btn-arrow">→</span></a>
    </div>

    <?php if ($courses): ?>
      <div class="grid sm:grid-2 lg:grid-3">
        <?php foreach ($courses as $c) render_course_card($c); ?>
      </div>
    <?php else: ?>
      <div class="card" style="padding:48px; text-align:center; border-style:dashed; color:var(--muted);">No courses published yet. Check back soon.</div>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:580px; margin:0 auto 40px;">
      <span class="eyebrow">Simple By Design</span>
      <h2 class="h2" style="margin-top:10px;">How Obin Academy Works</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Whether you're here to learn or to teach, getting started takes just a few steps.</p>
    </div>
    <div class="grid lg:grid-2" style="gap:28px;">
      <div class="how-panel panel-learners reveal">
        <span class="how-panel-tag">🎓 For Learners</span>
        <div class="stack gap-3" style="margin-top:24px; position:relative; z-index:1;">
          <?php foreach ($learnerSteps as $i => [$title, $desc]): ?>
            <div class="step-row">
              <span class="step-num"><?= $i + 1 ?></span>
              <div><h4><?= e($title) ?></h4><p><?= e($desc) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:26px; position:relative; z-index:1;">Explore Courses <span class="btn-arrow">→</span></a>
      </div>
      <div class="how-panel panel-creators reveal reveal-delay-2">
        <span class="how-panel-tag tag-gold">💰 For Creators</span>
        <div class="stack gap-3" style="margin-top:24px; position:relative; z-index:1;">
          <?php foreach ($creatorSteps as $i => [$title, $desc]): ?>
            <div class="step-row">
              <span class="step-num num-gold"><?= $i + 1 ?></span>
              <div><h4><?= e($title) ?></h4><p><?= e($desc) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-gold" style="margin-top:26px; position:relative; z-index:1;">Become a Creator <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </div>
</section>

<?php if ($testimonials): ?>
<section class="section testimonials-decor">
  <div class="container">
    <div class="text-center" style="max-width:560px; margin:0 auto 40px;">
      <span class="eyebrow">Real Results</span>
      <h2 class="h2" style="margin-top:10px;">What Our Learners Say</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Real stories from people building real skills — and real income — on Obin Academy.</p>
    </div>
    <div class="grid sm:grid-2 lg:grid-3">
      <?php foreach ($testimonials as $t): ?>
        <div class="testimonial-card">
          <span class="quote-mark">&ldquo;</span>
          <div class="rating-row">
            <span class="stars"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></span>
            <span class="rating-num"><?= number_format((float) $t['rating'], 1) ?></span>
          </div>
          <p class="quote"><?= e($t['quote']) ?></p>
          <div class="author">
            <div class="avatar">
              <?php if (!empty($t['author_avatar_url'])): ?>
                <img src="<?= e(asset_src($t['author_avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($t['author_name'], 0, 1)) ?><?php endif; ?>
            </div>
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
    <div class="text-center" style="margin-top:36px;">
      <a href="<?= e(base_url('stories.php')) ?>" class="btn btn-dark">Read More Stories →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section feature-cta-section" style="background-image: url('<?= e(versioned_asset('assets/img/hero-couch-learner.jpg')) ?>');">
  <div class="container">
    <div class="grid lg:grid-3" style="gap: 40px;">
      <?php foreach ($features as [$emoji, $title, $desc]): ?>
        <div class="feature-block">
          <span class="bar"></span>
          <div class="icon"><?= $emoji ?></div>
          <h3><?= e($title) ?></h3>
          <p><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="cta-panel" style="margin-top:56px;">
      <div>
        <span class="eyebrow">Study at Your Own Pace</span>
        <h3 class="h3" style="margin-top:10px; max-width: 420px;">Boost Your Career by Learning Skills in High Demand</h3>
      </div>
      <a href="<?= e(base_url('signup.php')) ?>" class="btn btn-primary btn-lg">Get Started →</a>
    </div>
  </div>
</section>

<div class="section">
  <div class="container" style="max-width:760px;">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">FAQ</span>
      <h2 class="h2" style="margin-top:10px;">Questions? We've Got Answers</h2>
    </div>
    <div class="faq-list reveal">
      <?php foreach ($homeFaqs as $i => [$q, $a]): ?>
        <div class="faq-item">
          <button type="button" class="faq-question" aria-expanded="false" aria-controls="home-faq-panel-<?= $i ?>" id="home-faq-q-<?= $i ?>">
            <span><?= e($q) ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="faq-chevron" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
          </button>
          <div class="faq-answer" id="home-faq-panel-<?= $i ?>" role="region" aria-labelledby="home-faq-q-<?= $i ?>">
            <div class="faq-answer-inner"><p><?= e($a) ?></p></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:28px;">
      <a href="<?= e(base_url('contact.php')) ?>" class="chip" style="padding:11px 22px; font-size:13px;">See All FAQs <span class="btn-arrow">→</span></a>
    </div>
  </div>
</div>

<div class="section" style="background: var(--surface);">
  <div class="container" style="max-width:640px;">
    <div class="newsletter-panel reveal">
      <span class="icon-badge" style="--tint:#2563eb; margin:0 auto;">✉️</span>
      <h2 class="h2" style="margin-top:18px;">Stay Ahead With Obin Academy</h2>
      <p class="lede" style="margin:10px auto 0; max-width:440px;">Subscribe for updates on new courses, creator opportunities, and learning resources — straight to your inbox.</p>
      <form method="post" action="<?= e(base_url('contact.php')) ?>#newsletter" class="newsletter-form" data-loading-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="form_type" value="newsletter">
        <div class="field">
          <label for="home-newsletter-email" class="hidden">Email address</label>
          <input id="home-newsletter-email" name="newsletter_email" type="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="btn btn-primary">Subscribe</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
