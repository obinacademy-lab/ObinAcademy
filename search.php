<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/community.php';

$q = trim(query_param('q'));
$user = current_user();

$communities = $q !== '' ? search_communities($q, 12) : [];
$members = $q !== '' ? search_users($q, 12) : [];
$posts = $q !== '' ? search_posts($q, 15) : [];

$pageTitle = ($q !== '' ? "\"$q\" — Search" : 'Search') . ' — Obin Academy';
$noindex = true;
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:720px; padding-top:32px; padding-bottom:72px;">
  <h1 class="h2">Search</h1>
  <form method="get" class="field-icon" style="margin-top:16px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search communities, members, and posts…" autofocus>
  </form>

  <?php if ($q === ''): ?>
    <p class="muted small" style="margin-top:20px;">Search across communities, members, and posts.</p>
  <?php elseif (!$communities && !$members && !$posts): ?>
    <div class="feed-empty" style="margin-top:20px;">Nothing matches “<?= e($q) ?>”.</div>
  <?php else: ?>

    <?php if ($communities): ?>
      <h2 class="h3" style="margin-top:32px;">Communities</h2>
      <div class="profile-compact-list card card-pad" style="margin-top:12px;">
        <?php foreach ($communities as $c): ?>
          <a href="<?= e(base_url('community/view.php?slug=' . $c['slug'])) ?>" class="profile-compact-row">
            <div class="thumb" style="display:flex; align-items:center; justify-content:center; font-size:18px;"><?= $c['type'] === 'creator' ? '👤' : '🎓' ?></div>
            <div>
              <div class="title"><?= e($c['name']) ?></div>
              <div class="sub"><?= number_format((int) $c['member_count']) ?> member<?= (int) $c['member_count'] === 1 ? '' : 's' ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($members): ?>
      <h2 class="h3" style="margin-top:32px;">Members</h2>
      <div class="card card-pad" style="margin-top:12px; padding:6px;">
        <?php foreach ($members as $m): ?>
          <a href="<?= e(base_url('profile.php?id=' . $m['id'])) ?>" class="member-directory-row" style="padding:12px 10px;">
            <div class="avatar-circle" style="width:38px; height:38px; font-size:14px;">
              <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
            </div>
            <div>
              <div class="name"><?= e($m['name']) ?></div>
              <div class="sub"><?= e($m['headline'] ?: ucfirst(strtolower($m['role']))) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($posts): ?>
      <h2 class="h3" style="margin-top:32px;">Posts</h2>
      <div class="feed-post-list" style="margin-top:12px;">
        <?php foreach ($posts as $p): ?>
          <a href="<?= e(base_url('community/post.php?id=' . $p['id'])) ?>" class="feed-post" style="display:block; text-decoration:none; color:inherit;">
            <div class="feed-post-head">
              <div class="avatar-circle" style="width:32px; height:32px; font-size:12.5px;">
                <?php if ($p['author_avatar_url']): ?><img src="<?= e(asset_src($p['author_avatar_url'])) ?>" alt="">
                <?php else: ?><?= e(mb_substr($p['author_name'], 0, 1)) ?><?php endif; ?>
              </div>
              <div class="meta">
                <div class="name-row"><span class="author"><?= e($p['author_name']) ?></span></div>
                <div class="sub">in <?= e($p['community_name']) ?> · <?= time_ago($p['created_at']) ?></div>
              </div>
            </div>
            <div class="feed-post-body" style="margin-top:10px;"><?= e(mb_strimwidth($p['body'], 0, 200, '…')) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
