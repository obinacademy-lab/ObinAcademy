<?php
/**
 * Split-screen shell for auth pages (login, signup, forgot/reset password) —
 * a navy brand panel (logo, live platform stats, a real testimonial) on the
 * left, a white form panel on the right. Callers may set, before requiring
 * this file:
 *   $authTab            — 'login' | 'signup' to show the switcher pill
 *                          highlighting that tab, or omit to hide the
 *                          switcher entirely (forgot/reset password use none).
 *   $authBrandHeadline / $authBrandSub — override the brand panel's copy;
 *                          both fall back to a platform-wide default.
 *   $redirectTo          — carried onto the switcher's login.php/signup.php
 *                          links so toggling tabs doesn't lose it.
 * Usage: require __DIR__ . '/../includes/auth_header.php';
 */
require_once __DIR__ . '/data.php';

$authBrandHeadline = $authBrandHeadline ?? 'Skills that pay you back.';
$authBrandSub = $authBrandSub ?? "Join a community of learners and creators building real, income-generating skills — paid for instantly with mobile money.";
$authStats = get_platform_stats();
$authTestimonials = get_published_testimonials();
$authQuote = $authTestimonials ? $authTestimonials[array_rand($authTestimonials)] : null;
$authRedirect = $redirectTo ?? '/dashboard.php';
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
  <aside class="brand-pane">
    <div class="dot-field"></div>
    <div class="glow glow-a"></div>
    <div class="glow glow-b"></div>

    <?php render_logo(); ?>

    <div class="brand-copy">
      <span class="brand-eyebrow"><?php dash_icon('sparkle'); ?> East Africa's Learning Marketplace</span>
      <h2 class="brand-headline"><?= e($authBrandHeadline) ?></h2>
      <p class="brand-sub"><?= e($authBrandSub) ?></p>

      <div class="brand-stats">
        <div class="item"><div class="num"><?= (int) $authStats['course_count'] ?>+</div><div class="lbl">Courses Live</div></div>
        <div class="item"><div class="num"><?= (int) $authStats['learner_count'] ?>+</div><div class="lbl">Learners</div></div>
        <div class="item"><div class="num"><?= (int) $authStats['creator_count'] ?>+</div><div class="lbl">Creators</div></div>
      </div>

      <?php if ($authQuote): ?>
        <div class="quote-card">
          <p>&ldquo;<?= e($authQuote['quote']) ?>&rdquo;</p>
          <div class="who">
            <span class="av"><?= e(mb_substr($authQuote['author_name'], 0, 1)) ?></span>
            <span><?= e($authQuote['author_name']) ?></span>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <main class="form-pane">
    <div class="form-topbar">
      <?php render_logo(true); ?>
      <a href="<?= e(base_url('index.php')) ?>" class="back-home"><?php dash_icon('arrow-left'); ?> Back to Home</a>
    </div>

    <?php if (isset($authTab)): ?>
      <div class="auth-switcher">
        <a href="<?= e(base_url('login.php?redirect=' . urlencode($authRedirect))) ?>" class="<?= $authTab === 'login' ? 'active' : '' ?>">Log In</a>
        <a href="<?= e(base_url('signup.php?redirect=' . urlencode($authRedirect))) ?>" class="<?= $authTab === 'signup' ? 'active' : '' ?>">Sign Up</a>
      </div>
    <?php endif; ?>

    <div class="form-body">
