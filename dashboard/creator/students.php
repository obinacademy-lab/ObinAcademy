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

$pageTitle = 'My Students — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">My Students</h1>
<p class="muted" style="margin-top:6px;">Everyone enrolled across your courses — this list is private to you, never shown publicly.</p>

<form method="get" class="row gap-2" style="margin-top:20px; max-width:320px;">
  <select name="course" onchange="this.form.submit()" style="width:100%;">
    <option value="">All courses (<?= count($allStudents) ?> students)</option>
    <?php foreach ($myCourses as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $courseFilter === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
    <?php endforeach; ?>
  </select>
</form>

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
