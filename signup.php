<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) redirect('/dashboard.php');

$errors = [];
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = post('name');
    $email = strtolower(post('email'));
    $phone = post('phone');
    $password = post('password');

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
            redirect('/dashboard.php');
        }
    }
}

$pageTitle = 'Sign Up — Obin Academy';
require __DIR__ . '/includes/auth_header.php';
?>
  <h1>Create Your Account</h1>
  <p class="lede">Start learning, or apply to teach, in a couple of minutes.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" style="margin-top: 24px;">
    <?= csrf_field() ?>
    <div class="field">
      <label for="name">Full Name</label>
      <input id="name" name="name" type="text" required value="<?= e($name) ?>">
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="jane@example.com">
    </div>
    <div class="field">
      <label for="phone">Phone Number (optional)</label>
      <input id="phone" name="phone" type="tel" placeholder="e.g. 0772 123 456" value="<?= e($phone) ?>">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required minlength="8">
      <p class="help">At least 8 characters.</p>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
  </form>

  <p class="small" style="margin-top: 24px; text-align:center;">
    Already have an account? <a href="<?= e(base_url('login.php')) ?>" style="color: var(--accent); font-weight:600;">Log In</a>
  </p>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
