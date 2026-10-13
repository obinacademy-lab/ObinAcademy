<?php
/**
 * Admin: applications to teach a campus (from teach.php). Shortlist, decline, or accept, which makes
 * the applicant the professor of an existing campus or of a new draft campus from their proposal.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/instructor_applications.php';
$user = require_role(['ADMIN']);

if (!campuses_ready() || !instructor_applications_ready()) {
    $pageTitle = 'Teach Applications — Admin — Obin Academy';
    require __DIR__ . '/../../includes/dashboard_header.php';
    echo '<div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;"><p class="muted">Applications are being set up. Check back in a few minutes.</p></div>';
    require __DIR__ . '/../../includes/dashboard_footer.php';
    exit;
}

$filter = query_param('status');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $app = db_one('SELECT * FROM instructor_applications WHERE id = ?', [(int) post('app')]);
    $back = '/dashboard/admin/instructor-applications.php' . ($filter ? '?status=' . urlencode($filter) : '') . '#app-' . (int) post('app');
    if (!$app) redirect($back);
    $a = post('_action');
    if ($a === 'note') {
        db_run('UPDATE instructor_applications SET admin_note = ? WHERE id = ?', [mb_substr(trim(post('note')), 0, 2000) ?: null, $app['id']]);
        flash_set('success', 'Note saved.');
    } elseif ($a === 'shortlist' || $a === 'decline') {
        $to = $a === 'shortlist' ? 'SHORTLISTED' : 'DECLINED';
        db_run('UPDATE instructor_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?', [$to, $user['id'], $app['id']]);
        if ($to === 'DECLINED' && post('tell') === '1') {
            resend_send($app['email'], 'Your application to teach at Obin Academy', campus_email_html(
                'Thank you, ' . campus_first_name($app['name']),
                'Thank you for applying to teach at Obin Academy. We are not able to offer you a campus right now, but we keep every application and may contact you as new campuses open.',
                'See the campuses', base_url('index.php#campuses')
            ), ['reply_to' => $user['email']]);
        }
        log_admin_action((int) $user['id'], $user['name'], 'instructor.' . strtolower($to), 'Teach application', $app['name']);
        flash_set('success', $app['name'] . ($to === 'SHORTLISTED' ? ' is shortlisted. Call or WhatsApp them to talk it through.' : ' was declined' . (post('tell') === '1' ? ' and told by email.' : '.')));
    } elseif ($a === 'accept') {
        $target = post('campus');
        $campusId = $target === 'new' ? null : (int) $target;
        if ($campusId !== null && !get_campus($campusId)) { flash_set('error', 'Choose a campus.'); redirect($back); }
        if ($target === 'new' && !$app['proposed_campus']) { flash_set('error', 'This applicant did not propose a new campus. Choose an existing one.'); redirect($back); }
        $campus = accept_instructor_application($app, $campusId, (int) $user['id']);
        log_admin_action((int) $user['id'], $user['name'], 'instructor.accepted', 'Teach application', $app['name'], 'Professor of ' . $campus['name']);
        $linked = !empty($campus['professor_user_id']);
        resend_send($app['email'], 'Welcome to Obin Academy, Professor ' . campus_first_name($app['name']), campus_email_html(
            'Welcome aboard, ' . campus_first_name($app['name']),
            'You are the professor of the ' . e($campus['name']) . ' Campus. You earn ' . e(format_share(campus_professor_share($campus))) . ' of every member payment, sent to your mobile money. '
                . ($linked ? 'Your pay and every payment show in your dashboard under My Campus Pay.' : 'Create your Obin Academy account with this email address so you can see your pay and teach in the campus.'),
            $linked ? 'Open your dashboard' : 'Create your account', $linked ? base_url('dashboard/professor.php') : base_url('signup.php')
        ), ['reply_to' => $user['email']]);
        flash_set('success', $app['name'] . ' is now the professor of the ' . $campus['name'] . ' Campus' . ($campusId === null ? ' (a new draft campus)' : '') . '. ' . ($linked ? 'Their account is linked.' : 'They have no account yet; link it on the campus Professor tab once they sign up.'));
        redirect('/dashboard/admin/campus-edit.php?id=' . $campus['id'] . '&tab=professor');
    }
    redirect($back);
}

$apps = get_instructor_applications($filter);
$counts = ['' => 0] + array_fill_keys(array_keys(INSTRUCTOR_APP_STATUSES), 0);
foreach (db_all('SELECT status, COUNT(*) AS n FROM instructor_applications GROUP BY status') as $r) { $counts[$r['status']] = (int) $r['n']; $counts[''] += (int) $r['n']; }
$campuses = get_all_campuses();
$pills = ['NEW' => 'warn', 'SHORTLISTED' => 'good', 'ACCEPTED' => 'good', 'DECLINED' => 'mute'];

$pageTitle = 'Teach Applications — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Teach Applications</h1>
    <p class="muted" style="margin-top:6px;">People who want to lead a campus. Accepting one makes them its professor, on <?= e(format_share(CAMPUS_PROFESSOR_SHARE_DEFAULT)) ?> of every payment.</p>
  </div>
  <div class="row gap-2 wrap"><a class="ld-btn" href="<?= e(base_url('teach.php')) ?>" target="_blank" rel="noopener">View the Teach page</a></div>
</div>

<nav class="ca-tabs" aria-label="Filter applications">
  <?php foreach (['' => 'All'] + INSTRUCTOR_APP_STATUSES as $k => $label): ?>
    <a href="<?= e(base_url('dashboard/admin/instructor-applications.php' . ($k ? '?status=' . $k : ''))) ?>"<?= $filter === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="muted">(<?= (int) $counts[$k] ?>)</span></a>
  <?php endforeach; ?>
</nav>

<?php if (!$apps): ?>
  <div class="ld-panel" style="margin-top:16px; padding:40px 22px; text-align:center;"><p class="muted">No applications here yet. Share <a href="<?= e(base_url('teach.php')) ?>"><?= e(base_url('teach.php')) ?></a> with people you would like to teach.</p></div>
<?php endif; ?>

<div class="ia-list">
  <?php foreach ($apps as $a): $wa = whatsapp_number($a['phone']); $target = $a['campus_name'] ? $a['campus_name'] . ' Campus' : 'New: ' . $a['proposed_campus']; ?>
    <article class="ld-panel ia-card" id="app-<?= (int) $a['id'] ?>">
      <header class="ia-head">
        <span class="ia-av"><?= e(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?></span>
        <div style="min-width:0;">
          <b><?= e($a['name']) ?></b>
          <small><?= e($a['headline']) ?></small>
        </div>
        <div class="ia-meta">
          <span class="ca-pill <?= $pills[$a['status']] ?>"><span class="dot"></span><?= e(INSTRUCTOR_APP_STATUSES[$a['status']]) ?></span>
          <small><?= e(time_ago($a['created_at'])) ?></small>
        </div>
      </header>
      <div class="ia-body">
        <dl class="ia-facts">
          <div><dt>Wants to teach</dt><dd><?= e($target) ?></dd></div>
          <div><dt>Email</dt><dd><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></dd></div>
          <div><dt>Phone</dt><dd><?= e($a['phone']) ?></dd></div>
          <?php if ($a['links']): ?><div><dt>Work</dt><dd><?= e($a['links']) ?></dd></div><?php endif; ?>
          <?php if ($a['sample_url']): ?><div><dt>Teaching sample</dt><dd><a href="<?= e($a['sample_url']) ?>" target="_blank" rel="noopener nofollow">Watch</a></dd></div><?php endif; ?>
        </dl>
        <div class="ia-text">
          <h3>Experience</h3><p><?= e($a['experience']) ?></p>
          <?php if ($a['results']): ?><h3>Results</h3><p><?= e($a['results']) ?></p><?php endif; ?>
        </div>
      </div>
      <form method="post" class="ia-note"><?= csrf_field() ?><input type="hidden" name="_action" value="note"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>">
        <label for="note-<?= (int) $a['id'] ?>" class="muted small">Your notes (private)</label>
        <div class="row gap-2"><input id="note-<?= (int) $a['id'] ?>" name="note" value="<?= e($a['admin_note'] ?? '') ?>" placeholder="e.g. Called on Tuesday, strong results, wants Thursdays"><button class="ot-btn" type="submit">Save</button></div>
      </form>
      <footer class="ia-acts">
        <?php if ($wa): ?><a class="ot-btn wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . campus_first_name($a['name']) . ', this is Obin Academy. Thank you for applying to teach the ' . ($a['campus_name'] ?: $a['proposed_campus']) . ' campus. When is a good time for a short call?')) ?>">WhatsApp</a><?php endif; ?>
        <?php if ($a['status'] === 'NEW'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="shortlist"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>"><button class="ot-btn" type="submit">Shortlist</button></form>
        <?php endif; ?>
        <?php if ($a['status'] !== 'ACCEPTED' && $a['status'] !== 'DECLINED'): ?>
          <form method="post" data-confirm="Decline <?= e($a['name']) ?> and tell them by email?"><?= csrf_field() ?><input type="hidden" name="_action" value="decline"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>"><input type="hidden" name="tell" value="1"><button class="ot-btn" type="submit">Decline</button></form>
          <form method="post" class="ia-accept" data-confirm="Make <?= e($a['name']) ?> the professor of the chosen campus? They are emailed straight away.">
            <?= csrf_field() ?><input type="hidden" name="_action" value="accept"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>">
            <select id="camp-<?= (int) $a['id'] ?>" name="campus" aria-label="Campus they will teach">
              <?php if ($a['proposed_campus']): ?><option value="new">New campus: <?= e($a['proposed_campus']) ?></option><?php endif; ?>
              <?php foreach ($campuses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $a['campus_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['professor_name'] ? ' (now: ' . e($c['professor_name']) . ')' : '' ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Make professor</button>
          </form>
        <?php elseif ($a['status'] === 'ACCEPTED' && $a['campus_id']): ?>
          <a class="ld-view" href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $a['campus_id'] . '&tab=professor')) ?>">Open their campus</a>
        <?php endif; ?>
      </footer>
    </article>
  <?php endforeach; ?>
</div>
<script>document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
