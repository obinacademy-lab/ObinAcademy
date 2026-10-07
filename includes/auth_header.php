<?php
/**
 * Split shell for auth pages (login, signup, forgot/reset password): the form on the left, a rotating
 * photo stage on the right. Callers may set, before requiring this file:
 *   $authTab      — 'login' | 'signup' adds a lime button toward the other action under the form;
 *                    omit it (forgot/reset password) and the page keeps its own plain link.
 *   $redirectTo   — carried onto that button's login.php/signup.php link so switching pages doesn't lose it.
 */
require_once __DIR__ . '/data.php';

$authRedirect = $redirectTo ?? '/dashboard.php';
$authLoginUrl = base_url('login.php?redirect=' . urlencode($authRedirect));
$authSignupUrl = base_url('signup.php?redirect=' . urlencode($authRedirect));

$authAlts = [
    'login'  => ['lead' => 'New to Obin Academy?', 'cta' => 'Create account', 'href' => $authSignupUrl],
    'signup' => ['lead' => 'Already have an account?', 'cta' => 'Sign in', 'href' => $authLoginUrl],
];
$authAlt = $authAlts[$authTab ?? ''] ?? null;

$authSlides = [
    ['img' => 'auth-slide-1.jpg', 'pill' => 'Free to join', 'h' => 'Learn from African creators, pay with mobile money.', 'p' => 'MTN and Airtel Money accepted. Start in minutes.'],
    ['img' => 'auth-slide-2.jpg', 'pill' => 'For creators', 'h' => 'Teach what you know. Keep 90% of every sale.', 'p' => 'Open your own school and set your own prices.'],
    ['img' => 'auth-slide-3.jpg', 'pill' => 'Learn anywhere', 'h' => 'Courses on your phone, at your pace.', 'p' => 'Stream lessons and download PDFs anytime.'],
    ['img' => 'auth-slide-4.jpg', 'pill' => 'Certificates', 'h' => 'Finish a course and earn a certificate.', 'p' => 'Show what you have learned to employers and clients.'],
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
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700;800&family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
  <div class="auth-card<?= $authAlt ? ' auth-has-alt' : '' ?>">
    <section class="auth-left">
      <?php render_logo(true); ?>

      <div class="auth-form-side">