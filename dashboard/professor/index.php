<?php
/**
 * Professor Overview: the class dial (the month the class is on, held to complete it), the next live
 * class on a split-flap board, active students with an eight-week trend, what is owed to them, this
 * week's duties (worked out from the campus, not ticked by hand), and their students and community
 * messages behind one switch, with a reply box. Professors only; admins use their own dashboard.
 * Styles: assets/css/professor.css. Motion: assets/js/professor.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/professor.php';
require __DIR__ . '/../../includes/campus_content.php';
$user = require_login();
if (!is_professor($user)) redirect('/dashboard.php');

$campus = professor_current_campus($user);
$id = (int) $campus['id'];
$self = '/dashboard/professor/index.php' . (count(professor_campuses($user)) > 1 ? '?campus=' . $id : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $a = post('_action');
    if ($a === 'reply') {
        $body = trim(mb_substr(post('body'), 0, 2000));
        if ($body !== '') {
            db_insert('INSERT INTO campus_posts (campus_id, user_id, body) VALUES (?, ?, ?)', [$id, $user['id'], $body]);
            flash_set('success', 'Posted to your class.');
        }
        redirect($self . '#desk-switch');
    }
    if (in_array($a, ['month_complete', 'month_undo', 'month_back'], true)) {
        try { campus_content_handle($a, $campus, $user); } catch (RuntimeException $e) { flash_set('error', $e->getMessage()); }
        redirect($self . (str_contains($self, '?') ? '&' : '?') . 'g=' . (int) post('month'));
    }
    redirect($self);
}

$campus = get_campus($id) ?: $campus;
$paced = campus_progress_ready();
$stages = get_campus_stages($id);
$byMonth = get_campus_lessons_by_month($id);

// Groups by month: every student is on their own month; the dial shows one group at a time.
$groupsOn = campus_groups_ready();
if ($groupsOn) campus_release_due_months($id);
$groups = $groupsOn ? campus_month_groups($id) : [];
if ($groupsOn) {
    $g = (int) query_param('g');
    if (!isset($groups[$g])) { $g = 0; foreach ($groups as $m => $info) if (!$g || $info['n'] > $groups[$g]['n']) $g = $m; }
    $sel = $groups[$g] ?? ['n' => 0, 'pending' => 0, 'opens_at' => null];
    $classMonth = max(1, min(12, $g ?: 1));
    $done = max(0, ($g ?: 1) - 1);
    $yearDone = $g > 12;
} else {
    $g = 0;
    $sel = ['n' => 0, 'pending' => 0, 'opens_at' => null];
    $done = campus_months_completed($campus);
    $classMonth = campus_open_month($campus);
    $yearDone = $done >= 12;
}
$nowTitle = trim((string) $stages[$classMonth]['title']) ?: 'Stage ' . $classMonth;
$nowLessons = count($byMonth[$classMonth] ?? []);
$nextLessons = $classMonth < 12 ? count($byMonth[$classMonth + 1] ?? []) : 0;
$groupUrl = fn(int $m) => base_url(ltrim($self, '/')) . (str_contains($self, '?') ? '&' : '?') . 'g=' . $m;
$active = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [$id])['n'] ?? 0);
$newWeek = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until > NOW() AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [$id])['n'] ?? 0);
$trend = professor_student_trend($id, 8);
$live = db_one('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY starts_at LIMIT 1', [$id]);
$liveThisWeek = (bool) db_one('SELECT 1 FROM campus_live_classes WHERE campus_id = ? AND starts_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) LIMIT 1', [$id]);
$unread = professor_unread_messages($id, (int) $user['id']);
$lastPost = db_one('SELECT MAX(created_at) AS t FROM campus_posts WHERE campus_id = ? AND user_id = ? AND deleted_at IS NULL', [$id, $user['id']])['t'] ?? null;
$pay = professor_pay_summary((int) $user['id'])[$id] ?? ['owed' => 0, 'earned30' => 0, 'last_paid_at' => null];
$share = campus_professor_share($campus);
$students = professor_students($campus, 'active', '', 6);
$posts = get_campus_posts($id, 4); // newest first
$hour = (int) date('G');
$greet = $hour < 12 ? 'Morning' : ($hour < 17 ? 'Afternoon' : 'Evening');
$first = explode(' ', trim($user['name']))[0];
$monthsWord = fn(int $n) => $n === 1 ? '1 lesson' : $n . ' lessons';

// This week's duties, worked out from the campus.
$duties = [[$liveThisWeek, 'Schedule this week\'s live class', $live ? date('D j M, g:i a', strtotime($live['starts_at'])) . ($live['join_url'] ? ' · link added' : ' · no join link yet') : 'No live class in the next 7 days', $liveThisWeek ? 'DONE' : 'TO DO', '/dashboard/campus-studio.php?tab=live&campus=' . $id]];
if ($groupsOn && $groups) {
    // Every month a group is on needs lessons; the furthest group's next month needs preparing.
    $empty = array_values(array_filter(array_keys($groups), fn($m) => $m <= 12 && empty($byMonth[$m])));
    $duties[] = [!$empty, $empty ? 'Month ' . implode(', ', $empty) . ' ' . (count($empty) === 1 ? 'has' : 'have') . ' no lessons' : 'Lessons ready for every group', $empty ? 'Students are on ' . (count($empty) === 1 ? 'it' : 'them') . ' now' : count($groups) . ' group' . (count($groups) === 1 ? '' : 's') . ' learning right now', $empty ? 'NEEDED' : 'DONE', '/dashboard/campus-studio.php?tab=lessons&month=' . ($empty[0] ?? $classMonth) . '&campus=' . $id];
    $far = min(12, max(array_keys($groups)));
    if ($far < 12) { $farNext = count($byMonth[$far + 1] ?? []); $duties[] = [$farNext > 0, 'Prepare month ' . ($far + 1), $farNext ? $monthsWord($farNext) . ' ready for your furthest group' : 'Your furthest group is on month ' . $far, $farNext ? 'READY' : 'NEEDED', '/dashboard/campus-studio.php?tab=lessons&month=' . ($far + 1) . '&campus=' . $id]; }
} else {
    $duties[] = [$nowLessons > 0, 'Lessons for month ' . $classMonth, $nowLessons ? $monthsWord($nowLessons) . ' live for the class' : 'Students start here and it is empty', $nowLessons ? 'DONE' : 'NEEDED', '/dashboard/campus-studio.php?tab=lessons&month=' . $classMonth . '&campus=' . $id];
    if ($classMonth < 12) $duties[] = [$nextLessons > 0, 'Prepare month ' . ($classMonth + 1), $nextLessons ? $monthsWord($nextLessons) . ' ready to open' : 'Upload lessons before you open it', $nextLessons ? 'READY' : 'NEEDED', '/dashboard/campus-studio.php?tab=lessons&month=' . ($classMonth + 1) . '&campus=' . $id];
}
$duties[] = [$unread === 0, $unread ? 'Answer ' . $unread . ' community message' . ($unread === 1 ? '' : 's') : 'Community is answered', $lastPost ? 'Since your last reply, ' . time_ago($lastPost) : 'You have not posted yet', $unread ? $unread . ' NEW' : 'DONE', '/campus-learn.php?campus=' . $campus['slug'] . '&view=community'];
$dutiesDone = count(array_filter(array_column($duties, 0)));

$liveBoard = $live ? strtoupper(date('D', strtotime($live['starts_at']))) . ' ' . date('H:i', strtotime($live['starts_at'])) : 'NOT SET';
$avColors = ['#2b22ff', '#0f766e', '#be185d', '#b45309', '#6d28d9', '#15803d'];

$pageTitle = 'Overview — Professor — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="pf" data-pf-overview>
  <div class="pf-greet" data-in>
    <div>
      <h1><?= e($greet) ?>, <?= e($first) ?>. <em><?= $groupsOn ? (count($groups) > 1 ? count($groups) . ' groups are learning.' : ($groups ? 'Month ' . $classMonth . ' is live.' : 'Ready for your first students.')) : ($yearDone ? 'The year is complete.' : 'Month ' . $classMonth . ' is live.') ?></em></h1>
      <p><?= e($campus['name']) ?> Campus · <?= $active ?> student<?= $active === 1 ? '' : 's' ?><?= $campus['live_schedule'] ? ' · live ' . e($campus['live_schedule']) : '' ?></p>
    </div>
    <div class="pf-quick">
      <?php if (count(professor_campuses($user)) > 1): ?>
        <form method="get" class="pf-pick"><select name="campus" onchange="this.form.submit()" aria-label="Campus"><?php foreach (professor_campuses($user) as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $id ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></form>
      <?php endif; ?>
      <a class="pf-btn" href="<?= e(base_url('campus-learn.php?campus=' . $campus['slug'])) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>Student view</a>
      <a class="pf-btn solid" href="<?= e(base_url('dashboard/campus-studio.php?tab=lessons&month=' . $classMonth . '&campus=' . $id)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>Upload a lesson</a>
    </div>
  </div>

  <section class="pf-desk" data-desk>
    <article class="pf-tile dark pf-dial-tile" data-in>
      <div class="pf-tile-h"><small><?= $groupsOn ? 'Your groups' : 'Your class' ?></small><a href="<?= e(base_url('dashboard/campus-studio.php?tab=curriculum&campus=' . $id)) ?>" class="lime">Studio →</a></div>
      <?php if ($groupsOn && $groups): ?>
        <nav class="pf-groups" aria-label="Groups by month">
          <?php foreach ($groups as $m => $info): ?><a href="<?= e($groupUrl($m)) ?>" class="<?= $m === $g ? 'on' : '' ?>"<?= $m === $g ? ' aria-current="true"' : '' ?>><?= $m > 12 ? 'Finished' : 'Month ' . $m ?><em><?= $info['n'] ?></em><?= $info['pending'] ? '<i title="Moving on soon"></i>' : '' ?></a><?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <div class="pf-dial-wrap">
        <div class="pf-dial">
          <svg viewBox="0 0 240 240" aria-label="Month <?= $classMonth ?> of 12">
            <defs><linearGradient id="pfArc" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6d66ff"/><stop offset="1" stop-color="#cdfb5e"/></linearGradient></defs>
            <circle class="track" cx="120" cy="120" r="96"/>
            <circle class="arc" data-arc data-done="<?= $done ?>" data-now="<?= $yearDone ? 12 : $classMonth ?>" cx="120" cy="120" r="96" transform="rotate(-90 120 120)"/>
            <g data-ticks></g>
          </svg>
          <div class="pf-dial-c"><small><?= $yearDone ? 'THIS GROUP HAS' : ($groupsOn ? ($sel['n'] === 1 ? '1 STUDENT ON' : ($sel['n'] ?: 'NEW STUDENTS START ON')) . ($sel['n'] > 1 ? ' STUDENTS ON' : '') : 'THE CLASS IS ON') ?></small><b><?= $yearDone ? '12' : $classMonth ?><sup>/12</sup></b><span><?= e($yearDone ? 'finished the year' : $nowTitle) ?></span></div>
        </div>
      </div>
      <div class="pf-dial-foot">
        <?php if (!$paced): ?>
          <p>Completing months is being switched on by Obin Academy.</p>
        <?php elseif ($groupsOn && !$groups): ?>
          <p>No students yet. Every new student starts at month 1, and you complete each group's month when they are ready.</p>
        <?php elseif ($groupsOn && $sel['pending']): ?>
          <p>Month <?= $classMonth ?> is complete for this group. <?= $classMonth < 12 ? 'Month ' . ($classMonth + 1) . ' opens' : 'Their year finishes' ?> at <b data-opens-at="<?= e(date('c', strtotime($sel['opens_at']))) ?>"><?= e(date('g:i a', strtotime($sel['opens_at']))) ?></b>.</p>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="month_undo"><input type="hidden" name="month" value="<?= $g ?>"><button class="pf-undo" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-1"/></svg>Undo · <span data-undo-left>10:00</span> left</button></form>
          <p class="pf-hold-note">Students have not seen anything yet. Undo now and nobody is told.</p>
        <?php elseif ($yearDone): ?>
          <p>This group has finished the year. Students who finish every lesson get their certificate.</p>
          <?php if ($groupsOn): ?><form method="post" data-confirm="Move this group back to month 12?"><?= csrf_field() ?><input type="hidden" name="_action" value="month_back"><input type="hidden" name="month" value="<?= $g ?>"><button class="pf-back" type="submit">Move this group back a month</button></form><?php endif; ?>
        <?php else: ?>
          <p><?= $monthsWord($nowLessons) ?> in month <?= $classMonth ?><?= $classMonth < 12 ? ' · month ' . ($classMonth + 1) . ($nextLessons ? ' has ' . $monthsWord($nextLessons) : ' has none yet') : '' ?></p>
          <form method="post" data-hold-form>
            <?= csrf_field() ?><input type="hidden" name="_action" value="month_complete"><input type="hidden" name="month" value="<?= $groupsOn ? $g : $classMonth ?>">
            <button class="pf-hold" type="button" data-hold aria-describedby="pf-hold-note"><span class="fillbar" data-fillbar></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span data-hold-label>Hold to complete month <?= $classMonth ?></span></button>
          </form>
          <p class="pf-hold-note" id="pf-hold-note"><?= $groupsOn ? 'Moves only this group (' . ($sel['n'] === 1 ? '1 student' : $sel['n'] . ' students') . ') to month ' . min(12, $classMonth + 1) . ', ' . CAMPUS_MONTH_UNDO_MINUTES . ' minutes after you finish holding. You can undo until then. Only students with an active subscription move on.' : 'Press and hold, so a month never opens by accident.' ?></p>
          <?php if ($groupsOn && $g > 1): ?><form method="post" data-confirm="Move this group back to month <?= $g - 1 ?>? Students may notice month <?= $g ?> close."><?= csrf_field() ?><input type="hidden" name="_action" value="month_back"><input type="hidden" name="month" value="<?= $g ?>"><button class="pf-back" type="submit">Opened a month by mistake? Move this group back</button></form><?php endif; ?>
        <?php endif; ?>
      </div>
    </article>

    <article class="pf-tile dark" data-in>
      <div class="pf-tile-h"><small>Next live class</small><?php if ($live): ?><span class="pf-count" data-countdown="<?= e(date('c', strtotime($live['starts_at']))) ?>">soon</span><?php endif; ?></div>
      <div class="pf-board" data-board="<?= e($liveBoard) ?>" aria-label="<?= e($live ? date('l, g:i a', strtotime($live['starts_at'])) : 'No live class scheduled') ?>"></div>
      <div class="pf-live-meta">
        <div><b><?= e($live ? $live['title'] : 'No live class scheduled') ?></b><small><?= $live ? (int) $live['duration_minutes'] . ' minutes · ' . ($live['join_url'] ? 'join link ready' : 'add the join link') : 'Schedule the next one for your class' ?></small></div>
        <a class="pf-btn ghost" href="<?= e(base_url('dashboard/campus-studio.php?tab=live&campus=' . $id)) ?>"><?= $live ? 'Schedule' : 'Schedule one' ?></a>
      </div>
    </article>

    <article class="pf-tile" data-in>
      <div class="pf-tile-h"><small>Students</small><a href="<?= e(base_url('dashboard/professor/students.php' . (count(professor_campuses($user)) > 1 ? '?campus=' . $id : ''))) ?>">All →</a></div>
      <div class="pf-big"><b><?= $active ?></b><?php if ($newWeek): ?><span>▲ <?= $newWeek ?> this week</span><?php else: ?><span class="flat">active now</span><?php endif; ?></div>
      <svg class="pf-spark" viewBox="0 0 300 70" preserveAspectRatio="none" data-spark="<?= e(implode(',', $trend)) ?>" aria-label="Active students over the last 8 weeks, from <?= $trend[0] ?> to <?= end($trend) ?>">
        <defs><linearGradient id="pfSpark" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b22ff" stop-opacity=".22"/><stop offset="1" stop-color="#2b22ff" stop-opacity="0"/></linearGradient></defs>
        <path class="area" data-spark-area d=""/><path class="line" data-spark-line d=""/><circle class="end" data-spark-end r="4.5" cx="0" cy="0"/>
      </svg>
      <div class="pf-spark-l"><span>8 weeks ago</span><span>now</span></div>
    </article>

    <article class="pf-tile pf-pay" data-in>
      <div class="pf-tile-h"><small>Owed to you</small><a href="<?= e(base_url('dashboard/professor.php' . (count(professor_campuses($user)) > 1 ? '?campus=' . $id : ''))) ?>">My Pay →</a></div>
      <div class="pf-owed"><small>UGX</small><?= e(number_format((float) $pay['owed'])) ?></div>
      <div class="pf-pay-rows">
        <div><span>Earned, last 30 days</span><b><?= e(number_format((float) ($pay['earned30'] ?? 0))) ?></b></div>
        <div><span>Your share</span><b><?= e(format_share($share)) ?></b></div>
        <div><span>Last payout</span><b><?= !empty($pay['last_paid_at']) ? e(date('j M', strtotime($pay['last_paid_at']))) : '—' ?></b></div>
      </div>
    </article>
  </section>

  <section class="pf-row2">
    <article class="pf-tile" data-in>
      <div class="pf-tile-h"><small>This week</small><span class="pf-pill blue"><?= $dutiesDone ?> of <?= count($duties) ?> done</span></div>
      <?php foreach ($duties as [$ok, $title, $sub, $tag, $href]): ?>
        <a class="pf-duty<?= $ok ? ' done' : '' ?>" href="<?= e(base_url(ltrim($href, '/'))) ?>">
          <span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
          <span class="txt"><b><?= e($title) ?></b><small><?= e($sub) ?></small></span>
          <span class="pf-pill <?= $ok ? 'green' : ($tag === 'NEEDED' ? 'amber' : 'blue') ?>"><?= e($tag) ?></span>
        </a>
      <?php endforeach; ?>
    </article>

    <article class="pf-tile" data-in id="desk-switch">
      <div class="pf-tile-h">
        <div class="pf-seg" role="group" aria-label="Show"><button type="button" aria-pressed="true" data-pane="students">Students</button><button type="button" aria-pressed="false" data-pane="community">Community<?= $unread ? ' · ' . $unread : '' ?></button></div>
        <a href="<?= e(base_url('dashboard/professor/students.php' . (count(professor_campuses($user)) > 1 ? '?campus=' . $id : ''))) ?>" data-pane-link data-students-href="<?= e(base_url('dashboard/professor/students.php' . (count(professor_campuses($user)) > 1 ? '?campus=' . $id : ''))) ?>" data-community-href="<?= e(base_url('campus-learn.php?campus=' . $campus['slug'] . '&view=community')) ?>">All <?= $active ?> →</a>
      </div>
      <div class="pf-pane" data-pane-box>
        <div data-pane="students">
          <?php if (!$students): ?><p class="pf-empty">No students yet. They appear here as soon as someone joins.</p><?php endif; ?>
          <?php foreach ($students as $k => $s): $segs = max(1, min(12, $s['reached'])); $lit = $s['reached'] ? (int) round($s['done'] / $s['reached'] * $segs) : 0; ?>
            <div class="pf-stu"><span class="av" style="background:<?= $avColors[$k % count($avColors)] ?>"><?= e(professor_initials($s['name'])) ?></span>
              <div><b><?= e($s['name']) ?></b><small>Month <?= (int) $s['month'] ?> · <?= $s['plan'] === 'YEARLY' ? 'yearly' : 'monthly' ?> · joined <?= e(time_ago($s['started_at'])) ?></small></div>
              <div class="pf-meter"><span><?= (int) $s['done'] ?> / <?= (int) $s['reached'] ?></span><span class="dots" style="grid-template-columns:repeat(<?= $segs ?>,1fr)"><?php for ($i = 0; $i < $segs; $i++): ?><i<?= $i < $lit ? ' class="y"' : '' ?>></i><?php endfor; ?></span></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div data-pane="community" hidden>
          <?php if (!$posts): ?><p class="pf-empty">No messages yet. Start the conversation with your class.</p><?php endif; ?>
          <?php foreach ($posts as $p): $mine = (int) $p['user_id'] === (int) $user['id']; ?>
            <div class="pf-post"><span class="av<?= $mine ? ' me' : '' ?>"><?= e(professor_initials($p['name'])) ?></span><div><b><?= e($mine ? 'You' : $p['name']) ?></b><p><?= e(mb_strimwidth($p['body'], 0, 220, '…')) ?></p></div><time><?= e(time_ago($p['created_at'])) ?></time></div>
          <?php endforeach; ?>
          <form method="post" class="pf-answer"><?= csrf_field() ?><input type="hidden" name="_action" value="reply"><input name="body" maxlength="2000" required aria-label="Reply to your class" placeholder="Reply to your class…"><button type="submit">Send</button></form>
        </div>
      </div>
    </article>
  </section>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
