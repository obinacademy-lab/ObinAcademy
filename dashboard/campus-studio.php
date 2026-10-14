<?php
/**
 * Campus Studio: where a campus's professor builds the teaching for their own campus only. They write
 * the twelve monthly stages and give each a picture, upload, rename, reorder, move and remove lessons,
 * schedule live classes, and mark the month the class is on complete, which opens the next month for
 * every student (the whole campus moves together). Prices, campus details, publishing, members and pay are not here: Obin Academy sets those
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
require __DIR__ . '/../includes/campus_pages.php';
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
$tab = in_array(query_param('tab'), ['curriculum', 'lessons', 'live', 'profile'], true) ? query_param('tab') : 'lessons';
$here = fn(string $t, array $q = []) => '/dashboard/campus-studio.php?' . http_build_query(['campus' => $id, 'tab' => $t] + $q);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A lesson bigger than post_max_size arrives with an empty body: explain instead of failing the form check.
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash_set('error', 'That file is bigger than the server accepts. Export a smaller copy (720p MP4 works well for videos) and try again.');
        redirect($here('lessons', ['month' => max(1, min(12, (int) query_param('month', '1')))]));
    }
    csrf_verify();
    $a = post('_action');
    // The professor's own quote and social links on the public campus page (never prices or pay).
    if ($a === 'prof_links' && isset($teaching[(int) post('campus')])) {
        try {
            campus_save_professor_links($id);
            log_admin_action((int) $user['id'], $user['name'], 'campus.professor_links_updated', 'Campus', $campus['name']);
            flash_set('success', 'Saved. Your quote and links now show on the campus page.');
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
        }
        redirect($here('profile'));
    }
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
// Where the class is: students can open every month up to $classMonth; the professor completes it to open the next.
$paced = campus_progress_ready();
$done = campus_months_completed($campus);
$classMonth = campus_open_month($campus);
$yearDone = $done >= 12;
$stageState = fn(int $m) => $m <= $done ? 'done' : ($m === $classMonth && !$yearDone ? 'now' : 'later');

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
      <?php if ($paced): ?>
        <div class="cs-class <?= $yearDone ? 'finished' : '' ?>">
          <div class="cs-class-now">
            <span><?= $yearDone ? 'The year is complete' : 'The class is on' ?></span>
            <b><?= $yearDone ? 'All 12 months' : 'Month ' . $classMonth ?></b>
            <small><?= $yearDone ? 'Students who finish every lesson get their certificate.' : (trim((string) $stages[$classMonth]['title']) !== '' ? e($stages[$classMonth]['title']) : 'No title yet') . ' · ' . count($byMonth[$classMonth] ?? []) . ' lesson' . (count($byMonth[$classMonth] ?? []) === 1 ? '' : 's') ?></small>
          </div>
          <?php if (!$yearDone): $nextN = count($byMonth[$classMonth + 1] ?? []); ?>
            <form method="post" class="cs-complete" data-confirm="<?= e($classMonth < 12 ? 'Mark month ' . $classMonth . ' complete? Month ' . ($classMonth + 1) . ' opens right away for ' . ($members === 1 ? 'your 1 active student' : ($members ? 'all ' . $members . ' active students' : 'every student')) . ($nextN ? '' : ', but it has no lessons yet') . '. They get a notification.' : 'Mark month 12 complete? This finishes the year for the class.') ?>">
              <?= $hidden('month_complete', $tab) ?><input type="hidden" name="month" value="<?= $classMonth ?>">
              <button type="submit"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>Mark month <?= $classMonth ?> complete</button>
            </form>
          <?php endif; ?>
          <?php if ($done > 0): ?>
            <form method="post" class="cs-undo" data-confirm="Undo completing month <?= $done ?>? <?= $done < 12 ? 'Month ' . ($done + 1) . ' closes again for students until you complete month ' . $done . '.' : '' ?>"><?= $hidden('month_undo', $tab) ?><button type="submit">Undo month <?= $done ?></button></form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <p class="cs-note"><?= $paced ? 'Students see every month up to the one the class is on. ' : '' ?>Prices, opening the campus and memberships are handled by Obin Academy.</p>
    </div>
    <div>
      <div class="lc-rx-h"><b>Your year</b><span><?= $paced ? $done . ' of 12 months complete' : 'tap a month to fill it' ?></span></div>
      <div class="cs-year">
        <?php for ($m = 1; $m <= 12; $m++): $n = count($byMonth[$m] ?? []); $t = trim((string) $stages[$m]['title']); $st = $paced ? $stageState($m) : ''; $th = $stages[$m]['thumbnail_url'] ?? null; ?>
          <a href="<?= e(base_url(ltrim($here('lessons', ['month' => $m]), '/'))) ?>#studio" class="<?= $n ? 'has' : '' ?> <?= $t === '' ? 'untitled' : '' ?> <?= $tab === 'lessons' && $m === $month ? 'on' : '' ?> st-<?= $st ?> <?= $th ? 'pic' : '' ?>" style="--m:<?= $m ?>;<?= $th ? ' --pic:url(\'' . e(asset_src($th)) . '\')' : '' ?>">
            <span class="cs-mn">M<?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?><?php if ($st === 'done'): ?><i class="cs-tick" title="Complete">✓</i><?php elseif ($st === 'now'): ?><i class="cs-here">Class is here</i><?php endif; ?></span>
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
      <?php foreach (['lessons' => ['Lessons', $lessonTotal], 'curriculum' => ['Twelve stages', $written . '/12'], 'live' => ['Live classes', count($upcoming)], 'profile' => ['Your profile', count(campus_professor_links($campus)) . ' links']] as $k => [$label, $n]): ?>
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
      <form method="post" class="cs-form cs-stages" id="stages-form">
        <?= $hidden('curriculum', 'curriculum') ?>
        <?php if (!$paced): ?><div class="cs-setup"><b>Month pictures are being switched on</b><span>Obin Academy is finishing a quick update. Once it is done, a "+ Picture" box appears next to every month here, and you can mark months complete.</span></div><?php endif; ?>
        <p class="cs-lead"><?= $paced ? 'Build the whole year here. Students start on month 1 and see every month\'s picture and title, so they know what is coming. Each month opens for the class when you mark the month before it complete.' : 'One stage per month. Give each a clear title and a line on what it covers.' ?></p>
        <?php for ($m = 1; $m <= 12; $m++): $n = count($byMonth[$m] ?? []); $th = $stages[$m]['thumbnail_url'] ?? null; $st = $paced ? $stageState($m) : ''; ?>
          <div class="cs-stage acx-whip <?= trim((string) $stages[$m]['title']) !== '' ? 'done' : '' ?> <?= $paced ? 'paced' : '' ?>" style="--j:<?= $m - 1 ?>">
            <span class="cs-sn"><small>Month</small><?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?></span>
            <?php if ($paced): ?>
              <div class="cs-thumb <?= $th ? 'has' : '' ?>">
                <label title="<?= $th ? 'Change the picture for month ' . $m : 'Add a picture for month ' . $m ?>" style="<?= $th ? 'background-image:url(\'' . e(asset_src($th)) . '\')' : '' ?>">
                  <input type="file" name="thumb" accept="image/png,image/jpeg,image/webp" form="th-<?= $m ?>" data-thumb aria-label="Picture for month <?= $m ?>">
                  <span><?= $th ? 'Change' : '+ Picture' ?></span>
                </label>
                <?php if ($th): ?><button type="submit" form="thr-<?= $m ?>" class="cs-thumb-x" aria-label="Remove the picture for month <?= $m ?>">×</button><?php endif; ?>
              </div>
            <?php endif; ?>
            <input name="title[<?= $m ?>]" value="<?= e($stages[$m]['title']) ?>" placeholder="Stage title" maxlength="160" aria-label="Month <?= $m ?> title">
            <input name="summary[<?= $m ?>]" value="<?= e($stages[$m]['summary']) ?>" placeholder="What it covers (optional)" maxlength="255" aria-label="Month <?= $m ?> summary">
            <div class="cs-side">
              <?php if ($st === 'done'): ?><span class="cs-st done" title="<?= !empty($stages[$m]['completed_at']) ? 'Completed ' . e(date('j M Y', strtotime($stages[$m]['completed_at']))) : '' ?>">Complete</span>
              <?php elseif ($st === 'now'): ?><span class="cs-st now">Class is here</span>
              <?php elseif ($st === 'later'): ?><span class="cs-st later">Opens later</span><?php endif; ?>
              <a class="cs-count" href="<?= e(base_url(ltrim($here('lessons', ['month' => $m]), '/'))) ?>#studio" title="Lessons in month <?= $m ?>"><?= $n ?> lesson<?= $n === 1 ? '' : 's' ?></a>
            </div>
          </div>
        <?php endfor; ?>
        <div><button class="cs-btn lime" type="submit">Save titles and summaries <span>→</span></button></div>
      </form>
      <?php if ($paced): for ($m = 1; $m <= 12; $m++): ?>
        <form method="post" enctype="multipart/form-data" id="th-<?= $m ?>" hidden><?= $hidden('stage_thumb', 'curriculum') ?><input type="hidden" name="month" value="<?= $m ?>"></form>
        <?php if (!empty($stages[$m]['thumbnail_url'])): ?><form method="post" id="thr-<?= $m ?>" hidden data-confirm="Remove the picture for month <?= $m ?>?"><?= $hidden('stage_thumb_remove', 'curriculum') ?><input type="hidden" name="month" value="<?= $m ?>"></form><?php endif; ?>
      <?php endfor; endif; ?>

    <?php elseif ($tab === 'profile'): ?>
      <div class="cs-two">
        <div>
          <div class="cs-h"><div><h2>How students see you</h2><p>Your name, photo, title and bio are set by Obin Academy. Your quote and social links are yours to keep up to date.</p></div></div>
          <div class="cs-preview">
            <span class="cs-pv-ph"<?= $campus['professor_photo_url'] ? ' style="background-image:url(\'' . e(asset_src($campus['professor_photo_url'])) . '\')"' : '' ?>><?= $campus['professor_photo_url'] ? '' : e(mb_strtoupper(mb_substr((string) $campus['professor_name'], 0, 1))) ?></span>
            <div><b><?= e($campus['professor_name'] ?: $user['name']) ?></b><small><?= e($campus['professor_title'] ?: 'Lead professor, ' . $campus['name'] . ' Campus') ?></small>
              <?php $pl = campus_professor_links($campus); if ($pl): ?><span class="cs-pv-links"><?php foreach ($pl as [$label, $url, $path]): ?><a href="<?= e($url) ?>" target="_blank" rel="noopener" title="<?= e($label) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $path ?>"/></svg></a><?php endforeach; ?></span><?php else: ?><em>No links yet</em><?php endif; ?>
            </div>
          </div>
          <?php if (!empty($campus['professor_quote'])): ?><p class="cs-pv-quote">“<?= e($campus['professor_quote']) ?>”</p><?php endif; ?>
          <p class="cs-hint" style="padding:0 22px 22px;"><a href="<?= e(base_url('campus.php?slug=' . $campus['slug'])) ?>#join" target="_blank" rel="noopener" style="color:var(--ov-brand)">Open the campus page →</a> To change your name, photo or bio, message Obin Academy.</p>
        </div>
        <?php if (campus_links_ready()): ?>
          <form method="post" class="cs-form cs-upload">
            <?= $hidden('prof_links', 'profile') ?>
            <h3>Your quote and links</h3>
            <label><span>Your quote <small>one or two sentences, shown large on the campus page</small></span><input name="quote" maxlength="300" value="<?= e($campus['professor_quote'] ?? '') ?>" placeholder="I teach the exact methods I use every day."></label>
            <?php foreach (CAMPUS_PROFESSOR_SOCIALS as $key => [$label]): ?>
              <label><?= e($label) ?><input name="<?= $key ?>" value="<?= e($campus['professor_' . $key] ?? '') ?>" placeholder="<?= $key === 'whatsapp' ? '0772 123 456' : ($key === 'linkedin' ? 'https://linkedin.com/in/…' : '@username or full link') ?>" maxlength="<?= $key === 'whatsapp' ? 32 : 255 ?>"></label>
            <?php endforeach; ?>
            <button class="cs-btn lime" type="submit">Save my profile <span>→</span></button>
            <p class="cs-hint">Only the links you fill in show, as icons under your photo. Leave a box empty to hide that one.</p>
          </form>
        <?php else: ?>
          <div class="cs-form cs-upload"><div class="cs-setup"><b>Profile links are being switched on</b><span>Obin Academy is finishing a quick update. Once it is done, you can add your quote and social media links here.</span></div></div>
        <?php endif; ?>
      </div>
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
// Month pictures upload as soon as one is chosen (each has its own small form, so they don't need the Save button).
(function () {
  var stages = document.getElementById('stages-form'), dirty = false;
  if (stages) stages.addEventListener('input', function () { dirty = true; });
  document.querySelectorAll('[data-thumb]').forEach(function (input) {
    input.addEventListener('change', function () {
      if (!input.files.length) return;
      if (dirty && !confirm('You have changed titles you have not saved. Upload the picture anyway? Those changes will be lost.')) { input.value = ''; return; }
      input.closest('label').classList.add('busy');
      input.closest('label').querySelector('span').textContent = 'Uploading…';
      input.form.submit();
    });
  });
})();
document.querySelectorAll('form[data-upload]').forEach(function (f) {
  var input = f.querySelector('[data-file]'), name = f.querySelector('[data-file-name]');
  if (input && name) input.addEventListener('change', function () { var x = input.files[0]; name.textContent = x ? x.name + ' · ' + (x.size / 1048576).toFixed(1) + 'MB' : 'Choose the file'; f.querySelector('.cs-file').classList.toggle('has', !!x); });
  f.addEventListener('submit', function () { var b = f.querySelector('[data-upload-btn]'); if (b) { b.disabled = true; b.firstChild.textContent = 'Uploading… '; } var n = f.querySelector('[data-upload-note]'); if (n) n.hidden = false; });
});
</script>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
