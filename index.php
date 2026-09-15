<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/course_card.php';

$catalogPreview = get_featured_courses(6);

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

<?php if ($catalogPreview): ?>
<section class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">The Catalog</span>
      <h2 class="h2" style="margin-top:10px;">See What's Waiting Inside</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">A preview of real courses on Obin Academy — browse the full catalog free, then pay only for the one you want.</p>
    </div>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:36px;">
      <?php foreach ($catalogPreview as $c): render_course_card($c); endforeach; ?>
    </div>
    <div class="text-center" style="margin-top:40px;">
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-lg">Browse Courses <span class="btn-arrow">→</span></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
