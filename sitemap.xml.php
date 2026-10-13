<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

header('Content-Type: application/xml; charset=utf-8');

/** @var array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: string}> */
$urls = [];

// Obin Academy runs as campuses: the home page, each published campus and the plain information pages.
// Creators' courses and school pages are no longer public, so they are left out.
$staticPages = [
    ['index.php', 'daily', '1.0'],
    ['stories.php', 'weekly', '0.5'],
    ['about.php', 'monthly', '0.4'],
    ['teach.php', 'monthly', '0.5'],
    ['contact.php', 'monthly', '0.3'],
    ['privacy.php', 'yearly', '0.2'],
    ['terms.php', 'yearly', '0.2'],
];
foreach ($staticPages as [$path, $changefreq, $priority]) {
    $urls[] = ['loc' => base_url($path), 'changefreq' => $changefreq, 'priority' => $priority];
}

foreach (get_published_campuses() as $c) {
    $urls[] = [
        'loc' => base_url('campus.php?slug=' . $c['slug']),
        'lastmod' => date('Y-m-d', strtotime($c['updated_at'])),
        'changefreq' => 'weekly',
        'priority' => '0.9',
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
