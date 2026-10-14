<?php
/**
 * A member's campus: home, lessons by month, one lesson, live classes, community, certificate, membership.
 * Open while the member's access is open; otherwise it shows the "access ended" page with a way back in.
 * Admins see every campus in full, to check content before and after publishing.
 * Home reads in three levels: what to do now (next lesson + year ring), this month and this week
 * (lesson checklist, next live class, community), then the whole year as a 12-month track.
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
        // Ticked from a checklist: stay on that page and show the tick.
        $just = (int) ($lesson['id'] ?? 0);
        if (post('back') === 'home') redirect($self(['just' => $just]));
        if (post('back') === 'lessons') redirect($self(['view' => 'lessons', 'month' => (int) ($lesson['month_no'] ?? 1), 'just' => $just]));
        redirect(post('next') !== '' ? $self(['view' => 'lesson', 'lesson' => (int) post('next')]) : $self(['view' => 'lessons', 'month' => (int) ($lesson['month_no'] ?? 1), 'just' => $just]));
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
$left = count(array_filter($unlockedLessons, fn($l) => !isset($done[(int) $l['id']])));
$daysLeft = $membership ? campus_days_left($membership) : null;
$just = (int) query_param('just');
$mTitle = fn(int $m) => trim((string) ($stages[$m]['title'] ?? ''));
$monthName = fn(int $m) => 'Month ' . $m . ($mTitle($m) !== '' ? ' · ' . $mTitle($m) : '');
$kindLabel = fn(array $l) => ($l['kind'] === 'PDF' ? 'PDF guide' : 'Video') . ($l['duration_label'] ? ' · ' . $l['duration_label'] : '');
$profLabel = $campus['professor_name'] ?: 'Obin Academy';
$first = campus_first_name($user['name']);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
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

// Live classes: the next one drives the countdown on home and the live page.
$upcoming = $open ? upcoming_campus_live_classes(60, $campusId) : [];
$nextLive = $upcoming[0] ?? null;
$liveAt = $nextLive ? strtotime($nextLive['starts_at']) : 0;
$liveIn = $nextLive ? $liveAt - time() : 0;
$liveNow = $nextLive && $liveIn <= 0;
$liveDay = $nextLive ? (int) round((strtotime(date('Y-m-d', $liveAt)) - strtotime(date('Y-m-d'))) / 86400) : 0;
$liveChip = !$nextLive ? '' : ($liveNow ? 'on now' : ($liveDay === 0 ? 'today, ' : ($liveDay === 1 ? 'tomorrow, ' : ($liveDay < 7 ? date('l', $liveAt) . ', ' : date('j M', $liveAt) . ', '))) . date('g:i a', $liveAt));
$countCells = function (int $secs): array {
    $s = max(0, $secs);
    return [['Days', intdiv($s, 86400)], ['Hrs', intdiv($s, 3600) % 24], ['Min', intdiv($s, 60) % 60], ['Sec', $s % 60]];
};

$ic = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V21H3z"/>',
    'lessons' => '<rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 10 5-3v10l-5-3"/>',
    'live' => '<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4"/>',
    'community' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'certificate' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
    'membership' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>',
    'campuses' => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/>',
    'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
];
$svg = fn(string $k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic[$k] . '</svg>';
$check = '<svg viewBox="0 0 24 24" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
$play = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>';

// A month's picture (uploaded by the professor), or its number on a deep blue ground.
$art = function (int $m) use ($stages): string {
    $th = $stages[$m]['thumbnail_url'] ?? null;
    return $th ? '<span class="cl-art" style="background-image:url(\'' . e(asset_src($th)) . '\')"></span>'
               : '<span class="cl-art none"><b>' . $m . '</b></span>';
};

// One lesson in a checklist. $back is where a tick returns to; '' shows the state without a tick button.
$row = function (array $l, int $i, ?int $nowId, string $back) use ($done, $self, $kindLabel, $check, $just): string {
    $id = (int) $l['id']; $isDone = isset($done[$id]); $isNow = !$isDone && $id === $nowId;
    $cls = $isDone ? ' is-done' : ($isNow ? ' is-now' : '');
    $out = '<div class="cl-row' . $cls . ($just === $id ? ' is-just' : '') . '">';
    if ($isDone || $back === '') {
        $out .= '<span class="cl-tick">' . $check . '</span>';
    } else {
        $out .= '<form method="post" class="cl-tickf">' . csrf_field() . '<input type="hidden" name="_action" value="done"><input type="hidden" name="lesson" value="' . $id . '"><input type="hidden" name="back" value="' . e($back) . '">'
              . '<button class="cl-tick" type="submit" aria-label="Mark “' . e($l['title']) . '” as done" title="Mark as done">' . $check . '</button></form>';
    }
    $out .= '<span class="cl-no">' . (int) $l['month_no'] . '.' . ($i + 1) . '</span>'
          . '<a class="cl-rlink" href="' . e($self(['view' => 'lesson', 'lesson' => $id])) . '"><b>' . e($l['title']) . '</b><small>' . e($kindLabel($l)) . '</small></a>'
          . ($isDone ? '<span class="cl-state done">Done</span>' : ($isNow ? '<span class="cl-state now">Up next</span>' : '<span class="cl-state todo">To do</span>'))
          . '</div>';
    return $out;
};
$ring = function (int $pct, string $label) : string {
    $off = round(339.3 * (1 - $pct / 100), 1);
    return '<div class="cl-ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="trk" cx="60" cy="60" r="54" fill="none" stroke-width="7"/><circle class="val" cx="60" cy="60" r="54" fill="none" stroke-width="7" stroke-linecap="round" style="--off:' . $off . '"/></svg><div><b>' . $pct . '%</b><small>' . e($label) . '</small></div></div>';
};
$countdown = function (int $at) use ($countCells): string {
    $out = '<div class="cl-count" data-count data-at="' . ($at * 1000) . '">';
    foreach ($countCells($at - time()) as [$label, $n]) $out .= '<div><b>' . sprintf('%02d', $n) . '</b><small>' . $label . '</small></div>';
    return $out . '</div>';
};
$navItems = ['home' => 'Home', 'lessons' => 'Lessons', 'live' => 'Live classes', 'community' => 'Community', 'certificate' => 'Certificate'];
$navOn = $view === 'lesson' ? 'lessons' : $view;
$flash = function_exists('flash_get') ? (flash_get('success') ?: null) : null;
$memberLabel = $isAdmin ? $staffLabel : (($membership['plan'] ?? '') === 'YEARLY' ? 'Yearly member' : 'Monthly member');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#f3f4f8">
  <title><?= e($campus['name']) ?> Campus — Obin Academy</title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&display=swap" rel="stylesheet">
<?php if (!$open): ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
  <style>body { margin: 0; font-family: "Geist", ui-sans-serif, system-ui, sans-serif; background: #f4f5f8; -webkit-font-smoothing: antialiased; }</style>
<?php else: ?>
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
<?php else: ?>
<body class="cl-body<?= $just ? ' cl-quiet' : '' ?>">
<div class="cl-shell">
  <aside class="cl-rail">
    <a class="cl-brand" href="<?= e(base_url('index.php')) ?>"><i>O</i>Obin Academy</a>
    <div class="cl-camp">
      <small>Your campus</small><b><?= e($campus['name']) ?></b>
      <span class="<?= $isAdmin ? 'staff' : '' ?>"><?= $isAdmin ? e($staffLabel) . ' · every month open' : 'Month ' . $classMonth . ' of 12 · ' . (($membership['plan'] ?? '') === 'YEARLY' ? 'Yearly' : 'Monthly') ?></span>
    </div>
    <nav class="cl-nav" aria-label="Campus">
      <small>Campus</small>
      <?php foreach ($navItems as $k => $label): ?>
        <a href="<?= e($self($k === 'home' ? [] : ['view' => $k])) ?>"<?= $navOn === $k ? ' aria-current="page"' : '' ?>><?= $svg($k) ?><?= e($label) ?><?= $k === 'lessons' && $left && !$isAdmin ? '<em>' . $left . ' left</em>' : '' ?></a>
      <?php endforeach; ?>
    </nav>
    <nav class="cl-nav" aria-label="Account">
      <small>Account</small>
      <a href="<?= e($self(['view' => 'membership'])) ?>"<?= $view === 'membership' ? ' aria-current="page"' : '' ?>><?= $svg('membership') ?>Membership</a>
      <a href="<?= e(base_url('index.php#campuses')) ?>"><?= $svg('campuses') ?>All campuses</a>
      <a href="<?= e(base_url('dashboard.php')) ?>"><?= $svg('dashboard') ?>Dashboard</a>
    </nav>
    <a class="cl-me" href="<?= e($self(['view' => 'membership'])) ?>"><i><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></i><span><b><?= e($user['name']) ?></b><small><?= e($memberLabel) ?></small></span></a>
  </aside>

  <main class="cl-main">
    <div class="cl-wrap">
      <div class="cl-mtop">
        <a class="cl-brand" href="<?= e($self()) ?>"><i>O</i><?= e($campus['name']) ?></a>
        <a class="cl-chip" href="<?= e($self(['view' => 'membership'])) ?>"><?= $isAdmin ? e($staffLabel) : ($daysLeft <= 0 ? '<b>Ends today</b>' : '<b>' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . '</b> left') ?></a>
      </div>

      <?php if ($flash): ?><div class="cl-notice good" role="status"><?= $check ?><span><?= e($flash) ?></span></div><?php endif; ?>
      <?php if (!$isAdmin && $daysLeft !== null && $daysLeft <= 7 && $view !== 'membership'): ?>
        <div class="cl-notice <?= $daysLeft <= 1 ? 'bad' : 'warn' ?>" role="status">
          <?= $svg('clock') ?>
          <span><b><?= $daysLeft <= 0 ? 'Your access ends today.' : 'Your access ends in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ', on ' . e(date('j M', strtotime($membership['access_until']))) . '.' ?></b> Renew to keep your lessons, live classes and community.</span>
          <a class="cl-btn solid sm" href="<?= e($join) ?>">Renew · <?= e(format_money((float) $campus['monthly_price'])) ?></a>
        </div>
      <?php endif; ?>

<?php if ($view === 'home'): ?>
      <header class="cl-head cl-lv" style="--lv:0">
        <div><span class="cl-eyebrow"><?= e($campus['name']) ?> Campus · <?= $isAdmin ? 'Every month' : 'Month ' . $classMonth ?></span><h1><?= $greeting ?>, <em><?= e($first) ?></em>.</h1></div>
        <div class="cl-chips">
          <?php if ($isAdmin): ?><span class="cl-chip"><?= e($staffLabel) ?></span><?php else: ?><span class="cl-chip<?= $daysLeft <= 7 ? ' warn' : '' ?>">Access until <b><?= e(date('j M', strtotime($membership['access_until']))) ?></b></span><?php endif; ?>
          <?php if ($nextLive): ?><a class="cl-chip" href="<?= e($self(['view' => 'live'])) ?>">Live class <b><?= e($liveChip) ?></b></a><?php endif; ?>
        </div>
      </header>

      <div class="cl-l1">
        <?php if ($nextLesson): $nm = (int) $nextLesson['month_no']; $monthLs = $byMonth[$nm] ?? []; $pos = array_search((int) $nextLesson['id'], array_map(fn($l) => (int) $l['id'], $monthLs), true); $mDone = count(array_filter($monthLs, fn($l) => isset($done[(int) $l['id']]))); $lessonUrl = $self(['view' => 'lesson', 'lesson' => $nextLesson['id']]); ?>
          <article class="cl-next cl-lv" style="--lv:1">
            <a class="cl-pic" href="<?= e($lessonUrl) ?>" aria-label="Open <?= e($nextLesson['title']) ?>"><?= $art($nm) ?><span class="cl-tag">Month <?= $nm ?> · Lesson <?= $pos + 1 ?></span><span class="cl-play"><?= $play ?></span></a>
            <div class="cl-next-body">
              <span class="cl-eyebrow"><?= count($done) ? 'Up next' : 'Start here' ?></span>
              <h2><?= e($nextLesson['title']) ?></h2>
              <div class="cl-meta"><span><?= e($kindLabel($nextLesson)) ?></span><span><?= e($monthName($nm)) ?></span></div>
              <div class="cl-segs">
                <div class="cl-segs-top"><span>This month</span><span class="cl-num"><?= $mDone ?> of <?= count($monthLs) ?> lessons</span></div>
                <div class="cl-segs-bar"><?php foreach ($monthLs as $k => $l): $st = isset($done[(int) $l['id']]) ? 'on' : ((int) $l['id'] === (int) $nextLesson['id'] ? 'now' : ''); ?><i class="<?= $st ?>" style="--k:<?= $k ?>"></i><?php endforeach; ?></div>
              </div>
              <div class="cl-acts"><a class="cl-btn solid" href="<?= e($lessonUrl) ?>"><?= count($done) ? 'Continue lesson' : 'Start lesson' ?><?= $svg('arrow') ?></a><a class="cl-btn" href="<?= e($self(['view' => 'lessons', 'month' => $nm])) ?>">See month <?= $nm ?></a></div>
            </div>
          </article>
        <?php else: ?>
          <article class="cl-next cl-lv" style="--lv:1">
            <div class="cl-pic"><?= $art($classMonth) ?></div>
            <div class="cl-next-body">
              <?php if ($total && count($done) >= count($unlockedLessons)): ?>
                <span class="cl-eyebrow">All caught up</span>
                <h2>You have finished everything open so far</h2>
                <p class="cl-lede"><?= $monthsDone >= 12 ? 'You have reached the end of the year. Finish any lesson left to earn your certificate.' : 'Month ' . ($classMonth + 1) . ' opens when your professor completes month ' . $classMonth . ' for your group. Join the live class in the meantime.' ?></p>
                <div class="cl-acts"><?php if ($nextLive): ?><a class="cl-btn solid" href="<?= e($self(['view' => 'live'])) ?>">See the next live class<?= $svg('arrow') ?></a><?php endif; ?><a class="cl-btn" href="<?= e($self(['view' => 'community'])) ?>">Open the community</a></div>
              <?php else: ?>
                <span class="cl-eyebrow">Getting ready</span>
                <h2>Your first lessons are being prepared</h2>
                <p class="cl-lede">Your professor is uploading this month's lessons. You will see them here as soon as they are ready.</p>
                <div class="cl-acts"><a class="cl-btn" href="<?= e($self(['view' => 'community'])) ?>">Say hello in the community</a></div>
              <?php endif; ?>
            </div>
          </article>
        <?php endif; ?>
        <aside class="cl-year cl-lv" style="--lv:1">
          <?= $ring($pct, 'of your year') ?>
          <div class="cl-kv">
            <div><span>Lessons done</span><b><?= count($done) ?> of <?= $total ?></b></div>
            <div><span><?= $isAdmin ? 'Months open' : 'Your month' ?></span><b><?= $isAdmin ? '12 of 12' : $classMonth . ' of 12' ?></b></div>
            <div><span>Access left</span><b><?= $isAdmin ? 'Staff' : max(0, (int) $daysLeft) . ' day' . ((int) $daysLeft === 1 ? '' : 's') ?></b></div>
          </div>
        </aside>
      </div>

      <div class="cl-l2">
        <section class="cl-card cl-lv" style="--lv:2">
          <?php $homeMonth = $isAdmin ? (int) ($nextLesson['month_no'] ?? 1) : $currentMonth; ?>
          <header><h3><?= e($monthName($homeMonth)) ?></h3><a href="<?= e($self(['view' => 'lessons', 'month' => $homeMonth])) ?>">All lessons</a></header>
          <div class="cl-list">
            <?php $ls = $byMonth[$homeMonth] ?? []; if (!$ls): ?><p class="cl-none">This month's lessons are on their way.</p><?php endif; ?>
            <?php foreach ($ls as $i => $l) echo $row($l, $i, $nextLesson ? (int) $nextLesson['id'] : null, 'home'); ?>
          </div>
        </section>
        <section class="cl-card cl-lv" style="--lv:2">
          <header><h3>Next live class</h3><a href="<?= e($self(['view' => 'live'])) ?>">Schedule</a></header>
          <?php if ($nextLive): ?>
            <div class="cl-live">
              <div class="cl-live-top"><div class="cl-cal"><small><?= e(date('D', $liveAt)) ?></small><b><?= e(date('j', $liveAt)) ?></b></div><div><b class="t"><?= e($nextLive['title']) ?></b><small><?= e(date('j M, g:i a', $liveAt)) ?> · <?= (int) $nextLive['duration_minutes'] ?> min</small></div></div>
              <?php if ($liveNow): ?>
                <div class="cl-onair"><span></span>The class has started</div>
              <?php else: ?><?= $countdown($liveAt) ?><?php endif; ?>
              <?php if ($nextLive['join_url']): ?><a class="cl-btn solid sm" href="<?= e($nextLive['join_url']) ?>" target="_blank" rel="noopener">Join the class<?= $svg('arrow') ?></a><?php endif; ?>
            </div>
          <?php else: ?><p class="cl-none">The next live class will be announced here.</p><?php endif; ?>
          <header class="cl-sub"><h3>Community</h3><a href="<?= e($self(['view' => 'community'])) ?>">Open</a></header>
          <div class="cl-msgs">
            <?php $posts = get_campus_posts($campusId, 2); if (!$posts): ?><p class="cl-none">No messages yet. Say hello to your classmates.</p><?php endif; ?>
            <?php foreach ($posts as $p): [$who, $tag, $staff] = $poster($p); ?>
              <div class="cl-msg"><span class="cl-av" style="background:<?= $staff ? 'var(--cl-blue)' : $avColor($who) ?>"><?= e(mb_strtoupper(mb_substr($who, 0, 1))) ?></span><div><b><?= e($who) ?><?= $tag ? '<em>' . e($tag) . '</em>' : '' ?><time datetime="<?= e($p['created_at']) ?>"><?= e(time_ago($p['created_at'])) ?></time></b><p><?= e(mb_strimwidth($p['body'], 0, 140, '…')) ?></p></div></div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>

      <section class="cl-track cl-lv" style="--lv:3" aria-label="Your year">
        <header><h3>Your year</h3><span><?= $isAdmin ? 'You are viewing as staff, so every month is open.' : ($monthsDone >= 12 ? 'All twelve months are open.' : 'Month ' . ($classMonth + 1) . ' opens when your professor completes month ' . $classMonth . ' for your group.') ?></span></header>
        <div class="cl-scroll">
          <div class="cl-line">
            <?php for ($m = 1; $m <= 12; $m++): $openM = $m <= $currentMonth; $st = $m <= $monthsDone ? 'done' : (!$isAdmin && $m === $classMonth ? 'now' : ($openM ? 'open' : 'later')); $cnt = count($byMonth[$m] ?? []); ?>
              <div class="cl-stn <?= $st ?>" style="--m:<?= $m - 1 ?>">
                <span class="cl-dot"></span>
                <?php if ($openM): ?><a class="cl-th" href="<?= e($self(['view' => 'lessons', 'month' => $m])) ?>" aria-label="Month <?= $m ?> lessons"><?= $art($m) ?></a><?php else: ?><span class="cl-th" title="Opens when your professor completes month <?= $m - 1 ?>"><?= $svg('lock') ?></span><?php endif; ?>
                <small>Month <?= $m ?></small>
                <b><?= $openM ? e($mTitle($m) !== '' ? $mTitle($m) : $cnt . ' lesson' . ($cnt === 1 ? '' : 's')) : 'Locked' ?></b>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </section>

<?php elseif ($view === 'lessons'): $month = max(1, min(12, (int) query_param('month', (string) $currentMonth))); $ls = $byMonth[$month] ?? []; $mDone = count(array_filter($ls, fn($l) => isset($done[(int) $l['id']]))); $nowId = null; foreach ($ls as $l) { if (!isset($done[(int) $l['id']])) { $nowId = (int) $l['id']; break; } } ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Lessons</span><h1>Month <?= $month ?><?= $mTitle($month) !== '' ? ' · <em>' . e($mTitle($month)) . '</em>' : '' ?></h1></div><?php if ($month <= $currentMonth && $ls): ?><span class="cl-chip"><b><?= $mDone ?></b>&nbsp;of <?= count($ls) ?> done</span><?php endif; ?></header>
      <nav class="cl-months cl-lv" style="--lv:1" aria-label="Months">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <?php if ($m <= $currentMonth): ?><a href="<?= e($self(['view' => 'lessons', 'month' => $m])) ?>"<?= $m === $month ? ' aria-current="page"' : '' ?>><small><?= $m <= $monthsDone ? 'Done' : ($m === $classMonth && !$isAdmin ? 'Now' : 'Month') ?></small><b><?= sprintf('%02d', $m) ?></b></a><?php else: ?><span title="Opens when your professor completes month <?= $m - 1 ?>"><small>Locked</small><b><?= sprintf('%02d', $m) ?></b></span><?php endif; ?>
        <?php endfor; ?>
      </nav>
      <?php if ($month > $currentMonth): ?>
        <section class="cl-card cl-lv" style="--lv:2"><p class="cl-none">This month opens when your professor completes month <?= $month - 1 ?> for your group. You are on month <?= $classMonth ?>.</p></section>
      <?php else: ?>
        <div class="cl-mhead cl-lv" style="--lv:1">
          <div class="cl-pic"><?= $art($month) ?></div>
          <div><span class="cl-eyebrow"><?= $month <= $monthsDone ? 'Completed month' : ($month === $classMonth && !$isAdmin ? 'Your month now' : 'Month ' . $month) ?></span><h2><?= e($mTitle($month) !== '' ? $mTitle($month) : 'Month ' . $month) ?></h2><?php if (trim((string) $stages[$month]['summary']) !== ''): ?><p><?= e($stages[$month]['summary']) ?></p><?php endif; ?></div>
        </div>
        <section class="cl-card cl-lv" style="--lv:2">
          <header><h3><?= count($ls) ?> lesson<?= count($ls) === 1 ? '' : 's' ?></h3><span>Tick each one when you finish it</span></header>
          <div class="cl-list">
            <?php if (!$ls): ?><p class="cl-none">Lessons for this month are on their way.</p><?php endif; ?>
            <?php foreach ($ls as $i => $l) echo $row($l, $i, $nowId, 'lessons'); ?>
          </div>
        </section>
      <?php endif; ?>

<?php elseif ($view === 'lesson'):
        $lesson = get_campus_lesson((int) query_param('lesson'));
        if (!$lesson || (int) $lesson['campus_id'] !== $campusId || !campus_can_open_lesson($user, $lesson)): ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Lessons</span><h1>Lesson not available</h1></div></header>
      <section class="cl-card cl-lv" style="--lv:1"><p class="cl-none">It may open later in your year. <a href="<?= e($self(['view' => 'lessons'])) ?>">Back to lessons</a></p></section>
<?php else:
            $flat = array_values($unlockedLessons);
            $idx = array_search((int) $lesson['id'], array_map(fn($l) => (int) $l['id'], $flat), true);
            $next = $idx !== false && isset($flat[$idx + 1]) ? $flat[$idx + 1] : null;
            $media = base_url('campus-media.php?lesson=' . $lesson['id']);
            $lm = (int) $lesson['month_no']; $ls = $byMonth[$lm] ?? [];
            $pos = array_search((int) $lesson['id'], array_map(fn($l) => (int) $l['id'], $ls), true);
            $mDone = count(array_filter($ls, fn($l) => isset($done[(int) $l['id']])));
            $isDoneL = isset($done[(int) $lesson['id']]);
    ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Month <?= $lm ?> · Lesson <?= $pos + 1 ?> of <?= count($ls) ?></span><h1><?= e($lesson['title']) ?></h1></div><a class="cl-btn sm" href="<?= e($self(['view' => 'lessons', 'month' => $lm])) ?>">All lessons this month</a></header>
      <div class="cl-lesson">
        <div class="cl-stage cl-lv" style="--lv:1">
          <?php if ($lesson['kind'] === 'VIDEO'): ?>
            <div class="cl-player"><video src="<?= e($media) ?>" controls playsinline preload="metadata" controlsList="nodownload"></video></div>
          <?php else: ?>
            <div class="cl-pdf"><iframe src="<?= e($media) ?>#toolbar=0" title="<?= e($lesson['title']) ?>"></iframe></div>
          <?php endif; ?>
          <div class="cl-lfoot">
            <span><?= e($kindLabel($lesson)) ?><?php if ($lesson['kind'] === 'PDF'): ?> · <a href="<?= e($media . '&download=1') ?>">Download the PDF</a><?php endif; ?><?= $isDoneL ? ' · <b class="cl-good">Done</b>' : '' ?></span>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="_action" value="done"><input type="hidden" name="lesson" value="<?= (int) $lesson['id'] ?>"><input type="hidden" name="next" value="<?= $next ? (int) $next['id'] : '' ?>">
              <button class="cl-btn solid" type="submit"><?= $isDoneL ? ($next ? 'Next lesson' : 'Back to lessons') : ($next ? 'Mark as done and continue' : 'Mark as done') ?><?= $svg('arrow') ?></button>
            </form>
          </div>
        </div>
        <section class="cl-card cl-lv" style="--lv:2">
          <header><h3>In month <?= $lm ?></h3><span class="cl-num"><?= $mDone ?> of <?= count($ls) ?></span></header>
          <div class="cl-list cl-side">
            <?php foreach ($ls as $i => $l) echo str_replace('class="cl-row', 'class="cl-row' . ((int) $l['id'] === (int) $lesson['id'] ? ' is-here' : ''), $row($l, $i, null, '')); ?>
          </div>
        </section>
      </div>
<?php endif; ?>

<?php elseif ($view === 'live'):
        $past = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at DESC LIMIT 12', [$campusId]);
        $later = array_slice($upcoming, 1);
    ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Live classes</span><h1><?= $campus['live_schedule'] ? 'Every week, <em>' . e($campus['live_schedule']) . '</em>' : 'Every week with <em>your professor</em>' ?></h1></div></header>
      <?php if ($nextLive): ?>
        <div class="cl-biglive cl-lv" style="--lv:1">
          <div><span class="cl-eyebrow"><?= $liveNow ? 'On now' : 'Next class · ' . e(date('D j M', $liveAt)) ?></span><h2><?= e($nextLive['title']) ?></h2><p><?= e(date('g:i a', $liveAt)) ?> · <?= (int) $nextLive['duration_minutes'] ?> min<?= $nextLive['join_url'] ? '' : ' · The join link is added before the class.' ?></p></div>
          <div class="cl-biglive-side">
            <?php if ($liveNow): ?><div class="cl-onair dark"><span></span>The class has started</div><?php else: ?><?= $countdown($liveAt) ?><?php endif; ?>
            <?php if ($nextLive['join_url']): ?><a class="cl-btn lime" href="<?= e($nextLive['join_url']) ?>" target="_blank" rel="noopener">Join the class<?= $svg('arrow') ?></a><?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <section class="cl-card cl-lv" style="--lv:1"><p class="cl-none">The next live class will be announced here.</p></section>
      <?php endif; ?>
      <?php if ($later): ?>
        <section class="cl-card cl-lv" style="--lv:2"><header><h3>Coming up</h3><span>Join from here when the class starts</span></header>
          <div class="cl-list">
            <?php foreach ($later as $l): $at = strtotime($l['starts_at']); ?>
              <div class="cl-row cl-lrow"><div class="cl-cal"><small><?= e(date('D', $at)) ?></small><b><?= e(date('j', $at)) ?></b></div><div class="cl-rlink"><b><?= e($l['title']) ?></b><small><?= e(date('j M Y, g:i a', $at)) ?> · <?= (int) $l['duration_minutes'] ?> min</small></div><?php if ($l['join_url']): ?><a class="cl-btn solid sm" href="<?= e($l['join_url']) ?>" target="_blank" rel="noopener">Join</a><?php else: ?><span class="cl-state todo">Link soon</span><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
      <?php if ($past): ?>
        <section class="cl-card cl-lv" style="--lv:3"><header><h3>Past classes</h3><span>Recordings are added to your lessons</span></header>
          <div class="cl-list">
            <?php foreach ($past as $l): $at = strtotime($l['starts_at']); ?>
              <div class="cl-row cl-lrow is-past"><div class="cl-cal"><small><?= e(date('M', $at)) ?></small><b><?= e(date('j', $at)) ?></b></div><div class="cl-rlink"><b><?= e($l['title']) ?></b><small><?= e(date('j M Y', $at)) ?></small></div><span class="cl-state todo">Done</span></div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

<?php elseif ($view === 'community'): $posts = get_campus_posts($campusId, 60); ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Community</span><h1>The <em><?= e($campus['name']) ?></em> room</h1></div><span class="cl-chip">Only members of this campus see this</span></header>
      <section class="cl-card cl-lv" style="--lv:1">
        <form method="post" class="cl-compose">
          <?= csrf_field() ?><input type="hidden" name="_action" value="post">
          <textarea id="cl-post" name="body" maxlength="2000" required placeholder="Share your progress, ask a question, help a classmate" aria-label="Message"></textarea>
          <button class="cl-btn solid" type="submit">Post</button>
        </form>
        <div class="cl-msgs">
          <?php if (!$posts): ?><p class="cl-none">No messages yet. Be the first to say hello.</p><?php endif; ?>
          <?php foreach ($posts as $k => $p): [$who, $tag, $staff] = $poster($p); ?>
            <div class="cl-msg" style="--j:<?= min($k, 12) ?>"><span class="cl-av" style="background:<?= $staff ? 'var(--cl-blue)' : $avColor($who) ?>"><?= e(mb_strtoupper(mb_substr($who, 0, 1))) ?></span>
              <div><b><?= e($who) ?><?= $tag ? '<em>' . e($tag) . '</em>' : '' ?><time datetime="<?= e($p['created_at']) ?>"><?= e(time_ago($p['created_at'])) ?></time></b>
                <p><?= nl2br(e($p['body'])) ?></p>
                <?php if ($isAdmin): ?><form method="post" class="cl-remove"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_post"><input type="hidden" name="post" value="<?= (int) $p['id'] ?>"><button type="submit">Remove</button></form><?php endif; ?>
              </div></div>
          <?php endforeach; ?>
        </div>
      </section>

<?php elseif ($view === 'certificate'): $code = $membership['certificate_code'] ?? null; ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Certificate</span><h1><?= $code ? 'Your certificate is <em>ready</em>' : 'Earn your <em>certificate</em>' ?></h1></div></header>
      <div class="cl-l1">
        <section class="cl-card cl-pad cl-lv" style="--lv:1">
          <p class="cl-lede"><?= $code ? 'You finished every lesson of the twelve months. Share your certificate with anyone; it carries a code they can check.' : 'Your certificate is issued when you finish every lesson of the twelve months. Keep going one month at a time.' ?></p>
          <div class="cl-kv">
            <div><span>Lessons completed</span><b><?= count($done) ?> of <?= $total ?></b></div>
            <div><span>Your year</span><b>Month <?= $currentMonth ?> of 12</b></div>
            <div><span>Status</span><b><?= $code ? 'Issued ' . e(date('j M Y', strtotime($membership['certificate_issued_at']))) : 'In progress' ?></b></div>
          </div>
          <?php if ($code): ?><div class="cl-acts"><a class="cl-btn solid" href="<?= e(base_url('campus-certificate.php?code=' . $code)) ?>">View and share your certificate<?= $svg('arrow') ?></a></div><?php endif; ?>
        </section>
        <aside class="cl-year cl-lv" style="--lv:1"><?= $ring($pct, 'of your year') ?></aside>
      </div>

<?php elseif ($view === 'membership'): ?>
      <header class="cl-head cl-lv" style="--lv:0"><div><span class="cl-eyebrow">Membership</span><h1><em><?= e($campus['name']) ?></em> Campus</h1></div><?php if (!$isAdmin || $membership): ?><a class="cl-btn solid sm" href="<?= e($join) ?>">Add more time</a><?php endif; ?></header>
      <?php if ($isAdmin && !$membership): ?>
        <section class="cl-card cl-lv" style="--lv:1"><p class="cl-none">You are viewing as <?= $isProf ? 'the professor' : 'an admin' ?>, without a membership.</p></section>
      <?php else: ?>
        <section class="cl-card cl-pad cl-lv" style="--lv:1">
          <div class="cl-kv">
            <div><span>Plan</span><b><?= $membership['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></b></div>
            <div><span>Joined</span><b><?= e(date('j M Y', strtotime($membership['started_at']))) ?></b></div>
            <div><span>Access until</span><b><?= e(date('j M Y, g:i a', strtotime($membership['access_until']))) ?></b></div>
            <div><span>Renewal</span><b class="txt">Pay again before the end date. Nothing is charged automatically.</b></div>
            <div><span>Reminders</span><b class="txt"><?= (int) $membership['reminders_off'] ? 'Off' : 'Email and notification, from 7 days before' ?></b></div>
          </div>
          <form method="post" class="cl-remind"><?= csrf_field() ?><input type="hidden" name="_action" value="reminders"><input type="hidden" name="off" value="<?= (int) $membership['reminders_off'] ? '0' : '1' ?>">
            <button type="submit"><?= (int) $membership['reminders_off'] ? 'Turn reminders back on' : 'Stop reminders (cancel)' ?></button></form>
        </section>
      <?php endif; ?>
      <nav class="cl-links cl-lv" style="--lv:2" aria-label="More">
        <a href="<?= e(base_url('index.php#campuses')) ?>"><?= $svg('campuses') ?>All campuses</a>
        <a href="<?= e(base_url('dashboard.php')) ?>"><?= $svg('dashboard') ?>Your dashboard</a>
      </nav>
<?php endif; ?>
    </div>
  </main>
</div>

<nav class="cl-tabs" aria-label="Campus">
  <?php foreach (['home' => 'Home', 'lessons' => 'Lessons', 'live' => 'Live', 'community' => 'Chat', 'certificate' => 'Certificate'] as $k => $label): ?>
    <a href="<?= e($self($k === 'home' ? [] : ['view' => $k])) ?>"<?= $navOn === $k ? ' aria-current="page"' : '' ?>><?= $svg($k) ?><span><?= $label ?></span></a>
  <?php endforeach; ?>
</nav>
</body>
<?php endif; ?>
</html>
