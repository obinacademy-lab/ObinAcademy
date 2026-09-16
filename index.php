<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/school_card.php';

$schoolsPreview = get_featured_schools(6);
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
    <form action="<?= e(base_url('courses/index.php')) ?>" method="get" class="hero-search-v3" style="margin:0;">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search for anything">
      <button type="submit">Search</button>
    </form>
  </div>

  <nav class="home-discover-chips">
    <a href="<?= e(base_url('courses/index.php')) ?>" class="home-discover-chip active">🔥 Trending</a>
    <?php foreach (array_slice($categories, 0, 7) as $cat): ?>
      <a href="<?= e(base_url('courses/index.php?category=' . $cat['slug'])) ?>" class="home-discover-chip"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
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
      <div class="home-discover-cta">
        <a href="<?= e(base_url('courses/index.php')) ?>">Browse all schools <span class="btn-arrow">→</span></a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
