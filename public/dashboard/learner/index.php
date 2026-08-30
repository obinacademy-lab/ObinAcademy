<?php
require __DIR__ . '/../../../includes/bootstrap.php';
$user = require_login();

$enrollments = db_all('
    SELECT e.*, c.title, c.slug, c.thumbnail_url,
      (SELECT COUNT(*) FROM lessons l JOIN modules m ON m.id = l.module_id WHERE m.course_id = c.id) AS lesson_count
    FROM enrollments e JOIN courses c ON c.id = e.course_id
    WHERE e.user_id = ? ORDER BY e.enrolled_at DESC
', [$user['id']]);

$inProgress = 0;
foreach ($enrollments as $en) if ((float) $en['progress'] < 100) $inProgress++;

$pageTitle = 'My Learning — Obin Academy';
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<h1 class="h2">My Learning</h1>
<p class="muted" style="margin-top:6px;"><?= $inProgress ?> course<?= $inProgress === 1 ? '' : 's' ?> in progress</p>

<?php if (!$enrollments): ?>
  <div class="card card-pad" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">You haven't enrolled in any courses yet.</p>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Browse Courses</a>
  </div>
<?php else: ?>
  <div class="grid sm:grid-2 lg:grid-3" style="margin-top:24px;">
    <?php foreach ($enrollments as $en): ?>
      <div class="card">
        <div style="aspect-ratio:16/9; background:var(--brand-900); border-radius: var(--radius) var(--radius) 0 0; overflow:hidden;">
          <?php if ($en['thumbnail_url']): ?>
            <img src="<?= e(asset_src($en['thumbnail_url'])) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
          <?php endif; ?>
        </div>
        <div class="card-pad">
          <div class="cat" style="font-size:11px; font-weight:700; color:var(--accent); text-transform:uppercase;">Obin Academy</div>
          <h3 style="margin-top:6px; font-size:15px; font-weight:700;"><?= e($en['title']) ?></h3>
          <p class="small muted" style="margin-top:6px;"><?= (int) $en['lesson_count'] ?> lessons</p>
          <div class="progress-track" style="margin-top:12px;"><div class="progress-fill" style="width:<?= round((float) $en['progress']) ?>%;"></div></div>
          <p class="small muted" style="margin-top:6px;"><?= round((float) $en['progress']) ?>% complete</p>
          <a href="<?= e(base_url('learn.php?slug=' . $en['slug'])) ?>" class="btn btn-primary btn-block" style="margin-top:14px;">
            <?= (float) $en['progress'] > 0 ? '▶ Continue Learning' : '▶ Start Learning' ?>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
