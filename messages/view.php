<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = require_login();
$conversationId = (int) query_param('id');

if (!is_conversation_participant($conversationId, (int) $user['id'])) {
    http_response_code(404);
    exit('Conversation not found');
}

$otherUser = get_conversation_other_user($conversationId, (int) $user['id']);
$messages = get_conversation_messages($conversationId);
mark_conversation_read($conversationId, (int) $user['id']);

$pageTitle = $otherUser['name'] . ' — Messages — Obin Academy';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:640px; padding-top:32px; padding-bottom:72px;">
  <a href="<?= e(base_url('messages/index.php')) ?>" class="feed-action-btn" style="margin-bottom:10px; display:inline-flex;">← Messages</a>

  <a href="<?= e(base_url('profile.php?id=' . $otherUser['id'])) ?>" class="row gap-2" style="align-items:center; color:inherit; text-decoration:none;">
    <div class="avatar-circle" style="width:42px; height:42px; font-size:16px;">
      <?php if ($otherUser['avatar_url']): ?><img src="<?= e(asset_src($otherUser['avatar_url'])) ?>" alt="">
      <?php else: ?><?= e(mb_substr($otherUser['name'], 0, 1)) ?><?php endif; ?>
    </div>
    <div>
      <div style="font-weight:800; font-size:16px;"><?= e($otherUser['name']) ?></div>
      <?php if ($otherUser['headline']): ?><div class="small muted"><?= e($otherUser['headline']) ?></div><?php endif; ?>
    </div>
  </a>

  <div class="card card-pad" style="margin-top:18px; padding:0; display:flex; flex-direction:column; height:520px;">
    <div data-chat-log data-chat-endpoint="<?= e(base_url('api/messages-chat.php')) ?>" data-chat-id-param="conversationId" data-chat-id="<?= $conversationId ?>" data-my-user-id="<?= (int) $user['id'] ?>" style="flex:1; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:12px;">
      <?php if (!$messages): ?>
        <p class="muted small" data-chat-empty>Say hello to <?= e(explode(' ', $otherUser['name'])[0]) ?> 👋</p>
      <?php endif; ?>
      <?php foreach ($messages as $m): render_chat_message($m, (int) $user['id']); endforeach; ?>
    </div>
    <form data-chat-form style="display:flex; gap:8px; padding:12px; border-top:1px solid var(--border);">
      <input type="text" name="body" placeholder="Message <?= e(explode(' ', $otherUser['name'])[0]) ?>…" maxlength="2000" autocomplete="off" required style="margin:0;">
      <button type="submit" class="btn btn-primary" style="flex-shrink:0;">Send</button>
    </form>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/chat-poll.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
