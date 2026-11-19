<?php
/**
 * Home page, "Bright": the promise and a look inside a live class, the numbers, the open campuses, everything inside one
 * campus, how a campus works, the old way against a campus, pricing, why Obin Academy exists, questions and a closing call.
 * Campuses and prices always come from the database. Styles: assets/css/bright.css (scoped under .br).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/platform.php';
require __DIR__ . '/includes/bright.php';

$all = get_all_campuses();
$published = array_values(array_filter($all, fn($c) => $c['status'] === 'PUBLISHED'));
$priced = $published ?: $all;

$hasDay = campus_daily_ready();
$monthlies = array_filter(array_map(fn($c) => (float) $c['monthly_price'], $priced), fn($v) => $v > 0);
$from = $monthlies ? min($monthlies) : 0.0;
$fromText = $from > 0 ? format_money($from) : null;
$yearlies = array_filter(array_map(fn($c) => campus_plan_price($c, 'YEARLY') ?: (float) $c['monthly_price'] * CAMPUS_YEAR_MONTHS, $priced), fn($v) => $v > 0);
$yearFrom = $yearlies ? min($yearlies) : 0.0;
$dayPrices = $hasDay ? array_filter(array_map(fn($c) => (float) $c['daily_price'], $published), fn($v) => $v > 0) : [];
$dayFrom = $dayPrices ? min($dayPrices) : 0.0;
$perDay = fn(float $v) => format_money(round($v / 30 / 100) * 100);

$pageTitle = 'Obin Academy Campuses — Learn a skill that pays, in 12 months';
$pageDescription = 'Join an Obin Academy campus: a year-long programme with a professor who does it for a living. Step-by-step lessons, a live class every week and a private community'
    . ($fromText ? ', from ' . $fromText . ' a month' : '') . ', paid with MTN or Airtel Money.';
require __DIR__ . '/includes/header.php';
br_sprite();

$inside = [
    ['video', 'Step-by-step video lessons', 'A new stage each time your class is ready, in the right order. You always know what to do next.'],
    ['live', 'A live class every week', 'Learn live with your professor and ask your questions. Missed it? The recording waits for you.'],
    ['users', 'A professor who does it', 'Every professor is chosen by Obin Academy for real results in the skill they teach.'],
    ['chat', 'A private community', 'Share your wins, ask for help and stay accountable with people on the same path.'],
    ['file', 'PDF guides and worksheets', 'Checklists and notes to download and use while you practise.'],
    ['award', 'A certificate at the end', 'Finish your twelve months and receive your Obin Academy certificate.'],
];
$steps = [
    ['Choose your campus', 'Pick the one skill you want to master this year: business, trading, content, fitness, AI or e-commerce.'],
    ['Join daily, monthly or yearly', 'Pay with MTN or Airtel Mobile Money. Your year starts the day you join, and nothing renews by itself.'],
    ['Learn every month, live every week', 'A new stage of video lessons and PDF guides opens as your class moves through the year, and your professor teaches live every week.'],
    ['Earn your certificate', 'Finish all twelve stages and you are awarded your campus certificate.'],
];
$faqs = [
    ['Do I need any experience?', 'No. Every campus starts from the basics in month one and builds up step by step. If you can use a smartphone, you can follow along.'],
    ['Can I learn on my phone?', 'Yes. Lessons, PDFs, live classes and the community all work on a smartphone. A computer helps for some campuses, but it is not required to start.'],
    ['How do I pay?', 'With MTN or Airtel Mobile Money. A prompt arrives on your phone; enter your PIN to confirm. ' . ($dayFrom > 0 ? 'Pay for a single day (24 hours, then the campus closes until you pay again), monthly (30 days at a time) or once for the year.' : 'Pay monthly (30 days at a time) or once for the year.')],
    ['What if I miss a live class?', 'Every live class is recorded and added to your lessons, so you can watch it later.'],
    ['Can I stop any time?', "Yes. Nothing is charged automatically. If you're on the monthly plan and don't pay again, your access simply ends. Your progress is kept if you come back."],
    ['Can I join more than one campus?', 'Yes. Each campus has its own membership, so you can join a second one whenever you are ready.'],
    ['Will I make money?', "The campuses teach practical skills that people use to earn. Your results depend on how much you practise and apply what you learn. We don't promise any income."],
];
$plans = [];
if ($dayFrom > 0) $plans[] = ['Day pass', format_money($dayFrom), '24 hours', ['Full access for 24 hours', 'Every lesson, live class and PDF', 'Progress is kept'], 'Try a campus today', false];
$plans[] = ['Monthly', $fromText ?? '—', '30 days at a time', ['Full access for 30 days', 'Every lesson, live class and PDF', 'A reminder before your month ends'], 'Start monthly', true];
$plans[] = ['Yearly', $yearFrom > 0 ? format_money($yearFrom) : '—', '12 months, pay once', ['Full access for 12 months', 'Pay once, nothing to renew', 'Your certificate at the end'], 'Start yearly', false];
?>
<div class="br">
  <section class="b-hero ui">
    <div class="b-w">
      <div>
        <span class="b-eb">Obin Academy Campuses · Kampala, Uganda</span>
        <h1 style="margin-top:16px">School taught you to look for a job. We teach you to earn with a skill.</h1>
        <p class="b-sub">Join a campus, learn step by step from a professor who does it for a living, and build income you control, in twelve months, on your phone.</p>
        <div class="b-acts">
          <a class="b-btn p lg" href="#campuses">Choose your campus <?= br_ic('arrow') ?></a>
          <a class="b-btn o lg" href="#inside">See everything you get</a>
        </div>
        <?php if ($fromText && $dayFrom > 0): ?><p class="b-price">From <b><?= e($fromText) ?></b> a month — or <b><?= e(format_money($dayFrom)) ?></b> for a single day.</p>
        <?php elseif ($fromText): ?><p class="b-price">From <b><?= e($fromText) ?></b> a month — about <b><?= e($perDay($from)) ?></b> a day.</p><?php endif; ?>
        <div class="b-trust">
          <span><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN Mobile Money" width="26" height="26"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel Money" width="26" height="26"></span>
          <span><?= br_ic('check') ?>Stop any time</span>
          <span><?= br_ic('check') ?>Weekly live class</span>
          <span><?= br_ic('check') ?>Certificate included</span>
        </div>
      </div>
      <a class="b-week b-live" href="#inside" style="padding:14px">
        <div class="b-vid" style="aspect-ratio:16/10"><img src="<?= e(versioned_asset('assets/img/abt-video-call.jpg')) ?>" alt="A professor teaching a live class to learners on video" style="object-position:center 40%"><span class="b-onair"><i></i>Live class</span></div>
        <div style="padding:16px 8px 6px"><b style="display:block;font:700 16px var(--sans)">A live class, every week</b><small style="color:var(--mute);font-size:13px">With your professor. Missed it? It is recorded for you.</small></div>
      </a>
    </div>
  </section>

  <section class="b-strip"><div class="b-w">
    <div><b><?= count($all) ?></b><span>Campuses to choose from</span></div>
    <div><b>52</b><span>Live classes in your year</span></div>
    <div><b>12</b><span>Months, step by step</span></div>
    <div><b>1</b><span>Certificate when you finish</span></div>
  </div></section>

  <section class="b-sec" id="campuses"><div class="b-w">
    <div class="b-sh"><span class="b-eb">The campuses</span><h2>Pick the skill <i>that will pay you.</i></h2><p class="b-lead">Each campus has its own professor, a year-long programme, a weekly live class and a private community.</p></div>
    <?php if ($priced): ?>
      <div class="b-cgrid"><?php foreach ($priced as $c) echo br_campus_card($c, $hasDay); ?></div>
      <?php if (count($all) > count($priced)): ?><p class="b-note"><a href="<?= e(base_url('campuses.php')) ?>" style="color:var(--link);font-weight:600">See every campus, including the ones opening soon →</a></p><?php endif; ?>
    <?php endif; ?>
  </div></section>

  <section class="b-sec alt" id="inside"><div class="b-w">
    <div class="b-sh"><span class="b-eb">What you get</span><h2>Everything inside <i>one campus.</i></h2><p class="b-lead">One membership opens all of it for your campus, on your phone or computer.</p></div>
    <div class="b-feat"><?php foreach ($inside as [$ic, $title, $text]): ?><div><i><?= br_ic($ic) ?></i><b><?= e($title) ?></b><span><?= e($text) ?></span></div><?php endforeach; ?></div>
  </div></section>

  <section class="b-sec" id="how"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Simple by design</span><h2>How a campus <i>works.</i></h2></div>
    <div class="b-steps"><?php foreach ($steps as $i => [$title, $text]): ?><div><b class="b-n">STEP 0<?= $i + 1 ?></b><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div><?php endforeach; ?></div>
  </div></section>

  <section class="b-sec alt" id="compare"><div class="b-w">
    <div class="b-sh"><span class="b-eb">A better way to learn</span><h2>Four years of theory, or <i>one year of practice?</i></h2></div>
    <div class="b-vs">
      <div class="old"><h3>The old way</h3><ul>
        <li><?= br_ic('x') ?>Three to four years before you earn anything</li>
        <li><?= br_ic('x') ?>Millions of shillings in fees</li>
        <li><?= br_ic('x') ?>Taught by people who have never done it</li>
        <li><?= br_ic('x') ?>Notes to memorise, then hope for a job</li>
      </ul></div>
      <div class="new"><h3>An Obin <i>campus</i></h3><ul>
        <li><?= br_ic('check') ?>Twelve practical stages, taught as a class</li>
        <li><?= br_ic('check') ?><?= $fromText ? 'From ' . e($fromText) . ' a month, paid on your phone' : 'Paid monthly or yearly, on your phone' ?></li>
        <li><?= br_ic('check') ?>Taught by a professor who earns from the skill</li>
        <li><?= br_ic('check') ?>You practise every week and use the skill as you learn</li>
      </ul></div>
    </div>
  </div></section>

  <section class="b-sec" id="prices"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Pricing</span><h2>Less than you spend on <i>airtime.</i></h2><p class="b-lead">Each campus sets its own price; these are the lowest. Nothing is charged automatically.</p></div>
    <div class="b-plans<?= count($plans) === 2 ? ' two' : '' ?>">
      <?php foreach ($plans as [$name, $amt, $per, $bullets, $cta, $best]): ?>
        <div class="b-plan<?= $best ? ' best' : '' ?>"><?= $best ? '<span class="b-tagb">Most popular</span>' : '' ?>
          <h3><?= e($name) ?></h3>
          <div class="b-amt"><small>From UGX</small><?= e(preg_replace('/^UGX\s*/', '', $amt)) ?></div>
          <div class="b-per"><?= e($per) ?></div>
          <ul><?php foreach ($bullets as $bl): ?><li><?= br_ic('check') ?><?= e($bl) ?></li><?php endforeach; ?></ul>
          <a class="b-btn <?= $best ? 'a' : 'p' ?> full" href="#campuses"><?= e($cta) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="b-note">Pay with MTN or Airtel Money. Stop any time. Your progress is kept.</p>
  </div></section>

  <section class="b-sec alt" id="trust"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Why Obin Academy</span><h2>Professors who have <i>done the work.</i></h2><p class="b-lead">Every campus is led by someone with real results in that skill, chosen by Obin Academy.</p></div>
    <div class="b-trustgrid">
      <div class="b-founder"><img src="<?= e(versioned_asset('assets/img/founder.jpg')) ?>" alt="The founder of Obin Academy" loading="lazy"><div><b>The founder</b><small>Obin Academy, Kampala</small></div></div>
      <div>
        <p class="b-quote">Obin Academy was started in Uganda so that a practical, earning skill does not take four years and millions of shillings. It takes twelve months, a phone, and a professor who has done it.</p>
        <div class="b-promise">
          <div><b>A live class every week</b><span>Ask your questions live. Every class is recorded for you.</span></div>
          <div><b>Pay your way</b><span>MTN or Airtel Money, by the day, month or year. Nothing renews by itself.</span></div>
        </div>
      </div>
    </div>
  </div></section>

  <section class="b-sec" id="faq"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Questions</span><h2>Before you <i>join.</i></h2></div>
    <div class="b-faq" style="max-width:820px;margin:0 auto">
      <?php foreach ($faqs as $i => [$q, $a]): ?><details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
    </div>
  </div></section>

  <section class="b-sec" style="padding-top:20px"><div class="b-w">
    <div class="b-cta"><div><h2>Your year starts the day you join.</h2><p>Twelve months from now you will wish you had started today. Choose your campus and take the first step.</p></div><a class="b-btn a lg" href="#campuses">Choose your campus <?= br_ic('arrow') ?></a></div>
  </div></section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
