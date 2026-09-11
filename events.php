<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$q = query_param('q');
$categorySlug = query_param('category');

$events = search_events($q, $categorySlug);
$categories = get_categories();

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
function events_browse_url(string $q, string $category): string {
    $params = array_filter(['q' => $q, 'category' => $category], fn($v) => $v !== '');
    return base_url('events.php') . ($params ? '?' . http_build_query($params) : '');
}

if ($activeCategoryName) {
    $pageTitle = "$activeCategoryName Events — Obin Academy";
    $pageDescription = "Upcoming $activeCategoryName events on Obin Academy — workshops, webinars, and meetups from East African creators.";
} else {
    $pageTitle = 'Upcoming Events — Obin Academy';
    $pageDescription = 'Workshops, webinars, and meetups from Obin Academy creators — book your ticket and pay instantly with MTN or Airtel Mobile Money.';
}
$noindex = $q !== '';
require __DIR__ . '/includes/header.php';
?>
<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1><span class="hero-shine"><?= $activeCategoryName ? e($activeCategoryName) . ' Events' : 'Upcoming Events' ?></span></h1>
    <p class="summary" style="margin-left:auto; margin-right:auto;">
      Workshops, webinars, and meetups from Obin Academy creators &mdash; book your ticket and pay instantly with mobile money.
    </p>

    <form method="get" class="hero-search-v3">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search upcoming events" value="<?= e($q) ?>">
      <?php if ($categorySlug): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
      <button type="submit">Search</button>
    </form>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <div class="chip-row chip-row-scroll">
    <a href="<?= e(events_browse_url($q, '')) ?>" class="chip <?= !$categorySlug ? 'active' : '' ?>">✨ All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="<?= e(events_browse_url($q, $cat['slug'])) ?>" class="chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="browse-toolbar" style="justify-content:flex-end;">
    <div class="browse-result-info">
      <?php if ($activeCategoryName): ?>in <a href="<?= e(events_browse_url($q, '')) ?>" class="filter-pill"><?= e($activeCategoryName) ?> <span>&times;</span></a><?php endif; ?>
      <?php if ($q): ?>matching <a href="<?= e(events_browse_url('', $categorySlug)) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a><?php endif; ?>
    </div>
  </div>

  <?php if ($events): ?>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:24px;">
      <?php foreach ($events as $c) render_course_card($c); ?>
    </div>
  <?php else: ?>
    <div class="empty-browse">
      <div class="empty-browse-icon">🎟</div>
      <h3 class="h3">No upcoming events match your search</h3>
      <p class="muted" style="margin-top:8px;">Try a different keyword, or check back soon.</p>
      <div class="row gap-2" style="margin-top:20px; justify-content:center;">
        <a href="<?= e(base_url('events.php')) ?>" class="btn btn-primary btn-sm">Clear Filters</a>
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-outline btn-sm">Browse Courses Instead →</a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
