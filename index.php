<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$catalogPreview = get_featured_courses(6);
$categories = get_categories();
$categoryEmoji = [
    'finance' => '💰', 'business' => '💼', 'technology-software-development' => '💻',
    'marketing-digital-marketing' => '📣', 'design-creative' => '🎨', 'ecommerce' => '🛒',
    'education-teaching' => '🎓', 'agriculture' => '🌾', 'health-wellness' => '❤️',
    'artificial-intelligence' => '🤖', 'tech' => '💻',
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
    <h1>Discover courses</h1>
    <p class="sub">or <a href="<?= e(base_url('become-creator.php')) ?>">become a creator</a></p>
  </div>

  <form action="<?= e(base_url('courses/index.php')) ?>" method="get" class="home-discover-search">
    <div class="home-discover-search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.35-4.35"></path></svg>
      <input type="text" name="q" placeholder="Search for anything">
    </div>
  </form>

  <nav class="home-discover-chips">
    <a href="<?= e(base_url('courses/index.php')) ?>" class="home-discover-chip active">🔥 Trending</a>
    <?php foreach (array_slice($categories, 0, 7) as $cat): ?>
      <a href="<?= e(base_url('courses/index.php?category=' . $cat['slug'])) ?>" class="home-discover-chip"><?= $categoryEmoji[$cat['slug']] ?? '📚' ?> <?= e($cat['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="home-discover-chip-filter" aria-label="More filters" title="More filters">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"></circle><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"></circle><circle cx="9" cy="18" r="2" fill="currentColor" stroke="none"></circle></svg>
    </a>
  </nav>

  <?php if ($catalogPreview): ?>
    <div class="home-discover-grid-wrap">
      <div class="home-discover-grid">
        <?php foreach ($catalogPreview as $c): ?>
          <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" class="home-discover-card">
            <div class="thumb">
              <?php if (!empty($c['thumbnail_url'])): ?>
                <img src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="" loading="lazy">
              <?php else: ?>
                <div class="placeholder">Obin Academy</div>
              <?php endif; ?>
              <span class="avatar">
                <?php if (!empty($c['creator_avatar_url'])): ?>
                  <img src="<?= e(asset_src($c['creator_avatar_url'])) ?>" alt="">
                <?php else: ?><?= e(mb_substr($c['creator_name'], 0, 1)) ?><?php endif; ?>
              </span>
            </div>
            <div class="body">
              <h3><?= e($c['title']) ?></h3>
              <p class="creator"><?= e($c['creator_name']) ?> &middot; <?= e($c['category_name']) ?></p>
              <p class="desc"><?= e($c['summary']) ?></p>
              <p class="meta">
                <strong><?= number_format((int) $c['student_count']) ?></strong> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?>
                <span class="dot"></span>
                <strong><?= number_format((int) $c['view_count']) ?></strong> view<?= (int) $c['view_count'] === 1 ? '' : 's' ?>
                <span class="dot"></span>
                <?= (float) $c['price'] > 0 ? e(format_money((float) $c['price'])) : 'Free' ?>
              </p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="home-discover-cta">
        <a href="<?= e(base_url('courses/index.php')) ?>">Browse all courses <span class="btn-arrow">→</span></a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
