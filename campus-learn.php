<?php
/**
 * A member's campus: home, lessons by month, one lesson, live classes, community, certificate, membership.
 * Open while the member's access is open; otherwise it shows the "access ended" page with a way back in.
 * Admins see every campus in full, to check content before and after publishing.
 * Laid out as a step-by-step path in one column: Your path (home) > Month > Lesson, with Back in the top
 * bar. Live classes, Community, Certificate and Membership sit one level under the path.
 * Styles: assets/css/campus-learn.css (the access-ended page still uses campuses.css). Script: assets/js/campus-learn.js.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

$user = current_user();
$slug = query_param('campus');
if (!$user) redirect('/login.php?redirect=' . urlencode('/campus-learn.php?campus=' . $slug));
$campus = get_campus_by_slug($slug);
// Admins and the campus's own professor see the whole campus, published or not, without paying.
$isProf = $campus && campus_is_professor($user, $campus);
$isAdmin = $user['role'] === 'ADMIN' || $isProf;
$staffLabel = $isProf ? 'Professor' : 'Admin preview';
if (!$campus || ($campus['status'] !== 'PUBLISHED' && !$isAdmin)) redirect('/index.php#campuses');

$campusId = (int) $campus['id'];
$membership = get_campus_membership($campusId, (int) $user['id']);
$open = $isAdmin || campus_membership_open($membership);
$currentMonth = $isAdmin ? CAMPUS_YEAR_MONTHS : ($membership ? campus_current_month($membership) : 1);
$self = fn(array $q = []) => base_url('campus-learn.php?' . http_build_query(['campus' => $campus['slug']] + $q));

// ---- actions ---------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    csrf_verify();
    $action = post('_action');
    if ($action === 'done') {
        $lesson = get_campus_lesson((int) post('lesson'));
        if ($lesson && (int) $lesson['campus_id'] === $campusId && campus_can_open_lesson($user, $lesson)) {
            mark_campus_lesson_done((int) $user['id'], (int) $lesson['id']);
            if (!$isAdmin && campus_issue_certificate_if_earned($campusId, (int) $user['id'])) {
                flash_set('success', 'You have completed the full year. Your certificate is ready.');
                redirect($self(['view' => 'certificate']));
            }
        }
        // Done: straight on to the next lesson, or back to the month when it was the last one open.
        redirect(post('next') !== '' ? $self(['view' => 'lesson', 'lesson' => (int) post('next')]) : $self(['view' => 'lessons', 'month' => (int) ($lesson['month_no'] ?? 1)]));
    }
    if ($action === 'post') {
        $body = trim(mb_substr(post('body'), 0, 2000));
        if ($body !== '') db_insert('INSERT INTO campus_posts (campus_id, user_id, body) VALUES (?, ?, ?)', [$campusId, $user['id'], $body]);
        redirect($self(['view' => 'community']));
    }
    if ($action === 'delete_post' && $isAdmin) {
        db_run('UPDATE campus_posts SET deleted_at = NOW() WHERE id = ? AND campus_id = ?', [(int) post('post'), $campusId]);
        redirect($self(['view' => 'community']));
    }
    if ($action === 'reminders' && $membership) {
        db_run('UPDATE campus_memberships SET reminders_off = ? WHERE id = ?', [post('off') === '1' ? 1 : 0, $membership['id']]);
        flash_set('success', post('off') === '1' ? 'Reminders are off. Your access still runs until its end date.' : 'Reminders are on again.');
        redirect($self(['view' => 'membership']));
    }
}

// ---- data ------------------------------------------------------------------------------------
$view = query_param('view', 'home');
if (!in_array($view, ['home', 'lessons', 'lesson', 'live', 'community', 'certificate', 'membership'], true)) $view = 'home';
$stages = get_campus_stages($campusId);
// Where this student is in the year. With groups by month (migration/add-month-groups.sql) every
// student has their own month; staff see the whole year.
if (campus_groups_ready()) {
    campus_release_due_months($campusId);
    if ($membership) $membership = get_campus_membership($campusId, (int) $user['id']);
    $classMonth = $isAdmin ? 12 : ($membership ? campus_member_month($membership) : 1);
    $monthsDone = $isAdmin ? 0 : ($membership && (int) $membership['month_open'] > 12 ? 12 : $classMonth - 1);
    if (!$isAdmin) $currentMonth = $classMonth;
} else {
    $classMonth = campus_open_month($campus);
    $monthsDone = campus_months_completed($campus);
}
$byMonth = get_campus_lessons_by_month($campusId);
$done = array_flip(campus_completed_lesson_ids((int) $user['id'], $campusId));
$total = array_sum(array_map('count', $byMonth));
$pct = $total ? (int) round(count($done) / $total * 100) : 0;
$unlockedLessons = [];
foreach ($byMonth as $mn => $ls) if ($mn <= $currentMonth) foreach ($ls as $l) $unlockedLessons[] = $l;
$nextLesson = null;
foreach ($unlockedLessons as $l) { if (!isset($done[(int) $l['id']])) { $nextLesson = $l; break; } }
$daysLeft = $membership ? campus_days_left($membership) : null;
$mTitle = fn(int $m) => trim((string) ($stages[$m]['title'] ?? ''));
$kindLabel = fn(array $l) => ($l['kind'] === 'PDF' ? 'PDF guide' : 'Video') . ($l['duration_label'] ? ' · ' . $l['duration_label'] : '');
$profLabel = $campus['professor_name'] ?: 'Obin Academy';
$hour = (int) date('G');
$greeting = ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening')) . ', ' . campus_first_name($user['name']);
$join = base_url('campus.php?slug=' . $campus['slug'] . '#join');
// Who a community post is from: the linked professor posts as themselves; admin posts show as the
// professor until one is linked, then as the Obin Academy team.
$poster = function (array $p) use ($campus, $profLabel): array {
    if (!empty($campus['professor_user_id']) && (int) $p['user_id'] === (int) $campus['professor_user_id']) return [$profLabel, 'Professor', true];
    if ($p['role'] === 'ADMIN') return empty($campus['professor_user_id']) ? [$profLabel, 'Team', true] : ['Obin Academy', 'Team', true];
    return [$p['name'], '', false];
};
$avColors = ['#0f766e', '#be185d', '#b45309', '#6d28d9', '#15803d', '#0369a1'];
$avColor = fn(string $name) => $avColors[abs(crc32($name)) % count($avColors)];
$monthDone = fn(int $m) => count(array_filter($byMonth[$m] ?? [], fn($l) => isset($done[(int) $l['id']])));

// Live classes: the next one drives the countdown.
$upcoming = $open ? upcoming_campus_live_classes(60, $campusId) : [];
$nextLive = $upcoming[0] ?? null;
$liveAt = $nextLive ? strtotime($nextLive['starts_at']) : 0;
$liveNow = $nextLive && $liveAt <= time();
$liveDay = $nextLive ? (int) round((strtotime(date('Y-m-d', $liveAt)) - strtotime(date('Y-m-d'))) / 86400) : 0;
$liveTag = !$nextLive ? '' : ($liveNow ? 'On now' : ($liveDay === 0 ? 'Today' : ($liveDay === 1 ? 'Tomorrow' : 'In ' . $liveDay . ' days')));

$ic = [
    'back' => '<path d="M15 18l-6-6 6-6"/>',
    'chev' => '<path d="M9 6l6 6-6 6"/>',
    'right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'live' => '<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4"/>',
    'community' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'certificate' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
    'membership' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>',
    'campuses' => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/>',
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
];
$svg = fn(string $k, string $cls = '') => '<svg' . ($cls ? ' class="' . $cls . '"' : '') . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . (in_array($k, ['back', 'chev', 'right', 'left'], true) ? '2' : '1.8') . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic[$k] . '</svg>';
$check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
$play = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>';
$arrow = $svg('right', 'r');
$chev = $svg('chev', 'cl-chev');
// A month's picture (uploaded by the professor), or the deep blue ground; $inner goes on top.
$thumb = function (int $m, string $inner = '', string $cls = '') use ($stages): string {
    $th = $stages[$m]['thumbnail_url'] ?? null;
    return '<span class="cl-thumb' . ($th ? '' : ' none') . ($cls ? ' ' . $cls : '') . '"' . ($th ? ' style="background-image:url(\'' . e(asset_src($th)) . '\')"' : '') . '>' . $inner . '</span>';
};
$track = fn(int $p) => '<span class="cl-track" style="--p:' . max(0, min(100, $p)) . '%"><i></i></span>';

// Where each page sits in the path: depth for the slide direction (deeper slides forward).
$rank = ['home' => 0, 'lessons' => 1, 'live' => 1, 'community' => 1, 'certificate' => 1, 'membership' => 1, 'lesson' => 2][$view];
$flash = function_exists('flash_get') ? (flash_get('success') ?: null) : null;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#f4f5f9">
  <title><?= e($campus['name']) ?> Campus — Obin Academy</title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&display=swap" rel="stylesheet">
<?php if (!$open): ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
  <style>body { margin: 0; font-family: "Geist", ui-sans-serif, system-ui, sans-serif; background: #f4f5f8; -webkit-font-smoothing: antialiased; }</style>
<?php else: ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campus-learn.css')) ?>">
  <script>
    // Slide direction between pages: deeper in the path slides forward, coming out slides back.
    (function () {
      var here = <?= json_encode([$rank, $view === 'lesson' ? (int) query_param('lesson') : ($view === 'lessons' ? (int) query_param('month') : 0)]) ?>;
      addEventListener('pageswap', function () { try { sessionStorage.setItem('cl-rank', JSON.stringify(here)); } catch (e) {} });
      addEventListener('pagereveal', function (e) {
        if (!e.viewTransition) return;
        var was = null; try { was = JSON.parse(sessionStorage.getItem('cl-rank')); } catch (x) {}
        var dir = !was ? 'fwd' : (here[0] !== was[0] ? (here[0] > was[0] ? 'fwd' : 'bwd') : (here[1] >= was[1] ? 'fwd' : 'bwd'));
        document.documentElement.classList.add(dir);
        e.viewTransition.finished.finally(function () { document.documentElement.classList.remove(dir); });
      });
    })();
  </script>
  <script src="<?= e(versioned_asset('assets/js/campus-learn.js')) ?>" defer></script>
<?php endif; ?>
</head>
<?php if (!$open): ?>
<body>
<div class="cx">
  <div class="cx-lock">
    <span class="cx-label"><?= e($campus['name']) ?> Campus</span>
    <?php if ($membership): ?>
      <h1>Your access has ended.</h1>
      <p>Your access closed on <?= e(date('j F Y', strtotime($membership['access_until']))) ?>. Choose a plan to reopen your lessons, guides, live classes and community. You continue from month <?= campus_current_month($membership) ?>, and nothing you did is lost.</p>
    <?php else: ?>
      <h1>Join to enter this campus.</h1>
      <p>This campus is for members. Join monthly or for the year to open the lessons, guides, live classes and community.</p>
    <?php endif; ?>
    <div class="cx-enrol">
      <div class="cx-opt" style="cursor:default;"><span class="rd"></span><span><b>Monthly</b><small>30 days at a time</small></span><span class="amt"><?= e(format_money((float) $campus['monthly_price'])) ?><small>per month</small></span></div>
      <div class="cx-opt" style="cursor:default;"><span class="rd"></span><span><b>Yearly</b><small>12 months</small></span><span class="amt"><?= e(format_money((float) $campus['yearly_price'])) ?><small>per year</small></span></div>
      <div class="eb"><a class="cx-btn solid full" href="<?= e($join) ?>">Choose a plan and pay</a><p class="note">Pay with MTN or Airtel Money.</p></div>
    </div>
    <p class="cx-fine" style="margin-top:22px;"><a href="<?= e(base_url('dashboard.php')) ?>" style="text-decoration:underline;">Back to your dashboard</a></p>
  </div>
</div>
</body>
<?php else:
    // ---- the top bar: Back + where you are ----
    $lessonRow = $view === 'lesson' ? get_campus_lesson((int) query_param('lesson')) : null;
    $lessonOk = $lessonRow && (int) $lessonRow['campus_id'] === $campusId && campus_can_open_lesson($user, $lessonRow);
    $viewMonth = max(1, min(12, (int) query_param('month', (string) ($isAdmin ? (int) ($nextLesson['month_no'] ?? 1) : $currentMonth))));
    $lm = 0; $mls = []; $pos = 0;
    if ($view === 'home') { $backUrl = null; $crumb = $isAdmin ? $staffLabel : 'Your campus'; $title = $campus['name']; }
    elseif ($view === 'lesson' && $lessonOk) {
        $lm = (int) $lessonRow['month_no']; $mls = $byMonth[$lm] ?? [];
        $pos = (int) array_search((int) $lessonRow['id'], array_map(fn($l) => (int) $l['id'], $mls), true);
        $backUrl = $self(['view' => 'lessons', 'month' => $lm]); $crumb = 'Your path › Month ' . $lm; $title = 'Lesson ' . ($pos + 1) . ' of ' . count($mls);
    }
    elseif ($view === 'lessons') { $backUrl = $self(); $crumb = 'Your path'; $title = 'Month ' . $viewMonth; }
    else { $backUrl = $self(); $crumb = 'Your path'; $title = ['lesson' => 'Lesson', 'live' => 'Live classes', 'community' => 'Community', 'certificate' => 'Certificate', 'membership' => 'Membership'][$view]; }
?>
<body class="cl-body">
<header class="cl-bar"><div class="cl-bar-in">
  <div class="cl-where">
    <?php if ($backUrl): ?><a class="cl-back" href="<?= e($backUrl) ?>" aria-label="Back to <?= $view === 'lesson' && $lessonOk ? 'month ' . $lm : 'your path' ?>"><?= $svg('back') ?></a>
    <?php else: ?><a class="cl-mark" href="<?= e(base_url('dashboard.php')) ?>" title="Your dashboard" aria-label="Obin Academy dashboard">O</a><?php endif; ?>
    <div class="cl-crumbs"><small><?= e($crumb) ?></small><b><?= e($title) ?></b></div>
  </div>
  <a class="cl-me" href="<?= e($self(['view' => 'membership'])) ?>" title="<?= e($user['name']) ?> · Membership"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></a>
</div></header>

<main class="cl-page">
  <?php if ($flash): ?><div class="cl-notice good cl-rise" role="status"><?= $check ?><span><?= e($flash) ?></span></div><?php endif; ?>
  <?php if (!$isAdmin && $daysLeft !== null && $daysLeft <= 7 && $view !== 'membership'): ?>
    <div class="cl-notice <?= $daysLeft <= 1 ? 'bad' : 'warn' ?> cl-rise" role="status">
      <?= $svg('clock') ?>
      <span><b><?= $daysLeft <= 0 ? 'Your access ends today.' : 'Your access ends in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ', on ' . e(date('j M', strtotime($membership['access_until']))) . '.' ?></b> Renew to keep your lessons, live classes and community.</span>
      <a class="cl-btn solid sm" href="<?= e($join) ?>">Renew</a>
    </div>
  <?php endif; ?>

<?php if ($view === 'home'): ?>
  <section class="cl-rise" style="--i:0">
    <span class="cl-eyebrow"><?= e($greeting) ?></span>
    <h1><?= $isAdmin ? 'The campus <em>path</em>' : 'Your <em>path</em> this year' ?></h1>
    <div class="cl-prog">
      <div class="cl-prog-top"><span><?= $isAdmin ? 'Every month open' : 'Month <b>' . $classMonth . '</b> of 12' ?></span><span class="cl-mono"><?= count($done) ?> of <?= $total ?> lessons · <?= $pct ?>%</span></div>
      <?= $track($pct) ?>
    </div>
  </section>

  <section class="cl-rise" style="--i:1">
    <h2 class="cl-sec">Step now</h2>
    <?php if ($nextLesson): $nm = (int) $nextLesson['month_no']; $nls = $byMonth[$nm] ?? []; $p = (int) array_search((int) $nextLesson['id'], array_map(fn($l) => (int) $l['id'], $nls), true); ?>
      <a class="cl-now" href="<?= e($self(['view' => 'lesson', 'lesson' => $nextLesson['id']])) ?>">
        <?= $thumb($nm, '<span class="cl-play">' . $play . '</span>') ?>
        <span class="cl-now-txt"><small>Month <?= $nm ?> · Lesson <?= $p + 1 ?> of <?= count($nls) ?></small><b><?= e($nextLesson['title']) ?></b><span class="m"><?= e($kindLabel($nextLesson)) ?></span><span class="cl-go"><?= count($done) ? 'Continue lesson' : 'Start your first lesson' ?><?= $arrow ?></span></span>
      </a>
    <?php elseif ($total && count($done) >= count($unlockedLessons)): ?>
      <div class="cl-box"><b>You are up to date</b><p><?= $monthsDone >= 12 ? 'You have reached the end of the year. Finish any lesson left to earn your certificate.' : 'Month ' . ($classMonth + 1) . ' opens when your professor completes month ' . $classMonth . ' for your group. Join the live class in the meantime.' ?></p></div>
    <?php else: ?>
      <div class="cl-box"><b>Your first lessons are being prepared</b><p>Your professor is uploading this month's lessons. You will see them here as soon as they are ready.</p></div>
    <?php endif; ?>
  </section>

  <section class="cl-rise" style="--i:2">
    <h2 class="cl-sec"><?= $isAdmin ? 'The 12 months' : 'Your 12 months' ?></h2>
    <ol class="cl-steps">
      <?php for ($m = 1; $m <= 12; $m++):
          $cnt = count($byMonth[$m] ?? []); $t = $mTitle($m); $name = 'Month ' . $m . ($t !== '' ? ' · ' . $t : '');
          $st = $isAdmin ? 'open' : ($m <= $monthsDone ? 'done' : ($m === $classMonth ? 'cur' : 'lock'));
          if ($st === 'lock' && $m > $classMonth + 1) {
              if ($m === $classMonth + 2): ?>
        <li class="cl-step lock" style="--i:<?= $m ?>"><span class="cl-node"><?= $svg('lock') ?></span><div class="cl-row"><span><b><?= $m === 12 ? 'Month 12' : 'Months ' . $m . ' to 12' ?></b><small>Each one opens after the month before it</small></span></div></li>
              <?php endif; continue;
          }
          if ($st === 'cur'): $d = $monthDone($m); ?>
        <li class="cl-step cur" style="--i:<?= $m ?>"><span class="cl-node"><?= $m ?></span>
          <div class="cl-card">
            <span class="cl-eyebrow">Month <?= $m ?> · your month now</span>
            <div class="cl-ttl"><?= e($t !== '' ? $t : 'Month ' . $m) ?></div>
            <?php if ($cnt): ?><div class="cl-mini"><span class="cl-mono"><?= $d ?>/<?= $cnt ?></span><?= $track((int) round($d / $cnt * 100)) ?><span>lessons done</span></div><?php else: ?><span class="cl-m">Lessons for this month are on their way.</span><?php endif; ?>
            <a class="cl-btn solid" href="<?= e($self(['view' => 'lessons', 'month' => $m])) ?>">Open month <?= $m ?><?= $arrow ?></a>
          </div>
        </li>
          <?php elseif ($st === 'lock'): ?>
        <li class="cl-step lock" style="--i:<?= $m ?>"><span class="cl-node"><?= $svg('lock') ?></span><div class="cl-row"><span><b>Month <?= $m ?></b><small>Opens when your professor completes month <?= $m - 1 ?></small></span></div></li>
          <?php else: ?>
        <li class="cl-step <?= $st ?>" style="--i:<?= $m ?>"><span class="cl-node"><?= $st === 'done' ? $check : $m ?></span><a class="cl-row" href="<?= e($self(['view' => 'lessons', 'month' => $m])) ?>"><span><b><?= e($name) ?></b><small><?= $st === 'done' ? 'Completed · ' : '' ?><?= $cnt ?> lesson<?= $cnt === 1 ? '' : 's' ?></small></span><?= $chev ?></a></li>
          <?php endif; ?>
      <?php endfor; ?>
    </ol>
  </section>

  <section class="cl-rise" style="--i:3">
    <h2 class="cl-sec">More in your campus</h2>
    <nav class="cl-menu" aria-label="More in your campus">
      <?php $latest = get_campus_posts($campusId, 1)[0] ?? null; ?>
      <a class="cl-row" href="<?= e($self(['view' => 'live'])) ?>"><span class="cl-ico live"><?= $svg('live') ?></span><span><b>Live classes</b><small><?= $nextLive ? 'Next: ' . e(date('l g:i a', $liveAt)) : 'The next class will be announced here' ?></small></span><?= $nextLive ? '<span class="cl-tag' . ($liveNow ? ' on' : '') . '">' . e($liveTag) . '</span>' : $chev ?></a>
      <a class="cl-row" href="<?= e($self(['view' => 'community'])) ?>"><span class="cl-ico"><?= $svg('community') ?></span><span><b>Community</b><small><?= $latest ? e($poster($latest)[0]) . ' posted ' . e(time_ago($latest['created_at'])) : 'Say hello to your classmates' ?></small></span><?= $chev ?></a>
      <a class="cl-row" href="<?= e($self(['view' => 'certificate'])) ?>"><span class="cl-ico"><?= $svg('certificate') ?></span><span><b>Certificate</b><small><?= !empty($membership['certificate_code']) ? 'Ready to view and share' : 'After all twelve months' ?></small></span><?= $chev ?></a>
      <a class="cl-row" href="<?= e($self(['view' => 'membership'])) ?>"><span class="cl-ico"><?= $svg('membership') ?></span><span><b>Membership</b><small><?= $isAdmin && !$membership ? e($staffLabel) : (($membership['plan'] ?? '') === 'YEARLY' ? 'Yearly' : 'Monthly') . ' · access until ' . e(date('j M', strtotime($membership['access_until']))) ?></small></span><?= $chev ?></a>
    </nav>
  </section>

<?php elseif ($view === 'lessons'): $month = $viewMonth; $ls = $byMonth[$month] ?? []; $d = $monthDone($month); $nx = null; foreach ($ls as $k => $l) { if (!isset($done[(int) $l['id']])) { $nx = $k; break; } } ?>
  <section class="cl-mhead cl-rise" style="--i:0">
    <?= $thumb($month, '<b>' . $month . '</b>') ?>
    <div><span class="cl-eyebrow">Month <?= $month ?> of 12<?= !$isAdmin && $month === $classMonth ? ' · your month now' : ($month <= $monthsDone ? ' · completed' : '') ?></span><h1 class="cl-h1-sm"><?= e($mTitle($month) !== '' ? $mTitle($month) : 'Month ' . $month) ?></h1></div>
  </section>
  <?php if ($month > $currentMonth): ?>
    <div class="cl-box cl-rise" style="--i:1"><b>This month is still locked</b><p>It opens when your professor completes month <?= $month - 1 ?> for your group. You are on month <?= $classMonth ?>.</p><a class="cl-btn" href="<?= e($self(['view' => 'lessons', 'month' => $classMonth])) ?>">Go to month <?= $classMonth ?></a></div>
  <?php else: ?>
    <?php if (trim((string) $stages[$month]['summary']) !== ''): ?><p class="cl-sub cl-rise" style="--i:1"><?= e($stages[$month]['summary']) ?></p><?php endif; ?>
    <?php if ($ls): ?>
      <div class="cl-prog cl-rise" style="--i:1"><div class="cl-prog-top"><span>Progress this month</span><span class="cl-mono"><?= $d ?> of <?= count($ls) ?></span></div><?= $track((int) round($d / count($ls) * 100)) ?></div>
    <?php endif; ?>
    <section class="cl-rise" style="--i:2">
      <h2 class="cl-sec">Lessons, in order</h2>
      <?php if (!$ls): ?><div class="cl-box"><b>Lessons are on their way</b><p>Your professor is uploading this month's lessons. They appear here as soon as they are ready.</p></div><?php endif; ?>
      <ol class="cl-steps">
        <?php foreach ($ls as $k => $l): $id = (int) $l['id']; $isD = isset($done[$id]); $url = $self(['view' => 'lesson', 'lesson' => $id]); ?>
          <?php if ($k === $nx): ?>
            <li class="cl-step cur" style="--i:<?= $k ?>"><span class="cl-node"><?= $k + 1 ?></span>
              <div class="cl-card"><span class="cl-eyebrow">Step <?= $k + 1 ?> · up next</span><div class="cl-ttl"><?= e($l['title']) ?></div><span class="cl-m"><?= e($kindLabel($l)) ?></span><a class="cl-btn solid" href="<?= e($url) ?>"><?= $d ? 'Start lesson' : 'Start the first lesson' ?><?= $arrow ?></a></div>
            </li>
          <?php else: ?>
            <li class="cl-step <?= $isD ? 'done' : 'todo' ?>" style="--i:<?= $k ?>"><span class="cl-node"><?= $isD ? $check : $k + 1 ?></span><a class="cl-row" href="<?= e($url) ?>"><span><b><?= e($l['title']) ?></b><small><?= e($kindLabel($l)) ?></small></span><?= $isD ? '<span class="cl-pill good">Done</span>' : $chev ?></a></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

<?php elseif ($view === 'lesson'): ?>
  <?php if (!$lessonOk): ?>
    <section class="cl-rise" style="--i:0"><span class="cl-eyebrow">Lessons</span><h1 class="cl-h1-sm">This lesson is not open yet</h1></section>
    <div class="cl-box cl-rise" style="--i:1"><p>It may open later in your year.</p><a class="cl-btn" href="<?= e($self()) ?>">Back to your path</a></div>
  <?php else:
      $lesson = $lessonRow;
      $flat = array_values($unlockedLessons);
      $idx = array_search((int) $lesson['id'], array_map(fn($l) => (int) $l['id'], $flat), true);
      $next = $idx !== false && isset($flat[$idx + 1]) ? $flat[$idx + 1] : null;
      $prev = $pos > 0 ? $mls[$pos - 1] : null;
      $media = base_url('campus-media.php?lesson=' . $lesson['id']);
      $isDoneL = isset($done[(int) $lesson['id']]);
      $nextSame = $next && (int) $next['month_no'] === $lm;
      $label = $isDoneL ? ($next ? ($nextSame ? 'Next lesson' : 'Go to month ' . (int) $next['month_no']) : 'Back to month ' . $lm)
                        : ($next ? ($nextSame ? 'Mark as done and go to lesson ' . ($pos + 2) : 'Mark as done and go to month ' . (int) $next['month_no']) : 'Mark as done');
  ?>
    <section class="cl-rise" style="--i:0">
      <div class="cl-dots" aria-hidden="true"><?php foreach ($mls as $k => $l): ?><i class="<?= $k === $pos ? 'c' : (isset($done[(int) $l['id']]) ? 'd' : '') ?>"></i><?php endforeach; ?></div>
      <h1 class="cl-h1-sm"><?= e($lesson['title']) ?></h1>
      <p class="cl-sub"><?= e($kindLabel($lesson)) ?> · Month <?= $lm ?><?= $mTitle($lm) !== '' ? ', ' . e($mTitle($lm)) : '' ?></p>
    </section>
    <?php if ($lesson['kind'] === 'VIDEO'): ?>
      <div class="cl-player cl-rise" style="--i:1"><video src="<?= e($media) ?>" controls playsinline preload="metadata" controlsList="nodownload"></video></div>
    <?php else: ?>
      <div class="cl-pdf cl-rise" style="--i:1"><iframe src="<?= e($media) ?>#toolbar=0" title="<?= e($lesson['title']) ?>"></iframe></div>
      <p class="cl-fine cl-rise" style="--i:1"><a href="<?= e($media . '&download=1') ?>">Download the PDF</a></p>
    <?php endif; ?>
    <section class="cl-box cl-rise" style="--i:2">
      <p><?= $isDoneL ? '<b class="cl-good">You finished this lesson.</b>' : 'When you finish, mark it done to move to the next step.' ?></p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="_action" value="done"><input type="hidden" name="lesson" value="<?= (int) $lesson['id'] ?>"><input type="hidden" name="next" value="<?= $next ? (int) $next['id'] : '' ?>">
        <button class="cl-btn solid full" type="submit"><?= e($label) ?><?= $arrow ?></button>
      </form>
      <div class="cl-pair">
        <?php if ($prev): ?><a class="cl-btn" href="<?= e($self(['view' => 'lesson', 'lesson' => $prev['id']])) ?>"><?= $svg('left', 'l') ?>Previous</a><?php else: ?><span class="cl-btn off" aria-disabled="true"><?= $svg('left', 'l') ?>Previous</span><?php endif; ?>
        <a class="cl-btn" href="<?= e($self(['view' => 'lessons', 'month' => $lm])) ?>">All lessons</a>
      </div>
    </section>
  <?php endif; ?>

<?php elseif ($view === 'live'):
      $past = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at DESC LIMIT 12', [$campusId]);
      $later = array_slice($upcoming, 1);
?>
  <section class="cl-rise" style="--i:0"><span class="cl-eyebrow"><?= e($campus['live_schedule'] ? 'Every week, ' . $campus['live_schedule'] : 'Every week with your professor') ?></span><h1>Live <em>classes</em></h1></section>
  <?php if ($nextLive): ?>
    <section class="cl-live cl-rise" style="--i:1">
      <div><span class="cl-eyebrow"><?= $liveNow ? 'On now' : 'Next class · ' . e(date('D j M', $liveAt)) ?></span><h3><?= e($nextLive['title']) ?></h3><p><?= e(date('g:i a', $liveAt)) ?> · <?= (int) $nextLive['duration_minutes'] ?> min<?= $nextLive['join_url'] ? '' : ' · The join link is added before the class' ?></p></div>
      <?php if ($liveNow): ?><div class="cl-onair"><span></span>The class has started</div>
      <?php else: $s = max(0, $liveAt - time()); ?>
        <div class="cl-clock" data-count data-at="<?= $liveAt * 1000 ?>"><?php foreach ([['Days', intdiv($s, 86400)], ['Hrs', intdiv($s, 3600) % 24], ['Min', intdiv($s, 60) % 60], ['Sec', $s % 60]] as [$lab, $n]): ?><div><b><?= sprintf('%02d', $n) ?></b><small><?= $lab ?></small></div><?php endforeach; ?></div>
      <?php endif; ?>
      <?php if ($nextLive['join_url']): ?><a class="cl-btn lime full" href="<?= e($nextLive['join_url']) ?>" target="_blank" rel="noopener">Join the class<?= $arrow ?></a><?php endif; ?>
    </section>
  <?php else: ?>
    <div class="cl-box cl-rise" style="--i:1"><b>No class scheduled yet</b><p>The next live class will be announced here, with a button to join.</p></div>
  <?php endif; ?>
  <?php if ($later): ?>
    <section class="cl-rise" style="--i:2"><h2 class="cl-sec">After that</h2>
      <div class="cl-menu">
        <?php foreach ($later as $l): $at = strtotime($l['starts_at']); ?>
          <div class="cl-row"><span class="cl-ico cl-mono"><?= e(date('j', $at)) ?></span><span><b><?= e($l['title']) ?></b><small><?= e(date('D j M · g:i a', $at)) ?> · <?= (int) $l['duration_minutes'] ?> min</small></span><?php if ($l['join_url']): ?><a class="cl-btn solid sm" href="<?= e($l['join_url']) ?>" target="_blank" rel="noopener">Join</a><?php else: ?><span class="cl-pill mute">Link soon</span><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
  <?php if ($past): ?>
    <section class="cl-rise" style="--i:3"><h2 class="cl-sec">Past classes</h2>
      <div class="cl-menu">
        <?php foreach ($past as $l): $at = strtotime($l['starts_at']); ?>
          <div class="cl-row is-past"><span class="cl-ico cl-mono"><?= e(date('j', $at)) ?></span><span><b><?= e($l['title']) ?></b><small><?= e(date('j M Y', $at)) ?> · recordings are added to your lessons</small></span><span class="cl-pill mute">Done</span></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

<?php elseif ($view === 'community'): $posts = get_campus_posts($campusId, 60); ?>
  <section class="cl-rise" style="--i:0"><span class="cl-eyebrow">Only members of this campus see this</span><h1>The class <em>room</em></h1></section>
  <form method="post" class="cl-compose cl-rise" style="--i:1">
    <?= csrf_field() ?><input type="hidden" name="_action" value="post">
    <textarea id="cl-post" name="body" maxlength="2000" required placeholder="Share your progress, ask a question, help a classmate" aria-label="Message"></textarea>
    <button class="cl-btn solid" type="submit">Post</button>
  </form>
  <section class="cl-menu cl-rise" style="--i:2">
    <?php if (!$posts): ?><p class="cl-empty">No messages yet. Be the first to say hello.</p><?php endif; ?>
    <?php foreach ($posts as $p): [$who, $tag, $staff] = $poster($p); ?>
      <div class="cl-msg"><span class="cl-av" style="background:<?= $staff ? 'var(--cl-blue)' : $avColor($who) ?>"><?= e(mb_strtoupper(mb_substr($who, 0, 1))) ?></span>
        <div><b><?= e($who) ?><?= $tag ? '<em>' . e($tag) . '</em>' : '' ?><time datetime="<?= e($p['created_at']) ?>"><?= e(time_ago($p['created_at'])) ?></time></b>
          <p><?= nl2br(e($p['body'])) ?></p>
          <?php if ($isAdmin): ?><form method="post" class="cl-remove"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_post"><input type="hidden" name="post" value="<?= (int) $p['id'] ?>"><button type="submit">Remove</button></form><?php endif; ?>
        </div></div>
    <?php endforeach; ?>
  </section>

<?php elseif ($view === 'certificate'): $code = $membership['certificate_code'] ?? null; ?>
  <section class="cl-rise" style="--i:0"><span class="cl-eyebrow">At the end of the year</span><h1>Your <em>certificate</em></h1><p class="cl-sub"><?= $code ? 'You finished every lesson of the twelve months. Share it with anyone; it carries a code they can check.' : 'It is issued when you finish every lesson of the twelve months.' ?></p></section>
  <div class="cl-prog cl-rise" style="--i:1"><div class="cl-prog-top"><span>Lessons done</span><span class="cl-mono"><?= count($done) ?> of <?= $total ?></span></div><?= $track($pct) ?></div>
  <div class="cl-kv cl-rise" style="--i:2">
    <div><span>Your month</span><b><?= $isAdmin ? 'All 12 open' : $classMonth . ' of 12' ?></b></div>
    <div><span>Status</span><b><?= $code ? 'Issued ' . e(date('j M Y', strtotime($membership['certificate_issued_at']))) : 'In progress' ?></b></div>
  </div>
  <?php if ($code): ?><a class="cl-btn solid full cl-rise" style="--i:3" href="<?= e(base_url('campus-certificate.php?code=' . $code)) ?>">View and share your certificate<?= $arrow ?></a><?php endif; ?>

<?php elseif ($view === 'membership'): ?>
  <section class="cl-rise" style="--i:0"><span class="cl-eyebrow"><?= e($campus['name']) ?> Campus</span><h1>Your <em>membership</em></h1></section>
  <?php if ($isAdmin && !$membership): ?>
    <div class="cl-box cl-rise" style="--i:1"><p>You are viewing as <?= $isProf ? 'the professor' : 'an admin' ?>, without a membership.</p></div>
  <?php else: ?>
    <div class="cl-kv cl-rise" style="--i:1">
      <div><span>Plan</span><b><?= $membership['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></b></div>
      <div><span>Joined</span><b><?= e(date('j M Y', strtotime($membership['started_at']))) ?></b></div>
      <div><span>Access until</span><b><?= e(date('j M Y, g:i a', strtotime($membership['access_until']))) ?></b></div>
      <div><span>Renewal</span><b>Pay again before the end date. Nothing is charged automatically.</b></div>
      <div><span>Reminders</span><b><?= (int) $membership['reminders_off'] ? 'Off' : 'Email and notification, from 7 days before' ?></b></div>
    </div>
    <a class="cl-btn solid full cl-rise" style="--i:2" href="<?= e($join) ?>">Add more time<?= $arrow ?></a>
    <form method="post" class="cl-remind cl-rise" style="--i:2"><?= csrf_field() ?><input type="hidden" name="_action" value="reminders"><input type="hidden" name="off" value="<?= (int) $membership['reminders_off'] ? '0' : '1' ?>">
      <button type="submit"><?= (int) $membership['reminders_off'] ? 'Turn reminders back on' : 'Stop reminders (cancel)' ?></button></form>
  <?php endif; ?>
  <nav class="cl-menu cl-rise" style="--i:3" aria-label="Leave the campus">
    <a class="cl-row" href="<?= e(base_url('dashboard.php')) ?>"><span class="cl-ico"><?= $svg('dashboard') ?></span><span><b>Your dashboard</b><small>Everything in your Obin Academy account</small></span><?= $chev ?></a>
    <a class="cl-row" href="<?= e(base_url('index.php#campuses')) ?>"><span class="cl-ico"><?= $svg('campuses') ?></span><span><b>All campuses</b><small>See the other campuses</small></span><?= $chev ?></a>
  </nav>
<?php endif; ?>
</main>
</body>
<?php endif; ?>
</html>
