<?php
/**
 * Home page, "The Offer": a long-form sales page. The promise, "does this sound like you?", the old way vs a campus,
 * everything inside one campus, every campus with its real monthly price and price per day, why joining is safe,
 * why Obin Academy exists, questions, and a closing call. Campuses and prices always come from the database.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

$all = get_all_campuses();
$published = array_values(array_filter($all, fn($c) => $c['status'] === 'PUBLISHED'));
$upcoming = array_values(array_filter($all, fn($c) => $c['status'] !== 'PUBLISHED'));
$rows = array_merge($published, $upcoming);

$perDay = fn(float $v) => format_money(round($v / 30 / 100) * 100);
$priced = $published ?: $all;
$from = $priced ? min(array_map(fn($c) => (float) $c['monthly_price'], $priced)) : 0.0;
$fromText = $from > 0 ? format_money($from) : null;
$joinUrl = fn(array $c) => base_url('campus.php?slug=' . rawurlencode($c['slug']) . '#join');
// Day pass (24 hours), once migration/add-daily-plan.sql has run. Until then the page reads exactly as before.
$hasDay = campus_daily_ready();
$dayPrices = $hasDay ? array_filter(array_map(fn($c) => (float) $c['daily_price'], $published), fn($v) => $v > 0) : [];
$dayFrom = $dayPrices ? min($dayPrices) : 0.0;

$newest = null;
foreach ($published as $c) {
    if (!$newest || strcmp((string) $c['created_at'], (string) $newest['created_at']) > 0) $newest = $c;
}

$icon = fn(string $id) => '<svg class="ho-i" aria-hidden="true"><use href="#ho-' . $id . '"/></svg>';

$pageTitle = 'Obin Academy Campuses — Learn a skill that pays, in 12 months';
$pageDescription = 'Join an Obin Academy campus: a year-long programme with a professor who does it for a living. Step-by-step lessons, a live class every week and a private community'
    . ($fromText ? ', from ' . $fromText . ' a month' : '') . ', paid with MTN or Airtel Money.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400;1,6..72,500&family=Geist+Mono:wght@500;600';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/home-offer.css')) ?>">
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="ho-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
  <symbol id="ho-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
  <symbol id="ho-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
  <symbol id="ho-video" viewBox="0 0 24 24"><rect x="3" y="5" width="13" height="14" rx="3"/><path d="m16 10 5-3v10l-5-3"/></symbol>
  <symbol id="ho-live" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19 5a10 10 0 0 1 0 14M5 19A10 10 0 0 1 5 5"/></symbol>
  <symbol id="ho-user" viewBox="0 0 24 24"><circle cx="12" cy="7" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></symbol>
  <symbol id="ho-chat" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></symbol>
  <symbol id="ho-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></symbol>
  <symbol id="ho-award" viewBox="0 0 24 24"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/></symbol>
  <symbol id="ho-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></symbol>
  <symbol id="ho-pause" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M10 9v6M14 9v6"/></symbol>
  <symbol id="ho-redo" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></symbol>
</svg>

<div class="ho">
  <?php if ($newest): ?>
    <div class="ho-ann">Now open: the <b><?= e($newest['name']) ?> Campus</b> — <?= e(format_money((float) $newest['monthly_price'])) ?> a month.<a href="<?= e(base_url('campus.php?slug=' . rawurlencode($newest['slug']))) ?>">See it <?= $icon('arrow') ?></a></div>
  <?php endif; ?>

  <header class="ho-hero" id="ho-hero">
    <div class="ho-wrap">
      <span class="ho-kick">OBIN ACADEMY CAMPUSES · KAMPALA, UGANDA</span>
      <h1 class="ho-h1">School taught you to look for a job. <span class="ho-mark" id="ho-mark"><i>We teach you to earn</i></span> with a skill.</h1>
      <p class="ho-sub">Join a campus, learn step by step from a professor who does it for a living, and build income you control — in twelve months, on your phone.</p>
      <div class="ho-acts">
        <a class="ho-btn blue" href="#prices">Choose your campus <?= $icon('arrow') ?></a>
        <a class="ho-btn line" href="#offer">See everything you get</a>
      </div>
      <?php if ($fromText && $dayFrom > 0): ?><p class="ho-from">From <b><?= e($fromText) ?></b> a month — or <b><?= e(format_money($dayFrom)) ?></b> for a single day.</p>
      <?php elseif ($fromText): ?><p class="ho-from">From <b><?= e($fromText) ?></b> a month — about <b><?= e($perDay($from)) ?></b> a day.</p><?php endif; ?>
      <div class="ho-trust">
        <span><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN Mobile Money" width="26" height="26"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel Money" width="26" height="26"></span>
        <span><?= $icon('check') ?>Stop any time</span>
        <span><?= $icon('check') ?>Certificate at the end</span>
      </div>
    </div>
  </header>

  <div class="ho-strip">
    <figure data-rv style="--d:0"><img src="<?= e(versioned_asset('assets/img/home/offer-students.jpg')) ?>" alt="Two students learning together on a laptop" loading="lazy"><figcaption>Learn together</figcaption></figure>
    <figure data-rv style="--d:1"><img src="<?= e(versioned_asset('assets/img/home/offer-professor.jpg')) ?>" alt="A professor teaching at a whiteboard" style="object-position:center 30%"><figcaption>Taught by people who do it</figcaption></figure>
    <figure data-rv style="--d:2"><img src="<?= e(versioned_asset('assets/img/home/offer-couch.jpg')) ?>" alt="A learner studying on a laptop at home" loading="lazy"><figcaption>From your couch</figcaption></figure>
  </div>

  <section class="ho-sec">
    <div class="ho-col">
      <span class="ho-eb" data-rv>Be honest with yourself</span>
      <h2 class="ho-h2" data-rv>Does this sound <i>like you?</i></h2>
      <div class="ho-you">
        <?php foreach ([
            'Your salary or allowance is finished before the month is.',
            'You finished school, and the job you were promised never came.',
            'You have watched hundreds of free videos, and nothing changed.',
            'You want a skill you can use from your phone, anywhere in Uganda.',
            'You are ready to put in the work if someone gives you a clear plan.',
        ] as $i => $line): ?>
          <div data-rv style="--d:<?= $i ?>"><span class="ho-tick"><?= $icon('check') ?></span><?= e($line) ?></div>
        <?php endforeach; ?>
      </div>
      <p class="ho-pull" data-rv>If you nodded at even one, a campus was built <b>for you</b> — not for people who only think about it, but for people ready to move.</p>
    </div>
  </section>

  <section class="ho-sec flush">
    <div class="ho-col ho-center"><span class="ho-eb" data-rv>A better way to learn</span><h2 class="ho-h2" data-rv>Four years of theory, or <i>one year of practice?</i></h2></div>
    <div class="ho-vs">
      <div class="old" data-rv="left"><h3>The old way</h3><ul>
        <li><?= $icon('x') ?><span>Three to four years before you earn anything</span></li>
        <li><?= $icon('x') ?><span>Millions of shillings in fees</span></li>
        <li><?= $icon('x') ?><span>Taught by people who have never done it</span></li>
        <li><?= $icon('x') ?><span>Notes to memorise, then hope for a job</span></li>
      </ul></div>
      <div class="new" data-rv="right"><h3>An Obin <i>campus</i></h3><ul>
        <li><?= $icon('check') ?><span>Twelve practical stages, taught as a class</span></li>
        <li><?= $icon('check') ?><span><?= $fromText ? 'From ' . e($fromText) . ' a month, paid on your phone' : 'Paid monthly or yearly, on your phone' ?></span></li>
        <li><?= $icon('check') ?><span>Taught by a professor who earns from the skill</span></li>
        <li><?= $icon('check') ?><span>You practise every week and use the skill as you learn</span></li>
      </ul></div>
    </div>
  </section>

  <section class="ho-sec flush" id="offer">
    <div class="ho-col">
      <span class="ho-eb" data-rv>The offer</span>
      <h2 class="ho-h2" data-rv>Here's <i>everything</i> you get.</h2>
      <p class="ho-lead" data-rv>One membership opens all of it for your campus, on your phone or computer.</p>
      <div class="ho-stack" data-rv="zoom">
        <?php foreach ([
            ['video', 'Step-by-step video lessons', 'A new stage each time your class is ready, in the right order.'],
            ['live', 'A live class every week', "52 live classes across your year. Missed one? It's recorded."],
            ['user', 'A professor who does it for a living', 'Chosen by Obin Academy for real results in the skill they teach.'],
            ['chat', 'A private community', 'Share your wins, ask for help and stay accountable.'],
            ['file', 'PDF guides and worksheets', 'Checklists and notes to download and use while you practise.'],
            ['award', 'Your Obin Academy certificate', 'Finish your twelve months and receive it.'],
        ] as [$ic, $title, $text]): ?>
          <div class="ho-item"><span class="ic"><?= $icon($ic) ?></span><div><h3><?= e($title) ?></h3><p><?= e($text) ?></p></div><span class="tag">INCLUDED</span></div>
        <?php endforeach; ?>
        <div class="ho-stack-ft"><p>All of it, for <b>one simple monthly price</b><?= $fromText ? ' — from ' . e($fromText) : '' ?>.</p><a class="ho-btn lime sm" href="#prices">See the prices <?= $icon('arrow') ?></a></div>
      </div>
    </div>
  </section>

  <section class="ho-sec flush" id="prices">
    <div class="ho-wrap" style="max-width:1000px">
      <div class="ho-center"><span class="ho-eb" data-rv>Pick your campus</span><h2 class="ho-h2" data-rv>Less than you spend on <i>airtime.</i></h2><p class="ho-lead" data-rv>Each campus sets its own price. <?= $dayFrom > 0 ? 'Pay for a single day, a month, or the whole year.' : 'Pay monthly, or once for the whole year.' ?> Nothing is charged automatically.</p></div>
      <?php if ($rows): ?>
        <div class="ho-prices" data-rv>
          <div class="ho-phead"><span></span><span>Campus</span><span>Per month</span><span class="d"><?= $hasDay ? 'Day pass' : 'Per day' ?></span><span class="x"></span></div>
          <?php foreach ($rows as $c):
              $open = $c['status'] === 'PUBLISHED';
              $photo = campus_photo($c);
          ?>
            <div class="ho-prow<?= $open ? '' : ' soon' ?>">
              <span class="ph"><?= $photo ? '<img src="' . e($photo) . '" alt="" loading="lazy">' : campus_icon($c['icon'], 1.7) ?></span>
              <div><h3><?= e($c['name']) ?></h3><small><?= e($c['tagline']) ?></small></div>
              <?php if ($open): ?>
                <span class="m"><?= e(format_money((float) $c['monthly_price'])) ?></span>
                <?php if ($hasDay): ?><span class="d"><?= (float) $c['daily_price'] > 0 ? e(format_money((float) $c['daily_price'])) . ' / 24h' : '—' ?></span>
                <?php else: ?><span class="d">≈ <?= e($perDay((float) $c['monthly_price'])) ?>/day</span><?php endif; ?>
                <a class="ho-btn blue sm" href="<?= e($joinUrl($c)) ?>">Join <?= $icon('arrow') ?></a>
              <?php else: ?>
                <span class="m">Opening soon</span><span class="d"></span><span></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="ho-safe">
        <div data-rv style="--d:0"><span class="ic"><?= $icon('shield') ?></span><h3>No auto-charges</h3><p>Nothing is ever taken from your phone without your PIN.</p></div>
        <div data-rv style="--d:1"><span class="ic"><?= $icon('pause') ?></span><h3>Stop any time</h3><p>On monthly? Don't pay again and your access simply ends.</p></div>
        <div data-rv style="--d:2"><span class="ic"><?= $icon('redo') ?></span><h3>Progress kept</h3><p>Come back later and pick up exactly where you stopped.</p></div>
        <div data-rv style="--d:3"><span class="ic"><?= $icon('video') ?></span><h3>Never miss a class</h3><p>Every live class is recorded and added to your lessons.</p></div>
      </div>
    </div>
  </section>

  <section class="ho-sec flush">
    <div class="ho-wrap" style="max-width:1000px">
      <div class="ho-note" data-rv>
        <img src="<?= e(versioned_asset('assets/img/founder.jpg')) ?>" alt="The founder of Obin Academy" loading="lazy">
        <div><span class="ho-eb">Why Obin Academy exists</span><p>Obin Academy was started in Uganda so that a practical, earning skill doesn't take four years and millions of shillings — it takes twelve months, a phone, and a professor who has done it.</p><small>The founder, Obin Academy</small></div>
      </div>
    </div>
  </section>

  <section class="ho-sec flush" id="faq">
    <div class="ho-col">
      <span class="ho-eb" data-rv>Questions</span>
      <h2 class="ho-h2" data-rv>Before you <i>join.</i></h2>
      <div class="ho-faq">
        <?php foreach ([
            ['Do I need any experience?', 'No. Every campus starts from the basics in month one and builds up step by step. If you can use a smartphone, you can follow along.'],
            ['Can I learn on my phone?', 'Yes. Lessons, PDFs, live classes and the community all work on a smartphone. A computer helps for some campuses, but it is not required to start.'],
            ['How do I pay?', 'With MTN or Airtel Mobile Money. A prompt arrives on your phone; enter your PIN to confirm. ' . ($dayFrom > 0 ? 'Pay for a single day (24 hours, then the campus closes until you pay again), monthly (30 days at a time) or once for the year.' : 'Pay monthly (30 days at a time) or once for the year.')],
            ['What if I miss a live class?', 'Every live class is recorded and added to your lessons, so you can watch it later.'],
            ['Can I stop any time?', "Yes. Nothing is charged automatically. If you're on the monthly plan and don't pay again, your access simply ends. Your progress is kept if you come back."],
            ['Can I join more than one campus?', 'Yes. Each campus has its own membership, so you can join a second one whenever you are ready.'],
            ['Will I make money?', "The campuses teach practical skills that people use to earn. Your results depend on how much you practise and apply what you learn. We don't promise any income."],
        ] as $i => [$q, $a]): ?>
          <details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?><i></i></summary><p><?= e($a) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="ho-final">
    <div class="ho-wrap">
      <h2 data-rv>Your year starts<br><i>the day you join.</i></h2>
      <p data-rv style="--d:1">Twelve months from now you will wish you had started today.</p>
      <a class="ho-btn lime" href="#prices" data-rv style="--d:2">Choose your campus <?= $icon('arrow') ?></a>
    </div>
  </section>

  <div class="ho-sticky" id="ho-sticky"><?= $fromText ? 'From <b>' . e($fromText) . '</b>/month · ' : '' ?>Pay with MTN or Airtel <a class="ho-btn lime sm" href="#prices">Choose your campus</a></div>
  <div class="ho-mbar" id="ho-mbar"><div class="mp"><?= $fromText ? 'From<b>' . e($fromText) . ' / month</b>' : 'Obin Academy<b>Campuses</b>' ?></div><a class="ho-btn blue sm" href="#prices">Choose campus</a></div>
</div>
<script>
// Lime highlighter on the promise, sections rising in as they arrive, and the join bar once the hero has scrolled
// away. Content is fully visible without this script; it only opts elements into the entrance.
(function () {
  var root = document.querySelector('.ho');
  var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  var mark = document.getElementById('ho-mark');
  setTimeout(function () { mark.classList.add('go'); }, 300);
  if (reduce || !('IntersectionObserver' in window)) return;
  root.classList.add('ho-js');
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { threshold: .12, rootMargin: '0px 0px -6% 0px' });
  root.querySelectorAll('[data-rv]').forEach(function (el) { io.observe(el); });
  var bars = [document.getElementById('ho-sticky'), document.getElementById('ho-mbar')];
  new IntersectionObserver(function (es) {
    es.forEach(function (en) { var past = !en.isIntersecting && en.boundingClientRect.top < 0; bars.forEach(function (b) { b.classList.toggle('show', past); }); });
  }).observe(document.getElementById('ho-hero'));
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
