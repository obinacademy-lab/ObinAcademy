<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/follows.php';

$creatorId = (int) query_param('id');
$profile = $creatorId ? get_profile($creatorId) : null;
if (!$profile || !in_array($profile['role'], ['CREATOR', 'ADMIN'], true)) { http_response_code(404); exit('School not found'); }

$schoolLabel = $profile['school_name'] ?: $profile['name'];
$followers = get_school_followers_public($creatorId);

$pageTitle = $schoolLabel . ' — Followers — Obin Academy';
$pageDescription = 'People following ' . $schoolLabel . ' on Obin Academy.';
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:640px; padding-top:40px; padding-bottom:80px;">
  <a href="<?= e(base_url('profile.php?id=' . $creatorId)) ?>" class="back-link" style="display:inline-flex; align-items:center; gap:6px; font-size:13.5px; font-weight:600; color:var(--muted); text-decoration:none;">
    <?php dash_icon('arrow-left'); ?> Back to <?= e($schoolLabel) ?>
  </a>
  <h1 class="h3" style="margin-top:16px;"><?= number_format(count($followers)) ?> Follower<?= count($followers) === 1 ? '' : 's' ?></h1>
  <p class="muted" style="margin-top:4px;">People following <?= e($schoolLabel) ?>.</p>

  <?php if (!$followers): ?>
    <div class="card card-pad" style="text-align:center; margin-top:24px; border-style:dashed;">
      <p class="muted">No one is following this school yet.</p>
    </div>
  <?php else: ?>
    <div class="stack gap-2" style="margin-top:24px;">
      <?php foreach ($followers as $f): ?>
        <div class="card card-pad row gap-3" style="align-items:center;">
          <div class="profile-avatar" style="width:44px; height:44px; font-size:16px; flex-shrink:0;">
            <?php if ($f['avatar_url']): ?><img src="<?= e(asset_src($f['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($f['name'], 0, 1)) ?><?php endif; ?>
          </div>
          <div>
            <span style="font-weight:700; color:var(--ink);"><?= e($f['name']) ?></span>
            <p class="small muted" style="margin-top:2px;">Following since <?= e(format_date($f['created_at'])) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
