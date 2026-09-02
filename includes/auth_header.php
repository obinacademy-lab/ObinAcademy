<?php
/**
 * Slim shell for auth pages (login, signup, forgot/reset password) — just a
 * logo + back-to-home bar and a centered card, no full site nav/footer.
 * Usage: require __DIR__ . '/../includes/auth_header.php';
 */
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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-topbar">
    <?php render_logo(true); ?>
    <a href="<?= e(base_url('index.php')) ?>" class="auth-back">← Back to Home</a>
  </div>
  <div class="auth-main">
    <div class="auth-card">
