<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$creatorId = (int) query_param('id');
$creator = get_creator_profile($creatorId);
if (!$creator) {
    http_response_code(404);
    $pageTitle = 'Community Not Found — Obin Academy';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:80px 0; text-align:center;"><h1 class="h2">Community not found</h1><p class="muted" style="margin-top:10px;">This creator doesn\'t have a published community yet.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$courses = get_course_cards('c.creator_id = ?', [$creatorId]);

$socials = array_filter([
    'facebook' => $creator['facebook_url'],
    'instagram' => $creator['instagram_url'],
    'youtube' => $creator['youtube_url'],
    'tiktok' => $creator['tiktok_url'],
    'linkedin' => $creator['linkedin_url'],
]);
$socialIconPaths = [
    'facebook' => '<path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/>',
    'instagram' => '<path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/>',
    'youtube' => '<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.5V8.5l6.3 3.5-6.3 3.5Z"/>',
    'tiktok' => '<path d="M16.5 2h-3v13.5a2.5 2.5 0 1 1-2.5-2.5c.2 0 .4 0 .6.05V9.9a5.6 5.6 0 0 0-.6 0 5.6 5.6 0 1 0 5.6 5.6V8.4a7.4 7.4 0 0 0 4.4 1.4V6.7a4.4 4.4 0 0 1-4.5-4.4Z"/>',
    'linkedin' => '<path d="M6.9 8.4H3.6V20h3.3V8.4zM5.3 3.4a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 20h-3.3v-6.1c0-1.5-.5-2.5-1.9-2.5-1 0-1.6.7-1.9 1.4-.1.2-.1.6-.1.9V20H10s0-10.6 0-11.6h3.3v1.6c.4-.7 1.2-1.7 3-1.7 2.2 0 3.8 1.4 3.8 4.5V20z"/>',
];

$pageTitle = $creator['name'] . "'s Community — Obin Academy";
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:720px;">
    <nav class="breadcrumb reveal" style="justify-content:center;">
      <a href="<?= e(base_url('/')) ?>">Home</a>
      <?php dash_icon('chevron-right'); ?>
      <a href="<?= e(base_url('communities.php')) ?>">Communities</a>
      <?php dash_icon('chevron-right'); ?>
      <span><?= e($creator['name']) ?></span>
    </nav>

    <div class="community-header" style="margin-top:20px;">
      <div class="avatar">
        <?php if (!empty($creator['avatar_url'])): ?>
          <img src="<?= e(asset_src($creator['avatar_url'])) ?>" alt="">
        <?php else: ?><?= e(mb_substr($creator['name'], 0, 1)) ?><?php endif; ?>
      </div>
      <h1 style="margin-top:16px; text-align:center;"><?= e($creator['name']) ?>'s Community</h1>
      <?php if (!empty($creator['headline'])): ?>
        <p class="summary" style="margin-top:6px; text-align:center;"><?= e($creator['headline']) ?></p>
      <?php endif; ?>
      <?php if (!empty($creator['bio'])): ?>
        <p class="summary" style="margin-top:6px; text-align:center; max-width:560px; opacity:0.8;"><?= e($creator['bio']) ?></p>
      <?php endif; ?>

      <?php if ($socials): ?>
        <div class="creator-social" style="margin-top:16px;">
          <?php foreach ($socials as $network => $url): ?>
            <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($network)) ?>">
              <svg viewBox="0 0 24 24" fill="currentColor"><?= $socialIconPaths[$network] ?></svg>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="community-stats-row">
        <div class="stat"><span class="value"><?= (int) $creator['course_count'] ?></span><span class="label">Course<?= (int) $creator['course_count'] === 1 ? '' : 's' ?></span></div>
        <div class="stat"><span class="value"><?= (int) $creator['student_count'] ?></span><span class="label">Student<?= (int) $creator['student_count'] === 1 ? '' : 's' ?></span></div>
        <?php if ((int) $creator['review_count'] > 0): ?>
          <div class="stat"><span class="value">★ <?= number_format((float) $creator['avg_rating'], 1) ?></span><span class="label"><?= (int) $creator['review_count'] ?> Review<?= (int) $creator['review_count'] === 1 ? '' : 's' ?></span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="container" style="padding-top:40px; padding-bottom:72px;">
  <h2 class="h3" style="text-align:center;">Courses in This Community</h2>
  <div class="grid sm:grid-2 lg:grid-3" style="margin-top:24px;">
    <?php foreach ($courses as $c) render_course_card($c); ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
