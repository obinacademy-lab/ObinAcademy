<?php
/**
 * A learner's dashboard: their campuses, progress through each year, the next lesson, live classes and
 * what to do next. Laid out as the same "desk" as the admin Overview (approved mockup): a deep blue hero
 * (greeting, next lesson, four numbers, their campus photo), a dark "Continue learning" card that stays
 * in view, and white blocks (my campuses, live this week, explore, quick actions).
 * Styles: admin-desk.css (shared desk + shell) and learner-desk.css. Script: admin-desk.js (ripple).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_login();
require_once __DIR__ . '/../../includes/professor.php';
if (is_professor($user)) redirect('/dashboard/professor/index.php'); // professors have their own dashboard, no student pages

$myCampuses = campuses_ready() ? db_all(
    'SELECT m.*, c.name, c.slug, c.icon, c.live_schedule, c.professor_name, c.status AS campus_status FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id WHERE m.user_id = ? ORDER BY m.access_until DESC',
    [$user['id']]
) : [];
$certificates = count(array_filter($myCampuses, fn($m) => !empty($m['certificate_code'])));

// Each campus: where the student is in the year, lessons done, and the next lesson they can open.
$lessonsDone = 0;
foreach ($myCampuses as $k => $m) {
    $cid = (int) $m['campus_id'];
    $isOpen = strtotime($m['access_until']) > time();
    $month = campus_current_month($m);
    $byMonth = get_campus_lessons_by_month($cid);
    $doneIds = array_flip(campus_completed_lesson_ids((int) $user['id'], $cid));
    $next = null; $nextPos = 0;
    foreach ($byMonth as $mn => $ls) {
        if ($mn > $month) break;
        foreach ($ls as $i => $l) { if (!isset($doneIds[(int) $l['id']])) { $next = $l; $nextPos = $i; break 2; } }
    }
    $monthLs = $byMonth[$next ? (int) $next['month_no'] : $month] ?? [];
    $myCampuses[$k] += [
        'open' => $isOpen, 'month' => $month, 'done' => count($doneIds), 'next' => $next, 'next_pos' => $nextPos,
        'month_ls' => $monthLs, 'month_done' => count(array_filter($monthLs, fn($l) => isset($doneIds[(int) $l['id']]))),
        'done_ids' => $doneIds, 'photo' => campus_photo($m),
    ];
    if ($isOpen) $lessonsDone += count($doneIds);
}
ksort($myCampuses);
$open = array_values(array_filter($myCampuses, fn($m) => $m['open']));
// The campus to continue: the first open one with a lesson waiting, else the first open one.
$main = null;
foreach ($open as $m) { if ($m['next']) { $main = $m; break; } }
if (!$main && $open) $main = $open[0];

$weekDone = $open ? (int) db_one('SELECT COUNT(*) AS n FROM campus_lesson_progress WHERE user_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [$user['id']])['n'] : 0;

// Live classes in their open campuses: the next one, and this week's.
$live = [];
foreach ($open as $m) foreach (upcoming_campus_live_classes(7, (int) $m['campus_id']) as $l) $live[] = $l;
usort($live, fn($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
$nextLive = $live[0] ?? null;
if (!$nextLive) {
    foreach ($open as $m) {
        $l = upcoming_campus_live_classes(30, (int) $m['campus_id'])[0] ?? null;
        if ($l && (!$nextLive || $l['starts_at'] < $nextLive['starts_at'])) $nextLive = $l;
    }
}
$hm = fn(int $t) => date(date('i', $t) === '00' ? 'g A' : 'g:i A', $t);
$liveIn = function (int $t): string {
    $d = (int) round((strtotime(date('Y-m-d', $t)) - strtotime(date('Y-m-d'))) / 86400);
    return $t <= time() ? 'on now' : ($d === 0 ? 'today' : ($d === 1 ? 'tomorrow' : 'in ' . $d . ' days'));
};

// Campuses they have not joined, for "Explore more".
$joined = array_map(fn($m) => (int) $m['campus_id'], $myCampuses);
$explore = [];
if (campuses_ready()) {
    foreach (get_all_campuses() as $c) {
        if ($c['status'] !== 'PUBLISHED' || in_array((int) $c['id'], $joined, true)) continue;
        $c['photo'] = campus_photo($c);
        $explore[] = $c;
        if (count($explore) === 3) break;
    }
}

$firstName = explode(' ', trim($user['name']))[0];
$hour = (int) date('G');
$quotes = [
    'Small steps, every day, add up to real change.',
    'The expert in anything was once a beginner.',
    'Discipline beats motivation when motivation runs out.',
    'Every lesson you finish is a skill you keep for life.',
    'Progress, not perfection.',
];
$quote = $quotes[(int) date('z') % count($quotes)];
$learnUrl = fn(array $m, array $q = []) => base_url('campus-learn.php?' . http_build_query(['campus' => $m['slug']] + $q));
$ic = [
    'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    'live' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4"/></svg>',
    'arrow' => '<svg class="r" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    'play' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>',
    'campus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 21h18M5 21V8l7-5 7 5v13"/></svg>',
    'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>',
    'chat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
];

$pageTitle = 'My Campuses — Obin Academy';
$dashClass = 'dash-ov ovd-shell';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-desk.css', 'assets/css/learner-desk.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
$mainNext = $main['next'] ?? null;
?>
<div class="ovd ld">
  <section class="dk-hero<?= $main && $main['photo'] ? '' : ' ld-nopic' ?>">
    <span class="dk-aurora" aria-hidden="true"><i></i><i></i><i></i></span>
    <div class="dk-copy">
      <span class="dk-chip"><i></i><?= e(date('l j F')) ?> · your learning</span>
      <h1>
        <span class="dk-line"><span style="--k:0">Welcome back, <?= e($firstName) ?>.</span></span>
        <span class="dk-line"><span style="--k:1"><?php if ($mainNext): ?><em>Month <?= (int) $mainNext['month_no'] ?></em> is waiting.<?php elseif ($main): ?>You're <em>up to date.</em><?php else: ?>Start your <em>first campus.</em><?php endif; ?></span></span>
      </h1>
      <?php if ($mainNext): $left = count($main['month_ls']) - $main['month_done']; ?>
        <p class="ld-lede">Up next in <?= e($main['name']) ?>: <b><?= e($mainNext['title']) ?></b>. <?= $left ?> lesson<?= $left === 1 ? '' : 's' ?> left this month.</p>
      <?php elseif ($main): ?>
        <p class="ld-lede">You have finished everything open in <?= e($main['name']) ?>. The next month opens when your professor completes this one for your group.</p>
      <?php else: ?>
        <p class="ld-lede">A year-long programme with a professor: lessons, PDF guides and a live class every week, ending with a certificate.</p>
      <?php endif; ?>
      <div class="dk-kpis">
        <div class="dk-kpi" style="--k:0"><small>Active campuses</small><b><i data-count="<?= count($open) ?>"><?= count($open) ?></i></b><span class="dk-meta ld-d"><?= $open ? e(implode(', ', array_map(fn($m) => $m['name'], array_slice($open, 0, 2)))) : 'None yet' ?></span></div>
        <div class="dk-kpi" style="--k:1"><small>Lessons done</small><b><i><?= $lessonsDone ?></i></b><span class="dk-meta ld-d"><?= $weekDone ? '▲ ' . $weekDone . ' this week' : 'Across your campuses' ?></span></div>
        <div class="dk-kpi" style="--k:2"><small>Next live class</small><b class="ld-sm"><?= $nextLive ? e(date('D j M', strtotime($nextLive['starts_at'])) . ', ' . $hm(strtotime($nextLive['starts_at']))) : '—' ?></b><span class="dk-meta ld-d"><?= $nextLive ? e($liveIn(strtotime($nextLive['starts_at']))) : 'None booked yet' ?></span></div>
        <div class="dk-kpi" style="--k:3"><small>Certificates</small><b><i><?= $certificates ?></i></b><span class="dk-meta ld-d"><?= $certificates ? 'Well done' : 'After month 12' ?></span></div>
      </div>
    </div>
    <?php if ($main && $main['photo']): ?>
      <a class="ld-pic" href="<?= e($mainNext ? $learnUrl($main, ['view' => 'lesson', 'lesson' => $mainNext['id']]) : $learnUrl($main)) ?>"><img src="<?= e($main['photo']) ?>" alt=""><span class="ld-play"><i><?= $ic['play'] ?></i><?= $mainNext ? 'Continue lesson' : 'Open campus' ?></span></a>
    <?php endif; ?>
  </section>

  <aside class="dk-side">
    <div class="dk-dark">
      <?php if ($main && $mainNext): $mls = $main['month_ls']; ?>
        <span class="dk-eb">Continue learning</span>
        <h3><?= e($mainNext['title']) ?></h3>
        <p class="ld-sub"><?= e($main['name']) ?> · Month <?= (int) $mainNext['month_no'] ?> · Lesson <?= $main['next_pos'] + 1 ?> of <?= count($mls) ?></p>
        <div class="ld-segs" style="--n:<?= max(1, count($mls)) ?>"><?php foreach ($mls as $l): ?><i class="<?= isset($main['done_ids'][(int) $l['id']]) ? 'd' : ((int) $l['id'] === (int) $mainNext['id'] ? 'c' : '') ?>"></i><?php endforeach; ?></div>
        <div class="ld-segs-l"><span><?= $main['month_done'] ?> of <?= count($mls) ?> done this month</span><span><?= $mainNext['kind'] === 'PDF' ? 'PDF guide' : 'Video' ?><?= $mainNext['duration_label'] ? ' · ' . e($mainNext['duration_label']) : '' ?></span></div>
        <a class="dk-btn lime full" href="<?= e($learnUrl($main, ['view' => 'lesson', 'lesson' => $mainNext['id']])) ?>">Continue lesson<?= $ic['arrow'] ?></a>
        <a class="dk-btn ghost full" href="<?= e($learnUrl($main)) ?>">Open my campus</a>
      <?php elseif ($main): ?>
        <span class="dk-eb">Your campus</span>
        <h3>You're up to date in <?= e($main['name']) ?></h3>
        <p class="ld-sub">Month <?= (int) $main['month'] ?> of 12 · <?= (int) $main['done'] ?> lessons done</p>
        <a class="dk-btn lime full" href="<?= e($learnUrl($main)) ?>">Open my campus<?= $ic['arrow'] ?></a>
      <?php else: ?>
        <span class="dk-eb">Get started</span>
        <h3>Join a campus</h3>
        <p class="ld-sub">Pick a campus, pay with MTN or Airtel Money, and start month 1 today.</p>
        <a class="dk-btn lime full" href="<?= e(base_url('index.php#campuses')) ?>">See the campuses<?= $ic['arrow'] ?></a>
      <?php endif; ?>
      <?php if ($main): $dl = campus_days_left($main); ?>
        <div class="ld-checks">
          <div><?= $ic['check'] ?>Access until <?= e(date('j M', strtotime($main['access_until']))) ?> · <?= max(0, $dl) ?> day<?= $dl === 1 ? '' : 's' ?> left</div>
          <?php if ($nextLive): ?><div class="live"><?= $ic['live'] ?>Live class <?= e(date('l', strtotime($nextLive['starts_at'])) . ', ' . $hm(strtotime($nextLive['starts_at']))) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="dk-white ld-thought"><small>Today's thought</small><q><?= e($quote) ?></q></div>
  </aside>

  <div class="dk-col">
    <section class="dk-block" style="--b:0">
      <div class="dk-block-h"><h2>My <em>campuses</em></h2><span><?= count($myCampuses) ?> joined</span></div>
      <?php if (!$myCampuses): ?>
        <div class="ld-empty"><div><b>Join a campus</b><p>A year-long programme with a professor: lessons, PDF guides and a live class every week, ending with a certificate.</p></div><a class="dk-btn ld-blue" href="<?= e(base_url('index.php#campuses')) ?>">See the campuses</a></div>
      <?php else: ?>
        <div class="ld-mine">
          <?php foreach ($myCampuses as $k => $m): $month = (int) $m['month']; $ended = !$m['open']; ?>
            <article class="ld-mc<?= $ended ? ' ended' : '' ?>" style="--i:<?= $k ?>">
              <a class="ph" href="<?= e($ended ? base_url('campus.php?slug=' . $m['slug'] . '#join') : $learnUrl($m)) ?>"><?php if ($m['photo']): ?><img src="<?= e($m['photo']) ?>" alt=""><?php else: ?><span class="ic"><?= campus_icon($m['icon'] ?: 'book', 1.8) ?></span><?php endif; ?><span class="st"><?= $ended ? 'Ended' : 'Open' ?></span></a>
              <div class="bd">
                <div class="row1">
                  <div><b class="t"><?= e($m['name']) ?></b><span class="pf"><?= $m['professor_name'] ? 'Prof. ' . e($m['professor_name']) . ' · ' : '' ?><?= $m['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></span></div>
                  <?php if ($ended): ?><a class="dk-btn ld-light" href="<?= e(base_url('campus.php?slug=' . $m['slug'] . '#join')) ?>">Renew</a><?php else: ?><a class="dk-btn ld-blue" href="<?= e($learnUrl($m)) ?>">Open campus</a><?php endif; ?>
                </div>
                <div class="ld-yr"><?php for ($i = 1; $i <= 12; $i++): ?><i class="<?= $i < $month ? 'd' : ($i === $month ? 'c' : '') ?>"></i><?php endfor; ?></div>
                <div class="ld-yr-l"><span><?= $ended ? 'Stopped at month ' . $month : 'Month ' . $month . ' of 12' ?></span><span><?= (int) $m['done'] ?> lesson<?= (int) $m['done'] === 1 ? '' : 's' ?> done</span></div>
                <div class="ld-facts">
                  <?php if ($ended): ?>
                    <span class="warn">Access ended <?= e(date('j M', strtotime($m['access_until']))) ?></span><span>Renew to continue from month <?= $month ?></span>
                  <?php else: ?>
                    <span>Access until <b><?= e(date('j M', strtotime($m['access_until']))) ?></b></span>
                    <?php if ($m['live_schedule']): ?><span>Live <b><?= e($m['live_schedule']) ?></b></span><?php endif; ?>
                    <span><?= !empty($m['certificate_code']) ? '<a href="' . e(base_url('campus-certificate.php?code=' . $m['certificate_code'])) . '">Certificate ready</a>' : 'Certificate after month 12' ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <div class="dk-two even">
      <section class="dk-block" style="--b:1">
        <div class="dk-block-h"><h2>Live <em>this week</em></h2><span>Your campuses</span></div>
        <?php if ($live): ?>
          <div class="dk-rows">
            <?php foreach (array_slice($live, 0, 4) as $l): $at = strtotime($l['starts_at']); ?>
              <div class="dk-row"><span class="date"><small><?= e(date('D', $at)) ?></small><b><?= e(date('j', $at)) ?></b></span><span class="tx"><b><?= e($l['title']) ?></b><small><?= e($l['campus_name']) ?> · <?= e($hm($at)) ?> · <?= (int) $l['duration_minutes'] ?> min</small></span><?php if (trim((string) $l['join_url']) !== ''): ?><a class="dk-btn ld-blue sm" href="<?= e($l['join_url']) ?>" target="_blank" rel="noopener">Join</a><?php else: ?><small class="ago">Link soon</small><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="dk-empty"><?= $open ? 'No live class in the next 7 days. It will show here with a Join button.' : 'Live classes from your campuses show here.' ?></p><?php endif; ?>
      </section>
      <section class="dk-block" style="--b:2">
        <div class="dk-block-h"><h2>Explore <em>more</em></h2><a href="<?= e(base_url('index.php#campuses')) ?>">All campuses →</a></div>
        <?php if ($explore): ?>
          <div class="ld-explore">
            <?php foreach ($explore as $c): ?><a class="ex" href="<?= e(base_url('campus.php?slug=' . $c['slug'])) ?>"><?php if ($c['photo']): ?><img src="<?= e($c['photo']) ?>" alt=""><?php endif; ?><span><?= e($c['name']) ?></span></a><?php endforeach; ?>
          </div>
        <?php else: ?><p class="dk-empty">You have joined every open campus. More are on the way.</p><?php endif; ?>
      </section>
    </div>

    <section class="dk-block" style="--b:3">
      <div class="dk-block-h"><h2>Quick <em>actions</em></h2></div>
      <div class="ld-qa">
        <a href="<?= e(base_url('index.php#campuses')) ?>"><span class="ic"><?= $ic['campus'] ?></span><b>Explore campuses</b><small>Year-long campuses with a professor</small></a>
        <a href="<?= e(base_url('dashboard/settings.php')) ?>"><span class="ic"><?= $ic['settings'] ?></span><b>Account settings</b><small>Your name, photo and password</small></a>
        <a href="<?= e(base_url('contact.php')) ?>"><span class="ic"><?= $ic['chat'] ?></span><b>Get help</b><small>Talk to the Obin Academy team</small></a>
      </div>
    </section>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-desk.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
