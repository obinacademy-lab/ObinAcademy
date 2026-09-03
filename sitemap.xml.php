<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';

header('Content-Type: application/xml; charset=utf-8');

/** @var array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: string}> */
$urls = [];

$staticPages = [
    ['index.php', 'daily', '1.0'],
    ['courses/index.php', 'daily', '0.9'],
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

$courses = db_all("SELECT slug, updated_at FROM courses WHERE status = 'PUBLISHED'");
foreach ($courses as $c) {
    $urls[] = [
        'loc' => base_url('courses/view.php?slug=' . $c['slug']),
        'lastmod' => date('Y-m-d', strtotime($c['updated_at'])),
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ];
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
