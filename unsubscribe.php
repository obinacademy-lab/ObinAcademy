<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/leads.php';
require __DIR__ . '/includes/retention.php';

// Two separate token spaces share this one page — a lead's plain numeric
// token (see unsubscribe_token()) and a learner's 'u'-prefixed retention
// token (see retention_unsubscribe_token()) — since they unsubscribe from
// different things (pre-signup marketing vs. inactivity nudges) stored on
// different tables. The 'u' prefix is enough to route between them.
$token = query_param('token');
$isRetentionToken = str_starts_with($token, 'u');

$email = null;
$found = false;
if ($isRetentionToken) {
    $userId = $token !== '' ? retention_unsubscribe_token_user_id($token) : null;
    $learner = $userId ? db_one('SELECT email, retention_emails_opt_out FROM users WHERE id = ?', [$userId]) : null;
    if ($learner) {
        if (!$learner['retention_emails_opt_out']) {
            db_run('UPDATE users SET retention_emails_opt_out = 1 WHERE id = ?', [$userId]);
        }
        $found = true;
        $email = $learner['email'];
    }
} else {
    $leadId = $token !== '' ? unsubscribe_token_lead_id($token) : null;
    $lead = $leadId ? db_one('SELECT email, unsubscribed FROM leads WHERE id = ?', [$leadId]) : null;
    if ($lead) {
        if (!$lead['unsubscribed']) {
            db_run('UPDATE leads SET unsubscribed = 1 WHERE id = ?', [$leadId]);
        }
        $found = true;
        $email = $lead['email'];
    }
}

$pageTitle = 'Unsubscribe — Obin Academy';
$noindex = true;
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:440px; padding: 90px 20px; text-align:center;">
  <?php if ($found): ?>
    <div style="font-size:40px;">✓</div>
    <h1 class="h3" style="margin-top:16px;">You're unsubscribed</h1>
    <p class="muted" style="margin-top:10px;">
      <?php if ($isRetentionToken): ?>
        <?= e($email) ?> won't receive "come back and learn" reminders anymore. You'll still get
        emails you need for your account, like receipts, password resets, or certificates.
      <?php else: ?>
        <?= e($email) ?> won't receive marketing emails from Obin Academy anymore. You'll still get
        emails you need for your account, like receipts or password resets.
      <?php endif; ?>
    </p>
  <?php else: ?>
    <div style="font-size:40px;">🔒</div>
    <h1 class="h3" style="margin-top:16px;">Invalid Unsubscribe Link</h1>
    <p class="muted" style="margin-top:10px;">This link isn't valid or has already been used.</p>
  <?php endif; ?>
  <a href="<?= e(base_url('index.php')) ?>" class="btn btn-primary" style="margin-top:20px;">Back to Obin Academy</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
