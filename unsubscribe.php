<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/leads.php';
require __DIR__ . '/includes/retention.php';
require __DIR__ . '/includes/course_notify.php';

// Three separate token spaces share this one page — a lead's plain numeric
// token (unsubscribe_token()), a learner's 'u'-prefixed retention token
// (retention_unsubscribe_token()), and a 'c'-prefixed new-course-announcement
// token (new_course_unsubscribe_token()) — since each unsubscribes from a
// different thing (pre-signup marketing, inactivity nudges, new-course
// broadcasts) stored/toggled independently. The prefix alone routes between them.
$token = query_param('token');
if (str_starts_with($token, 'u')) {
    $tokenType = 'retention';
} elseif (str_starts_with($token, 'c')) {
    $tokenType = 'new_course';
} else {
    $tokenType = 'lead';
}

$email = null;
$found = false;
if ($tokenType === 'retention') {
    $userId = $token !== '' ? retention_unsubscribe_token_user_id($token) : null;
    $learner = $userId ? db_one('SELECT email, retention_emails_opt_out FROM users WHERE id = ?', [$userId]) : null;
    if ($learner) {
        if (!$learner['retention_emails_opt_out']) {
            db_run('UPDATE users SET retention_emails_opt_out = 1 WHERE id = ?', [$userId]);
        }
        $found = true;
        $email = $learner['email'];
    }
} elseif ($tokenType === 'new_course') {
    $userId = $token !== '' ? new_course_unsubscribe_token_user_id($token) : null;
    $account = $userId ? db_one('SELECT email, new_course_emails_opt_out FROM users WHERE id = ?', [$userId]) : null;
    if ($account) {
        if (!$account['new_course_emails_opt_out']) {
            db_run('UPDATE users SET new_course_emails_opt_out = 1 WHERE id = ?', [$userId]);
        }
        $found = true;
        $email = $account['email'];
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
      <?php if ($tokenType === 'retention'): ?>
        <?= e($email) ?> won't receive "come back and learn" reminders anymore. You'll still get
        emails you need for your account, like receipts, password resets, or certificates.
      <?php elseif ($tokenType === 'new_course'): ?>
        <?= e($email) ?> won't receive "new course published" announcements anymore. You'll still get
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
