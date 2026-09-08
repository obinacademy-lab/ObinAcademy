<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$slug = query_param('slug');
$creator = $slug ? db_one("SELECT * FROM users WHERE slug = ? AND role IN ('CREATOR','ADMIN')", [$slug]) : null;
if (!$creator) { http_response_code(404); exit('School not found'); }

$courses = get_course_cards('c.creator_id = ?', [$creator['id']]);

$stats = db_one(
    "SELECT COUNT(DISTINCT c.id) AS course_count, COUNT(e.id) AS student_count
     FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id
     WHERE c.creator_id = ? AND c.status = 'PUBLISHED'",
    [$creator['id']]
);
$courseCount = (int) $stats['course_count'];
$studentCount = (int) $stats['student_count'];

$socials = [
    'facebook' => $creator['facebook_url'],
    'instagram' => $creator['instagram_url'],
    'youtube' => $creator['youtube_url'],
    'tiktok' => $creator['tiktok_url'],
    'linkedin' => $creator['linkedin_url'],
];

$schoolName = $creator['name'] . "'s School";
$pageTitle = $schoolName . ' — Obin Academy';
$pageDescription = $creator['headline'] ?: ($creator['bio'] ? mb_strimwidth($creator['bio'], 0, 155, '…') : 'Courses from ' . $creator['name'] . ' on Obin Academy.');
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:1100px; padding-top:32px; padding-bottom:72px;">
  <div class="school-hero">
    <?php if ($creator['school_banner_url']): ?>
      <div class="school-banner">
        <img src="<?= e(asset_src($creator['school_banner_url'])) ?>" alt="">
      </div>
    <?php endif; ?>
    <div class="card card-pad profile-hero<?= $creator['school_banner_url'] ? ' has-banner' : '' ?>">
    <div class="profile-avatar">
      <?php if ($creator['avatar_url']): ?><img src="<?= e(asset_src($creator['avatar_url'])) ?>" alt="">
      <?php else: ?><?= e(mb_substr($creator['name'], 0, 1)) ?><?php endif; ?>
    </div>
    <div style="flex:1; min-width:220px;">
      <div class="profile-name-row">
        <h1><?= e($schoolName) ?></h1>
      </div>
      <?php if ($creator['headline']): ?><p class="profile-headline"><?= e($creator['headline']) ?></p><?php endif; ?>
      <?php if ($creator['bio']): ?><p class="profile-bio"><?= nl2br(e($creator['bio'])) ?></p><?php endif; ?>

      <?php render_social_links($socials); ?>

      <div class="profile-actions">
        <?php render_share_button(base_url('school.php?slug=' . $creator['slug']), $schoolName, 'Share School', 'light'); ?>
      </div>
    </div>

    <div class="profile-stats-row" style="width:100%;">
      <div class="stat"><span class="value"><?= number_format($courseCount) ?></span><span class="label">Course<?= $courseCount === 1 ? '' : 's' ?></span></div>
      <div class="stat"><span class="value"><?= number_format($studentCount) ?></span><span class="label">Student<?= $studentCount === 1 ? '' : 's' ?></span></div>
    </div>
    </div>
  </div>

  <?php if ($courses): ?>
    <h2 class="h3" style="margin-top:36px;">Courses</h2>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:16px;">
      <?php foreach ($courses as $c) render_course_card($c); ?>
    </div>
  <?php else: ?>
    <div class="empty-browse" style="margin-top:36px;">
      <div class="empty-browse-icon">🎓</div>
      <h3 class="h3">No courses published yet</h3>
      <p class="muted" style="margin-top:8px;">Check back soon — <?= e($creator['name']) ?> is working on it.</p>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
