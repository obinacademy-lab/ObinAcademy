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
  <div class="activity-feed" style="margin-top:24px;">
    <?php foreach ($gifts as $gift): ?>
      <div class="list-row">
        <div class="list-row-main">
          <div style="min-width:0;">
            <a href="<?= e(base_url('courses/view.php?slug=' . $gift['course_slug'])) ?>" style="color:var(--ink); font-weight:700; display:block;"><?= e($gift['course_title']) ?></a>
            <div class="small muted" style="margin-top:2px;"><?= e($gift['recipient_name']) ?> &middot; <?= e($gift['recipient_email']) ?> &middot; sent <?= e(format_date($gift['created_at'])) ?></div>
          </div>
        </div>
        <div class="list-row-meta">
          <?php if ($gift['status'] === 'CLAIMED'): ?>
            <span class="badge badge-published">Claimed <?= e(format_date($gift['claimed_at'])) ?></span>
          <?php else: ?>
            <span class="badge badge-pending">Not Yet Claimed</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
