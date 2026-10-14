<?php
/**
 * Home page: sells the campuses. A clear promise, "is this you?", old way vs a campus, the campuses,
 * what's inside, three steps, pricing, real students on video (only published video stories, never invented),
 * questions, and a closing call to action. Joining always goes through a campus page and payment.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/stories.php';

$viewer = current_user();
$all = get_all_campuses();
$published = array_values(array_filter($all, fn($c) => $c['status'] === 'PUBLISHED'));
$upcoming = array_values(array_filter($all, fn($c) => $c['status'] !== 'PUBLISHED'));
$grid = array_merge($published, $upcoming);
$extra = (3 - count($grid) % 3) % 3; // wide cards so the last row is always full

$priced = $published ?: $all;
$monthly = $priced ? min(array_map(fn($c) => (float) $c['monthly_price'], $priced)) : 100000;
$yearly = $priced ? min(array_map(fn($c) => (float) $c['yearly_price'], $priced)) : 1000000;
$saving = max(0, $monthly * 12 - $yearly);
// Each campus sets its own prices. When they differ, show the lowest as "From ..." and only promise a saving that is true for every campus.
$samePrices = count(array_unique(array_map(fn($c) => (float) $c['monthly_price'] . '|' . (float) $c['yearly_price'], $priced ?: [['monthly_price' => $monthly, 'yearly_price' => $yearly]]))) === 1;
$from = $samePrices ? '' : 'From ';
$perDay = fn(float $v, int $days) => 'UGX ' . number_format(round($v / $days / 100) * 100);
$firstLink = $published ? base_url('campus.php?slug=' . $published[0]['slug'] . '#join') : '#campuses';

$stories = get_published_video_stories(3);

$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
$cross = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$names = $all ? array_column($all, 'name') : ['Business & Finance', 'Crypto Trading', 'Forex Trading', 'Content Creation', 'Fitness & Nutrition', 'AI & Technology', 'E-Commerce'];

$pageTitle = 'Obin Academy Campuses — Earning is a skill. Master it in 12 months.';
$pageDescription = 'Join an Obin Academy campus: a year-long programme with a professor who does it for a living. Step-by-step lessons, a live class every week and a private community, paid monthly or yearly with MTN or Airtel Money.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/home-campus.css')) ?>">
<?php if ($stories): ?><link rel="stylesheet" href="<?= e(versioned_asset('assets/css/stories.css')) ?>"><?php endif; ?>
<div class="hx-yearline" aria-hidden="true"><i></i></div><div class="hx-month" aria-hidden="true">Month <b>01</b> of 12</div>
<div class="hx">
  <section class="hx-hero">
    <div class="hx-wrap">
      <span class="hx-eyebrow">Obin Academy Campuses</span>
      <h1><span class="w" style="--w:0">Earning</span> <span class="w" style="--w:1">is</span> <span class="w" style="--w:2">a</span> <span class="w" style="--w:3">skill.</span><br><em><span class="w" style="--w:4">Master</span> <span class="w" style="--w:5">it</span> <span class="w" style="--w:6">in</span> <span class="w" style="--w:7">12</span> <span class="w" style="--w:8">months.</span></em></h1>
      <p class="lede">School taught you to look for a job. We teach you to make money with a skill. Join a campus, learn step by step from a professor who does it for a living, and build income you control.</p>
      <div class="acts">
        <a class="hx-cta" href="#campuses">Choose your campus <?= $arrow ?></a>
        <a class="hx-ghost" href="#how">How it works</a>
      </div>
      <div class="hx-trust">
        <span><?= $check ?>Pay with MTN or Airtel Money</span>
        <span><?= $check ?>Live class every week</span>
        <span><?= $check ?>Stop any time</span>
        <span><?= $check ?>Certificate at the end</span>
      </div>
    </div>
    <div class="hx-marquee" aria-hidden="true"><div>
      <?php for ($r = 0; $r < 2; $r++) foreach ($names as $n): ?><span><?= e($n) ?></span><b>✦</b><?php endforeach; ?>
    </div></div>
    <div class="hx-wrap hx-stats">
      <div><b><?= max(7, count($all)) ?></b><span>Campuses to choose from</span></div>
      <div><b>52</b><span>Live classes in your year</span></div>
      <div><b>12</b><span>Months, step by step</span></div>
      <div><b>1</b><span>Certificate when you finish</span></div>
    </div>
  </section>

  <section class="hx-light hx-sec">
    <div class="hx-wrap">
      <div class="hx-head"><span class="hx-eyebrow dark">Be honest with yourself</span><h2>Does this sound <em>like you?</em></h2></div>
      <div class="hx-you">
        <?php foreach ([
            'Your salary or allowance is finished before the month is.',
            'You finished school, and the job you were promised never came.',
            'You have watched hundreds of free videos, and nothing changed.',
            'You want a skill you can use from your phone, anywhere in Uganda.',
            'You know you can do more, but nobody around you shows you how.',
            'You are ready to put in the work if someone gives you a clear plan.',
        ] as $line): ?><div><?= $check ?><span><?= e($line) ?></span></div><?php endforeach; ?>
      </div>
      <p class="hx-you-close">If you nodded at even one of these, a campus was built <em>for you.</em> Not for people who only want to think about it, but for people who are ready to move.</p>
    </div>
  </section>

  <section class="hx-light hx-sec flush">
    <div class="hx-wrap">
      <div class="hx-head c"><span class="hx-eyebrow dark">A better way to learn</span><h2>Four years of theory, or <em>one year of practice?</em></h2></div>
      <div class="hx-vs">
        <div class="old"><h3>The old way</h3><ul>
          <li><?= $cross ?><span>Three to four years before you earn anything</span></li>
          <li><?= $cross ?><span>Millions of shillings in fees</span></li>
          <li><?= $cross ?><span>Taught by people who have never done it</span></li>
          <li><?= $cross ?><span>Notes to memorise, then hope for a job</span></li>
        </ul></div>
        <div class="new"><h3>An Obin <em>campus</em></h3><ul>
          <li><?= $check ?><span>Twelve practical stages, taught as a class</span></li>
          <li><?= $check ?><span>From <?= e(format_money($monthly)) ?> a month, paid on your phone</span></li>
          <li><?= $check ?><span>Taught by a professor who earns from the skill</span></li>
          <li><?= $check ?><span>You practise every week and use the skill as you learn</span></li>
        </ul></div>
      </div>
    </div>
  </section>

  <section class="hx-deep hx-sec" id="campuses">
    <div class="hx-wrap">
      <div class="hx-head"><span class="hx-eyebrow">The campuses</span><h2>Pick the skill <em>that will pay you.</em></h2><p>Each campus has its own professor, its own year-long programme, a weekly live class and a private community.</p></div>
      <div class="hx-cgrid">
        <?php foreach ($grid as $i => $c):
            $open = $c['status'] === 'PUBLISHED';
            $wide = ($i === 0 && $extra >= 1) || ($i === count($grid) - 1 && $extra >= 2);
            $cls = 'hx-cc' . ($wide ? ' wide' : '') . ($i === 0 && $open ? ' feature' : '');
            $tag = $open ? 'a' : 'div';
            $photo = campus_photo($c);
        ?>
          <<?= $tag ?> class="<?= $cls ?><?= $photo ? ' has-pic' : '' ?>"<?= $open ? ' href="' . e(base_url('campus.php?slug=' . $c['slug'])) . '"' : '' ?>>
            <?php if ($photo): ?><span class="pic"><img src="<?= e($photo) ?>" alt="" loading="lazy"><span class="ic"><?= campus_icon($c['icon'], 1.7) ?></span></span><?php endif; ?>
            <span class="body">
              <?php if (!$photo): ?><span class="ic"><?= campus_icon($c['icon'], 1.7) ?></span><?php endif; ?>
              <h3><?= e($c['name']) ?> Campus</h3>
              <p><?= e($c['tagline']) ?></p>
              <span class="ft">
                <?php if ($open): ?><b><?= e(format_money((float) $c['monthly_price'])) ?> / month</b><span>Join now →</span>
                <?php else: ?><b>Opening soon</b><?php endif; ?>
              </span>
            </span>
          </<?= $tag ?>>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="hx-light hx-sec" id="inside">
    <div class="hx-wrap">
      <div class="hx-head"><span class="hx-eyebrow dark">What you get</span><h2>Everything inside <em>one campus.</em></h2><p>One membership opens all of it for your campus, on your phone or computer.</p></div>
      <div class="hx-inside">
        <div><?= campus_icon('content', 1.8) ?><h3>Step-by-step video lessons</h3><p>A new stage each time your class is ready, in the right order. You always know what to do next.</p></div>
        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19 5a10 10 0 0 1 0 14M5 19A10 10 0 0 1 5 5"/></svg><h3>A live class every week</h3><p>Learn live with your professor and ask your questions. Missed it? The recording waits for you.</p></div>
        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg><h3>A professor who does it</h3><p>Every professor is chosen by Obin Academy for real results in the skill they teach.</p></div>
        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><h3>A private community</h3><p>Share your wins, ask for help and stay accountable with people on the same path.</p></div>
        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg><h3>PDF guides and worksheets</h3><p>Checklists and notes to download and use while you practise.</p></div>
        <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/></svg><h3>A certificate at the end</h3><p>Finish your twelve months and receive your Obin Academy certificate.</p></div>
      </div>
    </div>
  </section>

  <section class="hx-light hx-sec flush" id="how">
    <div class="hx-wrap">
      <div class="hx-head"><span class="hx-eyebrow dark">How it works</span><h2>Three steps. <em>Start today.</em></h2></div>
      <div class="hx-steps">
        <div><b>1</b><h3>Choose your campus</h3><p>Pick the skill you want to build and meet the professor who teaches it.</p></div>
        <div><b>2</b><h3>Pay with mobile money</h3><p>Monthly or for the whole year. A prompt arrives on your phone; enter your PIN and you are in.</p></div>
        <div><b>3</b><h3>Learn, apply, earn</h3><p>Follow the monthly stages, join the live class every week and put the skill to work.</p></div>
      </div>
    </div>
  </section>

  <section class="hx-deep hx-sec" id="pricing">
    <div class="hx-wrap">
      <div class="hx-head c"><span class="hx-eyebrow">Pricing</span><h2>Less than a cup of tea <em>a day.</em></h2><p><?= $samePrices ? 'Prices are per campus.' : 'Each campus has its own price, shown on its card and its page.' ?> Pay with MTN or Airtel Money. Nothing is charged automatically.</p></div>
      <div class="hx-plans">
        <div class="hx-plan">
          <div class="nm">Monthly</div>
          <div class="amt"><?php if ($from): ?><small>From</small> <?php endif; ?><?= e(format_money($monthly)) ?> <small>/ month</small></div>
          <p class="eq">About <?= e($perDay($monthly, 30)) ?> a day</p>
          <ul><li><?= $check ?>Full access for 30 days</li><li><?= $check ?>Every lesson, live class and PDF</li><li><?= $check ?>A reminder before your month ends</li></ul>
          <a class="hx-ghost" href="<?= e($firstLink) ?>">Start monthly</a>
        </div>
        <div class="hx-plan best">
          <div class="nm">Yearly <?php if ($samePrices && $saving > 0): ?><span class="tag">Save <?= e(format_money($saving)) ?></span><?php elseif (!$samePrices): ?><span class="tag">Best value</span><?php endif; ?></div>
          <div class="amt"><?php if ($from): ?><small>From</small> <?php endif; ?><?= e(format_money($yearly)) ?> <small>/ year</small></div>
          <p class="eq"><?= $samePrices && $saving >= $monthly ? floor($saving / $monthly) . ' month' . (floor($saving / $monthly) >= 2 ? 's' : '') . ' free · ' : '' ?>about <?= e($perDay($yearly, 365)) ?> a day</p>
          <ul><li><?= $check ?>Full access for 12 months</li><li><?= $check ?>Pay once, nothing to renew</li><li><?= $check ?>Your certificate at the end</li></ul>
          <a class="hx-cta" href="<?= e($firstLink) ?>">Start yearly</a>
        </div>
      </div>
      <p class="hx-price-note">Your twelve months start the day you join. Join this week and you are in this week's live class.</p>
    </div>
  </section>

  <?php if ($stories): ?>
  <section class="hx-light hx-sec">
    <div class="hx-wrap">
      <div class="hx-head"><span class="hx-eyebrow dark">Real people</span><h2>In their <em>own words.</em></h2></div>
      <div class="vs-wall three">
        <?php foreach ($stories as $s) render_video_story_card($s); ?>
      </div>
      <p class="vs-more"><a class="hx-more" href="<?= e(base_url('stories.php')) ?>">Watch every story <?= $arrow ?></a></p>
    </div>
  </section>
  <?php endif; ?>

  <section class="hx-light hx-sec<?= $stories ? ' flush' : '' ?>" id="faq">
    <div class="hx-wrap">
      <div class="hx-head c"><span class="hx-eyebrow dark">Questions</span><h2>Before you <em>join.</em></h2></div>
      <div class="hx-faq">
        <details open><summary>Do I need any experience?</summary><p>No. Every campus starts from the basics in month one and builds up step by step. If you can use a smartphone, you can follow along.</p></details>
        <details><summary>Can I learn on my phone?</summary><p>Yes. Lessons, PDFs, live classes and the community all work on a smartphone. A computer helps for some campuses, but it is not required to start.</p></details>
        <details><summary>How do I pay?</summary><p>With MTN or Airtel Mobile Money. A prompt arrives on your phone; enter your PIN to confirm. Pay monthly (30 days at a time) or once for the year.</p></details>
        <details><summary>What if I miss a live class?</summary><p>Every live class is recorded and added to your lessons, so you can watch it later.</p></details>
        <details><summary>Can I stop any time?</summary><p>Yes. Nothing is charged automatically. If you are on the monthly plan and don't pay again, your access simply ends. Your progress is kept if you come back.</p></details>
        <details><summary>Can I join more than one campus?</summary><p>Yes. Each campus has its own membership, so you can join a second one whenever you are ready.</p></details>
        <details><summary>Will I make money?</summary><p>The campuses teach practical skills that people use to earn. Your results depend on how much you practise and apply what you learn. We don't promise any income.</p></details>
      </div>
    </div>
  </section>

  <section class="hx-deep hx-final">
    <div class="hx-wrap">
      <h2><span class="w" style="--w:0">Your</span> <span class="w" style="--w:1">year</span> <span class="w" style="--w:2">starts</span><br><em><span class="w" style="--w:3">the</span> <span class="w" style="--w:4">day</span> <span class="w" style="--w:5">you</span> <span class="w" style="--w:6">join.</span></em></h2>
      <p>Twelve months from now you will wish you had started today. Choose your campus and take the first step.</p>
      <a class="hx-cta" href="#campuses">Choose your campus <?= $arrow ?></a>
    </div>
  </section>
  <div class="hx-mbar"><a class="hx-cta" href="#campuses">Choose your campus</a></div>
</div>
<script>
// "The year in motion": the year line's month counter, rolling stat counters, self-drawing icons and a
// light cursor tilt. Scroll-linked effects live in CSS (scroll timelines); nothing here hides any content.
(function () {
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var body = document.body;
  // On this site the page scrolls inside <body>, not the window.
  var scroller = (body.scrollHeight > body.clientHeight + 4 && /(auto|scroll)/.test(getComputedStyle(body).overflowY)) ? body : (document.scrollingElement || document.documentElement);
  var line = document.querySelector('.hx-yearline i'), month = document.querySelector('.hx-month'), monthB = month && month.querySelector('b');
  var cssTimeline = window.CSS && CSS.supports && CSS.supports('animation-timeline: view()');
  var tick = function () {
    var max = scroller.scrollHeight - scroller.clientHeight, p = max > 0 ? Math.min(1, Math.max(0, scroller.scrollTop / max)) : 0;
    if (line && !reduce) line.style.transform = 'scaleX(' + p.toFixed(4) + ')';
    if (month) { month.classList.toggle('on', p > .04 && p < .985); if (monthB) monthB.textContent = String(Math.min(12, 1 + Math.floor(p * 12))).padStart(2, '0'); }
  };
  var busy = false;
  document.addEventListener('scroll', function () { if (!busy) { busy = true; requestAnimationFrame(function () { busy = false; tick(); }); } }, { passive: true, capture: true });
  tick();
  if (reduce) return;

  // Odometer: each digit is a column of 0-9 that rolls to its value when the numbers come into view.
  var odos = [];
  document.querySelectorAll('.hx-stats b').forEach(function (b) {
    var txt = b.textContent.trim(); if (!/^\d+$/.test(txt)) return;
    b.setAttribute('aria-label', txt);
    var wrap = document.createElement('span'); wrap.className = 'hx-odo'; wrap.setAttribute('aria-hidden', 'true');
    txt.split('').forEach(function (d, i) {
      var col = document.createElement('span'); col.style.setProperty('--d', i);
      for (var n = 0; n <= 9; n++) { var c = document.createElement('span'); c.textContent = n; col.appendChild(c); }
      col.dataset.v = d; wrap.appendChild(col);
    });
    b.textContent = ''; b.appendChild(wrap); odos.push(wrap);
  });
  var roll = function (wrap) { wrap.querySelectorAll(':scope > span').forEach(function (col) { col.style.transform = 'translateY(' + (-col.dataset.v) + 'em)'; }); };

  // Icons draw themselves when their card arrives.
  var cards = document.querySelectorAll('.hx-inside > div');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        io.unobserve(en.target);
        if (en.target.classList.contains('hx-odo')) roll(en.target); else en.target.classList.add('hx-draw');
      });
    }, { threshold: 0.35 });
    odos.forEach(function (o) { io.observe(o); });
    cards.forEach(function (c) { io.observe(c); });
  } else { odos.forEach(roll); }
  setTimeout(function () { odos.forEach(roll); }, 4000); // never leave a number showing 0

  // A light tilt toward the cursor on cards (mouse only).
  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    document.querySelectorAll('.hx-cc, .hx-inside > div, .hx-plan').forEach(function (el) {
      el.classList.add('hx-tilt');
      el.addEventListener('pointermove', function (ev) {
        var r = el.getBoundingClientRect(), x = (ev.clientX - r.left) / r.width - .5, y = (ev.clientY - r.top) / r.height - .5;
        el.style.rotate = (-y).toFixed(3) + ' ' + x.toFixed(3) + ' 0 ' + (Math.hypot(x, y) * 7).toFixed(2) + 'deg';
      });
      el.addEventListener('pointerleave', function () { el.style.rotate = ''; });
    });
  }
})();
</script>
<?php if ($stories): ?><script src="<?= e(versioned_asset('assets/js/stories.js')) ?>" defer></script><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
