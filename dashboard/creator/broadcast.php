<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/broadcasts.php';
$user = require_role(['CREATOR', 'ADMIN']);

$errors = [];
$preparedMessage = null;
$preparedRecipients = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'send') {
    csrf_verify();
    $message = trim(post('message'));
    $selectedIds = array_map('intval', $_POST['recipientIds'] ?? []);

    if (strlen($message) < 3) {
        $errors[] = 'Write a message before sending.';
    } elseif (!$selectedIds) {
        $errors[] = 'Select at least one follower to send to.';
    } else {
        $allRecipients = get_broadcast_recipients((int) $user['id']);
        $preparedRecipients = array_values(array_filter($allRecipients, fn($r) => in_array((int) $r['id'], $selectedIds, true)));
        if (!$preparedRecipients) {
            $errors[] = 'None of the selected followers could be matched.';
        } else {
            record_broadcast((int) $user['id'], $message, count($preparedRecipients));
            $preparedMessage = $message;
        }
    }
}

$allFollowers = get_school_followers((int) $user['id']);
$recipients = get_broadcast_recipients((int) $user['id']);
$excludedCount = count($allFollowers) - count($recipients);
$broadcasts = get_broadcasts_for_creator((int) $user['id']);

$pageTitle = 'WhatsApp Broadcast — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">WhatsApp Broadcast</h1>
<p class="muted" style="margin-top:6px;">Message your followers on WhatsApp. There's no bulk-sending API connected yet, so you'll open each chat with your message ready to go and send it yourself, one click per follower.</p>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<?php if ($preparedRecipients): ?>
  <div class="card card-pad" style="margin-top:20px; border-color: var(--success);">
    <h2 class="h3">Ready — send to <?= count($preparedRecipients) ?> follower<?= count($preparedRecipients) === 1 ? '' : 's' ?></h2>
    <p class="muted small" style="margin-top:4px;">Click each one below — it opens WhatsApp with your message already typed in. Nothing sends until you press send yourself in WhatsApp.</p>
    <div class="stack gap-2" style="margin-top:14px;">
      <?php foreach ($preparedRecipients as $r): ?>
        <a href="https://wa.me/<?= e($r['wa']) ?>?text=<?= urlencode($preparedMessage) ?>" target="_blank" rel="noopener" class="btn btn-outline" style="justify-content:flex-start;">
          <?php dash_icon('message-square'); ?> <?= e($r['name']) ?> <span class="small muted" style="margin-left:6px;"><?= e($r['phone']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!$recipients): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;">
    <p class="muted"><?= $allFollowers ? "None of your followers have a phone number on file yet." : "You don't have any followers yet." ?></p>
  </div>
<?php else: ?>
  <form method="post" class="card card-pad" style="margin-top:20px; max-width:560px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="send">
    <div class="field">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="4" required placeholder="e.g. New lesson just dropped in Practical Web Development — check it out!"><?= e(post('message', '')) ?></textarea>
    </div>
    <div class="field" style="margin-bottom:0;">
      <label>Send to (<?= count($recipients) ?> follower<?= count($recipients) === 1 ? '' : 's' ?> with a phone number)</label>
      <?php if ($excludedCount > 0): ?>
        <p class="help"><?= $excludedCount ?> other follower<?= $excludedCount === 1 ? '' : 's' ?> <?= $excludedCount === 1 ? "doesn't" : "don't" ?> have a phone number on file and can't be included.</p>
      <?php endif; ?>
      <div class="stack gap-2" style="margin-top:8px; max-height:260px; overflow-y:auto; border:1px solid var(--dash-border); border-radius:10px; padding:12px;">
        <?php foreach ($recipients as $r): ?>
          <label class="row gap-2" style="align-items:center; font-weight:600; cursor:pointer;">
            <input type="checkbox" name="recipientIds[]" value="<?= (int) $r['id'] ?>" checked>
            <span><?= e($r['name']) ?> <span class="small muted"><?= e($r['phone']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-top:16px;">Prepare Broadcast</button>
  </form>
<?php endif; ?>

<?php if ($broadcasts): ?>
  <h2 class="h3" style="margin-top:36px;">Broadcast History</h2>
  <div class="table-wrap" style="margin-top:14px;">
    <table>
      <thead>
        <tr><th>Message</th><th>Followers</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($broadcasts as $b): ?>
          <tr>
            <td style="max-width:360px;"><?= e(mb_strimwidth($b['message'], 0, 120, '…')) ?></td>
            <td><?= (int) $b['recipient_count'] ?></td>
            <td class="small muted"><?= e(format_date($b['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
