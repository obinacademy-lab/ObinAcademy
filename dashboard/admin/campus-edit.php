<?php
/**
 * Admin: set up one campus. Details and prices, the professor, the twelve-month curriculum, lessons
 * (video and PDF uploads per month), live classes, and members (including giving access by hand).
 * Curriculum, lessons and live classes go through includes/campus_content.php, shared with the
 * professor's Campus Studio; everything else here is admin-only.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/storage.php';
require __DIR__ . '/../../includes/campuses.php';
require __DIR__ . '/../../includes/campus_content.php';
$user = require_role(['ADMIN']);

if (!campuses_ready()) redirect('/dashboard/admin/campuses.php');
$campus = get_campus((int) query_param('id'));
if (!$campus) { flash_set('error', 'Campus not found.'); redirect('/dashboard/admin/campuses.php'); }
$id = (int) $campus['id'];
$tab = query_param('tab', 'details');
if (!in_array($tab, ['details', 'professor', 'curriculum', 'lessons', 'live', 'members'], true)) $tab = 'details';
$here = fn(string $t, array $q = []) => '/dashboard/admin/campus-edit.php?' . http_build_query(['id' => $id, 'tab' => $t] + $q);
$money = fn(string $v) => (float) str_replace([',', ' ', 'UGX', 'ugx'], '', $v);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $a = post('_action');
    $log = fn(string $what, string $detail = '') => log_admin_action((int) $user['id'], $user['name'], 'campus.' . $what, 'Campus', $campus['name'], $detail);
    try {
        if ($a === 'details') {
            $monthly = $money(post('monthly_price'));
            $yearly = $money(post('yearly_price'));
            if (trim(post('name')) === '' || $monthly <= 0 || $yearly <= 0) throw new RuntimeException('Give the campus a name and a monthly and yearly price above zero.');
            db_run(
                'UPDATE campuses SET name = ?, tagline = ?, description = ?, icon = ?, monthly_price = ?, yearly_price = ?, live_schedule = ?, sort_order = ? WHERE id = ?',
                [mb_substr(trim(post('name')), 0, 120), mb_substr(trim(post('tagline')), 0, 255), trim(post('description')) ?: null,
                 array_key_exists(post('icon'), CAMPUS_ICON_PATHS) ? post('icon') : 'biz', $monthly, $yearly, mb_substr(trim(post('live_schedule')), 0, 120) ?: null, (int) post('sort_order'), $id]
            );
            $log('updated', 'details and prices');
            flash_set('success', 'Saved.');
            redirect($here('details'));
        }
        if ($a === 'status') {
            $to = post('to') === 'PUBLISHED' ? 'PUBLISHED' : 'DRAFT';
            if ($to === 'PUBLISHED') {
                $c = get_campus($id);
                $hasMonthOne = (int) (db_one('SELECT COUNT(*) AS n FROM campus_lessons WHERE campus_id = ? AND month_no = 1', [$id])['n'] ?? 0);
                if (!$c['professor_name'] || !$hasMonthOne) throw new RuntimeException('Before publishing, add the professor and at least one lesson for month 1.');
            }
            db_run('UPDATE campuses SET status = ? WHERE id = ?', [$to, $id]);
            $log($to === 'PUBLISHED' ? 'published' : 'unpublished');
            flash_set('success', $to === 'PUBLISHED' ? 'The campus is open. Learners can now join and pay.' : 'The campus is back to draft. Members keep their access, but nobody new can join.');
            redirect($here('details'));
        }
        if ($a === 'professor') {
            $photo = $campus['professor_photo_url'];
            if (!empty($_FILES['photo']['name'])) $photo = save_upload($_FILES['photo'], 'thumbnails');
            db_run(
                'UPDATE campuses SET professor_name = ?, professor_title = ?, professor_bio = ?, professor_photo_url = ? WHERE id = ?',
                [mb_substr(trim(post('pname')), 0, 120) ?: null, mb_substr(trim(post('ptitle')), 0, 160) ?: null, trim(post('pbio')) ?: null, $photo, $id]
            );
            $note = '';
            if (professor_pay_ready()) {
                $share = (float) str_replace('%', '', post('share'));
                if ($share < 0 || $share > 100) throw new RuntimeException('The professor\'s share must be between 0 and 100%.');
                $linkId = null;
                $email = strtolower(trim(post('account_email')));
                if ($email !== '') {
                    $acct = db_one('SELECT id FROM users WHERE email = ?', [$email]);
                    if (!$acct) $note = ' No account uses ' . $email . ' yet, so their account is not linked. Ask them to sign up with that email, then save again.';
                    else $linkId = (int) $acct['id'];
                }
                db_run(
                    'UPDATE campuses SET professor_share_percent = ?, professor_user_id = ?, professor_payout_phone = ?, professor_payout_name = ? WHERE id = ?',
                    [$share, $linkId, mb_substr(trim(post('payout_phone')), 0, 32) ?: null, mb_substr(trim(post('payout_name')), 0, 120) ?: null, $id]
                );
            }
            $log('professor_updated', trim(post('pname')) . (professor_pay_ready() ? ', share ' . format_share((float) post('share')) : ''));
            flash_set($note ? 'error' : 'success', 'Professor saved.' . $note);
            redirect($here('professor'));
        }
        if (in_array($a, CAMPUS_CONTENT_ACTIONS, true)) {
            // Stages, lessons and live classes: the same rules as the professor's Campus Studio.
            $to = campus_content_handle($a, $campus, $user);
            redirect($here($to['tab'], $to['month'] ? ['month' => $to['month']] : []));
        }
        if ($a === 'grant') {
            $member = db_one('SELECT id, name, email FROM users WHERE email = ?', [strtolower(trim(post('email')))]);
            if (!$member) throw new RuntimeException('No account uses that email. The person must sign up first.');
            if (mb_strlen(trim(post('reason'))) < 5) throw new RuntimeException('Write a short reason, for the audit log.');
            $plan = post('plan') === 'YEARLY' ? 'YEARLY' : 'MONTHLY';
            $period = $plan === 'YEARLY' ? '+12 months' : '+' . CAMPUS_MONTH_DAYS . ' days';
            $m = get_campus_membership($id, (int) $member['id']);
            if ($m) {
                $until = date('Y-m-d H:i:s', strtotime($period, max(time(), strtotime($m['access_until']))));
                db_run('UPDATE campus_memberships SET plan = ?, access_until = ?, last_reminder_days = NULL WHERE id = ?', [$plan, $until, $m['id']]);
            } else {
                $until = date('Y-m-d H:i:s', strtotime($period));
                db_insert('INSERT INTO campus_memberships (campus_id, user_id, plan, started_at, access_until) VALUES (?, ?, ?, NOW(), ?)', [$id, $member['id'], $plan, $until]);
            }
            $log('access_granted', $member['email'] . ', ' . strtolower($plan) . ', until ' . date('j M Y', strtotime($until)) . '. Reason: ' . trim(post('reason')));
            create_user_notification((int) $member['id'], null, 'campus_joined', 'You now have access to the ' . $campus['name'] . ' Campus until ' . date('j M Y', strtotime($until)) . '.', base_url('campus-learn.php?campus=' . $campus['slug']));
            flash_set('success', $member['name'] . ' has access until ' . date('j M Y', strtotime($until)) . '. No payment was recorded.');
            redirect($here('members'));
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
        redirect($here($tab, $tab === 'lessons' ? ['month' => (int) post('month', '1')] : []));
    }
    redirect($here($tab));
}

$campus = get_campus($id);
$stages = get_campus_stages($id);
$byMonth = get_campus_lessons_by_month($id);
$lessonTotal = array_sum(array_map('count', $byMonth));
$members = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [$id])['n'] ?? 0);
$checks = [
    'Professor added' => (bool) $campus['professor_name'],
    'Curriculum written for all 12 months' => campus_curriculum_written($stages),
    'Lessons in month 1' => !empty($byMonth[1]),
    'Live class time set' => (bool) $campus['live_schedule'],
];
$tabs = ['details' => 'Details and prices', 'professor' => 'Professor', 'curriculum' => 'Curriculum', 'lessons' => 'Lessons (' . $lessonTotal . ')', 'live' => 'Live classes', 'members' => 'Members (' . $members . ')'];
$iconNames = ['biz' => 'Business', 'crypto' => 'Chart', 'fx' => 'Globe', 'content' => 'Video', 'fit' => 'Fitness', 'ai' => 'Chip', 'shop' => 'Cart', 'book' => 'Book'];

$pageTitle = $campus['name'] . ' — Campuses — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<a href="<?= e(base_url('dashboard/admin/campuses.php')) ?>" class="muted small">&larr; All campuses</a>
<div class="dash-page-head" style="margin-top:12px;">
  <div>
    <h1 class="h2"><?= e($campus['name']) ?> Campus</h1>
    <p class="muted" style="margin-top:6px;"><?= $campus['status'] === 'PUBLISHED' ? 'Open: learners can join and pay.' : 'Draft: only you can see it.' ?> <?= number_format($members) ?> active member<?= $members === 1 ? '' : 's' ?> · <?= $lessonTotal ?> lesson<?= $lessonTotal === 1 ? '' : 's' ?></p>
  </div>
  <div class="row gap-2 wrap">
    <a class="ld-btn" href="<?= e(base_url('campus.php?slug=' . $campus['slug'])) ?>" target="_blank" rel="noopener">Preview page</a>
    <a class="ld-btn" href="<?= e(base_url('campus-learn.php?campus=' . $campus['slug'])) ?>" target="_blank" rel="noopener">Preview member area</a>
    <form method="post" <?= $campus['status'] === 'PUBLISHED' ? 'data-confirm="Move this campus back to draft? Members keep access, but nobody new can join."' : '' ?>>
      <?= csrf_field() ?><input type="hidden" name="_action" value="status"><input type="hidden" name="to" value="<?= $campus['status'] === 'PUBLISHED' ? 'DRAFT' : 'PUBLISHED' ?>">
      <button class="btn <?= $campus['status'] === 'PUBLISHED' ? 'btn-outline' : 'btn-primary' ?>" type="submit"><?= $campus['status'] === 'PUBLISHED' ? 'Unpublish' : 'Publish campus' ?></button>
    </form>
  </div>
</div>

<?php if ($campus['status'] !== 'PUBLISHED'): ?>
  <div class="ld-panel ca-checks" style="margin-top:18px;">
    <?php foreach ($checks as $label => $okc): ?><span class="<?= $okc ? 'ok' : '' ?>"><i><?= $okc ? '&#10003;' : '' ?></i><?= e($label) ?></span><?php endforeach; ?>
    <small>A professor and at least one lesson in month 1 are needed to publish.</small>
  </div>
<?php endif; ?>

<nav class="ca-tabs" aria-label="Campus sections">
  <?php foreach ($tabs as $k => $label): ?><a href="<?= e(base_url(ltrim($here($k), '/'))) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>

<?php if ($tab === 'details'): ?>
  <form method="post" class="ld-panel ca-form">
    <?= csrf_field() ?><input type="hidden" name="_action" value="details">
    <div class="ca-f2">
      <label>Campus name<input name="name" value="<?= e($campus['name']) ?>" maxlength="120" required></label>
      <label>Icon<select name="icon"><?php foreach ($iconNames as $k => $n): ?><option value="<?= e($k) ?>"<?= $campus['icon'] === $k ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>One-line summary <small>Shown in the campus list</small><input name="tagline" value="<?= e($campus['tagline']) ?>" maxlength="255"></label>
    <label>Description <small>Shown at the top of the campus page</small><textarea name="description" rows="4"><?= e($campus['description'] ?? '') ?></textarea></label>
    <div class="ca-f3">
      <label>Monthly price (UGX)<input name="monthly_price" inputmode="numeric" value="<?= e(number_format((float) $campus['monthly_price'], 0, '.', '')) ?>" required></label>
      <label>Yearly price (UGX)<input name="yearly_price" inputmode="numeric" value="<?= e(number_format((float) $campus['yearly_price'], 0, '.', '')) ?>" required></label>
      <label>Order in the list<input name="sort_order" type="number" value="<?= (int) $campus['sort_order'] ?>"></label>
    </div>
    <label>Weekly live class time <small>For example: Thursdays, 8 pm</small><input name="live_schedule" value="<?= e($campus['live_schedule'] ?? '') ?>" maxlength="120"></label>
    <div><button class="btn btn-primary" type="submit">Save details</button></div>
  </form>

<?php elseif ($tab === 'professor'): ?>
  <form method="post" enctype="multipart/form-data" class="ld-panel ca-form">
    <?= csrf_field() ?><input type="hidden" name="_action" value="professor">
    <div class="ca-prof">
      <span class="ph"><?php if ($campus['professor_photo_url']): ?><img src="<?= e(asset_src($campus['professor_photo_url'])) ?>" alt=""><?php else: ?>P<?php endif; ?></span>
      <label>Photo <small>A square photo works best (PNG, JPG or WebP, up to 5MB)</small><input type="file" name="photo" accept="image/*"></label>
    </div>
    <div class="ca-f2">
      <label>Professor's name<input name="pname" value="<?= e($campus['professor_name'] ?? '') ?>" maxlength="120"></label>
      <label>Title <small>For example: Lead professor, 8 years trading</small><input name="ptitle" value="<?= e($campus['professor_title'] ?? '') ?>" maxlength="160"></label>
    </div>
    <label>About the professor <small>Their background and real results</small><textarea name="pbio" rows="5"><?= e($campus['professor_bio'] ?? '') ?></textarea></label>
    <?php if (professor_pay_ready()): $acct = $campus['professor_user_id'] ? db_one('SELECT name, email FROM users WHERE id = ?', [$campus['professor_user_id']]) : null; $owedNow = professor_pay_summary()[$id]['owed'] ?? 0; ?>
      <div class="ca-paybox">
        <div class="ca-paybox-h"><b>Pay</b><span>Private. Learners never see this.</span></div>
        <div class="ca-f3">
          <label>Share of each payment (%) <small>Paid on every monthly or yearly payment</small><input name="share" inputmode="decimal" value="<?= e(format_share(campus_professor_share($campus))) ?>" required></label>
          <label>Mobile money number <small>Where you send their pay</small><input name="payout_phone" type="tel" value="<?= e($campus['professor_payout_phone'] ?? '') ?>" placeholder="07XX XXX XXX" maxlength="32"></label>
          <label>Name on that number <small>To check before you send</small><input name="payout_name" value="<?= e($campus['professor_payout_name'] ?? '') ?>" maxlength="120"></label>
        </div>
        <label style="max-width:420px;">Their Obin Academy account email <small><?= $acct ? 'Linked to ' . e($acct['name']) . '. They build the stages, lessons and live classes in their Campus Studio, see their earnings, and post in the community as the professor.' : 'Optional. Once linked, they build the stages, lessons and live classes in their own Campus Studio (never prices), see every payment and their share, and post in the community as the professor.' ?></small><input name="account_email" type="email" value="<?= e($acct['email'] ?? '') ?>" placeholder="name@example.com"></label>
        <?php if ($owedNow > 0): ?><p class="ca-paynote">You owe this campus's professor <b><?= e(format_money($owedNow)) ?></b>. Pay it on <a href="<?= e(base_url('dashboard/admin/professor-pay.php?campus=' . $id)) ?>">Professor Pay</a> before you change the professor.</p><?php endif; ?>
      </div>
    <?php endif; ?>
    <div><button class="btn btn-primary" type="submit">Save professor</button></div>
  </form>

<?php elseif ($tab === 'curriculum'): $paced = campus_progress_ready(); $done = campus_months_completed($campus); $classMonth = campus_open_month($campus); ?>
  <?php if (!$paced): ?>
    <div class="ld-panel ca-class" style="border-color:#f5b301;"><div><small>One step left</small><b>Month pictures and the Month complete button are not switched on yet</b><span>Run migration/add-month-completion.sql in phpMyAdmin (Obin Academy database, SQL tab, paste, Go). A "+ Picture" box then appears next to every month below, and the class progress control appears here.</span></div></div>
  <?php endif; ?>
  <?php if ($paced): ?>
    <div class="ld-panel ca-class">
      <div><small>The class is on</small><b><?= $done >= 12 ? 'All 12 months complete' : 'Month ' . $classMonth . (trim((string) $stages[$classMonth]['title']) !== '' ? ' · ' . e($stages[$classMonth]['title']) : '') ?></b><span>Students see every month up to this one. The professor<?= $campus['professor_name'] ? ' (' . e($campus['professor_name']) . ')' : '' ?> marks it complete in Campus Studio to open the next month for everyone; you can do it here too.</span></div>
      <?php if ($done < 12): ?>
        <form method="post" data-confirm="Mark month <?= $classMonth ?> complete? <?= $classMonth < 12 ? 'Month ' . ($classMonth + 1) . ' opens right away for every active student.' : 'This finishes the year.' ?>"><?= csrf_field() ?><input type="hidden" name="_action" value="month_complete"><input type="hidden" name="month" value="<?= $classMonth ?>"><button class="btn btn-primary" type="submit">Mark month <?= $classMonth ?> complete</button></form>
      <?php endif; ?>
      <?php if ($done > 0): ?>
        <form method="post" data-confirm="Undo completing month <?= $done ?>?"><?= csrf_field() ?><input type="hidden" name="_action" value="month_undo"><button class="ot-btn" type="submit">Undo month <?= $done ?></button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" class="ld-panel ca-form" id="ca-stages" style="<?= $paced ? 'margin-top:16px;' : '' ?>">
    <?= csrf_field() ?><input type="hidden" name="_action" value="curriculum">
    <p class="muted small" style="margin:0;"><?= $paced ? 'One stage per month, each with an optional picture students see in their year. A month opens for the whole class when the month before it is marked complete.' : 'One stage per month.' ?></p>
    <?php for ($m = 1; $m <= 12; $m++): $th = $stages[$m]['thumbnail_url'] ?? null; ?>
      <div class="ca-stage<?= $paced ? ' paced' : '' ?>"><span class="n">Month <?= str_pad((string) $m, 2, '0', STR_PAD_LEFT) ?><?= $paced && $m <= $done ? ' <em class="ca-done">✓</em>' : '' ?></span>
        <?php if ($paced): ?><label class="ca-thumb" title="<?= $th ? 'Change' : 'Add' ?> the picture for month <?= $m ?>" style="<?= $th ? 'background-image:url(\'' . e(asset_src($th)) . '\')' : '' ?>"><input type="file" name="thumb" accept="image/png,image/jpeg,image/webp" form="ca-th-<?= $m ?>" data-thumb aria-label="Picture for month <?= $m ?>"><span><?= $th ? 'Change' : '+ Picture' ?></span></label><?php endif; ?>
        <input name="title[<?= $m ?>]" value="<?= e($stages[$m]['title']) ?>" placeholder="Stage title" maxlength="160" aria-label="Month <?= $m ?> title">
        <input name="summary[<?= $m ?>]" value="<?= e($stages[$m]['summary']) ?>" placeholder="What it covers (optional)" maxlength="255" aria-label="Month <?= $m ?> summary">
        <?php if ($paced && $th): ?><button class="ot-btn" type="submit" form="ca-thr-<?= $m ?>" aria-label="Remove the picture for month <?= $m ?>">Remove picture</button><?php endif; ?></div>
    <?php endfor; ?>
    <div><button class="btn btn-primary" type="submit">Save curriculum</button></div>
  </form>
  <?php if ($paced): for ($m = 1; $m <= 12; $m++): ?>
    <form method="post" enctype="multipart/form-data" id="ca-th-<?= $m ?>" hidden><?= csrf_field() ?><input type="hidden" name="_action" value="stage_thumb"><input type="hidden" name="month" value="<?= $m ?>"></form>
    <?php if (!empty($stages[$m]['thumbnail_url'])): ?><form method="post" id="ca-thr-<?= $m ?>" hidden data-confirm="Remove the picture for month <?= $m ?>?"><?= csrf_field() ?><input type="hidden" name="_action" value="stage_thumb_remove"><input type="hidden" name="month" value="<?= $m ?>"></form><?php endif; ?>
  <?php endfor; endif; ?>
  <style>
    .ca-class { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 16px 20px; }
    .ca-class > div { flex: 1 1 320px; display: grid; gap: 3px; }
    .ca-class small { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
    .ca-class b { font-size: 17px; }
    .ca-class span { font-size: 12.5px; color: var(--muted); }
    .ca-class form { margin: 0; }
    .ca-stage.paced { grid-template-columns: 90px 84px minmax(0, 1fr) minmax(0, 1.3fr) 124px; align-items: center; }
    .ca-done { font-style: normal; color: #067647; font-weight: 800; }
    .ca-thumb { position: relative; width: 84px; height: 54px; border-radius: 8px; border: 1.5px dashed var(--border-strong, #d0d0e8); background: var(--surface, #f5f5f7) center / cover; display: grid; place-items: center; cursor: pointer; overflow: hidden; }
    .ca-thumb input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .ca-thumb span { font-size: 11px; font-weight: 600; color: var(--accent, #2b22ff); }
    .ca-thumb[style*="background-image"] { border-style: solid; border-color: transparent; }
    .ca-thumb[style*="background-image"] span { color: #fff; background: rgba(10, 15, 44, .6); padding: 1px 6px; border-radius: 5px; opacity: 0; }
    .ca-thumb:hover span { opacity: 1; }
    @media (max-width: 760px) { .ca-stage.paced { grid-template-columns: 1fr 84px; } .ca-stage.paced > input { grid-column: 1 / -1; } }
  </style>
  <script>
    (function () {
      var f = document.getElementById('ca-stages'), dirty = false;
      if (f) f.addEventListener('input', function () { dirty = true; });
      document.querySelectorAll('[data-thumb]').forEach(function (i) { i.addEventListener('change', function () {
        if (!i.files.length) return;
        if (dirty && !confirm('You have unsaved curriculum changes. Upload the picture anyway? Those changes will be lost.')) { i.value = ''; return; }
        i.nextElementSibling.textContent = 'Uploading…'; i.form.submit();
      }); });
    })();
  </script>

<?php elseif ($tab === 'lessons'): $month = max(1, min(12, (int) query_param('month', '1'))); $ls = $byMonth[$month] ?? []; ?>
  <nav class="ca-months" aria-label="Months">
    <?php for ($m = 1; $m <= 12; $m++): ?><a href="<?= e(base_url(ltrim($here('lessons', ['month' => $m]), '/'))) ?>"<?= $m === $month ? ' aria-current="page"' : '' ?>><?= $m ?><small><?= count($byMonth[$m] ?? []) ?></small></a><?php endfor; ?>
  </nav>
  <div class="ld-two" style="margin-top:16px;">
    <div class="ld-panel" style="overflow:hidden;">
      <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Month <?= $month ?><?= $stages[$month]['title'] !== '' ? ' · ' . e($stages[$month]['title']) : '' ?></h2><p class="sub"><?= count($ls) ?> lesson<?= count($ls) === 1 ? '' : 's' ?>, in the order learners see them</p></div></div></div>
      <div class="ca-lessons">
        <?php if (!$ls): ?><p class="muted small" style="padding:0 22px 20px;">No lessons in this month yet. Upload the first one.</p><?php endif; ?>
        <?php foreach ($ls as $i => $l): ?>
          <div>
            <span class="k"><?= $l['kind'] === 'PDF' ? 'PDF' : 'Video' ?></span>
            <div style="min-width:0;"><b><?= $month ?>.<?= $i + 1 ?> &nbsp;<?= e($l['title']) ?></b><small><?= e($l['file_name'] ?: '') ?><?= $l['duration_label'] ? ' · ' . e($l['duration_label']) : '' ?></small>
              <details class="ca-ledit"><summary>Edit</summary><form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="lesson_rename"><input type="hidden" name="lesson" value="<?= (int) $l['id'] ?>"><input type="hidden" name="month" value="<?= $month ?>">
                <input name="title" maxlength="200" required value="<?= e($l['title']) ?>" aria-label="Title"><input name="duration" maxlength="40" value="<?= e($l['duration_label'] ?? '') ?>" placeholder="Length" aria-label="Length"><select name="to_month" aria-label="Month"><?php for ($mm = 1; $mm <= 12; $mm++): ?><option value="<?= $mm ?>"<?= $mm === $month ? ' selected' : '' ?>>Month <?= $mm ?></option><?php endfor; ?></select><button class="ot-btn" type="submit">Save</button></form></details>
            </div>
            <div class="acts">
              <a class="ot-btn" href="<?= e(base_url('campus-learn.php?campus=' . $campus['slug'] . '&view=lesson&lesson=' . $l['id'])) ?>" target="_blank" rel="noopener">View</a>
              <?php foreach (['up' => '&uarr;', 'down' => '&darr;'] as $dir => $sym): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="lesson_move"><input type="hidden" name="lesson" value="<?= (int) $l['id'] ?>"><input type="hidden" name="dir" value="<?= $dir ?>"><button class="ot-btn" type="submit" aria-label="Move <?= $dir ?>"><?= $sym ?></button></form>
              <?php endforeach; ?>
              <form method="post" data-confirm="Remove this lesson and its file? Learners lose access to it."><?= csrf_field() ?><input type="hidden" name="_action" value="lesson_delete"><input type="hidden" name="lesson" value="<?= (int) $l['id'] ?>"><button class="ot-btn" type="submit" style="color:var(--dash-bad, #dc2626);">Remove</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="ld-panel ca-form" data-upload>
      <?= csrf_field() ?><input type="hidden" name="_action" value="lesson_add"><input type="hidden" name="month" value="<?= $month ?>">
      <h2 style="margin:0; font-size:15px;">Add a lesson to month <?= $month ?></h2>
      <label>Title<input name="title" maxlength="200" required></label>
      <div class="ca-f2">
        <label>Type<select name="kind"><option value="VIDEO">Video</option><option value="PDF">PDF guide</option></select></label>
        <label>Length <small>Optional, e.g. 18 min or 4 pages</small><input name="duration" maxlength="40"></label>
      </div>
      <label>File <small>Video up to 3GB (MP4 is best) · PDF up to 500MB</small><input type="file" name="file" accept="video/*,application/pdf" required></label>
      <div><button class="btn btn-primary" type="submit" data-upload-btn>Upload lesson</button></div>
      <p class="muted small" data-upload-note hidden style="margin:0;">Uploading… keep this page open until it finishes.</p>
    </form>
  </div>

<?php elseif ($tab === 'live'):
    $up = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at', [$id]);
    $past = db_all('SELECT * FROM campus_live_classes WHERE campus_id = ? AND starts_at < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY starts_at DESC LIMIT 10', [$id]);
?>
  <div class="ld-two" style="margin-top:16px;">
    <div class="ld-panel" style="overflow:hidden;">
      <div class="ld-card" style="padding-bottom:12px;"><div class="ld-chead"><div><h2>Coming up</h2><p class="sub">Members see these with a Join button</p></div></div></div>
      <div class="ca-list" style="padding:0 22px 14px;">
        <?php if (!$up): ?><p class="muted small">No live classes scheduled.</p><?php endif; ?>
        <?php foreach (array_merge($up, $past) as $l): $isPast = strtotime($l['starts_at']) < time() - 10800; ?>
          <div><div class="ca-when"><small><?= e(date('M', strtotime($l['starts_at']))) ?></small><b><?= e(date('j', strtotime($l['starts_at']))) ?></b></div>
            <div style="min-width:0;"><b class="t"><?= e($l['title']) ?></b><small><?= e(date('D j M, g:i a', strtotime($l['starts_at']))) ?> · <?= (int) $l['duration_minutes'] ?> min<?= $l['join_url'] ? '' : ' · no link yet' ?><?= $isPast ? ' · done' : '' ?></small></div>
            <form method="post" data-confirm="Remove this live class?"><?= csrf_field() ?><input type="hidden" name="_action" value="live_delete"><input type="hidden" name="live" value="<?= (int) $l['id'] ?>"><button class="ot-btn" type="submit">Remove</button></form></div>
        <?php endforeach; ?>
      </div>
    </div>
    <form method="post" class="ld-panel ca-form">
      <?= csrf_field() ?><input type="hidden" name="_action" value="live_add">
      <h2 style="margin:0; font-size:15px;">Schedule a live class</h2>
      <label>Topic<input name="title" maxlength="200" required placeholder="e.g. Position sizing on real charts"></label>
      <div class="ca-f3">
        <label>Date<input type="date" name="date" required value="<?= e(date('Y-m-d')) ?>"></label>
        <label>Start time<input type="time" name="time" required value="20:00"></label>
        <label>Minutes<input type="number" name="minutes" value="90" min="15" max="300"></label>
      </div>
      <label>Join link <small>Paste the Zoom or Google Meet link</small><input name="url" type="url" placeholder="https://meet.google.com/..."></label>
      <div><button class="btn btn-primary" type="submit">Add live class</button></div>
      <p class="muted small" style="margin:0;">Upload the recording afterwards as a video lesson in the current month.</p>
    </form>
  </div>

<?php elseif ($tab === 'members'):
    $list = db_all(
        'SELECT m.*, u.name, u.email, u.phone,
                (SELECT COUNT(*) FROM campus_lesson_progress p JOIN campus_lessons l ON l.id = p.lesson_id WHERE p.user_id = m.user_id AND l.campus_id = m.campus_id) AS done
         FROM campus_memberships m JOIN users u ON u.id = m.user_id WHERE m.campus_id = ? ORDER BY m.access_until DESC LIMIT 300',
        [$id]
    );
?>
  <div class="ld-two" style="margin-top:16px;">
    <div class="ld-panel" style="overflow:hidden;">
      <div class="ca-tbl"><table class="ca-table">
        <thead><tr><th>Member</th><th>Plan</th><th>Access until</th><th class="num">Lessons done</th></tr></thead>
        <tbody>
          <?php if (!$list): ?><tr><td colspan="4" class="muted">No members yet.</td></tr><?php endif; ?>
          <?php foreach ($list as $r): $openR = strtotime($r['access_until']) > time(); ?>
            <tr>
              <td><a href="<?= e(base_url('dashboard/admin/user.php?id=' . $r['user_id'])) ?>" style="font-weight:600;"><?= e($r['name']) ?></a><small><?= e($r['email']) ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></small></td>
              <td><?= $r['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?><small>Joined <?= e(date('j M Y', strtotime($r['started_at']))) ?></small></td>
              <td><span class="ca-pill <?= $openR ? (strtotime($r['access_until']) - time() < 7 * 86400 ? 'warn' : 'good') : 'mute' ?>"><?= e(date('j M Y', strtotime($r['access_until']))) ?><?= $openR ? '' : ' · ended' ?></span></td>
              <td class="num"><?= (int) $r['done'] ?> / <?= $lessonTotal ?><?= $r['certificate_code'] ? '<small>Certificate issued</small>' : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <form method="post" class="ld-panel ca-form">
      <?= csrf_field() ?><input type="hidden" name="_action" value="grant">
      <h2 style="margin:0; font-size:15px;">Give access without payment</h2>
      <p class="muted small" style="margin:0;">For example, a free month for an existing student. Adds time on top of any access they already have, and is written to the audit log.</p>
      <label>Their account email<input type="email" name="email" required></label>
      <label>Length<select name="plan"><option value="MONTHLY">30 days</option><option value="YEARLY">12 months</option></select></label>
      <label>Reason<input name="reason" required minlength="5" maxlength="200" placeholder="e.g. Free first month for existing students"></label>
      <div><button class="btn btn-primary" type="submit">Give access</button></div>
    </form>
  </div>
<?php endif; ?>
<style>.ca-ledit summary { cursor: pointer; font-size: 12px; font-weight: 600; color: var(--accent, #2b22ff); margin-top: 4px; } .ca-ledit form { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px; } .ca-ledit input, .ca-ledit select { height: 34px; font-size: 13px; padding: 0 10px; } .ca-ledit input[name=title] { flex: 1 1 200px; } .ca-ledit input[name=duration] { width: 110px; }</style>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });
document.querySelectorAll('form[data-upload]').forEach(function (f) { f.addEventListener('submit', function () { var b = f.querySelector('[data-upload-btn]'); if (b) { b.disabled = true; b.textContent = 'Uploading…'; } var n = f.querySelector('[data-upload-note]'); if (n) n.hidden = false; }); });
</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
