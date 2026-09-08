<?php
require __DIR__ . '/includes/bootstrap.php';

$schools = db_all(
    "SELECT u.id, u.name, u.slug, u.avatar_url, u.school_banner_url, u.headline,
            COUNT(DISTINCT c.id) AS course_count,
            COUNT(e.id) AS student_count
     FROM users u
     JOIN courses c ON c.creator_id = u.id AND c.status = 'PUBLISHED'
     LEFT JOIN enrollments e ON e.course_id = c.id
     WHERE u.role IN ('CREATOR', 'ADMIN')
     GROUP BY u.id
     ORDER BY student_count DESC, course_count DESC"
);

$totalStudents = 0;
foreach ($schools as $s) $totalStudents += (int) $s['student_count'];

$pageTitle = 'Schools — Obin Academy';
$pageDescription = 'Browse every creator\'s School on Obin Academy — each one a home for all of that creator\'s courses in one place.';
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero browse-hero page-hero-light">
  <div class="container" style="max-width:820px; text-align:center;">
    <span class="pill">Browse by Creator</span>
    <h1 style="text-align:center;">Find a School</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto; text-align:center;">
      Every creator on Obin Academy runs their own School — a home for all their courses in one place. Find the one that fits what you want to learn.
    </p>
    <div class="browse-hero-stats">
      <span><strong><?= count($schools) ?>+</strong> schools</span>
      <span class="dot">&middot;</span>
      <span><strong><?= number_format($totalStudents) ?>+</strong> students</span>
    </div>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <?php if ($schools): ?>
    <div class="grid sm:grid-2 lg:grid-3" style="row-gap:48px;">
      <?php foreach ($schools as $s): $slug = $s['slug'] ?: ensure_creator_slug((int) $s['id']); ?>
        <a href="<?= e(base_url('school.php?slug=' . $slug)) ?>" class="creator-card">
          <div class="banner" <?php if ($s['school_banner_url']): ?>style="background-image:url('<?= e(asset_src($s['school_banner_url'])) ?>'); background-size:cover; background-position:center;"<?php endif; ?>></div>
          <div class="avatar">
            <?php if ($s['avatar_url']): ?><img src="<?= e(asset_src($s['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($s['name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div class="body">
            <h3><?= e($s['name']) ?>'s School</h3>
            <?php if ($s['headline']): ?><p class="headline"><?= e($s['headline']) ?></p><?php endif; ?>
            <div class="stats-row">
              <div class="stat"><span class="value"><?= (int) $s['course_count'] ?></span><span class="label">Course<?= (int) $s['course_count'] === 1 ? '' : 's' ?></span></div>
              <div class="stat"><span class="value"><?= (int) $s['student_count'] ?></span><span class="label">Student<?= (int) $s['student_count'] === 1 ? '' : 's' ?></span></div>
            </div>
            <span class="view-btn">Visit School →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-browse">
      <div class="empty-browse-icon">🎓</div>
      <h3 class="h3">No Schools yet</h3>
      <p class="muted" style="margin-top:8px;">Check back soon — creators are just getting started.</p>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
