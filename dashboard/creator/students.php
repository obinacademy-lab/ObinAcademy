<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
$user = require_role(['CREATOR', 'ADMIN']);

$allStudents = get_students_for_creator((int) $user['id']);

$courseFilter = query_param('course');
$students = $courseFilter !== ''
    ? array_values(array_filter($allStudents, fn($s) => (string) $s['course_id'] === $courseFilter))
    : $allStudents;

$myCourses = db_all("SELECT id, title FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);

$sourceLabel = ['PURCHASE' => 'Purchased', 'SUBSCRIPTION' => 'Subscription'];

$totalStudents = count($allStudents);
$coursesWithStudents = count(array_unique(array_column($allStudents, 'course_id')));
$avgProgress = $totalStudents ? array_sum(array_column($allStudents, 'progress')) / $totalStudents : 0;
$completedCount = count(array_filter($allStudents, fn($s) => (float) $s['progress'] >= 100));

$pageTitle = 'My Students — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-hero reveal">
  <div>
    <h1 class="h2" style="color:#fff;">My Students</h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72);">Everyone enrolled across your courses — this list is private to you, never shown publicly.</p>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:24px;">
  <div class="stat-card accent-top reveal" data-hoverable="true" style="--hover-color:#3b82f6;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= $totalStudents ?></div><div class="label">Total Students</div>
  </div>
  <div class="stat-card accent-top reveal reveal-delay-1" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('book-open'); ?></div>
    <div class="value"><?= $coursesWithStudents ?></div><div class="label">Courses With Students</div>
  </div>
  <div class="stat-card accent-top reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('clock'); ?></div>
    <div class="value"><?= round($avgProgress) ?>%</div><div class="label">Avg. Completion</div>
  </div>
  <div class="stat-card accent-top reveal reveal-delay-3" data-hoverable="true" style="--hover-color:#34d399;">
    <div class="icon"><?php dash_icon('check-circle'); ?></div>
    <div class="value"><?= $completedCount ?></div><div class="label">Completed the Course</div>
  </div>
</div>

<div class="chart-card" style="margin-top:28px; padding:18px 20px;">
  <form method="get" class="row gap-2 wrap" style="align-items:center;">
    <select name="course" onchange="this.form.submit()" style="width:auto; flex:1 1 auto; min-width:0; max-width:340px;">
      <option value="">All courses (<?= $totalStudents ?> students)</option>
      <?php foreach ($myCourses as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $courseFilter === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($courseFilter !== ''): ?><a href="<?= e(base_url('dashboard/creator/students.php')) ?>" class="small muted">Clear</a><?php endif; ?>
    <span class="muted small" style="margin-left:auto; white-space:nowrap;"><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?> shown</span>
  </form>
</div>

<?php if ($students): ?>
  <div class="activity-feed" style="margin-top:20px;">
    <?php foreach ($students as $s): ?>
      <div class="list-row">
        <div class="list-row-main">
          <div class="profile-avatar" style="width:36px; height:36px; font-size:13px; flex-shrink:0;">
            <?php if ($s['learner_avatar_url']): ?><img src="<?= e(asset_src($s['learner_avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($s['learner_name'] ?: '?', 0, 1)) ?><?php endif; ?>
          </div>
          <div style="min-width:0;">
            <div style="font-weight:700;"><?= e($s['learner_name'] ?: 'Unknown') ?><?= !$s['learner_user_id'] ? ' <span class="badge badge-draft" style="margin-left:4px;">Guest</span>' : '' ?></div>
            <div class="small muted" style="margin-top:2px;"><?= e($s['learner_email'] ?: '—') ?> &middot; <?= e($s['course_title']) ?> &middot; <?= number_format((float) $s['progress'], 0) ?>% complete</div>
          </div>
        </div>
        <div class="list-row-meta">
          <span class="badge badge-published"><?= e($sourceLabel[$s['source']] ?? $s['source']) ?></span>
          <span class="small muted"><?= e(format_date($s['enrolled_at'])) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card card-pad" style="text-align:center; margin-top:24px; border-style:dashed;">
    <p class="muted"><?= $courseFilter !== '' ? 'No students enrolled in this course yet.' : "You don't have any students yet." ?></p>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
