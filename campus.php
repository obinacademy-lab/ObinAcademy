<?php
/**
 * One campus, kept clean: a short hero, then what's included, the professor (with social links), how
 * it works, the year as locked tiles (no lesson or stage titles: those are for members only) and four
 * questions. The join card (plan switch, price, mobile money) stays in view the whole time: sticky on
 * the right on wide screens, straight under the hero on phones, with a pay bar at the bottom.
 * Paid through the shared payment widget (assets/js/payment.js). Draft campuses: admins only, as a preview.
 * Styles: assets/css/campus-page.css. Motion: assets/js/campus-page.js.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/campus_pages.php';

$campus = get_campus_by_slug(query_param('slug'));
$viewer = current_user();
$isAdmin = $viewer && $viewer['role'] === 'ADMIN';
if (!$campus || ($campus['status'] !== 'PUBLISHED' && !$isAdmin)) {
    http_response_code(404);
    $pageTitle = 'Campus not found — Obin Academy';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:80px 0; text-align:center;"><h1 class="h2">Campus not found</h1><p class="muted" style="margin-top:8px;"><a href="' . e(base_url('index.php#campuses')) . '">See all campuses</a></p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

[$headline, $lede] = campus_page_intro($campus);
$membership = $viewer ? get_campus_membership((int) $campus['id'], (int) $viewer['id']) : null;
$open = campus_membership_open($membership);
$published = $campus['status'] === 'PUBLISHED';
$isTrading = (bool) preg_match('/crypto|forex|trading/i', $campus['slug'] . ' ' . $campus['name']);
$learnUrl = base_url('campus-learn.php?campus=' . $campus['slug']);
$joinBack = '/campus.php?slug=' . $campus['slug'] . '#join';
$monthly = (float) $campus['monthly_price'];
$yearly = (float) $campus['yearly_price'];
$saving = max(0, $monthly * 12 - $yearly);
$savePct = $monthly > 0 && $saving > 0 ? (int) round($saving / ($monthly * 12) * 100) : 0;
$perDay = fn(float $v, int $days) => 'UGX ' . number_format(max(100, round($v / $days / 100) * 100));
$photo = campus_photo($campus);
$links = campus_professor_links($campus);
$hasProf = trim((string) $campus['professor_name']) !== '';
$initials = $hasProf ? mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($campus['professor_name'])), 0, 2)))) : '?';
$classMonth = campus_open_month($campus);
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$tick = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
$lock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
$ic = fn(string $p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';

$faq = [
    ['Do I need any experience?', 'No. Every campus starts from the basics, and you can learn on a smartphone.'],
    ['How do I pay?', 'With MTN or Airtel Mobile Money. A prompt arrives on your phone; enter your PIN and your campus opens.'],
    ['What if I join mid-year?', 'Every stage your class has reached opens for you, with recordings, so you can catch up.'],
    ['Can I stop any time?', 'Yes. Nothing renews by itself. We remind you before your access ends.'],
];
if ($isTrading) $faq[] = ['Will I make money?', 'Trading carries a real risk of loss and nobody can promise profit. Lessons are educational and are not financial advice.'];

$pageTitle = $campus['name'] . ' Campus — Obin Academy';
$pageDescription = $campus['tagline'] ?: ('A year-long ' . $campus['name'] . ' programme at Obin Academy.');
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;1,6..72,400&family=Geist+Mono:wght@500;600';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campus-page.css')) ?>">
<div class="cq" data-cq>
  <?php if (!$published): ?>
    <div class="cq-draft">Preview: this campus is a draft and only admins can see it. <a href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $campus['id'])) ?>">Edit and publish</a></div>
  <?php endif; ?>

  <div class="cq-page">
    <section class="cq-hero">
      <div class="cq-aurora" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="cq-hero-copy">
        <p class="cq-crumb cq-fade" style="--d:.05s"><a href="<?= e(base_url('index.php#campuses')) ?>">Campuses</a> / <?= e($campus['name']) ?></p>
        <span class="cq-chip cq-fade" style="--d:.1s"><i></i><?= $published ? 'OPEN · JOIN ANY TIME' : 'OPENING SOON' ?></span>
        <h1><?php foreach ($headline as $i => $line): ?><span class="cq-line"><span style="--l:<?= $i ?>"><?= $i === count($headline) - 1 ? '<em>' . e($line) . '</em>' : e($line) ?></span></span><?php endforeach; ?></h1>
        <p class="cq-lede cq-fade" style="--d:.6s"><?= e($lede) ?></p>
        <div class="cq-facts cq-fade" style="--d:.75s">
          <span><?= $ic('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>') ?>12 months</span>
          <span><?= $ic('<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>') ?><?= e($campus['live_schedule'] ? 'Live ' . $campus['live_schedule'] : 'Live weekly') ?></span>
          <span><?= $ic('<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>') ?>On your phone</span>
          <span><?= $ic('<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>') ?>Certificate</span>
        </div>
      </div>
      <?php if ($photo): ?><div class="cq-hero-pic"><img src="<?= e($photo) ?>" alt="" data-parallax></div><?php endif; ?>
    </section>

    <aside class="cq-join" id="join" aria-label="Join this campus">
      <span class="cq-label">Join the campus</span>
      <h3><?= e($campus['name']) ?></h3>
      <?php if ($open): ?>
        <div class="cq-member">
          <b>You are a member</b>
          <p>Access open until <strong><?= e(date('j M Y', strtotime($membership['access_until']))) ?></strong>.</p>
          <a class="cq-pay" href="<?= e($learnUrl) ?>">Go to your campus <?= $arrow ?></a>
          <span class="cq-more">Or add more time</span>
        </div>
      <?php elseif ($membership): ?>
        <p class="cq-ended">Your access ended on <?= e(date('j M Y', strtotime($membership['access_until']))) ?>. Rejoin to continue where you stopped.</p>
      <?php endif; ?>
      <div class="cq-switch" data-switch data-plan="YEARLY">
        <span class="knob" aria-hidden="true"></span>
        <button type="button" aria-pressed="true" data-plan="YEARLY" data-price="<?= (int) $yearly ?>" data-per-label="/ year" data-day-label="About <b><?= e($perDay($yearly, 365)) ?></b> a day" data-amount="<?= e(format_money($yearly)) ?>">Yearly<?php if ($savePct > 0): ?><span class="save">−<?= $savePct ?>%</span><?php endif; ?></button>
        <button type="button" aria-pressed="false" data-plan="MONTHLY" data-price="<?= (int) $monthly ?>" data-per-label="/ month" data-day-label="About <b><?= e($perDay($monthly, 30)) ?></b> a day" data-amount="<?= e(format_money($monthly)) ?>">Monthly</button>
      </div>
      <div class="cq-price"><span class="cur">UGX</span><span class="cq-odo" data-odo aria-hidden="true"><?= e(number_format($yearly)) ?></span><span class="cq-sr" data-price-text aria-live="polite"><?= e(number_format($yearly)) ?></span><span class="per" data-per>/ year</span></div>
      <p class="cq-daily" data-day>About <b><?= e($perDay($yearly, 365)) ?></b> a day</p>

      <?php if (!$viewer): ?>
        <a class="cq-pay" href="<?= e(base_url('signup.php?redirect=' . urlencode($joinBack))) ?>">Create an account to join <?= $arrow ?></a>
        <a class="cq-pay alt" href="<?= e(base_url('login.php?redirect=' . urlencode($joinBack))) ?>">I already have an account</a>
        <p class="cq-note">A free account saves your lessons, progress and certificate.</p>
      <?php elseif (!$published): ?>
        <p class="cq-note">Payments open once this campus is published.</p>
      <?php else: ?>
        <div data-payment-widget data-campus-id="<?= (int) $campus['id'] ?>" data-plan="YEARLY" data-initiate-url="<?= e(base_url('api/initiate-campus.php')) ?>" data-success-redirect="<?= e($learnUrl) ?>">
          <div data-state="phone">
            <label class="cq-field" for="cq-phone">Mobile money number<input id="cq-phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="07XX XXX XXX" value="<?= e($viewer['phone'] ?? '') ?>" data-phone-input></label>
            <button type="button" class="cq-pay" data-action="pay">Pay <span data-pay-amount><?= e(format_money($yearly)) ?></span> <?= $arrow ?></button>
            <div class="cq-err hidden" data-error></div>
          </div>
          <div data-state="waiting" class="hidden"><div class="cq-state"><div class="spin" aria-hidden="true"></div><b>Approve on your phone</b><p data-status-text>Check your phone and approve the mobile money prompt.</p></div></div>
          <div data-state="success" class="hidden"><div class="cq-state"><b>Payment received</b><p>Opening your campus…</p></div></div>
          <div data-state="failed" class="hidden"><div class="cq-state"><b>Payment not completed</b><p data-fail-text>The payment was not completed.</p><button type="button" class="cq-pay alt" data-action="retry">Try again</button></div></div>
        </div>
      <?php endif; ?>
      <div class="cq-momo"><i class="mtn">MTN MoMo</i><i class="air">Airtel Money</i></div>
      <div class="cq-trust">
        <div><?= $tick ?>Opens the moment you pay</div>
        <div><?= $tick ?>Nothing renews by itself</div>
        <div><?= $tick ?>Certificate at the end</div>
      </div>
    </aside>

    <div class="cq-col">
      <section class="cq-block cq-reveal">
        <div class="cq-block-h"><h2>What's <em>included</em></h2><span>Everything, from day one</span></div>
        <div class="cq-incl">
          <div><span class="ic"><?= $ic('<polygon points="6 3 20 12 6 21 6 3"/>') ?></span><b>Video lessons</b><small>Short, step by step</small></div>
          <div><span class="ic"><?= $ic('<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>') ?></span><b>Live classes</b><small>Weekly, recorded</small></div>
          <div><span class="ic"><?= $ic('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>') ?></span><b>PDF guides</b><small>To keep and reuse</small></div>
          <div><span class="ic"><?= $ic('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>') ?></span><b>Community</b><small>Feedback every day</small></div>
        </div>
      </section>

      <section class="cq-block cq-reveal">
        <div class="cq-block-h"><h2>Your <em>professor</em></h2><span>One professor, the whole year</span></div>
        <div class="cq-prof">
          <div class="ph"><?php if ($hasProf && $campus['professor_photo_url']): ?><img src="<?= e(asset_src($campus['professor_photo_url'])) ?>" alt="<?= e($campus['professor_name']) ?>"><?php else: ?><?= e($initials) ?><?php endif; ?></div>
          <div>
            <b><?= e($hasProf ? $campus['professor_name'] : 'Professor to be announced') ?></b>
            <div class="role"><?= e($hasProf ? ($campus['professor_title'] ?: 'Lead professor, ' . $campus['name'] . ' Campus') : 'Someone with real results in ' . $campus['name'] . '.') ?></div>
            <?php if ($hasProf && !empty($campus['professor_quote'])): ?><q><?= e($campus['professor_quote']) ?></q><?php endif; ?>
            <?php if ($links): ?>
              <div class="cq-soc">
                <?php foreach ($links as [$label, $url, $path]): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener nofollow" aria-label="<?= e($campus['professor_name'] . ' on ' . $label) ?>" title="<?= e($label) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $path ?>"/></svg></a><?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="cq-block cq-reveal">
        <div class="cq-block-h"><h2>How it <em>works</em></h2><span>Three steps</span></div>
        <div class="cq-steps">
          <div><b>Pay with Mobile Money</b><small>MTN or Airtel, in a minute.</small></div>
          <div><b>Start your first stage</b><small>Lessons open straight away.</small></div>
          <div><b>Learn live, every week</b><small><?= e($campus['live_schedule'] ? $campus['live_schedule'] . ', with your class.' : 'With your professor and class.') ?></small></div>
        </div>
      </section>

      <section class="cq-block cq-reveal">
        <div class="cq-block-h"><h2>Your <em>year</em></h2><span>12 stages</span></div>
        <div class="cq-year" data-year>
          <?php for ($m = 1; $m <= 12; $m++): $reached = $m <= $classMonth; ?><i class="<?= $reached ? 'open' : '' ?>" style="--n:<?= $m - 1 ?>"><?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?><?= $reached ? '' : $lock ?></i><?php endfor; ?>
        </div>
        <p class="cq-year-note"><?= $lock ?><?= $classMonth > 1 ? 'The class is on stage ' . $classMonth . '. New members get every stage up to it. ' : 'Each stage opens as your class moves forward. ' ?>Lessons are visible to members only.</p>
      </section>

      <section class="cq-block cq-reveal cq-faq">
        <div class="cq-block-h"><h2>Questions</h2><span>Quick answers</span></div>
        <?php foreach ($faq as [$q, $a]): ?><details><summary><?= e($q) ?><i aria-hidden="true">+</i></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
      </section>
    </div>
  </div>

  <div class="cq-paybar"><div><small><?= e(mb_strtoupper($campus['name'])) ?></small><b><?= $open ? 'You are a member' : e(format_money($monthly)) . ' / month' ?></b></div><a href="<?= $open ? e($learnUrl) : '#join' ?>"><?= $open ? 'Open' : 'Join' ?> <?= $arrow ?></a></div>
</div>
<script src="<?= e(versioned_asset('assets/js/campus-page.js')) ?>" defer></script>
<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
