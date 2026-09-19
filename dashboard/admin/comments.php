<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/comments.php';
$user = require_role(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $commentId = (int) post('commentId');
    $action = post('_action');

    if ($action === 'restore') {
        db_run("UPDATE comments SET status = 'VISIBLE', hidden_reason = NULL WHERE id = ?", [$commentId]);
        flash_set('success', 'Comment restored — it\'s visible on the page again.');
    } elseif ($action === 'delete') {
        db_run('DELETE FROM comments WHERE id = ?', [$commentId]);
        flash_set('success', 'Comment permanently deleted.');
    }
    redirect('/dashboard/admin/comments.php');
}

$hiddenComments = db_all(
    "SELECT c.*, u.name AS author_name, u.email AS author_email, co.title AS course_title, co.slug AS course_slug
     FROM comments c
     JOIN users u ON u.id = c.user_id
     JOIN courses co ON co.id = c.course_id
     WHERE c.status = 'HIDDEN'
     ORDER BY c.created_at DESC"
);

$pageTitle = 'Comments — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Comments</h1>
<p class="muted" style="margin-top:6px;">Comments automatically hidden for containing blocked language — review and restore any false positive, or delete for good.</p>

<?php if (!$hiddenComments): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;">
    <p class="muted">No hidden comments right now.</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:20px;">
    <?php foreach ($hiddenComments as $c): ?>
      <div class="list-row" style="align-items:flex-start;">
        <div class="list-row-main" style="align-items:flex-start;">
          <span class="activity-dot tone-danger" style="flex-shrink:0; margin-top:2px;"><?php dash_icon('x-circle'); ?></span>
          <div style="min-width:0;">
            <div style="font-size:13.5px; line-height:1.5;"><?= e(mb_strimwidth($c['body'], 0, 200, '…')) ?></div>
            <div class="small muted" style="margin-top:6px;">
              <?= e($c['author_name']) ?> &middot;
              <a href="<?= e(base_url('courses/view.php?slug=' . $c['course_slug'])) ?>" target="_blank" rel="noopener" style="color:inherit;"><?= e($c['course_title']) ?></a>
              &middot; <?= e($c['hidden_reason']) ?> &middot; <?= e(format_date($c['created_at'])) ?>
            </div>
          </div>
        </div>
        <div class="list-row-meta">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="commentId" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="_action" value="restore">
            <button type="submit" class="btn btn-outline btn-sm">Restore</button>
          </form>
          <form method="post" onsubmit="return confirm('Permanently delete this comment?');">
            <?= csrf_field() ?>
            <input type="hidden" name="commentId" value="<?= (int) $c['id'] ?>">
            <input type="hidden" name="_action" value="delete">
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:var(--danger);">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
