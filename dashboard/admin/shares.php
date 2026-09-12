<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['ADMIN']);

$channelLabels = ['copy_link' => 'Copy Link', 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'twitter' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'tiktok' => 'TikTok'];

$filters = ['q' => query_param('q'), 'channel' => query_param('channel')];
$page = max(1, (int) (query_param('page') ?: 1));
$perPage = 30;
$result = get_course_shares($filters, $page, $perPage);
$shares = $result['rows'];
$totalShares = $result['total'];
$totalPages = max(1, (int) ceil($totalShares / $perPage));

$summary = get_share_summary(30);
$topCourses = get_top_shared_courses(30, 8);

$pageTitle = 'Course Shares — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Course Shares</h1>
<p class="muted" style="margin-top:6px;">How course links are being shared, and how far each one is actually reaching — last 30 days.</p>

<div class="grid md:grid-3" style="margin-top:20px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('share'); ?></div>
    <div class="value"><?= number_format($summary['shares']) ?></div><div class="label">Shares</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#10b981;">
    <div class="icon"><?php dash_icon('eye'); ?></div>
    <div class="value"><?= number_format($summary['visits']) ?></div><div class="label">Visits Via Shared Links</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#8b5cf6;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= number_format($summary['reach']) ?></div><div class="label">People Reached</div>
  </div>
</div>

<div class="growth-layout" style="margin-top:24px;">
  <div class="chart-card">
    <h2 class="h3">Most-Shared Courses</h2>
    <p class="muted small" style="margin-top:4px;">Ranked by visits their links generated, not just how many times they were shared — a course with more visits than shares is a sign its link is circulating beyond the person it was first sent to.</p>
    <?php if ($topCourses): ?>
      <div class="table-wrap" style="margin-top:16px; box-shadow:none;">
        <table>
          <thead><tr><th>Course</th><th>Shares</th><th>Visits</th><th>Reach</th></tr></thead>
          <tbody>
            <?php foreach ($topCourses as $c): ?>
              <tr>
                <td><a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" target="_blank" rel="noopener" style="font-weight:600;"><?= e($c['title']) ?></a> <?php if ($c['type'] === 'EVENT'): ?><span class="badge badge-new">🎟 Event</span><?php endif; ?></td>
                <td style="font-variant-numeric:tabular-nums;"><?= (int) $c['share_count'] ?></td>
                <td style="font-variant-numeric:tabular-nums;"><?= (int) $c['visit_count'] ?></td>
                <td style="font-variant-numeric:tabular-nums;"><?= (int) $c['reach'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="muted small" style="margin-top:16px;">No shares logged yet in this range.</p>
    <?php endif; ?>
  </div>

  <div class="growth-side">
    <div class="chart-card">
      <h2 class="h3">By Channel</h2>
      <?php render_bar_list($summary['by_channel'], $channelLabels); ?>
    </div>
  </div>
</div>

<h3 class="dash-section-label" style="margin-top:32px;">All Shares</h3>
<div class="chart-card" style="margin-top:14px; padding:18px 20px;">
  <form method="get" class="leads-filter-bar">
    <div class="field-icon" style="flex:1 1 220px; max-width:280px; margin:0;">
      <?php dash_icon('search'); ?>
      <input type="text" name="q" placeholder="Search by course title" value="<?= e($filters['q']) ?>">
    </div>
    <select name="channel" style="flex:0 1 170px;">
      <option value="">All Channels</option>
      <?php foreach ($channelLabels as $val => $label): ?>
        <option value="<?= e($val) ?>" <?= $filters['channel'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if (array_filter($filters)): ?><a href="<?= e(base_url('dashboard/admin/shares.php')) ?>" class="small muted">Clear</a><?php endif; ?>
    <span class="muted small" style="margin-left:auto; white-space:nowrap;"><?= number_format($totalShares) ?> share<?= $totalShares === 1 ? '' : 's' ?></span>
  </form>
</div>

<div class="table-wrap" style="margin-top:14px;">
  <?php if ($shares): ?>
    <table>
      <thead><tr><th>Course</th><th>Channel</th><th>Shared By</th><th>When</th><th>Visits</th><th>Reach</th></tr></thead>
      <tbody>
        <?php foreach ($shares as $s): ?>
          <tr>
            <td><a href="<?= e(base_url('courses/view.php?slug=' . $s['course_slug'])) ?>" target="_blank" rel="noopener" style="font-weight:600;"><?= e($s['course_title']) ?></a> <?php if ($s['course_type'] === 'EVENT'): ?><span class="badge badge-new">🎟 Event</span><?php endif; ?></td>
            <td><span class="badge badge-draft"><?= e($channelLabels[$s['channel']] ?? $s['channel']) ?></span></td>
            <td class="small"><?= $s['sharer_name'] ? e($s['sharer_name']) : '<span class="muted">Guest</span>' ?></td>
            <td class="small muted"><?= e(format_date($s['created_at'])) ?></td>
            <td style="font-variant-numeric:tabular-nums;"><?= (int) $s['visit_count'] ?></td>
            <td style="font-variant-numeric:tabular-nums;">
              <?= (int) $s['reach'] ?>
              <?php if ((int) $s['reach'] > 1): ?><span class="small" style="color:var(--dash-good); font-weight:700;" title="Multiple distinct people visited via this one link — it's been passed around further than just the first recipient.">↑ passed on</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="card" style="padding:36px; text-align:center; border-style:dashed; color:var(--muted);">No shares match these filters yet.</div>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
  <div class="row gap-2" style="margin-top:16px; justify-content:center;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="<?= e(base_url('dashboard/admin/shares.php?' . http_build_query(array_filter($filters) + ['page' => $p]))) ?>"
         class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
