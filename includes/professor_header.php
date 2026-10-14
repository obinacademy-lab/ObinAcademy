<?php
/**
 * The professor's dashboard shell: a floating dock at the top (Overview, Studio, Students, Community,
 * My Pay, Settings) instead of the student sidebar. Included by dashboard_header.php for professor
 * accounts; dashboard_footer.php closes it. Styles: assets/css/professor.css. Motion: assets/js/professor.js.
 */
$profShell = true;
$profCampus = professor_current_campus($user);
$profCampusQ = $profCampus && count(professor_campuses($user)) > 1 ? '?campus=' . (int) $profCampus['id'] : '';
$profActive = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [(int) ($profCampus['id'] ?? 0)])['n'] ?? 0);
$profUnread = $profCampus ? professor_unread_messages((int) $profCampus['id'], (int) $user['id']) : 0;
$profPath = current_path();
$profTabs = [
    ['/dashboard/professor/index.php', 'Overview', '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>', 0],
    ['/dashboard/campus-studio.php', 'Studio', '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2zM22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>', 0],
    ['/dashboard/professor/students.php', 'Students', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>', $profActive],
    ['/campus-learn.php?campus=' . ($profCampus['slug'] ?? '') . '&view=community', 'Community', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>', $profUnread],
    ['/dashboard/professor.php', 'My Pay', '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/>', 0],
    ['/dashboard/settings.php', 'Settings', '<circle cx="12" cy="12" r="3"/><path d="M12 1v3M12 20v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M1 12h3M20 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>', 0],
];
$profFirst = explode(' ', trim($user['name']))[0];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle ?? 'Professor — Obin Academy') ?></title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="icon" href="<?= e(versioned_asset('favicon-32x32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= e(versioned_asset('apple-touch-icon.png')) ?>" sizes="180x180">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Geist+Mono:wght@400;500;600;700&family=Newsreader:ital,opsz,wght@0,6..72,400;1,6..72,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/dashboard.css')) ?>">
  <?php foreach ($pageCss ?? [] as $css): ?><link rel="stylesheet" href="<?= e(versioned_asset($css)) ?>">
  <?php endforeach; ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/professor.css')) ?>">
  <?php require __DIR__ . '/pwa-head.php'; ?>
</head>
<body class="pf-body">
<header class="pf-dock" data-pf-dock>
  <div class="pf-dock-in">
    <a class="pf-logo" href="<?= e(base_url('dashboard/professor/index.php')) ?>"><i><svg viewBox="0 0 24 24" fill="none" stroke="#0a0d1c" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg></i><span>Obin <b>Academy</b></span></a>
    <nav class="pf-tabs" data-pf-tabs aria-label="Professor">
      <span class="pf-ink" data-pf-ink aria-hidden="true"></span>
      <?php foreach ($profTabs as [$pfHref, $pfLabel, $pfIcon, $pfBadge]):
          $pfOn = $profPath === strtok($pfHref, '?') && !str_contains($pfHref, 'campus-learn');
          $pfUrl = base_url(ltrim($pfHref, '/')) . (str_contains($pfHref, '?') ? '' : $profCampusQ); ?>
        <a href="<?= e($pfUrl) ?>" class="<?= $pfOn ? 'on' : '' ?>"<?= $pfOn ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $pfIcon ?></svg><span class="t"><?= e($pfLabel) ?></span><?php if ($pfBadge > 0): ?><em><?= $pfBadge ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="pf-me">
      <button type="button" class="pf-me-btn" data-pf-menu aria-haspopup="true" aria-expanded="false">
        <span class="av"><?= e(professor_initials($user['name'])) ?></span>
        <span class="who"><b><?= e($user['name']) ?></b><span class="badge">PROFESSOR</span></span>
      </button>
      <div class="pf-menu" data-pf-menu-list hidden>
        <div class="pf-menu-head"><b><?= e($user['name']) ?></b><small><?= e($user['email']) ?></small></div>
        <a href="<?= e(base_url('dashboard/settings.php')) ?>">Settings</a>
        <a href="<?= e(base_url('index.php')) ?>" target="_blank" rel="noopener">Visit site</a>
        <a href="<?= e(base_url('logout.php')) ?>" class="out">Sign out</a>
      </div>
    </div>
  </div>
</header>
<main class="pf-wrap">
  <?php $flashError = flash_get('error'); $flashSuccess = flash_get('success'); ?>
  <?php if ($flashError): ?><div class="alert alert-error pf-flash" data-flash><?= e($flashError) ?></div><?php endif; ?>
  <?php if ($flashSuccess): ?><div class="alert alert-success pf-flash" data-flash><?= e($flashSuccess) ?></div><?php endif; ?>
