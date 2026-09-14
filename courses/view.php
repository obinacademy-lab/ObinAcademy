<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/enroll_panel.php';
require __DIR__ . '/../includes/enrollment.php';
require __DIR__ . '/../includes/subscriptions.php';

$slug = query_param('slug');
$course = get_course_by_slug($slug);
$user = current_user();

$isOwner = $user && (int) $user['id'] === (int) $course['creator_user_id'];
$isAdmin = $user && $user['role'] === 'ADMIN';
$canPreview = $isOwner || $isAdmin;

if (!$course || ($course['status'] !== 'PUBLISHED' && !$canPreview)) {
    http_response_code(404);
    $pageTitle = 'Course Not Found — Obin Academy';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="container" style="padding:80px 0; text-align:center;"><h1 class="h2">Course not found</h1><p class="muted" style="margin-top:10px;">This course doesn\'t exist or isn\'t published yet.</p></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$isEnrolled = $user
    ? (bool) db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$user['id'], $course['id']])
    : (bool) guest_enrollment_for_course((int) $course['id']);

// Every course is locked to an active subscriber (or admin/owner) — the one
// exception is a grandfathered one-time buyer, kept to just the specific
// course they already own, not the whole catalog (courses/index.php has no
// such exception). Checked before the view counter/share-attribution below
// so a locked-out visitor's redirect doesn't inflate either — they never
// actually saw the course.
if (!$canPreview && !$isEnrolled && !($user && user_has_active_subscription((int) $user['id']))) {
    redirect('/subscribe.php');
}

// A plain, publicly-shown view counter — not deduped per visitor, and
// excludes the course's own creator/admin so their own checks don't
// inflate the number learners see.
if ($course['status'] === 'PUBLISHED' && !$isOwner && !$isAdmin) {
    db_run('UPDATE courses SET view_count = view_count + 1 WHERE id = ?', [$course['id']]);
    $course['view_count']++;
}

// If this visit arrived via a tracked share link (?ref=<token>), attribute
// it back to that exact share so the admin "Course Shares" page can tell a
// direct single-recipient share from one that's been passed around further.
// Silently does nothing for a bad/missing/foreign token — never breaks the
// page over what's purely an analytics side-effect.
$refToken = query_param('ref');
if ($refToken && preg_match('/^[a-f0-9]{12}$/', $refToken)) {
    $share = db_one('SELECT id FROM course_shares WHERE share_token = ? AND course_id = ?', [$refToken, $course['id']]);
    if ($share) {
        db_run('INSERT INTO course_share_visits (share_id, visitor_id) VALUES (?, ?)', [$share['id'], ensure_visitor_id()]);
    }
}

$totalLessons = 0;
foreach ($course['modules'] as $m) $totalLessons += count($m['lessons']);

$statusLabel = ['DRAFT' => 'a draft', 'PENDING_REVIEW' => 'pending admin review', 'REJECTED' => 'rejected and needs changes'];

$pageTitle = $course['title'] . ' — Obin Academy';
$pageDescription = mb_strimwidth(preg_replace('/\s+/', ' ', trim($course['summary'])), 0, 160, '…');
if (!empty($course['thumbnail_url'])) $pageImage = asset_src($course['thumbnail_url']);
$pageType = 'website';
// Never crawlable content anymore — a non-subscriber never reaches this
// point (redirected above), so this page has nothing to offer a search
// engine's index. Also no structured data: it would only ever be seen by
// an already-subscribed, logged-in visitor, never a crawler.
$noindex = true;

$stats = get_platform_stats();

require __DIR__ . '/../includes/header.php';
?>

<?php if ($course['status'] !== 'PUBLISHED'): ?>
  <div style="background:#fbbf24; color:#78350f; text-align:center; font-size:12.5px; font-weight:700; padding:10px 20px;">
    Preview only — this course is <?= e($statusLabel[$course['status']] ?? strtolower($course['status'])) ?> and not visible to learners yet.
  </div>
<?php endif; ?>

<section class="course-hero course-hero-centered">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container">
    <nav class="breadcrumb reveal">
      <a href="<?= e(base_url('/')) ?>">Home</a>
      <?php dash_icon('chevron-right'); ?>
      <a href="<?= e(base_url('courses/index.php')) ?>">Courses</a>
      <?php dash_icon('chevron-right'); ?>
      <a href="<?= e(base_url('courses/index.php?category=' . $course['category_slug'])) ?>"><?= e($course['category_name']) ?></a>
    </nav>

    <div class="reveal reveal-delay-1">
      <span class="pill"><?php dash_icon('tag'); ?><?= e($course['category_name']) ?></span>
      <h1><?= e($course['title']) ?></h1>
      <p class="summary"><?= e($course['summary']) ?></p>

      <div class="meta-row">
        <span class="meta-chip"><?php dash_icon('users'); ?><?= (int) $course['student_count'] ?> students</span>
        <span class="meta-chip"><?php dash_icon('eye'); ?><?= number_format((int) $course['view_count']) ?> view<?= (int) $course['view_count'] === 1 ? '' : 's' ?></span>
        <span class="meta-chip"><?php dash_icon('play'); ?><?= $totalLessons ?> lessons</span>
        <?php if (!empty($course['reviewed_at'])): ?>
          <span class="meta-chip"><?php dash_icon('calendar'); ?>Published <?= e(format_date($course['reviewed_at'])) ?> at <?= e(date('g:i A', strtotime($course['reviewed_at']))) ?></span>
        <?php endif; ?>
      </div>

      <div class="row gap-2 wrap" style="align-items:center; margin-top:26px;">
        <a href="<?= e(base_url('profile.php?id=' . $course['creator_user_id'])) ?>" class="instructor">
          <div class="avatar">
            <?php if (!empty($course['creator_avatar_url'])): ?>
              <img src="<?= e(asset_src($course['creator_avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($course['creator_name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div>
            <div class="name"><?= e($course['creator_name']) ?></div>
            <div class="headline"><?= e($course['creator_headline'] ?: 'Instructor') ?></div>
          </div>
        </a>
        <?php render_share_button(base_url('courses/view.php?slug=' . $course['slug']), $course['title'], 'Share Course', 'dark', (int) $course['id'], 'Share this course'); ?>
      </div>
    </div>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<section class="section">
  <div class="container grid lg:grid-3" style="gap:48px; align-items:start;">
    <div style="grid-column: span 2;" class="reveal reveal-delay-1 course-content">
      <h2 class="h3">About This Course</h2>
      <div class="muted course-description"><?= format_rich_text($course['description']) ?></div>

      <div class="row between wrap gap-2" style="margin-top:48px; align-items:baseline;">
        <h2 class="h3">Curriculum</h2>
        <div class="curriculum-stat"><strong><?= count($course['modules']) ?></strong> module<?= count($course['modules']) === 1 ? '' : 's' ?> &middot; <strong><?= $totalLessons ?></strong> lesson<?= $totalLessons === 1 ? '' : 's' ?></div>
      </div>
      <div class="timeline">
        <?php foreach ($course['modules'] as $mi => $module): ?>
          <div class="tmod reveal reveal-delay-<?= min($mi + 1, 5) ?>">
            <div class="tmod-num"><?= $mi + 1 ?></div>
            <details class="tmod-card" <?= $mi === 0 ? 'open' : '' ?>>
              <summary class="tmod-summary">
                <span class="tmod-title"><?= e($module['title']) ?></span>
                <span class="tmod-count"><?= count($module['lessons']) ?> lesson<?= count($module['lessons']) === 1 ? '' : 's' ?></span>
                <?php dash_icon('chevron-down', 'tmod-chevron'); ?>
              </summary>
              <div class="tmod-body-outer"><div class="tmod-body-inner">
                <?php foreach ($module['lessons'] as $lesson): ?>
                  <div class="tlesson">
                    <span class="tlesson-icon"><?php dash_icon($lesson['type'] === 'VIDEO' ? 'play' : 'file-text'); ?></span>
                    <span><?= e($lesson['title']) ?></span>
                    <span class="tlesson-dur">
                      <?php if (!empty($lesson['duration'])): $d = (int) $lesson['duration']; ?>
                        <?= sprintf('%d:%02d', intdiv($d, 60), $d % 60) ?>
                      <?php else: ?>
                        <?= $lesson['type'] === 'VIDEO' ? 'Video' : 'PDF' ?>
                      <?php endif; ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div></div>
            </details>
          </div>
        <?php endforeach; ?>
      </div>

    </div>

    <aside class="course-sidebar">
      <?php render_enroll_panel($course, $isOwner, $isEnrolled); ?>
    </aside>
  </div>
</section>

<script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
