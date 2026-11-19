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
  <meta name="theme-color" content="#ffffff">
  <title><?= e($pageTitle ?? 'Obin Academy') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800&family=Geist+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/auth-bright.css')) ?>">
  <?php require __DIR__ . '/pwa-head.php'; ?>
</head>
<body class="auth-body ab">
<main class="auth-shell ab-shell">
  <aside class="ab-photo" aria-hidden="false">
    <img src="<?= e(versioned_asset('assets/img/hero-couch-learner.jpg')) ?>" alt="">
    <a class="ab-logo" href="<?= e(base_url('index.php')) ?>"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"/></svg></span>Obin Academy</a>
    <div class="ab-quote"><p>Twelve months from now you will wish you had started today.</p><small>Obin Academy · Kampala, Uganda</small></div>
  </aside>
  <section class="auth-card">
    <div class="auth-brand">
      <a class="auth-mark" href="<?= e(base_url('index.php')) ?>" aria-label="Obin Academy home"><?php dash_icon('graduation-cap'); ?></a>
      <span class="auth-wordmark"><b>Obin</b> Academy</span>
    </div>
