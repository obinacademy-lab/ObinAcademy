<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/storage.php';
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
        $sql = 'UPDATE users SET name=?, phone=?, headline=?, bio=?, facebook_url=?, instagram_url=?, youtube_url=?, tiktok_url=?, linkedin_url=?' . ($avatarUrl ? ', avatar_url=?' : '') . ' WHERE id=?';
        $params = [$name, $phone ?: null, $headline ?: null, $bio ?: null, $facebookUrl ?: null, $instagramUrl ?: null, $youtubeUrl ?: null, $tiktokUrl ?: null, $linkedinUrl ?: null];
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

  <?php if ($isCreator): ?>
    <div class="field">
      <label for="headline">Headline</label>
      <input id="headline" name="headline" type="text" placeholder="e.g. Financial Analyst & Educator" value="<?= e($user['headline'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="bio">Bio</label>
      <textarea id="bio" name="bio" rows="4"><?= e($user['bio'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label>Social Media Links</label>
      <p class="help" style="margin-bottom:10px;">Let learners follow and connect with you outside the platform.</p>
      <div class="stack gap-2">
        <input name="facebookUrl" type="url" placeholder="Facebook profile URL" value="<?= e($user['facebook_url'] ?? '') ?>">
        <input name="instagramUrl" type="url" placeholder="Instagram profile URL" value="<?= e($user['instagram_url'] ?? '') ?>">
        <input name="youtubeUrl" type="url" placeholder="YouTube channel URL" value="<?= e($user['youtube_url'] ?? '') ?>">
        <input name="tiktokUrl" type="url" placeholder="TikTok profile URL" value="<?= e($user['tiktok_url'] ?? '') ?>">
        <input name="linkedinUrl" type="url" placeholder="LinkedIn profile URL" value="<?= e($user['linkedin_url'] ?? '') ?>">
      </div>
    </div>
  <?php endif; ?>

  <div class="field">
    <label for="avatar">Profile Photo</label>
    <input id="avatar" name="avatar" type="file" accept="image/*">
  </div>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
