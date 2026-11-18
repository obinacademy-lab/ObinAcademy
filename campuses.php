<?php
/**
 * All campuses: a listing page with a filter by category. Open campuses link to their page with the live monthly price
 * (and the day pass when it is on); campuses that are not open yet show as "Opening soon". Everything comes from the database.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/platform.php';

$all = get_all_campuses();
$open = array_values(array_filter($all, fn($c) => $c['status'] === 'PUBLISHED'));
$soon = array_values(array_filter($all, fn($c) => $c['status'] !== 'PUBLISHED'));
$rows = array_merge($open, $soon);
$hasDay = campus_daily_ready();

$cats = [];
foreach ($rows as $c) $cats[pl_campus_category($c)] = true;
$cats = array_keys($cats);
sort($cats);

$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
$from = $open ? min(array_map(fn($c) => (float) $c['monthly_price'], $open)) : 0.0;

$pageTitle = 'Campuses — Obin Academy';
$pageDescription = 'Choose your Obin Academy campus: ' . count($open) . ' open now'
    . ($from > 0 ? ', from ' . format_money($from) . ' a month' : '')
    . '. Year-long programmes taught by professors who do it for a living, with a live class every week.';
require __DIR__ . '/includes/header.php';
?>
<section class="cp-hero">
  <div class="pl-w">
    <div class="ht">
      <span class="cp-eb">Obin Academy Campuses</span>
      <h1>Choose the skill <i>that will pay you.</i></h1>
      <p class="cp-sub"><?= count($open) ? e(count($open) === 1 ? 'One campus is' : count($open) . ' campuses are') . ' open.' : 'Campuses are opening soon.' ?> Each has its own professor, a year-long programme, a weekly live class and a private community.</p>
      <div class="cp-acts">
        <a class="pl-btn p lg" href="#campuses">Browse campuses <?= $arrow ?></a>
        <a class="pl-btn o lg" href="<?= e(base_url('index.php#offer')) ?>">How it works</a>
      </div>
      <div class="cp-trust">
        <span><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN Mobile Money" width="26" height="26"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel Money" width="26" height="26"></span>
        <span><?= $check ?>Stop any time</span>
        <span><?= $check ?>Weekly live class</span>
        <span><?= $check ?>Certificate included</span>
      </div>
    </div>
    <div class="cp-mos" aria-hidden="true">
      <img class="m1" src="<?= e(versioned_asset('assets/img/home/offer-professor.jpg')) ?>" alt="">
      <img class="m2" src="<?= e(versioned_asset('assets/img/home/offer-students.jpg')) ?>" alt="">
      <div class="badge"><i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19 5a10 10 0 0 1 0 14M5 19A10 10 0 0 1 5 5"/></svg></i><div><b>Live class every week</b><small>With your professor</small></div></div>
    </div>
  </div>
</section>

<div class="pl-w" id="campuses">
  <?php if (!$rows): ?>
    <p class="cp-empty">Campuses are opening soon. Check back shortly.</p>
  <?php else: ?>
  <div class="cp-bar">
    <div class="cp-chips" role="group" aria-label="Filter campuses">
      <button type="button" class="on" data-cat="all">All campuses</button>
      <?php foreach ($cats as $cat): ?><button type="button" data-cat="<?= e($cat) ?>"><?= e($cat) ?></button><?php endforeach; ?>
    </div>
    <span class="cp-count" data-count><?= count($open) ?> open<?= $soon ? ' · ' . count($soon) . ' opening soon' : '' ?></span>
  </div>
  <div class="cp-grid">
    <?php foreach ($rows as $c):
        $isOpen = $c['status'] === 'PUBLISHED';
        $photo = campus_photo($c);
        $tag = $isOpen ? 'a' : 'div';
        $day = $hasDay ? (float) ($c['daily_price'] ?? 0) : 0.0;
    ?>
      <<?= $tag ?> class="cp-card<?= $isOpen ? '' : ' soon' ?>" data-cat="<?= e(pl_campus_category($c)) ?>" data-open="<?= $isOpen ? '1' : '0' ?>"<?= $isOpen ? ' href="' . e(base_url('campus.php?slug=' . rawurlencode($c['slug']))) . '"' : '' ?>>
        <div class="im"><?php if ($photo): ?><img src="<?= e($photo) ?>" alt="" loading="lazy"><?php else: ?><?= campus_icon($c['icon'], 1.6) ?><?php endif; ?><span class="cp-tag"><?= $isOpen ? 'Open now' : 'Opening soon' ?></span></div>
        <div class="bd">
          <h3><?= e($c['name']) ?></h3>
          <p><?= e($c['tagline']) ?></p>
          <div class="pr">
            <?php if ($isOpen): ?>
              <div><b><?= e(format_money((float) $c['monthly_price'])) ?> <small>/ month</small></b><?php if ($day > 0): ?><span>Day pass <?= e(format_money($day)) ?></span><?php endif; ?></div>
              <span class="go">View campus <?= $arrow ?></span>
            <?php else: ?>
              <div><b style="font-family:var(--font-sans);font-weight:600;font-size:15px;">Opening soon</b></div>
            <?php endif; ?>
          </div>
        </div>
      </<?= $tag ?>>
    <?php endforeach; ?>
  </div>

  <div class="cp-assure">
    <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19 5a10 10 0 0 1 0 14M5 19A10 10 0 0 1 5 5"/></svg><span><b>Live class every week</b>Ask your professor live. Missed it? It is recorded.</span></div>
    <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg><span><b>Pay on your phone</b>MTN or Airtel Money. Nothing charges automatically.</span></div>
    <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M10 9v6M14 9v6"/></svg><span><b>Stop any time</b>Your progress is kept if you come back.</span></div>
    <div><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/></svg><span><b>A certificate</b>Finish your twelve months and receive it.</span></div>
  </div>

  <div class="cp-cta">
    <div><h2>Your year starts the day you join.</h2><p>Twelve months from now you will wish you had started today. Choose your campus and take the first step.</p></div>
    <a class="pl-btn a lg" href="#campuses">Choose your campus <?= $arrow ?></a>
  </div>
  <?php endif; ?>
</div>

<script>
// Category filter: shows the campuses in a category and updates the count. Everything stays visible without this script.
(function () {
  var chips = document.querySelectorAll('.cp-chips button'), cards = document.querySelectorAll('.cp-card'), count = document.querySelector('[data-count]');
  chips.forEach(function (b) {
    b.addEventListener('click', function () {
      chips.forEach(function (x) { x.classList.toggle('on', x === b); });
      var cat = b.dataset.cat, open = 0, soon = 0;
      cards.forEach(function (c) {
        var show = cat === 'all' || c.dataset.cat === cat;
        c.hidden = !show;
        if (show) { if (c.dataset.open === '1') open++; else soon++; }
      });
      if (count) count.textContent = open + ' open' + (soon ? ' · ' + soon + ' opening soon' : '');
    });
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
