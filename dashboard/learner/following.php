<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/follows.php';
$user = require_login();

$followed = get_followed_schools_for_learner((int) $user['id']);

$pageTitle = 'Following — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Following</h1>
<p class="muted" style="margin-top:6px;">Schools you follow — you'll be first to know when they publish a new course or run a sale.</p>

<?php if (!$followed): ?>
  <div class="card card-pad" style="text-align:center; margin-top:24px; border-style:dashed;">
    <p class="muted">You're not following any schools yet.</p>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Explore Schools</a>
  </div>
<?php else: ?>
  <div class="stack gap-3" style="margin-top:24px; max-width:640px;">
    <?php foreach ($followed as $f): $schoolLabel = $f['school_name'] ?: $f['creator_name']; ?>
      <div class="card card-pad">
        <div class="row between wrap gap-2">
          <div class="row gap-3" style="align-items:center;">
            <div class="profile-avatar" style="width:48px; height:48px; font-size:18px;">
              <?php if ($f['creator_avatar_url']): ?><img src="<?= e(asset_src($f['creator_avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($f['creator_name'], 0, 1)) ?><?php endif; ?>
            </div>
            <div>
              <a href="<?= e(base_url('profile.php?id=' . $f['creator_id'])) ?>" style="font-weight:700; color:var(--ink);"><?= e($schoolLabel) ?></a>
              <p class="small muted" style="margin-top:2px;"><?= number_format((int) $f['course_count']) ?> course<?= (int) $f['course_count'] === 1 ? '' : 's' ?></p>
            </div>
          </div>
          <a href="<?= e(base_url('profile.php?id=' . $f['creator_id'])) ?>" class="btn btn-outline btn-sm">Visit School</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
