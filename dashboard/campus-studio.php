<?php
/**
 * Campus Studio: where a campus's professor builds the teaching for their own campus only. They write
 * the twelve monthly stages, upload, rename, reorder, move and remove lessons, and schedule live
 * classes. Prices, campus details, publishing, members and pay are not here: Obin Academy sets those
 * (dashboard/admin/campus-edit.php), and sees and can change everything a professor does. Every change
 * is written to the audit log under the professor's name.
 *
 * Content rules: includes/campus_content.php (shared with the admin page).
 * Styles: assets/css/admin-campuses.css (console) + assets/css/campus-studio.css.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/audit.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/campuses.php';
require __DIR__ . '/../includes/campus_content.php';
$user = require_login();

// Only the campuses this account is the linked professor of. Anything else is refused.
$teaching = [];
foreach (get_professor_campuses((int) $user['id']) as $c) $teaching[(int) $c['id']] = $c;
if (!$teaching) {
    flash_set('error', $user['role'] === 'ADMIN' ? 'Campus Studio is for professors. Set up campuses from Admin, Campuses.' : 'You are not the professor of a campus yet.');
    redirect($user['role'] === 'ADMIN' ? '/dashboard/admin/campuses.php' : '/teach.php');
}
$campus = $teaching[(int) ($_SERVER['REQUEST_METHOD'] === 'POST' ? post('campus') : query_param('campus'))] ?? reset($teaching);
$id = (int) $campus['id'];
$tab = in_array(query_param('tab'), ['curriculum', 'lessons', 'live'], true) ? query_param('tab') : 'lessons';
$here = fn(string $t, array $q = []) => '/dashboard/campus-studio.php?' . http_build_query(['campus' => $id, 'tab' => $t] + $q);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A lesson bigger than post_max_size arrives with an empty body: explain instead of failing the form check.
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash_set('error', 'That file is bigger than the server accepts. Export a smaller copy (720p MP4 works well for videos) and try again.');
        redirect($here('lessons', ['month' => max(1, min(12, (int) query_param('month', '1')))]));
    }
    csrf_verify();
    $a = post('_action');
    if (!in_array($a, CAMPUS_CONTENT_ACTIONS, true) || !isset($teaching[(int) post('campus')])) {
        flash_set('error', 'You can only change the lessons, stages and live classes of your own campus.');
        redirect($here($tab));
    }
    try {
        $to = campus_content_handle($a, $campus, $user);
        redirect($here($to['tab'], $to['month'] ? ['month' => $to['month']] : []));
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
        redirect($here(post('back_tab') ?: $tab, post('month') !== '' ? ['month' => max(1, min(12, (int) post('month')))] : []));
    }
}

$stages = get_campus_stages($id);
$byMonth = get_campus_lessons_by_month($id);
$lessonTotal = array_sum(array_map('count', $byMonth));
$written = count(array_filter($stages, fn($s) => trim((string) $s['title']) !== ''));
$monthsFilled = count(array_filter(range(1, 12), fn($m) => !empty($byMonth[$m])));
$upcoming = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at', [$id]);
$past = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at DESC LIMIT 10', [$id]);
$members = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [$id])['n'] ?? 0);
// Lessons open on the month being asked for, else the first month that has no lessons yet.
$firstEmpty = 1;
foreach (range(1, 12) as $m) if (empty($byMonth[$m])) { $firstEmpty = $m; break; }
$month = max(1, min(12, (int) query_param('month', (string) $firstEmpty)));
$open = $campus['status'] === 'PUBLISHED';
$photo = campus_photo($campus);

// The biggest file this server accepts: the smallest of our cap and PHP's upload and post limits.
$iniBytes = function (string $v): int {
    $v = trim($v); $n = (int) $v; $u = strtolower(substr($v, -1));
    return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
};
$serverMax = min($iniBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, $iniBytes((string) ini_get('post_max_size')) ?: PHP_INT_MAX);
$maxVideo = min(UPLOAD_MAX_BYTES['videos'], $serverMax);
$maxPdf = min(UPLOAD_MAX_BYTES['pdfs'], $serverMax);
$hidden = fn(string $action, string $backTab = '') => csrf_field() . '<input type="hidden" name="campus" value="' . $id . '"><input type="hidden" name="_action" value="' . e($action) . '">' . ($backTab ? '<input type="hidden" name="back_tab" value="' . e($backTab) . '">' : '');

$pageTitle = 'Campus Studio — ' . $campus['name'] . ' — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/campus-studio.css'];
require __DIR__ . '/../includes/dashboard_header.php';
?>
<div class="ov acx cs" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Campus Studio</h1><p>Build the year for your students: the twelve stages, the lessons in each month, and your live classes.</p></div>
    <div class="acx-acts">
      <?php if (count($teaching) > 1): ?>
        <form method="get" class="cs-pick"><input type="hidden" name="tab" value="<?= e($tab) ?>"><select name="campus" onchange="this.form.submit()" aria-label="Campus"><?php foreach ($teaching as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $id ? ' selected' : '' ?>><?= e($c['name']) ?> Campus</option><?php endforeach; ?></select></form>
      <?php endif; ?>
      <a class="acx-btn" href="<?= e(base_url('campus-learn.php?campus=' . $campus['slug'])) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>See it as a student</a>
    </div>
  </div>

  <section class="lc-console cs-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $open ? '' : 'calm' ?>"><i></i><?= $open ? 'Open · students can join' : 'Not open yet · Obin Academy opens it' ?></span>
      <div class="cs-who">
        <span class="cs-ph" style="<?= $photo ? 'background-image:url(\'' . e($photo) . '\')' : '' ?>"><?= $photo ? '' : campus_icon($campus['icon'] ?: 'book') ?></span>
        <div><h2><?= e($campus['name']) ?> Campus</h2><p>You are the professor<?= $campus['live_schedule'] ? ' · live ' . e($campus['live_schedule']) : '' ?></p></div>
      </div>
      <div class="lc-stats">
        <div><span>Stages written</span><b class="<?= $written === 12 ? 'lime' : '' ?>"><?= $written ?><small style="margin-left:4px">/ 12</small></b></div>
        <div><span>Months with lessons</span><b class="<?= $monthsFilled === 12 ? 'lime' : '' ?>"><?= $monthsFilled ?><small style="margin-left:4px">/ 12</small></b></div>
        <div><span>Lessons</span><b data-scramble="<?= $lessonTotal ?>"><?= $lessonTotal ?></b></div>
        <div><span>Active students</span><b data-scramble="<?= $members ?>"><?= $members ?></b></div>
      </div>
      <p class="cs-note">Prices, opening the campus and memberships are handled by Obin Academy.</p>
    </div>
    <div>
      <div class="lc-rx-h"><b>Your year</b><span>tap a month to fill it</span></div>
      <div class="cs-year">
        <?php for ($m = 1; $m <= 12; $m++): $n = count($byMonth[$m] ?? []); $t = trim((string) $stages[$m]['title']); ?>
          <a href="<?= e(base_url(ltrim($here('lessons', ['month' => $m]), '/'))) ?>#studio" class="<?= $n ? 'has' : '' ?> <?= $t === '' ? 'untitled' : '' ?> <?= $tab === 'lessons' && $m === $month ? 'on' : '' ?>" style="--m:<?= $m ?>">
            <span class="cs-mn">M<?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?></span>
            <b><?= $t !== '' ? e($t) : 'No title yet' ?></b>
            <small><?= $n ?> lesson<?= $n === 1 ? '' : 's' ?></small>
          </a>
        <?php endfor; ?>
      </div>
      <div class="cs-next">
        <?php if ($upcoming): $nx = $upcoming[0]; ?>
          <span class="cs-live-dot"></span><b>Next live class</b><span><?= e($nx['title']) ?> · <?= e(date('D j M, g:i a', strtotime($nx['starts_at']))) ?></span>
        <?php else: ?>
          <span class="cs-live-dot off"></span><b>No live class scheduled</b><a href="<?= e(base_url(ltrim($here('live'), '/'))) ?>#studio">Schedule one →</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="acx-sec acx-slam" style="--i:2" id="studio">
    <nav class="cs-tabs" aria-label="Studio sections">
      <?php foreach (['lessons' => ['Lessons', $lessonTotal], 'curriculum' => ['Twelve stages', $written . '/12'], 'live' => ['Live classes', count($upcoming)]] as $k => [$label, $n]): ?>
        <a href="<?= e(base_url(ltrim($here($k, $k === 'lessons' ? ['month' => $month] : []), '/'))) ?>#studio"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?><em><?= e((string) $n) ?></em></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'lessons'): $ls = $byMonth[$month] ?? []; ?>
      <div class="cs-months" role="tablist" aria-label="Months">
        <?php for ($m = 1; $m <= 12; $m++): $n = count($byMonth[$m] ?? []); ?>
          <a href="<?= e(base_url(ltrim($here('lessons', ['month' => $m]), '/'))) ?>#studio" class="<?= $n ? 'has' : '' ?>"<?= $m === $month ? ' aria-current="page"' : '' ?>><?= $m ?><small><?= $n ?></small></a>
        <?php endfor; ?>
      </div>
      <div class="cs-two">
        <div>
          <div class="cs-h"><div><h2>Month <?= $month ?><?= trim((string) $stages[$month]['title']) !== '' ? ' · ' . e($stages[$month]['title']) : '' ?></h2><p><?= count($ls) ?> lesson<?= count($ls) === 1 ? '' : 's' ?>, in the order students see them<?= trim((string) $stages[$month]['summary']) !== '' ? '. ' . e($stages[$month]['summary']) : '' ?></p></div></div>
          <?php if (!$ls): ?>
            <div class="cs-empty"><b>Month <?= $month ?> is empty</b><p>Upload the first lesson with the form. Students reach month <?= $month ?> <?= $month === 1 ? 'the day they join' : ($month - 1) . ' month' . ($month === 2 ? '' : 's') . ' after joining' ?>.</p></div>
          <?php endif; ?>
          <div class="cs-lessons">
            <?php foreach ($ls as $i => $l): ?>
              <div class="cs-lesson acx-whip" style="--j:<?= $i ?>">
                <span class="cs-kind <?= $l['kind'] === 'PDF' ? 'pdf' : '' ?>"><?= $l['kind'] === 'PDF' ? 'PDF' : 'Video' ?></span>
                <div class="cs-lt"><b><span><?= $month ?>.<?= $i + 1 ?></span><?= e($l['title']) ?></b><small><?= e($l['file_name'] ?: '') ?><?= $l['duration_label'] ? ' · ' . e($l['duration_label']) : '' ?></small></div>
                <div class="cs-acts">
                  <a class="cs-ic" href="<?= e(base_url('campus-learn.php?campus=' . $campus['slug'] . '&view=lesson&lesson=' . $l['id'])) ?>" target="_blank" rel="noopener" title="Watch as a student" aria-label="Watch as a student"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></a>
                  <?php foreach (['up' => ['↑', $i === 0], 'down' => ['↓', $i === count($ls) - 1]] as $dir => [$sym, $off]): ?>
                    <form method="post"><?= $hidden('lesson_move') ?><input type="hidden" name="lesson" value="<?= (int) $l['id'] ?>"><input type="hidden" name="dir" value="<?= $dir ?>"><button class="cs-ic" type="submit" aria-label="Move <?= $dir ?>" <?= $off ? 'disabled' : '' ?>><?= $sym ?></button></form>
                  <?php endforeach; ?>
                  <form method="post" data-confirm="Remove “<?= e($l['title']) ?>” and its file? Students lose access to it."><?= $hidden('lesson_delete') ?><input type="hidden" name="lesson" value="<?= (int) $l['id'] ?>"><button class="cs-ic del" type="submit" aria-label="Remove lesson"><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
                </div>
                <details class="cs-edit">
                  <summary>Edit</summary>
                  <form method="post"><?= $hidden('lesson_rename', 'lessons') ?><input type="hidden" name="lesson" value="<?= (int) $l['id'] ?>"><input type="hidden" name="month" value="<?= $month ?>">
                    <label>Title<input name="title" maxlength="200" required value="<?= e($l['title']) ?>"></label>
                    <label>Length<input name="duration" maxlength="40" value="<?= e($l['duration_label'] ?? '') ?>" placeholder="e.g. 18 min"></label>
                    <label>Month<select name="to_month"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"<?= $m === $month ? ' selected' : '' ?>>Month <?= $m ?><?= trim((string) $stages[$m]['title']) !== '' ? ' · ' . e(mb_strimwidth($stages[$m]['title'], 0, 28, '…')) : '' ?></option><?php endfor; ?></select></label>
                    <button class="cs-btn sm" type="submit">Save</button>
                  </form>
                </details>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="cs-form cs-upload" data-upload>
          <?= $hidden('lesson_add', 'lessons') ?><input type="hidden" name="month" value="<?= $month ?>">
          <h3>Add a lesson to month <?= $month ?></h3>
          <label>Title<input name="title" maxlength="200" required placeholder="e.g. Reading a candlestick chart"></label>
          <div class="cs-f2">
            <label>Type<select name="kind" data-kind><option value="VIDEO">Video</option><option value="PDF">PDF guide</option></select></label>
            <label><span>Length <small>optional</small></span><input name="duration" maxlength="40" placeholder="18 min or 4 pages"></label>
          </div>
          <label class="cs-file"><input type="file" name="file" accept="video/mp4,video/webm,video/quicktime,video/ogg,application/pdf" required data-file>
            <span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg><b data-file-name>Choose the file</b><small>Video up to <?= e(format_max_size($maxVideo)) ?> (MP4 is best) · PDF up to <?= e(format_max_size($maxPdf)) ?></small></span>
          </label>
          <button class="cs-btn lime" type="submit" data-upload-btn>Upload lesson <span>→</span></button>
          <p class="cs-uploading" data-upload-note hidden><i></i>Uploading… keep this page open until it finishes.</p>
        </form>
      </div>

    <?php elseif ($tab === 'curriculum'): ?>
      <form method="post" class="cs-form cs-stages">
        <?= $hidden('curriculum', 'curriculum') ?>
        <p class="cs-lead">One stage per month. Month 1 opens the day a student joins, month 2 a month later, and so on. Give each a clear title and a line on what it covers.</p>
        <?php for ($m = 1; $m <= 12; $m++): $n = count($byMonth[$m] ?? []); ?>
          <div class="cs-stage acx-whip <?= trim((string) $stages[$m]['title']) !== '' ? 'done' : '' ?>" style="--j:<?= $m - 1 ?>">
            <span class="cs-sn"><small>Month</small><?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?></span>
            <input name="title[<?= $m ?>]" value="<?= e($stages[$m]['title']) ?>" placeholder="Stage title" maxlength="160" aria-label="Month <?= $m ?> title">
            <input name="summary[<?= $m ?>]" value="<?= e($stages[$m]['summary']) ?>" placeholder="What it covers (optional)" maxlength="255" aria-label="Month <?= $m ?> summary">
            <a class="cs-count" href="<?= e(base_url(ltrim($here('lessons', ['month' => $m]), '/'))) ?>#studio" title="Lessons in month <?= $m ?>"><?= $n ?> lesson<?= $n === 1 ? '' : 's' ?></a>
          </div>
        <?php endfor; ?>
        <div><button class="cs-btn lime" type="submit">Save the twelve stages <span>→</span></button></div>
      </form>

    <?php else: ?>
      <div class="cs-two">
        <div>
          <div class="cs-h"><div><h2>Coming up</h2><p>Students see these on their campus page with a Join button.</p></div></div>
          <div class="cs-lives">
            <?php if (!$upcoming && !$past): ?><div class="cs-empty"><b>No live classes yet</b><p>Schedule your first one with the form<?= $campus['live_schedule'] ? '. Your weekly time is ' . e($campus['live_schedule']) : '' ?>.</p></div><?php endif; ?>
            <?php foreach (array_merge($upcoming, $past) as $j => $l): $isPast = strtotime($l['starts_at']) < time() - 10800; ?>
              <div class="cs-live acx-whip <?= $isPast ? 'past' : '' ?>" style="--j:<?= min($j, 10) ?>">
                <div class="cs-date"><small><?= e(date('M', strtotime($l['starts_at']))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></div>
                <div class="cs-lt"><b><?= e($l['title']) ?></b><small><?= e(date('D, g:i a', strtotime($l['starts_at']))) ?> · <?= (int) $l['duration_minutes'] ?> min<?= $isPast ? ' · done' : ($l['join_url'] ? '' : ' · <em>no join link yet</em>') ?></small></div>
                <form method="post" data-confirm="Remove this live class?"><?= $hidden('live_delete', 'live') ?><input type="hidden" name="live" value="<?= (int) $l['id'] ?>"><button class="cs-ic del" type="submit" aria-label="Remove live class"><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button></form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <form method="post" class="cs-form cs-upload">
          <?= $hidden('live_add', 'live') ?>
          <h3>Schedule a live class</h3>
          <label>Topic<input name="title" maxlength="200" required placeholder="e.g. Position sizing on real charts"></label>
          <div class="cs-f3">
            <label>Date<input type="date" name="date" required value="<?= e(date('Y-m-d')) ?>"></label>
            <label>Start<input type="time" name="time" required value="20:00"></label>
            <label>Minutes<input type="number" name="minutes" value="90" min="15" max="300"></label>
          </div>
          <label><span>Join link <small>Zoom or Google Meet</small></span><input name="url" type="url" placeholder="https://meet.google.com/..."></label>
          <button class="cs-btn lime" type="submit">Add live class <span>→</span></button>
          <p class="cs-hint">Afterwards, upload the recording as a video lesson in the current month.</p>
        </form>
      </div>
    <?php endif; ?>
  </section>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<script>
document.querySelectorAll('form[data-upload]').forEach(function (f) {
  var input = f.querySelector('[data-file]'), name = f.querySelector('[data-file-name]');
  if (input && name) input.addEventListener('change', function () { var x = input.files[0]; name.textContent = x ? x.name + ' · ' + (x.size / 1048576).toFixed(1) + 'MB' : 'Choose the file'; f.querySelector('.cs-file').classList.toggle('has', !!x); });
  f.addEventListener('submit', function () { var b = f.querySelector('[data-upload-btn]'); if (b) { b.disabled = true; b.firstChild.textContent = 'Uploading… '; } var n = f.querySelector('[data-upload-note]'); if (n) n.hidden = false; });
});
</script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
