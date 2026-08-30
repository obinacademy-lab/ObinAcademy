<?php
require __DIR__ . '/includes/bootstrap.php';

$GLOW_COLORS = ['blue', 'cyan', 'purple', 'pink', 'gold', 'emerald', 'orange', 'indigo'];

// Slugs point at real categories where one exists, so those tiles return
// actual courses. The rest link out too — an honest empty state ("no
// courses match yet") rather than a dead end, for industries we don't have
// courses in yet.
$industries = [
    ['Finance', 'finance', '💰'], ['Business', 'business', '💼'], ['Artificial Intelligence', 'artificial-intelligence', '🤖'],
    ['Medical', 'medical', '🩺'], ['Health & Wellness', 'health-wellness', '❤️'], ['Ecommerce', 'ecommerce', '🛒'],
    ['Agriculture', 'agriculture', '🌾'], ['Food', 'food', '🍽️'],
    ['Technology & Software Development', 'technology-software-development', '💻'], ['Marketing & Digital Marketing', 'marketing-digital-marketing', '📣'], ['Design & Creative', 'design-creative', '🎨'],
    ['Education & Teaching', 'education-teaching', '🎓'], ['Law', 'law', '⚖️'], ['Human Resources', 'human-resources', '👥'],
    ['Engineering', 'engineering', '⚙️'], ['Construction', 'construction', '🏗️'], ['Hospitality', 'hospitality', '🏨'],
    ['Fashion & Beauty', 'fashion-beauty', '👗'], ['Music & Arts', 'music-arts', '🎵'], ['Photography & Film', 'photography-film', '📷'],
    ['Sports & Fitness', 'sports-fitness', '🏋️'], ['Logistics', 'logistics', '🚚'], ['Environment', 'environment', '🌿'],
    ['Energy', 'energy', '⚡'], ['Automotive', 'automotive', '🚗'], ['Media & Journalism', 'media-journalism', '📰'],
];

$pageTitle = 'Browse All Skills — Obin Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:680px; text-align:center;">
    <span class="pill">26 Industries</span>
    <h1 style="margin-top:14px;">Skills Across Every Industry</h1>
    <p class="summary" style="margin:14px auto 0;">Whatever field you're in, there's a path to grow it here — browse by industry to find courses built for your world.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="industry-grid">
      <?php foreach ($industries as $i => [$name, $slug, $emoji]): ?>
        <a href="<?= e(base_url('courses/index.php?category=' . $slug)) ?>" class="industry-item industry-glow industry-glow-<?= $GLOW_COLORS[$i % count($GLOW_COLORS)] ?>">
          <span class="icon-wrap"><?= $emoji ?></span>
          <span class="label"><?= e($name) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="cta-panel" style="margin-top:56px; border:1px solid var(--border);">
      <div>
        <span class="eyebrow">Not Sure Where to Start?</span>
        <h3 class="h3" style="margin-top:10px; max-width:420px;">Browse every published course in one place.</h3>
      </div>
      <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary btn-lg">Explore Courses <span class="btn-arrow">→</span></a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
