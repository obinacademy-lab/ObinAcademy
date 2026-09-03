<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = require_login();
$conversations = get_user_conversations((int) $user['id']);

$pageTitle = 'Messages — Obin Academy';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:640px; padding-top:32px; padding-bottom:72px;">
  <h1 class="h2">Messages</h1>

  <?php if (!$conversations): ?>
    <div class="feed-empty" style="margin-top:20px;">No conversations yet. Visit a member's profile and click "Message" to start one.</div>
  <?php else: ?>
    <div class="card card-pad" style="margin-top:20px; padding:6px;">
      <?php foreach ($conversations as $c): ?>
        <a href="<?= e(base_url('messages/view.php?id=' . $c['conversation_id'])) ?>" class="member-directory-row" style="padding:14px 10px;">
          <div class="avatar-circle" style="width:42px; height:42px; font-size:15px;">
            <?php if ($c['other_user_avatar_url']): ?><img src="<?= e(asset_src($c['other_user_avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($c['other_user_name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div style="min-width:0; flex:1;">
            <div class="name"><?= e($c['other_user_name']) ?></div>
            <div class="sub" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
              <?= $c['last_message'] ? e(mb_strimwidth($c['last_message'], 0, 60, '…')) : 'No messages yet' ?>
            </div>
          </div>
          <div style="text-align:right; flex-shrink:0;">
            <?php if ($c['last_message_at']): ?><div class="small muted"><?= time_ago($c['last_message_at']) ?></div><?php endif; ?>
            <?php if ((int) $c['unread_count'] > 0): ?><span class="site-notif-dot" style="position:static; display:inline-block; margin-top:4px; border-color:#fff;"></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
