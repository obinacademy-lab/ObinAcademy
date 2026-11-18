<?php
/**
 * Shared page shell. Expects (optionally) before include:
 *   $pageTitle       — <title> and og:title (falls back to the site default)
 *   $pageDescription — meta description and og:description (falls back to the site default)
 *   $pageImage       — absolute or base_url()-relative image for social share previews (falls back to a default photo)
 *   $pageType        — og:type, e.g. 'website' (default) or 'article'
 *   $noindex         — set true on pages that shouldn't be indexed (defaults false)
 *   $canonicalUrl    — the page's own canonical address, for pages that differ only by their query string (campus.php)
 * Usage: require __DIR__ . '/../includes/header.php';
 */
$user = current_user();
$currentPath = current_path();
require_once __DIR__ . '/affiliates.php';
require_once __DIR__ . '/platform.php';
ensure_referral_cookie(); // remembers ?ref=CODE for up to 30 days, in case this visitor signs up later

$seoTitle = $pageTitle ?? 'Obin Academy Campuses — Learn a skill for one year';
$seoDescription = $pageDescription ?? 'Obin Academy Campuses: year-long programmes in business, trading, content creation, fitness, AI and e-commerce, taught by professors we choose. Lessons, PDF guides and a weekly live class, paid monthly or yearly with MTN or Airtel Mobile Money.';
$seoImage = isset($pageImage) ? (str_starts_with($pageImage, 'http') ? $pageImage : base_url($pageImage)) : base_url('assets/img/hero-couch-learner.jpg');
$seoType = $pageType ?? 'website';
$canonicalUrl = $canonicalUrl ?? base_url(ltrim($currentPath, '/')); // a page whose address carries ?slug= sets its own
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($seoTitle) ?></title>
  <meta name="description" content="<?= e($seoDescription) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, follow"><?php endif; ?>

  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="icon" href="<?= e(versioned_asset('favicon-32x32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="icon" href="<?= e(versioned_asset('favicon-16x16.png')) ?>" type="image/png" sizes="16x16">
  <?php require __DIR__ . '/pwa-head.php'; ?>

  <meta property="og:site_name" content="Obin Academy">
  <meta property="og:type" content="<?= e($seoType) ?>">
  <meta property="og:title" content="<?= e($seoTitle) ?>">
  <meta property="og:description" content="<?= e($seoDescription) ?>">
  <meta property="og:image" content="<?= e($seoImage) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($seoTitle) ?>">
  <meta name="twitter:description" content="<?= e($seoDescription) ?>">
  <meta name="twitter:image" content="<?= e($seoImage) ?>">

  <?php if (!empty($structuredData)): ?>
    <script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Sora:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400;1,6..72,500<?= isset($extraGoogleFont) ? '&family=' . e($extraGoogleFont) : '' ?>&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/platform.css')) ?>">
</head>
<body id="top" class="pl">
<?php
$plBell = '';
if ($user) {
    ob_start(); ?>
          <div class="site-notif-menu account-menu">
            <button type="button" class="site-icon-btn" aria-haspopup="true" aria-label="Notifications" data-notif-trigger>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              <span class="site-notif-dot hidden" data-notif-dot></span>
            </button>
            <div class="account-dropdown site-notif-dropdown">
              <div class="account-dropdown-head" style="display:flex; align-items:center; justify-content:space-between;">
                <span class="name" style="font-size:13px;">Notifications</span>
                <button type="button" class="site-notif-mark-read hidden" data-notif-mark-read>Mark all read</button>
              </div>
              <div data-notif-list>
                <p class="muted small" style="padding:14px;">No notifications yet.</p>
              </div>
            </div>
          </div>
<?php
    $plBell = ob_get_clean();
}
pl_render_head($user, ['bell' => $plBell]);
?>

  <?php
    $flashError = flash_get('error');
    $flashSuccess = flash_get('success');
  ?>
  <?php if ($flashError): ?>
    <div class="container" style="margin-top:20px;"><div class="alert alert-error" data-flash><?= e($flashError) ?></div></div>
  <?php endif; ?>
  <?php if ($flashSuccess): ?>
    <div class="container" style="margin-top:20px;"><div class="alert alert-success" data-flash><?= e($flashSuccess) ?></div></div>
  <?php endif; ?>

  <main>
