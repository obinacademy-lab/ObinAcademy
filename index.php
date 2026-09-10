<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$stats = get_platform_stats();

// Skool-style numbered pagination for the homepage course grid, instead of
// a single curated batch — the "Explore Courses" page still exists for
// search/category/sort; this is just a plain paged view of everything.
const HOME_COURSES_PER_PAGE = 9;
$totalCourseCount = (int) $stats['course_count'];
$totalPages = max(1, (int) ceil($totalCourseCount / HOME_COURSES_PER_PAGE));
$page = max(1, min($totalPages, (int) query_param('page', '1')));
$courses = get_course_cards('', [], POPULARITY_ORDER, HOME_COURSES_PER_PAGE, ($page - 1) * HOME_COURSES_PER_PAGE);

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
    <h1>Discover Courses</h1>
    <p class="hero-subline">or <a href="<?= e(base_url('become-creator.php')) ?>" class="hero-accent-link">teach your own</a></p>
    <p class="summary" style="margin-left:auto; margin-right:auto;">
      Practical skills in Finance, Tech, Business and more — taught by real African creators, paid for instantly with MTN or Airtel Mobile Money.
    </p>

    <form method="get" action="<?= e(base_url('courses/index.php')) ?>" class="hero-search-v3">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search for anything">
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

<div class="container" style="padding-top:28px;">
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

<section class="section" style="padding-top:32px;">
  <div class="container">
    <div class="row between wrap gap-3" style="align-items:flex-end; margin-bottom: 36px;">
      <div>
        <div class="row gap-2 wrap" style="align-items:center;">
          <span class="eyebrow">Fresh On The Marketplace</span>
          <?php if ((int) $stats['course_count'] > 0): ?>
            <span class="live-pill"><span class="live-dot"></span><?= (int) $stats['course_count'] ?> course<?= (int) $stats['course_count'] === 1 ? '' : 's' ?> live now</span>
          <?php endif; ?>
        </div>
        <h2 class="h2" style="margin-top:10px;">Popular Courses</h2>
      </div>
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary">Explore All Courses <span class="btn-arrow">→</span></a>
    </div>

    <?php if ($courses): ?>
      <div class="grid sm:grid-2 lg:grid-3" id="courses">
        <?php foreach ($courses as $c) render_course_card($c); ?>
      </div>
      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Course pages">
          <?php if ($page > 1): ?>
            <a href="<?= e(base_url('index.php?page=' . ($page - 1) . '#courses')) ?>" class="page-link page-prev">Previous</a>
          <?php endif; ?>
          <?php foreach (paginate_window($page, $totalPages) as $p): ?>
            <?php if ($p === null): ?>
              <span class="page-ellipsis">…</span>
            <?php else: ?>
              <a href="<?= e(base_url('index.php?page=' . $p . '#courses')) ?>" class="page-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($page < $totalPages): ?>
            <a href="<?= e(base_url('index.php?page=' . ($page + 1) . '#courses')) ?>" class="page-link page-next">Next</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php else: ?>
      <div class="card" style="padding:48px; text-align:center; border-style:dashed; color:var(--muted);">No courses published yet. Check back soon.</div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
