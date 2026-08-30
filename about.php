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

$industries = [
    ['Finance', 'finance', '💰'], ['Business', 'business', '💼'], ['Artificial Intelligence', 'artificial-intelligence', '🤖'],
    ['Technology', 'technology-software-development', '💻'], ['Marketing', 'marketing-digital-marketing', '📣'],
    ['Design', 'design-creative', '🎨'], ['Ecommerce', 'ecommerce', '🛒'], ['Education', 'education-teaching', '🎓'],
];

$pageTitle = 'About Us — Obin Academy';
require __DIR__ . '/includes/header.php';
?>
<!-- 1. Hero -->
<section class="course-hero">
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill">About Obin Academy</span>
    <h1 style="text-align:center;">Learn. Teach. Earn.</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto; text-align:center;">A modern online learning marketplace built to empower people with practical, income-generating skills that create real opportunities in today's digital economy.</p>
    <div class="row gap-2" style="justify-content:center; margin-top:26px;">
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-gold">Start Learning <span class="btn-arrow">→</span></a>
      <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline-light">▶ Become a Creator</a>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
