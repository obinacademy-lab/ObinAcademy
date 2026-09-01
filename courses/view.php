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
$creatorSocials = array_filter($creatorSocials);

$socialIconPaths = [
    'facebook' => '<path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/>',
    'instagram' => '<path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/>',
    'youtube' => '<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.5V8.5l6.3 3.5-6.3 3.5Z"/>',
    'tiktok' => '<path d="M16.5 2h-3v13.5a2.5 2.5 0 1 1-2.5-2.5c.2 0 .4 0 .6.05V9.9a5.6 5.6 0 0 0-.6 0 5.6 5.6 0 1 0 5.6 5.6V8.4a7.4 7.4 0 0 0 4.4 1.4V6.7a4.4 4.4 0 0 1-4.5-4.4Z"/>',
    'linkedin' => '<path d="M6.9 8.4H3.6V20h3.3V8.4zM5.3 3.4a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 20h-3.3v-6.1c0-1.5-.5-2.5-1.9-2.5-1 0-1.6.7-1.9 1.4-.1.2-.1.6-.1.9V20H10s0-10.6 0-11.6h3.3v1.6c.4-.7 1.2-1.7 3-1.7 2.2 0 3.8 1.4 3.8 4.5V20z"/>',
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
    </div>
  </div>
</section>

<section class="section">
  <div class="container grid lg:grid-3" style="gap:48px; align-items:start;">
    <div style="grid-column: span 2;" class="reveal reveal-delay-1">
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
            <div style="font-weight:700;"><?= e($course['creator_name']) ?></div>
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

        <?php if ($creatorSocials): ?>
          <div class="creator-social">
            <?php foreach ($creatorSocials as $network => $url): ?>
              <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(ucfirst($network)) ?>">
                <svg viewBox="0 0 24 24" fill="currentColor"><?= $socialIconPaths[$network] ?></svg>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</section>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/review.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
