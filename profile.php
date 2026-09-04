<?php
require __DIR__ . '/includes/bootstrap.php';

$profileId = (int) query_param('id');
$profile = $profileId ? get_profile($profileId) : null;
if (!$profile) { http_response_code(404); exit('Profile not found'); }

$user = current_user();
$isMe = $user && (int) $user['id'] === $profileId;
$isCreator = in_array($profile['role'], ['CREATOR', 'ADMIN'], true);

$stats = [
    'completed' => get_courses_completed_count($profileId),
    'teaching' => $isCreator ? count(get_courses_teaching($profileId, 50)) : 0,
];
$teaching = $isCreator ? get_courses_teaching($profileId, 6) : [];

$socials = [
    'facebook' => $profile['facebook_url'],
    'instagram' => $profile['instagram_url'],
    'youtube' => $profile['youtube_url'],
    'tiktok' => $profile['tiktok_url'],
    'linkedin' => $profile['linkedin_url'],
];

$pageTitle = $profile['name'] . ' — Obin Academy';
$pageDescription = $profile['headline'] ?: ($profile['bio'] ? mb_strimwidth($profile['bio'], 0, 155, '…') : 'View ' . $profile['name'] . '\'s profile on Obin Academy.');
require __DIR__ . '/includes/header.php';
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
      <?php $profileCountry = AFRICAN_COUNTRIES[$profile['country']] ?? null; ?>
      <?php if ($profileCountry): ?><p class="profile-country"><?= e($profileCountry['flag']) ?> <?= e($profileCountry['name']) ?></p><?php endif; ?>
      <?php if ($profile['bio']): ?><p class="profile-bio"><?= nl2br(e($profile['bio'])) ?></p><?php endif; ?>

      <?php render_social_links($socials); ?>

      <div class="profile-actions">
        <?php if ($isMe): ?>
          <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="btn btn-outline">Edit Profile</a>
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
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
