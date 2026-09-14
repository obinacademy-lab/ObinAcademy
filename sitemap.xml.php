<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

header('Content-Type: application/xml; charset=utf-8');

/** @var array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: string}> */
$urls = [];

// courses/index.php and courses/view.php are deliberately left out — every
// course requires an active subscription now (see
// require_course_access_or_redirect() in includes/subscriptions.php), so a
// crawler hitting either just gets redirected to subscribe.php with nothing
// to index. subscribe.php is the real crawlable entry point instead.
$staticPages = [
    ['index.php', 'daily', '1.0'],
    ['subscribe.php', 'daily', '0.9'],
    ['skills.php', 'weekly', '0.6'],
    ['stories.php', 'weekly', '0.5'],
    ['become-creator.php', 'monthly', '0.6'],
    ['about.php', 'monthly', '0.4'],
    ['contact.php', 'monthly', '0.3'],
    ['privacy.php', 'yearly', '0.2'],
    ['terms.php', 'yearly', '0.2'],
];
foreach ($staticPages as [$path, $changefreq, $priority]) {
    $urls[] = ['loc' => base_url($path), 'changefreq' => $changefreq, 'priority' => $priority];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= e($u['loc']) ?></loc>
    <?php if (!empty($u['lastmod'])): ?><lastmod><?= e($u['lastmod']) ?></lastmod><?php endif; ?>
    <changefreq><?= e($u['changefreq']) ?></changefreq>
    <priority><?= e($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
