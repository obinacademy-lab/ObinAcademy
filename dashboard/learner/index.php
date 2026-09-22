<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/course_card.php';
$user = require_login();

$enrollments = db_all('
    SELECT e.*, c.title, c.slug, c.thumbnail_url, c.category_id, cat.name AS category_name,
      u.name AS creator_name, cert.code AS certificate_code,
      (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS lesson_count
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN categories cat ON cat.id = c.category_id
    JOIN users u ON u.id = c.creator_id
    LEFT JOIN certificates cert ON cert.enrollment_id = e.id
    WHERE e.user_id = ?
    ORDER BY e.enrolled_at DESC
', [$user['id']]);

$completedCount = 0; $inProgressCount = 0; $certificateCount = 0;
$continuing = [];
foreach ($enrollments as $en) {
    $p = (float) $en['progress'];
    if ($p >= 100) $completedCount++;
    elseif ($p > 0) { $inProgressCount++; $continuing[] = $en; }
    if ($en['certificate_code']) $certificateCount++;
}
$enrolledCount = count($enrollments);

// Recommended: published courses in the same categories the learner is
// already studying, excluding anything they're already enrolled in — falls
// back to featured courses for a brand-new learner with no enrollments yet.
$enrolledCourseIds = array_column($enrollments, 'course_id');
$enrolledCategoryIds = array_values(array_unique(array_column($enrollments, 'category_id')));
$recommended = [];
if ($enrolledCategoryIds) {
    $catPlaceholders = implode(',', array_fill(0, count($enrolledCategoryIds), '?'));
    $exclPlaceholders = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
    $recommended = get_course_cards(
        "c.category_id IN ($catPlaceholders) AND c.id NOT IN ($exclPlaceholders)",
        array_merge($enrolledCategoryIds, $enrolledCourseIds),
        'student_count DESC, c.created_at DESC',
        3
    );
}
if (!$recommended) $recommended = get_featured_courses(3);

$firstName = explode(' ', trim($user['name']))[0];
$quotes = [
    'Small steps, every day, add up to real change.',
    'The expert in anything was once a beginner.',
    'Discipline beats motivation when motivation runs out.',
    'Every lesson you finish is a skill you keep for life.',
    'Progress, not perfection.',
];
$quote = $quotes[(int) date('z') % count($quotes)];

// "Almost there" nudge: whichever in-progress course the learner is
// furthest through — a real, data-driven prompt to come back and finish it,
// with lessons-remaining estimated from their lesson count and % progress.
$nextUp = null;
foreach ($continuing as $en) {
    if (!$nextUp || (float) $en['progress'] > (float) $nextUp['progress']) $nextUp = $en;
}
$lessonsLeft = $nextUp ? max(0, (int) round((float) $nextUp['lesson_count'] * (1 - (float) $nextUp['progress'] / 100))) : 0;

$pageTitle = 'My Learning — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-hero reveal">
  <div>
    <h1 class="h2" style="color:#fff;">Welcome back, <?= e($firstName) ?></h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72); font-style:italic;">"<?= e($quote) ?>"</p>
  </div>
  <div class="row gap-2" style="flex-wrap:wrap;">
    <?php if ($nextUp): ?>
      <a href="<?= e(base_url('learn.php?slug=' . $nextUp['slug'])) ?>" class="btn btn-gold" style="border-radius:999px;">▶ Resume Learning</a>
    <?php endif; ?>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-outline" style="border-radius:999px;">Browse Courses</a>
  </div>
</div>

<div class="grid md:grid-2 lg:grid-4" style="margin-top:24px; gap:16px;">
  <div class="stat-card reveal" style="background:linear-gradient(135deg,#ec4899,#be185d); border:none; color:#fff;"><div class="label" style="color:rgba(255,255,255,0.85);">Enrolled Courses</div><div class="value" style="color:#fff;" data-count-up data-count-value="<?= $enrolledCount ?>" data-count-suffix="">0</div></div>
  <div class="stat-card reveal reveal-delay-1" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9); border:none; color:#fff;"><div class="label" style="color:rgba(255,255,255,0.85);">Completed</div><div class="value" style="color:#fff;" data-count-up data-count-value="<?= $completedCount ?>" data-count-suffix="">0</div></div>
  <div class="stat-card reveal reveal-delay-2" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8); border:none; color:#fff;"><div class="label" style="color:rgba(255,255,255,0.85);">In Progress</div><div class="value" style="color:#fff;" data-count-up data-count-value="<?= $inProgressCount ?>" data-count-suffix="">0</div></div>
  <div class="stat-card reveal reveal-delay-3" style="background:linear-gradient(135deg,#f5b301,#c98e00); border:none; color:#3d2600;"><div class="label" style="color:#5c3d00;">Certificates</div><div class="value" style="color:#3d2600;" data-count-up data-count-value="<?= $certificateCount ?>" data-count-suffix="">0</div></div>
</div>

<?php if ($continuing): ?>
  <h3 class="dash-section-label" style="margin-top:36px;">Continue Learning</h3>
  <div class="continue-track" style="margin-top:14px;">
    <?php foreach ($continuing as $i => $en): ?>
      <div class="continue-card reveal reveal-delay-<?= min($i + 1, 5) ?>">
        <div class="continue-thumb">
          <?php if ($en['thumbnail_url']): ?><img src="<?= e(asset_src($en['thumbnail_url'])) ?>" alt="">
          <?php else: ?><div class="placeholder">Obin Academy</div><?php endif; ?>
        </div>
        <div class="continue-body">
          <div class="cat"><?= e($en['category_name']) ?></div>
          <h3><?= e($en['title']) ?></h3>
          <p class="muted small" style="margin-top:2px;">by <?= e($en['creator_name']) ?></p>
          <div class="continue-progress">
            <div class="progress-track"><div class="progress-fill" style="width:<?= round((float) $en['progress']) ?>%;"></div></div>
            <span><?= round((float) $en['progress']) ?>%</span>
          </div>
          <p class="muted small" style="margin-top:8px;"><?= (int) $en['lesson_count'] ?> lesson<?= (int) $en['lesson_count'] === 1 ? '' : 's' ?> total</p>
          <a href="<?= e(base_url('learn.php?slug=' . $en['slug'])) ?>" class="btn btn-primary" style="margin-top:14px;">▶ Resume Learning</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="row between wrap gap-2" style="margin-top:36px; align-items:baseline;">
  <h3 class="dash-section-label">My Courses</h3>
</div>
<?php if (!$enrollments): ?>
  <div class="card card-pad" style="margin-top:14px; text-align:center; border-style:dashed;">
    <p class="muted">You haven't enrolled in any courses yet.</p>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Browse Courses</a>
  </div>
<?php else: ?>
  <div class="my-courses-toolbar" style="margin-top:14px;" data-mycourses-toolbar>
    <div class="search-pill" style="max-width:320px; padding:6px 6px 6px 16px;">
      <?php dash_icon('search'); ?>
      <input type="text" placeholder="Search your courses…" data-mycourses-search>
    </div>
    <select class="role-select" style="width:auto; border:1px solid var(--border);" data-mycourses-category>
      <option value="">All Categories</option>
      <?php foreach (array_unique(array_column($enrollments, 'category_name')) as $catName): ?>
        <option value="<?= e($catName) ?>"><?= e($catName) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="role-select" style="width:auto; border:1px solid var(--border);" data-mycourses-sort>
      <option value="recent">Recently Enrolled</option>
      <option value="progress-high">Progress: High to Low</option>
      <option value="progress-low">Progress: Low to High</option>
      <option value="az">Title: A–Z</option>
    </select>
  </div>

  <div class="grid sm:grid-2 lg:grid-3" style="margin-top:18px;" data-mycourses-grid>
    <?php foreach ($enrollments as $i => $en): ?>
      <div class="enrolled-course-card reveal reveal-delay-<?= min($i % 5 + 1, 5) ?>" data-title="<?= e(mb_strtolower($en['title'])) ?>" data-category="<?= e($en['category_name']) ?>" data-progress="<?= (float) $en['progress'] ?>" data-enrolled="<?= e($en['enrolled_at']) ?>">
        <div class="ecc-thumb">
          <?php if ($en['thumbnail_url']): ?><img src="<?= e(asset_src($en['thumbnail_url'])) ?>" alt="">
          <?php else: ?><div class="placeholder">Obin Academy</div><?php endif; ?>
          <?php if ((float) $en['progress'] >= 100): ?><span class="ecc-done-badge"><?php dash_icon('check-circle'); ?> Completed</span><?php endif; ?>
        </div>
        <div class="ecc-body">
          <div class="cat"><?= e($en['category_name']) ?></div>
          <h3><?= e($en['title']) ?></h3>
          <p class="muted small" style="margin-top:4px;">by <?= e($en['creator_name']) ?></p>
          <div class="continue-progress" style="margin-top:12px;">
            <div class="progress-track"><div class="progress-fill" style="width:<?= round((float) $en['progress']) ?>%;"></div></div>
            <span><?= round((float) $en['progress']) ?>%</span>
          </div>
          <div class="row gap-2" style="margin-top:14px;">
            <a href="<?= e(base_url('learn.php?slug=' . $en['slug'])) ?>" class="btn btn-primary btn-sm" style="flex:1;">
              <?= (float) $en['progress'] > 0 ? '▶ Continue' : '▶ Start' ?>
            </a>
            <?php if ($en['certificate_code']): ?>
              <a href="<?= e(base_url('certificate.php?code=' . $en['certificate_code'])) ?>" target="_blank" rel="noopener" class="btn btn-gold btn-sm">🎓</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted small" style="margin-top:14px; display:none;" data-mycourses-empty>No courses match your search.</p>
<?php endif; ?>

<?php if ($recommended): ?>
  <h3 class="dash-section-label" style="margin-top:40px;">Recommended For You</h3>
  <div class="grid sm:grid-2 lg:grid-3" style="margin-top:14px;">
    <?php foreach ($recommended as $c) render_course_card($c); ?>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:40px;">Quick Actions</h3>
<div class="quick-actions">
  <a href="<?= e(base_url('courses/index.php')) ?>" class="quick-action">
    <span class="qa-icon" style="--tint:#2563eb;"><?php dash_icon('book-open'); ?></span>
    <span class="qa-text">Browse Courses</span>
    <?php dash_icon('arrow-right', 'qa-arrow'); ?>
  </a>
  <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="quick-action">
    <span class="qa-icon" style="--tint:#8b5cf6;"><?php dash_icon('settings'); ?></span>
    <span class="qa-text">Account Settings</span>
    <?php dash_icon('arrow-right', 'qa-arrow'); ?>
  </a>
  <a href="<?= e(base_url('contact.php')) ?>" class="quick-action">
    <span class="qa-icon" style="--tint:#06b6d4;"><?php dash_icon('quote'); ?></span>
    <span class="qa-text">Support Center</span>
    <?php dash_icon('arrow-right', 'qa-arrow'); ?>
  </a>
</div>

<div class="dash-quote-block">
  <p>"Success is built one lesson at a time. Keep learning. Keep growing."</p>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
