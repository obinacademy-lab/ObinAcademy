<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/community.php';

$user = current_user();
$q = query_param('q');
$groups = get_study_groups($q, 50);
$myGroups = $user ? get_user_study_groups((int) $user['id']) : [];

$pageTitle = 'Study Groups — Obin Academy';
$pageDescription = 'Join a study group to learn together — live meetups, shared discussion, and a running group chat.';
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill">👥 Study Groups</span>
    <h1 style="margin-top:14px;">Learn together, not alone</h1>
    <p class="summary" style="margin-top:10px; margin-left:auto; margin-right:auto;">Small groups meeting around a shared goal — with a running group chat and a place to keep your meet link.</p>
    <?php if ($user): ?>
      <a href="<?= e(base_url('study-groups/create.php')) ?>" class="btn btn-primary" style="margin-top:22px;">+ Create a Study Group</a>
    <?php else: ?>
      <a href="<?= e(base_url('login.php?redirect=' . urlencode('/study-groups/create.php'))) ?>" class="btn btn-primary" style="margin-top:22px;">Log in to Create a Group</a>
    <?php endif; ?>
  </div>
</section>

<div class="container" style="padding-top:32px; padding-bottom:72px; max-width:820px;">
  <?php if ($myGroups): ?>
    <h2 class="h3">Your Study Groups</h2>
    <div class="profile-compact-list card card-pad" style="margin-top:14px;">
      <?php foreach ($myGroups as $g): ?>
        <a href="<?= e(base_url('study-groups/view.php?slug=' . $g['slug'])) ?>" class="profile-compact-row">
          <div class="thumb" style="display:flex; align-items:center; justify-content:center; font-size:18px;">👥</div>
          <div>
            <div class="title"><?= e($g['name']) ?></div>
            <div class="sub"><?= number_format((int) $g['member_count']) ?> member<?= (int) $g['member_count'] === 1 ? '' : 's' ?> · <?= $g['role'] === 'owner' ? 'Owner' : 'Member' ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="h3" style="margin-top:32px;">Browse Public Groups</h2>
  <form method="get" class="field-icon" style="margin-top:14px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search study groups…">
  </form>

  <?php if (!$groups): ?>
    <div class="feed-empty" style="margin-top:18px;">No public study groups<?= $q !== '' ? ' match “' . e($q) . '”' : ' yet' ?>. <?= $user ? 'Start one!' : '' ?></div>
  <?php else: ?>
    <div class="grid sm:grid-2" style="margin-top:18px; gap:14px;">
      <?php foreach ($groups as $g): ?>
        <a href="<?= e(base_url('study-groups/view.php?slug=' . $g['slug'])) ?>" class="card card-pad card-hover" style="display:block;">
          <div style="font-weight:800; font-size:15px;"><?= e($g['name']) ?></div>
          <?php if ($g['description']): ?><p class="small muted" style="margin-top:6px; line-height:1.6;"><?= e(mb_strimwidth($g['description'], 0, 100, '…')) ?></p><?php endif; ?>
          <div class="small muted" style="margin-top:10px;">👥 <?= number_format((int) $g['member_count']) ?> member<?= (int) $g['member_count'] === 1 ? '' : 's' ?><?= $g['schedule_text'] ? ' · 🗓 ' . e($g['schedule_text']) : '' ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
