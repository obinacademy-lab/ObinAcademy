<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/storage.php';
$user = require_role(['ADMIN']);

/**
 * Checks every lesson's underlying file is actually readable — the same
 * failure mode stream.php now catches per-request (see its is_readable()
 * check) surfaced here as a one-page sweep across the whole platform,
 * instead of waiting for a learner to hit a broken one and report it.
 * A locally-stored file is checked directly on disk; an external URL (rare
 * — seed/demo content) gets a short HEAD request instead, since there's no
 * local file to stat.
 */
$lessons = db_all(
    "SELECT l.id, l.title, l.type, l.file_url, c.id AS course_id, c.title AS course_title, c.status AS course_status
     FROM lessons l
     JOIN modules m ON m.id = l.module_id
     JOIN courses c ON c.id = m.course_id
     ORDER BY c.title, m.sort_order, l.sort_order"
);

$results = [];
foreach ($lessons as $lesson) {
    $isExternal = preg_match('#^https?://#', $lesson['file_url']);
    if ($isExternal) {
        $ch = curl_init($lesson['file_url']);
        curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $ok = $httpCode >= 200 && $httpCode < 400;
        $detail = $curlError ?: "HTTP $httpCode";
    } else {
        $filePath = resolve_private_path($lesson['file_url']);
        clearstatcache(true, $filePath);
        $exists = is_file($filePath);
        $readable = $exists && is_readable($filePath);
        $ok = $readable;
        $detail = !$exists ? 'File missing on disk' : (!$readable ? 'File exists but is not readable (permissions?)' : format_max_size(filesize($filePath)));
    }
    $results[] = ['lesson' => $lesson, 'ok' => $ok, 'detail' => $detail, 'external' => $isExternal];
}

$brokenCount = count(array_filter($results, fn($r) => !$r['ok']));

$pageTitle = 'Content Health — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Content Health</h1>
<p class="muted" style="margin-top:6px;">Checks every lesson's video/PDF file is actually readable — catches a broken upload before a learner does.</p>

<div class="grid md:grid-3" style="margin-top:20px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#2563eb;">
    <div class="icon"><?php dash_icon('book-open'); ?></div>
    <div class="value"><?= count($results) ?></div><div class="label">Lessons Checked</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#34d399;">
    <div class="icon"><?php dash_icon('check-circle'); ?></div>
    <div class="value"><?= count($results) - $brokenCount ?></div><div class="label">Readable</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f87171;">
    <div class="icon"><?php dash_icon('x-circle'); ?></div>
    <div class="value"><?= $brokenCount ?></div><div class="label">Broken</div>
  </div>
</div>

<?php if ($brokenCount > 0): ?>
  <h3 class="dash-section-label" style="margin-top:32px;">Broken (<?= $brokenCount ?>)</h3>
  <div class="table-wrap" style="margin-top:14px;">
    <table>
      <thead><tr><th>Course</th><th>Lesson</th><th>Type</th><th>Problem</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($results as $r): if ($r['ok']) continue; $l = $r['lesson']; ?>
          <tr>
            <td><?= e($l['course_title']) ?></td>
            <td><?= e($l['title']) ?></td>
            <td><span class="badge badge-draft"><?= e($l['type']) ?></span></td>
            <td class="small" style="color:var(--danger);"><?= e($r['detail']) ?></td>
            <td><a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $l['course_id'])) ?>" class="btn btn-outline btn-sm">Manage Course</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="all-caught-up" style="margin-top:24px;">
    <?php dash_icon('check-circle'); ?>
    <div><strong>Everything checks out.</strong> Every lesson's file is readable.</div>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:36px;">All Lessons</h3>
<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>Course</th><th>Lesson</th><th>Type</th><th>Status</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($results as $r): $l = $r['lesson']; ?>
        <tr>
          <td><?= e($l['course_title']) ?> <?php if ($l['course_status'] !== 'PUBLISHED'): ?><span class="small muted">(<?= e($l['course_status']) ?>)</span><?php endif; ?></td>
          <td><?= e($l['title']) ?></td>
          <td><span class="badge badge-draft"><?= e($l['type']) ?><?= $r['external'] ? ' · external' : '' ?></span></td>
          <td><?php if ($r['ok']): ?><span class="badge badge-published">OK</span><?php else: ?><span class="badge badge-rejected">Broken</span><?php endif; ?></td>
          <td class="small muted"><?= e($r['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$results): ?><tr><td colspan="5" class="muted">No lessons found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
