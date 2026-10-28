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

    $ip = get_client_ip();
    if (rate_limited('login-email', $email, 8, 900) || rate_limited('login-ip', $ip, 60, 900)) {
        $errors[] = 'Too many sign-in attempts. Please wait a few minutes and try again.';
    } else {
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user || !verify_password($password, $user['password_hash'])) {
            rate_limit_record('login-email', $email, 900);   // only failures count
            rate_limit_record('login-ip', $ip, 900);
            $errors[] = 'Invalid email or password.';
        } else {
            login_user($user);
            redirect($redirectTo);
        }
    }
}

$pageTitle = 'Log In — Obin Academy';
$authTab = 'login';
require __DIR__ . '/includes/auth_header.php';
?>
  <h1 class="auth-headline" style="--i:0">Welcome <em>back!</em></h1>
  <p class="auth-sub" style="--i:1">Log in to continue at your campus.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:14px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
    <div class="field" style="--i:2">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="Enter your email" autocomplete="email">
    </div>
    <div class="field" style="--i:3">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required placeholder="Enter your password" autocomplete="current-password">
    </div>
    <div class="field-row" style="--i:4">
      <a href="<?= e(base_url('forgot-password.php')) ?>" class="forgot-link">Forgot password?</a>
    </div>
    <button type="submit" class="btn btn-primary" style="--i:5">Log In <span class="btn-arrow">→</span></button>
  </form>

  <div class="auth-or" style="--i:6">Or</div>
  <a class="auth-ghost" style="--i:6" href="<?= e(base_url('index.php#campuses')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/></svg>Explore the campuses</a>

  <p class="switch-line" style="--i:7">
    Don't have an account? <a href="<?= e(base_url('signup.php?redirect=' . urlencode($redirectTo))) ?>">Sign Up</a>
  </p>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
