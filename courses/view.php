<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/enroll_panel.php';
require __DIR__ . '/../includes/enrollment.php';

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

$totalLessons = 0;
foreach ($course['modules'] as $m) $totalLessons += count($m['lessons']);

$reviewCount = count($course['reviews']);
$avgRating = 0;
$ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if ($reviewCount > 0) {
    $sum = 0;
    foreach ($course['reviews'] as $r) {
        $sum += (int) $r['rating'];
        $ratingBreakdown[(int) $r['rating']]++;
    }
    $avgRating = $sum / $reviewCount;
}
$myReview = null;
if ($user) {
    foreach ($course['reviews'] as $r) {
        if ((int) $r['author_id'] === (int) $user['id']) { $myReview = $r; break; }
    }
}

$creatorSocials = [
    'facebook' => $course['creator_facebook_url'] ?? null,
    'instagram' => $course['creator_instagram_url'] ?? null,
    'youtube' => $course['creator_youtube_url'] ?? null,
    'tiktok' => $course['creator_tiktok_url'] ?? null,
    'linkedin' => $course['creator_linkedin_url'] ?? null,
];

$statusLabel = ['DRAFT' => 'a draft', 'PENDING_REVIEW' => 'pending admin review', 'REJECTED' => 'rejected and needs changes'];

$pageTitle = $course['title'] . ' — Obin Academy';
$pageDescription = mb_strimwidth(preg_replace('/\s+/', ' ', trim($course['summary'])), 0, 160, '…');
if (!empty($course['thumbnail_url'])) $pageImage = asset_src($course['thumbnail_url']);
$pageType = 'website';
$noindex = $course['status'] !== 'PUBLISHED';

$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'Course',
    'name' => $course['title'],
    'description' => $pageDescription,
    'provider' => [
        '@type' => 'Organization',
        'name' => 'Obin Academy',
        'sameAs' => base_url('index.php'),
    ],
    'url' => base_url('courses/view.php?slug=' . $course['slug']),
];
if (!empty($course['thumbnail_url'])) $structuredData['image'] = asset_src($course['thumbnail_url']);
if ((float) $course['price'] > 0) {
    $structuredData['offers'] = [
        '@type' => 'Offer',
        'price' => number_format((float) ($course['sale_price'] ?? $course['price']), 2, '.', ''),
        'priceCurrency' => 'UGX',
        'url' => base_url('courses/view.php?slug=' . $course['slug']),
        'availability' => 'https://schema.org/InStock',
    ];
}
if ($reviewCount > 0) {
    $structuredData['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($avgRating, 1),
        'reviewCount' => $reviewCount,
    ];
}
if (!empty($course['creator_name'])) {
    $structuredData['hasCourseInstance'] = [
        '@type' => 'CourseInstance',
        'courseMode' => 'online',
        'instructor' => ['@type' => 'Person', 'name' => $course['creator_name']],
    ];
}

require __DIR__ . '/../includes/header.php';
?>

<?php if ($course['status'] !== 'PUBLISHED'): ?>
  <div style="background:#fbbf24; color:#78350f; text-align:center; font-size:12.5px; font-weight:700; padding:10px 20px;">
    Preview only — this course is <?= e($statusLabel[$course['status']] ?? strtolower($course['status'])) ?> and not visible to learners yet.
  </div>
<?php endif; ?>

<section class="course-hero">
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
        <span class="meta-chip">
          <?php dash_icon('star'); ?>
          <?php if ($reviewCount > 0): ?>
            <?= number_format($avgRating, 1) ?> (<?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?>)
          <?php else: ?>
            No reviews yet
          <?php endif; ?>
        </span>
        <span class="meta-chip"><?php dash_icon('users'); ?><?= (int) $course['student_count'] ?> students</span>
        <span class="meta-chip"><?php dash_icon('play'); ?><?= $totalLessons ?> lessons</span>
      </div>

      <div class="row gap-2 wrap" style="align-items:center; margin-top:26px;">
        <a href="#instructor-card" class="instructor">
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
        <?php render_share_button(base_url('courses/view.php?slug=' . $course['slug']), $course['title']); ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container course-detail-grid">
    <div class="reveal reveal-delay-1">
      <h2 class="h3">About This Course</h2>
      <p class="muted" style="margin-top:14px; line-height:1.75; white-space:pre-line;"><?= e($course['description']) ?></p>

      <h2 class="h3" style="margin-top:48px;">Curriculum</h2>
      <div class="curriculum-list" style="margin-top:16px;">
        <?php foreach ($course['modules'] as $mi => $module): ?>
          <details class="module-block" <?= $mi === 0 ? 'open' : '' ?>>
            <summary>
              <span class="module-num"><?= $mi + 1 ?></span>
              <span class="module-title"><?= e($module['title']) ?></span>
              <span class="module-count"><?= count($module['lessons']) ?> lesson<?= count($module['lessons']) === 1 ? '' : 's' ?></span>
              <?php dash_icon('chevron-down', 'module-chevron'); ?>
            </summary>
            <ul>
              <?php foreach ($module['lessons'] as $lesson): ?>
                <li>
                  <?php dash_icon($lesson['type'] === 'VIDEO' ? 'play' : 'file-text', 'lesson-icon'); ?>
                  <span><?= e($lesson['title']) ?></span>
                  <?php if (!empty($lesson['duration'])): $d = (int) $lesson['duration']; ?>
                    <span class="lesson-duration"><?= sprintf('%d:%02d', intdiv($d, 60), $d % 60) ?></span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
      </div>

      <h2 class="h3" style="margin-top:48px;">Reviews<?= $reviewCount > 0 ? " ($reviewCount)" : '' ?></h2>

      <?php if ($reviewCount > 0): ?>
        <div class="rating-summary">
          <div style="text-align:center;">
            <div class="big-num"><?= number_format($avgRating, 1) ?></div>
            <div class="big-stars"><?= str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) ?></div>
            <div class="big-count"><?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></div>
          </div>
          <div class="bars">
            <?php for ($star = 5; $star >= 1; $star--): $count = $ratingBreakdown[$star]; $pct = $reviewCount > 0 ? round($count / $reviewCount * 100) : 0; ?>
              <div class="rating-bar-row">
                <span class="label"><?= $star ?> star</span>
                <span class="rating-bar-track"><span class="rating-bar-fill" style="width:<?= $pct ?>%;"></span></span>
                <span class="count"><?= $count ?></span>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($isEnrolled && $user): ?>
        <div style="margin-top:16px;" data-review-form data-course-id="<?= (int) $course['id'] ?>" data-submit-url="<?= e(base_url('api/submit-review.php')) ?>">
          <form data-review-submit class="card card-pad stack gap-2">
            <label>Your Rating</label>
            <div class="row gap-1" data-star-input style="font-size:22px; cursor:pointer; color: var(--gold);">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <span data-star="<?= $i ?>"><?= $myReview && (int) $myReview['rating'] >= $i ? '★' : '☆' ?></span>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="rating" value="<?= $myReview ? (int) $myReview['rating'] : 5 ?>">
            <label for="comment">Your Review</label>
            <textarea id="comment" name="comment" rows="3" placeholder="What did you learn? Would you recommend it?"><?= e($myReview['comment'] ?? '') ?></textarea>
            <p class="error-text hidden" data-review-error></p>
            <button type="submit" class="btn btn-primary" style="width:fit-content;"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
          </form>
        </div>
      <?php elseif ($isEnrolled): ?>
        <p class="card card-pad muted small" style="margin-top:16px; border-style:dashed;">
          <a href="<?= e(base_url('signup.php')) ?>" style="color:var(--accent); font-weight:600;">Create a free account</a> to leave a review after completing this course.
        </p>
      <?php elseif ($user): ?>
        <p class="card card-pad muted small" style="margin-top:16px; border-style:dashed;">Enroll in this course to leave a review once you've learned from it.</p>
      <?php else: ?>
        <p class="card card-pad muted small" style="margin-top:16px; border-style:dashed;">
          <a href="<?= e(base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" style="color:var(--accent); font-weight:600;">Log in</a> to leave a review after completing this course.
        </p>
      <?php endif; ?>

      <div style="margin-top:20px;">
        <?php if (!$course['reviews']): ?>
          <p class="small muted">No reviews yet. Be the first to share your experience.</p>
        <?php else: ?>
          <?php foreach ($course['reviews'] as $r): ?>
            <div class="review-card">
              <div class="head">
                <div class="avatar">
                  <?php if (!empty($r['author_avatar_url'])): ?><img src="<?= e(asset_src($r['author_avatar_url'])) ?>" alt="">
                  <?php else: ?><?= e(mb_substr($r['author_name'], 0, 1)) ?><?php endif; ?>
                </div>
                <div>
                  <div class="name"><?= e($r['author_name']) ?></div>
                  <div class="stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></div>
                </div>
              </div>
              <p class="comment"><?= e($r['comment']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <aside class="course-sidebar">
      <?php render_enroll_panel($course, $user, $isOwner, $isEnrolled); ?>

      <div class="instructor-card card card-pad reveal reveal-delay-3" id="instructor-card">
        <h3 class="small" style="text-transform:uppercase; letter-spacing:0.04em; color:var(--muted); font-weight:700;">About the Instructor</h3>
        <div class="row gap-2" style="margin-top:14px;">
          <div class="avatar-lg">
            <?php if (!empty($course['creator_avatar_url'])): ?>
              <img src="<?= e(asset_src($course['creator_avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($course['creator_name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div>
            <a href="<?= e(base_url('profile.php?id=' . $course['creator_user_id'])) ?>" style="font-weight:700; color:var(--ink);"><?= e($course['creator_name']) ?></a>
            <div class="small muted"><?= e($course['creator_headline'] ?? '') ?></div>
          </div>
        </div>

        <?php if (!empty($course['creator_bio'])): ?>
          <p class="creator-bio"><?= e(mb_strimwidth($course['creator_bio'], 0, 180, '…')) ?></p>
        <?php endif; ?>

        <div class="instructor-stats">
          <div class="stat">
            <?php dash_icon('book-open'); ?>
            <div><div class="stat-value"><?= (int) $course['creator_course_count'] ?></div><div class="stat-label">Course<?= (int) $course['creator_course_count'] === 1 ? '' : 's' ?></div></div>
          </div>
          <div class="stat">
            <?php dash_icon('users'); ?>
            <div><div class="stat-value"><?= (int) $course['creator_student_count'] ?></div><div class="stat-label">Student<?= (int) $course['creator_student_count'] === 1 ? '' : 's' ?></div></div>
          </div>
        </div>

        <?php render_social_links($creatorSocials); ?>
        <a href="<?= e(base_url('profile.php?id=' . $course['creator_user_id'])) ?>" class="btn btn-outline" style="width:100%; margin-top:14px; justify-content:center;">View Full Profile</a>
      </div>
    </aside>
  </div>
</section>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/review.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
