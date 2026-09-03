<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$postId = (int) query_param('id');
$user = current_user();
$post = get_post($postId, $user ? (int) $user['id'] : null);
if (!$post) { http_response_code(404); exit('Post not found'); }

$community = db_one('SELECT * FROM communities WHERE id = ?', [$post['community_id']]);
if (!$community) { http_response_code(404); exit('Community not found'); }
$communityId = (int) $community['id'];

$isMember = $user && is_community_member($communityId, (int) $user['id']);
$isModerator = $user && user_can_moderate_community($communityId, (int) $user['id']);
$redirectBack = '/community/post.php?id=' . $postId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    csrf_verify();
    $action = post('_action');

    if ($action === 'create_comment') {
        if (!$isMember) { flash_set('error', 'Join this community before commenting.'); redirect($redirectBack); }
        $body = post('body');
        $parentId = post('parent_comment_id') !== '' ? (int) post('parent_comment_id') : null;
        $mentionIds = array_map('intval', $_POST['mention_ids'] ?? []);
        if ($body === '') { flash_set('error', 'Write a comment first.'); redirect($redirectBack); }
        create_comment($postId, (int) $user['id'], $body, $parentId, $mentionIds);
        redirect($redirectBack . '#comments');
    } elseif ($action === 'delete_comment') {
        delete_comment((int) post('comment_id'), $communityId, (int) $user['id']);
        redirect($redirectBack . '#comments');
    } elseif ($action === 'toggle_pin') {
        toggle_post_pin($postId, $communityId, (int) $user['id']);
        redirect($redirectBack);
    } elseif ($action === 'delete_post') {
        delete_post($postId, $communityId, (int) $user['id']);
        flash_set('success', 'Post deleted.');
        redirect('/community/view.php?slug=' . $community['slug']);
    }
    redirect($redirectBack);
}

$comments = get_post_comments($postId, $user ? (int) $user['id'] : null);
$members = get_community_members($communityId, 200);

$pageTitle = mb_substr(strip_tags($post['body']), 0, 60) . ' — ' . $community['name'] . ' — Obin Academy';
$pageDescription = mb_substr(strip_tags($post['body']), 0, 155);
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:720px; padding-top:32px; padding-bottom:72px;">
  <a href="<?= e(base_url('community/view.php?slug=' . $community['slug'])) ?>" class="feed-action-btn" style="margin-bottom:16px; display:inline-flex;">← Back to <?= e($community['name']) ?></a>

  <?php render_post_card($post, $communityId, $user, $isModerator, true); ?>

  <div id="comments" style="margin-top:28px;">
    <h2 class="h3"><?= (int) $post['comment_count'] ?> Comment<?= (int) $post['comment_count'] === 1 ? '' : 's' ?></h2>

    <?php if ($isMember): ?>
      <form method="post" class="feed-comment-form" style="margin-top:14px;">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="create_comment">
        <textarea name="body" placeholder="Write a comment… use @ to mention a member" data-mentionable data-members="<?= mentionable_members_json($members) ?>" required></textarea>
        <button type="submit" class="btn btn-primary" style="flex-shrink:0;">Comment</button>
      </form>
    <?php elseif ($user): ?>
      <p class="card card-pad muted small" style="margin-top:14px; border-style:dashed;">Join this community to comment.</p>
    <?php else: ?>
      <p class="card card-pad muted small" style="margin-top:14px; border-style:dashed;">
        <a href="<?= e(base_url('login.php?redirect=' . urlencode($redirectBack))) ?>" style="color:var(--accent); font-weight:600;">Log in</a> to join the discussion.
      </p>
    <?php endif; ?>

    <div class="feed-comments" style="margin-top:20px;">
      <?php if (!$comments): ?>
        <p class="muted small">No comments yet. Start the conversation.</p>
      <?php else: ?>
        <?php foreach ($comments as $comment): render_comment_node($comment, $communityId, $user, $isModerator, $members); endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/community.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
