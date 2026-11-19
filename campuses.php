<?php
/**
 * All campuses: a listing page with a filter by category. Open campuses link to their page with the live monthly price
 * (and the day pass when it is on); campuses that are not open yet show as "Opening soon". Everything comes from the database.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/platform.php';
require __DIR__ . '/includes/bright.php';

$all = get_all_campuses();
$open = array_values(array_filter($all, fn($c) => $c['status'] === 'PUBLISHED'));
$soon = array_values(array_filter($all, fn($c) => $c['status'] !== 'PUBLISHED'));
$rows = array_merge($open, $soon);
$hasDay = campus_daily_ready();

$cats = [];
foreach ($rows as $c) $cats[pl_campus_category($c)] = true;
$cats = array_keys($cats);
sort($cats);

$from = $open ? min(array_map(fn($c) => (float) $c['monthly_price'], $open)) : 0.0;

$pageTitle = 'Campuses — Obin Academy';
$pageDescription = 'Choose your Obin Academy campus: ' . count($open) . ' open now'
    . ($from > 0 ? ', from ' . format_money($from) . ' a month' : '')
    . '. Year-long programmes taught by professors who do it for a living, with a live class every week.';
require __DIR__ . '/includes/header.php';
?>
<?php br_sprite(); ?>
<div class="br">
  <section class="b-ph">
    <div class="b-w">
      <div>
        <span class="b-eb">Obin Academy Campuses</span>
        <h1>Choose the skill <i>that will pay you.</i></h1>
        <p class="b-lead"><?= count($open) ? e(count($open) === 1 ? 'One campus is' : count($open) . ' campuses are') . ' open.' : 'Campuses are opening soon.' ?> Each has its own professor, a year-long programme, a weekly live class and a private community.</p>
      </div>
    </div>
  </section>

  <div class="b-w" id="campuses">
    <?php if (!$rows): ?>
      <p class="b-empty">Campuses are opening soon. Check back shortly.</p>
    <?php else: ?>
    <div class="b-bar">
      <div class="b-chips" role="group" aria-label="Filter campuses">
        <button type="button" class="on" data-cat="all">All campuses</button>
        <?php foreach ($cats as $cat): ?><button type="button" data-cat="<?= e($cat) ?>"><?= e($cat) ?></button><?php endforeach; ?>
      </div>
      <small data-count><?= count($open) ?> open<?= $soon ? ' · ' . count($soon) . ' opening soon' : '' ?></small>
    </div>
    <div class="b-cgrid">
      <?php foreach ($rows as $c): echo br_campus_card($c, $hasDay); endforeach; ?>
    </div>

    <div class="b-assure">
      <div><?= br_ic('live') ?><span><b>Live class every week</b>Ask your professor live. Missed it? It is recorded.</span></div>
      <div><?= br_ic('phone') ?><span><b>Pay on your phone</b>MTN or Airtel Money. Nothing charges automatically.</span></div>
      <div><?= br_ic('pause') ?><span><b>Stop any time</b>Your progress is kept if you come back.</span></div>
      <div><?= br_ic('award') ?><span><b>A certificate</b>Finish your twelve months and receive it.</span></div>
    </div>
    <?php endif; ?>
  </div>

  <section class="b-sec" style="padding-top:20px">
    <div class="b-w"><div class="b-cta"><div><h2>Your year starts the day you join.</h2><p>Twelve months from now you will wish you had started today. Choose your campus and take the first step.</p></div><a class="b-btn a lg" href="#campuses">Choose your campus <?= br_ic('arrow') ?></a></div></div>
  </section>
</div>
<script>
// Category filter: shows the campuses in a category and updates the count. Everything stays visible without this script.
(function () {
  var chips = document.querySelectorAll('.b-chips button'), cards = document.querySelectorAll('.b-card'), count = document.querySelector('[data-count]');
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
