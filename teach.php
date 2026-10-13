<?php
/**
 * Teach at Obin Academy: a faculty prospectus. The professor's week (an example, live), the role, the
 * year of weekly live classes, the open campuses, questions, and a three-step application (stored in
 * instructor_applications, reviewed by admins). Pay is deliberately not shown publicly: it is
 * explained to applicants on the call. Works without JavaScript: the steps then show as one long form.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/instructor_applications.php';

$viewer = current_user();
$ready = campuses_ready() && instructor_applications_ready();
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

// The hero card shows this calendar week, with the live class on Thursday.
$monday = new DateTimeImmutable('monday this week');
$exampleCampus = 'Forex Trading';
foreach ($campuses as $c) { if (stripos($c['name'], 'forex') !== false) { $exampleCampus = $c['name']; break; } }

// The year: one bar per weekly live class; the week each monthly stage opens is marked.
$opens = [];
for ($k = 0; $k < 12; $k++) $opens[(int) round($k * 52 / 12)] = true;

$arr = '<svg class="arr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$tick = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

$pageTitle = 'Teach at Obin Academy — Lead a Campus';
$pageDescription = 'Apply to lead a year-long Obin Academy campus: monthly lessons, a weekly live class and a community of members who want to learn what you do.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,300;0,6..72,400;0,6..72,500;1,6..72,300;1,6..72,400&family=Geist+Mono:wght@400;500';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/teach.css')) ?>">
<main class="tx">
  <section class="tx-hero">
    <div class="tx-gridlines" aria-hidden="true"></div>
    <div class="tx-wrap tx-hero-in">
      <div>
        <p class="tx-label lime">Obin Academy · Faculty</p>
        <h1><span class="ln"><span>Teach what</span></span><span class="ln"><span>you know.</span></span><span class="ln"><span><em>Lead a campus.<svg viewBox="0 0 300 12" preserveAspectRatio="none" aria-hidden="true"><path d="M2 8 C 60 2, 140 2, 298 7"/></svg></em></span></span></h1>
        <p class="lede">We are building a faculty of people who have done the work. You teach a year-long campus. We bring the members and run everything around you.</p>
        <div class="acts">
          <a class="tx-btn solid" href="#apply">Apply to teach <?= $arr ?></a>
          <a class="tx-btn" href="#role">See the role</a>
        </div>
        <div class="rule" aria-hidden="true"></div>
        <div class="tx-facts">
          <div><b>12 months</b><span>One stage a month</span></div>
          <div><b>52 classes</b><span>Live, once a week</span></div>
          <div><b>1 professor</b><span>Leads each campus</span></div>
        </div>
      </div>

      <aside class="tx-week" aria-label="An example week as a professor">
        <div class="tx-week-h"><div><b>Your week</b><small><?= e($exampleCampus) ?> Campus · example</small></div><span class="tx-live"><i></i>LIVE THU</span></div>
        <div class="tx-days" aria-hidden="true">
          <span class="sweep"></span>
          <?php for ($d = 0; $d < 7; $d++): $day = $monday->modify("+$d day"); ?><div<?= $d === 3 ? ' class="on"' : '' ?>><?= e(strtoupper($day->format('D'))) ?><b><?= e($day->format('j')) ?></b></div><?php endfor; ?>
        </div>
        <div class="tx-slots">
          <div class="tx-slot"><span class="d">MON</span><div><b>Month 4 lessons open</b><small>3 videos · 1 PDF guide</small></div><span class="tag">READY</span></div>
          <div class="tx-slot"><span class="d">THU</span><div><b>Live class: managing risk</b><small>8:00 pm · 90 minutes</small></div><span class="tag go">LIVE</span></div>
          <div class="tx-slot"><span class="d">DAILY</span><div><b>Community</b><small>Questions, wins and reviews</small></div><span class="tag">OPEN</span></div>
        </div>
        <div class="tx-feed"><span class="tx-label">In the community now</span><ul id="tx-feed"></ul></div>
      </aside>
    </div>
  </section>

  <section class="tx-sec" id="role">
    <div class="tx-wrap">
      <div class="tx-sec-h">
        <p class="tx-label lime rv">The role</p>
        <div class="mask"><h2>You bring the knowledge. <em>We carry everything else.</em></h2><p class="sub">One professor leads each campus for the whole year. Here is what that means week to week.</p></div>
      </div>
      <div class="tx-ledger">
        <div class="rv"><span class="tx-label">Once</span><h3>Plan the year</h3><p>Twelve stages, one a month, from first steps to mastery. We plan it with you.</p></div>
        <div class="rv"><span class="tx-label">Each month</span><h3>Record the lessons</h3><p>Short videos and a PDF guide for that month's stage.</p></div>
        <div class="rv"><span class="tx-label">Each week</span><h3>Teach live</h3><p>One live class on Zoom or Google Meet, with time for questions.</p></div>
        <div class="rv"><span class="tx-label">Every day</span><h3>Lead the community</h3><p>Answer, encourage and review your members' work.</p></div>
      </div>
      <div class="tx-handle">
        <p class="tx-label rv">What we handle</p>
        <ul>
          <li class="rv"><?= $tick ?><div><b>Members</b><span>Marketing, the website and your campus page</span></div></li>
          <li class="rv"><?= $tick ?><div><b>Payments and renewals</b><span>Mobile money, reminders and access</span></div></li>
          <li class="rv"><?= $tick ?><div><b>The platform</b><span>Lesson hosting, community and certificates</span></div></li>
          <li class="rv"><?= $tick ?><div><b>Getting started</b><span>We record month one with you</span></div></li>
        </ul>
      </div>
    </div>
  </section>

  <section class="tx-sec" id="year">
    <div class="tx-wrap">
      <div class="tx-sec-h">
        <p class="tx-label lime rv">Your year</p>
        <div class="mask"><h2>52 live classes. 12 stages. <em>One certificate</em> for every member who finishes.</h2></div>
      </div>
      <div class="tx-year">
        <div class="tx-ticks" aria-hidden="true"><?php for ($w = 0; $w < 52; $w++): ?><span style="--i:<?= $w ?>"<?= $w === 51 ? ' class="c"' : (isset($opens[$w]) ? ' class="m"' : '') ?>></span><?php endfor; ?></div>
        <div class="tx-months" aria-hidden="true"><span>Month 1</span><?php for ($m = 2; $m <= 12; $m++): ?><span><?= $m ?></span><?php endfor; ?></div>
        <div class="tx-legend"><span><i style="background:var(--tx-brand-2)"></i>New stage opens</span><span><i style="background:var(--tx-line-2)"></i>Weekly live class</span><span><i style="background:var(--tx-lime)"></i>Certificates issued</span></div>
      </div>
    </div>
  </section>

  <?php if ($campuses): ?>
  <section class="tx-sec" id="campuses">
    <div class="tx-wrap">
      <div class="tx-sec-h">
        <p class="tx-label lime rv">Open campuses</p>
        <div class="mask"><h2>Campuses looking for <em>a professor.</em></h2><p class="sub">Pick one when you apply, or propose a campus we don't have yet.</p></div>
      </div>
      <div class="tx-index">
        <?php foreach ($campuses as $i => $c): ?>
          <a class="tx-row" href="#apply" data-pick="<?= (int) $c['id'] ?>"><span class="n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><h3><?= e($c['name']) ?></h3><span class="st">Open</span><span class="go"><?= $arr ?></span></a>
        <?php endforeach; ?>
        <a class="tx-row" href="#apply" data-pick="new"><span class="n">+</span><h3><em>Propose your own campus</em></h3><span class="st">Your idea</span><span class="go"><?= $arr ?></span></a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="tx-sec">
    <div class="tx-wrap tx-sec-h">
      <p class="tx-label lime rv">Who we look for</p>
      <div class="tx-who">
        <p class="rv">You get <em>real results</em> in your field, and you can show them.</p>
        <p class="rv">You explain things <em>simply</em>, one step at a time.</p>
        <p class="rv">You can show up <em>every week</em> for a full year.</p>
        <p class="rv">You care about your students' progress, <em>not only your own.</em></p>
      </div>
    </div>
  </section>

  <section class="tx-sec" id="questions">
    <div class="tx-wrap tx-sec-h">
      <p class="tx-label lime rv">Questions</p>
      <div>
        <div class="mask"><h2>Before you <em>apply.</em></h2></div>
        <div class="tx-faq">
          <details><summary>Do I need a big following?<i></i></summary><div class="ans"><p>No. We look for real skill and the ability to teach it. Bringing your audience helps, but we market every campus ourselves.</p></div></details>
          <details><summary>How much time does it take?<i></i></summary><div class="ans"><p>Plan for a live class each week, a few hours a month to record lessons, and some time in the community each day.</p></div></details>
          <details><summary>How are professors paid?<i></i></summary><div class="ans"><p>We explain pay and terms on a call after you apply, once we know the campus is a fit.</p></div></details>
          <details><summary>Can I teach a campus that isn't listed?<i></i></summary><div class="ans"><p>Yes. Choose "Propose a new campus" in the form and tell us what you would teach.</p></div></details>
        </div>
      </div>
    </div>
  </section>

  <section class="tx-apply" id="apply">
    <div class="tx-wrap tx-apply-in">
      <div>
        <p class="tx-label">Apply</p>
        <h2>Tell us what <em>you teach.</em></h2>
        <p class="lead">Three short steps, about five minutes. We read every application.</p>
        <div class="tx-after">
          <div><span>Within a week</span><b>We reply by WhatsApp or email</b></div>
          <div><span>Next</span><b>A call where we explain everything: the role, the schedule and how professors are paid</b></div>
          <div><span>Then</span><b>We plan your twelve stages and record month one together</b></div>
          <div><span>Opening day</span><b>Members join your campus</b></div>
        </div>
      </div>

      <?php if (!$ready): ?>
        <div class="tx-form"><p class="tx-closed">Applications open shortly. Please check back soon.</p></div>
      <?php elseif ($sent): ?>
        <div class="tx-form"><div class="tx-done" role="status">
          <svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="30"/><path d="M20 33l8 8 16-17"/></svg>
          <h3>Application received</h3>
          <p><?= $applicant ? 'Thank you, ' . e($applicant['name']) . '. We will call or WhatsApp you on ' . e($applicant['phone']) . ' within a week.' : 'Thank you. We will call or WhatsApp you within a week.' ?> A copy is in your email.</p>
        </div></div>
      <?php else: ?>
        <form method="post" class="tx-form" id="tx-form" data-open-step="<?= $openStep ?>" novalidate>
          <?= csrf_field() ?>
          <div class="tx-hp" aria-hidden="true"><label for="tx-web">Website</label><input id="tx-web" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="tx-prog" role="tablist" aria-label="Application steps">
            <button type="button" role="tab" id="tx-t1" aria-controls="tx-p1"><small>Step 1</small><b>About you</b></button>
            <button type="button" role="tab" id="tx-t2" aria-controls="tx-p2"><small>Step 2</small><b>Your campus</b></button>
            <button type="button" role="tab" id="tx-t3" aria-controls="tx-p3"><small>Step 3</small><b>Your proof</b></button>
            <span class="bar" aria-hidden="true"></span>
          </div>

          <div class="tx-pane" id="tx-p1" role="tabpanel" aria-labelledby="tx-t1">
            <h3>About you</h3>
            <?php if ($error && $openStep === 1): ?><div class="tx-err" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="tx-field<?= $bad('name') ?>"><label for="tx-name">Full name</label><input id="tx-name" name="name" autocomplete="name" required minlength="2" maxlength="120" value="<?= $v('name', $viewer['name'] ?? '') ?>"><span class="ul"></span></div>
            <div class="tx-f2">
              <div class="tx-field<?= $bad('email') ?>"><label for="tx-email">Email</label><input id="tx-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= $v('email', $viewer['email'] ?? '') ?>"><span class="ul"></span></div>
              <div class="tx-field<?= $bad('phone') ?>"><label for="tx-phone">Phone or WhatsApp</label><input id="tx-phone" name="phone" type="tel" autocomplete="tel" required minlength="9" maxlength="32" placeholder="07XX XXX XXX" value="<?= $v('phone', $viewer['phone'] ?? '') ?>"><span class="ul"></span></div>
            </div>
            <div class="tx-field<?= $bad('headline') ?>"><label for="tx-head">What you do, in one line</label><input id="tx-head" name="headline" required minlength="5" maxlength="200" placeholder="e.g. Forex trader since 2017, funded account holder" value="<?= $v('headline') ?>"><span class="ul"></span></div>
            <div class="tx-pane-foot"><span class="note">We only use these to contact you about teaching.</span><button type="button" class="tx-btn ink" data-go="2">Next <?= $arr ?></button></div>
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
            <div class="tx-field<?= $bad('proposed_campus') ?>" data-new-field<?= ($old['campus'] ?? '') === 'new' ? '' : ' hidden' ?>><label for="tx-new">Name of the new campus</label><input id="tx-new" name="proposed_campus" maxlength="160" placeholder="e.g. Real Estate" value="<?= $v('proposed_campus') ?>"><span class="ul"></span></div>
            <div class="tx-field<?= $bad('experience') ?>"><label for="tx-exp">Your experience</label><textarea id="tx-exp" name="experience" rows="4" required minlength="40" maxlength="5000" placeholder="How long you have done this, and what you have done"><?= $v('experience') ?></textarea><span class="ul"></span></div>
            <div class="tx-pane-foot"><button type="button" class="tx-btn ghost" data-go="1">Back</button><button type="button" class="tx-btn ink" data-go="3">Next <?= $arr ?></button></div>
          </div>

          <div class="tx-pane" id="tx-p3" role="tabpanel" aria-labelledby="tx-t3">
            <h3>Your proof</h3>
            <?php if ($error && $openStep === 3): ?><div class="tx-err" role="alert"><?= e($error) ?></div><?php endif; ?>
            <div class="tx-field"><label for="tx-res">Results you can show <small>(optional)</small></label><textarea id="tx-res" name="results" rows="3" maxlength="3000" placeholder="Income, clients, students, followers, anything real"><?= $v('results') ?></textarea><span class="ul"></span></div>
            <div class="tx-f2">
              <div class="tx-field"><label for="tx-links">Where we can see your work <small>(optional)</small></label><input id="tx-links" name="links" maxlength="500" placeholder="Instagram, TikTok, YouTube, website" value="<?= $v('links') ?>"><span class="ul"></span></div>
              <div class="tx-field<?= $bad('sample_url') ?>"><label for="tx-sample">A short video of you teaching <small>(optional)</small></label><input id="tx-sample" name="sample_url" type="url" maxlength="500" placeholder="https://" value="<?= $v('sample_url') ?>"><span class="ul"></span></div>
            </div>
            <div class="tx-pane-foot"><button type="button" class="tx-btn ghost" data-go="2">Back</button><button type="submit" class="tx-btn ink">Send application</button></div>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</main>
<script>
(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Hero: an example community feed, one new message every few seconds, oldest drops off.
  var feed = document.getElementById('tx-feed');
  if (feed) {
    var msgs = [
      ['P', 'p', 'Thursday 8 pm: we break down this week together'],
      ['A', '', 'Finished my first week with a written plan'],
      ['J', '', 'Is 1% risk per trade too careful?'],
      ['P', 'p', 'Not at all. Month 3 shows why it works'],
      ['M', '', 'The month 2 PDF was worth it'],
      ['K', '', 'Joined today, hello everyone']
    ];
    var at = 0;
    var push = function () {
      var m = msgs[at++ % msgs.length], li = document.createElement('li'), av = document.createElement('i'), tx = document.createElement('span');
      av.className = m[1]; av.textContent = m[0]; tx.textContent = m[2];
      li.appendChild(av); li.appendChild(tx);
      feed.insertBefore(li, feed.firstChild);
      while (feed.children.length > 3) feed.removeChild(feed.lastChild);
    };
    push(); push(); push();
    if (!reduce) setInterval(push, 3200);
  }

  var form = document.getElementById('tx-form');
  if (!form) return;
  form.classList.add('js');
  var tabs = [1, 2, 3].map(function (i) { return document.getElementById('tx-t' + i); });
  var panes = [1, 2, 3].map(function (i) { return document.getElementById('tx-p' + i); });
  var bar = form.querySelector('.tx-prog .bar');
  var current = 0;
  // Panes enter from the direction of travel; the bar slides under the active step.
  function show(n, focus) {
    var dir = current && n !== current ? (n > current ? 'in-r' : 'in-l') : '';
    current = n;
    tabs.forEach(function (b, i) {
      b.setAttribute('aria-selected', String(i + 1 === n));
      b.classList.toggle('done', i + 1 < n);
      panes[i].hidden = i + 1 !== n;
      panes[i].classList.remove('in-r', 'in-l');
    });
    if (dir && !reduce) { void panes[n - 1].offsetWidth; panes[n - 1].classList.add(dir); }
    bar.style.transform = 'translateX(' + (n - 1) * 100 + '%)';
    if (focus) { var f = panes[n - 1].querySelector('input:not([type=radio]), textarea'); if (f) f.focus({ preventScroll: true }); }
  }
  // A step is complete when every visible required field in it is valid.
  function stepValid(n) {
    var ok = true;
    panes[n - 1].querySelectorAll('input, textarea').forEach(function (el) {
      if (el.closest('[hidden]') || el.type === 'hidden' || el.closest('.tx-hp')) return;
      var isBad = !el.checkValidity();
      if (el.type === 'radio') isBad = !panes[n - 1].querySelector('input[name="' + el.name + '"]:checked');
      el.closest('.tx-field').classList.toggle('bad', isBad);
      if (isBad && ok) { ok = false; if (el.type !== 'radio') el.focus(); }
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
  var pick = function (r) {
    newField.hidden = !r.hasAttribute('data-new');
    newField.querySelector('input').required = !newField.hidden;
    r.closest('.tx-field').classList.remove('bad');
  };
  form.querySelectorAll('input[name="campus"]').forEach(function (r) {
    r.addEventListener('change', function () { pick(r); if (!newField.hidden) newField.querySelector('input').focus(); });
  });
  if (!newField.hidden) newField.querySelector('input').required = true;

  // Choosing a campus in the list pre-selects it in the form.
  document.querySelectorAll('[data-pick]').forEach(function (a) {
    a.addEventListener('click', function () {
      var r = form.querySelector('input[name="campus"][value="' + a.dataset.pick + '"]');
      if (r) { r.checked = true; pick(r); }
    });
  });
  show(+form.dataset.openStep || 1);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
