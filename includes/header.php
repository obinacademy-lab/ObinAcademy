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
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Sora:wght@400;500;600;700;800<?= isset($extraGoogleFont) ? '&family=' . e($extraGoogleFont) : '' ?>&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
</head>
<body id="top">
  <header class="site-header" data-site-header>    <div class="container">
      <?php render_logo(); ?>

      <div class="nav-right">
        <?php if ($user): ?>
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
        <?php endif; ?>

        <div class="nav-actions">
        <?php if ($user): ?>
          <a href="<?= e(base_url('index.php#campuses')) ?>" class="link nav-campuses<?= $currentPath === '/campuses.php' ? ' active' : '' ?>">Campuses</a>
          <div class="account-menu">
            <button class="account-trigger" aria-haspopup="true">
              <span class="account-avatar"><?= e(mb_substr($user['name'], 0, 1)) ?></span>
              <span class="account-name"><?= e(explode(' ', trim($user['name']))[0]) ?></span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>
            </button>
            <div class="account-dropdown">
              <div class="account-dropdown-head">
                <div class="name"><?= e($user['name']) ?></div>
                <div class="email"><?= e($user['email']) ?></div>
              </div>
              <a href="<?= e(base_url('dashboard.php')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>
                Dashboard
              </a>
              <a href="<?= e(base_url('dashboard/settings.php')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Settings
              </a>
              <a href="<?= e(base_url('logout.php')) ?>" class="danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path></svg>
                Sign Out
              </a>
            </div>
          </div>
        <?php else: ?>
          <a href="<?= e(base_url('login.php')) ?>" class="btn btn-ink btn-sm shine">Log In</a>
          <a href="<?= e(base_url('index.php#campuses')) ?>" class="btn btn-gold btn-sm shine">Join a Campus <span class="btn-arrow">→</span></a>
        <?php endif; ?>
        </div>

        <button class="nav-toggle" data-nav-toggle aria-label="Toggle menu" aria-expanded="false">
          <svg class="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>
          <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
        </button>
      </div>
    </div>
  </header>

  <div class="mobile-menu" data-mobile-menu>
    <div class="container">
      <?php if ($user): ?>
        <div class="mobile-menu-account">
          <span class="account-avatar"><?= e(mb_substr($user['name'], 0, 1)) ?></span>
          <div>
            <div class="name"><?= e($user['name']) ?></div>
            <div class="email"><?= e($user['email']) ?></div>
          </div>
        </div>
      <?php endif; ?>
      <a href="<?= e(base_url('index.php#campuses')) ?>"><span class="mm-icon">🎓</span>Campuses</a>
      <?php if ($user): $mmPath = current_path(); ?>
        <a href="<?= e(base_url('dashboard.php')) ?>" class="<?= str_starts_with($mmPath, '/dashboard') && $mmPath !== '/dashboard/settings.php' ? 'active' : '' ?>"><span class="mm-icon">📊</span>Dashboard</a>
        <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="<?= $mmPath === '/dashboard/settings.php' ? 'active' : '' ?>"><span class="mm-icon">⚙️</span>Settings</a>
        <hr class="mm-rule">
        <a href="<?= e(base_url('logout.php')) ?>" class="mm-danger"><span class="mm-icon">↩</span>Sign Out</a>
      <?php else: ?>
        <a href="<?= e(base_url('login.php')) ?>" class="mm-cta mm-cta-ink">Log In</a>
        <a href="<?= e(base_url('index.php#campuses')) ?>" class="mm-cta mm-cta-gold">Join a Campus →</a>
      <?php endif; ?>
      <a href="#" data-install-app hidden><span class="mm-icon">📲</span>Install the app</a>
    </div>
  </div>
  <div class="mobile-menu-scrim" data-mobile-scrim></div>

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
