<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$profileId = (int) query_param('id');
$profile = $profileId ? get_profile($profileId) : null;
if (!$profile) { http_response_code(404); exit('Profile not found'); }

$user = current_user();
$isMe = $user && (int) $user['id'] === $profileId;
$isCreator = in_array($profile['role'], ['CREATOR', 'ADMIN'], true);

$teachingSummary = $isCreator ? get_courses_teaching($profileId, 50) : [];
$stats = [
    'completed' => get_courses_completed_count($profileId),
    'teaching' => count($teachingSummary),
    // Total enrollments across this creator's own published courses — the
    // same "own students" figure a course page already shows next to its
    // creator (creator_student_count in get_course_by_slug()), surfaced
    // here too so a creator's public profile reads as their own school:
    // their own courses, taught to their own students.
    'students' => array_sum(array_column($teachingSummary, 'student_count')),
];
// The catalog is public now — anyone can browse a creator's course cards,
// not just subscribers. Watching still requires a subscription, checked
// independently on learn.php/stream.php.
$teaching = $isCreator ? get_course_cards('c.creator_id = ?', [$profileId], 'c.created_at DESC', 6) : [];
// No school_cover_url of their own yet — borrow a thumbnail from one of
// their own published courses instead of showing a bare flat hero.
$schoolCoverUrl = $isCreator ? ($profile['school_cover_url'] ?: get_creator_fallback_thumbnail($profileId)) : null;

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
<?php if ($isCreator): ?>
  <section class="school-hero">
    <?php if (!empty($schoolCoverUrl)): ?>
      <div class="school-hero-cover"><img src="<?= e(asset_src($schoolCoverUrl)) ?>" alt=""></div>
    <?php endif; ?>
    <div class="container school-hero-inner">
      <span class="school-hero-byline">
        <span class="school-hero-avatar">
          <?php if ($profile['avatar_url']): ?><img src="<?= e(asset_src($profile['avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($profile['name'], 0, 1)) ?><?php endif; ?>
        </span>
        by <?= e($profile['name']) ?><?php if ($profile['role'] === 'ADMIN'): ?> · Admin<?php endif; ?>
      </span>
      <h1><?= e($profile['school_name'] ?: ($profile['name'] . "'s School")) ?></h1>
      <?php if ($profile['headline']): ?><p class="school-hero-headline"><?= e($profile['headline']) ?></p><?php endif; ?>
      <div class="school-hero-stats">
        <div class="stat"><span class="value"><?= number_format($stats['teaching']) ?></span><span class="label">Course<?= $stats['teaching'] === 1 ? '' : 's' ?></span></div>
        <div class="stat"><span class="value"><?= number_format($stats['students']) ?></span><span class="label">Student<?= $stats['students'] === 1 ? '' : 's' ?></span></div>
      </div>
      <?php if ($isMe): ?>
        <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="school-hero-edit-btn">Edit Your School</a>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($profile['bio'] || array_filter($socials)): ?>
    <div class="container" style="max-width:700px; padding-top:40px; text-align:center;">
      <?php if ($profile['bio']): ?><div class="profile-bio" style="margin:0 auto; max-width:none; text-align:left;"><?= nl2br(e($profile['bio'])) ?></div><?php endif; ?>
      <?php render_social_links($socials); ?>
    </div>
  <?php endif; ?>

  <div class="container" style="max-width:900px; padding-top:40px; padding-bottom:80px;">
    <?php if ($stats['teaching'] > 0): ?>
      <div class="profile-section" style="margin-top:0;">
        <div class="profile-section-head">
          <h2><?= e($profile['name']) ?>'s Courses</h2>
          <span class="count"><?= number_format($stats['teaching']) ?> course<?= $stats['teaching'] === 1 ? '' : 's' ?></span>
        </div>
        <?php if ($teaching): ?>
          <div class="grid sm:grid-2 lg:grid-3">
            <?php foreach ($teaching as $c) render_course_card($c); ?>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="card card-pad" style="text-align:center; border-style:dashed;">
        <p class="muted"><?= e($profile['name']) ?> hasn't published any courses yet.</p>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="container" style="max-width:900px; padding-top:40px; padding-bottom:80px;">
    <div class="profile-hero">
      <div class="profile-hero-top">
        <div class="profile-avatar">
          <?php if ($profile['avatar_url']): ?><img src="<?= e(asset_src($profile['avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($profile['name'], 0, 1)) ?><?php endif; ?>
        </div>
        <div class="profile-identity">
          <div class="profile-name-row">
            <h1><?= e($profile['name']) ?></h1>
          </div>
          <?php if ($profile['headline']): ?><p class="profile-headline"><?= e($profile['headline']) ?></p><?php endif; ?>
        </div>
        <?php if ($isMe): ?>
          <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="profile-edit-btn">Edit Profile</a>
        <?php endif; ?>
      </div>

      <?php if ($profile['bio']): ?><div class="profile-bio"><?= nl2br(e($profile['bio'])) ?></div><?php endif; ?>

      <?php render_social_links($socials); ?>

      <?php if ($profile['role'] === 'LEARNER' || $stats['completed'] > 0): ?>
        <div class="profile-stats-row">
          <div class="stat"><span class="value"><?= number_format($stats['completed']) ?></span><span class="label">Completed</span></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
