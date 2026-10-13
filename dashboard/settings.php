<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/storage.php';
$user = require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('formName') === 'theme') {
    csrf_verify();
    $theme = post('dashboardThemeColor');
    if (isset(DASHBOARD_THEMES[$theme])) {
        db_run('UPDATE users SET dashboard_theme_color=? WHERE id=?', [$theme, $user['id']]);
        flash_set('success', 'Dashboard theme updated.');
    }
    redirect('/dashboard/settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = post('name');
    $phone = post('phone');
    $headline = post('headline');

    if (strlen($name) < 1) $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\s-]{9,}$/', $phone)) $errors[] = 'Enter a valid phone number.';

    $avatarUrl = null;
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $avatarUrl = save_upload($_FILES['avatar'], 'thumbnails');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $params = [$name, $phone ?: null, $headline ?: null];
        if ($avatarUrl) $params[] = $avatarUrl;
        $params[] = $user['id'];
        db_run('UPDATE users SET name=?, phone=?, headline=?' . ($avatarUrl ? ', avatar_url=?' : '') . ' WHERE id=?', $params);
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
    <p class="help" style="margin-bottom:8px;">Used for your campus renewal reminders and as the default mobile money number.</p>
    <input id="phone" name="phone" type="tel" placeholder="e.g. 0772 123 456" value="<?= e($user['phone'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="headline">Headline (optional)</label>
    <input id="headline" name="headline" type="text" placeholder="e.g. Aspiring trader | Small business owner" value="<?= e($user['headline'] ?? '') ?>">
  </div>
  <div class="field">
    <label for="avatar">Profile Photo</label>
    <input id="avatar" name="avatar" type="file" accept="image/*">
  </div>
  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>

<div class="card card-pad" style="margin-top:20px; max-width:560px;">
  <label style="display:block;">Dashboard Theme</label>
  <p class="help" style="margin-bottom:14px;">Pick the color that shows up across your dashboard's sidebar, buttons, and charts.</p>
  <form method="post" id="theme-form">
    <?= csrf_field() ?>
    <input type="hidden" name="formName" value="theme">
    <input type="hidden" name="dashboardThemeColor" id="theme-input" value="<?= e(dashboard_theme_for_user($user)) ?>">
    <div class="theme-swatch-row">
      <?php foreach (DASHBOARD_THEMES as $key => $theme): ?>
        <button type="button" class="theme-swatch <?= dashboard_theme_for_user($user) === $key ? 'is-active' : '' ?>" data-theme-key="<?= e($key) ?>" style="--swatch: <?= e($theme['swatch']) ?>;" aria-label="<?= e($theme['label']) ?>" title="<?= e($theme['label']) ?>">
          <span class="theme-swatch-dot"></span>
          <span class="theme-swatch-label"><?= e($theme['label']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </form>
</div>
<script>
(function () {
  var form = document.getElementById('theme-form');
  var input = document.getElementById('theme-input');
  var shell = document.querySelector('.dash');
  if (!form) return;
  form.querySelectorAll('.theme-swatch').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-theme-key');
      input.value = key;
      form.querySelectorAll('.theme-swatch').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      if (shell) {
        shell.className = shell.className.replace(/\btheme-\S+/g, '').trim() + ' theme-' + key;
      }
      form.submit();
    });
  });
})();
</script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
