<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/school_card.php';

// Skool-style discover list: a page of schools at a time with numbered
// pagination underneath (hidden while everything fits on one page).
$schoolsPerPage = 12;
$schoolsTotal = count_school_cards();
$schoolsPages = max(1, (int) ceil($schoolsTotal / $schoolsPerPage));
$schoolsPage = min($schoolsPages, max(1, (int) query_param('page', '1')));
$schoolsPreview = get_school_cards('', [], SCHOOL_POPULARITY_ORDER . ', u.created_at ASC', $schoolsPerPage, ($schoolsPage - 1) * $schoolsPerPage);
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

$pageTitle = 'Obin Academy — Learn New Skills, Teach What You Know';
$pageDescription = "Buy only the courses you want on Obin Academy — Finance, AI, Business, Tech and more — taught by East Africa's best creators. Pay instantly with MTN or Airtel Mobile Money.";
$structuredData = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            'name' => 'Obin Academy',
            'url' => base_url('index.php'),
            'logo' => base_url('apple-touch-icon.png'),
        ],
        [
            '@type' => 'WebSite',
            'name' => 'Obin Academy',
            'url' => base_url('index.php'),
        ],
    ],
];
require __DIR__ . '/includes/header.php';
?>

<div class="home-discover">
  <div class="home-discover-hero">
    <h1>Discover schools</h1>
    <p class="sub">or <a href="<?= e(base_url('become-creator.php')) ?>">create your own school</a></p>
  </div>

  <div class="home-discover-search">
    <form action="<?= e(base_url('courses/index.php')) ?>" method="get" class="hero-search-v3 home-search-bare" style="margin:0;">
      <?php dash_icon('search'); ?>
      <input type="search" name="q" placeholder="Search for anything" aria-label="Search schools and courses" enterkeyhint="search">
    </form>
  </div>

  <nav class="home-discover-chips" data-home-chips aria-label="Categories">
    <a href="<?= e(base_url('courses/index.php')) ?>" class="home-discover-chip active">🔥 Trending</a>
    <?php foreach ($categories as $ci => $cat): ?>
      <a href="<?= e(base_url('courses/index.php?category=' . $cat['slug'])) ?>" class="home-discover-chip<?= $ci >= 7 ? ' is-extra' : '' ?>"<?= $ci >= 7 ? ' hidden' : '' ?>><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <?php if (count($categories) > 7): ?>
      <button type="button" class="home-discover-chip home-discover-chip-more" data-home-chips-more aria-expanded="false">More…</button>
    <?php endif; ?>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="home-discover-chip-filter" aria-label="More filters" title="More filters">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"></circle><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"></circle><circle cx="9" cy="18" r="2" fill="currentColor" stroke="none"></circle></svg>
      Filter
    </a>
  </nav>

  <?php if ($schoolsPreview): ?>
    <div class="home-discover-grid-wrap">
      <div class="home-discover-grid">
        <?php foreach ($schoolsPreview as $school) render_school_card($school); ?>
      </div>
      <?php if ($schoolsPages > 1):
        // 1 … (page-1) page (page+1) … last, Skool-style.
        $pageLink = fn(int $n): string => base_url($n === 1 ? 'index.php' : 'index.php?page=' . $n);
        $nums = array_values(array_unique(array_filter([1, $schoolsPage - 1, $schoolsPage, $schoolsPage + 1, $schoolsPages], fn($n) => $n >= 1 && $n <= $schoolsPages)));
        sort($nums);
      ?>
        <nav class="home-pager" aria-label="Pages of schools">
          <?php if ($schoolsPage > 1): ?><a href="<?= e($pageLink($schoolsPage - 1)) ?>" rel="prev">Previous</a><?php else: ?><span class="dis">Previous</span><?php endif; ?>
          <?php $prev = 0; foreach ($nums as $n): ?>
            <?php if ($n - $prev > 1): ?><i>…</i><?php endif; ?>
            <?php if ($n === $schoolsPage): ?><span class="on" aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($pageLink($n)) ?>"><?= $n ?></a><?php endif; ?>
            <?php $prev = $n; endforeach; ?>
          <?php if ($schoolsPage < $schoolsPages): ?><a href="<?= e($pageLink($schoolsPage + 1)) ?>" rel="next">Next</a><?php else: ?><span class="dis">Next</span><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script src="<?= e(versioned_asset('assets/js/home-discover.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
