<?php
/**
 * Admin: storage. A dark console with how much space uploads take, a disk map split into what is in use
 * (lesson videos and PDFs, story videos, month pictures, professor and profile photos) and what nothing uses any more;
 * below, the kept files by kind, and the unused ones (biggest first) with one button that deletes them
 * all. Unused files are mostly left behind by the old per-course platform. A file is kept if any campus
 * lesson, month picture, professor photo, profile photo or video story still points at it; the list is worked out again
 * at the moment of deleting, so a file uploaded in the meantime is never removed. Every clean-up is
 * written to the audit log.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-storage.css. Motion: assets/js/admin-pay.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/storage.php';
require_once __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

/** Every stored file still in use, as [absolute path => what uses it]. */
function storage_files_in_use(): array {
    $used = [];
    $add = function (string $path, string $kind) use (&$used) { $used[realpath($path) ?: $path] = $kind; };
    foreach (db_all('SELECT kind, file_url FROM campus_lessons WHERE file_url IS NOT NULL') as $r) {
        $add(resolve_private_path($r['file_url']), $r['kind'] === 'PDF' ? 'pdf' : 'video');
    }
    $public = [];
    foreach (db_all("SELECT avatar_url AS u FROM users WHERE avatar_url LIKE '/uploads/%'") as $r) $public[] = [$r['u'], 'avatar'];
    foreach (db_all("SELECT professor_photo_url AS u FROM campuses WHERE professor_photo_url LIKE '/uploads/%'") as $r) $public[] = [$r['u'], 'professor'];
    try {
        foreach (db_all("SELECT video_url AS u FROM video_stories WHERE video_url LIKE '/uploads/%'") as $r) $public[] = [$r['u'], 'story'];
    } catch (Throwable $e) {
        // Video stories not set up yet.
    }
    try {
        foreach (db_all("SELECT thumbnail_url AS u FROM campus_stages WHERE thumbnail_url LIKE '/uploads/%'") as $r) $public[] = [$r['u'], 'stage'];
    } catch (Throwable $e) {
        // Month pictures not set up yet.
    }
    foreach ($public as [$u, $kind]) $add(public_uploads_root() . '/' . substr($u, strlen('/uploads/')), $kind);
    return $used;
}

/** Every stored file: [path, bytes, modified, folder ("videos", "thumbnails"...), in-use kind or null]. */
function storage_scan(): array {
    $used = storage_files_in_use();
    $out = [];
    foreach ([private_uploads_root(), public_uploads_root()] as $root) {
        if (!is_dir($root)) continue;
        $base = realpath($root);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) continue;
            $path = $file->getRealPath();
            $rel = ltrim(str_replace('\\', '/', substr($path, strlen($base))), '/');
            $folder = str_contains($rel, '/') ? strstr($rel, '/', true) : '';
            $out[] = [$path, $file->getSize(), $file->getMTime(), $folder, $used[$path] ?? null];
        }
    }
    return $out;
}

if (!campuses_ready()) { flash_set('error', 'Campuses are not set up, so nothing can be checked safely.'); redirect('/dashboard/admin/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('_action') === 'delete') {
    csrf_verify();
    $deleted = 0; $bytes = 0; $failed = 0;
    foreach (storage_scan() as [$path, $size, , , $kind]) {
        if ($kind !== null) continue;
        if (@unlink($path)) { $deleted++; $bytes += $size; } else { $failed++; }
    }
    log_admin_action((int) $user['id'], $user['name'], 'storage.cleaned', 'Storage', $deleted . ' file' . ($deleted === 1 ? '' : 's'), round($bytes / 1048576, 1) . ' MB freed' . ($failed ? ", $failed could not be deleted" : ''));
    flash_set($failed ? 'error' : 'success', 'Deleted ' . number_format($deleted) . ' unused file' . ($deleted === 1 ? '' : 's') . ' (' . round($bytes / 1048576, 1) . ' MB).' . ($failed ? ' ' . $failed . ' could not be deleted.' : ''));
    redirect('/dashboard/admin/storage-cleanup.php');
}

$files = storage_scan();
$mb = fn(int $b) => $b >= 1073741824 ? round($b / 1073741824, 2) . ' GB' : ($b >= 1048576 || $b === 0 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB');
$numUnit = function (int $b): array { // the big figure, split into number and unit
    if ($b >= 1073741824) return [number_format($b / 1073741824, 2), 'GB'];
    return [number_format($b / 1048576, $b >= 104857600 ? 0 : 1), 'MB'];
};

// What is kept, by kind; what is unused, by folder.
$keptKinds = [
    'video' => ['Lesson videos', 'Campus lessons members watch', 'play'],
    'pdf' => ['Lesson PDFs', 'Guides attached to campus lessons', 'file-text'],
    'story' => ['Story videos', 'Student video stories', 'quote'],
    'stage' => ['Month pictures', 'One for each of the twelve stages', 'calendar'],
    'professor' => ['Professor photos', 'Shown on campus pages', 'graduation-cap'],
    'avatar' => ['Profile photos', 'Members\' own pictures', 'users'],
];
$unusedFolders = ['videos' => 'Old videos', 'pdfs' => 'Old PDFs', 'thumbnails' => 'Old pictures', 'stories' => 'Removed story videos'];
$kept = array_fill_keys(array_keys($keptKinds), ['n' => 0, 'b' => 0]);
$unused = [];
$unusedList = [];
$totalBytes = 0;
foreach ($files as [$path, $size, $mtime, $folder, $kind]) {
    $totalBytes += $size;
    if ($kind !== null) { $kept[$kind]['n']++; $kept[$kind]['b'] += $size; continue; }
    $label = $unusedFolders[$folder] ?? 'Other files';
    $unused[$label] = ['n' => ($unused[$label]['n'] ?? 0) + 1, 'b' => ($unused[$label]['b'] ?? 0) + $size];
    $unusedList[] = ['name' => basename($path), 'label' => $label, 'b' => $size, 't' => $mtime];
}
uasort($unused, fn($x, $y) => $y['b'] <=> $x['b']);
usort($unusedList, fn($x, $y) => $y['b'] <=> $x['b']);
$unusedCount = count($unusedList);
$unusedBytes = array_sum(array_column($unusedList, 'b'));
$keptBytes = $totalBytes - $unusedBytes;
$keptCount = count($files) - $unusedCount;
$lastClean = db_one("SELECT actor_name, target_label, detail, created_at FROM audit_log WHERE action = 'storage.cleaned' ORDER BY created_at DESC, id DESC LIMIT 1");
[$bigNum, $bigUnit] = $numUnit($totalBytes);
$pct = fn(int $b) => $totalBytes ? round($b / $totalBytes * 100, 2) : 0;
$showList = 12;

$pageTitle = 'Storage — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-storage.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx st" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Storage</h1><p>Every uploaded file, what still uses it, and what can go. Campus lessons, story videos and photos in use are always kept.</p></div>
  </div>

  <section class="lc-console st-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $unusedCount ? 'warn' : '' ?>"><i></i><?= $unusedCount ? e($mb($unusedBytes)) . ' can be freed' : 'Every file is in use' ?></span>
      <div class="lc-big"><b><?= e($bigNum) ?><em><?= e($bigUnit) ?></em></b><span>stored in<br>uploads</span></div>
      <p class="lc-copy"><?= $files ? number_format(count($files)) . ' file' . (count($files) === 1 ? '' : 's') . ' across lesson media, story videos and pictures.' : 'Nothing has been uploaded yet.' ?></p>
      <div class="lc-stats">
        <div><span>Files in use</span><b class="lime" data-scramble="<?= $keptCount ?>"><?= $keptCount ?></b></div>
        <div><span>Unused files</span><b class="<?= $unusedCount ? 'red' : '' ?>" data-scramble="<?= $unusedCount ?>"><?= $unusedCount ?></b></div>
        <div><span>Space in use</span><b><?= e($mb($keptBytes)) ?></b></div>
        <div><span>Last clean-up</span><b class="st-small" title="<?= $lastClean ? e($lastClean['target_label'] . ', ' . $lastClean['detail'] . ' by ' . $lastClean['actor_name']) : '' ?>"><?= $lastClean ? e(time_ago($lastClean['created_at'])) : 'Never' ?></b></div>
      </div>
    </div>
    <div>
      <div class="lc-rx-h"><b>Disk map</b><span><?= $totalBytes ? 'share of ' . e($mb($totalBytes)) : 'empty' ?></span></div>
      <div class="st-map" role="img" aria-label="<?= e($mb($keptBytes)) ?> in use, <?= e($mb($unusedBytes)) ?> unused">
        <?php $k = 0; foreach ($keptKinds as $key => [$label]): if (!$kept[$key]['b']) continue; ?>
          <i class="k-<?= $key ?>" style="--w:<?= $pct($kept[$key]['b']) ?>; --k:<?= $k++ ?>" title="<?= e($label) ?> · <?= e($mb($kept[$key]['b'])) ?>"></i>
        <?php endforeach; ?>
        <?php if ($unusedBytes): ?><i class="k-unused" style="--w:<?= $pct($unusedBytes) ?>; --k:<?= $k ?>" title="Unused · <?= e($mb($unusedBytes)) ?>"></i><?php endif; ?>
        <?php if (!$totalBytes): ?><i class="k-empty" style="--w:100"></i><?php endif; ?>
      </div>
      <div class="st-legend">
        <?php foreach ($keptKinds as $key => [$label]): ?>
          <div class="<?= $kept[$key]['n'] ? '' : 'none' ?>"><span><i class="k-<?= $key ?>"></i><?= e($label) ?></span><b><?= e($mb($kept[$key]['b'])) ?></b><small><?= $pct($kept[$key]['b']) ? round($pct($kept[$key]['b'])) . '%' : '—' ?></small></div>
        <?php endforeach; ?>
        <div class="<?= $unusedCount ? 'bad' : 'none' ?>"><span><i class="k-unused"></i>Unused</span><b><?= e($mb($unusedBytes)) ?></b><small><?= $unusedBytes ? round($pct($unusedBytes)) . '%' : '—' ?></small></div>
      </div>
    </div>
  </section>

  <div class="st-cols">
    <section class="acx-sec acx-slam" style="--i:2">
      <div class="st-h"><div><h2>Kept</h2><p>Files something on the platform still uses. These are never deleted.</p></div></div>
      <div class="st-kinds">
        <?php $j = 0; foreach ($keptKinds as $key => [$label, $what, $icon]): ?>
          <div class="st-kind acx-whip <?= $kept[$key]['n'] ? '' : 'none' ?>" style="--j:<?= $j++ ?>">
            <span class="st-ic k-<?= $key ?>"><?php dash_icon($icon); ?></span>
            <div><b><?= e($label) ?></b><small><?= e($what) ?></small></div>
            <span class="st-n"><?= number_format($kept[$key]['n']) ?> <small>file<?= $kept[$key]['n'] === 1 ? '' : 's' ?></small></span>
            <span class="st-b"><?= e($mb($kept[$key]['b'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="acx-sec acx-slam st-clean <?= $unusedCount ? 'has' : 'clear' ?>" style="--i:3" id="unused">
      <div class="st-h"><div><h2>Unused</h2><p><?= $unusedCount ? 'Nothing points at these files any more, mostly leftovers from the old courses.' : 'Nothing to clean up.' ?></p></div></div>
      <?php if (!$unusedCount): ?>
        <div class="st-ok">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          <b>Every stored file is in use</b>
          <p><?= $lastClean ? 'Last clean-up ' . e(time_ago($lastClean['created_at'])) . ': ' . e($lastClean['target_label']) . ', ' . e($lastClean['detail']) . '.' : 'Unused files will show up here when lessons, photos or stories are replaced or removed.' ?></p>
        </div>
      <?php else: ?>
        <div class="st-groups">
          <?php foreach ($unused as $label => $g): ?>
            <div><span><?= e($label) ?></span><b><?= number_format($g['n']) ?> <small>file<?= $g['n'] === 1 ? '' : 's' ?></small></b><em><?= e($mb($g['b'])) ?></em></div>
          <?php endforeach; ?>
        </div>
        <div class="st-files">
          <div class="st-files-h"><span>Biggest first</span><span><?= $unusedCount > $showList ? 'showing ' . $showList . ' of ' . number_format($unusedCount) : number_format($unusedCount) . ' file' . ($unusedCount === 1 ? '' : 's') ?></span></div>
          <?php foreach (array_slice($unusedList, 0, $showList) as $j => $f): ?>
            <div class="st-file acx-whip" style="--j:<?= $j ?>">
              <span class="st-ext"><?= e(strtoupper(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></span>
              <div><b title="<?= e($f['name']) ?>"><?= e($f['name']) ?></b><small><?= e($f['label']) ?> · uploaded <?= e(date('j M Y', $f['t'])) ?></small></div>
              <span class="st-size"><?= e($mb($f['b'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="st-go" data-confirm="Permanently delete <?= number_format($unusedCount) ?> unused file<?= $unusedCount === 1 ? '' : 's' ?> (<?= e($mb($unusedBytes)) ?>)? This cannot be undone.">
          <?= csrf_field() ?><input type="hidden" name="_action" value="delete">
          <button type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>Delete <?= number_format($unusedCount) ?> unused file<?= $unusedCount === 1 ? '' : 's' ?> · free <?= e($mb($unusedBytes)) ?></button>
          <p>Deleting is permanent. The list is checked again at the moment you press the button, so anything uploaded since is kept.</p>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
