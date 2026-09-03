<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    mark_all_notifications_read((int) $user['id']);
    redirect('/notifications.php');
}

$notifications = get_user_notifications((int) $user['id'], 50);

$pageTitle = 'Notifications — Obin Academy';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:600px; padding-top:32px; padding-bottom:72px;">
  <div class="row between" style="align-items:center;">
    <h1 class="h2">Notifications</h1>
    <?php if (array_filter($notifications, fn($n) => !$n['is_read'])): ?>
      <form method="post"><?= csrf_field() ?>
        <button type="submit" class="site-notif-mark-read" style="font-size:13px;">Mark all read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$notifications): ?>
    <div class="feed-empty" style="margin-top:20px;">No notifications yet — activity on your posts, comments, and profile will show up here.</div>
  <?php else: ?>
    <div class="card card-pad" style="margin-top:20px; padding:6px;">
      <?php foreach ($notifications as $n): ?>
        <a href="<?= e(base_url(ltrim($n['link_url'] ?? 'notifications.php', '/'))) ?>" class="site-notif-row" style="padding:14px 12px; border-radius:10px; <?= $n['is_read'] ? '' : 'background:rgba(37,99,235,0.05);' ?>">
          <p style="font-size:14px;"><?= e($n['message']) ?></p>
          <span style="font-size:12px;"><?= e(time_ago($n['created_at'])) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
