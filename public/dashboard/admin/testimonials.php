<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/audit.php';
$user = require_role(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) post('testimonialId');
    $action = post('_action');
    $t = db_one('SELECT * FROM testimonials WHERE id = ?', [$id]);
    if ($t && $action === 'approve') {
        db_run("UPDATE testimonials SET status='PUBLISHED', reviewed_at=NOW(), rejection_reason=NULL WHERE id=?", [$id]);
        log_admin_action((int) $user['id'], $user['name'], 'testimonial.approved', 'Testimonial', substr($t['quote'], 0, 60));
    } elseif ($t && $action === 'reject') {
        db_run("UPDATE testimonials SET status='REJECTED', reviewed_at=NOW() WHERE id=?", [$id]);
        log_admin_action((int) $user['id'], $user['name'], 'testimonial.rejected', 'Testimonial', substr($t['quote'], 0, 60));
    }
    redirect('/dashboard/admin/testimonials.php');
}

$pending = db_all("SELECT t.*, u.name AS author_name FROM testimonials t JOIN users u ON u.id=t.author_id WHERE t.status='PENDING_REVIEW' ORDER BY t.created_at ASC");
$published = db_all("SELECT t.*, u.name AS author_name FROM testimonials t JOIN users u ON u.id=t.author_id WHERE t.status='PUBLISHED' ORDER BY t.reviewed_at DESC");

$pageTitle = 'Stories — Admin — Obin Academy';
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<h1 class="h2">Stories</h1>

<h2 class="h3" style="margin-top:24px;">Pending Review (<?= count($pending) ?>)</h2>
<div class="stack gap-2" style="margin-top:14px;">
  <?php foreach ($pending as $t): ?>
    <div class="card card-pad">
      <div class="row gap-1"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></div>
      <p style="margin-top:8px;">"<?= e($t['quote']) ?>"</p>
      <p class="small muted" style="margin-top:6px;">— <?= e($t['author_name']) ?></p>
      <div class="row gap-2" style="margin-top:12px;">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="approve"><input type="hidden" name="testimonialId" value="<?= (int) $t['id'] ?>"><button class="btn btn-primary btn-sm">Approve</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="reject"><input type="hidden" name="testimonialId" value="<?= (int) $t['id'] ?>"><button class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);">Reject</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p class="muted small">Nothing pending review.</p><?php endif; ?>
</div>

<h2 class="h3" style="margin-top:36px;">Published</h2>
<div class="stack gap-2" style="margin-top:14px;">
  <?php foreach ($published as $t): ?>
    <div class="card card-pad">
      <div class="row gap-1" style="color:var(--gold);"><?= str_repeat('★', (int) $t['rating']) . str_repeat('☆', 5 - (int) $t['rating']) ?></div>
      <p style="margin-top:8px;">"<?= e($t['quote']) ?>"</p>
      <p class="small muted" style="margin-top:6px;">— <?= e($t['author_name']) ?></p>
    </div>
  <?php endforeach; ?>
  <?php if (!$published): ?><p class="muted small">No published stories yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
