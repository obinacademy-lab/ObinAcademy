<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

$q = trim(query_param('q'));
$creators = get_active_creators();
if ($q !== '') {
    $needle = mb_strtolower($q);
    $creators = array_values(array_filter($creators, function ($c) use ($needle) {
        return str_contains(mb_strtolower($c['name']), $needle)
            || str_contains(mb_strtolower((string) $c['headline']), $needle);
    }));
}

$pageTitle = 'Communities — Obin Academy';
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill">Find Your Creator</span>
    <h1 style="text-align:center;">Communities</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto; text-align:center;">
      Discover creator Schools where all their courses are organized in one place, making it
      easier to find the right creator and learning path for you.
    </p>
    <form method="get" class="search-pill browse-search" style="margin-top:26px;">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search creators by name or expertise..." value="<?= e($q) ?>">
      <button type="submit" class="btn btn-gold btn-sm">Search</button>
    </form>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <?php if ($q): ?>
    <div class="browse-toolbar" style="border-bottom:none; margin-bottom:0;">
      <div class="browse-result-info">
        <strong><?= count($creators) ?></strong> creator<?= count($creators) === 1 ? '' : 's' ?>
        matching <a href="<?= e(base_url('communities.php')) ?>" class="filter-pill">&ldquo;<?= e($q) ?>&rdquo; <span>&times;</span></a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($creators): ?>
    <div class="grid sm:grid-2 lg:grid-3" style="margin-top:24px;">
      <?php foreach ($creators as $c): $rating = round((float) $c['avg_rating'], 1); ?>
        <a href="<?= e(base_url('community.php?id=' . $c['id'])) ?>" class="creator-card">
          <div class="banner"></div>
          <div class="avatar">
            <?php if (!empty($c['avatar_url'])): ?>
              <img src="<?= e(asset_src($c['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($c['name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div class="body">
            <h3><?= e($c['name']) ?></h3>
            <div class="headline"><?= e($c['headline'] ?? 'Creator on Obin Academy') ?></div>
            <div class="stats-row">
              <div class="stat"><span class="value"><?= (int) $c['course_count'] ?></span><span class="label">Course<?= (int) $c['course_count'] === 1 ? '' : 's' ?></span></div>
              <div class="stat"><span class="value"><?= (int) $c['student_count'] ?></span><span class="label">Student<?= (int) $c['student_count'] === 1 ? '' : 's' ?></span></div>
              <?php if ((int) $c['review_count'] > 0): ?>
                <div class="stat"><span class="value">★ <?= number_format($rating, 1) ?></span><span class="label">Rating</span></div>
              <?php endif; ?>
            </div>
            <span class="view-btn">Visit Community <span class="arrow">→</span></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-browse">
      <div class="empty-browse-icon">🏫</div>
      <h3 class="h3"><?= $q ? 'No creators match your search' : 'No communities yet' ?></h3>
      <p class="muted" style="margin-top:8px;"><?= $q ? 'Try a different name or keyword.' : 'Check back soon as creators publish their first courses.' ?></p>
      <div class="row gap-2" style="margin-top:20px; justify-content:center;">
        <?php if ($q): ?><a href="<?= e(base_url('communities.php')) ?>" class="btn btn-primary btn-sm">Clear Search</a><?php endif; ?>
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline btn-sm">Start Your Own Community →</a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
