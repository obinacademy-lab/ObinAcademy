<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/school_card.php';

$q = query_param('q');
$categorySlug = query_param('category');
// 'popular' (purchases + views, see SCHOOL_POPULARITY_ORDER) is the default browse
// order now, not 'newest' — so 'popular' is the value treated as "no sort
// filter applied" throughout this page (browse_url()'s URL-cleaning, the
// chip active-states, $hasFilters), the same role 'newest' used to play.
$sort = query_param('sort', 'popular');
if (!isset(SCHOOL_SORT_OPTIONS[$sort])) $sort = 'popular';

$schools = search_schools($q, $categorySlug, $sort);
$categories = get_categories();
$stats = get_platform_stats();
$hasFilters = $q !== '' || $categorySlug !== '' || $sort !== 'popular';
$trending = !$hasFilters ? get_trending_schools(3) : [];

$activeCategoryName = null;
foreach ($categories as $cat) {
    if ($cat['slug'] === $categorySlug) { $activeCategoryName = $cat['name']; break; }
}

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

/** Rebuilds the browse URL with one param overridden, keeping the others intact. */
function browse_url(string $q, string $category, string $sort, array $override = []): string {
    $params = array_merge(['q' => $q, 'category' => $category, 'sort' => $sort], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 'popular');
    return base_url('courses/index.php') . ($params ? '?' . http_build_query($params) : '');
}

if ($activeCategoryName) {
    $pageTitle = "$activeCategoryName Schools — Obin Academy";
    $pageDescription = "Explore schools teaching $activeCategoryName on Obin Academy — practical, real-world skills taught by East African creators, paid for instantly with mobile money.";
} else {
    $pageTitle = 'Explore Schools — Obin Academy';
    $pageDescription = 'Browse schools teaching Finance, Tech, Business, Marketing, and more — taught by real creators and paid for instantly with MTN or Airtel Mobile Money.';
}
$noindex = $q !== ''; // search-result pages: let the base browse/category pages get indexed, not every query variation
require __DIR__ . '/../includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1><?= $activeCategoryName ? e($activeCategoryName) . ' Schools' : 'Find Your Next School' ?></h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">
      Real schools run by real African creators &mdash; taught by people doing the work, paid for instantly with mobile money.
    </p>

    <form method="get" class="hero-search-v3">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="What do you want to learn today?" value="<?= e($q) ?>">
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
      <button type="submit">Search</button>
    </form>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<div class="container" style="padding-top:20px; padding-bottom:72px;">
  <div class="chip-row chip-row-scroll">
    <a href="<?= e(browse_url($q, '', 'popular')) ?>" class="chip <?= (!$categorySlug && $sort === 'popular') ? 'active' : '' ?>">✨ All</a>
    <a href="<?= e(browse_url($q, $categorySlug, $sort === 'popular' ? 'newest' : 'popular')) ?>" class="chip <?= $sort === 'popular' ? 'active' : '' ?>">🔥 Trending</a>
    <a href="<?= e(browse_url($q, $categorySlug, $sort === 'rating' ? 'popular' : 'rating')) ?>" class="chip <?= $sort === 'rating' ? 'active' : '' ?>">⭐ Top</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= e(browse_url($q, $cat['slug'], $sort)) ?>" class="chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="browse-toolbar">
    <div class="browse-result-info">
      <?php if ($activeCategoryName): ?>in <a href="<?= e(browse_url($q, '', $sort)) ?>" class="filter-pill"><?= e($activeCategoryName) ?> <span>&times;</span></a><?php endif; ?>
      <?php if ($q): ?>matching <a href="<?= e(browse_url('', $categorySlug, $sort)) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a><?php endif; ?>
    </div>
    <form method="get" class="sort-select-wrap">
      <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <label for="sort" class="small muted" style="flex-shrink:0;">Sort by</label>
      <select name="sort" id="sort" class="sort-select" onchange="this.form.submit()">
        <?php foreach (SCHOOL_SORT_OPTIONS as $key => $opt): ?>
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
        <?php foreach ($trending as $school) render_school_card($school); ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($trending): ?><h2 class="h3" style="margin-top:40px;">All Schools</h2><?php endif; ?>

  <?php if ($schools): ?>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:<?= $trending ? '20' : '32' ?>px;">
      <?php foreach ($schools as $school) render_school_card($school); ?>
    </div>
  <?php else: ?>
    <div class="empty-browse">
      <div class="empty-browse-icon">🔍</div>
      <h3 class="h3">No schools match your search</h3>
      <p class="muted" style="margin-top:8px;">Try a different keyword, or browse a different category.</p>
      <div class="row gap-2" style="margin-top:20px; justify-content:center;">
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-sm">Clear Filters</a>
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline btn-sm">Teach This Instead →</a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
