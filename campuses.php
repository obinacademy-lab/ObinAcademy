<?php
/**
 * The campuses prospectus: every published campus as one aligned index, with the terms beside the headline.
 * Draft campuses never appear here; an admin sees a note pointing to where they are set up.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

$campuses = get_published_campuses();
$viewer = current_user();
$minMonthly = $campuses ? min(array_map(fn($c) => (float) $c['monthly_price'], $campuses)) : 100000;
$minYearly = $campuses ? min(array_map(fn($c) => (float) $c['yearly_price'], $campuses)) : 1000000;
$samePrices = $campuses && count(array_unique(array_map(fn($c) => $c['monthly_price'] . '/' . $c['yearly_price'], $campuses))) === 1;
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

$pageTitle = 'Obin Academy Campuses — Learn a skill for one year';
$pageDescription = 'Year-long campuses at Obin Academy: lessons, PDF guides and a weekly live class with a professor, paid monthly or yearly with mobile money, ending with a certificate.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
<main class="cx">
  <section class="cx-deep">
    <div class="cx-wrap cx-hero">
      <div>
        <span class="cx-label">Obin Academy Campuses</span>
        <h1>Learn a skill<br>for <i>one year.</i><br>Use it for life.</h1>
        <p class="lede">Each campus is led by a professor chosen for real results. A year-long programme of video lessons, PDF guides and a live class every week, finishing with an Obin Academy certificate.</p>
        <div class="acts">
          <a class="cx-btn light" href="#campuses">View the campuses</a>
          <?php if (!$viewer): ?><a class="cx-btn ghost" href="<?= e(base_url('login.php')) ?>">Log in</a><?php else: ?><a class="cx-btn ghost" href="<?= e(base_url('dashboard.php')) ?>">My dashboard</a><?php endif; ?>
        </div>
      </div>
      <div class="cx-terms" aria-label="Terms">
        <div><span>Programme</span><b>12 months</b></div>
        <div><span>Live classes</span><b>Every week</b></div>
        <div><span>Monthly</span><b><?= $samePrices ? '' : 'From ' ?><?= e(format_money($minMonthly)) ?></b></div>
        <div><span>Yearly</span><b><?= $samePrices ? '' : 'From ' ?><?= e(format_money($minYearly)) ?></b></div>
        <div><span>Awarded</span><b>Certificate</b></div>
      </div>
    </div>
  </section>

  <section class="cx-wrap cx-index" id="campuses">
    <div class="cx-ihead">
      <div><span class="cx-label">The campuses</span><h2>Choose where you want to grow</h2></div>
      <p>Each campus has its own professor, curriculum, weekly live class and private community. Your year begins the day you join.</p>
    </div>
    <div class="cx-cols cx-label" aria-hidden="true"><span></span><span>Campus</span><span>What you will learn</span><span>Monthly</span><span>Yearly</span><span></span></div>
    <div class="cx-list">
      <?php if (!$campuses): ?>
        <div class="cx-empty">
          Our first campuses open soon. <a href="<?= e(WHATSAPP_COMMUNITY_URL) ?>" target="_blank" rel="noopener" style="color:var(--cx-brand); font-weight:600;">Join our WhatsApp community</a> to hear the moment they do.
          <?php if ($viewer && $viewer['role'] === 'ADMIN'): ?><br><a href="<?= e(base_url('dashboard/admin/campuses.php')) ?>" style="color:var(--cx-brand); font-weight:600;">Admin: set up and publish campuses</a><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($campuses as $c): $href = base_url('campus.php?slug=' . $c['slug']); ?>
        <a class="cx-row" href="<?= e($href) ?>">
          <?php $photo = campus_photo($c); ?>
          <?php if ($photo): ?><span class="cx-gl ph"><img src="<?= e($photo) ?>" alt="" loading="lazy"></span><?php else: ?><span class="cx-gl"><?= campus_icon($c['icon']) ?></span><?php endif; ?>
          <h3><?= e($c['name']) ?></h3>
          <span class="d"><?= e($c['tagline']) ?></span>
          <span class="pr"><?= e(format_money((float) $c['monthly_price'])) ?><small>per month</small></span>
          <span class="pr"><?= e(format_money((float) $c['yearly_price'])) ?><small>per year</small></span>
          <span class="cx-go"><?= $arrow ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="cx-trio">
    <div><span class="cx-label">Each week</span><h3>A live class</h3><p>Your professor teaches live and answers questions, and the recording stays in your campus.</p></div>
    <div><span class="cx-label">Each month</span><h3>A new stage</h3><p>Video lessons and PDF guides released in order, so you always know what comes next.</p></div>
    <div><span class="cx-label">After twelve months</span><h3>Your certificate</h3><p>Complete the full path to receive your Obin Academy certificate.</p></div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
