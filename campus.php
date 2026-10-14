<?php
/**
 * One campus, as a page that sells it: a dark hero with a live demo of what students learn to do, who
 * the campus is for, six skills, the year (the professor's real twelve stages and pictures, or the
 * planned path until they are written), the professor with their social links, the weekly rhythm,
 * the join panel (monthly or yearly, paid with mobile money through assets/js/payment.js) and
 * questions. Copy per campus: includes/campus_pages.php. Styles: assets/css/campus-page.css.
 * Motion: assets/js/campus-page.js. Draft campuses are visible to admins only, as a preview.
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

$c = campus_page_content($campus);
$stages = get_campus_stages((int) $campus['id']);
$written = array_filter($stages, fn($s) => trim((string) $s['title']) !== '');
$membership = $viewer ? get_campus_membership((int) $campus['id'], (int) $viewer['id']) : null;
$open = campus_membership_open($membership);
$published = $campus['status'] === 'PUBLISHED';
$isTrading = (bool) preg_match('/crypto|forex|trading/i', $campus['slug'] . ' ' . $campus['name']);
$learnUrl = base_url('campus-learn.php?campus=' . $campus['slug']);
$joinBack = '/campus.php?slug=' . $campus['slug'] . '#join';
$monthly = (float) $campus['monthly_price'];
$yearly = (float) $campus['yearly_price'];
$saving = max(0, $monthly * 12 - $yearly);
$freeMonths = $monthly > 0 ? (int) floor($saving / $monthly) : 0;
$perDay = fn(float $v, int $days) => 'UGX ' . number_format(max(100, round($v / $days / 100) * 100));
$photo = campus_photo($campus);
$links = campus_professor_links($campus);
$hasProf = trim((string) $campus['professor_name']) !== '';
$initials = $hasProf ? mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($campus['professor_name'])), 0, 2)))) : 'P';
$classMonth = campus_open_month($campus);
$quarters = ['Foundations', 'Foundations', 'Foundations', 'Method', 'Method', 'Method', 'Practice', 'Practice', 'Practice', 'Mastery', 'Mastery', 'Mastery'];
$hues = [232, 240, 248, 256, 210, 222, 168, 176, 184, 84, 92, 76];
$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$words = fn(string $s, int $from) => implode(' ', array_map(fn($w, $i) => '<span class="cp-pull" style="--w:' . ($from + $i) . '">' . e($w) . '</span>', preg_split('/\s+/', trim($s)), array_keys(preg_split('/\s+/', trim($s)))));
$headA = $c['headline'][0];
$headB = $c['headline'][1];

$faq = array_merge($c['faq'], [
    ['What if I join in the middle of the year?', 'Every stage the class has already reached is open to you straight away, with recordings of past live classes, so you can catch up at your own pace.'],
    ['How do I pay?', 'With MTN or Airtel Mobile Money. A prompt arrives on your phone; enter your PIN to confirm. Pay monthly (30 days at a time) or once for the year.'],
    ['Will I make money?', $isTrading ? 'Trading carries a real risk of loss, and nobody can promise profit. We teach the skills, risk control and discipline; your results depend on you.' : 'We teach skills people pay for and how to find clients or customers. Your results depend on how much you practise and apply what you learn. We don\'t promise any income.'],
    ['Can I stop any time?', 'Yes. Nothing is charged automatically. If you don\'t pay again, your access ends, and your progress is kept if you come back.'],
]);

$pageTitle = $campus['name'] . ' Campus — Obin Academy';
$pageDescription = $campus['tagline'] ?: ('A year-long ' . $campus['name'] . ' programme at Obin Academy.');
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&family=Geist+Mono:wght@400;500;600';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campus-page.css')) ?>">
<div class="cp" data-cp>
  <?php if (!$published): ?>
    <div class="cp-draft">Preview: this campus is a draft and only admins can see it. <a href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $campus['id'])) ?>">Edit and publish</a></div>
  <?php endif; ?>

  <section class="cp-hero">
    <canvas class="cp-net" aria-hidden="true"></canvas>
    <div class="cp-wrap cp-hero-in">
      <div>
        <p class="cp-crumb cp-rise"><a href="<?= e(base_url('index.php#campuses')) ?>">Campuses</a> &nbsp;/&nbsp; <?= e($campus['name']) ?></p>
        <span class="cp-kicker cp-rise" style="--d:120ms"><i></i><?= $published ? 'Join any time · live classes weekly' : 'Opening soon · live classes weekly' ?></span>
        <h1><?= $words($headA, 0) ?> <em><?= $words($headB, count(preg_split('/\s+/', trim($headA)))) ?></em></h1>
        <p class="cp-lede cp-rise" style="--d:600ms"><?= e($c['lede']) ?></p>
        <div class="cp-acts cp-rise" style="--d:750ms">
          <a class="cp-cta" href="#join" data-magnet><?= $open ? 'Go to your campus' : 'Join the campus' ?> <?= $arrow ?></a>
          <a class="cp-ghost" href="#year">See the twelve months</a>
        </div>
        <div class="cp-facts cp-rise" style="--d:900ms">
          <div><small>Length</small><b>12 months</b></div>
          <div><small>Live class</small><b><?= e($campus['live_schedule'] ?: 'Every week') ?></b></div>
          <div><small>Start with</small><b>Your phone</b></div>
          <div><small>You earn</small><b>A certificate</b></div>
        </div>
      </div>

      <div class="cp-console-wrap cp-rise" style="--d:400ms">
        <div class="cp-console" aria-label="Examples of what you learn to do in this campus">
          <div class="cp-console-top"><i></i><i></i><i></i><span><?= e($c['demo_title']) ?></span><em><?= e($c['demo_badge']) ?></em></div>
          <div class="cp-console-body">
            <div class="cp-msg you"><span class="av">YOU</span><p data-q><?= e($c['tasks'][0][1]) ?></p></div>
            <div class="cp-msg ai"><span class="av"><?= e($c['demo_who']) ?></span><p data-a><?= e($c['tasks'][0][2]) ?></p></div>
          </div>
          <div class="cp-console-foot"><span data-task-name>Task 1 of <?= count($c['tasks']) ?> · <b><?= e($c['tasks'][0][0]) ?></b></span><span class="cp-dots"><?php foreach ($c['tasks'] as $t): ?><i></i><?php endforeach; ?></span></div>
        </div>
        <?php if ($photo): ?><div class="cp-photo"><img src="<?= e($photo) ?>" alt=""></div><?php endif; ?>
        <script type="application/json" data-tasks><?= json_encode($c['tasks'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      </div>
    </div>
  </section>

  <section class="cp-sec">
    <div class="cp-wrap cp-curtain">
      <div class="cp-h">
        <div><span class="cp-label">Is this you?</span><h2><?= e($c['fit_h2'][0]) ?> <em><?= e($c['fit_h2'][1]) ?></em></h2></div>
        <p><?= e($c['fit_p']) ?></p>
      </div>
      <div class="cp-fit cp-stagger">
        <?php foreach ($c['fit'] as [$t, $d]): ?><div><span class="tick"><?= $check ?></span><b><?= e($t) ?></b><p><?= e($d) ?></p></div><?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($c['skills']): ?>
  <section class="cp-band cp-sec">
    <div class="cp-wrap cp-curtain">
      <div class="cp-h">
        <div><span class="cp-label"><?= e($c['skills_label']) ?></span><h2><?= e($c['skills_h2'][0]) ?> <em><?= e($c['skills_h2'][1]) ?></em></h2></div>
        <p><?= e($c['skills_p']) ?></p>
      </div>
      <div class="cp-skills" data-glow>
        <?php foreach ($c['skills'] as [$icon, $when, $t, $d]): ?>
          <div class="cp-skill"><span class="ic"><?= campus_page_icon($icon) ?></span><small><?= e($when) ?></small><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
        <?php endforeach; ?>
      </div>
      <?php if ($c['skills_note']): ?><p class="cp-band-note"><?= e($c['skills_note']) ?></p><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="cp-sec" id="year">
    <div class="cp-wrap cp-curtain">
      <div class="cp-h">
        <div><span class="cp-label"><?= $written ? 'Your year' : 'The planned year' ?></span><h2>Twelve stages, <em>taught as a class.</em></h2></div>
        <p>Your professor opens each stage when the class completes the one before. Join at any point and every stage the class has reached is open for you to catch up.</p>
      </div>
      <?php if ($written || $c['year']): ?>
        <div class="cp-year">
          <div class="cp-trace"><i data-trace></i></div>
          <div class="cp-months">
            <?php for ($m = 1; $m <= 12; $m++):
                $dbTitle = trim((string) $stages[$m]['title']);
                [$title, $sum] = $written ? [$dbTitle !== '' ? $dbTitle : 'Stage ' . $m, (string) $stages[$m]['summary']] : ($c['year'][$m - 1] ?? ['Stage ' . $m, '']);
                $th = $stages[$m]['thumbnail_url'] ?? null; ?>
              <div class="cp-month cp-stagger-m">
                <div class="node<?= $th ? ' pic' : '' ?>" style="<?= $th ? 'background-image:url(\'' . e(asset_src($th)) . '\')' : 'background:linear-gradient(140deg, hsl(' . $hues[$m - 1] . ' 60% 28%), hsl(' . $hues[$m - 1] . ' 70% 12%))' ?>"><?= $th ? '' : $m ?></div>
                <span class="q">Month <?= $m ?> · <?= $quarters[$m - 1] ?></span><b><?= e($title) ?></b><?php if ($sum !== ''): ?><p><?= e($sum) ?></p><?php endif; ?>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
      <div class="cp-year-foot"><span>Finish every lesson of the twelve stages and you receive the <b><?= e($campus['name']) ?> Campus certificate</b>.</span><span class="pill">● <?= $classMonth > 1 ? 'The class is on month ' . $classMonth : 'The class starts at month 1' ?></span></div>
    </div>
  </section>

  <section class="cp-sec cp-flush">
    <div class="cp-wrap cp-curtain">
      <div class="cp-h">
        <div><span class="cp-label">Your professor</span><h2>Taught by someone <em>who does this for a living.</em></h2></div>
        <p>Every Obin Academy campus is led by one professor for the whole year, chosen for real results in the skill they teach.</p>
      </div>
      <div class="cp-prof">
        <div class="cp-prof-card" data-tilt>
          <div class="ph"><?php if ($hasProf && $campus['professor_photo_url']): ?><img src="<?= e(asset_src($campus['professor_photo_url'])) ?>" alt="<?= e($campus['professor_name']) ?>"><?php else: ?><span><?= e($initials) ?></span><?php endif; ?></div>
          <span class="tag"><?= $hasProf ? 'LEAD PROFESSOR' : 'ANNOUNCED SOON' ?></span>
          <div class="who"><b><?= e($hasProf ? $campus['professor_name'] : 'Professor to be announced') ?></b><small><?= e($hasProf ? ($campus['professor_title'] ?: 'Lead professor, ' . $campus['name'] . ' Campus') : 'We are choosing someone with real results in ' . $campus['name'] . '.') ?></small></div>
          <?php if ($links): ?>
            <div class="cp-socials">
              <?php $s = 0; foreach ($links as $key => [$label, $url, $path]): ?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener nofollow" style="--s:<?= $s++ ?>" aria-label="<?= e($campus['professor_name'] . ' on ' . $label) ?>" title="<?= e($label) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $path ?>"/></svg></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="cp-prof-copy">
          <h3><?= $hasProf && !empty($campus['professor_quote']) ? '“' . e($campus['professor_quote']) . '”' : ($hasProf ? 'Learn directly from ' . e(campus_first_name($campus['professor_name'])) . ', live every week.' : 'A professor who has done the work.') ?></h3>
          <?php if ($hasProf && $campus['professor_bio']): ?><p><?= nl2br(e($campus['professor_bio'])) ?></p><?php elseif (!$hasProf): ?><p>The professor for this campus will be announced here. Every professor teaches live every week, answers questions in the community, and reviews your work as you go.</p><?php endif; ?>
          <div class="cp-creds">
            <div><b>Teaches</b><span><?= e($campus['live_schedule'] ? 'Live every week, ' . $campus['live_schedule'] . ', with recordings' : 'A live class every week, with recordings') ?></span></div>
            <?php if ($hasProf && $campus['professor_title']): ?><div><b>Background</b><span><?= e($campus['professor_title']) ?></span></div><?php endif; ?>
            <?php if ($links): ?><div><b>Follow</b><span>Tap an icon to see <?= e(campus_first_name($campus['professor_name'])) ?>'s work before you join</span></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cp-sec cp-flush">
    <div class="cp-wrap cp-curtain">
      <div class="cp-h"><div><span class="cp-label">Your week</span><h2>A rhythm that <em>keeps you moving.</em></h2></div><p>Small steps, every week, with someone checking you are on track.</p></div>
      <div class="cp-rhythm cp-stagger">
        <div><span class="cp-label">Each stage</span><b>Video lessons and guides</b><p>Short videos and PDF guides you can follow on your phone, at your pace.</p></div>
        <div><span class="cp-label">Each week</span><b><?= e($campus['live_schedule'] ? 'Live, ' . $campus['live_schedule'] : 'A live class') ?></b><p>Your professor teaches, shows real work and answers your questions. Recorded if you miss it.</p></div>
        <div><span class="cp-label">Every day</span><b>The community</b><p>Share your work, get feedback and learn from classmates building the same skill.</p></div>
      </div>
      <?php if ($isTrading): ?><p class="cp-fine">Trading carries a real risk of loss. Lessons are educational and are not financial advice.</p><?php endif; ?>
    </div>
  </section>

  <section class="cp-band cp-sec" id="join">
    <div class="cp-wrap cp-join cp-curtain">
      <div>
        <span class="cp-label lime">Join the campus</span>
        <h2>Start this week. <em>Pay with Mobile Money.</em></h2>
        <p class="cp-lede">A prompt arrives on your phone, you enter your PIN, and your campus opens straight away.</p>
        <div class="cp-inc">
          <div><?= $check ?>Every lesson and guide the class has reached</div>
          <div><?= $check ?>A live class every week, with recordings</div>
          <div><?= $check ?>The private community and your professor</div>
          <div><?= $check ?>Nothing renews by itself. We remind you before access ends</div>
        </div>
      </div>
      <div class="cp-plan-box">
        <?php if ($open): ?>
          <div class="cp-member">
            <span class="cp-label">Your membership</span>
            <b>You are a member</b>
            <p>Access open until <strong><?= e(date('j M Y', strtotime($membership['access_until']))) ?></strong> (<?= $membership['plan'] === 'YEARLY' ? 'yearly' : 'monthly' ?>).</p>
            <a class="cp-pay" href="<?= e($learnUrl) ?>">Go to your campus <?= $arrow ?></a>
            <span class="cp-more">Add more time below</span>
          </div>
        <?php elseif ($membership): ?>
          <p class="cp-ended">Your access ended on <?= e(date('j M Y', strtotime($membership['access_until']))) ?>. Rejoin to continue where you stopped.</p>
        <?php endif; ?>
        <div class="cp-switch" data-switch data-plan="YEARLY">
          <span class="knob" aria-hidden="true"></span>
          <button type="button" aria-pressed="true" data-plan="YEARLY" data-amount="<?= e(format_money($yearly)) ?>" data-per="per year" data-day="About <b><?= e($perDay($yearly, 365)) ?> a day</b>, for twelve months of access.">Yearly<?php if ($freeMonths >= 1): ?><small><?= $freeMonths ?> MONTH<?= $freeMonths === 1 ? '' : 'S' ?> FREE</small><?php elseif ($saving > 0): ?><small>SAVE <?= e(format_money($saving)) ?></small><?php endif; ?></button>
          <button type="button" aria-pressed="false" data-plan="MONTHLY" data-amount="<?= e(format_money($monthly)) ?>" data-per="per month" data-day="About <b><?= e($perDay($monthly, 30)) ?> a day</b>, 30 days at a time.">Monthly</button>
        </div>
        <div class="cp-price"><b data-amount><?= e(format_money($yearly)) ?></b><span data-per>per year</span></div>
        <p class="cp-per-day" data-day>About <b><?= e($perDay($yearly, 365)) ?> a day</b>, for twelve months of access.</p>

        <?php if (!$viewer): ?>
          <p class="cp-fine">Create a free account or log in first, so your lessons, progress and certificate are saved to you.</p>
          <a class="cp-pay" href="<?= e(base_url('signup.php?redirect=' . urlencode($joinBack))) ?>">Create an account to join <?= $arrow ?></a>
          <a class="cp-pay alt" href="<?= e(base_url('login.php?redirect=' . urlencode($joinBack))) ?>">I already have an account</a>
        <?php elseif (!$published): ?>
          <p class="cp-fine">Payments open once this campus is published.</p>
        <?php else: ?>
          <div data-payment-widget data-campus-id="<?= (int) $campus['id'] ?>" data-plan="YEARLY" data-initiate-url="<?= e(base_url('api/initiate-campus.php')) ?>" data-success-redirect="<?= e($learnUrl) ?>">
            <div data-state="phone">
              <label class="cp-field" for="cp-phone">Mobile money number<input id="cp-phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="07XX XXX XXX" value="<?= e($viewer['phone'] ?? '') ?>" data-phone-input></label>
              <button type="button" class="cp-pay" data-action="pay">Pay <span data-pay-amount><?= e(format_money($yearly)) ?></span></button>
              <div class="cp-err hidden" data-error></div>
            </div>
            <div data-state="waiting" class="hidden"><div class="cp-state"><div class="spin" aria-hidden="true"></div><b>Approve on your phone</b><p data-status-text>Check your phone and approve the mobile money prompt.</p></div></div>
            <div data-state="success" class="hidden"><div class="cp-state"><b>Payment received</b><p>Opening your campus…</p></div></div>
            <div data-state="failed" class="hidden"><div class="cp-state"><b>Payment not completed</b><p data-fail-text>The payment was not completed.</p><button type="button" class="cp-pay alt" data-action="retry">Try again</button></div></div>
          </div>
        <?php endif; ?>
        <div class="cp-momo">Pay with <i class="mtn">MTN MoMo</i><i class="air">Airtel Money</i></div>
      </div>
    </div>
  </section>

  <section class="cp-sec">
    <div class="cp-wrap cp-curtain">
      <div class="cp-h"><div><span class="cp-label">Questions</span><h2>Before you <em>join.</em></h2></div><p>Still unsure? <a href="<?= e(base_url('contact.php')) ?>">Send us a message</a> and a real person answers.</p></div>
      <div class="cp-faq">
        <?php foreach ($faq as $i => [$q, $a]): ?>
          <details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?><i aria-hidden="true">+</i></summary><p><?= e($a) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="cp-dock" data-dock><div><small><?= e($campus['name']) ?> Campus</small><b><?= $open ? 'You are a member' : 'From ' . e(format_money($monthly)) . ' / month' ?></b></div><a href="<?= $open ? e($learnUrl) : '#join' ?>"><?= $open ? 'Open' : 'Join' ?></a></div>
</div>
<script src="<?= e(versioned_asset('assets/js/campus-page.js')) ?>" defer></script>
<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
