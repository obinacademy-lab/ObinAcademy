<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = require_login();
$type = query_param('type');
$id = (int) query_param('id');

if (!in_array($type, REPORTABLE_TYPES, true) || !$id) {
    http_response_code(404);
    exit('Nothing to report');
}

$target = get_report_target(['reportable_type' => $type, 'reportable_id' => $id]);
if (!$target) { http_response_code(404); exit('This content no longer exists.'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $reason = post('reason');
    if (strlen($reason) < 5) $errors[] = 'Tell us a bit more about why you\'re reporting this (at least 5 characters).';

    if (!$errors) {
        $reported = report_content($type, $id, (int) $user['id'], $reason);
        flash_set('success', $reported ? 'Thanks — our team will review this.' : 'You\'ve already reported this and it\'s pending review.');
        redirect(ltrim($target['link'], '/'));
    }
}

$pageTitle = 'Report ' . ($type === 'post' ? 'Post' : 'Comment') . ' — Obin Academy';
$noindex = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:480px; padding-top:32px; padding-bottom:72px;">
  <h1 class="h2">Report this <?= $type === 'post' ? 'post' : 'comment' ?></h1>
  <p class="muted small" style="margin-top:8px;">Our team will review it against the community guidelines.</p>

  <div class="card card-pad" style="margin-top:20px; background:var(--surface); border-style:dashed;">
    <div class="small muted">By <?= e($target['author_name']) ?></div>
    <p style="margin-top:6px; font-size:13.5px; line-height:1.6;"><?= e(mb_strimwidth($target['body'], 0, 200, '…')) ?></p>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" class="card card-pad" style="margin-top:16px;">
    <?= csrf_field() ?>
    <div class="field">
      <label for="reason">Why are you reporting this?</label>
      <textarea id="reason" name="reason" rows="4" placeholder="e.g. spam, harassment, off-topic, misleading information…" required><?= e(post('reason')) ?></textarea>
    </div>
    <div class="row gap-2">
      <button type="submit" class="btn btn-primary">Submit Report</button>
      <a href="<?= e(base_url(ltrim($target['link'], '/'))) ?>" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
