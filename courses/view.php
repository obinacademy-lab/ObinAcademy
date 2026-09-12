<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/enroll_panel.php';
require __DIR__ . '/../includes/enrollment.php';
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

$isEvent = $course['type'] === 'EVENT';
$comments = get_visible_comments((int) $course['id']);
$commentCount = count($comments);
$canModerateComments = $isOwner || $isAdmin;

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
    '@type' => $isEvent ? 'Event' : 'Course',
    'name' => $course['title'],
    'description' => $pageDescription,
    'url' => base_url('courses/view.php?slug=' . $course['slug']),
];
if (!$isEvent) {
    $structuredData['provider'] = [
        '@type' => 'Organization',
        'name' => 'Obin Academy',
        'sameAs' => base_url('index.php'),
    ];
}
if (!empty($course['thumbnail_url'])) $structuredData['image'] = asset_src($course['thumbnail_url']);
if ($isEvent) {
    if (!empty($course['event_starts_at'])) $structuredData['startDate'] = date('c', strtotime($course['event_starts_at']));
    if (!empty($course['event_ends_at'])) $structuredData['endDate'] = date('c', strtotime($course['event_ends_at']));
    $structuredData['eventAttendanceMode'] = $course['event_online_url']
        ? 'https://schema.org/OnlineEventAttendanceMode'
        : 'https://schema.org/OfflineEventAttendanceMode';
    $structuredData['location'] = $course['event_online_url']
        ? ['@type' => 'VirtualLocation', 'url' => $course['event_online_url']]
        : ['@type' => 'Place', 'name' => $course['event_location'] ?: 'TBA'];
    $structuredData['organizer'] = ['@type' => 'Person', 'name' => $course['creator_name']];
}
if ((float) $course['price'] > 0) {
    $structuredData['offers'] = [
        '@type' => 'Offer',
        'price' => number_format(course_has_active_sale($course) ? (float) $course['sale_price'] : (float) $course['price'], 2, '.', ''),
        'priceCurrency' => 'UGX',
        'url' => base_url('courses/view.php?slug=' . $course['slug']),
        'availability' => 'https://schema.org/InStock',
    ];
}
if (!$isEvent && $reviewCount > 0) {
    $structuredData['aggregateRating'] = [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($avgRating, 1),
        'reviewCount' => $reviewCount,
    ];
}
if (!$isEvent && !empty($course['creator_name'])) {
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

<section class="course-hero course-hero-centered">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container">
    <nav class="breadcrumb reveal">
      <a href="<?= e(base_url('/')) ?>">Home</a>
      <?php dash_icon('chevron-right'); ?>
      <?php if ($isEvent): ?>
        <a href="<?= e(base_url('events.php')) ?>">Events</a>
      <?php else: ?>
        <a href="<?= e(base_url('courses/index.php')) ?>">Courses</a>
        <?php dash_icon('chevron-right'); ?>
        <a href="<?= e(base_url('courses/index.php?category=' . $course['category_slug'])) ?>"><?= e($course['category_name']) ?></a>
      <?php endif; ?>
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
        <?php if ($isEvent): ?>
          <span class="meta-chip"><?php dash_icon('calendar'); ?><?= $course['event_starts_at'] ? e(date('M j, Y \a\t g:i A', strtotime($course['event_starts_at']))) : 'Date TBA' ?></span>
          <span class="meta-chip"><?php dash_icon($course['event_online_url'] ? 'globe' : 'map-pin'); ?><?= $course['event_online_url'] ? 'Online' : e($course['event_location'] ?: 'Location TBA') ?></span>
          <span class="meta-chip"><?php dash_icon('users'); ?><?= (int) $course['student_count'] ?> ticket<?= (int) $course['student_count'] === 1 ? '' : 's' ?> sold</span>
          <span class="meta-chip"><?php dash_icon('eye'); ?><?= number_format((int) $course['view_count']) ?> view<?= (int) $course['view_count'] === 1 ? '' : 's' ?></span>
        <?php else: ?>
          <span class="meta-chip"><?php dash_icon('users'); ?><?= (int) $course['student_count'] ?> students</span>
          <span class="meta-chip"><?php dash_icon('eye'); ?><?= number_format((int) $course['view_count']) ?> view<?= (int) $course['view_count'] === 1 ? '' : 's' ?></span>
          <span class="meta-chip"><?php dash_icon('play'); ?><?= $totalLessons ?> lessons</span>
        <?php endif; ?>
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
        <?php render_share_button(base_url('courses/view.php?slug=' . $course['slug']), $course['title'], $isEvent ? 'Share Event' : 'Share Course', 'dark', (int) $course['id'], $isEvent ? "I'm going to this event" : 'Share this course'); ?>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container grid lg:grid-3" style="gap:48px; align-items:start;">
    <div style="grid-column: span 2;" class="reveal reveal-delay-1 course-content">
      <h2 class="h3"><?= $isEvent ? 'About This Event' : 'About This Course' ?></h2>
      <p class="muted course-description" style="margin-top:14px; line-height:1.75; white-space:pre-line;"><?= e($course['description']) ?></p>

      <?php if ($isEvent): ?>
        <h2 class="h3" style="margin-top:48px;">Event Details</h2>
        <div class="event-detail-block reveal" style="margin-top:16px;">
          <div class="event-detail-row">
            <span class="event-detail-icon"><?php dash_icon('calendar'); ?></span>
            <div>
              <div class="event-detail-label">When</div>
              <div class="event-detail-value">
                <?= $course['event_starts_at'] ? e(format_date($course['event_starts_at'])) . ' at ' . e(date('g:i A', strtotime($course['event_starts_at']))) : 'Date to be announced' ?>
                <?php if ($course['event_ends_at']): ?> &ndash; <?= e(date('g:i A', strtotime($course['event_ends_at']))) ?><?php endif; ?>
              </div>
            </div>
          </div>
          <div class="event-detail-row">
            <span class="event-detail-icon"><?php dash_icon($course['event_online_url'] ? 'globe' : 'map-pin'); ?></span>
            <div>
              <div class="event-detail-label"><?= $course['event_online_url'] ? 'Online' : 'Location' ?></div>
              <div class="event-detail-value">
                <?php if ($course['event_online_url'] && ($isEnrolled || $isOwner)): ?>
                  <a href="<?= e($course['event_online_url']) ?>" target="_blank" rel="noopener noreferrer">Join the event &rarr;</a>
                <?php elseif ($course['event_online_url']): ?>
                  The link is emailed to ticket holders before the event.
                <?php else: ?>
                  <?= e($course['event_location'] ?: 'To be announced') ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="event-detail-row">
            <span class="event-detail-icon"><?php dash_icon('users'); ?></span>
            <div>
              <div class="event-detail-label">Capacity</div>
              <div class="event-detail-value">
                <?= $course['ticket_capacity'] !== null ? (int) $course['student_count'] . ' of ' . (int) $course['ticket_capacity'] . ' tickets sold' : (int) $course['student_count'] . ' attending so far' ?>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
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
      <?php endif; ?>

      <h2 class="h3" style="margin-top:48px;">Reviews<?= $reviewCount > 0 ? " ($reviewCount)" : '' ?></h2>

      <div class="reviews-grid">
        <?php if ($reviewCount > 0): ?>
          <div class="rsummary reveal">
            <div class="num"><?= number_format($avgRating, 1) ?></div>
            <div class="stars"><?= str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) ?></div>
            <div class="count"><?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></div>
            <div class="bars">
              <?php for ($star = 5; $star >= 1; $star--): $count = $ratingBreakdown[$star]; $pct = $reviewCount > 0 ? round($count / $reviewCount * 100) : 0; ?>
                <div class="rbar-row">
                  <span class="label"><?= $star ?> star</span>
                  <span class="rbar-track"><span class="rbar-fill" style="--pct:<?= $pct ?>%;"></span></span>
                  <span class="count"><?= $count ?></span>
                </div>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>

        <div>
          <?php if ($isEnrolled && $user): ?>
            <div data-review-form data-course-id="<?= (int) $course['id'] ?>" data-submit-url="<?= e(base_url('api/submit-review.php')) ?>">
              <form data-review-submit class="rform reveal">
                <label>Your Rating</label>
                <div class="star-input" data-star-input>
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" data-star="<?= $i ?>"><?= $myReview && (int) $myReview['rating'] >= $i ? '★' : '☆' ?></button>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" value="<?= $myReview ? (int) $myReview['rating'] : 5 ?>">
                <label for="comment" style="display:block; margin-top:14px;">Your Review</label>
                <textarea id="comment" name="comment" rows="3" placeholder="What did you learn? Would you recommend it?"><?= e($myReview['comment'] ?? '') ?></textarea>
                <p class="error-text hidden" data-review-error></p>
                <button type="submit" class="btn btn-primary"><?= $myReview ? 'Update Review' : 'Submit Review' ?></button>
              </form>
            </div>
          <?php elseif ($isEnrolled): ?>
            <p class="card card-pad muted small reveal" style="border-style:dashed;">
              <a href="<?= e(base_url('signup.php')) ?>" style="color:var(--accent); font-weight:600;">Create a free account</a> to leave a review after completing this course.
            </p>
          <?php elseif ($user): ?>
            <p class="card card-pad muted small reveal" style="border-style:dashed;">Enroll in this course to leave a review once you've learned from it.</p>
          <?php else: ?>
            <p class="card card-pad muted small reveal" style="border-style:dashed;">
              <a href="<?= e(base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" style="color:var(--accent); font-weight:600;">Log in</a> to leave a review after completing this course.
            </p>
          <?php endif; ?>

          <?php if (!$course['reviews']): ?>
            <p class="small muted" style="margin-top:16px;">No reviews yet. Be the first to share your experience.</p>
          <?php else: ?>
            <div class="rlist">
              <?php foreach ($course['reviews'] as $ri => $r): ?>
                <div class="rcard reveal reveal-delay-<?= min($ri + 1, 5) ?>">
                  <span class="quote">&rdquo;</span>
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
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="comments-heading">
        <span class="comments-heading-icon"><?php dash_icon('message-square'); ?></span>
        <h2 class="h3">Comments<?= $commentCount > 0 ? " ($commentCount)" : '' ?></h2>
      </div>
      <p class="muted small" style="margin-top:4px;">Open to any Obin Academy member — <?= $isEvent ? 'you don\'t need a ticket' : 'you don\'t need to be enrolled' ?> to join the discussion.</p>

      <div data-comments-root data-course-id="<?= (int) $course['id'] ?>" data-submit-url="<?= e(base_url('api/submit-comment.php')) ?>" data-delete-url="<?= e(base_url('api/delete-comment.php')) ?>" style="margin-top:18px; max-width:660px;">
        <?php if ($user): ?>
          <form data-comment-submit class="comment-form reveal">
            <div class="avatar comment-form-avatar">
              <?php if (!empty($user['avatar_url'])): ?><img src="<?= e(asset_src($user['avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($user['name'], 0, 1)) ?><?php endif; ?>
            </div>
            <div class="comment-form-body">
              <textarea id="commentBody" name="body" rows="2" maxlength="2000" placeholder="<?= $isEvent ? 'Ask a question or share your thoughts about this event…' : 'Ask a question or share your thoughts about this course…' ?>" required></textarea>
              <div class="comment-form-footer">
                <p class="error-text hidden" data-comment-error></p>
                <span class="comment-char-count" data-char-count>2000</span>
                <button type="submit" class="btn btn-primary btn-sm">Post Comment</button>
              </div>
            </div>
          </form>
        <?php else: ?>
          <p class="card card-pad muted small reveal" style="border-style:dashed;">
            <a href="<?= e(base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" style="color:var(--accent); font-weight:600;">Log in</a> to join the discussion — no <?= $isEvent ? 'ticket' : 'enrollment' ?> needed.
          </p>
        <?php endif; ?>

        <?php if (!$comments): ?>
          <div class="comment-empty">
            <?php dash_icon('message-square'); ?>
            <p class="small muted">No comments yet. Be the first to start the discussion.</p>
          </div>
        <?php else: ?>
          <div class="clist" data-comment-list>
            <?php foreach ($comments as $ci => $cm): ?>
              <div class="ccard reveal reveal-delay-<?= min($ci + 1, 5) ?>" data-comment-id="<?= (int) $cm['id'] ?>">
                <div class="head">
                  <div class="avatar">
                    <?php if (!empty($cm['author_avatar_url'])): ?><img src="<?= e(asset_src($cm['author_avatar_url'])) ?>" alt="">
                    <?php else: ?><?= e(mb_substr($cm['author_name'], 0, 1)) ?><?php endif; ?>
                  </div>
                  <div>
                    <div class="name"><?= e($cm['author_name']) ?></div>
                    <div class="small muted"><?= e(time_ago($cm['created_at'])) ?></div>
                  </div>
                  <?php if ($user && ($canModerateComments || (int) $cm['user_id'] === (int) $user['id'])): ?>
                    <button type="button" class="ccard-delete" data-comment-delete title="Delete comment"><?php dash_icon('trash'); ?></button>
                  <?php endif; ?>
                </div>
                <p class="comment"><?= nl2br(e($cm['body'])) ?></p>

                <?php if ($user): ?>
                  <button type="button" class="comment-reply-toggle" data-reply-toggle data-reply-to-id="<?= (int) $cm['id'] ?>" data-reply-to-name="<?= e($cm['author_name']) ?>">↩ Reply</button>
                <?php endif; ?>

                <?php if ($cm['replies']): ?>
                  <div class="comment-replies">
                    <?php foreach ($cm['replies'] as $rp): ?>
                      <div class="comment-reply" data-comment-id="<?= (int) $rp['id'] ?>">
                        <div class="head">
                          <div class="avatar">
                            <?php if (!empty($rp['author_avatar_url'])): ?><img src="<?= e(asset_src($rp['author_avatar_url'])) ?>" alt="">
                            <?php else: ?><?= e(mb_substr($rp['author_name'], 0, 1)) ?><?php endif; ?>
                          </div>
                          <div>
                            <div class="name"><?= e($rp['author_name']) ?></div>
                            <div class="small muted"><?= e(time_ago($rp['created_at'])) ?></div>
                          </div>
                          <?php if ($user && ($canModerateComments || (int) $rp['user_id'] === (int) $user['id'])): ?>
                            <button type="button" class="ccard-delete" data-comment-delete title="Delete reply"><?php dash_icon('trash'); ?></button>
                          <?php endif; ?>
                        </div>
                        <?php if ($rp['reply_to_author_name'] && $rp['reply_to_author_name'] !== $cm['author_name']): ?>
                          <div class="comment-reply-to">↪ Replying to <strong><?= e($rp['reply_to_author_name']) ?></strong></div>
                        <?php endif; ?>
                        <p class="comment"><?= nl2br(e($rp['body'])) ?></p>
                        <?php if ($user): ?>
                          <button type="button" class="comment-reply-toggle" data-reply-toggle data-reply-to-id="<?= (int) $rp['id'] ?>" data-reply-to-name="<?= e($rp['author_name']) ?>">↩ Reply</button>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if ($user): ?>
                  <form data-comment-submit data-thread-id="<?= (int) $cm['id'] ?>" class="comment-reply-form">
                    <div class="comment-reply-to-chip hidden" data-reply-chip>
                      <span>Replying to <strong data-reply-chip-name></strong></span>
                      <button type="button" data-reply-cancel aria-label="Cancel reply target">✕</button>
                    </div>
                    <textarea name="body" rows="2" maxlength="2000" placeholder="Write a reply…" required></textarea>
                    <div class="comment-form-footer">
                      <p class="error-text hidden" data-comment-error></p>
                      <span class="comment-char-count" data-char-count>2000</span>
                      <button type="submit" class="btn btn-outline btn-sm">Post Reply</button>
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
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
<script src="<?= e(versioned_asset('assets/js/comments.js')) ?>"></script>
<script src="<?= e(versioned_asset('assets/js/share.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
