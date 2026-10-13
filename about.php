<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/campuses.php';

$campuses = get_published_campuses() ?: get_all_campuses();

$howItWorks = [
    ['Choose your campus', 'Pick the one skill you want to master this year: business, trading, content, fitness, AI or e-commerce.'],
    ['Join monthly or yearly', 'Pay with MTN or Airtel Mobile Money. Your year starts the day you join, and nothing renews by itself.'],
    ['Learn every month, live every week', 'A new stage of video lessons and PDF guides opens each month, and your professor teaches live every week.'],
    ['Earn your certificate', 'Finish all twelve stages and you are awarded your campus certificate.'],
];

$commitments = [
    ['🎓', '#0b00ff', 'Professors who have done the work', 'Every campus is led by someone with real results in that skill, chosen by us.'],
    ['🗓️', '#f5b301', 'A clear path for the year', 'Twelve stages, one a month, so you always know what comes next.'],
    ['📡', '#10b981', 'Live, every week', 'A live class each week, with time for your questions.'],
    ['🤝', '#8b5cf6', 'A community that keeps you going', 'Learn alongside people working on the same skill as you.'],
    ['📱', '#f97316', 'Built for your phone', 'Lessons, live classes and payments all work from a phone.'],
];

$aboutTestimonials = array_slice(get_published_testimonials(), 0, 3);

$aboutFaqs = [
    ['How do I pay?', 'With MTN or Airtel Mobile Money. You approve a prompt on your phone and your campus opens straight away.'],
    ['Monthly or yearly?', 'Monthly gives you 30 days at a time; yearly gives you twelve months at once and costs less overall. Nothing is charged automatically: we remind you before your access ends.'],
    ['Do I need a laptop?', 'No. Everything works in the browser on your phone, tablet or laptop.'],
    ['Will I get a certificate?', 'Yes. Complete every lesson of the twelve stages and your campus certificate is issued.'],
];

$stats = get_platform_stats();

$pageTitle = 'About Us — Obin Academy';
$pageDescription = 'Obin Academy runs year-long campuses: one skill, twelve stages, a live class every week and a professor who has done the work.';
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1>One skill. One year. Mastered.</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">Obin Academy runs year-long campuses led by professors who have done the work, so you learn a skill that changes what you earn, not just what you know.</p>
    <div class="row gap-2" style="justify-content:center; flex-wrap:wrap; margin-top:34px;">
      <a href="<?= e(base_url('index.php#campuses')) ?>" class="btn btn-gold">See the campuses <span class="btn-arrow">→</span></a>
      <a href="<?= e(base_url('teach.php')) ?>" class="btn btn-outline">Teach at Obin Academy</a>
    </div>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<div class="section">
  <div class="container">
    <div class="why-exist-grid">
      <div class="reveal">
        <span class="eyebrow">About Obin Academy</span>
        <h2 class="h2" style="margin-top:14px;">Education That Creates Real Results</h2>
        <p class="lede" style="margin-top:16px; max-width:none; font-size:16.5px; line-height:1.75; color:var(--muted);">
          We believe learning should change your life, not just fill your notes. So instead of a library of scattered courses, we run campuses: one skill, taught over a full year by a professor with real results, with a live class every week and a community learning beside you.
        </p>
        <div class="mission-callout" style="margin-top:24px;">
          <span class="tag">Our Mission, Simply Put</span>
          <p>To help people across Africa master one valuable skill at a time, with the structure and support to actually finish.</p>
        </div>
      </div>
      <div class="why-exist-photo reveal reveal-delay-2">
        <img src="<?= e(versioned_asset('assets/img/abt-students-laptop.jpg')) ?>" alt="Two learners studying together on a laptop">
      </div>
    </div>
  </div>
</div>

<?php if ($campuses): ?>
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:600px; margin:0 auto 36px;">
      <span class="eyebrow">Our Campuses</span>
      <h2 class="h2" style="margin-top:10px;">Pick the skill you want to master</h2>
    </div>
    <div class="center-grid">
      <?php foreach ($campuses as $i => $c): ?>
        <a href="<?= e(base_url('campus.php?slug=' . $c['slug'])) ?>" class="value-card reveal reveal-delay-<?= min($i % 5 + 1, 5) ?>" style="text-decoration:none; color:inherit;">
          <span class="icon-badge" style="--tint:#0b00ff;"><?= campus_icon($c['icon'], 1.8) ?></span>
          <h3><?= e($c['name']) ?></h3>
          <p><?= e($c['tagline']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:580px; margin:0 auto 40px;">
      <span class="eyebrow">Simple By Design</span>
      <h2 class="h2" style="margin-top:10px;">How a Campus Works</h2>
    </div>
    <div class="how-panel panel-learners reveal" style="max-width:760px; margin:0 auto;">
      <div class="stack gap-3" style="position:relative; z-index:1;">
        <?php foreach ($howItWorks as $i => [$title, $desc]): ?>
          <div class="step-row">
            <span class="step-num"><?= $i + 1 ?></span>
            <div><h4><?= e($title) ?></h4><p><?= e($desc) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
      <a href="<?= e(base_url('index.php#campuses')) ?>" class="btn btn-primary" style="margin-top:26px; position:relative; z-index:1;">Choose your campus <span class="btn-arrow">→</span></a>
    </div>
  </div>
</div>

<div class="section" style="background:var(--surface);">
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
  </div>
</div>

<?php if ($aboutTestimonials): ?>
<div class="section testimonials-decor">
  <div class="container">
    <div class="text-center" style="max-width:560px; margin:0 auto 40px;">
      <span class="eyebrow">Real Results</span>
      <h2 class="h2" style="margin-top:10px;">What Our Learners Say</h2>
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
  </div>
</div>

<section class="feature-cta-section" style="background-image: url('<?= e(versioned_asset('assets/img/abt-video-call.jpg')) ?>');">
  <div class="container">
    <div class="cta-panel-premium reveal">
      <span class="eyebrow" style="background:rgba(255,255,255,0.1); color:var(--gold);">Your year starts the day you join</span>
      <h2 class="h2" style="margin-top:14px; color:#fff;">Ready to master one skill?</h2>
      <div class="row gap-2" style="justify-content:center; margin-top:28px;">
        <a href="<?= e(base_url('index.php#campuses')) ?>" class="btn btn-gold btn-lg">Choose your campus <span class="btn-arrow">→</span></a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
