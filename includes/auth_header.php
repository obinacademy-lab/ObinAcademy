<?php
/**
 * Shell for auth pages (login, signup, forgot/reset password): a white rounded card over a blurred photo —
 * the form on the left, a photo with floating glass callouts on the right. Callers may set, before requiring
 * this file:
 *   $redirectTo   — kept for callers that pass it; the pages build their own switch links.
 */
require_once __DIR__ . '/data.php';

// One slide = a photo, a bottom pill and two glass callouts (all real: real course, real fee split, real payment methods).
$authSlides = [
    ['img' => 'auth-slide-1.jpg', 'pill' => 'Free to join',
     'a' => ['Tutor Elvis', 'Business Growth Mastery'], 'b' => ['UGX 30,000', 'one-time payment']],
    ['img' => 'auth-slide-2.jpg', 'pill' => 'Open your school',
     'a' => ['For creators', 'Set your own prices'], 'b' => ['Keep 90%', 'of every sale']],
    ['img' => 'auth-slide-3.jpg', 'pill' => 'Learn anywhere',
     'a' => ['On your phone', 'Stream or download'], 'b' => ['MTN + Airtel', 'Mobile Money']],
    ['img' => 'auth-slide-4.jpg', 'pill' => 'Earn a certificate',
     'a' => ['On completion', 'Share it anywhere'], 'b' => ['Included', 'with every course']],
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
  <style>body.auth-body::before { background-image: url('<?= e(versioned_asset('assets/img/auth-bg.jpg')) ?>'); }</style>
</head>
<body class="auth-body">
<div class="auth-shell">
  <div class="auth-card">
    <section class="auth-left">
      <div class="auth-brand"><?php render_logo(true); ?></div>

      <div class="auth-form-side">