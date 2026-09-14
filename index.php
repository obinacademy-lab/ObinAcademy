<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$stats = get_platform_stats();

// A curated highlight, not the full list — keeps the homepage from feeling
// crowded. The complete set lives on skills.php. Slugs point at real
// categories so every tile here actually returns courses.
$industries = [
    ['Finance', 'finance', '💰'], ['Business', 'business', '💼'], ['Artificial Intelligence', 'artificial-intelligence', '🤖'],
    ['Technology', 'technology-software-development', '💻'], ['Marketing', 'marketing-digital-marketing', '📣'],
    ['Design', 'design-creative', '🎨'], ['Ecommerce', 'ecommerce', '🛒'], ['Education', 'education-teaching', '🎓'],
];

$pageTitle = 'Obin Academy — Learn New Skills, Teach What You Know';
$pageDescription = "Learn practical skills in Finance, Tech, Business and more from East Africa's best creators — or turn your own expertise into courses. Pay instantly with MTN or Airtel Mobile Money.";
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
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => base_url('courses/index.php') . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ],
];
require __DIR__ . '/includes/header.php';
?>

<section class="home-hero-v3">
  <div class="container" style="text-align:center;">
    <h1><span class="hero-shine">Discover Courses</span></h1>
    <p class="hero-subline">or <a href="<?= e(base_url('become-creator.php')) ?>" class="hero-accent-link">teach your own</a></p>
    <p class="summary" style="margin-left:auto; margin-right:auto;">
      Master practical skills in AI, Business, Finance, Technology, and more — taught by experienced African creators and accessible with instant MTN &amp; Airtel Mobile Money payments.
    </p>

    <form method="get" action="<?= e(base_url('courses/index.php')) ?>" class="hero-search-v3">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search for anything">
      <button type="submit">Search</button>
    </form>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<div class="container" style="padding-top:18px;">
  <div class="chip-row chip-row-scroll">
    <a href="<?= e(base_url('courses/index.php')) ?>" class="chip">✨ All</a>
    <a href="<?= e(base_url('courses/index.php?sort=popular')) ?>" class="chip">🔥 Trending</a>
    <a href="<?= e(base_url('courses/index.php?price=free')) ?>" class="chip">🆓 Free</a>
    <a href="<?= e(base_url('courses/index.php?price=paid')) ?>" class="chip">💳 Paid</a>
    <a href="<?= e(base_url('courses/index.php?sort=rating')) ?>" class="chip">⭐ Top</a>
    <?php foreach ($industries as [$name, $slug, $emoji]): ?>
      <a href="<?= e(base_url('courses/index.php?category=' . $slug)) ?>" class="chip"><?= $emoji ?> <?= e($name) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(base_url('skills.php')) ?>" class="chip">More…</a>
  </div>
</div>

<section class="section" style="padding-top:22px;">
  <div class="container" style="text-align:center; max-width:640px;">
    <div class="row gap-2 wrap" style="align-items:center; justify-content:center;">
      <span class="eyebrow">One Subscription</span>
      <?php if ((int) $stats['course_count'] > 0): ?>
        <span class="live-pill"><span class="live-dot"></span><?= (int) $stats['course_count'] ?> course<?= (int) $stats['course_count'] === 1 ? '' : 's' ?> live now</span>
      <?php endif; ?>
    </div>
    <h2 class="h2" style="margin-top:10px;">Every Course. One Membership.</h2>
    <p class="summary" style="margin:14px auto 0;">Business, Finance, AI, Technology, Marketing, Design and more — every course on Obin Academy is included the moment you subscribe.</p>
    <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-primary btn-lg" style="margin-top:24px;">Subscribe to Unlock the Full Catalog <span class="btn-arrow">→</span></a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
