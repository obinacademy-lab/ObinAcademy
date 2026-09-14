<?php
/**
 * Cinematic shell for auth pages (login, signup, forgot/reset password) — a
 * full-bleed crossfading photo background behind a centered card, with a
 * single trust strip (live platform stats + a rotating real testimonial)
 * beneath it. Callers may set, before requiring this file:
 *   $authTab            — 'login' | 'signup' to show the switcher pill
 *                          highlighting that tab, or omit to hide the
 *                          switcher entirely (forgot/reset password use none).
 *   $redirectTo          — carried onto the switcher's login.php/signup.php
 *                          links so toggling tabs doesn't lose it.
 * Usage: require __DIR__ . '/../includes/auth_header.php';
 */
require_once __DIR__ . '/data.php';

$authStats = get_platform_stats();
$authTestimonials = get_published_testimonials();
$authRedirect = $redirectTo ?? '/dashboard.php';
$authBgPhotos = ['assets/img/hero-bg-premium.jpg', 'assets/img/hero-couch-learner.jpg', 'assets/img/abt-creator-earnings.jpg'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="robots" content="noindex, follow">
  <title><?= e($pageTitle ?? 'Obin Academy') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-bg-stage">
    <?php foreach ($authBgPhotos as $photo): ?>
      <div class="auth-bg-slide"><img src="<?= e(asset_src($photo)) ?>" alt=""></div>
    <?php endforeach; ?>
  </div>
  <div class="auth-bg-scrim"></div>

  <div class="auth-topbar">
    <?php render_logo(); ?>
    <a href="<?= e(base_url('index.php')) ?>" class="back-home"><?php dash_icon('arrow-left'); ?> Back to Home</a>
  </div>

  <div class="auth-stage">
    <div class="auth-card">
      <div class="auth-card-pad">
        <?php if (isset($authTab)): ?>
          <div class="auth-switcher <?= $authTab === 'signup' ? 'signup' : '' ?>">
            <div class="pill-indicator"></div>
            <a href="<?= e(base_url('login.php?redirect=' . urlencode($authRedirect))) ?>" class="<?= $authTab === 'login' ? 'active' : '' ?>">Log In</a>
            <a href="<?= e(base_url('signup.php?redirect=' . urlencode($authRedirect))) ?>" class="<?= $authTab === 'signup' ? 'active' : '' ?>">Sign Up</a>
          </div>
        <?php endif; ?>
