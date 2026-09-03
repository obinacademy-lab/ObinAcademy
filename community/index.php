<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$q = trim(query_param('q'));
$user = current_user();

$results = $q !== '' ? search_communities($q, 30) : [];
$featured = $q === '' ? get_featured_communities(6) : [];
$new = $q === '' ? get_new_communities(6) : [];
$yours = $user ? get_user_communities((int) $user['id']) : [];
$moduleStats = $q === '' ? get_community_module_stats() : [];

$pageTitle = 'Community — Obin Academy';
$pageDescription = 'Join the discussion — every course and every creator on Obin Academy has its own community for questions, wins, and support.';
$noindex = $q !== '';
require __DIR__ . '/../includes/header.php';

/** Renders one community card — shared by every section on this page. */
function render_community_card(array $c): void {
    $isCreator = $c['type'] === 'creator';
    $href = base_url('community/view.php?slug=' . $c['slug']);
    $isActive = !empty($c['last_post_at']) && strtotime($c['last_post_at']) >= strtotime('-24 hours');
    $previewMembers = (int) $c['member_count'] > 0 ? get_community_preview_members((int) $c['id'], 4) : [];
    ?>
    <a href="<?= e($href) ?>" class="creator-card">
      <div class="banner"<?= $c['banner_url'] ? ' style="background-image:url(' . e(asset_src($c['banner_url'])) . '); background-size:cover; background-position:center;"' : '' ?>>
        <?php if ($isActive): ?><span class="community-activity-badge">🔥 Active today</span><?php endif; ?>
      </div>
      <div class="avatar"><?= $isCreator ? '👤' : '🎓' ?></div>
      <div class="body">
        <h3><?= e($c['name']) ?></h3>
        <div class="headline"><?= $isCreator ? 'Creator Community' : 'Course Community' ?><?= $c['creator_name'] ? ' · ' . e($c['creator_name']) : '' ?></div>

        <?php if ($previewMembers): ?>
          <div class="avatar-stack" style="margin-top:12px;">
            <?php foreach ($previewMembers as $m): ?>
              <div class="avatar-circle" style="width:30px; height:30px; font-size:11px;">
                <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="">
                <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if ((int) $c['member_count'] > count($previewMembers)): ?>
              <div class="avatar-stack-more">+<?= (int) $c['member_count'] - count($previewMembers) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="stats-row">
          <div class="stat"><span class="value"><?= number_format((int) $c['member_count']) ?></span><span class="label">Member<?= (int) $c['member_count'] === 1 ? '' : 's' ?></span></div>
        </div>
        <span class="view-btn">Visit Community <span class="arrow">→</span></span>
      </div>
    </a>
    <?php
}
?>
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill">Learn Together</span>
    <h1 style="text-align:center;">Community</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto; text-align:center;">
      Every course and every creator on Obin Academy has its own community — ask questions, share wins,
      and learn alongside people on the same path as you.
    </p>
    <form method="get" class="search-pill browse-search" style="margin-top:26px;">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search communities..." value="<?= e($q) ?>">
      <button type="submit" class="btn btn-gold btn-sm">Search</button>
    </form>

    <?php if ($moduleStats): ?>
      <div class="meta-row" style="justify-content:center; margin-top:28px;">
        <span class="meta-chip">🏘️ <?= number_format($moduleStats['communities']) ?> Communit<?= $moduleStats['communities'] === 1 ? 'y' : 'ies' ?></span>
        <span class="meta-chip">👥 <?= number_format($moduleStats['members']) ?> Member<?= $moduleStats['members'] === 1 ? '' : 's' ?></span>
        <span class="meta-chip">💬 <?= number_format($moduleStats['posts']) ?> Post<?= $moduleStats['posts'] === 1 ? '' : 's' ?></span>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <?php if ($q !== ''): ?>
    <div class="browse-toolbar" style="border-bottom:none; margin-bottom:0;">
      <div class="browse-result-info">
        <strong><?= count($results) ?></strong> communit<?= count($results) === 1 ? 'y' : 'ies' ?>
        matching <a href="<?= e(base_url('community/index.php')) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a>
      </div>
    </div>
    <?php if ($results): ?>
      <div class="grid sm:grid-2 lg:grid-3" style="margin-top:24px;">
        <?php foreach ($results as $c): render_community_card($c); ?><?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-browse">
        <div class="empty-browse-icon">🔍</div>
        <h3 class="h3">No communities match your search</h3>
        <p class="muted" style="margin-top:8px;">Try a different name.</p>
      </div>
    <?php endif; ?>
  <?php else: ?>

    <a href="<?= e(base_url('study-groups/index.php')) ?>" class="card card-pad card-hover" style="display:flex; align-items:center; gap:16px; margin-bottom:32px;">
      <div style="font-size:28px; flex-shrink:0;">👥</div>
      <div style="flex:1;">
        <div style="font-weight:800; font-size:15px;">Study Groups</div>
        <div class="small muted" style="margin-top:2px;">Small groups learning together — group chat, a meeting link, and a shared schedule.</div>
      </div>
      <span class="btn btn-outline btn-sm" style="flex-shrink:0;">Browse →</span>
    </a>

    <?php if ($yours): ?>
      <h2 class="h3">Your Communities</h2>
      <div class="grid sm:grid-2 lg:grid-3" style="margin-top:16px;">
        <?php foreach ($yours as $c): render_community_card($c); ?><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($featured): ?>
      <h2 class="h3" style="margin-top:<?= $yours ? '40px' : '0' ?>;">🌟 Featured Communities</h2>
      <div class="grid sm:grid-2 lg:grid-3" style="margin-top:16px;">
        <?php foreach ($featured as $c): render_community_card($c); ?><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($new): ?>
      <h2 class="h3" style="margin-top:40px;">✨ New Communities</h2>
      <div class="grid sm:grid-2 lg:grid-3" style="margin-top:16px;">
        <?php foreach ($new as $c): render_community_card($c); ?><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$featured && !$new && !$yours): ?>
      <div class="empty-browse">
        <div class="empty-browse-icon">🏫</div>
        <h3 class="h3">No communities yet</h3>
        <p class="muted" style="margin-top:8px;">Communities appear automatically as courses launch and creators join.</p>
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-sm" style="margin-top:20px;">Browse Courses</a>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
