<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$profileId = (int) query_param('id');
$profile = $profileId ? get_profile($profileId) : null;
if (!$profile) { http_response_code(404); exit('Profile not found'); }

$user = current_user();
$isMe = $user && (int) $user['id'] === $profileId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && !$isMe) {
    csrf_verify();
    $action = post('_action');
    if ($action === 'follow') {
        follow_user((int) $user['id'], $profileId);
    } elseif ($action === 'unfollow') {
        unfollow_user((int) $user['id'], $profileId);
    }
    redirect('/profile.php?id=' . $profileId);
}

$isFollowing = $user && !$isMe && is_following((int) $user['id'], $profileId);
$isCreator = in_array($profile['role'], ['CREATOR', 'ADMIN'], true);

$stats = [
    'completed' => get_courses_completed_count($profileId),
    'teaching' => $isCreator ? count(get_courses_teaching($profileId, 50)) : 0,
    'posts' => get_user_post_count($profileId),
    'followers' => get_follower_count($profileId),
    'following' => get_following_count($profileId),
];
$teaching = $isCreator ? get_courses_teaching($profileId, 6) : [];
$communities = get_profile_communities($profileId, 8);

$socials = [
    'facebook' => $profile['facebook_url'],
    'instagram' => $profile['instagram_url'],
    'youtube' => $profile['youtube_url'],
    'tiktok' => $profile['tiktok_url'],
    'linkedin' => $profile['linkedin_url'],
];

$pageTitle = $profile['name'] . ' — Obin Academy';
$pageDescription = $profile['headline'] ?: ($profile['bio'] ? mb_strimwidth($profile['bio'], 0, 155, '…') : 'View ' . $profile['name'] . '\'s profile on Obin Academy.');
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:820px; padding-top:32px; padding-bottom:72px;">
  <div class="card card-pad profile-hero">
    <div class="profile-avatar">
      <?php if ($profile['avatar_url']): ?><img src="<?= e(asset_src($profile['avatar_url'])) ?>" alt="">
      <?php else: ?><?= e(mb_substr($profile['name'], 0, 1)) ?><?php endif; ?>
    </div>
    <div style="flex:1; min-width:220px;">
      <div class="profile-name-row">
        <h1><?= e($profile['name']) ?></h1>
        <?php if ($isCreator): ?><span class="role-badge"><?= $profile['role'] === 'ADMIN' ? 'Admin' : 'Creator' ?></span><?php endif; ?>
      </div>
      <?php if ($profile['headline']): ?><p class="profile-headline"><?= e($profile['headline']) ?></p><?php endif; ?>
      <?php if ($profile['bio']): ?><p class="profile-bio"><?= nl2br(e($profile['bio'])) ?></p><?php endif; ?>

      <?php if ($profile['skills_list']): ?>
        <div class="profile-tag-group">
          <h4>Skills</h4>
          <div class="chip-row">
            <?php foreach ($profile['skills_list'] as $skill): ?><span class="profile-chip"><?= e($skill) ?></span><?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($profile['looking_for_list']): ?>
        <div class="profile-tag-group">
          <h4>Looking For</h4>
          <div class="chip-row">
            <?php foreach ($profile['looking_for_list'] as $item): ?><span class="profile-chip looking-for">🔎 <?= e($item) ?></span><?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php render_social_links($socials); ?>

      <div class="profile-actions">
        <?php if ($isMe): ?>
          <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="btn btn-outline">Edit Profile</a>
        <?php elseif ($user): ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="<?= $isFollowing ? 'unfollow' : 'follow' ?>">
            <button type="submit" class="btn <?= $isFollowing ? 'btn-outline' : 'btn-primary' ?>"><?= $isFollowing ? '✓ Following' : '+ Follow' ?></button>
          </form>
          <a href="<?= e(base_url('messages/start.php?to=' . $profileId)) ?>" class="btn btn-outline">✉ Message</a>
        <?php else: ?>
          <a href="<?= e(base_url('login.php?redirect=' . urlencode('/profile.php?id=' . $profileId))) ?>" class="btn btn-primary">Log in to Follow</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-stats-row" style="width:100%;">
      <?php if ($profile['role'] === 'LEARNER' || $stats['completed'] > 0): ?>
        <div class="stat"><span class="value"><?= number_format($stats['completed']) ?></span><span class="label">Completed</span></div>
      <?php endif; ?>
      <?php if ($isCreator): ?>
        <div class="stat"><span class="value"><?= number_format($stats['teaching']) ?></span><span class="label">Teaching</span></div>
      <?php endif; ?>
      <div class="stat"><span class="value"><?= number_format($stats['posts']) ?></span><span class="label">Posts</span></div>
      <div class="stat"><span class="value"><?= number_format($stats['followers']) ?></span><span class="label">Follower<?= $stats['followers'] === 1 ? '' : 's' ?></span></div>
      <div class="stat"><span class="value"><?= number_format($stats['following']) ?></span><span class="label">Following</span></div>
      <div class="stat"><span class="value"><?= number_format(count($communities)) ?></span><span class="label">Communit<?= count($communities) === 1 ? 'y' : 'ies' ?></span></div>
    </div>
  </div>

  <?php if ($teaching): ?>
    <div class="profile-section">
      <h2>Teaching</h2>
      <div class="profile-compact-list">
        <?php foreach ($teaching as $c): ?>
          <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" class="profile-compact-row">
            <?php if ($c['thumbnail_url']): ?><img class="thumb" src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="">
            <?php else: ?><div class="thumb"></div><?php endif; ?>
            <div>
              <div class="title"><?= e($c['title']) ?></div>
              <div class="sub"><?= (int) $c['student_count'] ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($communities): ?>
    <div class="profile-section">
      <h2>Communities</h2>
      <div class="profile-compact-list">
        <?php foreach ($communities as $c): ?>
          <a href="<?= e(base_url('community/view.php?slug=' . $c['slug'])) ?>" class="profile-compact-row">
            <?php if ($c['banner_url']): ?><img class="thumb" src="<?= e(asset_src($c['banner_url'])) ?>" alt="">
            <?php else: ?><div class="thumb" style="display:flex; align-items:center; justify-content:center; font-size:18px;"><?= $c['type'] === 'creator' ? '👤' : '🎓' ?></div><?php endif; ?>
            <div>
              <div class="title"><?= e($c['name']) ?></div>
              <div class="sub"><?= $c['type'] === 'creator' ? 'Creator Community' : 'Course Community' ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
