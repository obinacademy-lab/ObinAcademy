<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$slug = query_param('slug');
$community = get_community_by_slug($slug);
if (!$community) { http_response_code(404); exit('Community not found'); }

$user = current_user();
$isMember = $user && is_community_member((int) $community['id'], (int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    csrf_verify();
    $action = post('_action');
    if ($action === 'join') {
        join_community((int) $community['id'], (int) $user['id']);
    } elseif ($action === 'leave') {
        leave_community((int) $community['id'], (int) $user['id']);
    }
    redirect('/community/view.php?slug=' . $slug);
}

$course = $community['course_id'] ? db_one('SELECT id, title, slug, thumbnail_url, creator_id FROM courses WHERE id = ?', [$community['course_id']]) : null;
// The course's creator's OWN community, for a "Creator Profile" link on a
// course community page — not shown when already viewing a creator
// community, since that would just link to the current page.
$creatorCommunity = ($community['type'] === 'course' && $course) ? get_community_by_creator((int) $course['creator_id']) : null;
$categories = get_community_categories((int) $community['id']);
$members = get_community_members((int) $community['id'], 24);

$pageTitle = $community['name'] . ' — Community — Obin Academy';
$pageDescription = $community['description'] ?: ('Join the ' . $community['name'] . ' discussion on Obin Academy.');
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:820px;">
    <div class="community-header">
      <div class="avatar">
        <?php if ($community['banner_url']): ?><img src="<?= e(asset_src($community['banner_url'])) ?>" alt="">
        <?php else: ?><?= $community['type'] === 'creator' ? '👤' : '🎓' ?><?php endif; ?>
      </div>
      <span class="pill" style="margin-top:14px;"><?= $community['type'] === 'creator' ? 'Creator Community' : 'Course Community' ?></span>
      <h1 style="margin-top:14px; text-align:center;"><?= e($community['name']) ?></h1>
      <?php if ($community['description']): ?><p class="summary" style="margin-top:10px; text-align:center;"><?= e($community['description']) ?></p><?php endif; ?>

      <div class="community-stats-row">
        <div class="stat"><span class="value"><?= number_format((int) $community['member_count']) ?></span><span class="label">Member<?= (int) $community['member_count'] === 1 ? '' : 's' ?></span></div>
        <div class="stat"><span class="value"><?= count($categories) ?></span><span class="label">Categor<?= count($categories) === 1 ? 'y' : 'ies' ?></span></div>
      </div>

      <div class="row gap-2" style="margin-top:22px;">
        <?php if (!$user): ?>
          <a href="<?= e(base_url('signup.php')) ?>" class="btn btn-primary">Sign Up to Join</a>
        <?php elseif ($isMember): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="leave">
            <button type="submit" class="btn btn-outline">✓ Member — Leave</button>
          </form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="join">
            <button type="submit" class="btn btn-primary">Join Community</button>
          </form>
        <?php endif; ?>
        <?php if ($course): ?><a href="<?= e(base_url('courses/view.php?slug=' . $course['slug'])) ?>" class="btn btn-outline">View Course</a><?php endif; ?>
        <?php if ($creatorCommunity): ?><a href="<?= e(base_url('community/view.php?slug=' . $creatorCommunity['slug'])) ?>" class="btn btn-outline">Creator Community</a><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px;">
  <div class="grid lg:grid-2" style="gap:32px;">
    <div>
      <h2 class="h3">Discussion Categories</h2>
      <p class="muted small" style="margin-top:4px;">The feed for posting and replying is coming very soon — categories are ready now so the space is set up.</p>
      <div class="grid sm:grid-2" style="margin-top:16px; gap:12px;">
        <?php foreach ($categories as $cat): ?>
          <div class="card card-pad" style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:20px;"><?= e($cat['icon']) ?></span>
            <span style="font-weight:700; font-size:14px;"><?= e($cat['name']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <h2 class="h3">Members</h2>
      <div class="card card-pad" style="margin-top:16px;">
        <?php if ($members): ?>
          <div style="display:flex; flex-direction:column; gap:14px;">
            <?php foreach ($members as $m): ?>
              <div class="row gap-2" style="align-items:center;">
                <div class="row-avatar" style="width:40px; height:40px; border-radius:50%;">
                  <?php if ($m['avatar_url']): ?><img src="<?= e(asset_src($m['avatar_url'])) ?>" alt="" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                  <?php else: ?><?= e(mb_substr($m['name'], 0, 1)) ?><?php endif; ?>
                </div>
                <div style="min-width:0; flex:1;">
                  <div style="font-weight:700; font-size:13.5px;"><?= e($m['name']) ?></div>
                  <div class="small muted"><?= e($m['headline'] ?: ($m['role'] !== 'member' ? ucfirst($m['role']) : 'Member')) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted small">No members yet — be the first to join.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
