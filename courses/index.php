<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/course_card.php';

$q = query_param('q');
$categorySlug = query_param('category');
$sort = query_param('sort', 'newest');
if (!isset(COURSE_SORT_OPTIONS[$sort])) $sort = 'newest';

$courses = search_courses($q, $categorySlug, $sort);
$categories = get_categories();
$stats = get_platform_stats();
$hasFilters = $q !== '' || $categorySlug !== '';
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
function browse_url(string $q, string $category, string $sort, array $override = []): string {
    $params = array_merge(['q' => $q, 'category' => $category, 'sort' => $sort], $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== 'newest');
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
<section class="course-hero browse-hero page-hero-light">
  <div class="container" style="max-width:820px; text-align:center;">
    <span class="pill">Browse the Marketplace</span>
    <h1 style="text-align:center;">Find Your Next Skill</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto; text-align:center;">
      Real, practical courses from real African creators &mdash; taught by people doing the work, paid for instantly with mobile money.
    </p>
    <div class="browse-hero-stats">
      <span><strong><?= (int) $stats['course_count'] ?>+</strong> courses</span>
      <span class="dot">&middot;</span>
      <span><strong><?= (int) $stats['learner_count'] ?>+</strong> learners</span>
      <span class="dot">&middot;</span>
      <span><strong><?= (int) $stats['creator_count'] ?>+</strong> creators</span>
    </div>

    <form method="get" class="search-pill browse-search">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="What do you want to learn today?" value="<?= e($q) ?>">
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
      <button type="submit" class="btn btn-gold btn-sm">Search</button>
    </form>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <div class="chip-row">
    <a href="<?= e(browse_url($q, '', $sort)) ?>" class="chip <?= !$categorySlug ? 'active' : '' ?>">✨ All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= e(browse_url($q, $cat['slug'], $sort)) ?>" class="chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="browse-toolbar">
    <div class="browse-result-info">
      <strong><?= count($courses) ?></strong> course<?= count($courses) === 1 ? '' : 's' ?>
      <?php if ($activeCategoryName): ?>in <a href="<?= e(browse_url($q, '', $sort)) ?>" class="filter-pill"><?= e($activeCategoryName) ?> <span>&times;</span></a><?php endif; ?>
      <?php if ($q): ?>matching <a href="<?= e(browse_url('', $categorySlug, $sort)) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a><?php endif; ?>
    </div>
    <form method="get" class="sort-select-wrap">
      <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
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
