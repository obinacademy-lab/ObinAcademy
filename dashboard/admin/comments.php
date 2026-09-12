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

<div class="table-wrap" style="margin-top:20px;">
  <?php if ($hiddenComments): ?>
    <table>
      <thead><tr><th>Comment</th><th>Author</th><th>On</th><th>Why</th><th>When</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($hiddenComments as $c): ?>
          <tr>
            <td style="max-width:320px;"><?= e(mb_strimwidth($c['body'], 0, 160, '…')) ?></td>
            <td class="small"><?= e($c['author_name']) ?><br><span class="muted"><?= e($c['author_email']) ?></span></td>
            <td class="small"><a href="<?= e(base_url('courses/view.php?slug=' . $c['course_slug'])) ?>" target="_blank" rel="noopener"><?= e($c['course_title']) ?></a></td>
            <td class="small muted"><?= e($c['hidden_reason']) ?></td>
            <td class="small muted"><?= e(format_date($c['created_at'])) ?></td>
            <td>
              <div class="row gap-2">
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
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="card" style="padding:36px; text-align:center; border-style:dashed; color:var(--muted);">No hidden comments right now.</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
