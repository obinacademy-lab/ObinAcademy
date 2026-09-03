<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';
require __DIR__ . '/../includes/storage.php';

$slug = query_param('slug');
$community = get_community_by_slug($slug);
if (!$community) { http_response_code(404); exit('Community not found'); }
$communityId = (int) $community['id'];

$user = current_user();
$isMember = $user && is_community_member($communityId, (int) $user['id']);
$isModerator = $user && user_can_moderate_community($communityId, (int) $user['id']);

$categorySlug = query_param('category');
$categories = get_community_categories($communityId);
$activeCategory = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) { $activeCategory = $cat; break; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    csrf_verify();
    $action = post('_action');
    $redirectBack = '/community/view.php?slug=' . $slug . ($categorySlug ? '&category=' . urlencode($categorySlug) : '');

    if ($action === 'join') {
        join_community($communityId, (int) $user['id']);
        redirect($redirectBack);
    } elseif ($action === 'leave') {
        leave_community($communityId, (int) $user['id']);
        redirect($redirectBack);
    } elseif ($action === 'create_post') {
        if (!$isModerator) { flash_set('error', "Only this community's creator or moderators can start a new post."); redirect($redirectBack); }

        $body = post('body');
        $type = post('type', 'post');
        $categoryId = post('category_id') !== '' ? (int) post('category_id') : null;
        $linkUrl = post('link_url');
        $pollOptions = array_filter(array_map('trim', $_POST['poll_options'] ?? []));
        $mentionIds = array_map('intval', $_POST['mention_ids'] ?? []);

        if ($body === '') { flash_set('error', 'Write something before posting.'); redirect($redirectBack); }
        if ($type === 'poll' && count($pollOptions) < 2) { flash_set('error', 'A poll needs at least 2 options.'); redirect($redirectBack); }
        if ($linkUrl !== '' && !preg_match('#^https?://.+#i', $linkUrl)) { flash_set('error', 'Link must be a full URL starting with http:// or https://'); redirect($redirectBack); }

        $imageUrl = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imageUrl = save_upload($_FILES['image'], 'community');
            } catch (Throwable $e) {
                flash_set('error', $e->getMessage());
                redirect($redirectBack);
            }
        }

        create_post($communityId, (int) $user['id'], $type, $body, $categoryId, $imageUrl, $linkUrl ?: null, $pollOptions, $mentionIds);
        flash_set('success', 'Posted to the community.');
        redirect($redirectBack);
    } elseif ($action === 'toggle_pin') {
        toggle_post_pin((int) post('post_id'), $communityId, (int) $user['id']);
        redirect($redirectBack);
    } elseif ($action === 'delete_post') {
        delete_post((int) post('post_id'), $communityId, (int) $user['id']);
        flash_set('success', 'Post deleted.');
        redirect($redirectBack);
    }
    redirect($redirectBack);
}

$offset = max(0, (int) query_param('offset', '0'));
$limit = 10;
$posts = get_feed_posts($communityId, $activeCategory ? (int) $activeCategory['id'] : null, $user ? (int) $user['id'] : null, $limit, $offset);
$hasMore = count($posts) === $limit;

$course = $community['course_id'] ? db_one('SELECT id, title, slug, thumbnail_url, creator_id FROM courses WHERE id = ?', [$community['course_id']]) : null;
// The course's creator's OWN community, for a "Creator Profile" link on a
// course community page — not shown when already viewing a creator
// community, since that would just link to the current page.
$creatorCommunity = ($community['type'] === 'course' && $course) ? get_community_by_creator((int) $course['creator_id']) : null;
$members = get_community_members($communityId, 200);
$previewMembers = get_community_preview_members($communityId, 6);
$leaderboard = get_community_leaderboard($communityId, 5);
$trending = (!$activeCategory && $offset === 0) ? get_trending_posts($communityId, 3) : [];
$postCount = (int) db_one('SELECT COUNT(*) AS n FROM community_posts WHERE community_id = ?', [$communityId])['n'];

$pageTitle = $community['name'] . ' — Community — Obin Academy';
$pageDescription = $community['description'] ?: ('Join the ' . $community['name'] . ' discussion on Obin Academy.');
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:820px;">
    <div class="community-header">
      <div class="avatar">
        <?php if ($community['banner_url']): ?><img src="<?= e(asset_src($community['banner_url'])) ?>" alt="">
        <?php else: ?><?= $community['type'] === 'creator' ? '👤' : '🎓' ?><?php endif; ?>
      </div>
      <span class="pill" style="margin-top:14px;"><?= $community['type'] === 'creator' ? 'Creator Community' : 'Course Community' ?></span>
      <h1 style="margin-top:14px; text-align:center;"><?= e($community['name']) ?></h1>
      <?php if ($community['description']): ?><p class="summary" style="margin-top:10px; text-align:center;"><?= e($community['description']) ?></p><?php endif; ?>

      <div class="community-stats-row">
        <div class="stat"><span class="value"><?= number_format((int) $community['member_count']) ?></span><span class="label">Member<?= (int) $community['member_count'] === 1 ? '' : 's' ?></span></div>
        <div class="stat"><span class="value"><?= count($categories) ?></span><span class="label">Categor<?= count($categories) === 1 ? 'y' : 'ies' ?></span></div>
        <div class="stat"><span class="value"><?= number_format($postCount) ?></span><span class="label">Post<?= $postCount === 1 ? '' : 's' ?></span></div>
      </div>

      <?php if ($previewMembers): ?>
        <div class="avatar-stack" style="margin-top:20px;">
          <?php foreach ($previewMembers as $m): ?>
            <div class="avatar-circle" style="width:34px; height:34px; font-size:12px;">
              <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if ((int) $community['member_count'] > count($previewMembers)): ?>
            <div class="avatar-stack-more">+<?= number_format((int) $community['member_count'] - count($previewMembers)) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="row gap-2" style="margin-top:22px;">
        <?php if (!$user): ?>
          <a href="<?= e(base_url('signup.php')) ?>" class="btn btn-primary">Sign Up to Join</a>
        <?php elseif ($isMember): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="leave">
            <button type="submit" class="btn btn-outline">✓ Member — Leave</button>
          </form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="join">
            <button type="submit" class="btn btn-primary">Join Community</button>
          </form>
        <?php endif; ?>
        <?php if ($course): ?><a href="<?= e(base_url('courses/view.php?slug=' . $course['slug'])) ?>" class="btn btn-outline">View Course</a><?php endif; ?>
        <?php if ($creatorCommunity): ?><a href="<?= e(base_url('community/view.php?slug=' . $creatorCommunity['slug'])) ?>" class="btn btn-outline">Creator Community</a><?php endif; ?>
      </div>

      <?php if ($isMember && (int) $user['xp_points'] > 0): ?>
        <div class="meta-row" style="justify-content:center; margin-top:18px;">
          <span class="meta-chip">⚡ <?= number_format((int) $user['xp_points']) ?> XP</span>
          <?php if ((int) $user['current_streak'] > 1): ?><span class="meta-chip">🔥 <?= (int) $user['current_streak'] ?>-day streak</span><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <div class="feed-layout">
    <div>
      <div class="chip-row" style="margin-bottom:18px;">
        <a href="<?= e(base_url('community/view.php?slug=' . $slug)) ?>" class="chip<?= !$activeCategory ? ' active' : '' ?>">All Posts</a>
        <?php foreach ($categories as $cat): ?>
          <a href="<?= e(base_url('community/view.php?slug=' . $slug . '&category=' . $cat['slug'])) ?>" class="chip<?= $activeCategory && $activeCategory['id'] === $cat['id'] ? ' active' : '' ?>"><?= e($cat['icon'] . ' ' . $cat['name']) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if ($trending): ?>
        <div class="trending-strip">
          <div class="trending-strip-head">🔥 Trending This Week <span class="count"><?= count($trending) ?></span></div>
          <?php foreach ($trending as $i => $t): ?>
            <a href="<?= e(base_url('community/post.php?id=' . $t['id'])) ?>" class="trending-post-row">
              <span class="trending-post-rank">#<?= $i + 1 ?></span>
              <div class="trending-post-body">
                <div class="trending-post-text"><?= e($t['body']) ?></div>
                <div class="trending-post-meta">by <?= e($t['author_name']) ?> · ❤ <?= (int) $t['like_count'] ?> · 💬 <?= (int) $t['comment_count'] ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($isModerator): render_composer($community, $categories, $members); elseif ($isMember): ?>
        <p class="card card-pad muted small" style="border-style:dashed;">Only this community's creator and moderators can start new posts — you can still comment and reply below.</p>
      <?php elseif ($user): ?>
        <p class="card card-pad muted small" style="border-style:dashed;">Join this community to comment on posts.</p>
      <?php else: ?>
        <p class="card card-pad muted small" style="border-style:dashed;">
          <a href="<?= e(base_url('login.php?redirect=' . urlencode('/community/view.php?slug=' . $slug))) ?>" style="color:var(--accent); font-weight:600;">Log in</a> to join the discussion.
        </p>
      <?php endif; ?>

      <div class="feed-post-list">
        <?php if (!$posts && $offset === 0): ?>
          <div class="feed-empty">No posts yet<?= $activeCategory ? ' in ' . e($activeCategory['name']) : '' ?>. <?= $isMember ? 'Be the first to share something.' : '' ?></div>
        <?php else: ?>
          <?php foreach ($posts as $post): render_post_card($post, $communityId, $user, $isModerator); endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ($hasMore): ?>
        <a class="feed-load-more btn btn-outline" href="<?= e(base_url('community/view.php?slug=' . $slug . ($categorySlug ? '&category=' . urlencode($categorySlug) : '') . '&offset=' . ($offset + $limit))) ?>">Load more</a>
      <?php endif; ?>
    </div>

    <div class="feed-sidebar">
      <?php if ($leaderboard): ?>
        <div class="card card-pad">
          <h3>🏆 Top Contributors</h3>
          <div style="margin-top:10px;">
            <?php foreach ($leaderboard as $i => $m): ?>
              <a href="<?= e(base_url('profile.php?id=' . $m['id'])) ?>" class="leaderboard-row">
                <span class="leaderboard-rank"><?= ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) ?></span>
                <div class="avatar-circle" style="width:30px; height:30px; font-size:11px;">
                  <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
                  <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
                </div>
                <div style="min-width:0; flex:1;">
                  <div class="leaderboard-name"><?= e($m['name']) ?></div>
                  <?php if ((int) $m['current_streak'] > 1): ?><div class="leaderboard-streak">🔥 <?= (int) $m['current_streak'] ?>-day streak</div><?php endif; ?>
                </div>
                <span class="leaderboard-xp"><?= number_format((int) $m['xp_points']) ?> XP</span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($categories): ?>
        <div class="card card-pad">
          <h3>Categories</h3>
          <div class="feed-category-list">
            <?php foreach ($categories as $cat): ?>
              <a href="<?= e(base_url('community/view.php?slug=' . $slug . '&category=' . $cat['slug'])) ?>" class="<?= $activeCategory && $activeCategory['id'] === $cat['id'] ? 'active' : '' ?>"><?= e($cat['icon'] . ' ' . $cat['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card card-pad">
        <div class="row between" style="align-items:center;">
          <h3 style="margin-bottom:0;">Members (<?= number_format((int) $community['member_count']) ?>)</h3>
          <a href="<?= e(base_url('community/members.php?slug=' . $slug)) ?>" class="small" style="color:var(--accent); font-weight:600;">See all</a>
        </div>
        <?php if ($members): ?>
          <div style="display:flex; flex-direction:column; gap:12px; margin-top:14px;">
            <?php foreach (array_slice($members, 0, 10) as $m): ?>
              <a href="<?= e(base_url('profile.php?id=' . $m['id'])) ?>" class="row gap-2" style="align-items:center; color:inherit; text-decoration:none;">
                <div class="avatar-circle" style="width:34px; height:34px; font-size:13px;">
                  <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
                  <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
                </div>
                <div style="min-width:0; flex:1;">
                  <div style="font-weight:700; font-size:13px;"><?= e($m['name']) ?></div>
                  <div class="small muted"><?= e($m['headline'] ?: ($m['role'] !== 'member' ? ucfirst($m['role']) : 'Member')) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted small">No members yet — be the first to join.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/community.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
