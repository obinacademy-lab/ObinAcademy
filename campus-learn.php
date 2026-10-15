<?php
/**
 * A member's campus: home, lessons by month, one lesson, live classes, community, certificate, membership.
 * Open while the member's access is open; otherwise it shows the "access ended" page with a way back in.
 * Admins see every campus in full, to check content before and after publishing.
 * Built in the public campus page's own look (campus.php): the blue site bar, a split hero (deep blue
 * copy + the campus photo), a dark progress card that stays in view on the right, and white blocks down
 * the left (this month's lessons, the year, live classes, professor, community). A lesson takes the
 * hero's place as the player. Styles: assets/css/campus-learn.css (the access-ended page still uses
 * campuses.css). Script: assets/js/campus-learn.js.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';
require __DIR__ . '/includes/campus_pages.php';

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
        // Done: on to the next lesson, or back to the month when it was the last one open. "just"
        // makes the finished step pop in the list.
        $just = (int) ($lesson['id'] ?? 0);
        redirect(post('next') !== '' ? $self(['view' => 'lesson', 'lesson' => (int) post('next'), 'just' => $just]) : $self(['month' => (int) ($lesson['month_no'] ?? 1), 'just' => $just]) . '#cl-month');
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
if ($view === 'lessons') $view = 'home'; // the month's lessons live on the home page now
if (!in_array($view, ['home', 'lesson', 'live', 'community', 'certificate', 'membership'], true)) $view = 'home';
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
$kindLabel = fn(array $l) => $l['kind'] === 'PDF' ? 'PDF guide' : 'Video';
$lenLabel = fn(array $l) => trim((string) $l['duration_label']);
$monthDone = fn(int $m) => count(array_filter($byMonth[$m] ?? [], fn($l) => isset($done[(int) $l['id']])));
$profName = trim((string) $campus['professor_name']);
$profLabel = $profName !== '' ? $profName : 'Obin Academy';
$join = base_url('campus.php?slug=' . $campus['slug'] . '#join');
$photo = campus_photo($campus);
$just = (int) query_param('just');
// The month shown in "This month": the one asked for, else the student's month (staff: the next lesson's).
$homeMonth = $isAdmin ? (int) ($nextLesson['month_no'] ?? 1) : $currentMonth;
$viewMonth = max(1, min(12, (int) query_param('month', (string) $homeMonth)));
// Who a community post is from: the linked professor posts as themselves; admin posts show as the
// professor until one is linked, then as the Obin Academy team.
$poster = function (array $p) use ($campus, $profLabel): array {
    if (!empty($campus['professor_user_id']) && (int) $p['user_id'] === (int) $campus['professor_user_id']) return [$profLabel, 'Professor', true];
    if ($p['role'] === 'ADMIN') return empty($campus['professor_user_id']) ? [$profLabel, 'Team', true] : ['Obin Academy', 'Team', true];
    return [$p['name'], '', false];
};
$avColors = ['#0f766e', '#be185d', '#b45309', '#6d28d9', '#15803d', '#0369a1'];
$avColor = fn(string $name) => $avColors[abs(crc32($name)) % count($avColors)];

// Live classes: the next one drives the countdown.
$upcoming = $open ? upcoming_campus_live_classes(60, $campusId) : [];
$nextLive = $upcoming[0] ?? null;
$liveAt = $nextLive ? strtotime($nextLive['starts_at']) : 0;
$liveNow = $nextLive && $liveAt <= time();
$liveDay = $nextLive ? (int) round((strtotime(date('Y-m-d', $liveAt)) - strtotime(date('Y-m-d'))) / 86400) : 0;
$liveTag = !$nextLive ? '' : ($liveNow ? 'on now' : ($liveDay === 0 ? 'today' : ($liveDay === 1 ? 'tomorrow' : 'in ' . $liveDay . ' days')));
$hm = fn(int $t) => date(date('i', $t) === '00' ? 'g A' : 'g:i A', $t);
$liveWhen = $campus['live_schedule'] ? 'Every week, ' . $campus['live_schedule'] : 'Every week';

$ic = [
    'cap' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/>',
    'right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'back' => '<path d="M15 18l-6-6 6-6"/>',
    'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'video' => '<rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 10 5-3v10l-5-3"/>',
    'doc' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
    'cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'live' => '<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
];
$svg = fn(string $k, string $cls = '') => '<svg' . ($cls ? ' class="' . $cls . '"' : '') . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . ($k === 'check' ? '3' : '2') . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic[$k] . '</svg>';
$play = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>';
$arrow = $svg('right', 'r');
$pad2 = fn(int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
$flash = function_exists('flash_get') ? (flash_get('success') ?: null) : null;
$countdown = function (int $at): string {
    $s = max(0, $at - time()); $out = '<div class="cl-cd" data-count data-at="' . ($at * 1000) . '">';
    foreach ([['days', intdiv($s, 86400)], ['hrs', intdiv($s, 3600) % 24], ['min', intdiv($s, 60) % 60], ['sec', $s % 60]] as [$lab, $n]) $out .= '<span><b>' . str_pad((string) $n, 2, '0', STR_PAD_LEFT) . '</b><small>' . $lab . '</small></span>';
    return $out . '</div>';
};
// One lesson as a step: lime and ticked when done, blue when it is next, the one being watched highlighted.
$step = function (array $l, int $i, ?int $nextId, ?int $hereId) use ($done, $self, $kindLabel, $lenLabel, $svg, $just): string {
    $id = (int) $l['id']; $isD = isset($done[$id]); $isN = !$isD && $id === $nextId;
    $cls = ($isD ? ' d' : '') . ($isN ? ' c' : '') . ($id === $hereId ? ' here' : '') . ($id === $just ? ' pop' : '');
    $right = $isN && $hereId === null ? '<span class="t go">Continue →</span>' : '<span class="t">' . e($lenLabel($l)) . '</span>';
    return '<li><a class="cl-st' . $cls . '" href="' . e($self(['view' => 'lesson', 'lesson' => $id])) . '"><span class="n">' . ($isD ? $svg('check') : $i + 1) . '</span><span class="tt"><b>' . e($l['title']) . '</b><small>' . e($kindLabel($l)) . '</small></span>' . $right . '</a></li>';
};
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#0b00ff">
  <title><?= e($campus['name']) ?> Campus — Obin Academy</title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;1,6..72,400&display=swap" rel="stylesheet">
<?php if (!$open): ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
  <style>body { margin: 0; font-family: "Geist", ui-sans-serif, system-ui, sans-serif; background: #f4f5f8; -webkit-font-smoothing: antialiased; }</style>
<?php else: ?>
  <script>
    // Moving around inside the campus: skip the opening animations, keep the page-to-page morph.
    try { if (document.referrer && new URL(document.referrer).pathname.indexOf('campus-learn.php') > -1) document.documentElement.classList.add('cl-again'); } catch (e) {}
  </script>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campus-learn.css')) ?>">
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
    // ---- the lesson being watched (lesson view) ----
    $lesson = null; $lm = 0; $mls = []; $pos = 0; $next = null; $prev = null;
    if ($view === 'lesson') {
        $row = get_campus_lesson((int) query_param('lesson'));
        if ($row && (int) $row['campus_id'] === $campusId && campus_can_open_lesson($user, $row)) {
            $lesson = $row; $lm = (int) $lesson['month_no']; $mls = $byMonth[$lm] ?? [];
            $pos = (int) array_search((int) $lesson['id'], array_map(fn($l) => (int) $l['id'], $mls), true);
            $flat = array_values($unlockedLessons);
            $idx = array_search((int) $lesson['id'], array_map(fn($l) => (int) $l['id'], $flat), true);
            $next = $idx !== false && isset($flat[$idx + 1]) ? $flat[$idx + 1] : null;
            $prev = $pos > 0 ? $mls[$pos - 1] : null;
            $viewMonth = $lm;
        } else {
            $view = 'home';
        }
    }
    $monthLs = $byMonth[$viewMonth] ?? [];
    $nextInMonth = null; foreach ($monthLs as $l) { if (!isset($done[(int) $l['id']])) { $nextInMonth = (int) $l['id']; break; } }
    $sideMonth = $isAdmin ? $homeMonth : $classMonth; $sideLs = $byMonth[$sideMonth] ?? []; $sideDone = $monthDone($sideMonth);
?>
<body class="cl-body">
<header class="cl-site"><div class="cl-site-in">
  <a class="cl-logo" href="<?= e(base_url('dashboard.php')) ?>"><i><?= $svg('cap') ?></i>Obin Academy</a>
  <span class="cl-site-r"><a class="cl-site-link" href="<?= e(base_url('index.php#campuses')) ?>">All campuses</a><a class="cl-me" href="<?= e($self(['view' => 'membership'])) ?>" title="<?= e($user['name']) ?> · Membership"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></a></span>
</div></header>

<?php if ($flash || (!$isAdmin && $daysLeft !== null && $daysLeft <= 7 && $view !== 'membership')): ?>
<div class="cl-notes">
  <?php if ($flash): ?><div class="cl-notice good" role="status"><?= $svg('check') ?><span><?= e($flash) ?></span></div><?php endif; ?>
  <?php if (!$isAdmin && $daysLeft !== null && $daysLeft <= 7 && $view !== 'membership'): ?>
    <div class="cl-notice <?= $daysLeft <= 1 ? 'bad' : 'warn' ?>" role="status"><?= $svg('clock') ?>
      <span><b><?= $daysLeft <= 0 ? 'Your access ends today.' : 'Your access ends in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ', on ' . e(date('j M', strtotime($membership['access_until']))) . '.' ?></b> Renew to keep your lessons, live classes and community.</span>
      <a class="cl-btn blue sm" href="<?= e($join) ?>">Renew</a></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<main class="cl-page<?= $view === 'lesson' ? ' is-lesson' : '' ?>">
<?php // ---------------------------------------------------------------- hero ?>
<?php if ($view === 'lesson'): $media = base_url('campus-media.php?lesson=' . $lesson['id']); ?>
  <section class="cl-hero watch<?= $lesson['kind'] === 'PDF' ? ' pdf' : '' ?>">
    <a class="cl-backpill" href="<?= e($self(['month' => $lm]) . '#cl-month') ?>"><?= $svg('back') ?>Back to my campus</a>
    <?php if ($lesson['kind'] === 'VIDEO'): ?>
      <div class="cl-media"><video src="<?= e($media) ?>" controls playsinline preload="metadata" controlsList="nodownload"<?= $photo ? ' poster="' . e($photo) . '"' : '' ?>></video></div>
    <?php else: ?>
      <div class="cl-media cl-pdf"><iframe src="<?= e($media) ?>#toolbar=0" title="<?= e($lesson['title']) ?>"></iframe></div>
    <?php endif; ?>
  </section>
<?php else:
    $small = $view !== 'home';
    if ($view === 'live') { $h1 = ['Live', '<em>classes.</em>']; $lede = e($liveWhen) . ($nextLive ? ' · next ' . e($liveTag) : ''); }
    elseif ($view === 'community') { $h1 = ['The class', '<em>community.</em>']; $lede = 'Only members of this campus see this.'; }
    elseif ($view === 'certificate') { $h1 = ['Your', '<em>certificate.</em>']; $lede = 'Issued when you finish every lesson of the twelve months.'; }
    elseif ($view === 'membership') { $h1 = ['Your', '<em>membership.</em>']; $lede = e($campus['name']) . ' Campus'; }
    elseif ($nextLesson) { $h1 = count($done) ? ['Pick up where', 'you <em>left off.</em>'] : ['Start your', '<em>first lesson.</em>']; $lede = ''; }
    elseif ($total && count($done) >= count($unlockedLessons)) { $h1 = ['You are', '<em>up to date.</em>']; $lede = $monthsDone >= 12 ? 'You have reached the end of the year. Finish any lesson left to earn your certificate.' : 'Month ' . ($classMonth + 1) . ' opens when your professor completes month ' . $classMonth . ' for your group.'; }
    else { $h1 = ['Your lessons are', '<em>on their way.</em>']; $lede = 'Your professor is uploading this month\'s lessons. You will see them here as soon as they are ready.'; }
    $nl = $nextLesson; $nm = $nl ? (int) $nl['month_no'] : 0; $nls = $nl ? ($byMonth[$nm] ?? []) : [];
    $np = $nl ? (int) array_search((int) $nl['id'], array_map(fn($l) => (int) $l['id'], $nls), true) : 0;
?>
  <section class="cl-hero<?= $small ? ' sm' : '' ?><?= $photo ? '' : ' no-pic' ?>">
    <span class="cl-aurora" aria-hidden="true"><i></i><i></i><i></i></span>
    <div class="cl-copy">
      <span class="cl-crumb"><?php if ($small): ?><a href="<?= e($self()) ?>"><?= $svg('back') ?>My campus</a> / <?= e($campus['name']) ?><?php else: ?><a href="<?= e(base_url('dashboard.php')) ?>">My dashboard</a> / <?= e($campus['name']) ?><?php endif; ?></span>
      <span class="cl-chip"><i></i><?= $isAdmin ? e($staffLabel) . ' · every month open' : 'Month ' . $classMonth . ' of 12 · your month' ?></span>
      <h1><span class="cl-line"><span style="--k:0"><?= $h1[0] ?></span></span><span class="cl-line"><span style="--k:1"><?= $h1[1] ?></span></span></h1>
      <?php if (!$small && $nl): ?>
        <p class="cl-lede">Lesson <?= $nm ?>.<?= $np + 1 ?> · <b><?= e($nl['title']) ?></b></p>
        <div class="cl-facts"><span><?= $svg($nl['kind'] === 'PDF' ? 'doc' : 'video') ?><?= e($kindLabel($nl)) ?><?= $lenLabel($nl) !== '' ? ' · ' . e($lenLabel($nl)) : '' ?></span><span><?= $svg('cal') ?>Lesson <?= $np + 1 ?> of <?= count($nls) ?></span><?php if ($nextLive): ?><span><?= $svg('live') ?>Live <?= e(date('l', $liveAt) . ', ' . $hm($liveAt)) ?></span><?php endif; ?></div>
      <?php elseif ($lede !== ''): ?>
        <p class="cl-lede"><?= $lede ?></p>
      <?php endif; ?>
    </div>
    <?php if ($photo): ?>
      <?php if (!$small && $nl): ?><a class="cl-pic" href="<?= e($self(['view' => 'lesson', 'lesson' => $nl['id']])) ?>" aria-label="Continue: <?= e($nl['title']) ?>"><img src="<?= e($photo) ?>" alt=""><span class="cl-play"><i><?= $play ?></i><?= count($done) ? 'Continue lesson' : 'Start lesson' ?></span></a>
      <?php else: ?><div class="cl-pic"><img src="<?= e($photo) ?>" alt=""></div><?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php // ---------------------------------------------------------------- the dark card ?>
  <aside class="cl-side"><div class="cl-card-d">
  <?php if ($view === 'lesson'): $isDoneL = isset($done[(int) $lesson['id']]); $nextSame = $next && (int) $next['month_no'] === $lm; $mDone = $monthDone($lm);
      $label = $isDoneL ? ($next ? ($nextSame ? 'Next lesson' : 'Go to month ' . (int) $next['month_no']) : 'Back to month ' . $lm)
                        : ($next ? ($nextSame ? 'Mark complete & next' : 'Complete · go to month ' . (int) $next['month_no']) : 'Mark complete'); ?>
    <span class="cl-eb">Lesson <?= $pos + 1 ?> of <?= count($mls) ?> · Month <?= $lm ?></span>
    <h3><?= e($lesson['title']) ?></h3>
    <span class="cl-track" style="--p:<?= count($mls) ? (int) round($mDone / count($mls) * 100) : 0 ?>%"><i></i></span>
    <form method="post" class="cl-form">
      <?= csrf_field() ?><input type="hidden" name="_action" value="done"><input type="hidden" name="lesson" value="<?= (int) $lesson['id'] ?>"><input type="hidden" name="next" value="<?= $next ? (int) $next['id'] : '' ?>">
      <button class="cl-btn lime full" type="submit"><?= e($label) ?><?= $arrow ?></button>
    </form>
    <?php if ($prev): ?><a class="cl-btn ghost full" href="<?= e($self(['view' => 'lesson', 'lesson' => $prev['id']])) ?>"><?= $svg('left') ?>Previous lesson</a><?php endif; ?>
    <div class="cl-checks">
      <div><?= $svg('check') ?><?= $mDone ?> of <?= count($mls) ?> done in month <?= $lm ?></div>
      <div><?= $svg('check') ?><?= e($kindLabel($lesson)) ?><?= $lenLabel($lesson) !== '' ? ' · ' . e($lenLabel($lesson)) : '' ?><?php if ($lesson['kind'] === 'PDF'): ?> · <a href="<?= e($media . '&download=1') ?>">Download</a><?php endif; ?></div>
      <?php if ($isDoneL): ?><div><?= $svg('check') ?>You finished this lesson</div><?php endif; ?>
    </div>
  <?php else: $mPct = count($sideLs) ? (int) round($sideDone / count($sideLs) * 100) : 0; ?>
    <span class="cl-eb">Your progress</span>
    <h3><?= e($campus['name']) ?></h3>
    <div class="cl-seg" data-seg data-on="month"><span class="knob"></span><button type="button" data-scope="month" aria-pressed="true">This month</button><button type="button" data-scope="year" aria-pressed="false">Whole year</button></div>
    <div class="cl-big" data-scope-show="month"><b><?= $sideDone ?></b><span>of <?= count($sideLs) ?> lessons done in month <?= $sideMonth ?></span></div>
    <div class="cl-big" data-scope-show="year" hidden><b><?= count($done) ?></b><span>of <?= $total ?> lessons done this year</span></div>
    <span class="cl-track" data-scope-track data-month="<?= $mPct ?>" data-year="<?= $pct ?>" style="--p:<?= $mPct ?>%"><i></i></span>
    <?php if ($nextLesson): ?><a class="cl-btn lime full" href="<?= e($self(['view' => 'lesson', 'lesson' => $nextLesson['id']])) ?>"><?= count($done) ? 'Continue lesson' : 'Start lesson' ?><?= $arrow ?></a><?php endif; ?>
    <?php if ($nextLive): ?><a class="cl-btn ghost full" href="<?= e($self(['view' => 'live'])) ?>">Live class <span class="cl-livepill"><?= e($liveTag) ?></span></a><?php endif; ?>
    <div class="cl-checks">
      <?php if (!$isAdmin): ?>
        <div><?= $svg('check') ?><?= $monthsDone ? ($monthsDone === 1 ? 'Month 1 complete' : 'Months 1 to ' . $monthsDone . ' complete') : 'Month 1 is open' ?></div>
        <div><?= $svg('check') ?>Access until <?= e(date('j M', strtotime($membership['access_until']))) ?> · <?= max(0, (int) $daysLeft) ?> day<?= (int) $daysLeft === 1 ? '' : 's' ?> left</div>
        <div><?= $svg('check') ?><?= !empty($membership['certificate_code']) ? '<a href="' . e($self(['view' => 'certificate'])) . '">Your certificate is ready</a>' : 'Certificate after month 12' ?></div>
      <?php else: ?>
        <div><?= $svg('check') ?><?= e($staffLabel) ?>: every month is open</div>
        <div><?= $svg('check') ?><?= $total ?> lesson<?= $total === 1 ? '' : 's' ?> in the campus so far</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </div></aside>

<?php // ---------------------------------------------------------------- the blocks ?>
  <div class="cl-col">
<?php if ($view === 'home' || $view === 'lesson'): ?>
    <section class="cl-block" id="cl-month" style="--b:0">
      <div class="cl-block-h"><h2>Month <?= $viewMonth ?><?= $mTitle($viewMonth) !== '' ? ' · <em>' . e($mTitle($viewMonth)) . '</em>' : '' ?></h2><span><?= $viewMonth > $currentMonth ? 'Locked' : $monthDone($viewMonth) . ' of ' . count($monthLs) . ' done' ?></span></div>
      <?php if ($viewMonth > $currentMonth): ?>
        <p class="cl-empty"><?= $svg('lock') ?>This month opens when your professor completes month <?= $viewMonth - 1 ?> for your group. You are on month <?= $classMonth ?>.</p>
      <?php elseif (!$monthLs): ?>
        <p class="cl-empty">Lessons for this month are on their way. They appear here as soon as your professor uploads them.</p>
      <?php else: ?>
        <?php if ($view === 'home' && trim((string) $stages[$viewMonth]['summary']) !== ''): ?><p class="cl-sum"><?= e($stages[$viewMonth]['summary']) ?></p><?php endif; ?>
        <ol class="cl-steps"><?php foreach ($monthLs as $i => $l) echo $step($l, $i, $nextInMonth, $view === 'lesson' ? (int) $lesson['id'] : null); ?></ol>
      <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($view === 'home'): ?>
    <section class="cl-block" style="--b:1">
      <div class="cl-block-h"><h2>Your <em>year</em></h2><span>12 months</span></div>
      <div class="cl-year">
        <?php for ($m = 1; $m <= 12; $m++): $st = $isAdmin ? 'o' : ($m <= $monthsDone ? 'd' : ($m === $classMonth ? 'c' : ($m <= $currentMonth ? 'o' : ''))); ?>
          <?php if ($st === ''): ?><span class="cl-tile" style="--i:<?= $m - 1 ?>" title="Opens when your professor completes month <?= $m - 1 ?>"><?= $pad2($m) ?><?= $svg('lock') ?></span>
          <?php else: ?><a class="cl-tile <?= $st ?>" style="--i:<?= $m - 1 ?>" href="<?= e($self(['month' => $m]) . '#cl-month') ?>"<?= $m === $viewMonth ? ' aria-current="true"' : '' ?> title="<?= e($mTitle($m) !== '' ? $mTitle($m) : 'Month ' . $m) ?>"><?= $pad2($m) ?><?= $st === 'd' ? $svg('check') : ($st === 'c' ? '<small>NOW</small>' : '') ?></a><?php endif; ?>
        <?php endfor; ?>
      </div>
      <p class="cl-note"><?= $svg('lock') ?><?= $isAdmin ? 'You are viewing as staff, so every month is open.' : ($monthsDone >= 12 ? 'All twelve months are open.' : 'Month ' . ($classMonth + 1) . ' opens when your professor completes month ' . $classMonth . ' for your group.') ?></p>
    </section>
<?php endif; ?>

<?php if ($view === 'home' || $view === 'live'):
      $later = array_slice($upcoming, 1, $view === 'home' ? 2 : 20);
      $past = $view === 'live' ? db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at DESC LIMIT 12', [$campusId]) : [];
?>
    <section class="cl-block" id="cl-live" style="--b:<?= $view === 'home' ? 2 : 0 ?>">
      <div class="cl-block-h"><h2>Live <em>classes</em></h2><span><?= e($liveWhen) ?></span></div>
      <?php if ($nextLive): ?>
        <div class="cl-live">
          <div class="cl-date"><small><?= e(date('D', $liveAt)) ?></small><b><?= e(date('j', $liveAt)) ?></b></div>
          <div class="cl-live-t"><b><?= e($nextLive['title']) ?></b><small><?= e(date('l j M · g:i A', $liveAt)) ?> · <?= (int) $nextLive['duration_minutes'] ?> min</small>
            <?php if ($liveNow): ?><span class="cl-onair">The class has started</span><?php else: ?><?= $countdown($liveAt) ?><?php endif; ?></div>
          <?php if ($nextLive['join_url']): ?><a class="cl-btn blue" href="<?= e($nextLive['join_url']) ?>" target="_blank" rel="noopener">Join class<?= $arrow ?></a><?php else: ?><span class="cl-pill">Link soon</span><?php endif; ?>
        </div>
      <?php else: ?><p class="cl-empty">The next live class will be announced here, with a button to join.</p><?php endif; ?>
      <?php if ($later): ?>
        <div class="cl-after"><?php foreach ($later as $l): $at = strtotime($l['starts_at']); ?><div><span><?= e($l['title']) ?></span><span class="w"><?= e(date('D j M', $at) . ' · ' . $hm($at)) ?><?php if ($l['join_url'] && $view === 'live'): ?> · <a href="<?= e($l['join_url']) ?>" target="_blank" rel="noopener">Join</a><?php endif; ?></span></div><?php endforeach; ?></div>
      <?php endif; ?>
      <?php if ($view === 'home' && count($upcoming) > 3): ?><a class="cl-more" href="<?= e($self(['view' => 'live'])) ?>">All live classes<?= $arrow ?></a><?php endif; ?>
      <?php if ($past): ?>
        <h3 class="cl-sub">Past classes</h3>
        <div class="cl-after past"><?php foreach ($past as $l): ?><div><span><?= e($l['title']) ?></span><span class="w"><?= e(date('j M Y', strtotime($l['starts_at']))) ?></span></div><?php endforeach; ?></div>
        <p class="cl-note">Recordings are added to your lessons.</p>
      <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($view === 'home'): $links = campus_professor_links($campus); $initials = $profName !== '' ? mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', $profName), 0, 2)))) : 'O'; ?>
    <section class="cl-block" style="--b:3">
      <div class="cl-block-h"><h2>Your <em>professor</em></h2><span>Here all year</span></div>
      <div class="cl-prof">
        <div class="ph"><?php if ($profName !== '' && $campus['professor_photo_url']): ?><img src="<?= e(asset_src($campus['professor_photo_url'])) ?>" alt="<?= e($profName) ?>"><?php else: ?><?= e($initials) ?><?php endif; ?></div>
        <div>
          <b><?= e($profName !== '' ? $profName : 'Your professor is joining soon') ?></b>
          <div class="role"><?= e($profName !== '' ? ($campus['professor_title'] ?: 'Lead professor, ' . $campus['name'] . ' Campus') : 'Obin Academy runs the campus until then.') ?></div>
          <?php if ($profName !== '' && !empty($campus['professor_quote'])): ?><q><?= e($campus['professor_quote']) ?></q><?php endif; ?>
          <div class="cl-prof-acts">
            <a class="cl-btn light" href="<?= e($self(['view' => 'community'])) ?>">Ask a question</a>
            <?php foreach ($links as [$label, $url, $path]): ?><a class="cl-soc" href="<?= e($url) ?>" target="_blank" rel="noopener nofollow" aria-label="<?= e($profName . ' on ' . $label) ?>" title="<?= e($label) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $path ?>"/></svg></a><?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php if ($view === 'home' || $view === 'community'): $posts = get_campus_posts($campusId, $view === 'home' ? 2 : 60); ?>
    <section class="cl-block" id="cl-community" style="--b:<?= $view === 'home' ? 4 : 0 ?>">
      <div class="cl-block-h"><h2>Class <em>community</em></h2><span>Only members see this</span></div>
      <?php if ($view === 'community'): ?>
        <form method="post" class="cl-compose">
          <?= csrf_field() ?><input type="hidden" name="_action" value="post">
          <textarea id="cl-post" name="body" maxlength="2000" required placeholder="Share your progress, ask a question, help a classmate" aria-label="Message"></textarea>
          <button class="cl-btn blue" type="submit">Post</button>
        </form>
      <?php endif; ?>
      <div class="cl-posts">
        <?php if (!$posts): ?><p class="cl-empty">No messages yet. Be the first to say hello.</p><?php endif; ?>
        <?php foreach ($posts as $p): [$who, $tag, $staff] = $poster($p); ?>
          <div class="cl-post"><span class="av" style="background:<?= $staff ? 'var(--cl-brand)' : $avColor($who) ?>"><?= e(mb_strtoupper(mb_substr($who, 0, 1))) ?></span>
            <div><b><?= e($who) ?><?= $tag ? '<em>' . e($tag) . '</em>' : '' ?><time datetime="<?= e($p['created_at']) ?>"><?= e(time_ago($p['created_at'])) ?></time></b>
              <p><?= $view === 'home' ? e(mb_strimwidth($p['body'], 0, 180, '…')) : nl2br(e($p['body'])) ?></p>
              <?php if ($isAdmin && $view === 'community'): ?><form method="post" class="cl-remove"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_post"><input type="hidden" name="post" value="<?= (int) $p['id'] ?>"><button type="submit">Remove</button></form><?php endif; ?>
            </div></div>
        <?php endforeach; ?>
      </div>
      <?php if ($view === 'home'): ?><a class="cl-btn blue" href="<?= e($self(['view' => 'community'])) ?>">Open the community<?= $arrow ?></a><?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($view === 'certificate'): $code = $membership['certificate_code'] ?? null; ?>
    <section class="cl-block" style="--b:0">
      <div class="cl-block-h"><h2>Your <em>certificate</em></h2><span><?= $code ? 'Ready' : 'In progress' ?></span></div>
      <div class="cl-kv">
        <div><span>Lessons completed</span><b><?= count($done) ?> of <?= $total ?></b></div>
        <div><span>Your year</span><b><?= $isAdmin ? 'Every month open' : 'Month ' . $classMonth . ' of 12' ?></b></div>
        <div><span>Status</span><b><?= $code ? 'Issued ' . e(date('j M Y', strtotime($membership['certificate_issued_at']))) : 'Complete every lesson of the twelve months' ?></b></div>
      </div>
      <?php if ($code): ?><a class="cl-btn blue" href="<?= e(base_url('campus-certificate.php?code=' . $code)) ?>">View and share your certificate<?= $arrow ?></a><?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($view === 'membership'): ?>
    <section class="cl-block" style="--b:0">
      <div class="cl-block-h"><h2>Your <em>membership</em></h2><span><?= e($campus['name']) ?> Campus</span></div>
      <?php if ($isAdmin && !$membership): ?>
        <p class="cl-empty">You are viewing as <?= $isProf ? 'the professor' : 'an admin' ?>, without a membership.</p>
      <?php else: ?>
        <div class="cl-kv">
          <div><span>Plan</span><b><?= $membership['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></b></div>
          <div><span>Joined</span><b><?= e(date('j M Y', strtotime($membership['started_at']))) ?></b></div>
          <div><span>Access until</span><b><?= e(date('j M Y, g:i a', strtotime($membership['access_until']))) ?></b></div>
          <div><span>Renewal</span><b>Pay again before the end date. Nothing is charged automatically.</b></div>
          <div><span>Reminders</span><b><?= (int) $membership['reminders_off'] ? 'Off' : 'Email and notification, from 7 days before' ?></b></div>
        </div>
        <div class="cl-row-acts">
          <a class="cl-btn blue" href="<?= e($join) ?>">Add more time<?= $arrow ?></a>
          <form method="post" class="cl-remind"><?= csrf_field() ?><input type="hidden" name="_action" value="reminders"><input type="hidden" name="off" value="<?= (int) $membership['reminders_off'] ? '0' : '1' ?>"><button type="submit"><?= (int) $membership['reminders_off'] ? 'Turn reminders back on' : 'Stop reminders (cancel)' ?></button></form>
        </div>
      <?php endif; ?>
      <div class="cl-row-acts"><a class="cl-btn light" href="<?= e(base_url('dashboard.php')) ?>">Your dashboard</a><a class="cl-btn light" href="<?= e(base_url('index.php#campuses')) ?>">All campuses</a></div>
    </section>
<?php endif; ?>
  </div>
</main>

<?php if ($view === 'home' && $nextLesson): ?>
<div class="cl-mbar"><span>Up next<b><?= e($nextLesson['title']) ?></b></span><a class="cl-btn lime" href="<?= e($self(['view' => 'lesson', 'lesson' => $nextLesson['id']])) ?>">Continue<?= $arrow ?></a></div>
<?php endif; ?>
</body>
<?php endif; ?>
</html>
