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
// Same fixed 6-color rotation used for school cards without a cover photo
// (includes/school_card.php) — deterministic per user id, so a given
// follower's ring color stays stable across page loads.
$avatarTints = ['#0b00ff', '#0e7490', '#b45309', '#15803d', '#7c3aed', '#a21caf'];
?>
<div class="container" style="max-width:640px; padding-top:40px; padding-bottom:80px;">
  <a href="<?= e(base_url('profile.php?id=' . $creatorId)) ?>" class="followers-back-link">
    <span class="icon"><?php dash_icon('arrow-left'); ?></span> Back to <?= e($schoolLabel) ?>
  </a>

  <div class="followers-identity">
    <div class="followers-identity-avatar">
      <?php if ($profile['avatar_url']): ?><img src="<?= e(asset_src($profile['avatar_url'])) ?>" alt="">
      <?php else: ?><?= e(mb_substr($schoolLabel, 0, 1)) ?><?php endif; ?>
    </div>
    <div>
      <h1><?= e($schoolLabel) ?></h1>
      <span class="followers-count-badge"><span class="dot"></span><?= number_format(count($followers)) ?> Follower<?= count($followers) === 1 ? '' : 's' ?></span>
    </div>
  </div>

  <?php if (!$followers): ?>
    <div class="followers-section-label">People Following This School</div>
    <div class="card card-pad" style="text-align:center; margin-top:14px; border-style:dashed;">
      <p class="muted">No one is following this school yet.</p>
    </div>
  <?php else: ?>
    <div class="followers-section-label">People Following This School</div>
    <div class="followers-panel">
      <?php foreach ($followers as $f): ?>
        <div class="followers-row">
          <div class="followers-row-avatar" style="--tint: <?= e($avatarTints[(int) $f['id'] % count($avatarTints)]) ?>;">
            <?php if ($f['avatar_url']): ?><img src="<?= e(asset_src($f['avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($f['name'], 0, 1)) ?><?php endif; ?>
          </div>
          <span class="followers-row-name"><?= e($f['name']) ?></span>
          <span class="followers-row-since">Since <?= e(format_date($f['created_at'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
