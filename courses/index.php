<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/course_card.php';

$q = query_param('q');
$categorySlug = query_param('category');
// 'popular' (purchases + views, see POPULARITY_ORDER) is the default browse
// order now, not 'newest' — so 'popular' is the value treated as "no sort
// filter applied" throughout this page (browse_url()'s URL-cleaning, the
// chip active-states, $hasFilters), the same role 'newest' used to play.
$sort = query_param('sort', 'popular');
if (!isset(COURSE_SORT_OPTIONS[$sort])) $sort = 'popular';
$price = query_param('price');
if (!in_array($price, ['free', 'paid'], true)) $price = '';

$courses = search_courses($q, $categorySlug, $sort, $price);
$categories = get_categories();
$stats = get_platform_stats();
$hasFilters = $q !== '' || $categorySlug !== '' || $price !== '' || $sort !== 'popular';
$trending = !$hasFilters ? get_trending_courses(3) : [];

$activeCategoryName = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) { $activeCategoryName = $cat['name']; break; }
}

$categoryEmoji = [
    'finance' => '💰', 'business' => '💼', 'technology-software-development' => '💻',
    'marketing-digital-marketing' => '📣', 'design-creative' => '🎨', 'ecommerce' => '🛒',
    'education-teaching' => '🎓', 'agriculture' => '🌾', 'health-wellness' => '❤️',
    'artificial-intelligence' => '🤖', 'tech' => '💻',
];

/** Rebuilds the browse URL with one param overridden, keeping the others intact. */
function browse_url(string $q, string $category, string $sort, string $price = '', array $override = []): string {
    $params = array_merge(['q' => $q, 'category' => $category, 'sort' => $sort, 'price' => $price], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 'popular');
    return base_url('courses/index.php') . ($params ? '?' . http_build_query($params) : '');
}

if ($activeCategoryName) {
    $pageTitle = "$activeCategoryName Courses — Obin Academy";
    $pageDescription = "Explore $activeCategoryName courses on Obin Academy — practical, real-world skills taught by East African creators, paid for instantly with mobile money.";
} else {
    $pageTitle = 'Explore Courses — Obin Academy';
    $pageDescription = 'Browse practical courses in Finance, Tech, Business, Marketing, and more — taught by real creators and paid for instantly with MTN or Airtel Mobile Money.';
}
$noindex = $q !== ''; // search-result pages: let the base browse/category pages get indexed, not every query variation
require __DIR__ . '/../includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1><?= $activeCategoryName ? e($activeCategoryName) . ' Courses' : 'Find Your Next Skill' ?></h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">
      Real, practical courses from real African creators &mdash; taught by people doing the work, paid for instantly with mobile money.
    </p>

    <form method="get" class="hero-search-v3">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="What do you want to learn today?" value="<?= e($q) ?>">
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
      <?php if ($price): ?><input type="hidden" name="price" value="<?= e($price) ?>"><?php endif; ?>
      <button type="submit">Search</button>
    </form>

    <div class="hero-stats-v3">
      <span><strong data-count-up data-count-value="<?= (int) $stats['course_count'] ?>">0+</strong> courses</span>
      <span class="dot">&middot;</span>
      <span><strong data-count-up data-count-value="<?= (int) $stats['learner_count'] ?>">0+</strong> learners</span>
      <span class="dot">&middot;</span>
      <span><strong data-count-up data-count-value="<?= (int) $stats['creator_count'] ?>">0+</strong> creators</span>
    </div>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <div class="chip-row chip-row-scroll">
    <a href="<?= e(browse_url($q, '', 'popular', '')) ?>" class="chip <?= (!$categorySlug && !$price && $sort === 'popular') ? 'active' : '' ?>">✨ All</a>
    <a href="<?= e(browse_url($q, $categorySlug, $sort === 'popular' ? 'newest' : 'popular', $price)) ?>" class="chip <?= $sort === 'popular' ? 'active' : '' ?>">🔥 Trending</a>
    <a href="<?= e(browse_url($q, $categorySlug, $sort, $price === 'free' ? '' : 'free')) ?>" class="chip <?= $price === 'free' ? 'active' : '' ?>">🆓 Free</a>
    <a href="<?= e(browse_url($q, $categorySlug, $sort, $price === 'paid' ? '' : 'paid')) ?>" class="chip <?= $price === 'paid' ? 'active' : '' ?>">💳 Paid</a>
    <a href="<?= e(browse_url($q, $categorySlug, $sort === 'rating' ? 'popular' : 'rating', $price)) ?>" class="chip <?= $sort === 'rating' ? 'active' : '' ?>">⭐ Top</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= e(browse_url($q, $cat['slug'], $sort, $price)) ?>" class="chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="browse-toolbar">
    <div class="browse-result-info">
      <?php if ($activeCategoryName): ?>in <a href="<?= e(browse_url($q, '', $sort, $price)) ?>" class="filter-pill"><?= e($activeCategoryName) ?> <span>&times;</span></a><?php endif; ?>
      <?php if ($price): ?><a href="<?= e(browse_url($q, $categorySlug, $sort, '')) ?>" class="filter-pill"><?= $price === 'free' ? 'Free' : 'Paid' ?> <span>&times;</span></a><?php endif; ?>
      <?php if ($q): ?>matching <a href="<?= e(browse_url('', $categorySlug, $sort, $price)) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a><?php endif; ?>
    </div>
    <form method="get" class="sort-select-wrap">
      <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <?php if ($price): ?><input type="hidden" name="price" value="<?= e($price) ?>"><?php endif; ?>
      <label for="sort" class="small muted" style="flex-shrink:0;">Sort by</label>
      <select name="sort" id="sort" class="sort-select" onchange="this.form.submit()">
        <?php foreach (COURSE_SORT_OPTIONS as $key => $opt): ?>
          <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php dash_icon('chevron-down', 'sort-select-chevron'); ?>
    </form>
  </div>

  <?php if ($trending): ?>
    <div class="trending-strip">
      <div class="trending-strip-head"><span class="fire">🔥</span> Trending This Week</div>
      <div class="grid sm:grid-2 lg:grid-3">
        <?php foreach ($trending as $c) render_course_card($c); ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($trending): ?><h2 class="h3" style="margin-top:40px;">All Courses</h2><?php endif; ?>

  <?php if ($courses): ?>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:<?= $trending ? '20' : '32' ?>px;">
      <?php foreach ($courses as $c) render_course_card($c); ?>
    </div>
  <?php else: ?>
    <div class="empty-browse">
      <div class="empty-browse-icon">🔍</div>
      <h3 class="h3">No courses match your search</h3>
      <p class="muted" style="margin-top:8px;">Try a different keyword, or browse a different category.</p>
      <div class="row gap-2" style="margin-top:20px; justify-content:center;">
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-sm">Clear Filters</a>
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline btn-sm">Teach This Instead →</a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
