<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/gifts.php';
$user = require_login();

$gifts = get_gifts_sent_by_buyer((int) $user['id']);

$pageTitle = 'Gifts Sent — Obin Academy';
require __DIR__ . '/../includes/dashboard_header.php';
?>
<h1 class="h2">Gifts Sent</h1>
<p class="muted" style="margin-top:6px;">Courses you've paid for as a gift for someone else.</p>

<?php if (!$gifts): ?>
  <div class="card card-pad" style="text-align:center; margin-top:24px; border-style:dashed;">
    <p class="muted">You haven't gifted a course yet.</p>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Browse Courses to Gift</a>
  </div>
<?php else: ?>
  <div class="table-wrap" style="margin-top:24px;">
    <table>
      <thead>
        <tr><th>Course</th><th>Recipient</th><th>Sent</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($gifts as $gift): ?>
          <tr>
            <td style="font-weight:700;"><a href="<?= e(base_url('courses/view.php?slug=' . $gift['course_slug'])) ?>" style="color:var(--ink);"><?= e($gift['course_title']) ?></a></td>
            <td><?= e($gift['recipient_name']) ?><br><span class="small muted"><?= e($gift['recipient_email']) ?></span></td>
            <td><?= e(format_date($gift['created_at'])) ?></td>
            <td>
              <?php if ($gift['status'] === 'CLAIMED'): ?>
                <span class="badge badge-published">Claimed <?= e(format_date($gift['claimed_at'])) ?></span>
              <?php else: ?>
                <span class="badge badge-pending">Not Yet Claimed</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
