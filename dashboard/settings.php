<?php
/**
 * Settings, for every account (members, professors and admins): a dark identity console with the
 * profile card and how complete the profile is; below, the profile form (photo with a live preview,
 * name, headline, phone), the password change and the dashboard colour.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/settings.css. Photo preview and
 * password checks: assets/js/settings.js.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/professor.php';
$user = require_login();
$isProf = is_professor($user); // professors have no sidebar, so no sidebar colour

$errors = [];
$pwErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('formName') === 'theme') {
    csrf_verify();
    $theme = post('dashboardThemeColor');
    if (isset(DASHBOARD_THEMES[$theme])) {
        db_run('UPDATE users SET dashboard_theme_color=? WHERE id=?', [$theme, $user['id']]);
        flash_set('success', 'Dashboard theme updated.');
    }
    redirect('/dashboard/settings.php#look');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('formName') === 'sound') {
    csrf_verify();
    // Class room alerts: the short sound when someone posts in a campus class room.
    try { db_run('UPDATE users SET chat_sound = ? WHERE id = ?', [post('chatSound') === '1' ? 1 : 0, $user['id']]); } catch (Throwable $e) {}
    flash_set('success', post('chatSound') === '1' ? 'Notification sound is on.' : 'Notification sound is off. You still get notifications, without the sound.');
    redirect('/dashboard/settings.php#alerts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('formName') === 'password') {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!empty($user['password_hash']) && !verify_password($current, $user['password_hash'])) $pwErrors[] = 'Your current password is not right.';
    if (strlen($new) < 8) $pwErrors[] = 'The new password must be at least 8 characters.';
    elseif ($new !== $confirm) $pwErrors[] = 'The two new passwords do not match.';
    elseif (!empty($user['password_hash']) && verify_password($new, $user['password_hash'])) $pwErrors[] = 'Choose a password different from your current one.';
    if (!$pwErrors) {
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($new), $user['id']]);
        session_regenerate_id(true);
        flash_set('success', 'Your password has been changed.');
        redirect('/dashboard/settings.php#security');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array(post('formName'), ['theme', 'password', 'sound'], true)) {
    csrf_verify();
    $name = trim(post('name'));
    $phone = trim(post('phone'));
    $headline = trim(post('headline'));

    if (strlen($name) < 1) $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\s-]{9,}$/', $phone)) $errors[] = 'Enter a valid phone number.';

    $avatarUrl = null;
    if (!$errors && !empty($_FILES['avatar']['name'])) {
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

// What the form shows: what was just typed when saving failed, otherwise what is stored.
$val = fn(string $k) => $_SERVER['REQUEST_METHOD'] === 'POST' && $errors ? post($k) : (string) ($user[$k] ?? '');
$initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($user['name'])) ?: [''], 0, 2)))) ?: '?';
$roleLabel = $isProf ? 'Professor' : (['ADMIN' => 'Admin', 'LEARNER' => 'Member', 'CREATOR' => 'Member'][$user['role']] ?? ucfirst(strtolower($user['role'])));
$checks = [
    ['Your name', trim($user['name']) !== ''],
    ['Profile photo', !empty($user['avatar_url'])],
    ['Phone number', !empty($user['phone'])],
    ['Headline', !empty($user['headline'])],
    ['Password set', !empty($user['password_hash'])],
];
$done = count(array_filter(array_column($checks, 1)));
$pct = (int) round($done / count($checks) * 100);
$currentTheme = dashboard_theme_for_user($user);

$pageTitle = 'Settings — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/settings.css'];
require __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="ov acx se" data-se>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Settings</h1><p>Your profile, your password and how your dashboard looks.</p></div>
  </div>

  <section class="lc-console se-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div class="se-id">
      <span class="lc-eyebrow"><i></i><?= e($roleLabel) ?> account</span>
      <div class="se-card">
        <span class="se-photo" data-photo-mirror><?php if (!empty($user['avatar_url'])): ?><img src="<?= e(asset_src($user['avatar_url'])) ?>" alt=""><?php else: ?><b><?= e($initials) ?></b><?php endif; ?></span>
        <div>
          <h2><?= e($user['name']) ?></h2>
          <p><?= e($user['headline'] ?: 'Add a headline so people know what you do') ?></p>
        </div>
      </div>
      <div class="lc-stats">
        <div><span>Email</span><b class="se-small" title="<?= e($user['email']) ?>"><?= e($user['email']) ?></b></div>
        <div><span>Phone</span><b class="se-small"><?= e($user['phone'] ?: 'Not added') ?></b></div>
        <div><span>Member since</span><b class="se-small"><?= e(date('j M Y', strtotime($user['created_at']))) ?></b></div>
        <?php if ($isProf): ?><div><span>Teaching</span><b class="se-small"><?= e(implode(', ', array_column(professor_campuses($user), 'name'))) ?></b></div><?php else: ?><div><span>Dashboard colour</span><b class="se-small"><i class="se-dot" style="--sw:<?= e(DASHBOARD_THEMES[$currentTheme]['swatch']) ?>"></i><?= e(DASHBOARD_THEMES[$currentTheme]['label']) ?></b></div><?php endif; ?>
      </div>
    </div>
    <div>
      <div class="lc-rx-h"><b>Profile strength</b><span><?= $done ?> of <?= count($checks) ?> done</span></div>
      <div class="se-meter">
        <div class="se-ring" style="--p:<?= $pct ?>"><b><span data-scramble="<?= $pct ?>"><?= $pct ?></span><em>%</em></b><small>complete</small></div>
        <ul class="se-checks">
          <?php foreach ($checks as $k => [$label, $ok]): ?>
            <li class="<?= $ok ? 'ok' : '' ?>" style="--k:<?= $k ?>"><i aria-hidden="true"><?= $ok ? '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>' : '' ?></i><?= e($label) ?><span><?= $ok ? 'Done' : 'Missing' ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <p class="se-why"><?= $pct === 100 ? 'Your profile is complete. Professors and other members see your photo and headline in campus communities.' : 'A photo and headline help professors and other members know who you are in campus communities. Your phone number is used for renewal reminders and as your Mobile Money number.' ?></p>
    </div>
  </section>

  <div class="se-cols">
    <section class="acx-sec acx-slam" style="--i:2" id="profile">
      <div class="se-h"><div><h2>Profile</h2><p>How you appear across Obin Academy.</p></div></div>
      <?php if ($errors): ?><div class="se-alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="se-form" data-profile>
        <?= csrf_field() ?><input type="hidden" name="formName" value="profile">
        <div class="se-upload">
          <span class="se-photo lg" data-photo><?php if (!empty($user['avatar_url'])): ?><img src="<?= e(asset_src($user['avatar_url'])) ?>" alt=""><?php else: ?><b><?= e($initials) ?></b><?php endif; ?></span>
          <div>
            <b>Profile photo</b>
            <small>A clear photo of your face works best. PNG, JPG or WebP, up to <?= e(format_max_size(UPLOAD_MAX_BYTES['thumbnails'])) ?>.</small>
            <label class="se-btn ghost sm" for="avatar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg><span data-photo-label><?= !empty($user['avatar_url']) ? 'Change photo' : 'Upload a photo' ?></span></label>
            <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="se-file" data-photo-input>
          </div>
        </div>
        <div class="se-grid">
          <label for="name">Full name<input id="name" name="name" type="text" required maxlength="120" value="<?= e($val('name')) ?>"></label>
          <label for="headline"><span>Headline <small>optional</small></span><input id="headline" name="headline" type="text" maxlength="160" placeholder="e.g. Aspiring trader | Small business owner" value="<?= e($val('headline')) ?>"></label>
          <label for="phone">Phone number<input id="phone" name="phone" type="tel" placeholder="e.g. 0772 123 456" value="<?= e($val('phone')) ?>"><small class="hint">For renewal reminders and as your default Mobile Money number.</small></label>
          <label>Email<span class="se-locked"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><?= e($user['email']) ?></span><small class="hint">Your sign-in email. Contact us to change it.</small></label>
        </div>
        <div class="se-actions"><button type="submit" class="se-btn lime">Save changes <span>→</span></button></div>
      </form>
    </section>

    <div class="se-side">
      <section class="acx-sec acx-slam" style="--i:3" id="security">
        <div class="se-h"><div><h2>Password</h2><p><?= !empty($user['password_hash']) ? 'Change the password you sign in with.' : 'Set a password to sign in with your email.' ?></p></div></div>
        <?php if ($pwErrors): ?><div class="se-alert"><?= e(implode(' ', $pwErrors)) ?></div><?php endif; ?>
        <form method="post" class="se-form" data-password>
          <?= csrf_field() ?><input type="hidden" name="formName" value="password">
          <input type="text" name="username" value="<?= e($user['email']) ?>" autocomplete="username" hidden>
          <?php if (!empty($user['password_hash'])): ?>
            <label for="current_password">Current password<span class="se-pw"><input id="current_password" name="current_password" type="password" required autocomplete="current-password"><button type="button" data-reveal aria-label="Show password">Show</button></span></label>
          <?php endif; ?>
          <label for="new_password">New password<span class="se-pw"><input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password" data-new><button type="button" data-reveal aria-label="Show password">Show</button></span></label>
          <div class="se-strength" data-strength><i></i><i></i><i></i><i></i><span>At least 8 characters</span></div>
          <label for="confirm_password">Repeat new password<span class="se-pw"><input id="confirm_password" name="confirm_password" type="password" required minlength="8" autocomplete="new-password" data-confirm-pw><button type="button" data-reveal aria-label="Show password">Show</button></span></label>
          <div class="se-actions"><button type="submit" class="se-btn">Change password</button><a class="se-link" href="<?= e(base_url('forgot-password.php')) ?>">Forgot it?</a></div>
        </form>
      </section>

      <?php $soundOn = !array_key_exists('chat_sound', $user) || (int) $user['chat_sound'] === 1; ?>
      <section class="acx-sec acx-slam" style="--i:4" id="alerts">
        <div class="se-h"><div><h2>Notifications</h2><p>When someone posts or replies in your campus class room, you get a notification and a short sound.</p></div></div>
        <form method="post" class="se-form se-sound">
          <?= csrf_field() ?><input type="hidden" name="formName" value="sound"><input type="hidden" name="chatSound" value="<?= $soundOn ? '0' : '1' ?>">
          <div class="se-sound-row"><span><b>Notification sound</b><small><?= $soundOn ? 'On. A short sound plays for new class room messages.' : 'Off. You still see notifications, just without the sound.' ?></small></span>
            <button type="submit" class="se-switch" role="switch" aria-checked="<?= $soundOn ? 'true' : 'false' ?>" aria-label="Notification sound"></button></div>
        </form>
      </section>

      <?php if (!$isProf): ?>
      <section class="acx-sec acx-slam" style="--i:5" id="look">
        <div class="se-h"><div><h2>Dashboard colour</h2><p>The colour of your sidebar. It changes as soon as you pick one.</p></div></div>
        <form method="post" id="theme-form" class="se-themes">
          <?= csrf_field() ?>
          <input type="hidden" name="formName" value="theme">
          <input type="hidden" name="dashboardThemeColor" id="theme-input" value="<?= e($currentTheme) ?>">
          <?php foreach (DASHBOARD_THEMES as $key => $theme): ?>
            <button type="button" class="se-theme <?= $currentTheme === $key ? 'on' : '' ?>" data-theme-key="<?= e($key) ?>" style="--sw: <?= e($theme['swatch']) ?>;" aria-pressed="<?= $currentTheme === $key ? 'true' : 'false' ?>">
              <span class="se-mini" aria-hidden="true"><i></i><em></em><em></em><em></em></span>
              <span class="se-theme-name"><?= e($theme['label']) ?></span>
            </button>
          <?php endforeach; ?>
        </form>
      </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/settings.js')) ?>" defer></script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
