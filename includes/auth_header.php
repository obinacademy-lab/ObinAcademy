<?php
/**
 * Shell for auth pages (login, signup, forgot/reset password): one frosted-glass card centred on a
 * full-screen night photo. The page body (heading, form, switch link) goes between this file and
 * auth_footer.php. Callers may set, before requiring this file:
 *   $redirectTo   — kept for callers that pass it; the pages build their own switch links.
 */
require_once __DIR__ . '/affiliates.php';
ensure_referral_cookie(); // a referral link can land straight on signup.php, not just the homepage
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex, follow">
  <meta name="theme-color" content="#070b24">
  <title><?= e($pageTitle ?? 'Obin Academy') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Newsreader:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <?php require __DIR__ . '/pwa-head.php'; ?>
  <style>.ag-bg { background-image: url('<?= e(versioned_asset('assets/img/auth-slide-2.jpg')) ?>'); }</style>
</head>
<body class="auth-body">
<div class="ag-bg" aria-hidden="true"></div><div class="ag-tint" aria-hidden="true"></div>
<div class="ag-glow ag-glow-a" aria-hidden="true"></div><div class="ag-glow ag-glow-b" aria-hidden="true"></div><div class="ag-stars" aria-hidden="true"></div>
<main class="auth-shell">
  <section class="auth-card">
    <div class="auth-brand">
      <a class="auth-mark" href="<?= e(base_url('index.php')) ?>" aria-label="Obin Academy home"><?php dash_icon('graduation-cap'); ?></a>
      <span class="auth-wordmark"><b>Obin</b> Academy</span>
    </div>
