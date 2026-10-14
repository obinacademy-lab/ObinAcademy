<?php
/** A learner's dashboard: their campuses, progress through each year, certificates and what to do next. */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_login();
require_once __DIR__ . '/../../includes/professor.php';
if (is_professor($user)) redirect('/dashboard/professor/index.php'); // professors have their own dashboard, no student pages

$myCampuses = campuses_ready() ? db_all(
    'SELECT m.*, c.name, c.slug, c.icon, c.live_schedule FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id WHERE m.user_id = ? ORDER BY m.access_until DESC',
    [$user['id']]
) : [];
$open = array_values(array_filter($myCampuses, fn($m) => strtotime($m['access_until']) > time()));
$certificates = count(array_filter($myCampuses, fn($m) => !empty($m['certificate_code'])));

// Lessons done across the campuses that are open now.
$lessonsDone = 0;
foreach ($open as $m) $lessonsDone += count(campus_completed_lesson_ids((int) $user['id'], (int) $m['campus_id']));

// The next live class in any of their open campuses.
$nextLive = null;
foreach ($open as $m) {
    $l = upcoming_campus_live_classes(14, (int) $m['campus_id'])[0] ?? null;
    if ($l && (!$nextLive || $l['starts_at'] < $nextLive['starts_at'])) $nextLive = $l;
}

$firstName = explode(' ', trim($user['name']))[0];
$quotes = [
    'Small steps, every day, add up to real change.',
    'The expert in anything was once a beginner.',
    'Discipline beats motivation when motivation runs out.',
    'Every lesson you finish is a skill you keep for life.',
    'Progress, not perfection.',
];
$quote = $quotes[(int) date('z') % count($quotes)];

$pageTitle = 'My Campuses — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-hero reveal">
  <div>
    <h1 class="h2" style="color:#fff;">Welcome back, <?= e($firstName) ?></h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72); font-style:italic;">"<?= e($quote) ?>"</p>
  </div>
  <div class="row gap-2" style="flex-wrap:wrap;">
    <?php if ($open): ?>
      <a href="<?= e(base_url('campus-learn.php?campus=' . $open[0]['slug'])) ?>" class="btn btn-gold" style="border-radius:999px;">▶ Go to your campus</a>
    <?php endif; ?>
    <a href="<?= e(base_url('index.php#campuses')) ?>" class="btn btn-outline" style="border-radius:999px;">Explore Campuses</a>
  </div>
</div>

<div class="grid md:grid-2 lg:grid-4" style="margin-top:24px;">
  <div class="stat-card accent-top reveal" data-hoverable="true" style="--hover-color:#0b00ff;">
    <div class="icon"><?php dash_icon('graduation-cap'); ?></div>
    <div class="value"><?= count($open) ?></div><div class="label">Active campuses</div>
  </div>
  <div class="stat-card accent-top reveal reveal-delay-1" data-hoverable="true" style="--hover-color:#10b981;">
    <div class="icon"><?php dash_icon('check-circle'); ?></div>
    <div class="value"><?= $lessonsDone ?></div><div class="label">Lessons completed</div>
  </div>
  <div class="stat-card accent-top reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('calendar'); ?></div>
    <div class="value" style="font-size:20px;"><?= $nextLive ? e(date('D j M, g:i a', strtotime($nextLive['starts_at']))) : '—' ?></div><div class="label">Next live class</div>
  </div>
  <div class="stat-card accent-top reveal reveal-delay-3" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('award'); ?></div>
    <div class="value"><?= $certificates ?></div><div class="label">Certificates</div>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:36px;">My Campuses</h3>
<?php if (!$myCampuses): ?>
  <div class="card card-pad" style="margin-top:14px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
    <div><strong>Join a campus</strong><p class="muted small" style="margin-top:4px;">A year-long programme with a professor: lessons, PDF guides and a live class every week, ending with a certificate.</p></div>
    <a href="<?= e(base_url('index.php#campuses')) ?>" class="btn btn-primary">See the campuses</a>
  </div>
<?php else: ?>
  <div class="ld-panel" style="margin-top:14px; overflow:hidden;">
    <?php foreach ($myCampuses as $mc): $mcOpen = strtotime($mc['access_until']) > time(); ?>
      <div class="ca-list" style="padding:0 20px;"><div>
        <span class="ca-ico"><?= campus_icon($mc['icon']) ?></span>
        <div style="min-width:0;"><b class="t"><?= e($mc['name']) ?> Campus</b><small><?= $mcOpen ? 'Access until ' . e(date('j M Y', strtotime($mc['access_until']))) . ' · month ' . campus_current_month($mc) . ' of 12' . ($mc['live_schedule'] ? ' · live ' . e($mc['live_schedule']) : '') : 'Access ended ' . e(date('j M Y', strtotime($mc['access_until']))) ?></small></div>
        <?php if ($mcOpen): ?><a class="btn btn-primary btn-sm" href="<?= e(base_url('campus-learn.php?campus=' . $mc['slug'])) ?>">Open campus</a><?php else: ?><a class="btn btn-outline btn-sm" href="<?= e(base_url('campus.php?slug=' . $mc['slug'] . '#join')) ?>">Renew</a><?php endif; ?>
      </div></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:40px;">Quick Actions</h3>
<div class="quick-actions">
  <a href="<?= e(base_url('index.php#campuses')) ?>" class="quick-action">
    <span class="qa-icon" style="--tint:#0b00ff;"><?php dash_icon('book-open'); ?></span>
    <span class="qa-text">Explore Campuses</span>
    <?php dash_icon('arrow-right', 'qa-arrow'); ?>
  </a>
  <a href="<?= e(base_url('dashboard/settings.php')) ?>" class="quick-action">
    <span class="qa-icon" style="--tint:#8b5cf6;"><?php dash_icon('settings'); ?></span>
    <span class="qa-text">Account Settings</span>
    <?php dash_icon('arrow-right', 'qa-arrow'); ?>
  </a>
  <a href="<?= e(base_url('contact.php')) ?>" class="quick-action">
    <span class="qa-icon" style="--tint:#06b6d4;"><?php dash_icon('quote'); ?></span>
    <span class="qa-text">Support Center</span>
    <?php dash_icon('arrow-right', 'qa-arrow'); ?>
  </a>
</div>

<div class="dash-quote-block">
  <p>"Success is built one lesson at a time. Keep learning. Keep growing."</p>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
