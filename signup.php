<?php
require __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$name = $email = $phone = '';
$redirectTo = safe_local_redirect_path(query_param('redirect', ''), '/dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = post('name');
    $email = strtolower(post('email'));
    $phone = post('phone');
    $password = post('password');
    $redirectTo = safe_local_redirect_path(post('redirect', ''), '/dashboard.php');

    if (!rate_limit_take('signup-ip', get_client_ip(), 30, 3600)) $errors[] = 'Too many sign-ups from this connection. Please try again later.';
    if (strlen($name) < 2) $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^[0-9+\s-]{9,}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        $existing = db_one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $id = db_insert(
                'INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)',
                [$name, $email, $phone ?: null, hash_password($password), 'LEARNER']
            );
            $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);
            login_user($user);
            redirect($redirectTo);
        }
    }
}

$pageTitle = 'Sign Up — Obin Academy';
$authTab = 'signup';
require __DIR__ . '/../includes/auth_header.php';
?>
  <h1 class="auth-headline" style="--i:0">Start your <em>journey</em></h1>
  <p class="auth-sub" style="--i:1">Join a campus and master one skill in a year. Free to sign up.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:14px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
    <div class="field" style="--i:2">
      <label for="name">Full name</label>
      <input id="name" name="name" type="text" required value="<?= e($name) ?>" placeholder="Your full name" autocomplete="name">
    </div>
    <div class="field" style="--i:3">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="Enter your email" autocomplete="email">
    </div>
    <div class="field" style="--i:4">
      <label for="phone">Phone <i>(optional)</i></label>
      <input id="phone" name="phone" type="tel" placeholder="+256 …" value="<?= e($phone) ?>" autocomplete="tel">
    </div>
    <div class="field" style="--i:5">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required minlength="8" placeholder="At least 8 characters" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary" style="--i:6">Create my account <span class="btn-arrow">→</span></button>
  </form>

  <p class="switch-line" style="--i:7">
    Already have an account? <a href="<?= e(base_url('login.php?redirect=' . urlencode($redirectTo))) ?>">Log In</a>
  </p>
<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
