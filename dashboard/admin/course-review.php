<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/community.php';
$user = require_role(['ADMIN']);

$courseId = (int) query_param('id');
$course = db_one('SELECT c.*, u.name AS creator_name FROM courses c JOIN users u ON u.id=c.creator_id WHERE c.id=?', [$courseId]);
if (!$course) { http_response_code(404); exit('Course not found'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('_action');
    if ($action === 'approve') {
        db_run("UPDATE courses SET status='PUBLISHED', reviewed_at=NOW(), rejection_reason=NULL WHERE id=?", [$courseId]);
        create_course_community($courseId);
        log_admin_action((int) $user['id'], $user['name'], 'course.approved', 'Course', $course['title']);
        flash_set('success', 'Course approved and published.');
    } elseif ($action === 'reject') {
        $reason = post('rejectionReason');
        db_run("UPDATE courses SET status='REJECTED', reviewed_at=NOW(), rejection_reason=? WHERE id=?", [$reason ?: 'No reason given.', $courseId]);
        log_admin_action((int) $user['id'], $user['name'], 'course.rejected', 'Course', $course['title'], $reason);
        flash_set('success', 'Course rejected.');
    }
    redirect('/dashboard/admin/index.php');
}

$modules = db_all('SELECT * FROM modules WHERE course_id = ? ORDER BY sort_order ASC', [$courseId]);
foreach ($modules as &$m) $m['lessons'] = db_all('SELECT * FROM lessons WHERE module_id = ? ORDER BY sort_order ASC', [$m['id']]);
unset($m);

$pageTitle = 'Review Course — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2"><?= e($course['title']) ?></h1>
<p class="muted" style="margin-top:6px;">by <?= e($course['creator_name']) ?> &middot; <?= e(format_money((float) $course['price'])) ?></p>

<div class="card card-pad" style="margin-top:20px;">
  <h3 class="small" style="font-weight:700; text-transform:uppercase; color:var(--muted);">Summary</h3>
  <p style="margin-top:8px;"><?= e($course['summary']) ?></p>
  <h3 class="small" style="margin-top:20px; font-weight:700; text-transform:uppercase; color:var(--muted);">Description</h3>
  <p style="margin-top:8px; white-space:pre-line;"><?= e($course['description']) ?></p>
</div>

<h2 class="h3" style="margin-top:28px;">Curriculum</h2>
<div class="stack gap-2" style="margin-top:14px;">
  <?php foreach ($modules as $mi => $module): ?>
    <div class="module-block">
      <div class="head">Module <?= $mi + 1 ?>: <?= e($module['title']) ?></div>
      <ul>
        <?php foreach ($module['lessons'] as $lesson): ?>
          <li><?= $lesson['type'] === 'VIDEO' ? '▶' : '📄' ?> <?= e($lesson['title']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid sm:grid-2" style="margin-top:28px; gap:16px;">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="approve"><button class="btn btn-primary btn-block">✓ Approve & Publish</button></form>
  <details class="card">
    <summary class="card-pad" style="cursor:pointer; font-weight:700; color:var(--danger); list-style:none;">✕ Reject</summary>
    <form method="post" class="card-pad" style="border-top:1px solid var(--border);">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="reject">
      <textarea name="rejectionReason" rows="3" placeholder="Explain what needs to change..." required></textarea>
      <button class="btn btn-outline btn-block" style="margin-top:10px; color:var(--danger); border-color:var(--danger);">Confirm Rejection</button>
    </form>
  </details>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
