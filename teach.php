<?php
/**
 * Teach at Obin Academy: built around the professor's share of every payment. A receipt splitting one
 * real campus payment, an earnings calculator on today's prices, the deal, a year of weekly live
 * classes, and a three-step application (stored in instructor_applications, reviewed by admins).
 * Works without JavaScript: the steps then show as one long form.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/instructor_applications.php';

$viewer = current_user();
$ready = campuses_ready() && instructor_applications_ready();
$sharePct = CAMPUS_PROFESSOR_SHARE_DEFAULT;
$share = format_share($sharePct);
$campuses = $ready ? get_all_campuses() : [];
$sent = query_param('sent') === '1';
$old = [];
$error = '';
$errorField = '';

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (trim(post('website')) !== '') redirect('/teach.php?sent=1#apply'); // filled only by bots
    $result = submit_instructor_application($_POST, $viewer, get_client_ip());
    if (!empty($result['ok'])) {
        $_SESSION['teach_applicant'] = ['name' => campus_first_name(post('name')), 'phone' => trim(post('phone'))];
        redirect('/teach.php?sent=1#apply');
    }
    $error = $result['error'];
    $errorField = $result['field'] ?? '';
    $old = $_POST;
}
$v = fn(string $k, string $fallback = '') => e((string) ($old[$k] ?? $fallback));
$bad = fn(string $k) => $errorField === $k ? ' bad' : '';
$fieldStep = ['name' => 1, 'email' => 1, 'phone' => 1, 'headline' => 1, 'campus' => 2, 'proposed_campus' => 2, 'experience' => 2];
$openStep = $error ? ($fieldStep[$errorField] ?? 3) : 1;
$applicant = $sent ? ($_SESSION['teach_applicant'] ?? null) : null;

// Real numbers: the receipt splits a real campus's monthly price; the calculator uses the lowest
// prices on offer, so the example is never overstated.
$published = array_values(array_filter($campuses, fn($c) => $c['status'] === 'PUBLISHED'));
$exCampus = $published[0] ?? ($campuses[0] ?? null);
$exPrice = $exCampus ? (float) $exCampus['monthly_price'] : 100000.0;
$exYou = round($exPrice * $sharePct / 100);
$minMonthly = $campuses ? min(array_map(fn($c) => (float) $c['monthly_price'], $campuses)) : 100000.0;
$minYearly = $campuses ? min(array_map(fn($c) => (float) $c['yearly_price'], $campuses)) : 1000000.0;
$num = fn(float $n) => number_format(round($n));
$startMonthly = 50; $startYearly = 4;
$startEarn = ($startMonthly * $minMonthly + $startYearly * $minYearly) * $sharePct / 100;
$names = array_map(fn($c) => $c['name'], $campuses);
$nameList = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) : ($names[0] ?? '');

// The year: one bar per weekly live class; the week each monthly stage opens is marked.
$opens = [];
for ($k = 0; $k < 12; $k++) $opens[(int) round($k * 52 / 12)] = true;

$pageTitle = 'Teach at Obin Academy — Earn ' . $share . ' of every payment';
$pageDescription = 'Lead a year-long Obin Academy campus and earn ' . $share . ' of every member payment. Weekly live classes, monthly lessons, paid to your mobile money.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=Geist+Mono:wght@400;500';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/teach.css')) ?>">
<main class="tx">
  <div class="tx-wrap tx-hero">
    <div>
      <p class="tx-eyebrow">Teach at Obin Academy</p>
      <h1>Every time a member pays, <em><?= e($share) ?></em> is yours.</h1>
      <p class="lede">Lead a year-long campus in what you already do well. You teach. We bring the members, collect the money, send the reminders and run the platform.</p>
      <div class="acts"><a class="tx-btn lime" href="#apply">Apply to teach</a><a class="tx-btn" href="#earn">See what you could earn</a></div>
      <?php if ($nameList): ?><p class="fine">Open for <?= e($nameList) ?>, or a campus you propose.</p><?php endif; ?>
    </div>

    <figure class="tx-receipt" aria-label="Example: how one campus payment is split">
      <div class="tx-r-top"><span>OBIN ACADEMY · EXAMPLE PAYMENT</span><span class="tx-r-ok">Received</span></div>
      <p class="tx-r-what"><?= e($exCampus ? $exCampus['name'] . ' Campus' : 'A campus') ?> · monthly membership</p>
      <p class="tx-r-amt"><small>UGX</small><?= e($num($exPrice)) ?></p>
      <div class="tx-split" role="img" aria-label="<?= e($num($exYou)) ?> to the professor, <?= e($num($exPrice - $exYou)) ?> to Obin Academy">
        <div class="you" style="flex: <?= e((string) $sharePct) ?> 1 0;"><span>Professor</span><b><?= e($num($exYou)) ?></b></div>
        <div class="us" style="flex: <?= e((string) (100 - $sharePct)) ?> 1 0;"><span>Obin Academy</span><b><?= e($num($exPrice - $exYou)) ?></b></div>
      </div>
      <div class="tx-r-rows">
        <div class="hl"><span>Your share (<?= e($share) ?>)</span><b>UGX <?= e($num($exYou)) ?></b></div>
        <div><span>Shows in your dashboard</span><b>Instantly</b></div>
        <div><span>Sent to your mobile money</span><b>With the ID</b></div>
      </div>
    </figure>
  </div>

  <section class="tx-band" id="earn">
    <div class="tx-wrap tx-band-grid">
      <div>
        <p class="tx-eyebrow">What you could earn</p>
        <h2 class="tx-h2">Your pay grows with every member you keep.</h2>
        <p class="tx-sub">Move the sliders. This uses today's campus prices: <?= e(format_money($minMonthly)) ?> a month or <?= e(format_money($minYearly)) ?> a year. There is no cap.</p>
      </div>
      <div class="tx-calc" data-calc data-monthly="<?= e((string) $minMonthly) ?>" data-yearly="<?= e((string) $minYearly) ?>" data-share="<?= e((string) ($sharePct / 100)) ?>">
        <div class="tx-slider">
          <label for="tx-m">Members paying monthly <output id="tx-mo" for="tx-m"><?= $startMonthly ?></output></label>
          <input id="tx-m" type="range" min="0" max="300" step="5" value="<?= $startMonthly ?>">
        </div>
        <div class="tx-slider">
          <label for="tx-y">New yearly members that month <output id="tx-yo" for="tx-y"><?= $startYearly ?></output></label>
          <input id="tx-y" type="range" min="0" max="40" step="1" value="<?= $startYearly ?>">
        </div>
        <div class="tx-result">
          <small>You earn that month</small>
          <span class="big" id="tx-out"><?= e(format_money($startEarn)) ?></span>
          <span class="yr">Over a year at this pace<b id="tx-yr"><?= e(format_money($startEarn * 12)) ?></b></span>
        </div>
        <p class="fine">An example, not a promise. What you earn depends on how many members join and stay.</p>
      </div>
    </div>
  </section>

  <div class="tx-wrap">
    <section class="tx-sec" id="deal">
      <p class="tx-eyebrow">The deal</p>
      <h2 class="tx-h2">You bring the knowledge. We carry everything else.</h2>
      <div class="tx-deal">
        <div>
          <h3>You bring <b><?= e($share) ?></b></h3>
          <ul>
            <li><div><b>A twelve-month path</b><span>One stage a month, from first steps to mastery</span></div><i>Once</i></li>
            <li><div><b>Lessons for each stage</b><span>Short videos and PDF guides, recorded with our help</span></div><i>Monthly</i></li>
            <li><div><b>A live class</b><span>Teaching plus questions, on Zoom or Google Meet</span></div><i>Weekly</i></li>
            <li><div><b>Your presence in the community</b><span>Answer, encourage and review members' work</span></div><i>Daily</i></li>
          </ul>
        </div>
        <div>
          <h3>We bring <b><?= e(format_share(100 - $sharePct)) ?></b></h3>
          <ul>
            <li><div><b>Members</b><span>Marketing, the website and your campus page</span></div><i>Always</i></li>
            <li><div><b>Payments</b><span>Mobile money collection, renewals and reminders</span></div><i>Always</i></li>
            <li><div><b>The platform</b><span>Lesson hosting, community, certificates and support</span></div><i>Always</i></li>
            <li><div><b>Your pay</b><span>Tracked per payment and sent to your mobile money</span></div><i>Regularly</i></li>
          </ul>
        </div>
      </div>
    </section>

    <section class="tx-sec tight" id="year">
      <p class="tx-eyebrow">Your year</p>
      <h2 class="tx-h2">52 live classes. 12 stages. A certificate for every member who finishes.</h2>
      <div class="tx-year">
        <div class="tx-ticks" aria-hidden="true"><?php for ($w = 0; $w < 52; $w++): ?><span<?= $w === 51 ? ' class="c"' : (isset($opens[$w]) ? ' class="m"' : '') ?>></span><?php endfor; ?></div>
        <div class="tx-months" aria-hidden="true"><span>Month 1</span><?php for ($m = 2; $m <= 12; $m++): ?><span><?= $m ?></span><?php endfor; ?></div>
        <div class="tx-legend"><span><i style="background:var(--tx-brand-2)"></i>New stage opens</span><span><i style="background:var(--tx-line)"></i>Weekly live class</span><span><i style="background:var(--tx-lime)"></i>Certificates issued</span></div>
      </div>
    </section>

    <section class="tx-sec tight">
      <p class="tx-eyebrow">Who we are looking for</p>
      <div class="tx-who">
        <p>You get <em>real results</em> in your field, and you can show them.</p>
        <p>You explain things <em>simply</em>, one step at a time.</p>
        <p>You can show up <em>every week</em> for a full year.</p>
        <p>You care about your students' progress, <em>not only your own</em>.</p>
      </div>
    </section>
  </div>

  <section class="tx-apply" id="apply">
    <div class="tx-wrap tx-apply-grid">
      <div>
        <p class="tx-eyebrow">Apply</p>
        <h2>Tell us what you teach.</h2>
        <p class="lead">Three short steps, about five minutes. We read every application.</p>
        <div class="tx-after">
          <div><span>Within a week</span><b>We reply, by WhatsApp or email</b></div>
          <div><span>Next</span><b>A short call about you and the campus</b></div>
          <div><span>Then</span><b>We plan your twelve stages and record month one together</b></div>
          <div><span>Opening day</span><b>Members join, and you start earning</b></div>
        </div>
      </div>

      <?php if (!$ready): ?>
        <div class="tx-form"><p class="tx-closed">Applications open shortly. Please check back soon.</p></div>
      <?php elseif ($sent): ?>
        <div class="tx-form"><div class="tx-done" role="status">
          <div class="seal" aria-hidden="true">&#10003;</div>
          <h3>Application received</h3>
          <p><?= $applicant ? 'Thank you, ' . e($applicant['name']) . '. We will call or WhatsApp you on ' . e($applicant['phone']) . ' within a week.' : 'Thank you. We will call or WhatsApp you within a week.' ?> A copy is in your email.</p>
        </div></div>
      <?php else: ?>
        <form method="post" class="tx-form" id="tx-form" data-open-step="<?= $openStep ?>" novalidate>
          <?= csrf_field() ?>
          <div class="tx-hp" aria-hidden="true"><label for="tx-web">Website</label><input id="tx-web" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="tx-steps" role="tablist" aria-label="Application steps">
            <button type="button" role="tab" id="tx-t1" aria-controls="tx-p1"><small>Step 1</small><b>About you</b></button>
            <button type="button" role="tab" id="tx-t2" aria-controls="tx-p2"><small>Step 2</small><b>Your campus</b></button>
            <button type="button" role="tab" id="tx-t3" aria-controls="tx-p3"><small>Step 3</small><b>Your proof</b></button>
          </div>

          <div class="tx-pane" id="tx-p1" role="tabpanel" aria-labelledby="tx-t1">
            <h3>About you</h3>
            <?php if ($error && $openStep === 1): ?><div class="tx-err" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="tx-field<?= $bad('name') ?>"><label for="tx-name">Full name</label><input id="tx-name" name="name" autocomplete="name" required minlength="2" maxlength="120" value="<?= $v('name', $viewer['name'] ?? '') ?>"></div>
            <div class="tx-f2">
              <div class="tx-field<?= $bad('email') ?>"><label for="tx-email">Email</label><input id="tx-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= $v('email', $viewer['email'] ?? '') ?>"></div>
              <div class="tx-field<?= $bad('phone') ?>"><label for="tx-phone">Phone or WhatsApp</label><input id="tx-phone" name="phone" type="tel" autocomplete="tel" required minlength="9" maxlength="32" placeholder="07XX XXX XXX" value="<?= $v('phone', $viewer['phone'] ?? '') ?>"></div>
            </div>
            <div class="tx-field<?= $bad('headline') ?>"><label for="tx-head">What you do, in one line</label><input id="tx-head" name="headline" required minlength="5" maxlength="200" placeholder="e.g. Forex trader since 2017, funded account holder" value="<?= $v('headline') ?>"></div>
            <div class="tx-pane-foot"><span class="note">We only use these to contact you about teaching.</span><button type="button" class="tx-btn ink" data-go="2">Next: your campus</button></div>
          </div>

          <div class="tx-pane" id="tx-p2" role="tabpanel" aria-labelledby="tx-t2">
            <h3>Your campus</h3>
            <?php if ($error && $openStep === 2): ?><div class="tx-err" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="tx-field<?= $bad('campus') ?>" role="radiogroup" aria-labelledby="tx-campus-l">
              <span class="lbl" id="tx-campus-l">Campus you want to teach</span>
              <div class="tx-chips">
                <?php foreach ($campuses as $c): ?>
                  <label><input type="radio" name="campus" value="<?= (int) $c['id'] ?>" required<?= ($old['campus'] ?? '') === (string) $c['id'] ? ' checked' : '' ?>><span><?= e($c['name']) ?></span></label>
                <?php endforeach; ?>
                <label class="new"><input type="radio" name="campus" value="new" data-new<?= ($old['campus'] ?? '') === 'new' ? ' checked' : '' ?>><span>+ Propose a new campus</span></label>
              </div>
            </div>
            <div class="tx-field<?= $bad('proposed_campus') ?>" data-new-field<?= ($old['campus'] ?? '') === 'new' ? '' : ' hidden' ?>><label for="tx-new">Name of the new campus</label><input id="tx-new" name="proposed_campus" maxlength="160" placeholder="e.g. Real Estate" value="<?= $v('proposed_campus') ?>"></div>
            <div class="tx-field<?= $bad('experience') ?>"><label for="tx-exp">Your experience</label><textarea id="tx-exp" name="experience" rows="4" required minlength="40" maxlength="5000" placeholder="How long you have done this, and what you have done"><?= $v('experience') ?></textarea></div>
            <div class="tx-pane-foot"><button type="button" class="tx-btn ghost-ink" data-go="1">Back</button><button type="button" class="tx-btn ink" data-go="3">Next: your proof</button></div>
          </div>

          <div class="tx-pane" id="tx-p3" role="tabpanel" aria-labelledby="tx-t3">
            <h3>Your proof</h3>
            <?php if ($error && $openStep === 3): ?><div class="tx-err" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="tx-field"><label for="tx-res">Results you can show <small>(optional)</small></label><textarea id="tx-res" name="results" rows="3" maxlength="3000" placeholder="Income, clients, students, followers, anything real"><?= $v('results') ?></textarea></div>
            <div class="tx-f2">
              <div class="tx-field"><label for="tx-links">Where we can see your work <small>(optional)</small></label><input id="tx-links" name="links" maxlength="500" placeholder="Instagram, TikTok, YouTube, website" value="<?= $v('links') ?>"></div>
              <div class="tx-field<?= $bad('sample_url') ?>"><label for="tx-sample">A short video of you teaching <small>(optional)</small></label><input id="tx-sample" name="sample_url" type="url" maxlength="500" placeholder="https://" value="<?= $v('sample_url') ?>"></div>
            </div>
            <div class="tx-pane-foot"><button type="button" class="tx-btn ghost-ink" data-go="2">Back</button><button type="submit" class="tx-btn lime">Send application</button></div>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>
<script>
(function () {
  var calc = document.querySelector('[data-calc]');
  if (calc) {
    var P = +calc.dataset.monthly, Y = +calc.dataset.yearly, S = +calc.dataset.share;
    var m = document.getElementById('tx-m'), y = document.getElementById('tx-y');
    var fmt = function (n) { return 'UGX ' + Math.round(n).toLocaleString('en-US'); };
    var run = function () {
      document.getElementById('tx-mo').textContent = m.value;
      document.getElementById('tx-yo').textContent = y.value;
      var month = (m.value * P + y.value * Y) * S;
      document.getElementById('tx-out').textContent = fmt(month);
      document.getElementById('tx-yr').textContent = fmt(month * 12);
    };
    m.addEventListener('input', run); y.addEventListener('input', run);
  }

  var form = document.getElementById('tx-form');
  if (!form) return;
  form.classList.add('js');
  var tabs = [1, 2, 3].map(function (i) { return document.getElementById('tx-t' + i); });
  var panes = [1, 2, 3].map(function (i) { return document.getElementById('tx-p' + i); });
  var current = 1;
  function show(n, focus) {
    current = n;
    tabs.forEach(function (b, i) {
      b.setAttribute('aria-selected', String(i + 1 === n));
      b.classList.toggle('done', i + 1 < n);
      panes[i].hidden = i + 1 !== n;
    });
    if (focus) { var f = panes[n - 1].querySelector('input:not([type=radio]), textarea, input[type=radio]'); if (f) f.focus({ preventScroll: true }); }
  }
  // A step is complete when every visible required field in it is valid.
  function stepValid(n) {
    var ok = true;
    panes[n - 1].querySelectorAll('input, textarea').forEach(function (el) {
      if (el.closest('[hidden]') || el.type === 'hidden' || el.closest('.tx-hp')) return;
      var bad = !el.checkValidity();
      if (el.type === 'radio') bad = !panes[n - 1].querySelector('input[name="' + el.name + '"]:checked');
      el.closest('.tx-field').classList.toggle('bad', bad);
      if (bad && ok) { ok = false; if (el.type !== 'radio') el.focus(); }
    });
    return ok;
  }
  function goTo(n) {
    for (var s = current; s < n; s++) { if (!stepValid(s)) { show(s); return; } }
    show(n, true);
  }
  tabs.forEach(function (b, i) { b.addEventListener('click', function () { goTo(i + 1); }); });
  form.querySelectorAll('[data-go]').forEach(function (b) { b.addEventListener('click', function () { goTo(+b.dataset.go); }); });
  form.addEventListener('submit', function (e) { if (!stepValid(1)) { e.preventDefault(); show(1); } else if (!stepValid(2)) { e.preventDefault(); show(2); } else if (!stepValid(3)) { e.preventDefault(); } });

  var newField = form.querySelector('[data-new-field]');
  form.querySelectorAll('input[name="campus"]').forEach(function (r) {
    r.addEventListener('change', function () {
      newField.hidden = !r.hasAttribute('data-new');
      newField.querySelector('input').required = !newField.hidden;
      r.closest('.tx-field').classList.remove('bad');
      if (!newField.hidden) newField.querySelector('input').focus();
    });
  });
  if (!newField.hidden) newField.querySelector('input').required = true;
  show(+form.dataset.openStep || 1);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
