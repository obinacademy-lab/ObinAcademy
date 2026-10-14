<?php
/**
 * Professor: the class's students. Names, plan, when they joined, whether their access is open, lessons
 * done out of the lessons the class has reached, and their last finished lesson. No phone numbers or
 * emails: contact details stay with Obin Academy (the admin sees everything on Admin, Users).
 * Styles: assets/css/professor.css.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/professor.php';
$user = require_login();
if (!is_professor($user)) redirect('/dashboard.php');

$campus = professor_current_campus($user);
$id = (int) $campus['id'];
$show = in_array(query_param('show'), ['active', 'ended', 'all'], true) ? query_param('show') : 'active';
$q = trim(mb_substr(query_param('q'), 0, 80));
$rows = professor_students($campus, $show, $q);
$counts = [
    'active' => (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [$id])['n'] ?? 0),
    'ended' => (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until <= NOW()', [$id])['n'] ?? 0),
];
$counts['all'] = $counts['active'] + $counts['ended'];
$all = professor_students($campus, 'active');
$finishedAll = count(array_filter($all, fn($s) => $s['reached'] > 0 && (int) $s['done'] >= $s['reached']));
$notStarted = count(array_filter($all, fn($s) => (int) $s['done'] === 0));
$avg = $all ? (int) round(array_sum(array_column($all, 'pct')) / count($all)) : 0;
$multi = count(professor_campuses($user)) > 1;
$url = fn(array $p) => base_url('dashboard/professor/students.php?' . http_build_query(array_filter(['campus' => $multi ? $id : '', 'show' => $show, 'q' => $q] + $p, fn($v) => $v !== '' && $v !== null)));
$avColors = ['#2b22ff', '#0f766e', '#be185d', '#b45309', '#6d28d9', '#15803d'];

$pageTitle = 'Students — Professor — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="pf">
  <div class="pf-greet" data-in>
    <div><h1>Your <em>students</em></h1><p><?= e($campus['name']) ?> Campus · every student starts at month 1 and moves on when you complete their group's month. Progress counts the lessons in the months each student can open.</p></div>
  </div>

  <section class="pf-strip" data-in>
    <div><small>Active now</small><b><?= $counts['active'] ?></b></div>
    <div><small>Average progress</small><b><?= $avg ?>%</b></div>
    <div><small>Up to date</small><b class="good"><?= $finishedAll ?></b></div>
    <div><small>Not started</small><b class="<?= $notStarted ? 'warn' : '' ?>"><?= $notStarted ?></b></div>
  </section>

  <section class="pf-tile" data-in>
    <div class="pf-list-tools">
      <nav class="pf-seg as-links" aria-label="Show">
        <?php foreach (['active' => 'Active', 'ended' => 'Access ended', 'all' => 'Everyone'] as $k => $label): ?>
          <a href="<?= e($url(['show' => $k])) ?>"<?= $show === $k ? ' aria-current="page"' : '' ?>><?= $label ?> <em><?= $counts[$k] ?></em></a>
        <?php endforeach; ?>
      </nav>
      <form method="get" class="pf-search">
        <?php if ($multi): ?><input type="hidden" name="campus" value="<?= $id ?>"><?php endif; ?><input type="hidden" name="show" value="<?= e($show) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Find a student by name" aria-label="Find a student by name">
      </form>
    </div>
    <?php if (!$rows): ?>
      <p class="pf-empty"><?= $q !== '' ? 'No student matches “' . e($q) . '”.' : ($show === 'active' ? 'No active students yet. They appear here the moment someone joins.' : 'Nobody here yet.') ?></p>
    <?php else: ?>
      <div class="pf-table">
        <div class="pf-tr pf-th"><span>Student</span><span>Month</span><span>Progress</span><span>Last lesson</span><span>Access</span></div>
        <?php foreach ($rows as $k => $s): $segs = max(1, min(12, $s['reached'])); $lit = $s['reached'] ? (int) round($s['done'] / $s['reached'] * $segs) : 0; ?>
          <div class="pf-tr" style="--j:<?= min($k, 14) ?>">
            <span class="pf-who"><span class="av" style="background:<?= $avColors[$k % count($avColors)] ?>"><?= e(professor_initials($s['name'])) ?></span><span><b><?= e($s['name']) ?></b><small>Joined <?= e(date('j M Y', strtotime($s['started_at']))) ?></small></span></span>
            <span class="pf-mcell"><b>Month <?= (int) $s['month'] ?></b><small><?= $s['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></small></span>
            <span class="pf-meter wide"><span><?= (int) $s['done'] ?> / <?= (int) $s['reached'] ?><?= $s['certificate_code'] ? ' · certificate' : '' ?></span><span class="dots" style="grid-template-columns:repeat(<?= $segs ?>,1fr)"><?php for ($i = 0; $i < $segs; $i++): ?><i<?= $i < $lit ? ' class="y"' : '' ?>></i><?php endfor; ?></span></span>
            <span class="muted-c"><?= $s['last_done'] ? e(time_ago($s['last_done'])) : 'Not started' ?></span>
            <span><span class="pf-pill <?= $s['open'] ? 'green' : 'grey' ?>"><?= $s['open'] ? 'Until ' . e(date('j M', strtotime($s['access_until']))) : 'Ended' ?></span></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p class="pf-note">You see your students' names and progress. Their contact details stay private with Obin Academy.</p>
  </section>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
