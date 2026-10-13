<?php
/**
 * Teach at Obin Academy: the role, the deal, a year of weekly live classes, and a three-step
 * application (stored in instructor_applications, reviewed by admins). Pay is deliberately not shown
 * publicly: it is explained to applicants on the call. Works without JavaScript: the steps then show
 * as one long form.
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

// The year: one bar per weekly live class; the week each monthly stage opens is marked.
$opens = [];
for ($k = 0; $k < 12; $k++) $opens[(int) round($k * 52 / 12)] = true;

$pageTitle = 'Teach at Obin Academy — Lead a Campus';
$pageDescription = 'Apply to lead a year-long Obin Academy campus: monthly lessons, a weekly live class and a community of members who want to learn what you do.';
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=Geist+Mono:wght@400;500';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/teach.css')) ?>">
<main class="tx">
  <div class="tx-wrap tx-hero">
    <div>
      <p class="tx-eyebrow">Teach at Obin Academy</p>
      <h1>Teach what you know. <em>Lead a campus.</em></h1>
      <p class="lede">Lead a year-long campus in what you already do well. You teach. We bring the members, take care of payments and reminders, and run the platform.</p>
      <div class="acts"><a class="tx-btn lime" href="#apply">Apply to teach</a><a class="tx-btn" href="#deal">How it works</a></div>
    </div>

    <figure class="tx-receipt" aria-label="Campuses open for professors">
      <div class="tx-r-top"><span>CAMPUSES OPEN FOR PROFESSORS</span><span class="tx-r-ok">Applications open</span></div>
      <div class="tx-r-rows tx-r-list">
        <?php foreach ($campuses as $c): ?><div><span><?= e($c['name']) ?></span><b>12 months</b></div><?php endforeach; ?>
        <div class="hl"><span>Something else you teach well</span><b>Propose it</b></div>
      </div>
    </figure>
  </div>

  <div class="tx-wrap">
    <section class="tx-sec" id="deal">
      <p class="tx-eyebrow">The deal</p>
      <h2 class="tx-h2">You bring the knowledge. We carry everything else.</h2>
      <div class="tx-deal">
        <div>
          <h3>You bring</h3>
          <ul>
            <li><div><b>A twelve-month path</b><span>One stage a month, from first steps to mastery</span></div><i>Once</i></li>
            <li><div><b>Lessons for each stage</b><span>Short videos and PDF guides, recorded with our help</span></div><i>Monthly</i></li>
            <li><div><b>A live class</b><span>Teaching plus questions, on Zoom or Google Meet</span></div><i>Weekly</i></li>
            <li><div><b>Your presence in the community</b><span>Answer, encourage and review members' work</span></div><i>Daily</i></li>
          </ul>
        </div>
        <div>
          <h3>We bring</h3>
          <ul>
            <li><div><b>Members</b><span>Marketing, the website and your campus page</span></div><i>Always</i></li>
            <li><div><b>Payments</b><span>Mobile money collection, renewals and reminders</span></div><i>Always</i></li>
            <li><div><b>The platform</b><span>Lesson hosting, community, certificates and support</span></div><i>Always</i></li>
            <li><div><b>Help getting started</b><span>Planning your year and recording your first lessons together</span></div><i>Month one</i></li>
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
          <div><span>Next</span><b>A call where we explain everything: the role, the schedule and how professors are paid</b></div>
          <div><span>Then</span><b>We plan your twelve stages and record month one together</b></div>
          <div><span>Opening day</span><b>Members join your campus</b></div>
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
