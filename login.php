<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$email = '';
$redirectTo = safe_local_redirect_path(query_param('redirect', ''), '/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(post('email'));
    $password = post('password');
    $redirectTo = safe_local_redirect_path(post('redirect', ''), '/dashboard.php');

    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !verify_password($password, $user['password_hash'])) {
        $errors[] = 'Invalid email or password.';
    } else {
        login_user($user);
        redirect($redirectTo);
    }
}

$pageTitle = 'Log In — Obin Academy';
$authTab = 'login';
require __DIR__ . '/includes/auth_header.php';
?>
  <h1 class="auth-headline">Welcome Back</h1>
  <p class="auth-sub">Log in to continue learning or managing your courses.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:14px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="Email address">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required placeholder="Password">
      <div class="field-row">
        <a href="<?= e(base_url('forgot-password.php')) ?>" class="forgot-link">Forgot password?</a>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Log In <span class="btn-arrow">→</span></button>
  </form>

  <p class="switch-line">
    Don't have an account? <a href="<?= e(base_url('signup.php?redirect=' . urlencode($redirectTo))) ?>">Sign Up</a>
  </p>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
