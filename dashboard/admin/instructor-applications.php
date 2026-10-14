<?php
/**
 * Admin: applications to teach a campus (from teach.php), as a "faculty intake" desk. A dark console
 * shows the applications waiting and every campus's professor seat (filled, or open with how many
 * applicants want it). Below, each application can be noted, shortlisted, declined, or accepted, which
 * makes the applicant the professor of an existing campus or of a new draft campus from their proposal.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-apps.css. Motion: assets/js/admin-pay.js.
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

// Applicants still in play (new or shortlisted) per campus, and for brand-new campus proposals.
$waitingFor = array_map('intval', array_column(db_all("SELECT campus_id, COUNT(*) AS n FROM instructor_applications WHERE status IN ('NEW', 'SHORTLISTED') AND campus_id IS NOT NULL GROUP BY campus_id"), 'n', 'campus_id'));
$proposals = (int) (db_one("SELECT COUNT(*) AS n FROM instructor_applications WHERE status IN ('NEW', 'SHORTLISTED') AND campus_id IS NULL")['n'] ?? 0);
$seatsFilled = count(array_filter($campuses, fn($c) => (bool) $c['professor_name']));

$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
$look = [];
foreach ($campuses as $k => $c) $look[(int) $c['id']] = ['photo' => campus_photo($c), 'color' => $colors[$k % count($colors)], 'icon' => $c['icon'] ?: 'book'];
$thumb = function (?int $campusId, string $class) use ($look): string {
    $l = $campusId !== null ? ($look[$campusId] ?? null) : null;
    if (!$l) return '<span class="' . $class . ' badge new" aria-hidden="true">+</span>';
    return $l['photo']
        ? '<span class="' . $class . '" style="background-image:url(\'' . e($l['photo']) . '\')"></span>'
        : '<span class="' . $class . ' badge" style="background:' . e($l['color']) . '">' . campus_icon($l['icon'], 1.8) . '</span>';
};
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2))));
$appsUrl = fn(string $status = '') => base_url('dashboard/admin/instructor-applications.php' . ($status ? '?status=' . $status : ''));

$pageTitle = 'Teach Applications — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-apps.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx ia" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Teach applications</h1><p>People who want to lead a campus. Accepting one makes them its professor, on <?= e(format_share(CAMPUS_PROFESSOR_SHARE_DEFAULT)) ?> of every payment.</p></div>
    <div class="acx-acts"><a class="acx-btn" href="<?= e(base_url('teach.php')) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>View the Teach page</a></div>
  </div>

  <section class="lc-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $counts['NEW'] ? '' : 'calm' ?>"><i></i><?= $counts['NEW'] ? 'Waiting for you' : 'All read' ?></span>
      <div class="lc-big"><b><span data-scramble="<?= $counts['NEW'] ?>"><?= $counts['NEW'] ?></span></b><span>new application<?= $counts['NEW'] === 1 ? '' : 's' ?><br>to read</span></div>
      <p class="lc-copy"><?= $counts['NEW'] ? 'Read each one, call the strongest, then make them professor of a campus.' : 'New applications from the Teach page appear here straight away.' ?></p>
      <div class="lc-stats">
        <div><span>All applications</span><b data-scramble="<?= $counts[''] ?>"><?= $counts[''] ?></b></div>
        <div><span>Shortlisted</span><b data-scramble="<?= $counts['SHORTLISTED'] ?>"><?= $counts['SHORTLISTED'] ?></b></div>
        <div><span>Made professor</span><b class="lime" data-scramble="<?= $counts['ACCEPTED'] ?>"><?= $counts['ACCEPTED'] ?></b></div>
        <div><span>Declined</span><b data-scramble="<?= $counts['DECLINED'] ?>"><?= $counts['DECLINED'] ?></b></div>
      </div>
    </div>
    <div class="lc-rx">
      <div class="lc-rx-h"><b>Faculty seats</b><span><?= $seatsFilled ?> of <?= count($campuses) ?> filled</span></div>
      <?php foreach ($campuses as $li => $c): $cid = (int) $c['id']; $n = $waitingFor[$cid] ?? 0; $filled = (bool) $c['professor_name']; ?>
        <a class="ia-seat <?= $filled ? 'filled' : 'open' ?>" href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $cid . '&tab=professor')) ?>" style="--l:<?= $li ?>">
          <span class="nm"><?= $thumb($cid, 'pp-dot') ?><?= e($c['name']) ?></span>
          <span class="who"><?= $filled ? '<i></i>' . e($c['professor_name']) : '<i></i>Seat open' ?></span>
          <span class="n <?= $n ? 'on' : '' ?>"><?= $n ? $n . ' applicant' . ($n === 1 ? '' : 's') : '—' ?></span>
        </a>
      <?php endforeach; ?>
      <div class="ia-seat proposal">
        <span class="nm"><?= $thumb(null, 'pp-dot') ?>New campus proposals</span>
        <span class="who"><i></i>Ideas for campuses we don't have yet</span>
        <span class="n <?= $proposals ? 'on' : '' ?>"><?= $proposals ? $proposals . ' waiting' : '—' ?></span>
      </div>
    </div>
  </section>

  <div class="ia-bar acx-slam" style="--i:2">
    <h2>Applications</h2>
    <nav class="lc-chips ia-chips" aria-label="Filter applications">
      <?php foreach (['' => 'All'] + INSTRUCTOR_APP_STATUSES as $k => $label): ?>
        <a href="<?= e($appsUrl($k)) ?>" class="<?= $filter === $k ? 'on' : '' ?>" <?= $filter === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?> <em><?= (int) $counts[$k] ?></em></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <?php if (!$apps): ?>
    <section class="acx-sec acx-slam ia-empty" style="--i:3"><p>No applications here yet. Share <a href="<?= e(base_url('teach.php')) ?>"><?= e(base_url('teach.php')) ?></a> with people you would like to teach.</p></section>
  <?php endif; ?>

  <?php foreach ($apps as $j => $a): $wa = whatsapp_number($a['phone']); $target = $a['campus_name'] ? $a['campus_name'] . ' Campus' : 'New campus: ' . $a['proposed_campus']; $open = $a['status'] !== 'ACCEPTED' && $a['status'] !== 'DECLINED'; ?>
    <article class="acx-sec ia-card acx-whip st-<?= strtolower($a['status']) ?>" id="app-<?= (int) $a['id'] ?>" style="--j:<?= min($j, 10) ?>;--i:3">
      <header class="ia-head">
        <span class="ia-av"><?= e($initials($a['name'])) ?></span>
        <div class="ia-id"><b><?= e($a['name']) ?></b><small><?= e($a['headline']) ?></small></div>
        <div class="ia-meta">
          <span class="ia-st <?= strtolower($a['status']) ?>"><?= e(INSTRUCTOR_APP_STATUSES[$a['status']]) ?></span>
          <small><?= e(time_ago($a['created_at'])) ?></small>
        </div>
      </header>
      <div class="ia-body">
        <div class="ia-facts">
          <div class="ia-target"><?= $thumb($a['campus_id'] ? (int) $a['campus_id'] : null, 'ph') ?><span><small>Wants to teach</small><b><?= e($target) ?></b></span></div>
          <dl>
            <div><dt>Email</dt><dd><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></dd></div>
            <div><dt>Phone</dt><dd><?= e($a['phone']) ?></dd></div>
            <?php if ($a['links']): ?><div><dt>Work</dt><dd><?= e($a['links']) ?></dd></div><?php endif; ?>
            <?php if ($a['sample_url']): ?><div><dt>Teaching sample</dt><dd><a href="<?= e($a['sample_url']) ?>" target="_blank" rel="noopener nofollow">Watch</a></dd></div><?php endif; ?>
          </dl>
        </div>
        <div class="ia-text">
          <h3>Experience</h3><p><?= e($a['experience']) ?></p>
          <?php if ($a['results']): ?><h3>Results</h3><p><?= e($a['results']) ?></p><?php endif; ?>
        </div>
      </div>
      <form method="post" class="ia-note"><?= csrf_field() ?><input type="hidden" name="_action" value="note"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>">
        <label for="note-<?= (int) $a['id'] ?>">Your notes (private)</label>
        <div><input id="note-<?= (int) $a['id'] ?>" name="note" value="<?= e($a['admin_note'] ?? '') ?>" placeholder="e.g. Called on Tuesday, strong results, wants Thursdays"><button type="submit">Save</button></div>
      </form>
      <footer class="ia-acts">
        <?php if ($wa): ?><a class="ia-btn wa" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode('Hi ' . campus_first_name($a['name']) . ', this is Obin Academy. Thank you for applying to teach the ' . ($a['campus_name'] ?: $a['proposed_campus']) . ' campus. When is a good time for a short call?')) ?>">WhatsApp</a><?php endif; ?>
        <?php if ($a['status'] === 'NEW'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="_action" value="shortlist"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>"><button class="ia-btn" type="submit">Shortlist</button></form>
        <?php endif; ?>
        <?php if ($open): ?>
          <form method="post" data-confirm="Decline <?= e($a['name']) ?> and tell them by email?"><?= csrf_field() ?><input type="hidden" name="_action" value="decline"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>"><input type="hidden" name="tell" value="1"><button class="ia-btn ghost" type="submit">Decline</button></form>
          <form method="post" class="ia-accept" data-confirm="Make <?= e($a['name']) ?> the professor of the chosen campus? They are emailed straight away.">
            <?= csrf_field() ?><input type="hidden" name="_action" value="accept"><input type="hidden" name="app" value="<?= (int) $a['id'] ?>">
            <select id="camp-<?= (int) $a['id'] ?>" name="campus" aria-label="Campus they will teach">
              <?php if ($a['proposed_campus']): ?><option value="new">New campus: <?= e($a['proposed_campus']) ?></option><?php endif; ?>
              <?php foreach ($campuses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $a['campus_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?><?= $c['professor_name'] ? ' (now: ' . e($c['professor_name']) . ')' : '' ?></option><?php endforeach; ?>
            </select>
            <button class="ia-make" type="submit">Make professor <span>→</span></button>
          </form>
        <?php elseif ($a['status'] === 'ACCEPTED' && $a['campus_id']): ?>
          <a class="ia-btn" href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . $a['campus_id'] . '&tab=professor')) ?>">Open their campus →</a>
        <?php endif; ?>
      </footer>
    </article>
  <?php endforeach; ?>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
