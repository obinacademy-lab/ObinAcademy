<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$email = '';
$redirectTo = query_param('redirect', '/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(post('email'));
    $password = post('password');
    $redirectTo = post('redirect', '/dashboard.php');

    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !verify_password($password, $user['password_hash'])) {
        $errors[] = 'Invalid email or password.';
    } else {
        login_user($user);
        redirect($redirectTo ?: '/dashboard.php');
    }
}

$pageTitle = 'Log In — Obin Academy';
require __DIR__ . '/includes/auth_header.php';
?>
  <h1>Welcome Back</h1>
  <p class="lede">Log in to continue learning or managing your courses.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" style="margin-top: 24px;">
    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="jane@example.com">
    </div>
    <div class="field">
      <div class="row between">
        <label for="password" style="margin-bottom:0;">Password</label>
        <a href="<?= e(base_url('forgot-password.php')) ?>" class="small" style="color: var(--accent); font-weight:600;">Forgot password?</a>
      </div>
      <input id="password" name="password" type="password" required placeholder="Your password">
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Log In</button>
  </form>

  <p class="small" style="margin-top: 24px; text-align:center;">
    Don't have an account? <a href="<?= e(base_url('signup.php')) ?>" style="color: var(--accent); font-weight:600;">Sign Up</a>
  </p>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
