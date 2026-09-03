<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = post('name');
    $description = post('description');
    $privacy = post('privacy', 'public');
    $meetLink = post('meetLink');
    $zoomLink = post('zoomLink');
    $scheduleText = post('scheduleText');

    if (strlen($name) < 3) $errors[] = 'Give your group a name (at least 3 characters).';
    if (!in_array($privacy, ['public', 'private'], true)) $errors[] = 'Choose a valid privacy setting.';
    foreach (['Meet link' => $meetLink, 'Zoom link' => $zoomLink] as $label => $url) {
        if ($url !== '' && !preg_match('#^https?://.+#i', $url)) $errors[] = "$label must be a full URL starting with http:// or https://";
    }

    if (!$errors) {
        $groupId = create_study_group($name, $description, $privacy, (int) $user['id'], $meetLink, $zoomLink, $scheduleText);
        $group = db_one('SELECT slug FROM study_groups WHERE id = ?', [$groupId]);
        flash_set('success', 'Study group created.');
        redirect('/study-groups/view.php?slug=' . $group['slug']);
    }
}

$pageTitle = 'Create a Study Group — Obin Academy';
require __DIR__ . '/../includes/header.php';
?>
<div class="container" style="max-width:560px; padding-top:32px; padding-bottom:72px;">
  <h1 class="h2">Create a Study Group</h1>
  <p class="muted small" style="margin-top:6px;">A small space with its own chat, meeting link, and schedule.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <form method="post" class="card card-pad" style="margin-top:20px;">
    <?= csrf_field() ?>
    <div class="field">
      <label for="name">Group Name</label>
      <input id="name" name="name" type="text" required maxlength="191" placeholder="e.g. CPA Level 1 Study Circle" value="<?= e(post('name')) ?>">
    </div>
    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="3" placeholder="What's this group about, and who should join?"><?= e(post('description')) ?></textarea>
    </div>
    <div class="field">
      <label>Privacy</label>
      <div class="chip-row">
        <label class="chip active" style="cursor:pointer;"><input type="radio" name="privacy" value="public" checked style="width:auto; margin:0 6px 0 0;">Public — listed in the directory</label>
        <label class="chip" style="cursor:pointer;"><input type="radio" name="privacy" value="private" style="width:auto; margin:0 6px 0 0;">Private — link only</label>
      </div>
    </div>
    <div class="field">
      <label for="scheduleText">Meeting Schedule (optional)</label>
      <input id="scheduleText" name="scheduleText" type="text" maxlength="255" placeholder="e.g. Saturdays, 4pm EAT" value="<?= e(post('scheduleText')) ?>">
    </div>
    <div class="field">
      <label for="meetLink">Google Meet Link (optional)</label>
      <input id="meetLink" name="meetLink" type="url" placeholder="https://meet.google.com/…" value="<?= e(post('meetLink')) ?>">
    </div>
    <div class="field">
      <label for="zoomLink">Zoom Link (optional)</label>
      <input id="zoomLink" name="zoomLink" type="url" placeholder="https://zoom.us/j/…" value="<?= e(post('zoomLink')) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Create Group</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
