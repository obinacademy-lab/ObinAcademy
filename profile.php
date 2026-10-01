<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';
require __DIR__ . '/includes/follows.php';
require __DIR__ . '/includes/bundles.php';

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
    'views' => array_sum(array_column($teachingSummary, 'view_count')),
];
// The catalog is public now — anyone can browse a creator's course cards,
// not just subscribers. Watching still requires a subscription, checked
// independently on learn.php/stream.php.
$teaching = $isCreator ? get_course_cards('c.creator_id = ?', [$profileId], 'c.created_at DESC', 50) : [];
$publishedBundles = $isCreator ? array_values(array_filter(get_bundles_for_creator($profileId), fn($b) => $b['status'] === 'PUBLISHED')) : [];
// No school_cover_url of their own yet — borrow a thumbnail from one of
// their own published courses instead of showing a bare flat hero.
$schoolCoverUrl = $isCreator ? ($profile['school_cover_url'] ?: get_creator_fallback_thumbnail($profileId)) : null;
$schoolLabel = $profile['school_name'] ?: $profile['name'];
$followerCount = $isCreator ? get_school_follower_count($profileId) : 0;
$isFollowing = $isCreator && $user && !$isMe && is_following_school((int) $user['id'], $profileId);

$socials = [
    'facebook' => $profile['facebook_url'],
    'instagram' => $profile['instagram_url'],
    'youtube' => $profile['youtube_url'],
    'tiktok' => $profile['tiktok_url'],
    'linkedin' => $profile['linkedin_url'],
];

// LOCAL DESIGN EXPLORATION ONLY — Direction 4, not deployed. Real data only:
// this creator's own review aggregate (across all their courses) plus their
// single best real review, used as one quiet pull-quote rather than a grid
// of testimonial cards — no invented rating or quote.
$creatorRating = $isCreator ? db_one(
    "SELECT COALESCE(AVG(r.rating), 0) AS avg, COUNT(*) AS n
     FROM reviews r JOIN courses c ON c.id = r.course_id
     WHERE c.creator_id = ?",
    [$profileId]
) : ['avg' => 0, 'n' => 0];
$bestReview = $isCreator ? db_one(
    "SELECT r.rating, r.comment, u.name AS reviewer_name
     FROM reviews r JOIN courses c ON c.id = r.course_id JOIN users u ON u.id = r.author_id
     WHERE c.creator_id = ? AND r.rating >= 4
     ORDER BY r.rating DESC, r.created_at DESC LIMIT 1",
    [$profileId]
) : null;

$pageTitle = $profile['name'] . ' — Obin Academy';
$pageDescription = $profile['headline'] ?: ($profile['bio'] ? mb_strimwidth($profile['bio'], 0, 155, '…') : 'View ' . $profile['name'] . '\'s profile on Obin Academy.');
require __DIR__ . '/includes/header.php';
?>
<?php if ($isCreator): ?>
  <section class="school-hero-cinematic"<?php if (!empty($schoolCoverUrl)): ?> style="--bg-image:url('<?= e(asset_src($schoolCoverUrl)) ?>');"<?php endif; ?>>
    <div class="school-hero-cinematic-scrim"></div>
    <div class="container school-hero-cinematic-inner">
      <span class="byline">
        <span class="avatar">
          <?php if ($profile['avatar_url']): ?><img src="<?= e(asset_src($profile['avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($profile['name'], 0, 1)) ?><?php endif; ?>
        </span>
        by <?= e($profile['name']) ?><?php if ($profile['role'] === 'ADMIN'): ?> · Admin<?php endif; ?>
      </span>
      <h1><?= e($schoolLabel) ?></h1>
      <?php if ($profile['headline']): ?><p class="headline"><?= e($profile['headline']) ?></p><?php endif; ?>
      <div class="meta-line">
        <span><?= number_format($stats['teaching']) ?> Course<?= $stats['teaching'] === 1 ? '' : 's' ?></span>
        <span class="dot">·</span>
        <span><?= number_format($stats['students']) ?> Student<?= $stats['students'] === 1 ? '' : 's' ?></span>
        <span class="dot">·</span>
        <a href="<?= e(base_url('school-followers.php?id=' . $profileId)) ?>"><?= number_format($followerCount) ?> Follower<?= $followerCount === 1 ? '' : 's' ?></a>
        <?php if ($creatorRating['n'] > 0): ?>
          <span class="dot">·</span>
          <span>★ <?= number_format((float) $creatorRating['avg'], 1) ?> (<?= number_format((int) $creatorRating['n']) ?>)</span>
        <?php endif; ?>
      </div>
      <div class="actions">
        <?php if (!$isMe): ?>
          <button type="button" class="school-hero-cinematic-follow<?= $isFollowing ? ' is-following' : '' ?>" data-follow-toggle
                  data-creator-id="<?= (int) $profileId ?>"
                  data-logged-in="<?= $user ? '1' : '0' ?>"
                  data-toggle-url="<?= e(base_url('api/toggle-school-follow.php')) ?>"
                  data-login-url="<?= e(base_url('login.php?redirect=' . urlencode('/profile.php?id=' . $profileId))) ?>">
            <span data-follow-label><?= $isFollowing ? 'Following' : 'Follow' ?></span>
          </button>
        <?php endif; ?>
        <?php render_share_button(base_url('profile.php?id=' . $profile['id']), $schoolLabel, 'Share', 'dark', null, 'Share this school'); ?>
        <?php if ($isMe): ?>
          <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="school-hero-cinematic-edit">Edit Your School</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($profile['bio'] || array_filter($socials)): ?>
    <section class="container profile-about-cinematic">
      <?php if ($profile['bio']): ?><p class="profile-bio-cinematic"><?= nl2br(e($profile['bio'])) ?></p><?php endif; ?>
      <?php if (array_filter($socials)): ?><?php render_social_links($socials); ?><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($bestReview): ?>
    <section class="pull-quote-section">
      <div class="container">
        <blockquote class="pull-quote">
          <p>"<?= e($bestReview['comment']) ?>"</p>
          <cite>— <?= e($bestReview['reviewer_name']) ?></cite>
        </blockquote>
      </div>
    </section>
  <?php endif; ?>

  <div class="container" style="max-width:1000px; padding-top:8px; padding-bottom:80px;">
    <?php if ($publishedBundles): ?>
      <div class="profile-section" style="margin-top:0;">
        <div class="profile-section-head">
          <h2>Bundles</h2>
          <span class="count"><?= count($publishedBundles) ?> bundle<?= count($publishedBundles) === 1 ? '' : 's' ?></span>
        </div>
        <div class="stack gap-2">
          <?php foreach ($publishedBundles as $b): ?>
            <a href="<?= e(base_url('bundle.php?slug=' . $b['slug'])) ?>" class="card card-pad row between wrap gap-2" style="text-decoration:none; align-items:center;">
              <div>
                <div style="font-weight:700; color:var(--ink);"><?= e($b['title']) ?></div>
                <div class="small muted"><?= (int) $b['course_count'] ?> courses</div>
              </div>
              <div style="font-weight:800; color:var(--ink);"><?= e(format_money((float) $b['price'])) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($stats['teaching'] > 0): ?>
      <div class="profile-section" style="margin-top:<?= $publishedBundles ? '44px' : '0' ?>;">
        <div class="profile-section-head">
          <h2><?= e($profile['name']) ?>'s Courses</h2>
          <span class="count"><?= number_format($stats['teaching']) ?> course<?= $stats['teaching'] === 1 ? '' : 's' ?></span>
        </div>
        <div class="grid sm:grid-2">
          <?php foreach ($teaching as $c) render_course_card($c); ?>
        </div>
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
<?php if ($isCreator): ?><script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
