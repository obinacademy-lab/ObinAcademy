<?php
require __DIR__ . '/includes/bootstrap.php';

$token = query_param('token');
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $token = post('token');
    $password = post('password');
    $confirmPassword = post('confirmPassword');

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $tokenHash = hash('sha256', $token);
        $record = db_one('SELECT * FROM password_reset_tokens WHERE token_hash = ?', [$tokenHash]);

        if (!$record || $record['used_at'] !== null || strtotime($record['expires_at']) < time()) {
            $errors[] = 'This reset link is invalid or has expired. Request a new one.';
        } else {
            db_run('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $record['user_id']]);
            db_run('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?', [$record['id']]);
            $success = true;
        }
    }
}

$pageTitle = 'Reset Password — Obin Academy';
require __DIR__ . '/includes/auth_header.php';
?>
  <h1 class="auth-headline">Choose a New Password</h1>

  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-top:14px;">Your password has been reset.</div>
    <a href="<?= e(base_url('login.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Log In <span class="btn-arrow">→</span></a>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="alert alert-error" style="margin-top:14px;"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label for="password">New Password</label>
        <div class="field-icon">
          <?php dash_icon('lock'); ?>
          <input id="password" name="password" type="password" required minlength="8">
        </div>
      </div>
      <div class="field">
        <label for="confirmPassword">Confirm Password</label>
        <div class="field-icon">
          <?php dash_icon('lock'); ?>
          <input id="confirmPassword" name="confirmPassword" type="password" required minlength="8">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Reset Password <span class="btn-arrow">→</span></button>
    </form>
  <?php endif; ?>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
