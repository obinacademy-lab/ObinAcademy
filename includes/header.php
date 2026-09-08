<?php
/**
 * Shared page shell. Expects (optionally) before include:
 *   $pageTitle       — <title> and og:title (falls back to the site default)
 *   $pageDescription — meta description and og:description (falls back to the site default)
 *   $pageImage       — absolute or base_url()-relative image for social share previews (falls back to a default photo)
 *   $pageType        — og:type, e.g. 'website' (default) or 'article'
 *   $noindex         — set true on pages that shouldn't be indexed (defaults false)
 * Usage: require __DIR__ . '/../includes/header.php';
 */
$user = current_user();
$navLinks = [
    '/index.php' => 'Home',
    '/courses/index.php' => 'Explore Courses',
    '/stories.php' => 'Stories',
    '/about.php' => 'About Us',
    '/contact.php' => 'Contact',
];
$currentPath = current_path();

$seoTitle = $pageTitle ?? 'Obin Academy — Learn New Skills, Teach What You Know';
$seoDescription = $pageDescription ?? 'Obin Academy is Africa\'s learning marketplace — practical courses in finance, tech, business and more, taught by real creators and paid for instantly with MTN or Airtel Mobile Money.';
$seoImage = isset($pageImage) ? (str_starts_with($pageImage, 'http') ? $pageImage : base_url($pageImage)) : base_url('assets/img/hero-couch-learner.jpg');
$seoType = $pageType ?? 'website';
$canonicalUrl = base_url(ltrim($currentPath, '/'));
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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
</head>
<body id="top">
  <header class="site-header" data-site-header>
    <div class="container">
      <?php render_logo(true); ?>

      <nav class="nav-links">
        <?php foreach ($navLinks as $href => $label): ?>
          <a href="<?= e(base_url($href)) ?>" class="<?= $currentPath === $href ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="nav-actions">
        <?php if ($user): ?>
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
              <a href="<?= e(base_url('profile.php?id=' . $user['id'])) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a7 7 0 0 1 7-7h2a7 7 0 0 1 7 7v1"></path></svg>
                My Profile
              </a>
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
          <a href="<?= e(base_url('login.php')) ?>" class="link">Log In</a>
          <a href="<?= e(base_url('signup.php')) ?>" class="btn btn-primary btn-sm shine">Start Learning <span class="btn-arrow">→</span></a>
        <?php endif; ?>
      </div>

      <button class="nav-toggle" data-nav-toggle aria-label="Toggle menu" aria-expanded="false">
        <svg class="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>
        <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
      </button>
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
      <?php
        $navIcons = ['/index.php' => '🏠', '/courses/index.php' => '📚', '/stories.php' => '💬', '/about.php' => 'ℹ️', '/contact.php' => '✉️'];
      ?>
      <?php foreach ($navLinks as $href => $label): ?>
        <a href="<?= e(base_url($href)) ?>" class="<?= $currentPath === $href ? 'active' : '' ?>"><span class="mm-icon"><?= $navIcons[$href] ?? '' ?></span><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($user): ?>
        <a href="<?= e(base_url('profile.php?id=' . $user['id'])) ?>"><span class="mm-icon">👤</span>My Profile</a>
        <a href="<?= e(base_url('dashboard.php')) ?>"><span class="mm-icon">📊</span>Dashboard</a>
        <a href="<?= e(base_url('dashboard/settings.php')) ?>"><span class="mm-icon">⚙️</span>Settings</a>
        <a href="<?= e(base_url('logout.php')) ?>" class="mm-danger"><span class="mm-icon">↩</span>Sign Out</a>
      <?php else: ?>
        <a href="<?= e(base_url('login.php')) ?>"><span class="mm-icon">🔑</span>Log In</a>
        <a href="<?= e(base_url('signup.php')) ?>" class="mm-cta">Start Learning →</a>
      <?php endif; ?>
    </div>
  </div>

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
