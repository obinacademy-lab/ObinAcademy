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

<section class="wa-community-section">
  <div class="container">
    <div class="wa-community-card">
      <div class="wa-community-icon">
        <svg viewBox="0 0 24 24" width="26" height="26"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"/></svg>
      </div>
      <div class="wa-community-copy">
        <h2>Join Our Students WhatsApp Community</h2>
        <p>Connect with learners from every school on Obin Academy — ask questions, share wins, and hear about new courses first.</p>
      </div>
      <a href="<?= e(WHATSAPP_COMMUNITY_URL) ?>" target="_blank" rel="noopener noreferrer" class="btn wa-community-btn btn-lg">
        Join the Community <span class="btn-arrow">→</span>
      </a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
