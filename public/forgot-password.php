<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/email.php';

const RESET_TOKEN_TTL_SECONDS = 3600;

$errors = [];
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(post('email'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } else {
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user) {
            db_run('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL_SECONDS);

            db_insert(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
                [$user['id'], $tokenHash, $expiresAt]
            );

            $resetUrl = base_url('reset-password.php?token=' . $token);
            send_password_reset_email($user['email'], $resetUrl);
        }
        // Same response whether or not the email exists — never reveal which emails have accounts.
        $sent = true;
    }
}

$pageTitle = 'Forgot Password — Obin Academy';
require __DIR__ . '/../includes/auth_header.php';
?>
  <h1>Reset Your Password</h1>
  <p class="lede">Enter your email and we'll send you a reset link.</p>

  <?php if ($sent): ?>
    <div class="alert alert-success" style="margin-top:20px;">If an account exists for that email, a reset link is on its way.</div>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>
    <form method="post" style="margin-top: 24px;">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required placeholder="jane@example.com">
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg">Send Reset Link</button>
    </form>
  <?php endif; ?>

  <p class="small" style="margin-top: 24px; text-align:center;">
    <a href="<?= e(base_url('login.php')) ?>" style="color: var(--accent); font-weight:600;">Back to Log In</a>
  </p>
<?php require __DIR__ . '/../includes/auth_footer.php'; ?>
