<?php
/**
 * A member's campus: home, lessons by month, one lesson, live classes, community, certificate, membership.
 * Open while the member's access is open; otherwise it shows the "access ended" page with a way back in.
 * Admins see every campus in full, to check content before and after publishing.
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
$stageTitle = fn(int $m) => $stages[$m]['title'] !== '' ? $stages[$m]['title'] : 'Month ' . $m;
$kindLabel = fn(array $l) => ($l['kind'] === 'PDF' ? 'PDF' : 'Video') . ($l['duration_label'] ? ' · ' . $l['duration_label'] : '');
$profLabel = $campus['professor_name'] ?: 'Obin Academy';
// Who a community post is from: the linked professor posts as themselves; admin posts show as the
// professor until one is linked, then as the Obin Academy team.
$poster = function (array $p) use ($campus, $profLabel): array {
    if (!empty($campus['professor_user_id']) && (int) $p['user_id'] === (int) $campus['professor_user_id']) return [$profLabel, 'Professor', true];
    if ($p['role'] === 'ADMIN') return empty($campus['professor_user_id']) ? [$profLabel, 'Team', true] : ['Obin Academy', 'Team', true];
    return [$p['name'], '', false];
};

$ic = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V21H3z"/>',
    'lessons' => '<rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 10 5-3v10l-5-3"/>',
    'live' => '<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4"/>',
    'community' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'certificate' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
    'membership' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>',
    'campuses' => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
];
$svg = fn(string $k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic[$k] . '</svg>';
$flash = function_exists('flash_get') ? (flash_get('success') ?: null) : null;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex">
  <title><?= e($campus['name']) ?> Campus — Obin Academy</title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
  <style>body { margin: 0; font-family: "Geist", ui-sans-serif, system-ui, sans-serif; background: #f4f5f8; -webkit-font-smoothing: antialiased; }</style>
</head>
<body>
<div class="cx">
<?php if (!$open): ?>
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
      <div class="eb"><a class="cx-btn solid full" href="<?= e(base_url('campus.php?slug=' . $campus['slug'] . '#join')) ?>">Choose a plan and pay</a><p class="note">Pay with MTN or Airtel Money.</p></div>
    </div>
    <p class="cx-fine" style="margin-top:22px;"><a href="<?= e(base_url('dashboard.php')) ?>" style="text-decoration:underline;">Back to your dashboard</a></p>
  </div>
<?php else: ?>
<div class="cx-shell">
  <aside class="cx-side">
    <a class="cx-mark" href="<?= e(base_url('index.php')) ?>"><span class="seal">O</span><b>Obin Academy</b></a>
    <div>
      <small class="g"><?= e($campus['name']) ?></small>
      <nav aria-label="Campus">
        <?php foreach (['home' => 'Campus home', 'lessons' => 'Lessons', 'live' => 'Live classes', 'community' => 'Community', 'certificate' => 'Certificate'] as $k => $label): ?>
          <a href="<?= e($self($k === 'home' ? [] : ['view' => $k])) ?>"<?= ($view === $k || ($k === 'lessons' && $view === 'lesson')) ? ' aria-current="page"' : '' ?>><?= $svg($k) ?><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div>
      <small class="g">Account</small>
      <nav aria-label="Account">
        <a href="<?= e($self(['view' => 'membership'])) ?>"<?= $view === 'membership' ? ' aria-current="page"' : '' ?>><?= $svg('membership') ?>Membership</a>
        <a href="<?= e(base_url('index.php#campuses')) ?>"><?= $svg('campuses') ?>All campuses</a>
        <a href="<?= e(base_url('dashboard.php')) ?>"><?= $svg('home') ?>Dashboard</a>
      </nav>
    </div>
    <div class="who"><i><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></i><div><b><?= e($user['name']) ?></b><small><?= $isAdmin ? e($staffLabel) : ($membership['plan'] === 'YEARLY' ? 'Yearly member' : 'Monthly member') ?></small></div></div>
  </aside>

  <main class="cx-main">
    <?php if ($flash): ?><div class="cx-notice" style="background:var(--cx-good-soft); border-color:#bfe8d2;"><span></span><span><?= e($flash) ?></span><span></span></div><?php endif; ?>

    <?php if (!$isAdmin && $daysLeft !== null && $daysLeft <= 7 && $view !== 'membership'): ?>
      <div class="cx-notice<?= $daysLeft <= 1 ? ' bad' : '' ?>" role="status">
        <?= $svg('clock') ?>
        <span><b><?= $daysLeft <= 0 ? 'Your access ends today.' : 'Your access ends in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ', on ' . e(date('j M', strtotime($membership['access_until']))) . '.' ?></b> Renew to keep your lessons, live classes and community.</span>
        <a class="cx-btn solid sm" href="<?= e(base_url('campus.php?slug=' . $campus['slug'] . '#join')) ?>">Renew · <?= e(format_money((float) $campus['monthly_price'])) ?></a>
      </div>
    <?php endif; ?>

    <?php if ($view === 'home'): ?>
      <div class="cx-top"><div><span class="cx-label"><?= e($campus['name']) ?> Campus</span><h1>Welcome, <?= e(campus_first_name($user['name'])) ?></h1></div>
        <?php if (!$isAdmin): ?><span class="cx-pill <?= $daysLeft !== null && $daysLeft <= 7 ? 'warn' : 'good' ?>"><span class="dot"></span>Access until <?= e(date('j M', strtotime($membership['access_until']))) ?></span><?php else: ?><span class="cx-pill mute"><?= e($staffLabel) ?></span><?php endif; ?>
      </div>
      <div class="cx-mhero">
        <div class="cx-cont">
          <?php if ($nextLesson): ?>
            <span class="cx-label">Continue where you stopped</span>
            <h2><?= e($nextLesson['title']) ?></h2>
            <p>Month <?= (int) $nextLesson['month_no'] ?> · <?= e($stageTitle((int) $nextLesson['month_no'])) ?> · <?= e($kindLabel($nextLesson)) ?></p>
            <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
            <div class="acts"><a class="cx-btn light sm" href="<?= e($self(['view' => 'lesson', 'lesson' => $nextLesson['id']])) ?>">Open lesson</a><a class="cx-btn ghost sm" href="<?= e($self(['view' => 'lessons', 'month' => $nextLesson['month_no']])) ?>">See this month</a></div>
          <?php elseif ($total && count($done) >= count($unlockedLessons)): ?>
            <span class="cx-label">All caught up</span>
            <h2>You have finished everything open so far</h2>
            <p><?= $monthsDone >= 12 ? 'You have reached the end of the year. Finish any lesson left to earn your certificate.' : 'Month ' . ($classMonth + 1) . ' opens when your professor completes month ' . $classMonth . '. Join this week\'s live class in the meantime.' ?></p>
          <?php else: ?>
            <span class="cx-label">Getting ready</span>
            <h2>Your first lessons are being prepared</h2>
            <p>Your professor is uploading this month's lessons. You will see them here as soon as they are ready.</p>
          <?php endif; ?>
        </div>
        <div class="cx-ring">
          <svg viewBox="0 0 96 96" aria-hidden="true"><circle cx="48" cy="48" r="40" fill="none" stroke="#e4e6ee" stroke-width="8"/><circle cx="48" cy="48" r="40" fill="none" stroke="#0a0f2c" stroke-width="8" stroke-linecap="round" stroke-dasharray="251.3" stroke-dashoffset="<?= round(251.3 * (1 - $pct / 100), 1) ?>" transform="rotate(-90 48 48)"/><text x="48" y="54" text-anchor="middle" font-family="Newsreader, Georgia, serif" font-size="22" fill="#0e1124"><?= $pct ?>%</text></svg>
          <div><b>Month <?= $classMonth ?> of 12</b><p><?= $monthsDone ? $monthsDone . ' month' . ($monthsDone === 1 ? '' : 's') . ' completed. ' : '' ?>Your certificate comes after the full twelve-month path.</p></div>
        </div>
      </div>
      <section class="cx-year" aria-label="Your year">
        <header><h2>Your year</h2><span><?= $monthsDone >= 12 ? 'All twelve months are open' : ($isAdmin ? 'You are viewing as staff, so every month is open.' : 'You are on month ' . $classMonth . '. Your professor opens each month when your group completes the one before.') ?></span></header>
        <div class="cx-year-grid">
          <?php for ($m = 1; $m <= 12; $m++): $openM = $m <= $currentMonth; $st = $m <= $monthsDone ? 'done' : ($m === $classMonth ? 'now' : 'later'); $th = $stages[$m]['thumbnail_url'] ?? null; $cnt = count($byMonth[$m] ?? []); ?>
            <?php if ($openM): ?><a href="<?= e($self(['view' => 'lessons', 'month' => $m])) ?>" class="cx-ym st-<?= $st ?>"><?php else: ?><div class="cx-ym st-later locked" title="Opens when your professor completes month <?= $m - 1 ?>"><?php endif; ?>
              <span class="pic<?= $th ? '' : ' none' ?>"<?= $th ? ' style="background-image:url(\'' . e(asset_src($th)) . '\')"' : '' ?>><?php if (!$th): ?><b><?= $m ?></b><?php endif; ?>
                <?php if (!$openM): ?><i class="lock" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></i><?php elseif ($st === 'done'): ?><i class="ok" aria-hidden="true">✓</i><?php elseif ($st === 'now'): ?><i class="now">Now</i><?php endif; ?>
              </span>
              <span class="txt"><small>Month <?= $m ?></small><b><?= e(trim((string) $stages[$m]['title']) !== '' ? $stages[$m]['title'] : 'Stage ' . $m) ?></b><em><?= $openM ? $cnt . ' lesson' . ($cnt === 1 ? '' : 's') : 'Locked' ?></em></span>
            <?php if ($openM): ?></a><?php else: ?></div><?php endif; ?>
          <?php endfor; ?>
        </div>
      </section>
      <div class="cx-row2">
        <section class="cx-box">
          <header><h2>Month <?= $currentMonth ?> · <?= e($stageTitle($currentMonth)) ?></h2><a href="<?= e($self(['view' => 'lessons', 'month' => $currentMonth])) ?>">All lessons</a></header>
          <div class="cx-les">
            <?php $ls = $byMonth[$currentMonth] ?? []; if (!$ls): ?><div class="cx-none" style="display:block;">This month's lessons are on their way.</div><?php endif; ?>
            <?php foreach ($ls as $i => $l): ?>
              <a href="<?= e($self(['view' => 'lesson', 'lesson' => $l['id']])) ?>"><span class="cx-mono"><?= $currentMonth ?>.<?= $i + 1 ?></span><span><b><?= e($l['title']) ?></b><small><?= e($kindLabel($l)) ?></small></span><?= isset($done[(int) $l['id']]) ? '<span class="cx-pill good">Done</span>' : '<span class="cx-pill mute">To do</span>' ?></a>
            <?php endforeach; ?>
          </div>
        </section>
        <section class="cx-box">
          <header><h2>Next live class</h2><a href="<?= e($self(['view' => 'live'])) ?>">Schedule</a></header>
          <div class="cx-mlist">
            <?php $nextLive = upcoming_campus_live_classes(30, $campusId)[0] ?? null; ?>
            <?php if ($nextLive): ?>
              <div><div class="cx-when"><small><?= e(date('D', strtotime($nextLive['starts_at']))) ?></small><b><?= e(date('j', strtotime($nextLive['starts_at']))) ?></b></div><div><b class="t"><?= e($nextLive['title']) ?></b><small><?= e(date('j M, g:i a', strtotime($nextLive['starts_at']))) ?></small></div><?php if ($nextLive['join_url']): ?><a class="cx-btn solid sm" href="<?= e($nextLive['join_url']) ?>" target="_blank" rel="noopener">Join</a><?php endif; ?></div>
            <?php else: ?><div class="cx-none" style="display:block;">The next live class will be announced here.</div><?php endif; ?>
          </div>
          <header style="border-top:1px solid var(--cx-line);"><h2>Community</h2><a href="<?= e($self(['view' => 'community'])) ?>">Open</a></header>
          <div>
            <?php $posts = get_campus_posts($campusId, 3); if (!$posts): ?><div class="cx-none">No messages yet. Say hello to your classmates.</div><?php endif; ?>
            <?php foreach ($posts as $p): [$who, $tag, $staff] = $poster($p); ?>
              <div class="cx-msg"><span class="a<?= $staff ? ' prof' : '' ?>"><?= e(mb_strtoupper(mb_substr($who, 0, 1))) ?></span><div><b><?= e($who) ?><?= $tag ? '<em>' . e($tag) . '</em>' : '' ?></b><p><?= e(mb_strimwidth($p['body'], 0, 140, '…')) ?></p></div></div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>

    <?php elseif ($view === 'lessons'): $month = max(1, min(12, (int) query_param('month', (string) $currentMonth))); ?>
      <div class="cx-top"><div><span class="cx-label">Lessons</span><h1>Month <?= $month ?> · <?= e($stageTitle($month)) ?></h1></div></div>
      <nav class="cx-month-nav" aria-label="Months">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <?php if ($m <= $currentMonth): ?><a href="<?= e($self(['view' => 'lessons', 'month' => $m])) ?>"<?= $m === $month ? ' aria-current="page"' : '' ?>><?= $m ?></a><?php else: ?><span title="Opens when your professor completes month <?= $m - 1 ?>"><?= $m ?></span><?php endif; ?>
        <?php endfor; ?>
      </nav>
      <section class="cx-box" style="margin-top:16px;">
        <header><h2><?= e($stages[$month]['summary'] !== '' ? $stages[$month]['summary'] : 'This month') ?></h2><span><?= count(array_filter($byMonth[$month] ?? [], fn($l) => isset($done[(int) $l['id']]))) ?> of <?= count($byMonth[$month] ?? []) ?> done</span></header>
        <div class="cx-les">
          <?php if ($month > $currentMonth): ?><div class="cx-none" style="display:block;">This stage opens when your professor completes month <?= $month - 1 ?> for your group. You are on month <?= $classMonth ?>.</div>
          <?php elseif (empty($byMonth[$month])): ?><div class="cx-none" style="display:block;">Lessons for this month are on their way.</div><?php endif; ?>
          <?php if ($month <= $currentMonth): foreach ($byMonth[$month] ?? [] as $i => $l): ?>
            <a href="<?= e($self(['view' => 'lesson', 'lesson' => $l['id']])) ?>"><span class="cx-mono"><?= $month ?>.<?= $i + 1 ?></span><span><b><?= e($l['title']) ?></b><small><?= e($kindLabel($l)) ?></small></span><?= isset($done[(int) $l['id']]) ? '<span class="cx-pill good">Done</span>' : '<span class="cx-pill mute">To do</span>' ?></a>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'lesson'):
        $lesson = get_campus_lesson((int) query_param('lesson'));
        if (!$lesson || (int) $lesson['campus_id'] !== $campusId || !campus_can_open_lesson($user, $lesson)) { echo '<div class="cx-top"><div><h1>Lesson not available</h1></div></div><p class="cx-fine">It may open later in your year. <a style="text-decoration:underline;" href="' . e($self(['view' => 'lessons'])) . '">Back to lessons</a></p>'; }
        else {
            $flat = array_values(array_filter($unlockedLessons, fn($l) => true));
            $idx = array_search((int) $lesson['id'], array_map(fn($l) => (int) $l['id'], $flat), true);
            $next = $idx !== false && isset($flat[$idx + 1]) ? $flat[$idx + 1] : null;
            $media = base_url('campus-media.php?lesson=' . $lesson['id']);
    ?>
      <div class="cx-top"><div><span class="cx-label">Month <?= (int) $lesson['month_no'] ?> · <?= e($stageTitle((int) $lesson['month_no'])) ?></span><h1><?= e($lesson['title']) ?></h1></div>
        <a class="cx-btn sm" href="<?= e($self(['view' => 'lessons', 'month' => $lesson['month_no']])) ?>">All lessons this month</a></div>
      <?php if ($lesson['kind'] === 'VIDEO'): ?>
        <div class="cx-player"><video src="<?= e($media) ?>" controls playsinline preload="metadata" controlsList="nodownload"></video></div>
      <?php else: ?>
        <div class="cx-pdf"><iframe src="<?= e($media) ?>#toolbar=0" title="<?= e($lesson['title']) ?>"></iframe></div>
      <?php endif; ?>
      <div class="cx-lesson-foot">
        <span class="cx-fine" style="margin:0;"><?= e($kindLabel($lesson)) ?><?php if ($lesson['kind'] === 'PDF'): ?> · <a href="<?= e($media . '&download=1') ?>" style="text-decoration:underline;">Download the PDF</a><?php endif; ?></span>
        <form method="post" style="margin:0;">
          <?= csrf_field() ?><input type="hidden" name="_action" value="done"><input type="hidden" name="lesson" value="<?= (int) $lesson['id'] ?>"><input type="hidden" name="next" value="<?= $next ? (int) $next['id'] : '' ?>">
          <button class="cx-btn solid" type="submit"><?= isset($done[(int) $lesson['id']]) ? ($next ? 'Next lesson' : 'Back to lessons') : ($next ? 'Mark as done and continue' : 'Mark as done') ?></button>
        </form>
      </div>
    <?php } ?>

    <?php elseif ($view === 'live'):
        $up = upcoming_campus_live_classes(60, $campusId);
        $past = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at DESC LIMIT 12', [$campusId]);
    ?>
      <div class="cx-top"><div><span class="cx-label">Live classes</span><h1><?= e($campus['live_schedule'] ? 'Every week, ' . $campus['live_schedule'] : 'Every week with your professor') ?></h1></div></div>
      <section class="cx-box" style="margin-top:20px;">
        <header><h2>Coming up</h2><span>Join from here when the class starts</span></header>
        <div class="cx-mlist">
          <?php if (!$up): ?><div class="cx-none" style="display:block;">The next live class will be announced here.</div><?php endif; ?>
          <?php foreach ($up as $l): ?>
            <div><div class="cx-when"><small><?= e(date('D', strtotime($l['starts_at']))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></div><div><b class="t"><?= e($l['title']) ?></b><small><?= e(date('j M Y, g:i a', strtotime($l['starts_at']))) ?> · <?= (int) $l['duration_minutes'] ?> min</small></div><?php if ($l['join_url']): ?><a class="cx-btn solid sm" href="<?= e($l['join_url']) ?>" target="_blank" rel="noopener">Join</a><?php else: ?><span class="cx-pill mute">Link soon</span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php if ($past): ?>
        <section class="cx-box" style="margin-top:20px;">
          <header><h2>Past classes</h2><span>Recordings are added to your lessons</span></header>
          <div class="cx-mlist">
            <?php foreach ($past as $l): ?><div><div class="cx-when"><small><?= e(date('M', strtotime($l['starts_at']))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></div><div><b class="t"><?= e($l['title']) ?></b><small><?= e(date('j M Y', strtotime($l['starts_at']))) ?></small></div><span class="cx-pill mute">Done</span></div><?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

    <?php elseif ($view === 'community'): $posts = get_campus_posts($campusId, 60); ?>
      <div class="cx-top"><div><span class="cx-label">Community</span><h1>The <?= e($campus['name']) ?> room</h1></div><span class="cx-fine" style="margin:0;">Only members of this campus can see this.</span></div>
      <section class="cx-box" style="margin-top:20px;">
        <form method="post" class="cx-send" style="border-top:0; border-bottom:1px solid var(--cx-line);">
          <?= csrf_field() ?><input type="hidden" name="_action" value="post">
          <textarea name="body" maxlength="2000" required placeholder="Share your progress, ask a question, help a classmate" aria-label="Message"></textarea>
          <button class="cx-btn solid" type="submit">Post</button>
        </form>
        <?php if (!$posts): ?><div class="cx-none">No messages yet. Be the first to say hello.</div><?php endif; ?>
        <?php foreach ($posts as $p): [$who, $tag, $staff] = $poster($p); ?>
          <div class="cx-msg"><span class="a<?= $staff ? ' prof' : '' ?>"><?= e(mb_strtoupper(mb_substr($who, 0, 1))) ?></span>
            <div><b><?= e($who) ?><?= $tag ? '<em>' . e($tag) . '</em>' : '' ?></b><time datetime="<?= e($p['created_at']) ?>"><?= e(time_ago($p['created_at'])) ?></time>
              <p><?= e($p['body']) ?></p>
              <?php if ($isAdmin): ?><form method="post" style="margin-top:6px;"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_post"><input type="hidden" name="post" value="<?= (int) $p['id'] ?>"><button type="submit" style="border:0; background:none; padding:0; font-size:12px; color:var(--cx-bad); cursor:pointer;">Remove</button></form><?php endif; ?>
            </div></div>
        <?php endforeach; ?>
      </section>

    <?php elseif ($view === 'certificate'): $code = $membership['certificate_code'] ?? null; ?>
      <div class="cx-top"><div><span class="cx-label">Certificate</span><h1><?= $code ? 'Your certificate is ready' : 'Earn your certificate' ?></h1></div></div>
      <section class="cx-box" style="margin-top:20px;">
        <div class="cx-kv">
          <div><span>Lessons completed</span><b><?= count($done) ?> of <?= $total ?></b></div>
          <div><span>Your year</span><b>Month <?= $currentMonth ?> of 12</b></div>
          <div><span>Status</span><b><?= $code ? 'Issued ' . e(date('j M Y', strtotime($membership['certificate_issued_at']))) : 'Complete every lesson of the twelve months' ?></b></div>
        </div>
      </section>
      <?php if ($code): ?><p style="margin-top:18px;"><a class="cx-btn solid" href="<?= e(base_url('campus-certificate.php?code=' . $code)) ?>">View and share your certificate</a></p><?php endif; ?>

    <?php elseif ($view === 'membership'): ?>
      <div class="cx-top"><div><span class="cx-label">Membership</span><h1><?= e($campus['name']) ?> Campus</h1></div><a class="cx-btn solid sm" href="<?= e(base_url('campus.php?slug=' . $campus['slug'] . '#join')) ?>">Add more time</a></div>
      <?php if ($isAdmin && !$membership): ?><p class="cx-fine">You are viewing as <?= $isProf ? 'the professor' : 'an admin' ?>, without a membership.</p><?php else: ?>
      <section class="cx-box" style="margin-top:20px;">
        <div class="cx-kv">
          <div><span>Plan</span><b><?= $membership['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></b></div>
          <div><span>Joined</span><b><?= e(date('j M Y', strtotime($membership['started_at']))) ?></b></div>
          <div><span>Access until</span><b><?= e(date('j M Y, g:i a', strtotime($membership['access_until']))) ?></b></div>
          <div><span>Renewal</span><b>Pay again before the end date. Nothing is charged automatically.</b></div>
          <div><span>Reminders</span><b><?= (int) $membership['reminders_off'] ? 'Off' : 'Email and notification, from 7 days before' ?></b></div>
        </div>
      </section>
      <form method="post" style="margin-top:14px;"><?= csrf_field() ?><input type="hidden" name="_action" value="reminders"><input type="hidden" name="off" value="<?= (int) $membership['reminders_off'] ? '0' : '1' ?>">
        <button type="submit" style="border:0; background:none; padding:0; font-size:13px; color:var(--cx-muted); text-decoration:underline; cursor:pointer;"><?= (int) $membership['reminders_off'] ? 'Turn reminders back on' : 'Stop reminders (cancel)' ?></button></form>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
<?php endif; ?>
</div>
</body>
</html>
