<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/enroll_panel.php';
require __DIR__ . '/../includes/enrollment.php';
require __DIR__ . '/../includes/course_card.php';
require __DIR__ . '/../includes/comments.php';

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

$accessIsSubscription = ($course['creator_pricing_model'] ?? 'PER_COURSE') === 'MONTHLY_SUBSCRIPTION'
    && (float) ($course['creator_school_monthly_price'] ?? 0) > 0
    && (int) ($course['subscription_included'] ?? 1) === 1;
$accessFactValue = $accessIsSubscription
    ? 'While subscribed'
    : (!empty($course['access_duration_days']) ? (int) $course['access_duration_days'] . ' days' : 'Lifetime');

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

$comments = get_visible_comments((int) $course['id'], $user ? (int) $user['id'] : null);
$commentCount = count($comments);

$relatedCourses = $course['status'] === 'PUBLISHED'
    ? get_related_courses((int) $course['id'], (int) $course['category_id'], $user ? (int) $user['id'] : null)
    : [];

$recentActivity = $course['status'] === 'PUBLISHED'
    ? get_recent_activity_feed((int) $course['id'], 5)
    : [];
// This page renders its own specialized activity toast below (click
// scrolls to the enroll panel) — the site-wide one in includes/footer.php
// would otherwise double up with it here.
$suppressGlobalActivityToast = true;

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
  <div class="container course-layout">
    <div class="course-top reveal reveal-delay-1">
      <div class="course-flat-thumb course-flat-thumb-lg">
        <?php if (!empty($course['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($course['thumbnail_url'])) ?>" alt="">
        <?php else: ?>
          <div class="placeholder">Obin Academy</div>
        <?php endif; ?>
      </div>

      <div class="course-flat-sec">
        <div class="course-flat-sec-head"><span class="sec-eyebrow">Overview</span><h2>About this course</h2></div>
        <div class="muted course-description"><?= format_rich_text($course['description']) ?></div>
        <div class="course-facts">
          <div class="fact"><div class="v"><?= count($course['modules']) ?></div><div class="k">Module<?= count($course['modules']) === 1 ? '' : 's' ?></div></div>
          <div class="fact"><div class="v"><?= $totalLessons ?></div><div class="k">Lesson<?= $totalLessons === 1 ? '' : 's' ?></div></div>
          <div class="fact"><div class="v"><?= e($accessFactValue) ?></div><div class="k">Access</div></div>
          <div class="fact"><div class="v">Included</div><div class="k">Certificate</div></div>
        </div>
      </div>
    </div>

    <aside class="course-sidebar">
      <?php render_enroll_panel($course, $user, $isOwner, $isEnrolled, $isInterested); ?>
    </aside>

    <div class="course-rest">
      <div class="course-flat-sec">
        <div class="course-flat-sec-head"><span class="sec-eyebrow">Curriculum</span><h2>Inside the course</h2></div>
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
        <div class="course-flat-sec-head"><span class="sec-eyebrow">Reviews</span><h2>What learners say</h2></div>

        <div class="cpanel reviews-panel">
          <div class="rev-top">
            <div class="rev-score">
              <div class="n"><?= number_format($avgRating, 1) ?></div>
              <div class="stars"><?= str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) ?></div>
              <div class="c"><?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></div>
            </div>
            <?php if ($reviewCount > 0): ?>
              <div class="rbars reveal">
                <?php for ($star = 5; $star >= 1; $star--): $starCount = $ratingBreakdown[$star]; $pct = $reviewCount ? round($starCount / $reviewCount * 100) : 0; ?>
                  <div class="rbar-row" style="--pct:<?= $pct ?>%;">
                    <span class="label"><?= $star ?>★</span>
                    <span class="rbar-track"><span class="rbar-fill"></span></span>
                    <span class="count"><?= $starCount ?></span>
                  </div>
                <?php endfor; ?>
              </div>
            <?php else: ?>
              <p class="rev-empty"><?= ($isEnrolled && !$isOwner) ? 'No reviews yet — you could be the first.' : 'No reviews yet. Learners who enrol can leave the first one.' ?></p>
            <?php endif; ?>
          </div>

          <?php if ($user && $isEnrolled && !$isOwner): ?>
            <div class="rform" data-review-form data-course-id="<?= (int) $course['id'] ?>" data-submit-url="<?= e(base_url('api/submit-review.php')) ?>">
              <h3><?= $myReview ? 'Edit your review' : 'Leave a review' ?></h3>
              <p class="small muted">Tell other learners what you thought of this course.</p>
              <div class="star-input" data-star-input>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button type="button" data-star="<?= $i ?>"><?= $myReview && (int) $myReview['rating'] >= $i ? '★' : '☆' ?></button>
                <?php endfor; ?>
              </div>
              <form data-review-submit>
                <input type="hidden" name="rating" value="<?= $myReview ? (int) $myReview['rating'] : 0 ?>">
                <textarea name="comment" rows="3" placeholder="What did you learn? Would you recommend this course?"><?= $myReview ? e($myReview['comment']) : '' ?></textarea>
                <p class="small hidden" data-review-error style="color:var(--danger); margin-top:8px;"></p>
                <button type="submit" class="btn btn-primary"><?= $myReview ? 'Update review' : 'Submit review' ?></button>
              </form>
            </div>
          <?php endif; ?>
          <?php if (!($user && $isEnrolled && !$isOwner)):
            // Say why there is no review form, rather than silently omitting it.
            $reviewHere = '/courses/view.php?slug=' . urlencode($course['slug']) . '#reviews';
          ?>
            <div class="rev-gate">
              <?php if ($isOwner || $isAdmin): ?>
                <p>Reviews come from learners, so you can't review a course you run.</p>
              <?php elseif (!$user && $isEnrolled): ?>
                <p><b>Your purchase is saved on this device.</b> Log in or create a free account to leave a review.</p>
                <div class="rev-gate-actions">
                  <a class="btn btn-primary btn-sm" href="<?= e(base_url('login.php?redirect=' . urlencode($reviewHere))) ?>">Log in</a>
                  <a class="btn btn-outline btn-sm" href="<?= e(base_url('signup.php?redirect=' . urlencode($reviewHere))) ?>">Create account</a>
                </div>
              <?php else: ?>
                <p><b>Only learners enrolled in this course can leave a review.</b> Enrol first, then come back here to share what you thought.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php foreach ($course['reviews'] as $review): ?>
            <div class="rev">
              <span class="rev-av">
                <?php if (!empty($review['author_avatar_url'])): ?>
                  <img src="<?= e(asset_src($review['author_avatar_url'])) ?>" alt="">
                <?php else: ?><?= e(mb_substr($review['author_name'], 0, 1)) ?><?php endif; ?>
              </span>
              <div class="rev-main">
                <div class="rev-head"><b><?= e($review['author_name']) ?></b><time><?= e(format_date($review['created_at'])) ?></time></div>
                <div class="stars"><?= str_repeat('★', (int) $review['rating']) . str_repeat('☆', 5 - (int) $review['rating']) ?></div>
                <?php if (!empty($review['comment'])): ?><p><?= e($review['comment']) ?></p><?php endif; ?>
                <div class="rev-verified"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>Verified learner</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

<div class="course-flat-sec" id="comments">
        <div class="course-flat-sec-head"><span class="sec-eyebrow">Discussion</span><h2>Ask, answer, compare notes</h2></div>

        <div class="cpanel comments-panel" data-comments-root data-course-id="<?= (int) $course['id'] ?>" data-submit-url="<?= e(base_url('api/submit-comment.php')) ?>" data-delete-url="<?= e(base_url('api/delete-comment.php')) ?>" data-like-url="<?= e(base_url('api/toggle-comment-like.php')) ?>" data-gif-search-url="<?= e(base_url('api/search-gifs.php')) ?>">
          <div class="disc-head">
            <b><?= number_format($commentCount) ?> comment<?= $commentCount === 1 ? '' : 's' ?></b>
            <span>Newest first</span>
          </div>

          <?php if (!$comments): ?>
            <div class="comment-empty">
              <?php dash_icon('message-square'); ?>
              <span>No comments yet — be the first to start the discussion.</span>
            </div>
          <?php else: ?>
            <div class="clist" data-comment-list>
              <?php foreach ($comments as $c):
                $isMine = $user && (int) $user['id'] === (int) $c['user_id'];
              ?>
                <div class="crow <?= $isMine ? 'mine' : 'theirs' ?>" data-comment-id="<?= (int) $c['id'] ?>" data-author-name="<?= e($c['author_name']) ?>" data-snippet="<?= e($c['snippet']) ?>">
                  <?php if (!$isMine): ?>
                    <span class="mini-avatar">
                      <?php if (!empty($c['author_avatar_url'])): ?><img src="<?= e(asset_src($c['author_avatar_url'])) ?>" alt="">
                      <?php else: ?><?= e(mb_substr($c['author_name'], 0, 1)) ?><?php endif; ?>
                    </span>
                  <?php endif; ?>
                  <div class="bubble-stack">
                    <?php if (!$isMine): ?><span class="sender-name"><?= e($c['author_name']) ?></span><?php endif; ?>
                    <div class="cbubble">
                      <?php if ($c['reply_to_author_name']): ?>
                        <span class="reply-quote">
                          <span class="qname"><?= ($user && (int) $user['id'] === (int) $c['reply_to_user_id']) ? 'You' : e($c['reply_to_author_name']) ?></span>
                          <span class="qtext"><?= e($c['reply_to_snippet']) ?></span>
                        </span>
                      <?php endif; ?>
                      <?php if ($c['body'] !== ''): ?><p class="btext"><?= e($c['body']) ?></p><?php endif; ?>
                      <?php if (gif_url_is_trusted($c['gif_url'] ?? null)): ?><div class="gif-bubble"><img src="<?= e($c['gif_url']) ?>" alt="" loading="lazy"></div><?php endif; ?>
                      <div class="crow-meta">
                        <span><?= e(time_ago($c['created_at'])) ?></span>
                        <?php if ($user): ?>
                          <button type="button" class="like<?= $c['liked_by_me'] ? ' is-liked' : '' ?>" data-like-toggle data-comment-id="<?= (int) $c['id'] ?>">
                            <svg viewBox="0 0 24 24"><path d="M12 21s-6.7-4.35-9.3-8.1C1 10.2 1.6 6.9 4.4 5.4c2.3-1.2 4.9-.4 6.1 1.5l1.5 2.3 1.5-2.3c1.2-1.9 3.8-2.7 6.1-1.5 2.8 1.5 3.4 4.8 1.7 7.5C18.7 16.65 12 21 12 21Z"/></svg>
                            <span data-like-count><?= (int) $c['like_count'] ?></span>
                          </button>
                          <button type="button" class="reply-icon-btn" data-reply-toggle aria-label="Reply">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17 4 12l5-5"/><path d="M4 12h11a4 4 0 0 1 0 8h-1"/></svg>
                          </button>
                        <?php elseif ($c['like_count'] > 0): ?>
                          <span class="like is-static"><svg viewBox="0 0 24 24"><path d="M12 21s-6.7-4.35-9.3-8.1C1 10.2 1.6 6.9 4.4 5.4c2.3-1.2 4.9-.4 6.1 1.5l1.5 2.3 1.5-2.3c1.2-1.9 3.8-2.7 6.1-1.5 2.8 1.5 3.4 4.8 1.7 7.5C18.7 16.65 12 21 12 21Z"/></svg><?= (int) $c['like_count'] ?></span>
                        <?php endif; ?>
                        <?php if ($user && ($isMine || $isOwner || $isAdmin)): ?>
                          <button type="button" class="crow-delete" data-comment-delete aria-label="Delete comment"><?php dash_icon('trash'); ?></button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <div class="reply-affordance" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17 4 12l5-5"/><path d="M4 12h11a4 4 0 0 1 0 8h-1"/></svg>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($user): ?><p class="swipe-caption">Swipe a message to reply</p><?php endif; ?>
          <?php endif; ?>

          <?php if ($user): ?>
            <form class="comment-form" data-comment-submit>
              <div class="comment-reply-to-chip hidden" data-reply-chip>
                <span class="chip-text">Replying to <span data-reply-chip-name></span>: <span data-reply-chip-snippet></span></span>
                <button type="button" data-reply-cancel aria-label="Cancel reply">&times;</button>
              </div>
              <div class="comment-gif-preview hidden" data-gif-preview>
                <img data-gif-preview-img src="" alt="Attached GIF">
                <button type="button" data-gif-remove aria-label="Remove GIF">&times;</button>
              </div>
              <input type="hidden" name="gifUrl" data-gif-url-input value="">
              <div class="comment-form-bar">
                <button type="button" class="comment-emoji-toggle" data-emoji-toggle aria-label="Add an emoji">😊</button>
                <button type="button" class="comment-gif-toggle" data-gif-toggle aria-label="Add a GIF">GIF</button>
                <textarea name="body" rows="1" placeholder="Write a comment…" maxlength="2000"></textarea>
                <span class="comment-char-count" data-char-count>2000</span>
                <button type="submit" class="send-btn" aria-label="Post comment">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
                </button>
              </div>
              <p class="small hidden" data-comment-error style="color:var(--danger); margin-top:6px;"></p>
            </form>
          <?php else: ?>
            <p class="comment-login">
              <a href="<?= e(base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>">Log in</a> to join the discussion.
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($relatedCourses): ?>
      <div class="course-sab">
        <div class="sab-head"><span class="dash" aria-hidden="true"></span><h2>Students Also Bought</h2></div>
        <p class="sab-sub">More from <?= e($course['category_name']) ?> — picked from what other learners on Obin Academy bought.</p>
        <div class="sab-strip">
          <div class="grid sm:grid-2">
            <?php foreach ($relatedCourses as $rc) render_course_card($rc); ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
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
  <button type="button" class="activity-toast" id="activityToast" aria-live="polite">
    <div class="activity-toast-badge" aria-hidden="true"><span data-activity-initial></span><span class="ring"></span></div>
    <div class="activity-toast-body">
      <p class="activity-toast-text" data-activity-text></p>
      <p class="activity-toast-time"><span class="live-dot" aria-hidden="true"></span><span data-activity-time></span></p>
    </div>
    <div class="activity-toast-arrow" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </div>
    <span class="activity-toast-close" data-activity-close role="button" tabindex="0" aria-label="Dismiss">&times;</span>
  </button>
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
          timeAgo: <?= json_encode(time_ago($a['at']), JSON_HEX_TAG) ?>,
          action: <?= json_encode($a['action'], JSON_HEX_TAG) ?>
        },
        <?php endforeach; ?>
      ];

      var initialEl = toast.querySelector('[data-activity-initial]');
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
      // stopPropagation so dismissing never also triggers the toast's own
      // click-to-scroll below — closeBtn is a <span role="button">, not a
      // real nested <button> (a <button> can't validly contain another),
      // so Enter/Space activation needs its own handler too.
      closeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        dismiss();
      });
      closeBtn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          e.stopPropagation();
          dismiss();
        }
      });

      // Tapping the toast itself scrolls to and briefly highlights the
      // enroll/subscribe panel — social proof as an actual nudge toward
      // buying, not just something to glance at.
      var enrollPanel = document.querySelector('.enroll-panel');
      toast.addEventListener('click', function () {
        if (!enrollPanel) return;
        enrollPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        enrollPanel.classList.add('activity-pulse');
        setTimeout(function () { enrollPanel.classList.remove('activity-pulse'); }, 1400);
      });

      // Never sit ON the real footer nav (Home/Explore Schools/Stories/…)
      // once a visitor scrolls that far — the toast is viewport-fixed, so
      // it would otherwise cover those links for as long as it's
      // mid-cycle. Rather than hiding, float it up to rest in the plain
      // white space just above the footer instead, so it stays visible
      // and simply gets out of the footer's way. A lightweight poll
      // rather than a 'scroll' listener alone — some scroll paths (anchor
      // jumps, programmatic scrolls, certain trackpad/momentum scrolling)
      // don't reliably fire scroll events on every browser, and this is
      // cheap enough to just always be correct. Queried fresh on every
      // tick rather than captured once up front — this script block
      // renders and runs before includes/footer.php's own HTML further
      // down the page has been parsed, so a one-time lookup here would
      // always find null.
      function repositionAboveFooter() {
        var footer = document.querySelector('.site-footer-minimal');
        if (!footer) return;
        var overlap = window.innerHeight - footer.getBoundingClientRect().top;
        toast.style.bottom = overlap > 0 ? (overlap + 16) + 'px' : '';
      }
      setInterval(repositionAboveFooter, 200);

      // Loops indefinitely (wrapping back to the first real entry once it
      // reaches the end) rather than stopping after one pass — a new one
      // appears every 5s, for as long as the visitor stays on the page or
      // until they dismiss it.
      function showEntry(i) {
        if (dismissed || entries.length === 0) return;
        var e = entries[i % entries.length];
        toast.classList.remove('show');
        initialEl.textContent = (e.name.charAt(0) || '?').toUpperCase();
        textEl.innerHTML = '<b>' + e.name + '</b>' + (e.city ? ' from ' + e.city : '') + ' ' + e.action + ' this course';
        timeEl.textContent = e.timeAgo;
        requestAnimationFrame(function () { toast.classList.add('show'); });
        timers.push(setTimeout(function () {
          toast.classList.remove('show');
          timers.push(setTimeout(function () { showEntry(i + 1); }, 500));
        }, 4500));
      }

      timers.push(setTimeout(function () { showEntry(0); }, 5000));
    })();
  </script>
<?php endif; ?>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/review.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/comments.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
