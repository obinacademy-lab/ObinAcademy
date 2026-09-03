<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$slug = query_param('slug');
$community = get_community_by_slug($slug);
if (!$community) { http_response_code(404); exit('Community not found'); }
$communityId = (int) $community['id'];

$q = query_param('q');
$members = search_community_members($communityId, $q, 200);

$pageTitle = 'Members — ' . $community['name'] . ' — Obin Academy';
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:680px; padding-top:32px; padding-bottom:72px;">
  <a href="<?= e(base_url('community/view.php?slug=' . $slug)) ?>" class="feed-action-btn" style="margin-bottom:10px; display:inline-flex;">← Back to <?= e($community['name']) ?></a>
  <h1 class="h2">Members</h1>
  <p class="muted small" style="margin-top:4px;"><?= number_format((int) $community['member_count']) ?> member<?= (int) $community['member_count'] === 1 ? '' : 's' ?></p>

  <form method="get" class="field-icon" style="margin-top:20px;">
    <input type="hidden" name="slug" value="<?= e($slug) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search members by name…">
  </form>

  <div class="card card-pad" style="margin-top:18px;">
    <?php if (!$members): ?>
      <p class="muted small">No members found<?= $q !== '' ? ' for “' . e($q) . '”' : '' ?>.</p>
    <?php else: ?>
      <?php foreach ($members as $m): ?>
        <a href="<?= e(base_url('profile.php?id=' . $m['id'])) ?>" class="member-directory-row">
          <div class="avatar-circle" style="width:40px; height:40px; font-size:15px;">
            <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div>
            <div class="name"><?= e($m['name']) ?></div>
            <div class="sub"><?= e($m['headline'] ?: ucfirst(strtolower($m['user_role']))) ?></div>
          </div>
          <?php if ($m['role'] !== 'member'): ?><span class="role-tag"><?= e(ucfirst($m['role'])) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
