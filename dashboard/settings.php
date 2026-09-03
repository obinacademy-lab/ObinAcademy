<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/profiles.php';
$user = require_login();

$errors = [];
$isCreator = in_array($user['role'], ['CREATOR', 'ADMIN'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = post('name');
    $phone = post('phone');
    $headline = post('headline');
    $bio = post('bio');
    $facebookUrl = post('facebookUrl');
    $instagramUrl = post('instagramUrl');
    $youtubeUrl = post('youtubeUrl');
    $tiktokUrl = post('tiktokUrl');
    $linkedinUrl = post('linkedinUrl');
    $skills = tags_to_csv(explode(',', post('skills')));
    $lookingFor = tags_to_csv(array_intersect($_POST['looking_for'] ?? [], LOOKING_FOR_OPTIONS));

    if (strlen($name) < 1) $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\s-]{9,}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    foreach (['Facebook' => $facebookUrl, 'Instagram' => $instagramUrl, 'YouTube' => $youtubeUrl, 'TikTok' => $tiktokUrl, 'LinkedIn' => $linkedinUrl] as $label => $url) {
        if ($url !== '' && !preg_match('#^https?://.+#i', $url)) $errors[] = "$label link must be a full URL starting with http:// or https://";
    }

    $avatarUrl = null;
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $avatarUrl = save_upload($_FILES['avatar'], 'thumbnails');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $sql = 'UPDATE users SET name=?, phone=?, headline=?, bio=?, facebook_url=?, instagram_url=?, youtube_url=?, tiktok_url=?, linkedin_url=?, skills=?, looking_for=?' . ($avatarUrl ? ', avatar_url=?' : '') . ' WHERE id=?';
        $params = [$name, $phone ?: null, $headline ?: null, $bio ?: null, $facebookUrl ?: null, $instagramUrl ?: null, $youtubeUrl ?: null, $tiktokUrl ?: null, $linkedinUrl ?: null, $skills, $lookingFor];
        if ($avatarUrl) $params[] = $avatarUrl;
        $params[] = $user['id'];
        db_run($sql, $params);

        flash_set('success', 'Your settings have been saved.');
        redirect('/dashboard/settings.php');
    }
}

$pageTitle = 'Settings — Obin Academy';
require __DIR__ . '/../includes/dashboard_header.php';
?>
<h1 class="h2">Settings</h1>

<?php if ($errors): ?>
  <div class="alert alert-error" style="margin-top:16px;"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card card-pad" style="margin-top:20px; max-width:560px;">
  <?= csrf_field() ?>
  <div class="field">
    <label for="name">Full Name</label>
    <input id="name" name="name" type="text" required value="<?= e($user['name']) ?>">
  </div>
  <div class="field">
    <label>Email</label>
    <input disabled value="<?= e($user['email']) ?>" style="background: var(--surface); color: var(--muted);">
  </div>
  <div class="field">
    <label for="phone">Phone Number</label>
    <input id="phone" name="phone" type="tel" placeholder="e.g. 0772 123 456" value="<?= e($user['phone'] ?? '') ?>">
  </div>

  <div class="field">
    <label for="headline"><?= $isCreator ? 'Headline' : 'Headline (optional)' ?></label>
    <input id="headline" name="headline" type="text" placeholder="<?= $isCreator ? 'e.g. Financial Analyst & Educator' : 'e.g. Aspiring Accountant | Small Business Owner' ?>" value="<?= e($user['headline'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="bio">Bio</label>
    <textarea id="bio" name="bio" rows="4" placeholder="Shown on your community profile."><?= e($user['bio'] ?? '') ?></textarea>
  </div>
  <div class="field">
    <label for="skills">Skills</label>
    <p class="help" style="margin-bottom:10px;">Comma-separated — shown as tags on your community profile.</p>
    <input id="skills" name="skills" type="text" placeholder="e.g. Budgeting, Excel, Public Speaking" value="<?= e(implode(', ', parse_csv_tags($user['skills'] ?? null))) ?>">
  </div>
  <div class="field">
    <label>Looking For</label>
    <p class="help" style="margin-bottom:10px;">Let the community know what you're open to — shown on your profile.</p>
    <?php $myLookingFor = parse_csv_tags($user['looking_for'] ?? null); ?>
    <div class="chip-row">
      <?php foreach (LOOKING_FOR_OPTIONS as $option): ?>
        <label class="chip<?= in_array($option, $myLookingFor, true) ? ' active' : '' ?>" style="cursor:pointer;">
          <input type="checkbox" name="looking_for[]" value="<?= e($option) ?>" <?= in_array($option, $myLookingFor, true) ? 'checked' : '' ?> style="width:auto; margin:0 6px 0 0;">
          <?= e($option) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="field">
    <label>Social Media Links</label>
    <p class="help" style="margin-bottom:10px;">Let others follow and connect with you outside the platform.</p>
    <div class="stack gap-2">
      <input name="facebookUrl" type="url" placeholder="Facebook profile URL" value="<?= e($user['facebook_url'] ?? '') ?>">
      <input name="instagramUrl" type="url" placeholder="Instagram profile URL" value="<?= e($user['instagram_url'] ?? '') ?>">
      <input name="youtubeUrl" type="url" placeholder="YouTube channel URL" value="<?= e($user['youtube_url'] ?? '') ?>">
      <input name="tiktokUrl" type="url" placeholder="TikTok profile URL" value="<?= e($user['tiktok_url'] ?? '') ?>">
      <input name="linkedinUrl" type="url" placeholder="LinkedIn profile URL" value="<?= e($user['linkedin_url'] ?? '') ?>">
    </div>
  </div>

  <div class="field">
    <label for="avatar">Profile Photo</label>
    <input id="avatar" name="avatar" type="file" accept="image/*">
  </div>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
