<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$GLOW_COLORS = ['blue', 'cyan', 'purple', 'pink', 'gold', 'emerald', 'orange', 'indigo'];

$whatWeDo = [
    'Learn new skills from industry experts.',
    'Build practical knowledge that can be applied immediately.',
    'Access courses anytime and anywhere.',
    'Earn certificates after completing eligible courses.',
    'Grow personally, professionally, and financially.',
];

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

$paceBenefits = [
    'Learn from any device — phone, tablet, or laptop.',
    'Study anytime, on your own schedule.',
    'Pause and resume lessons whenever life gets busy.',
    'Access course materials whenever you need a refresher.',
    'Balance learning with work and personal life, at your pace.',
];

$commitments = [
    ['🧭', '#2563eb', 'An Easy-to-Use Platform', 'We deliver a learning experience that\'s simple, fast, and enjoyable to use.'],
    ['🚀', '#f5b301', 'Support for Creators', 'We help creators build real, successful education businesses on Obin Academy.'],
    ['🛠️', '#10b981', 'Real-World Skills', 'We help learners gain practical skills they can apply immediately.'],
    ['💡', '#8b5cf6', 'Innovation', 'We promote innovation and a culture of continuous learning.'],
    ['🌱', '#f97316', 'Opportunity', 'We create opportunities for growth and income through education.'],
];

$communityTags = ['Learners', 'Creators', 'Professionals', 'Entrepreneurs', 'Students', 'Innovators'];

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

$features = [
    ['📚', 'Real-World Skills', 'Access high-quality courses across every industry, taught by working professionals with practical experience.'],
    ['👥', 'Learn On Your Terms', 'Study at your own pace through video lessons and resources you can revisit any time.'],
    ['🏆', 'Certificates', 'Earn a certificate of completion for every course to showcase your new skills and advance your career.'],
];

$aboutTestimonials = array_slice(get_published_testimonials(), 0, 3);

// A short subset — the full FAQ list lives on contact.php.
$aboutFaqs = [
    ['How fast can I start learning?', 'Enroll and pay with MTN or Airtel Mobile Money, and you get instant access to the course — no waiting, no card required.'],
    ['Do I need a laptop to learn?', "No. Obin Academy works in any phone, tablet, or laptop browser, so you can learn from wherever you already are."],
    ['How much can creators earn?', 'Creators set their own price and keep 90% of every sale. Obin Academy takes a transparent 10% platform fee — nothing hidden.'],
    ['Will I get a certificate?', 'Yes — completing all lessons in a course automatically unlocks a Certificate of Completion in your learner dashboard.'],
];

$industries = [
    ['Finance', 'finance', '💰'], ['Business', 'business', '💼'], ['Artificial Intelligence', 'artificial-intelligence', '🤖'],
    ['Technology', 'technology-software-development', '💻'], ['Marketing', 'marketing-digital-marketing', '📣'],
    ['Design', 'design-creative', '🎨'], ['Ecommerce', 'ecommerce', '🛒'], ['Education', 'education-teaching', '🎓'],
];

$pageTitle = 'About Us — Obin Academy';
$pageDescription = "Obin Academy is a modern online learning marketplace built to empower people with practical, income-generating skills — connecting learners with real creators across Africa and beyond.";
require __DIR__ . '/includes/header.php';
?>
<!-- 1. Hero -->
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1>Learn. Teach. Earn.</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">A modern online learning marketplace built to empower people with practical, income-generating skills that create real opportunities in today's digital economy.</p>
    <div class="row gap-2" style="justify-content:center; flex-wrap:wrap; margin-top:34px;">
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-gold">Start Learning <span class="btn-arrow">→</span></a>
      <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline">▶ Become a Creator</a>
    </div>
  </div>
</section>

<!-- 2. About Obin Academy -->
<div class="section">
  <div class="container">
    <div class="why-exist-grid">
      <div class="reveal">
        <span class="eyebrow">About Obin Academy</span>
        <h2 class="h2" style="margin-top:14px;">Education That Creates Real Results</h2>
        <p class="lede" style="margin-top:16px; max-width:none; font-size:16.5px; line-height:1.75; color:var(--muted);">
          We believe education should do more than provide knowledge — it should create results. Whether you're a student building a career, an entrepreneur growing your business, or an expert ready to monetize your knowledge, Obin Academy provides the platform to help you succeed.
        </p>
        <div class="mission-callout" style="margin-top:24px;">
          <span class="tag">Our Mission, Simply Put</span>
          <p>To make quality education accessible while helping creators turn their expertise into sustainable income.</p>
        </div>
      </div>
      <div class="why-exist-photo reveal reveal-delay-2">
        <img src="<?= e(versioned_asset('assets/img/abt-students-laptop.jpg')) ?>" alt="Two learners studying together on a laptop">
      </div>
    </div>
  </div>
</div>

<!-- 3. What We Do -->
<div class="section" style="background:var(--surface); padding-top:0;">
  <div class="container">
    <div class="text-center reveal" style="max-width:620px; margin:0 auto 40px;">
      <span class="eyebrow">What We Do</span>
      <h2 class="h2" style="margin-top:10px;">Connecting Learners With Creators Who've Done the Work</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Obin Academy connects learners with experienced creators through high-quality online courses designed to solve real-world problems.</p>
    </div>
    <div class="grid lg:grid-2" style="gap:28px;">
      <div class="how-panel panel-learners reveal">
        <span class="how-panel-tag">🎓 For Learners</span>
        <ul class="check-list" style="margin-top:22px; position:relative; z-index:1;">
          <?php foreach ($whatWeDo as $item): ?>
            <li>
              <span class="check-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span>
              <span class="label-text"><?= e($item) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="how-panel panel-creators reveal reveal-delay-2">
        <span class="how-panel-tag tag-gold">💰 For Creators</span>
        <p style="margin-top:22px; position:relative; z-index:1; font-size:15px; line-height:1.75; color:var(--muted);">
          At the same time, we empower educators, professionals, entrepreneurs, coaches, consultants, and industry experts to create, publish, and sell their own courses to a growing audience across Africa and beyond.
        </p>
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-gold" style="margin-top:24px; position:relative; z-index:1;">Become a Creator <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </div>
</div>

<!-- 4 & 5. Vision & Mission -->
<div class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">Where We're Headed</span>
      <h2 class="h2" style="margin-top:10px;">Our Vision &amp; Mission</h2>
    </div>
    <div class="grid lg:grid-2" style="gap:24px;">
      <div class="value-card reveal" style="padding:34px 28px;">
        <span class="icon-badge" style="--tint:#2563eb; width:56px; height:56px; font-size:26px;">🔭</span>
        <h3 style="margin-top:20px; font-size:18px;">Our Vision</h3>
        <p style="margin-top:10px; font-size:14.5px;">To become Africa's leading digital learning marketplace where millions of people learn valuable skills, transform their lives, and create new income opportunities through education.</p>
      </div>
      <div class="value-card reveal reveal-delay-2" style="padding:34px 28px;">
        <span class="icon-badge" style="--tint:#f5b301; width:56px; height:56px; font-size:26px;">🎯</span>
        <h3 style="margin-top:20px; font-size:18px;">Our Mission</h3>
        <p style="margin-top:10px; font-size:14.5px;">To bridge the gap between knowledge and opportunity by making practical, affordable, and accessible education available to everyone while empowering creators to build sustainable businesses from their expertise.</p>
      </div>
    </div>
  </div>
</div>

<!-- 6. Why Obin Academy -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="why-exist-grid">
      <div class="reveal">
        <span class="eyebrow">Why Obin Academy?</span>
        <p class="lede" style="margin-top:14px; max-width:none; font-size:19px; line-height:1.6; color:var(--ink); font-weight:600;">
          Education is changing. Traditional classrooms are no longer the only way to learn.
        </p>
        <p class="muted" style="margin-top:16px; line-height:1.8;">
          Today, people want flexible, practical, and affordable learning experiences they can access from anywhere. Obin Academy was created to meet that need. Our platform focuses on skills that people can apply immediately to improve their careers, businesses, finances, and everyday lives. Instead of learning only theory, our learners gain practical knowledge they can use to create results.
        </p>
        <p class="muted" style="margin-top:16px; line-height:1.8; font-style:italic;">
          Our marketplace continues to grow as new creators publish courses across multiple industries.
        </p>
      </div>
      <div class="why-exist-photo reveal reveal-delay-2">
        <img src="<?= e(versioned_asset('assets/img/abt-video-call.jpg')) ?>" alt="A creator teaching a live class over video call">
      </div>
    </div>
  </div>
</div>

<!-- 7. What You'll Learn -->
<div class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">What You'll Learn</span>
      <h2 class="h2" style="margin-top:10px;">Skills Across Every Industry</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">From finance to tech to creative work — find a path that matches where you want to go.</p>
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
</div>

<!-- 8. Become a Creator -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:600px; margin:0 auto 40px;">
      <span class="eyebrow">Become a Creator</span>
      <h2 class="h2" style="margin-top:10px;">Knowledge Is Valuable</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">If you have experience, skills, or expertise that can help others, Obin Academy gives you the opportunity to turn that knowledge into income.</p>
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
    <div class="text-center reveal" style="max-width:640px; margin:44px auto 0;">
      <p class="muted" style="line-height:1.75;">Whether you're a teacher, coach, consultant, entrepreneur, freelancer, or industry professional, your knowledge has value — and Obin Academy provides the tools to share it with the world.</p>
      <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-gold btn-lg" style="margin-top:22px;">Become a Creator <span class="btn-arrow">→</span></a>
    </div>
  </div>
</div>

<!-- 9. Learn at Your Own Pace -->
<div class="section">
  <div class="container">
    <div class="why-exist-grid">
      <div class="reveal">
        <span class="eyebrow">Learn at Your Own Pace</span>
        <h2 class="h2" style="margin-top:14px;">Everyone Learns Differently</h2>
        <p class="lede" style="margin-top:14px; max-width:none;">That's why Obin Academy is designed to give learners the flexibility to study whenever and wherever it's convenient.</p>
        <ul class="check-list" style="margin-top:26px;">
          <?php foreach ($paceBenefits as $item): ?>
            <li>
              <span class="check-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span>
              <span class="label-text"><?= e($item) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="why-exist-photo reveal reveal-delay-2">
        <img src="<?= e(versioned_asset('assets/img/hero-couch-learner.jpg')) ?>" alt="A learner studying online from her couch on a laptop">
      </div>
    </div>
  </div>
</div>

<!-- 10. Our Community -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:640px; margin:0 auto;">
      <span class="eyebrow">Our Community</span>
      <h2 class="h2" style="margin-top:10px;">More Than a Course Marketplace</h2>
      <p class="lede" style="margin-top:14px; max-width:none; line-height:1.75;">
        It's a growing community of learners, creators, professionals, entrepreneurs, students, and innovators who believe that continuous learning is the key to personal and financial growth. By joining Obin Academy, you become part of a network that values collaboration, knowledge sharing, innovation, and lifelong learning.
      </p>
      <div class="community-tags" style="margin-top:28px;">
        <?php foreach ($communityTags as $tag): ?>
          <span><?= e($tag) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- 11. Our Commitment -->
<div class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 40px;">
      <span class="eyebrow">Our Commitment</span>
      <h2 class="h2" style="margin-top:10px;">What We Promise You</h2>
    </div>
    <div class="center-grid">
      <?php foreach ($commitments as $i => [$emoji, $tint, $title, $desc]): ?>
        <div class="value-card reveal reveal-delay-<?= $i + 1 ?>">
          <span class="icon-badge" style="--tint:<?= e($tint) ?>;"><?= $emoji ?></span>
          <h3><?= e($title) ?></h3>
          <p><?= e($desc) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-center muted reveal" style="max-width:560px; margin:36px auto 0; line-height:1.75;">Every feature we build is designed with one goal in mind: helping learners grow and helping creators succeed.</p>
  </div>
</div>

<!-- 12. Final CTA -->
<section class="feature-cta-section" style="background-image: url('<?= e(versioned_asset('assets/img/abt-creator-earnings.jpg')) ?>');">
  <div class="container">
    <div class="cta-panel-premium reveal">
      <span class="eyebrow" style="background:rgba(255,255,255,0.1); color:var(--gold);">Your Trusted Partner in Online Education</span>
      <h2 class="h2" style="margin-top:14px; color:#fff;">Ready to Learn, Teach, or Both?</h2>
      <p style="margin-top:12px; color:rgba(255,255,255,0.7); max-width:520px; margin-left:auto; margin-right:auto; line-height:1.7;">Whether you're looking to learn a new skill, advance your career, grow your business, or share your expertise with the world, Obin Academy is your trusted partner in online education.</p>
      <div class="row gap-2" style="justify-content:center; margin-top:28px;">
        <a href="<?= e(base_url('signup.php')) ?>" class="btn btn-gold btn-lg">Start Learning <span class="btn-arrow">→</span></a>
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline-light btn-lg">▶ Become a Creator</a>
      </div>
      <p style="margin-top:32px; font-size:22px; font-weight:800; letter-spacing:0.02em; color:var(--gold);">Learn. Teach. Earn.</p>
    </div>
  </div>
</section>
<!-- 13. How Obin Academy Works -->
<div class="section" style="background:var(--surface);">
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
</div>

<?php if ($aboutTestimonials): ?>
<!-- 14. What Our Learners Say -->
<div class="section testimonials-decor">
  <div class="container">
    <div class="text-center" style="max-width:560px; margin:0 auto 40px;">
      <span class="eyebrow">Real Results</span>
      <h2 class="h2" style="margin-top:10px;">What Our Learners Say</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Real stories from people building real skills — and real income — on Obin Academy.</p>
    </div>
    <div class="grid sm:grid-2 lg:grid-3">
      <?php foreach ($aboutTestimonials as $t): ?>
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
</div>
<?php endif; ?>

<!-- 15. Feature highlights + pace CTA -->
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

<!-- 16. FAQ -->
<div class="section">
  <div class="container" style="max-width:760px;">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">FAQ</span>
      <h2 class="h2" style="margin-top:10px;">Questions? We've Got Answers</h2>
    </div>
    <div class="faq-list reveal">
      <?php foreach ($aboutFaqs as $i => [$q, $a]): ?>
        <div class="faq-item">
          <button type="button" class="faq-question" aria-expanded="false" aria-controls="about-faq-panel-<?= $i ?>" id="about-faq-q-<?= $i ?>">
            <span><?= e($q) ?></span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="faq-chevron" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
          </button>
          <div class="faq-answer" id="about-faq-panel-<?= $i ?>" role="region" aria-labelledby="about-faq-q-<?= $i ?>">
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

<!-- 17. Newsletter -->
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
          <label for="about-newsletter-email" class="hidden">Email address</label>
          <input id="about-newsletter-email" name="newsletter_email" type="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="btn btn-primary">Subscribe</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
