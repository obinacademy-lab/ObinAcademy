<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/gifts.php';

$user = current_user();
$q = query_param('q');
$categorySlug = query_param('category');
$selectedSlug = query_param('slug');

$selectedCourse = null;
$selectedEligibility = null;
if ($selectedSlug !== '') {
    $selectedCourse = get_course_by_slug($selectedSlug);
    if ($selectedCourse) $selectedEligibility = gift_eligibility_for_course($selectedCourse);
}

$categories = get_categories();
$categoryEmoji = [
    'finance' => '💰', 'business' => '💼', 'technology-software-development' => '💻',
    'marketing-digital-marketing' => '📣', 'design-creative' => '🎨', 'ecommerce' => '🛒',
    'education-teaching' => '🎓', 'agriculture' => '🌾', 'health-wellness' => '❤️',
    'artificial-intelligence' => '🤖', 'tech' => '💻',
    'medical' => '🩺', 'food' => '🍽️', 'law' => '⚖️', 'human-resources' => '👥',
    'engineering' => '⚙️', 'construction' => '🏗️', 'hospitality' => '🏨',
    'fashion-beauty' => '👗', 'music-arts' => '🎵', 'photography-film' => '📷',
    'sports-fitness' => '🏋️', 'logistics' => '🚚', 'environment' => '🌿',
    'energy' => '⚡', 'automotive' => '🚗', 'media-journalism' => '📰',
];

/** Rebuilds the browse URL with one param overridden — same pattern as courses/index.php's browse_url(). */
function gift_browse_url(string $q, string $category, array $override = []): string {
    $params = array_merge(['q' => $q, 'category' => $category], $override);
    $params = array_filter($params, fn($v) => $v !== '');
    return base_url('gift.php') . ($params ? '?' . http_build_query($params) : '');
}

// Only courses that can actually be gifted (real one-time price, or a real
// subscription price) show up in the browse grid — a free course has
// nothing to buy someone, see gift_eligibility_for_course().
$allMatches = search_courses($q, $categorySlug, 'popular');
$giftableCourses = array_values(array_filter($allMatches, fn($c) => gift_eligibility_for_course($c)['available']));

$activeCategoryName = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) { $activeCategoryName = $cat['name']; break; }
}

$pageTitle = 'Gift a Course — Obin Academy';
$pageDescription = 'Give someone the gift of learning — browse any course on Obin Academy and pay for it as a gift, paid for instantly with MTN or Airtel Mobile Money.';
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1>Gift a Course</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">
      Pick any course from any school on Obin Academy and send it to someone — they get instant access, you cover the cost.
    </p>

    <form method="get" class="hero-search-v3">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search courses to gift…" value="<?= e($q) ?>">
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <button type="submit">Search</button>
    </form>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <?php if ($selectedSlug !== ''): ?>
    <?php if (!$selectedCourse || $selectedCourse['status'] !== 'PUBLISHED'): ?>
      <div class="alert alert-error" style="max-width:640px; margin:0 auto 32px;">That course couldn't be found.</div>
    <?php elseif (!$selectedEligibility['available']): ?>
      <div class="alert alert-error" style="max-width:640px; margin:0 auto 32px;">"<?= e($selectedCourse['title']) ?>" isn't available to gift right now.</div>
    <?php elseif ($user && (int) $user['id'] === (int) $selectedCourse['creator_id']): ?>
      <div class="alert alert-error" style="max-width:640px; margin:0 auto 32px;">You can't gift your own course.</div>
    <?php else: ?>
      <div style="max-width:480px; margin:0 auto 40px;">
        <a href="<?= e(gift_browse_url($q, $categorySlug, ['slug' => ''])) ?>" class="small muted" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none; margin-bottom:16px;">
          <?php dash_icon('arrow-left'); ?> Choose a different course
        </a>
        <div class="card card-pad" style="display:flex; align-items:center; gap:14px; margin-bottom:20px;">
          <div class="course-flat-thumb" style="width:80px; height:50px; flex-shrink:0; margin-top:0;">
            <?php if ($selectedCourse['thumbnail_url']): ?><img src="<?= e(asset_src($selectedCourse['thumbnail_url'])) ?>" alt="">
            <?php else: ?><div class="placeholder" style="font-size:10px;">Obin Academy</div><?php endif; ?>
          </div>
          <div>
            <div style="font-weight:700; color:var(--ink);"><?= e($selectedCourse['title']) ?></div>
            <div class="small muted">by <?= e($selectedCourse['creator_name']) ?></div>
          </div>
        </div>

        <?php if (!$user): ?>
          <div class="card card-pad" style="text-align:center;">
            <p class="muted">Gifting a course needs a free account first — that's where your receipt and gift history live.</p>
            <a href="<?= e(base_url('signup.php?redirect=' . urlencode('/gift.php?slug=' . $selectedCourse['slug']))) ?>" class="btn btn-gold btn-block btn-lg shine" style="margin-top:16px;">Sign Up to Gift This Course</a>
            <p class="guest-note">Already have an account? <a href="<?= e(base_url('login.php?redirect=' . urlencode('/gift.php?slug=' . $selectedCourse['slug']))) ?>">Log in</a></p>
          </div>
        <?php else: ?>
          <?php render_gift_panel($selectedCourse, false); ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="chip-row chip-row-scroll">
    <a href="<?= e(gift_browse_url($q, '')) ?>" class="chip <?= !$categorySlug ? 'active' : '' ?>">✨ All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= e(gift_browse_url($q, $cat['slug'])) ?>" class="chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="browse-toolbar">
    <div class="browse-result-info">
      <?php if ($activeCategoryName): ?>in <a href="<?= e(gift_browse_url($q, '')) ?>" class="filter-pill"><?= e($activeCategoryName) ?> <span>&times;</span></a><?php endif; ?>
      <?php if ($q): ?>matching <a href="<?= e(gift_browse_url('', $categorySlug)) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a><?php endif; ?>
    </div>
  </div>

  <?php if ($giftableCourses): ?>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:20px;">
      <?php foreach ($giftableCourses as $c) render_gift_course_card($c); ?>
    </div>
  <?php else: ?>
    <div class="empty-browse">
      <div class="empty-browse-icon">🎁</div>
      <h3 class="h3">No giftable courses match your search</h3>
      <p class="muted" style="margin-top:8px;">Try a different keyword, or browse a different category. Free courses can't be gifted — there's nothing to pay for.</p>
      <div class="row gap-2" style="margin-top:20px; justify-content:center;">
        <a href="<?= e(base_url('gift.php')) ?>" class="btn btn-primary btn-sm">Clear Filters</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
