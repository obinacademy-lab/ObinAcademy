<?php
/**
 * One campus: what it teaches, who teaches it, the year quarter by quarter, and the enrolment panel
 * (monthly or yearly, paid with mobile money through the shared payment widget, assets/js/payment.js).
 * Draft campuses are visible to admins only, as a preview.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

$campus = get_campus_by_slug(query_param('slug'));
$viewer = current_user();
$isAdmin = $viewer && $viewer['role'] === 'ADMIN';
if (!$campus || ($campus['status'] !== 'PUBLISHED' && !$isAdmin)) {
    http_response_code(404);
    $pageTitle = 'Campus not found — Obin Academy';
    require __DIR__ . '/includes/header.php';
    echo '<main class="container" style="padding:80px 0; text-align:center;"><h1 class="h2">Campus not found</h1><p class="muted" style="margin-top:8px;"><a href="' . e(base_url('campuses.php')) . '">See all campuses</a></p></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stages = get_campus_stages((int) $campus['id']);
$curriculumReady = campus_curriculum_written($stages);
$membership = $viewer ? get_campus_membership((int) $campus['id'], (int) $viewer['id']) : null;
$open = campus_membership_open($membership);
$isTrading = (bool) preg_match('/crypto|forex|trading/i', $campus['slug'] . ' ' . $campus['name']);
$learnUrl = base_url('campus-learn.php?campus=' . $campus['slug']);
$monthly = (float) $campus['monthly_price'];
$yearly = (float) $campus['yearly_price'];
$saving = max(0, $monthly * 12 - $yearly);
$quarterNames = ['Foundations', 'Method', 'Practice', 'Mastery'];
$initials = $campus['professor_name'] ? mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($campus['professor_name'])), 0, 2)))) : 'P';

$pageTitle = $campus['name'] . ' Campus — Obin Academy';
$pageDescription = $campus['tagline'] ?: ('A year-long ' . $campus['name'] . ' programme at Obin Academy.');
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
<main class="cx">
  <?php if ($campus['status'] !== 'PUBLISHED'): ?>
    <div style="background:#fff6e6; color:#a75a00; text-align:center; padding:10px 16px; font-size:13.5px; font-weight:600;">Preview: this campus is a draft and only admins can see it. <a href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $campus['id'])) ?>" style="text-decoration:underline;">Edit and publish</a></div>
  <?php endif; ?>
  <section class="cx-deep">
    <div class="cx-wrap cx-cphead">
      <p class="cx-crumb"><a href="<?= e(base_url('campuses.php')) ?>">Campuses</a> &nbsp;/&nbsp; <?= e($campus['name']) ?></p>
      <h1><?= e($campus['name']) ?> Campus</h1>
      <p class="lede"><?= e($campus['description'] ?: $campus['tagline']) ?></p>
      <div class="cx-meta">
        <div><small>Length</small><b>12 months</b></div>
        <div><small>Live class</small><b><?= e($campus['live_schedule'] ?: 'Every week') ?></b></div>
        <div><small>Materials</small><b>Video and PDF</b></div>
        <div><small>Awarded</small><b>Certificate</b></div>
      </div>
    </div>
  </section>

  <div class="cx-wrap cx-body">
    <div>
      <section class="cx-sec">
        <h2>The professor</h2>
        <div class="cx-prof">
          <div class="ph"><?php if ($campus['professor_photo_url']): ?><img src="<?= e(asset_src($campus['professor_photo_url'])) ?>" alt=""><?php else: ?><?= e($initials) ?><?php endif; ?></div>
          <div>
            <b><?= e($campus['professor_name'] ?: 'Professor to be announced') ?></b>
            <div class="role"><?= e($campus['professor_title'] ?: 'Lead professor, ' . $campus['name'] . ' Campus') ?></div>
            <?php if ($campus['professor_bio']): ?><p><?= e($campus['professor_bio']) ?></p><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="cx-sec">
        <h2>The year, quarter by quarter</h2>
        <p>One stage opens each month from the day you join. The final month closes the path, and completing it earns your certificate.</p>
        <?php if ($curriculumReady): ?>
          <div class="cx-quarters">
            <?php for ($q = 0; $q < 4; $q++): ?>
              <div class="cx-q">
                <div class="qh"><small>Quarter <?= $q + 1 ?></small><b><?= e($quarterNames[$q]) ?></b></div>
                <ul>
                  <?php for ($m = $q * 3 + 1; $m <= $q * 3 + 3; $m++): ?>
                    <li><span class="cx-mono">Month <?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?></span><span><b><?= e($stages[$m]['title']) ?></b><?php if ($stages[$m]['summary'] !== ''): ?> · <span><?= e($stages[$m]['summary']) ?></span><?php endif; ?></span></li>
                  <?php endfor; ?>
                </ul>
              </div>
            <?php endfor; ?>
          </div>
        <?php else: ?>
          <p class="cx-fine" style="font-size:14px;">The full month-by-month curriculum will be published here soon.</p>
        <?php endif; ?>
      </section>

      <section class="cx-sec">
        <h2>Your weekly rhythm</h2>
        <div class="cx-rhythm">
          <div><span class="cx-label">Each month</span><b>New lessons</b><span>Videos and PDF guides</span></div>
          <div><span class="cx-label">Each week</span><b><?= e($campus['live_schedule'] ? 'Live class, ' . $campus['live_schedule'] : 'A live class') ?></b><span>Teaching and questions</span></div>
          <div><span class="cx-label">Every day</span><b>Community</b><span>Practice and feedback</span></div>
        </div>
        <?php if ($isTrading): ?><p class="cx-fine">Trading carries a real risk of loss. Lessons are educational and are not financial advice.</p><?php endif; ?>
      </section>
    </div>

    <aside class="cx-enrol" id="join" aria-label="Join this campus">
      <?php if ($open): ?>
        <div class="cx-member-box">
          <span class="cx-label">Your membership</span>
          <b class="cx-serif" style="display:block; font-size:22px; margin-top:4px;">You are a member</b>
          <p>Access open until <strong><?= e(date('j M Y', strtotime($membership['access_until']))) ?></strong> (<?= $membership['plan'] === 'YEARLY' ? 'yearly' : 'monthly' ?>).</p>
          <a class="cx-btn solid full" style="margin-top:16px;" href="<?= e($learnUrl) ?>">Go to your campus</a>
        </div>
        <div class="eh" style="border-top:1px solid var(--cx-line);"><span class="cx-label">Add more time</span></div>
      <?php else: ?>
        <div class="eh"><span class="cx-label"><?= $membership ? 'Your access has ended' : 'Enrolment' ?></span><b><?= $membership ? 'Rejoin the campus' : 'Join the campus' ?></b></div>
      <?php endif; ?>

      <div role="radiogroup" aria-label="Plan" data-plan-group>
        <button type="button" class="cx-opt" role="radio" aria-checked="false" data-plan-opt="MONTHLY" data-amount="<?= e(format_money($monthly)) ?>"><span class="rd"></span><span><b>Monthly</b><small>30 days at a time</small></span><span class="amt"><?= e(format_money($monthly)) ?><small>per month</small></span></button>
        <button type="button" class="cx-opt" role="radio" aria-checked="true" data-plan-opt="YEARLY" data-amount="<?= e(format_money($yearly)) ?>"><span class="rd"></span><span><b>Yearly</b><small><?= $saving > 0 ? 'Save ' . e(format_money($saving)) : '12 months' ?></small></span><span class="amt"><?= e(format_money($yearly)) ?><small>per year</small></span></button>
      </div>

      <div class="eb">
        <?php if (!$viewer): ?>
          <p class="note" style="margin-top:0;">Create a free account or log in first, so your lessons, progress and certificate are saved to you.</p>
          <a class="cx-btn solid full" href="<?= e(base_url('signup.php?redirect=' . urlencode('/campus.php?slug=' . $campus['slug'] . '#join'))) ?>">Create an account to join</a>
          <a class="cx-btn full" style="margin-top:8px;" href="<?= e(base_url('login.php?redirect=' . urlencode('/campus.php?slug=' . $campus['slug'] . '#join'))) ?>">I already have an account</a>
        <?php elseif ($campus['status'] !== 'PUBLISHED'): ?>
          <p class="note" style="margin-top:0;">Payments open once this campus is published.</p>
        <?php else: ?>
          <div data-payment-widget data-campus-id="<?= (int) $campus['id'] ?>" data-plan="YEARLY" data-initiate-url="<?= e(base_url('api/initiate-campus.php')) ?>" data-success-redirect="<?= e($learnUrl) ?>">
            <div data-state="phone">
              <label for="cx-phone">Mobile money number</label>
              <input id="cx-phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="07XX XXX XXX" value="<?= e($viewer['phone'] ?? '') ?>" data-phone-input>
              <button type="button" class="cx-btn solid full" data-action="pay">Pay <span data-pay-amount><?= e(format_money($yearly)) ?></span></button>
              <p class="note" data-plan-note>Access for 12 months from today. A prompt arrives on your phone; enter your PIN to confirm.</p>
              <div class="err hidden" data-error></div>
            </div>
            <div data-state="waiting" class="hidden"><div class="state"><div class="spin" aria-hidden="true"></div><b>Approve on your phone</b><p data-status-text>Check your phone and approve the mobile money prompt.</p></div></div>
            <div data-state="success" class="hidden"><div class="state"><b>Payment received</b><p>Opening your campus…</p></div></div>
            <div data-state="failed" class="hidden"><div class="state"><b>Payment not completed</b><p data-fail-text>The payment was not completed.</p><button type="button" class="cx-btn full" style="margin-top:14px;" data-action="retry">Try again</button></div></div>
          </div>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</main>
<script>
(function () {
  var widget = document.querySelector('[data-payment-widget][data-campus-id]');
  var note = document.querySelector('[data-plan-note]');
  var amountEl = document.querySelector('[data-pay-amount]');
  var notes = {
    MONTHLY: 'Access for 30 days from today. We remind you by email from 7 days before it ends. Nothing renews by itself.',
    YEARLY: 'Access for 12 months from today. A prompt arrives on your phone; enter your PIN to confirm.'
  };
  document.querySelectorAll('[data-plan-opt]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('[data-plan-opt]').forEach(function (x) { x.setAttribute('aria-checked', String(x === b)); });
      if (widget) widget.dataset.plan = b.dataset.planOpt;
      if (amountEl) amountEl.textContent = b.dataset.amount;
      if (note) note.textContent = notes[b.dataset.planOpt];
    });
  });
})();
</script>
<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
