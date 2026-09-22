<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/storage.php';
$user = require_login();

$errors = [];
$isCreator = in_array($user['role'], ['CREATOR', 'ADMIN'], true);

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
    $bio = post('bio');
    $facebookUrl = post('facebookUrl');
    $instagramUrl = post('instagramUrl');
    $youtubeUrl = post('youtubeUrl');
    $tiktokUrl = post('tiktokUrl');
    $linkedinUrl = post('linkedinUrl');
    $schoolName = $isCreator ? post('schoolName') : '';
    $pricingModel = $isCreator ? post('pricingModel', 'PER_COURSE') : 'PER_COURSE';
    $schoolMonthlyPrice = $isCreator ? post('schoolMonthlyPrice') : '';

    if (strlen($name) < 1) $errors[] = 'Name is required.';
    if ($phone !== '' && !preg_match('/^[0-9+\s-]{9,}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    foreach (['Facebook' => $facebookUrl, 'Instagram' => $instagramUrl, 'YouTube' => $youtubeUrl, 'TikTok' => $tiktokUrl, 'LinkedIn' => $linkedinUrl] as $label => $url) {
        if ($url !== '' && !preg_match('#^https?://.+#i', $url)) $errors[] = "$label link must be a full URL starting with http:// or https://";
    }
    if ($isCreator) {
        if (!in_array($pricingModel, ['PER_COURSE', 'MONTHLY_SUBSCRIPTION'], true)) $pricingModel = 'PER_COURSE';
        if ($pricingModel === 'MONTHLY_SUBSCRIPTION' && (!is_numeric($schoolMonthlyPrice) || (float) $schoolMonthlyPrice <= 0)) {
            $errors[] = 'Enter a monthly subscription price greater than zero.';
        }
    }

    $avatarUrl = null;
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $avatarUrl = save_upload($_FILES['avatar'], 'thumbnails');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    $schoolCoverUrl = null;
    if ($isCreator && !empty($_FILES['schoolCover']['name'])) {
        try {
            $schoolCoverUrl = save_upload($_FILES['schoolCover'], 'thumbnails');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $sql = 'UPDATE users SET name=?, phone=?, headline=?, bio=?, facebook_url=?, instagram_url=?, youtube_url=?, tiktok_url=?, linkedin_url=?'
             . ($isCreator ? ', school_name=?, pricing_model=?, school_monthly_price=?' : '')
             . ($avatarUrl ? ', avatar_url=?' : '')
             . ($schoolCoverUrl ? ', school_cover_url=?' : '')
             . ' WHERE id=?';
        $params = [$name, $phone ?: null, $headline ?: null, $bio ?: null, $facebookUrl ?: null, $instagramUrl ?: null, $youtubeUrl ?: null, $tiktokUrl ?: null, $linkedinUrl ?: null];
        if ($isCreator) {
            $params[] = $schoolName ?: null;
            $params[] = $pricingModel;
            $params[] = $pricingModel === 'MONTHLY_SUBSCRIPTION' ? (float) $schoolMonthlyPrice : null;
        }
        if ($avatarUrl) $params[] = $avatarUrl;
        if ($schoolCoverUrl) $params[] = $schoolCoverUrl;
        $params[] = $user['id'];
        db_run($sql, $params);

        // Switching a school into subscription mode makes every course's own
        // price meaningless (and actively misleading — a stale price would
        // wrongly show as "Free" once it's 0, or as a per-course buy price
        // that no longer applies) — see includes/course_card.php and
        // includes/enroll_panel.php, both of which now hide the course's own
        // price for a subscription school. Clearing it here keeps the
        // creator's own course-manage forms honest too, and only fires on
        // the transition INTO subscription mode — switching back to
        // per-course never touches price data, since the creator would then
        // need to re-enter it themselves.
        if ($isCreator && $pricingModel === 'MONTHLY_SUBSCRIPTION' && $user['pricing_model'] !== 'MONTHLY_SUBSCRIPTION') {
            db_run("UPDATE courses SET price = 0, sale_price = NULL, sale_ends_at = NULL WHERE creator_id = ?", [$user['id']]);
        }

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
    <textarea id="bio" name="bio" rows="4" placeholder="Shown on your profile."><?= e($user['bio'] ?? '') ?></textarea>
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

  <?php if ($isCreator): ?>
    <div class="field">
      <label for="schoolName">Your School Name</label>
      <p class="help" style="margin-bottom:8px;">Shown at the top of your public page instead of a generic profile — e.g. "Sarah's Finance Academy". Leave blank to just show "<?= e($user['name']) ?>".</p>
      <input id="schoolName" name="schoolName" type="text" placeholder="e.g. <?= e($user['name']) ?>'s School" value="<?= e($user['school_name'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="schoolCover">School Cover Banner</label>
      <p class="help" style="margin-bottom:8px;">A wide photo shown behind your school name. Leave blank to use the default Obin Academy banner.</p>
      <input id="schoolCover" name="schoolCover" type="file" accept="image/*">
    </div>
    <div class="field">
      <label>Pricing Model</label>
      <p class="help" style="margin-bottom:10px;">Choose how learners pay for your school. Switching later doesn't affect anyone who already bought a course — they keep that course either way.</p>
      <div class="stack gap-2">
        <label class="row gap-2" style="align-items:flex-start; font-weight:600; cursor:pointer;">
          <input type="radio" name="pricingModel" value="PER_COURSE" data-pricing-radio <?= ($user['pricing_model'] ?? 'PER_COURSE') === 'PER_COURSE' ? 'checked' : '' ?> style="margin-top:3px;">
          <span>Charge per course<br><span class="help" style="font-weight:400;">Learners pay once for each course they want — set a price per course from the Creator Dashboard, like today.</span></span>
        </label>
        <label class="row gap-2" style="align-items:flex-start; font-weight:600; cursor:pointer;">
          <input type="radio" name="pricingModel" value="MONTHLY_SUBSCRIPTION" data-pricing-radio <?= ($user['pricing_model'] ?? '') === 'MONTHLY_SUBSCRIPTION' ? 'checked' : '' ?> style="margin-top:3px;">
          <span>Monthly subscription<br><span class="help" style="font-weight:400;">Learners pay the same monthly price per course instead of a one-time fee — subscribing to one course doesn't unlock your others, they'd subscribe to each separately.</span></span>
        </label>
      </div>
      <div class="field" data-monthly-price-field style="margin-top:14px; <?= ($user['pricing_model'] ?? '') === 'MONTHLY_SUBSCRIPTION' ? '' : 'display:none;' ?>">
        <label for="schoolMonthlyPrice">Monthly Price (UGX)</label>
        <input id="schoolMonthlyPrice" name="schoolMonthlyPrice" type="number" min="1000" step="1000" placeholder="e.g. 50000" value="<?= e($user['school_monthly_price'] ?? '') ?>">
      </div>
    </div>
    <script>
      (() => {
        const radios = document.querySelectorAll('[data-pricing-radio]');
        const priceField = document.querySelector('[data-monthly-price-field]');
        if (!radios.length || !priceField) return;
        radios.forEach((r) => r.addEventListener('change', () => {
          priceField.style.display = document.querySelector('[data-pricing-radio]:checked').value === 'MONTHLY_SUBSCRIPTION' ? '' : 'none';
        }));
      })();
    </script>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
