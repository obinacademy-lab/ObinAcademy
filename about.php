<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/stories.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/platform.php';
require __DIR__ . '/includes/bright.php';

$campuses = get_published_campuses() ?: get_all_campuses();

$howItWorks = [
    ['Choose your campus', 'Pick the one skill you want to master this year: business, trading, content, fitness, AI or e-commerce.'],
    ['Join monthly or yearly', 'Pay with MTN or Airtel Mobile Money. Your year starts the day you join, and nothing renews by itself.'],
    ['Learn every month, live every week', 'A new stage of video lessons and PDF guides opens as your class moves through the year, and your professor teaches live every week.'],
    ['Earn your certificate', 'Finish all twelve stages and you are awarded your campus certificate.'],
];

$commitments = [
    ['Professors who have done the work', 'Every campus is led by someone with real results in that skill, chosen by Obin Academy.'],
    ['One skill, one year', 'Not a library of scattered courses. One skill, taught over a full year with a clear path of twelve stages.'],
    ['A live class every week', 'Learn with your professor, with time for your questions, and a community learning beside you.'],
    ['Built for your phone', 'Lessons, PDFs, live classes and payments all work on any smartphone.'],
];
$aboutStories = get_published_video_stories(3);

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
br_sprite();
?>
<div class="br">
  <section class="b-ph">
    <div class="b-w">
      <div>
        <span class="b-eb">About Obin Academy</span>
        <h1>One skill. One year. <i>Mastered.</i></h1>
        <p class="b-lead">Obin Academy runs year-long campuses led by professors who have done the work, so you learn a skill that changes what you earn, not just what you know.</p>
        <div class="b-acts"><a class="b-btn p lg" href="<?= e(base_url('campuses.php')) ?>">See the campuses <?= br_ic('arrow') ?></a><a class="b-btn o lg" href="<?= e(base_url('teach.php')) ?>">Teach at Obin Academy</a></div>
      </div>
    </div>
  </section>

  <section class="b-strip"><div class="b-w">
    <div><b><?= (int) $stats['campus_count'] ?></b><span>Campuses to choose from</span></div>
    <div><b><?= (int) $stats['learner_count'] ?>+</b><span>Learners</span></div>
    <div><b>12</b><span>Months, step by step</span></div>
    <div><b>52</b><span>Live classes a year</span></div>
  </div></section>

  <section class="b-sec"><div class="b-w">
    <div class="b-split2">
      <img src="<?= e(versioned_asset('assets/img/abt-students-laptop.jpg')) ?>" alt="Two learners studying together on a laptop">
      <div>
        <span class="b-eb">About Obin Academy</span>
        <h2 style="font-size:clamp(30px,3.6vw,48px);line-height:1.06;margin:12px 0 18px">Education that creates <i>real results.</i></h2>
        <p class="b-lead">We believe learning should change your life, not just fill your notes. So instead of a library of scattered courses, we run campuses: one skill, taught over a full year by a professor with real results, with a live class every week and a community learning beside you.</p>
      </div>
    </div>
  </div></section>

  <section class="b-sec" style="padding-top:0"><div class="b-w">
    <div class="b-mission"><small>Our mission, simply put</small><p>To help people across Africa master one valuable skill at a time, with the structure and support to actually finish.</p></div>
  </div></section>

  <section class="b-sec alt"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Our commitment</span><h2>What we <i>promise you.</i></h2></div>
    <div class="b-cols4"><?php foreach ($commitments as $i => [$title, $desc]): ?><div><small>0<?= $i + 1 ?></small><b><?= e($title) ?></b><span><?= e($desc) ?></span></div><?php endforeach; ?></div>
  </div></section>

  <?php if ($campuses): ?>
  <section class="b-sec"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Our campuses</span><h2>Pick the skill you <i>want to master.</i></h2></div>
    <div class="b-feat b-camplinks">
      <?php foreach ($campuses as $c): ?>
        <a href="<?= e(base_url('campus.php?slug=' . rawurlencode($c['slug']))) ?>"><i><?= campus_icon($c['icon'], 1.8) ?></i><b><?= e($c['name']) ?></b><span><?= e($c['tagline']) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div></section>
  <?php endif; ?>

  <section class="b-sec alt"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Simple by design</span><h2>How a campus <i>works.</i></h2></div>
    <div class="b-steps"><?php foreach ($howItWorks as $i => [$title, $desc]): ?><div><b class="b-n">STEP 0<?= $i + 1 ?></b><h3><?= e($title) ?></h3><p><?= e($desc) ?></p></div><?php endforeach; ?></div>
  </div></section>

  <?php if ($aboutStories): ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/stories.css')) ?>">
  <section class="b-sec"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Real students</span><h2>In their <i>own words.</i></h2><p class="b-lead">Students on camera, talking about their campus. Tap a story to watch it with sound.</p></div>
    <div class="vs-wall three"><?php foreach ($aboutStories as $s) render_video_story_card($s); ?></div>
    <p class="b-note"><a href="<?= e(base_url('stories.php')) ?>" style="color:var(--link);font-weight:600">Watch every story →</a></p>
  </div></section>
  <script src="<?= e(versioned_asset('assets/js/stories.js')) ?>" defer></script>
  <?php endif; ?>

  <section class="b-sec<?= $aboutStories ? ' alt' : '' ?>"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Questions</span><h2>Before you <i>join.</i></h2></div>
    <div class="b-faq" style="max-width:820px;margin:0 auto">
      <?php foreach ($aboutFaqs as $i => [$q, $a]): ?><details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
    </div>
  </div></section>

  <section class="b-sec" style="padding-top:20px"><div class="b-w">
    <div class="b-cta"><div><h2>Your year starts the day you join.</h2><p>Twelve months from now you will wish you had started today. Choose your campus and take the first step.</p></div><a class="b-btn a lg" href="<?= e(base_url('campuses.php')) ?>">Choose your campus <?= br_ic('arrow') ?></a></div>
  </div></section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>