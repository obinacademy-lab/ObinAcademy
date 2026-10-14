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
        $_SESSION['teach_applicant'] = ['name' => campus_first_name(post('name')), 'phone' => trim(post('phone')), 'ref' => 'OA-T-' . str_pad((string) $result['id'], 4, '0', STR_PAD_LEFT)];
        redirect('/teach.php?sent=1#apply');
    }
    $error = $result['error'];
    $errorField = $result['field'] ?? '';
    $old = $_POST;
}
$v = fn(string $k, string $fallback = '') => e((string) ($old[$k] ?? $fallback));
$bad = fn(string $k) => $errorField === $k ? ' bad' : '';
// Which question each server-side error belongs to; anything else (e.g. a repeat application) shows on the review.
$fieldQ = ['name' => 1, 'email' => 2, 'phone' => 3, 'headline' => 4, 'campus' => 5, 'proposed_campus' => 5, 'experience' => 6, 'sample_url' => 8];
$openQ = $error ? ($fieldQ[$errorField] ?? 9) : 1;
$errAt = fn(int $q) => $error && $openQ === $q ? '<p class="tx-ap-err" role="alert">' . e($error) . '</p>' : '';
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
        <div class="rv"><span class="tx-label">Once</span><h3>Plan the year</h3><p>Twelve stages, from first steps to mastery, each with its own picture. You build them in your Campus Studio and open the next one when your class is ready.</p></div>
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

  <section class="tx-ap" id="apply">
    <div class="tx-wrap">
      <div class="tx-ap-shell">
        <aside class="tx-ap-rail">
          <div>
            <p class="tx-label lime">Apply</p>
            <h2>Tell us what <em>you teach.</em></h2>
            <p class="lead">One question at a time, about five minutes. We read every application.</p>
          </div>
          <div class="tx-ap-counter-wrap tx-js-only">
            <div class="tx-ap-counter"><span class="tx-odo" aria-hidden="true"><span data-d="0"></span><span data-d="1"></span></span><small>/ 08</small></div>
            <div class="tx-ap-meter"><i data-meter></i></div>
          </div>
          <ol class="tx-ap-track" aria-label="What happens after you apply">
            <li class="lit"><div><span><?= $sent ? 'Done' : 'Now' ?></span><b>You send your application</b></div></li>
            <li<?= $sent ? ' class="lit"' : '' ?>><div><span>Within a week</span><b>We reply by WhatsApp or email</b></div></li>
            <li><div><span>Next</span><b>A call where we explain everything: the role, the schedule and how professors are paid</b></div></li>
            <li><div><span>Then</span><b>We plan your twelve stages and record month one together</b></div></li>
            <li><div><span>Opening day</span><b>Members join your campus</b></div></li>
          </ol>
          <?php if ($ready && !$sent): ?><p class="tx-ap-keys"><kbd>Enter</kbd> to continue</p><?php endif; ?>
        </aside>

        <?php if (!$ready): ?>
          <div class="tx-ap-stage"><p class="tx-closed">Applications open shortly. Please check back soon.</p></div>
        <?php elseif ($sent): ?>
          <div class="tx-ap-stage"><div class="tx-sent" role="status"><div>
            <div class="tx-stamp" aria-hidden="true">
              <svg viewBox="0 0 168 168"><defs><path id="tx-ring" d="M84 84 m-66 0 a66 66 0 1 1 132 0 a66 66 0 1 1 -132 0"/></defs><circle cx="84" cy="84" r="82"/><text><textPath href="#tx-ring">APPLICATION RECEIVED · OBIN ACADEMY FACULTY ·</textPath></text></svg>
              <b>&#10003;</b>
            </div>
            <h3><?= $applicant ? 'Thank you, ' . e($applicant['name']) . '.' : 'Thank you.' ?></h3>
            <p><?= $applicant ? 'We will call or WhatsApp you on ' . e($applicant['phone']) . ' within a week.' : 'We will call or WhatsApp you within a week.' ?> A copy is in your email.</p>
            <?php if (!empty($applicant['ref'])): ?><p class="ref">Reference <?= e($applicant['ref']) ?></p><?php endif; ?>
          </div></div></div>
        <?php else: ?>
          <form method="post" class="tx-ap-stage" id="tx-form" data-open="<?= $openQ ?>" novalidate>
            <?= csrf_field() ?>
            <div class="tx-hp" aria-hidden="true"><label for="tx-web">Leave this empty</label><input id="tx-web" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="tx-ap-top"><span class="tx-label" data-section>About you</span><span class="tx-ap-dots" data-dots></span></div>

            <div class="tx-ap-slot">
              <div class="tx-q" data-q="1" data-sec="About you">
                <span class="num">01 · Your name</span>
                <h3>First, what is your <em>full name?</em></h3>
                <p class="help">As you would like it to appear on your campus page.</p>
                <div class="tx-ans<?= $bad('name') ?>"><label class="tx-hp" for="tx-name">Full name</label><input id="tx-name" name="name" autocomplete="name" required minlength="2" maxlength="120" value="<?= $v('name', $viewer['name'] ?? '') ?>"><span class="ul"></span></div>
                <?= $errAt(1) ?><p class="tx-ap-err" data-err hidden>Enter your full name.</p>
              </div>
              <div class="tx-q" data-q="2" data-sec="About you">
                <span class="num">02 · Email</span>
                <h3>Where can we <em>email</em> you?</h3>
                <p class="help">We send a copy of your application here.</p>
                <div class="tx-ans<?= $bad('email') ?>"><label class="tx-hp" for="tx-email">Email</label><input id="tx-email" name="email" type="email" autocomplete="email" required maxlength="190" value="<?= $v('email', $viewer['email'] ?? '') ?>"><span class="ul"></span></div>
                <?= $errAt(2) ?><p class="tx-ap-err" data-err hidden>Enter a valid email address.</p>
              </div>
              <div class="tx-q" data-q="3" data-sec="About you">
                <span class="num">03 · Phone</span>
                <h3>Your phone or <em>WhatsApp</em> number.</h3>
                <p class="help">This is how we will call you about next steps.</p>
                <div class="tx-ans<?= $bad('phone') ?>"><label class="tx-hp" for="tx-phone">Phone or WhatsApp</label><input id="tx-phone" name="phone" type="tel" autocomplete="tel" required minlength="9" maxlength="32" placeholder="07XX XXX XXX" value="<?= $v('phone', $viewer['phone'] ?? '') ?>"><span class="ul"></span></div>
                <?= $errAt(3) ?><p class="tx-ap-err" data-err hidden>Enter a phone number we can call or WhatsApp.</p>
              </div>
              <div class="tx-q" data-q="4" data-sec="About you">
                <span class="num">04 · In one line</span>
                <h3>What do you do, <em>in one line?</em></h3>
                <p class="help">For example: <b>Forex trader since 2017, funded account holder.</b></p>
                <div class="tx-ans<?= $bad('headline') ?>"><label class="tx-hp" for="tx-head">What you do, in one line</label><input id="tx-head" name="headline" required minlength="5" maxlength="200" placeholder="What you do" value="<?= $v('headline') ?>"><span class="ul"></span></div>
                <?= $errAt(4) ?><p class="tx-ap-err" data-err hidden>Tell us what you do, in one line.</p>
              </div>
              <div class="tx-q" data-q="5" data-sec="Your campus">
                <span class="num">05 · Campus</span>
                <h3 id="tx-campus-l">Which campus would <em>you lead?</em></h3>
                <div class="tx-pick" role="radiogroup" aria-labelledby="tx-campus-l">
                  <?php foreach ($campuses as $i => $c): ?>
                    <label><input type="radio" name="campus" value="<?= (int) $c['id'] ?>"<?= ($old['campus'] ?? '') === (string) $c['id'] ? ' checked' : '' ?>><kbd><?= $i < 26 ? chr(65 + $i) : '·' ?></kbd><span><?= e($c['name']) ?></span></label>
                  <?php endforeach; ?>
                  <label class="new"><input type="radio" name="campus" value="new" data-new<?= ($old['campus'] ?? '') === 'new' ? ' checked' : '' ?>><kbd>+</kbd><span>Propose a new campus</span></label>
                </div>
                <div class="tx-newname" data-new-field<?= ($old['campus'] ?? '') === 'new' ? '' : ' hidden' ?>><label for="tx-new">Name of the new campus</label><div class="tx-ans<?= $bad('proposed_campus') ?>"><input id="tx-new" name="proposed_campus" maxlength="160" placeholder="e.g. Real Estate" value="<?= $v('proposed_campus') ?>"><span class="ul"></span></div></div>
                <?= $errAt(5) ?><p class="tx-ap-err" data-err hidden>Choose a campus, or propose a new one.</p>
              </div>
              <div class="tx-q" data-q="6" data-sec="Your campus">
                <span class="num">06 · Experience</span>
                <h3>Tell us about your <em>experience.</em></h3>
                <p class="help">How long you have done this, and what you have done. A few sentences is enough.</p>
                <div class="tx-ans<?= $bad('experience') ?>"><label class="tx-hp" for="tx-exp">Your experience</label><textarea id="tx-exp" name="experience" required minlength="40" maxlength="5000" placeholder="Your experience"><?= $v('experience') ?></textarea><span class="ul"></span><span class="count tx-js-only" data-count></span></div>
                <?= $errAt(6) ?><p class="tx-ap-err" data-err hidden>A little more, please: at least a few sentences.</p>
              </div>
              <div class="tx-q" data-q="7" data-sec="Your proof">
                <span class="num">07 · Results · optional</span>
                <h3>What results <em>can you show?</em></h3>
                <p class="help">Income, clients, students, followers. Anything real.</p>
                <div class="tx-ans"><label class="tx-hp" for="tx-res">Results you can show</label><textarea id="tx-res" name="results" maxlength="3000" placeholder="Your results"><?= $v('results') ?></textarea><span class="ul"></span></div>
              </div>
              <div class="tx-q" data-q="8" data-sec="Your proof">
                <span class="num">08 · Your work · optional</span>
                <h3>Where can we <em>see you teach?</em></h3>
                <div class="tx-two">
                  <div><label for="tx-links">Your pages</label><div class="tx-ans"><input id="tx-links" name="links" maxlength="500" placeholder="Instagram, TikTok, YouTube" value="<?= $v('links') ?>"><span class="ul"></span></div></div>
                  <div><label for="tx-sample">A short teaching video</label><div class="tx-ans<?= $bad('sample_url') ?>"><input id="tx-sample" name="sample_url" type="url" maxlength="500" placeholder="https://" value="<?= $v('sample_url') ?>"><span class="ul"></span></div></div>
                </div>
                <?= $errAt(8) ?><p class="tx-ap-err" data-err hidden>The video link must start with https://</p>
              </div>
              <div class="tx-q tx-js-only" data-q="9" data-sec="Review">
                <span class="num">Review</span>
                <h3>Everything <em>look right?</em></h3>
                <div class="tx-sheet" data-sheet></div>
                <?= $errAt(9) ?>
              </div>
            </div>

            <div class="tx-ap-ctrl tx-js-only" data-ctrl>
              <button type="button" class="tx-btn solid" data-next><span data-next-label>Continue</span> <?= $arr ?></button>
              <span class="tx-hint" data-hint>or press <kbd>Enter</kbd></span>
              <button type="button" class="tx-link back" data-back>Back</button>
            </div>
            <div class="tx-ap-ctrl tx-nojs-only">
              <?php if ($error && $openQ === 9): ?><p class="tx-ap-err" role="alert"><?= e($error) ?></p><?php endif; ?>
              <button type="submit" class="tx-btn solid">Send application <?= $arr ?></button>
            </div>
          </form>
        <?php endif; ?>
      </div>
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
  var ap = form.closest('.tx-ap');
  ap.classList.add('js');
  var TOTAL = 8;
  var qs = [].slice.call(form.querySelectorAll('.tx-q'));
  var byN = function (n) { return form.querySelector('.tx-q[data-q="' + n + '"]'); };
  var next = form.querySelector('[data-next]'), nextLabel = form.querySelector('[data-next-label]');
  var back = form.querySelector('[data-back]'), hint = form.querySelector('[data-hint]');
  var dots = form.querySelector('[data-dots]'), meter = ap.querySelector('[data-meter]'), section = form.querySelector('[data-section]');
  var newField = form.querySelector('[data-new-field]'), newInput = document.getElementById('tx-new');
  for (var i = 0; i < TOTAL; i++) dots.appendChild(document.createElement('i'));

  // The rolling question counter.
  var digits = [].slice.call(ap.querySelectorAll('[data-d]'));
  digits.forEach(function (s) { for (var n = 0; n <= 9; n++) { var d = document.createElement('i'); d.textContent = n; s.appendChild(d); } });
  function roll(n) {
    var str = ('0' + Math.min(n, TOTAL)).slice(-2);
    digits[0].style.transform = 'translateY(-' + (+str[0]) * 10 + '%)';
    digits[1].style.transform = 'translateY(-' + (+str[1]) * 10 + '%)';
  }

  // Is question n answered well enough to move on?
  function valid(n) {
    var q = byN(n), ok = true, err = q.querySelector('[data-err]');
    if (n === 5) {
      var c = form.querySelector('input[name=campus]:checked');
      ok = !!c && (c.value !== 'new' || newInput.value.trim().length >= 3);
    } else {
      q.querySelectorAll('input, textarea').forEach(function (el) {
        if (el.closest('.tx-hp')) return;
        var v = el.value.trim(), isBad = !el.checkValidity() || (el.required && el.minLength > 0 && v.length < el.minLength);
        el.closest('.tx-ans').classList.toggle('bad', isBad);
        if (isBad) ok = false;
      });
    }
    q.querySelectorAll('.tx-ap-err[role=alert]').forEach(function (e) { e.remove(); });
    if (err) { err.hidden = ok; if (!ok) { err.style.animation = 'none'; void err.offsetWidth; err.style.animation = ''; } }
    return ok;
  }

  function sheet() {
    var c = form.querySelector('input[name=campus]:checked');
    var campus = c ? (c.value === 'new' ? 'New: ' + newInput.value.trim() : c.parentNode.querySelector('span').textContent) : '';
    var rows = [['Name', 'tx-name', 1], ['Email', 'tx-email', 2], ['Phone', 'tx-phone', 3], ['One line', 'tx-head', 4], ['Campus', null, 5, campus], ['Experience', 'tx-exp', 6], ['Results', 'tx-res', 7], ['Your work', 'tx-links', 8]];
    var box = form.querySelector('[data-sheet]'); box.innerHTML = '';
    rows.forEach(function (r) {
      var val = r[3] !== undefined ? r[3] : document.getElementById(r[1]).value.trim();
      if (r[2] === 8) { var s = document.getElementById('tx-sample').value.trim(); val = [val, s].filter(Boolean).join(' · '); }
      var row = document.createElement('div'), k = document.createElement('span'), b = document.createElement('b'), edit = document.createElement('button');
      k.className = 'k'; k.textContent = r[0];
      b.textContent = val || 'Not given'; if (!val) b.className = 'none';
      edit.type = 'button'; edit.className = 'tx-link'; edit.textContent = 'Edit';
      edit.addEventListener('click', function () { go(r[2]); });
      row.appendChild(k); row.appendChild(b); row.appendChild(edit);
      box.appendChild(row);
    });
  }

  var cur = 0;
  function paint() {
    section.textContent = byN(cur).dataset.sec;
    [].forEach.call(dots.children, function (d, j) { d.className = j + 1 === cur ? 'on' : j + 1 < cur ? 'done' : ''; });
    roll(cur);
    meter.style.transform = 'scaleX(' + (Math.min(cur, TOTAL + 1) - 1) / TOTAL + ')';
    back.style.visibility = cur > 1 ? 'visible' : 'hidden';
    nextLabel.textContent = cur === 9 ? 'Send application' : 'Continue';
    hint.style.display = cur >= 6 ? 'none' : '';
  }
  function go(n, instant) {
    if (n === cur) return;
    var from = cur ? byN(cur) : null, to = byN(n), dir = n < cur ? ' back' : '';
    if (n === 9) sheet();
    var focusIt = function () { var f = to.querySelector('input:not([type=radio]):not(#tx-new), textarea'); if (f && n < 9) f.focus({ preventScroll: true }); };
    if (!from || instant || reduce) {
      qs.forEach(function (q) { q.hidden = q !== to; q.className = q.className.replace(/ ?(enter|leave|back)/g, ''); });
      if (from) focusIt();
    } else {
      from.className += ' leave' + dir;
      setTimeout(function () { from.hidden = true; from.className = from.className.replace(/ ?(enter|leave|back)/g, ''); }, 460);
      setTimeout(function () { to.hidden = false; to.className = to.className.replace(/ ?(enter|leave|back)/g, '') + ' enter' + dir; focusIt(); }, 300);
    }
    cur = n;
    paint();
  }

  next.addEventListener('click', function () {
    if (cur === 9) {
      for (var n = 1; n <= TOTAL; n++) { if (!valid(n)) { go(n); return; } }
      next.disabled = true; nextLabel.textContent = 'Sending';
      form.submit();
      return;
    }
    if (valid(cur)) go(cur + 1);
  });
  back.addEventListener('click', function () { if (cur > 1) go(cur - 1); });
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') { e.preventDefault(); next.click(); }
    if (cur === 5 && e.target.type !== 'text' && e.target.id !== 'tx-new' && /^[a-z]$/i.test(e.key)) {
      var r = form.querySelectorAll('input[name=campus]:not([data-new])')[e.key.toLowerCase().charCodeAt(0) - 97];
      if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
    }
  });

  var pick = function (r) {
    newField.hidden = !r.hasAttribute('data-new');
    var err = byN(5).querySelector('[data-err]'); if (err) err.hidden = true;
  };
  form.querySelectorAll('input[name=campus]').forEach(function (r) {
    r.addEventListener('change', function () { pick(r); if (!newField.hidden) newInput.focus(); });
  });

  var exp = document.getElementById('tx-exp'), count = form.querySelector('[data-count]');
  var tally = function () { var n = exp.value.trim().length; count.textContent = n < 40 ? (40 - n) + ' more characters' : n + ' characters'; };
  exp.addEventListener('input', tally); tally();

  // Choosing a campus in the list above preselects it here.
  document.querySelectorAll('[data-pick]').forEach(function (a) {
    a.addEventListener('click', function () {
      var r = form.querySelector('input[name="campus"][value="' + a.dataset.pick + '"]');
      if (r) { r.checked = true; pick(r); }
    });
  });

  go(+form.dataset.open || 1, true);
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
