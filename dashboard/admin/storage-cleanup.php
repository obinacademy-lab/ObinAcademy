<?php
/**
 * Admin: find and delete uploaded files nothing uses any more — left behind by the old per-course
 * platform (course videos, PDFs, thumbnails). A file is kept if any campus lesson, professor photo or
 * user photo still points at it; the list is worked out again at the moment of deleting, so a file
 * uploaded in the meantime is never removed. Every clean-up is written to the audit log.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

/** Every stored file path still in use, as absolute paths. */
function storage_files_in_use(): array {
    $used = [];
    foreach (db_all('SELECT file_url FROM campus_lessons WHERE file_url IS NOT NULL') as $r) {
        $used[realpath(resolve_private_path($r['file_url'])) ?: resolve_private_path($r['file_url'])] = true;
    }
    $public = array_merge(
        array_column(db_all("SELECT avatar_url AS u FROM users WHERE avatar_url LIKE '/uploads/%'"), 'u'),
        array_column(db_all("SELECT professor_photo_url AS u FROM campuses WHERE professor_photo_url LIKE '/uploads/%'"), 'u')
    );
    try {
        $public = array_merge($public, array_column(db_all("SELECT video_url AS u FROM video_stories WHERE video_url LIKE '/uploads/%'"), 'u'));
    } catch (Throwable $e) {
        // Video stories not set up yet.
    }
    foreach ($public as $u) {
        $path = public_uploads_root() . '/' . substr($u, strlen('/uploads/'));
        $used[realpath($path) ?: $path] = true;
    }
    return $used;
}

/** Unused files grouped by folder: [label => [[path, bytes], ...]]. */
function storage_unused_files(): array {
    $used = storage_files_in_use();
    $out = [];
    foreach (['Lesson videos and PDFs' => private_uploads_root(), 'Pictures and story videos' => public_uploads_root()] as $label => $root) {
        if (!is_dir($root)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) continue;
            $path = $file->getRealPath();
            if (isset($used[$path])) continue;
            $out[$label][] = [$path, $file->getSize()];
        }
    }
    return $out;
}

if (!campuses_ready()) { flash_set('error', 'Campuses are not set up, so nothing can be checked safely.'); redirect('/dashboard/admin/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('_action') === 'delete') {
    csrf_verify();
    $deleted = 0; $bytes = 0; $failed = 0;
    foreach (storage_unused_files() as $files) {
        foreach ($files as [$path, $size]) {
            if (@unlink($path)) { $deleted++; $bytes += $size; } else { $failed++; }
        }
    }
    log_admin_action((int) $user['id'], $user['name'], 'storage.cleaned', 'Storage', $deleted . ' files', round($bytes / 1048576, 1) . ' MB freed' . ($failed ? ", $failed could not be deleted" : ''));
    flash_set($failed ? 'error' : 'success', 'Deleted ' . number_format($deleted) . ' unused file' . ($deleted === 1 ? '' : 's') . ' (' . round($bytes / 1048576, 1) . ' MB).' . ($failed ? ' ' . $failed . ' could not be deleted.' : ''));
    redirect('/dashboard/admin/storage-cleanup.php');
}

$groups = storage_unused_files();
$total = 0; $totalBytes = 0;
foreach ($groups as $files) { $total += count($files); $totalBytes += array_sum(array_column($files, 1)); }
$mb = fn(int $b) => $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : round($b / 1048576, 1) . ' MB';

$pageTitle = 'Storage — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Storage</h1>
<p class="muted" style="margin-top:6px;">Uploaded files nothing on the platform uses any more, such as videos, PDFs and pictures from the old courses. Campus lessons, professor photos and profile photos are always kept.</p>

<?php if (!$total): ?>
  <div class="card card-pad" style="margin-top:20px; border-style:dashed; text-align:center;"><p class="muted">Nothing to clean up. Every stored file is in use.</p></div>
<?php else: ?>
  <div class="grid sm:grid-2 lg:grid-3" style="margin-top:20px; gap:14px;">
    <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= number_format($total) ?></span><span class="mini-stat-label">Unused files</span></div>
    <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= e($mb($totalBytes)) ?></span><span class="mini-stat-label">Space they take</span></div>
  </div>
  <div class="card cm-wrap" style="margin-top:16px;">
    <table class="cm-table">
      <thead><tr><th>Where</th><th class="num">Files</th><th class="num">Size</th></tr></thead>
      <tbody>
        <?php foreach ($groups as $label => $files): ?>
          <tr><td data-label="Where"><?= e($label) ?></td><td class="num" data-label="Files"><?= number_format(count($files)) ?></td><td class="num" data-label="Size"><?= e($mb(array_sum(array_column($files, 1)))) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form method="post" style="margin-top:18px;" data-confirm="Permanently delete <?= number_format($total) ?> unused files (<?= e($mb($totalBytes)) ?>)? This cannot be undone.">
    <?= csrf_field() ?><input type="hidden" name="_action" value="delete">
    <button class="btn btn-primary" type="submit">Delete <?= number_format($total) ?> unused files</button>
    <p class="small muted" style="margin-top:8px;">Deleting is permanent. The list is checked again at the moment you press the button.</p>
  </form>
<?php endif; ?>
<script>document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
