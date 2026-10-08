<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$allStudents = get_students_for_creator((int) $user['id']);

$courseFilter = query_param('course');
$students = $courseFilter !== ''
    ? array_values(array_filter($allStudents, fn($s) => (string) $s['course_id'] === $courseFilter))
    : $allStudents;

$searchQ = trim(query_param('q'));
if ($searchQ !== '') {
    $needle = mb_strtolower($searchQ);
    $students = array_values(array_filter($students, fn($s) => str_contains(mb_strtolower(($s['learner_name'] ?? '') . ' ' . ($s['learner_email'] ?? '')), $needle)));
}

$myCourses = db_all("SELECT id, title FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);

$sourceLabel = ['PURCHASE' => 'Purchased', 'SUBSCRIPTION' => 'Subscription'];

/** One person can be enrolled in many courses; the list shows each person once, with their courses beneath. */
$personKey = fn(array $s): string => $s['learner_user_id'] ? 'u' . $s['learner_user_id'] : 'e' . mb_strtolower((string) $s['learner_email']);
$people = [];
foreach ($students as $s) {
    $k = $personKey($s);
    if (!isset($people[$k])) {
        $people[$k] = ['name' => $s['learner_name'] ?: 'Unknown', 'email' => $s['learner_email'], 'avatar' => $s['learner_avatar_url'], 'guest' => !$s['learner_user_id'], 'rows' => []];
    }
    $people[$k]['rows'][] = $s;
}

$totalPeople = count(array_unique(array_map($personKey, $allStudents)));
$totalEnrolments = count($allStudents);
$avgProgress = $totalEnrolments ? array_sum(array_column($allStudents, 'progress')) / $totalEnrolments : 0;
$completedCount = count(array_filter($allStudents, fn($s) => (float) $s['progress'] >= 100));
$topLocations = get_top_learner_locations_for_creator((int) $user['id'], 8);
$locMax = $topLocations ? max(array_map(fn($l) => (int) $l['n'], $topLocations)) : 1;
$showFirst = 3;

$pageTitle = 'My Students — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-hero reveal">
  <div>
    <h1 class="h2" style="color:#fff;">My Students</h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72);">Everyone enrolled across your courses. This list is private to you and never shown publicly.</p>
  </div>
</div>

<div class="st-strip" style="margin-top:24px;">
  <div><b><?= $totalPeople ?></b><span>Students</span></div>
  <div><b><?= $totalEnrolments ?></b><span>Enrolments</span></div>
  <div><b><?= round($avgProgress) ?>%</b><span>Average progress</span></div>
  <div><b><?= $completedCount ?></b><span>Finished a course</span></div>
</div>

<div class="st-sec" style="margin-top:32px;">
  <h2>Where your students are</h2>
  <p>Each student's latest known location. Approximate, taken from the IP address. The address itself is not kept.</p>
</div>
<?php if ($topLocations): ?>
  <div class="st-panel st-where">
    <?php foreach ($topLocations as $c): $n = (int) $c['n']; ?>
      <div class="st-loc">
        <div><strong><?= e($c['city']) ?></strong><small><?= e(country_name($c['country'])) ?></small></div>
        <div class="st-bar"><i style="width:<?= max(4, (int) round($n / $locMax * 100)) ?>%"></i></div>
        <div class="st-n"><?= number_format($n) ?> <small>student<?= $n === 1 ? '' : 's' ?></small></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="st-empty">
    <?php dash_icon('map-pin'); ?>
    <div><strong>Locations are still being worked out.</strong><br>They fill in over the next few hours as students visit. Check back later.</div>
  </div>
<?php endif; ?>

<div class="st-sec" style="margin-top:32px;"><h2>Students</h2></div>
<form method="get" class="st-bar-row">
  <label class="st-field st-grow"><?php dash_icon('search'); ?><input type="search" name="q" value="<?= e($searchQ) ?>" placeholder="Search by name or email" aria-label="Search students"></label>
  <label class="st-field">
    <select name="course" onchange="this.form.submit()" aria-label="Filter by course">
      <option value="">All courses</option>
      <?php foreach ($myCourses as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $courseFilter === (string) $c['id'] ? 'selected' : '' ?>><?= e(mb_strimwidth($c['title'], 0, 52, '…')) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <?php if ($courseFilter !== '' || $searchQ !== ''): ?><a href="<?= e(base_url('dashboard/creator/students.php')) ?>" class="small muted">Clear</a><?php endif; ?>
  <span class="st-count"><?= count($people) ?> student<?= count($people) === 1 ? '' : 's' ?></span>
</form>

<?php if ($people): ?>
  <div class="st-list">
    <?php foreach ($people as $p):
        $rows = $p['rows']; $multi = count($rows) > 1;
        $avg = (int) round(array_sum(array_map(fn($r) => (float) $r['progress'], $rows)) / count($rows));
        $line = function (array $r): string {
            $pct = (int) round((float) $r['progress']); $sub = $r['source'] === 'SUBSCRIPTION';
            return '<div class="st-crs"><div class="st-t" title="' . e($r['course_title']) . '">' . e($r['course_title']) . '</div>'
                . '<div class="st-prog"><div class="st-track"><i class="' . ($pct >= 100 ? 'full' : '') . '" style="width:' . min(100, $pct) . '%"></i></div><span>' . $pct . '%</span></div>'
                . '<span class="st-pill' . ($sub ? ' sub' : '') . '">' . ($sub ? 'Subscription' : 'Purchased') . '</span>'
                . '<div class="st-d">' . e(format_date($r['enrolled_at'])) . '</div></div>';
        };
    ?>
      <article class="st-card">
        <div class="st-who">
          <div class="st-id">
            <div class="st-av">
              <?php if ($p['avatar']): ?><img src="<?= e(asset_src($p['avatar'])) ?>" alt="">
              <?php else: ?><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?><?php endif; ?>
            </div>
            <div style="min-width:0;">
              <div class="st-nm"><strong><?= e($p['name']) ?></strong>
                <?php if ($p['guest']): ?><span class="st-chip guest">Guest</span><?php endif; ?>
                <?php if ($multi): ?><span class="st-chip multi"><?= count($rows) ?> courses</span><?php endif; ?>
              </div>
              <div class="st-em"><?= e($p['email'] ?: '—') ?></div>
            </div>
          </div>
          <div class="st-meta"><b><?= $multi ? $avg . '% average' : ($avg >= 100 ? 'Finished' : $avg . '% done') ?></b>Last enrolled <?= e(format_date($rows[0]['enrolled_at'])) ?></div>
        </div>
        <div class="st-courses">
          <?php foreach (array_slice($rows, 0, $showFirst) as $r) echo $line($r); ?>
          <?php if (count($rows) > $showFirst): ?>
            <details class="st-more">
              <summary><span class="st-open">Show <?= count($rows) - $showFirst ?> more courses</span><span class="st-close">Show fewer</span></summary>
              <?php foreach (array_slice($rows, $showFirst) as $r) echo $line($r); ?>
            </details>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card card-pad" style="text-align:center; margin-top:18px; border-style:dashed;">
    <p class="muted"><?= ($courseFilter !== '' || $searchQ !== '') ? 'No students match that search.' : "You don't have any students yet." ?></p>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
