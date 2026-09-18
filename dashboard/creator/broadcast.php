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
$stats = get_broadcast_stats_for_creator((int) $user['id']);

$pageTitle = 'WhatsApp Broadcast — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="reveal">
  <div class="row gap-2" style="align-items:center; flex-wrap:wrap;">
    <h1 class="h2" style="margin:0;">WhatsApp Broadcast</h1>
    <span class="status-pill" style="color:#128c7e; background:#e7f9ef; border-color:#bdf0d6;">
      <?php dash_icon('message-square'); ?> Personal, not bulk
    </span>
  </div>
  <p class="muted" style="margin-top:6px; max-width:580px;">Message your followers on WhatsApp — there's no bulk-sending API connected yet, so you'll open each chat with your message ready to go and send it yourself, one click per follower.</p>
</div>

<div class="grid md:grid-3 reveal" style="margin-top:22px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= count($recipients) ?></div>
    <div class="label">Followers reachable</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#128c7e;">
    <div class="icon"><?php dash_icon('message-square'); ?></div>
    <div class="value"><?= (int) $stats['sentThisMonth'] ?></div>
    <div class="label">Broadcasts sent this month</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('trending-up'); ?></div>
    <div class="value"><?= number_format($stats['totalReach']) ?></div>
    <div class="label">Total reach, all time</div>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:20px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<?php if ($preparedRecipients): ?>
  <div class="card card-pad reveal" style="margin-top:22px; border-color:#7fd8ac; background:#f2fbf6; color:#0b4f38;">
    <div class="row gap-2" style="align-items:center;">
      <span style="width:34px; height:34px; border-radius:10px; background:#25d366; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><?php dash_icon('check-circle'); ?></span>
      <div>
        <h2 class="h3" style="margin:0; color:#0b4f38;">Ready — send to <?= count($preparedRecipients) ?> follower<?= count($preparedRecipients) === 1 ? '' : 's' ?></h2>
        <p class="small" style="margin:2px 0 0; color:#128c7e; font-weight:600;">Click each one below — it opens WhatsApp with your message already typed in. Nothing sends until you press send yourself in WhatsApp.</p>
      </div>
    </div>
    <div class="grid sm:grid-2" style="margin-top:16px; gap:10px;">
      <?php foreach ($preparedRecipients as $r): ?>
        <a href="https://wa.me/<?= e($r['wa']) ?>?text=<?= urlencode($preparedMessage) ?>" target="_blank" rel="noopener" class="recip-open-row" data-recip-open>
          <span class="row-avatar"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
          <span style="flex:1; min-width:0;">
            <span style="display:block; font-weight:700; font-size:13px;"><?= e($r['name']) ?></span>
            <span class="small muted"><?= e($r['phone']) ?></span>
          </span>
          <span class="recip-open-pill"><?php dash_icon('chevron-right'); ?><span data-recip-label>Open</span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!$recipients): ?>
  <div class="card card-pad reveal" style="margin-top:22px; border-style:dashed; text-align:center;">
    <p class="muted"><?= $allFollowers ? "None of your followers have a phone number on file yet." : "You don't have any followers yet." ?></p>
  </div>
<?php else: ?>
  <div class="grid lg:grid-3 reveal" style="margin-top:22px; gap:20px; align-items:start;">

    <form method="post" class="card card-pad broadcast-compose" style="margin:0;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="send">

      <div class="row gap-2" style="align-items:center; margin-bottom:18px;">
        <span style="width:34px; height:34px; border-radius:10px; background:var(--dash-tint); color:var(--accent); display:flex; align-items:center; justify-content:center; flex-shrink:0;"><?php dash_icon('message-square'); ?></span>
        <div style="font-weight:800; font-size:15px;">Compose your message</div>
      </div>

      <div class="field">
        <label for="message">Message</label>
        <textarea id="message" name="message" rows="4" required placeholder="e.g. New lesson just dropped in Practical Web Development — check it out!" data-char-count><?= e(post('message', '')) ?></textarea>
        <div class="small muted" style="text-align:right; margin-top:4px;"><span data-char-count-out>0</span> characters</div>
      </div>

      <div class="row between" style="align-items:center; margin-top:6px; margin-bottom:10px;">
        <label style="margin:0;">Send to (<span data-recip-visible-count><?= count($recipients) ?></span> follower<?= count($recipients) === 1 ? '' : 's' ?> with a phone number)</label>
        <div class="row gap-2" style="font-size:12.5px;">
          <button type="button" class="link-btn" style="color:var(--accent);" data-select-all>Select all</button>
          <button type="button" class="link-btn muted" data-select-clear>Clear</button>
        </div>
      </div>
      <?php if ($excludedCount > 0): ?>
        <p class="help" style="margin-top:0;"><?= $excludedCount ?> other follower<?= $excludedCount === 1 ? '' : 's' ?> <?= $excludedCount === 1 ? "doesn't" : "don't" ?> have a phone number on file and can't be included.</p>
      <?php endif; ?>

      <div class="row gap-2" style="align-items:center; background:var(--dash-bg); border:1px solid var(--dash-border); border-radius:9px; padding:9px 13px; margin-bottom:10px;">
        <?php dash_icon('search'); ?>
        <input type="text" placeholder="Search followers by name or phone…" data-recip-search style="border:none; background:none; outline:none; flex:1; font-size:13px; font-family:inherit; color:var(--ink);">
      </div>

      <div class="activity-feed" style="max-height:280px; overflow-y:auto;">
        <?php foreach ($recipients as $r): ?>
          <label class="activity-row" style="cursor:pointer; align-items:center;" data-recip-row data-recip-match="<?= e(mb_strtolower($r['name'] . ' ' . $r['phone'])) ?>">
            <input type="checkbox" name="recipientIds[]" value="<?= (int) $r['id'] ?>" checked data-recip-checkbox style="accent-color:var(--accent); width:16px; height:16px; flex-shrink:0;">
            <span class="row-avatar"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></span>
            <span class="activity-body">
              <strong><?= e($r['name']) ?></strong> <span class="muted"><?= e($r['phone']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="small muted" style="margin-top:8px;"><?= count($recipients) ?> follower<?= count($recipients) === 1 ? '' : 's' ?> with a phone number · list updates live as you search above</div>

      <button type="submit" class="btn btn-block btn-lg" style="margin-top:20px; background:#25d366; color:#fff; border:none;">
        <?php dash_icon('message-square'); ?> Prepare Broadcast
      </button>
    </form>

    <div class="stack gap-3 broadcast-side">
      <div class="card card-pad">
        <div style="font-weight:800; font-size:14px; margin-bottom:14px;">How sending works</div>
        <div class="stack gap-3">
          <div class="row gap-2" style="align-items:flex-start;">
            <span style="width:22px; height:22px; border-radius:999px; background:var(--dash-tint); color:var(--accent); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; flex-shrink:0;">1</span>
            <span class="small muted">Write your message and pick who should get it.</span>
          </div>
          <div class="row gap-2" style="align-items:flex-start;">
            <span style="width:22px; height:22px; border-radius:999px; background:var(--dash-tint); color:var(--accent); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; flex-shrink:0;">2</span>
            <span class="small muted">Tap Prepare Broadcast — a chat link is queued for each follower.</span>
          </div>
          <div class="row gap-2" style="align-items:flex-start;">
            <span style="width:22px; height:22px; border-radius:999px; background:var(--dash-tint); color:var(--accent); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:11px; flex-shrink:0;">3</span>
            <span class="small muted">Click each one — WhatsApp opens with your text ready. You send it yourself.</span>
          </div>
        </div>
      </div>
      <div class="card card-pad" style="background:#fff8e8; border-color:#ffe9b3;">
        <p class="small" style="margin:0; color:#8a6100; line-height:1.55;"><strong>Tip:</strong> short, specific messages get opened fastest — lead with what's new, not a greeting.</p>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($broadcasts): ?>
  <h3 class="dash-section-label reveal" style="margin-top:36px;">Broadcast History</h3>
  <div class="activity-feed reveal" style="margin-top:14px;">
    <?php foreach ($broadcasts as $b): ?>
      <div class="activity-row broadcast-history-row">
        <div class="broadcast-history-main">
          <span class="activity-dot" style="background:#e7f9ef; color:#128c7e; flex-shrink:0;"><?php dash_icon('message-square'); ?></span>
          <div class="activity-body"><strong><?= e(mb_strimwidth($b['message'], 0, 90, '…')) ?></strong></div>
        </div>
        <div class="broadcast-history-meta">
          <span class="status-pill" style="color:var(--accent); background:var(--dash-tint); border-color:transparent; font-size:11.5px; padding:5px 12px;"><?= (int) $b['recipient_count'] ?> follower<?= (int) $b['recipient_count'] === 1 ? '' : 's' ?></span>
          <span class="small muted" style="width:90px; text-align:right; flex-shrink:0;"><?= e(format_date($b['created_at'])) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(() => {
  const textarea = document.querySelector('[data-char-count]');
  const charOut = document.querySelector('[data-char-count-out]');
  if (textarea && charOut) {
    const update = () => { charOut.textContent = textarea.value.length; };
    update();
    textarea.addEventListener('input', update);
  }

  const search = document.querySelector('[data-recip-search]');
  const rows = document.querySelectorAll('[data-recip-row]');
  const visibleCount = document.querySelector('[data-recip-visible-count]');
  if (search) {
    search.addEventListener('input', () => {
      const q = search.value.trim().toLowerCase();
      let visible = 0;
      rows.forEach((row) => {
        const match = !q || row.dataset.recipMatch.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      if (visibleCount) visibleCount.textContent = String(visible);
    });
  }

  const selectAll = document.querySelector('[data-select-all]');
  const selectClear = document.querySelector('[data-select-clear]');
  const checkboxes = document.querySelectorAll('[data-recip-checkbox]');
  if (selectAll) selectAll.addEventListener('click', () => checkboxes.forEach((cb) => { cb.checked = true; }));
  if (selectClear) selectClear.addEventListener('click', () => checkboxes.forEach((cb) => { cb.checked = false; }));

  document.querySelectorAll('[data-recip-open]').forEach((link) => {
    link.addEventListener('click', () => {
      link.classList.add('opened');
      const label = link.querySelector('[data-recip-label]');
      if (label) label.textContent = 'Opened';
    });
  });
})();
</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
