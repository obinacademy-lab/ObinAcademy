<?php
/**
 * Admin: stories. Real students on video, no profile pictures. Upload a recorded story (with the
 * student's permission), give it a short pull quote and a campus, then publish it: published stories
 * show on the home page, the About page and stories.php, in the order set here.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-stories.css. Upload preview,
 * counters and motion: assets/js/admin-stories.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/stories.php';
require_once __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

if (!video_stories_ready()) {
    $pageTitle = 'Stories — Admin — Obin Academy';
    require __DIR__ . '/../../includes/dashboard_header.php';
    echo '<div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;"><p class="muted">Video stories are being set up. Run migration/add-video-stories.sql, then come back.</p></div>';
    require __DIR__ . '/../../includes/dashboard_footer.php';
    exit;
}

// The biggest video this server accepts: the smallest of our own cap and PHP's upload and post limits.
$iniBytes = function (string $v): int {
    $v = trim($v); $n = (int) $v; $u = strtolower(substr($v, -1));
    return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
};
$maxUpload = min(UPLOAD_MAX_BYTES['stories'], $iniBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, $iniBytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
$filter = in_array(query_param('show'), ['PUBLISHED', 'DRAFT'], true) ? query_param('show') : '';
$back = '/dashboard/admin/testimonials.php' . ($filter ? '?show=' . $filter : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A video bigger than post_max_size arrives with an empty body, so explain that instead of failing the form check.
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash_set('error', 'That video is bigger than this server accepts (' . format_max_size($maxUpload) . '). Export a shorter or smaller copy (720p MP4 works well) and try again.');
        redirect($back);
    }
    csrf_verify();
    $a = post('_action');
    $story = (int) post('story') ? db_one('SELECT * FROM video_stories WHERE id = ?', [(int) post('story')]) : null;
    $name = trim(mb_substr((string) post('student_name'), 0, 120));
    $quote = trim(mb_substr((string) post('quote'), 0, VIDEO_STORY_QUOTE_MAX));
    $campusId = (int) post('campus') ?: null;
    if ($campusId && !get_campus($campusId)) $campusId = null;
    try {
        if ($a === 'add') {
            if ($name === '' || $quote === '') throw new RuntimeException('Add the student\'s name and a short quote from the video.');
            if (post('consent') !== '1') throw new RuntimeException('Tick the box to confirm the student agreed to their video being shown.');
            if (empty($_FILES['video']['name'])) throw new RuntimeException('Choose the video file.');
            $url = save_upload($_FILES['video'], 'stories');
            $publish = post('publish') === '1';
            $sort = (int) (db_one('SELECT COALESCE(MIN(sort_order), 1) - 1 AS n FROM video_stories')['n'] ?? 0);
            db_insert(
                'INSERT INTO video_stories (student_name, campus_id, quote, video_url, duration_seconds, status, sort_order, consent_confirmed, created_by, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [$name, $campusId, $quote, $url, (int) post('duration') ?: null, $publish ? 'PUBLISHED' : 'DRAFT', $sort, $user['id'], $publish ? date('Y-m-d H:i:s') : null]
            );
            log_admin_action((int) $user['id'], $user['name'], 'story.added', 'Video story', $name, $publish ? 'published' : 'draft');
            flash_set('success', $name . '\'s story is ' . ($publish ? 'live on the site.' : 'saved as a draft. Publish it when you are ready.'));
        } elseif ($story && $a === 'update') {
            if ($name === '' || $quote === '') throw new RuntimeException('A story needs the student\'s name and a quote.');
            db_run('UPDATE video_stories SET student_name = ?, campus_id = ?, quote = ? WHERE id = ?', [$name, $campusId, $quote, $story['id']]);
            log_admin_action((int) $user['id'], $user['name'], 'story.updated', 'Video story', $name);
            flash_set('success', 'Saved.');
        } elseif ($story && ($a === 'publish' || $a === 'unpublish')) {
            $on = $a === 'publish';
            db_run('UPDATE video_stories SET status = ?, published_at = ' . ($on ? 'COALESCE(published_at, NOW())' : 'published_at') . ' WHERE id = ?', [$on ? 'PUBLISHED' : 'DRAFT', $story['id']]);
            log_admin_action((int) $user['id'], $user['name'], 'story.' . ($on ? 'published' : 'unpublished'), 'Video story', $story['student_name']);
            flash_set('success', $story['student_name'] . '\'s story is ' . ($on ? 'now on the site.' : 'hidden from the site.'));
        } elseif ($story && ($a === 'up' || $a === 'down')) {
            // Swap places with the neighbour above or below, renumbering first so the order is always clean.
            $ids = array_map('intval', array_column(db_all('SELECT id FROM video_stories ORDER BY sort_order ASC, id DESC'), 'id'));
            $i = array_search((int) $story['id'], $ids, true);
            $j = $a === 'up' ? $i - 1 : $i + 1;
            if ($i !== false && isset($ids[$j])) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; }
            foreach ($ids as $pos => $id) db_run('UPDATE video_stories SET sort_order = ? WHERE id = ?', [$pos, $id]);
        } elseif ($story && $a === 'delete') {
            db_run('DELETE FROM video_stories WHERE id = ?', [$story['id']]);
            if (str_starts_with($story['video_url'], '/uploads/stories/')) {
                $path = public_uploads_root() . '/' . substr($story['video_url'], strlen('/uploads/'));
                if (is_file($path)) @unlink($path);
            }
            log_admin_action((int) $user['id'], $user['name'], 'story.deleted', 'Video story', $story['student_name']);
            flash_set('success', 'Deleted ' . $story['student_name'] . '\'s story and its video.');
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
    }
    redirect($back . ($a === 'add' ? '' : '#story-' . (int) post('story')));
}

$all = get_video_stories();
$stories = $filter ? array_values(array_filter($all, fn($s) => $s['status'] === $filter)) : $all;
$published = array_values(array_filter($all, fn($s) => $s['status'] === 'PUBLISHED'));
$drafts = count($all) - count($published);
$watchSeconds = array_sum(array_map(fn($s) => (int) $s['duration_seconds'], $published));
$campuses = get_all_campuses();
$covered = count(array_unique(array_filter(array_column($published, 'campus_id'))));
$orderIds = array_map('intval', array_column($all, 'id'));

$pageTitle = 'Stories — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-stories.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx sx" data-sx>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Stories</h1><p>Real students on video, talking about their campus. No profile pictures: the video is the proof.</p></div>
    <div class="acx-acts"><a class="acx-btn" href="<?= e(base_url('stories.php')) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View stories page</a></div>
  </div>

  <section class="lc-console sx-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $published ? '' : 'calm' ?>"><i></i><?= $published ? 'On the site now' : 'No stories yet' ?></span>
      <div class="lc-big"><b><span data-scramble="<?= count($published) ?>"><?= count($published) ?></span></b><span>video stor<?= count($published) === 1 ? 'y' : 'ies' ?><br>published</span></div>
      <p class="lc-copy"><?= $published ? 'They play on the home page, the About page and the Stories page, in the order below.' : 'Record a student talking about their campus, upload it below and publish it. It goes straight onto the site.' ?></p>
      <div class="lc-stats">
        <div><span>Drafts</span><b data-scramble="<?= $drafts ?>"><?= $drafts ?></b></div>
        <div><span>Total watch time</span><b><?= $watchSeconds ? e(video_story_length($watchSeconds)) : '0:00' ?></b></div>
        <div><span>Campuses with a story</span><b class="lime"><?= $covered ?><small style="margin-left:4px">/ <?= count($campuses) ?></small></b></div>
        <div><span>Biggest video accepted</span><b><?= e(format_max_size($maxUpload)) ?></b></div>
      </div>
    </div>
    <div>
      <div class="lc-rx-h"><b>Story reel</b><span><?= $published ? 'hover to preview' : 'your first stories appear here' ?></span></div>
      <div class="sx-reel">
        <?php for ($k = 0; $k < 5; $k++): $s = $published[$k] ?? null; ?>
          <?php if ($s): ?>
            <div class="sx-tile" style="--k:<?= $k ?>"><video src="<?= e(base_url($s['video_url'])) ?>#t=0.5" muted playsinline preload="metadata" loop></video><span><?= e($s['student_name']) ?></span></div>
          <?php else: ?>
            <div class="sx-tile empty" style="--k:<?= $k ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/></svg></div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    </div>
  </section>

  <section class="acx-sec acx-slam" style="--i:2" id="add">
    <div class="sx-h"><div><h2>Add a video story</h2><p>A phone video is perfect: 30 to 90 seconds, filmed upright, the student saying what changed for them.</p></div></div>
    <form method="post" enctype="multipart/form-data" class="sx-add" data-add>
      <?= csrf_field() ?><input type="hidden" name="_action" value="add"><input type="hidden" name="duration" value="" data-duration>
      <label class="sx-drop" data-drop>
        <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime" required data-file>
        <span class="sx-drop-empty">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
          <b>Choose or drop the video</b>
          <small>MP4, WebM or MOV · up to <?= e(format_max_size($maxUpload)) ?></small>
        </span>
        <video class="sx-preview" muted playsinline controls hidden data-preview></video>
        <span class="sx-file" hidden data-filename></span>
      </label>
      <div class="sx-fields">
        <label>Student's name<input name="student_name" maxlength="120" required placeholder="e.g. Grace Nakato"></label>
        <label>Campus<select name="campus"><option value="">Obin Academy in general</option><?php foreach ($campuses as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label>Pull quote <small><span data-count>0</span> / <?= VIDEO_STORY_QUOTE_MAX ?></small><textarea name="quote" rows="3" maxlength="<?= VIDEO_STORY_QUOTE_MAX ?>" required placeholder="The one line from the video that would make someone join" data-quote></textarea></label>
        <label class="sx-consent"><input type="checkbox" name="consent" value="1" required><span>The student agreed to this video being shown on Obin Academy and in our adverts.</span></label>
        <div class="sx-submit">
          <button type="submit" name="publish" value="1" class="sx-btn lime">Upload and publish <span>→</span></button>
          <button type="submit" name="publish" value="0" class="sx-btn ghost">Save as draft</button>
          <span class="sx-uploading" hidden data-uploading><i></i>Uploading the video. Keep this page open…</span>
        </div>
      </div>
    </form>
  </section>

  <section class="acx-sec acx-slam" style="--i:3" id="library">
    <div class="sx-h">
      <div><h2>Library</h2><p>The order here is the order on the site. Move the strongest stories to the top.</p></div>
      <nav class="sx-chips" aria-label="Show stories">
        <?php foreach (['' => 'All', 'PUBLISHED' => 'Published', 'DRAFT' => 'Drafts'] as $val => $label): $n = $val === '' ? count($all) : ($val === 'PUBLISHED' ? count($published) : $drafts); ?>
          <a href="<?= e(base_url('dashboard/admin/testimonials.php' . ($val ? '?show=' . $val : ''))) ?>#library" class="<?= $filter === $val ? 'on' : '' ?>"><?= $label ?><em><?= $n ?></em></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <?php if (!$stories): ?>
      <p class="sx-none"><?= $all ? 'No stories in this view.' : 'No video stories yet. Upload the first one above.' ?></p>
    <?php else: ?>
      <div class="sx-grid">
        <?php foreach ($stories as $j => $s): $pos = array_search((int) $s['id'], $orderIds, true); $live = $s['status'] === 'PUBLISHED'; ?>
          <article class="sx-card acx-whip <?= $live ? 'live' : '' ?>" id="story-<?= (int) $s['id'] ?>" style="--j:<?= min($j, 10) ?>">
            <div class="sx-media">
              <video src="<?= e(base_url($s['video_url'])) ?>#t=0.5" controls playsinline preload="metadata"></video>
              <span class="sx-st <?= $live ? 'live' : '' ?>"><?= $live ? 'Published' : 'Draft' ?></span>
              <span class="sx-pos">#<?= $pos + 1 ?></span>
            </div>
            <div class="sx-body">
              <q><?= e($s['quote']) ?></q>
              <div class="sx-who"><b><?= e($s['student_name']) ?></b><small><?= $s['campus_name'] ? e($s['campus_name']) . ' Campus' : 'Obin Academy' ?><?= $s['duration_seconds'] ? ' · ' . e(video_story_length((int) $s['duration_seconds'])) : '' ?></small></div>
              <div class="sx-acts">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="story" value="<?= (int) $s['id'] ?>"><input type="hidden" name="_action" value="<?= $live ? 'unpublish' : 'publish' ?>"><button class="sx-btn <?= $live ? 'ghost' : 'lime' ?> sm" type="submit"><?= $live ? 'Unpublish' : 'Publish' ?></button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="story" value="<?= (int) $s['id'] ?>"><input type="hidden" name="_action" value="up"><button class="sx-ic" type="submit" title="Move up" aria-label="Move up" <?= $pos === 0 ? 'disabled' : '' ?>>↑</button></form>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="story" value="<?= (int) $s['id'] ?>"><input type="hidden" name="_action" value="down"><button class="sx-ic" type="submit" title="Move down" aria-label="Move down" <?= $pos === count($orderIds) - 1 ? 'disabled' : '' ?>>↓</button></form>
                <form method="post" data-confirm="Delete <?= e($s['student_name']) ?>'s story and its video? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="story" value="<?= (int) $s['id'] ?>"><input type="hidden" name="_action" value="delete"><button class="sx-ic del" type="submit" title="Delete" aria-label="Delete"><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
              </div>
              <details class="sx-edit">
                <summary>Edit details</summary>
                <form method="post">
                  <?= csrf_field() ?><input type="hidden" name="story" value="<?= (int) $s['id'] ?>"><input type="hidden" name="_action" value="update">
                  <label>Student's name<input name="student_name" maxlength="120" required value="<?= e($s['student_name']) ?>"></label>
                  <label>Campus<select name="campus"><option value="">Obin Academy in general</option><?php foreach ($campuses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $s['campus_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
                  <label>Pull quote<textarea name="quote" rows="3" maxlength="<?= VIDEO_STORY_QUOTE_MAX ?>" required><?= e($s['quote']) ?></textarea></label>
                  <button class="sx-btn sm" type="submit">Save</button>
                </form>
              </details>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-stories.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
