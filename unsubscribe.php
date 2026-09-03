<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/leads.php';

$token = query_param('token');
$leadId = $token !== '' ? unsubscribe_token_lead_id($token) : null;
$lead = $leadId ? db_one('SELECT name, email, unsubscribed FROM leads WHERE id = ?', [$leadId]) : null;

if ($lead && !$lead['unsubscribed']) {
    db_run('UPDATE leads SET unsubscribed = 1 WHERE id = ?', [$leadId]);
    $lead['unsubscribed'] = 1;
}

$pageTitle = 'Unsubscribe — Obin Academy';
$noindex = true;
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:440px; padding: 90px 20px; text-align:center;">
  <?php if ($lead): ?>
    <div style="font-size:40px;">✓</div>
    <h1 class="h3" style="margin-top:16px;">You're unsubscribed</h1>
    <p class="muted" style="margin-top:10px;">
      <?= e($lead['email']) ?> won't receive marketing emails from Obin Academy anymore. You'll still get
      emails you need for your account, like receipts or password resets.
    </p>
  <?php else: ?>
    <div style="font-size:40px;">🔒</div>
    <h1 class="h3" style="margin-top:16px;">Invalid Unsubscribe Link</h1>
    <p class="muted" style="margin-top:10px;">This link isn't valid or has already been used.</p>
  <?php endif; ?>
  <a href="<?= e(base_url('index.php')) ?>" class="btn btn-primary" style="margin-top:20px;">Back to Obin Academy</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
