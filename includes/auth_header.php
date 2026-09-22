<?php
/**
 * Split-card shell for auth pages (login, signup, forgot/reset password) —
 * a white rounded card: a curved brand-blue panel on one side (headline +
 * a CTA toward the other auth action) and the real form on the other.
 * Callers may set, before requiring this file:
 *   $authTab      — 'login' | 'signup' picks the panel's copy/CTA toward
 *                    the other action, or omit for a generic branding
 *                    panel (forgot/reset password use none).
 *   $redirectTo   — carried onto the panel's login.php/signup.php link so
 *                    switching pages doesn't lose it.
 */
require_once __DIR__ . '/data.php';

$authStats = get_platform_stats();
$authRedirect = $redirectTo ?? '/dashboard.php';
$authLoginUrl = base_url('login.php?redirect=' . urlencode($authRedirect));
$authSignupUrl = base_url('signup.php?redirect=' . urlencode($authRedirect));

$authPanels = [
    'login' => [
        'title' => 'Hello, Friend!',
        'sub' => 'New here? Create a free account and start learning or teaching in minutes.',
        'cta' => 'Sign Up',
        'href' => $authSignupUrl,
    ],
    'signup' => [
        'title' => 'Welcome Back!',
        'sub' => 'Already learning or teaching with us? Log in to pick up right where you left off.',
        'cta' => 'Sign In',
        'href' => $authLoginUrl,
    ],
];
$authPanel = $authPanels[$authTab ?? ''] ?? [
    'title' => 'Obin Academy',
    'sub' => 'Real courses from African creators, paid for with mobile money.',
    'cta' => 'Back to Home',
    'href' => base_url('index.php'),
];
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
  <div class="auth-topbar">
    <a href="<?= e(base_url('index.php')) ?>" class="back-home"><?php dash_icon('arrow-left'); ?> Back to Home</a>
  </div>

  <div class="auth-stage">
    <div class="auth-card">
      <div class="auth-panel">
        <div class="auth-panel-logo"><?php render_logo(); ?></div>
        <div class="auth-panel-title"><?= e($authPanel['title']) ?></div>
        <p class="auth-panel-sub"><?= e($authPanel['sub']) ?></p>
        <a href="<?= e($authPanel['href']) ?>" class="auth-panel-cta"><?= e(mb_strtoupper($authPanel['cta'])) ?></a>
      </div>

      <div class="auth-form-side">
