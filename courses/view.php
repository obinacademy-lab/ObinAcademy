<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/enroll_panel.php';
require __DIR__ . '/../includes/enrollment.php';
require __DIR__ . '/../includes/course_card.php';

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
$isInterested = $user ? is_interested_in_course((int) $user['id'], (int) $course['id']) : false;

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

$reviewCount = count($course['reviews']);
$avgRating = $reviewCount ? array_sum(array_column($course['reviews'], 'rating')) / $reviewCount : 0;
$ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($course['reviews'] as $r) $ratingBreakdown[(int) $r['rating']]++;
$myReview = null;
if ($user) {
    foreach ($course['reviews'] as $r) {
        if ((int) $r['author_id'] === (int) $user['id']) { $myReview = $r; break; }
    }
}

$relatedCourses = $course['status'] === 'PUBLISHED'
    ? get_related_courses((int) $course['id'], (int) $course['category_id'], $user ? (int) $user['id'] : null)
    : [];

$recentActivity = $course['status'] === 'PUBLISHED'
    ? get_recent_enrollment_activity((int) $course['id'], 5)
    : [];

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
];
if (!empty($course['thumbnail_url'])) $structuredData['image'] = asset_src($course['thumbnail_url']);
if ((float) $course['price'] > 0) {
    $structuredData['offers'] = [
        '@type' => 'Offer',
        'price' => number_format(course_has_active_sale($course) ? (float) $course['sale_price'] : (float) $course['price'], 2, '.', ''),
        'priceCurrency' => 'UGX',
        'url' => base_url('courses/view.php?slug=' . $course['slug']),
        'availability' => 'https://schema.org/InStock',
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

<section class="course-hero2">
  <div class="container course-hero2-inner reveal">
    <nav class="course-hero2-crumb">
      <a href="<?= e(base_url('/')) ?>">Home</a> / <a href="<?= e(base_url('courses/index.php')) ?>">Courses</a> / <a href="<?= e(base_url('courses/index.php?category=' . $course['category_slug'])) ?>"><?= e($course['category_name']) ?></a>
    </nav>
    <span class="course-hero2-pill"><?= e($course['category_name']) ?></span>
    <h1><?= e($course['title']) ?></h1>
    <p class="course-hero2-sub"><?= e($course['summary']) ?></p>

    <?php if ($reviewCount > 0): ?>
      <div class="course-hero2-rating">
        <span class="stars"><?= str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) ?></span>
        <span class="num"><?= number_format($avgRating, 1) ?></span>
        <span class="count">&middot; <?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></span>
      </div>
    <?php endif; ?>

    <div class="course-hero2-meta">
      <span class="item"><?php dash_icon('users'); ?><?= (int) $course['student_count'] ?> student<?= (int) $course['student_count'] === 1 ? '' : 's' ?></span>
      <span class="item"><?php dash_icon('eye'); ?><?= number_format((int) $course['view_count']) ?> view<?= (int) $course['view_count'] === 1 ? '' : 's' ?></span>
      <a href="<?= e(base_url('profile.php?id=' . $course['creator_user_id'])) ?>" class="course-hero2-instructor">
        <span class="av">
          <?php if (!empty($course['creator_avatar_url'])): ?>
            <img src="<?= e(asset_src($course['creator_avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($course['creator_name'], 0, 1)) ?><?php endif; ?>
        </span>
        <span>
          <span class="name"><?= e($course['creator_name']) ?></span>
          <span class="role"><?= e($course['creator_headline'] ?: 'Instructor') ?></span>
        </span>
      </a>
      <?php render_share_button(base_url('courses/view.php?slug=' . $course['slug']), $course['title'], 'Share Course', 'light', (int) $course['id'], 'Share this course'); ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--surface);">
  <div class="container grid lg:grid-3" style="gap:48px; align-items:start;">
    <div class="course-detail-main reveal reveal-delay-1">
      <div class="course-flat-thumb course-flat-thumb-lg">
        <?php if (!empty($course['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($course['thumbnail_url'])) ?>" alt="">
        <?php else: ?>
          <div class="placeholder">Obin Academy</div>
        <?php endif; ?>
      </div>

      <div class="course-flat-sec">
        <div class="course-flat-sec-head"><span class="dash" aria-hidden="true"></span><h2>About This Course</h2></div>
        <div class="muted course-description"><?= format_rich_text($course['description']) ?></div>
      </div>

      <div class="course-flat-sec">
        <div class="course-flat-sec-head"><span class="dash" aria-hidden="true"></span><h2>Curriculum</h2></div>
        <div class="curriculum-stat"><strong><?= count($course['modules']) ?></strong> module<?= count($course['modules']) === 1 ? '' : 's' ?> &middot; <strong><?= $totalLessons ?></strong> lesson<?= $totalLessons === 1 ? '' : 's' ?></div>
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

      <div class="course-flat-sec" id="reviews">
        <div class="course-flat-sec-head"><span class="dash" aria-hidden="true"></span><h2>Reviews</h2></div>

        <div class="reviews-grid reveal">
          <div class="rsummary">
            <div class="num"><?= number_format($avgRating, 1) ?></div>
            <div class="stars"><?= str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) ?></div>
            <div class="count"><?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></div>
            <?php if ($reviewCount > 0): ?>
              <div class="bars">
                <?php for ($star = 5; $star >= 1; $star--): $starCount = $ratingBreakdown[$star]; $pct = $reviewCount ? round($starCount / $reviewCount * 100) : 0; ?>
                  <div class="rbar-row" style="--pct:<?= $pct ?>%;">
                    <span class="label"><?= $star ?>★</span>
                    <span class="rbar-track"><span class="rbar-fill"></span></span>
                    <span class="count"><?= $starCount ?></span>
                  </div>
                <?php endfor; ?>
              </div>
            <?php endif; ?>
          </div>

          <div>
            <?php if ($isEnrolled && !$isOwner): ?>
              <div class="rform" data-review-form data-course-id="<?= (int) $course['id'] ?>" data-submit-url="<?= e(base_url('api/submit-review.php')) ?>">
                <h3 style="margin:0; font-size:16px; font-weight:800;"><?= $myReview ? 'Edit Your Review' : 'Leave a Review' ?></h3>
                <p class="small muted" style="margin-top:4px;">Tell other learners what you thought of this course.</p>
                <div class="star-input" data-star-input>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" data-star="<?= $i ?>"><?= $myReview && (int) $myReview['rating'] >= $i ? '★' : '☆' ?></button>
                  <?php endfor; ?>
                </div>
                <form data-review-submit>
                  <input type="hidden" name="rating" value="<?= $myReview ? (int) $myReview['rating'] : 0 ?>">
                  <textarea name="comment" rows="3" placeholder="What did you learn? Would you recommend this course?"><?= $myReview ? e($myReview['comment']) : '' ?></textarea>
                  <p class="small hidden" data-review-error style="color:var(--danger); margin-top:8px;"></p>
                  <button type="submit" class="btn btn-primary"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
                </form>
              </div>
            <?php endif; ?>

            <?php if ($reviewCount > 0): ?>
              <div class="rlist" style="<?= $isEnrolled && !$isOwner ? 'margin-top:20px;' : '' ?>">
                <?php foreach ($course['reviews'] as $review): ?>
                  <div class="rcard">
                    <span class="quote">&rdquo;</span>
                    <div class="head">
                      <span class="avatar">
                        <?php if (!empty($review['author_avatar_url'])): ?>
                          <img src="<?= e(asset_src($review['author_avatar_url'])) ?>" alt="">
                        <?php else: ?><?= e(mb_substr($review['author_name'], 0, 1)) ?><?php endif; ?>
                      </span>
                      <div>
                        <div class="name"><?= e($review['author_name']) ?></div>
                        <div class="stars"><?= str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']) ?></div>
                      </div>
                    </div>
                    <?php if (!empty($review['comment'])): ?><p class="comment"><?= e($review['comment']) ?></p><?php endif; ?>
                    <p class="small muted" style="margin-top:10px;"><?= e(format_date($review['created_at'])) ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php elseif (!$isEnrolled || $isOwner): ?>
              <p class="muted">No reviews yet — be the first to finish this course and leave one.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($relatedCourses): ?>
        <div class="sab-head"><span class="dash" aria-hidden="true"></span><h2>Students Also Bought</h2></div>
        <p class="sab-sub">More from <?= e($course['category_name']) ?> — picked from what other learners on Obin Academy bought.</p>
        <div class="sab-strip">
          <div class="grid sm:grid-2">
            <?php foreach ($relatedCourses as $rc) render_course_card($rc); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside class="course-sidebar">
      <?php render_enroll_panel($course, $user, $isOwner, $isEnrolled, $isInterested); ?>
    </aside>
  </div>
</section>

<?php if (!$user && $course['status'] === 'PUBLISHED'): ?>
  <?php
    $popupHasSale = course_has_active_sale($course);
    $popupPrice = $popupHasSale ? (float) $course['sale_price'] : (float) $course['price'];
  ?>
  <div class="lead-overlay" data-guest-course-overlay>
    <div class="lead-modal">
      <button type="button" class="lead-modal-close" data-guest-course-close aria-label="Close">&times;</button>
      <div class="lead-modal-icon">🔒</div>
      <h2>Create a Free Account to Start Learning</h2>
      <p class="lead-sub">Join Obin Academy to unlock &ldquo;<?= e($course['title']) ?>&rdquo; — <?= $popupPrice > 0 ? e(format_money($popupPrice)) . ', one-time payment' : 'free' ?>. Takes less than a minute.</p>
      <a href="<?= e(base_url('signup.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" class="btn btn-primary btn-block btn-lg">Join Now <span class="btn-arrow">→</span></a>
      <p class="small muted" style="margin-top:12px; text-align:center;">
        Already have an account? <a href="<?= e(base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" style="color:var(--accent); font-weight:600;">Log in</a>
        &nbsp;&middot;&nbsp;
        <a href="#" data-guest-course-close style="color:var(--muted); font-weight:600;">Continue browsing</a>
      </p>
    </div>
  </div>
  <script>
    (() => {
      var overlay = document.querySelector('[data-guest-course-overlay]');
      if (!overlay) return;
      function open() { overlay.classList.add('open'); requestAnimationFrame(() => overlay.classList.add('visible')); }
      function close() { overlay.classList.remove('visible'); setTimeout(() => overlay.classList.remove('open'), 250); }
      overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
      overlay.querySelectorAll('[data-guest-course-close]').forEach((btn) => btn.addEventListener('click', (e) => { e.preventDefault(); close(); }));
      setTimeout(open, 900);
    })();
  </script>
<?php endif; ?>

<?php if ($recentActivity): ?>
  <div class="activity-toast" id="activityToast" role="status" aria-live="polite">
    <span class="activity-toast-dot" aria-hidden="true"></span>
    <div class="activity-toast-body">
      <p class="activity-toast-text" data-activity-text></p>
      <p class="activity-toast-time" data-activity-time></p>
    </div>
    <button type="button" class="activity-toast-close" data-activity-close aria-label="Dismiss">&times;</button>
  </div>
  <script>
    (() => {
      var toast = document.getElementById('activityToast');
      if (!toast) return;
      var storageKey = 'oaActivityToastDismissed_<?= (int) $course['id'] ?>';
      try { if (sessionStorage.getItem(storageKey)) return; } catch (e) {}

      var entries = [
        <?php foreach ($recentActivity as $a): ?>
        {
          name: <?= json_encode(display_name_initial($a['learner_name']), JSON_HEX_TAG) ?>,
          city: <?= json_encode($a['city'], JSON_HEX_TAG) ?>,
          timeAgo: <?= json_encode(time_ago($a['enrolled_at']), JSON_HEX_TAG) ?>
        },
        <?php endforeach; ?>
      ];

      var textEl = toast.querySelector('[data-activity-text]');
      var timeEl = toast.querySelector('[data-activity-time]');
      var closeBtn = toast.querySelector('[data-activity-close]');
      var dismissed = false;
      var timers = [];

      function dismiss() {
        dismissed = true;
        timers.forEach(clearTimeout);
        toast.classList.remove('show');
        try { sessionStorage.setItem(storageKey, '1'); } catch (e) {}
      }
      closeBtn.addEventListener('click', dismiss);

      function showEntry(i) {
        if (dismissed || i >= entries.length) return;
        var e = entries[i];
        textEl.innerHTML = '<b>' + e.name + '</b>' + (e.city ? ' from ' + e.city : '') + ' just enrolled in this course';
        timeEl.textContent = e.timeAgo;
        toast.classList.add('show');
        timers.push(setTimeout(function () {
          toast.classList.remove('show');
          timers.push(setTimeout(function () { showEntry(i + 1); }, 4000));
        }, 6000));
      }

      timers.push(setTimeout(function () { showEntry(0); }, 4000));
    })();
  </script>
<?php endif; ?>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/review.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
