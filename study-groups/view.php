<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$slug = query_param('slug');
$group = get_study_group_by_slug($slug);
if (!$group) { http_response_code(404); exit('Study group not found'); }
$groupId = (int) $group['id'];

$user = current_user();
$isMember = $user && is_study_group_member($groupId, (int) $user['id']);
$isOwner = $user && (int) $group['owner_id'] === (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    csrf_verify();
    $action = post('_action');
    if ($action === 'join') {
        join_study_group($groupId, (int) $user['id']);
        redirect('/study-groups/view.php?slug=' . $slug);
    } elseif ($action === 'leave') {
        if (!leave_study_group($groupId, (int) $user['id'])) {
            flash_set('error', 'The group owner can\'t leave — delete the group instead if you no longer need it.');
        }
        redirect('/study-groups/view.php?slug=' . $slug);
    } elseif ($action === 'delete') {
        if (delete_study_group($groupId, (int) $user['id'])) {
            flash_set('success', 'Study group deleted.');
            redirect('/study-groups/index.php');
        }
        redirect('/study-groups/view.php?slug=' . $slug);
    }
}

$members = get_study_group_members($groupId, 100);
$messages = $isMember ? get_study_group_messages($groupId) : [];

$pageTitle = $group['name'] . ' — Study Group — Obin Academy';
$pageDescription = $group['description'] ?: ('Join ' . $group['name'] . ' on Obin Academy.');
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:820px; padding-top:32px; padding-bottom:72px;">
  <a href="<?= e(base_url('study-groups/index.php')) ?>" class="feed-action-btn" style="margin-bottom:10px; display:inline-flex;">← Study Groups</a>

  <div class="card card-pad">
    <div class="row between" style="align-items:flex-start; flex-wrap:wrap; gap:12px;">
      <div>
        <div class="row gap-2" style="align-items:center;">
          <h1 class="h2"><?= e($group['name']) ?></h1>
          <span class="profile-chip"><?= $group['privacy'] === 'private' ? '🔒 Private' : '🌐 Public' ?></span>
        </div>
        <?php if ($group['description']): ?><p class="muted" style="margin-top:8px; max-width:520px; line-height:1.7;"><?= nl2br(e($group['description'])) ?></p><?php endif; ?>
        <div class="small muted" style="margin-top:10px;">
          👥 <?= number_format((int) $group['member_count']) ?> member<?= (int) $group['member_count'] === 1 ? '' : 's' ?>
          <?= $group['schedule_text'] ? ' · 🗓 ' . e($group['schedule_text']) : '' ?>
        </div>
      </div>

      <div class="stack gap-2" style="align-items:flex-end;">
        <?php if (!$user): ?>
          <a href="<?= e(base_url('login.php?redirect=' . urlencode('/study-groups/view.php?slug=' . $slug))) ?>" class="btn btn-primary">Log in to Join</a>
        <?php elseif ($isOwner): ?>
          <form method="post" onsubmit="return confirm('Delete this study group? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="delete">
            <button type="submit" class="btn btn-outline">Delete Group</button>
          </form>
        <?php elseif ($isMember): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="leave">
            <button type="submit" class="btn btn-outline">✓ Member — Leave</button>
          </form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="join">
            <button type="submit" class="btn btn-primary">Join Group</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($group['meet_link'] || $group['zoom_link']): ?>
      <div class="row gap-2" style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border); flex-wrap:wrap;">
        <?php if ($group['meet_link']): ?><a href="<?= e($group['meet_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline">📹 Join Google Meet</a><?php endif; ?>
        <?php if ($group['zoom_link']): ?><a href="<?= e($group['zoom_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline">📹 Join Zoom</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="feed-layout" style="margin-top:24px;">
    <div>
      <h2 class="h3">Group Chat</h2>
      <?php if (!$isMember): ?>
        <p class="card card-pad muted small" style="margin-top:12px; border-style:dashed;">Join this group to see and take part in the chat.</p>
      <?php else: ?>
        <div class="card card-pad" style="margin-top:12px; padding:0; display:flex; flex-direction:column; height:480px;">
          <div id="group-chat-log" data-group-chat-log data-group-id="<?= $groupId ?>" data-my-user-id="<?= (int) $user['id'] ?>" style="flex:1; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:12px;">
            <?php if (!$messages): ?>
              <p class="muted small" data-chat-empty>No messages yet. Say hello 👋</p>
            <?php endif; ?>
            <?php foreach ($messages as $m): render_chat_message($m, (int) $user['id']); endforeach; ?>
          </div>
          <form id="group-chat-form" data-group-chat-form style="display:flex; gap:8px; padding:12px; border-top:1px solid var(--border);">
            <input type="text" name="body" placeholder="Message the group…" maxlength="2000" autocomplete="off" required style="margin:0;">
            <button type="submit" class="btn btn-primary" style="flex-shrink:0;">Send</button>
          </form>
        </div>
      <?php endif; ?>
    </div>

    <div class="feed-sidebar">
      <div class="card card-pad">
        <h3>Members (<?= number_format((int) $group['member_count']) ?>)</h3>
        <div style="display:flex; flex-direction:column; gap:12px; margin-top:14px;">
          <?php foreach ($members as $m): ?>
            <a href="<?= e(base_url('profile.php?id=' . $m['id'])) ?>" class="row gap-2" style="align-items:center; color:inherit; text-decoration:none;">
              <div class="avatar-circle" style="width:34px; height:34px; font-size:13px;">
                <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
                <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
              </div>
              <div style="min-width:0; flex:1;">
                <div style="font-weight:700; font-size:13px;"><?= e($m['name']) ?></div>
                <div class="small muted"><?= $m['role'] === 'owner' ? 'Owner' : e($m['headline'] ?: 'Member') ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/study-group-chat.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
