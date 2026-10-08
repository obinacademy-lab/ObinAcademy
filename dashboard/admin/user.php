<?php
/**
 * Admin: one person in full — contact, courses they can open, payments, plans, last sign-in — and a
 * support tool to give them access to a course (for example after a payment problem was sorted
 * out outside the app). Every grant is written to the audit log with the reason given.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/admin_payments.php';
$user = require_role(['ADMIN']);

$id = (int) query_param('id');
$u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) { http_response_code(404); exit('User not found'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('_action') === 'grant') {
    csrf_verify();
    $courseId = (int) post('courseId');
    $reason = post('reason');
    $course = db_one("SELECT * FROM courses WHERE id = ? AND status = 'PUBLISHED'", [$courseId]);
    if (!$course) {
        flash_set('error', 'Choose a published course.');
    } elseif (strlen($reason) < 5) {
        flash_set('error', 'Write a short reason (at least 5 characters) so there is a record of why access was given.');
    } elseif ((int) $course['creator_id'] === $id) {
        flash_set('error', 'A creator already owns their own course.');
    } elseif (db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$id, $courseId])) {
        flash_set('error', $u['name'] . ' already has this course.');
    } else {
        $expires = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);
        db_insert('INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)', [$id, $courseId, $expires]);
        log_admin_action((int) $user['id'], $user['name'], 'enrollment.granted', 'Enrollment', $u['name'] . ' → ' . $course['title'], mb_substr('Reason: ' . $reason, 0, 480));
        flash_set('success', 'Done. ' . $u['name'] . ' can now open "' . $course['title'] . '". No payment or creator earning was recorded.');
    }
    redirect('/dashboard/admin/user.php?id=' . $id);
}

$lastLogin = db_one('SELECT logged_in_at, device_type, city, country FROM login_log WHERE user_id = ? ORDER BY logged_in_at DESC LIMIT 1', [$id]);
$enrollments = db_all(
    "SELECT e.*, c.title, c.slug, COALESCE(cr.school_name, cr.name) AS creator_label
     FROM enrollments e JOIN courses c ON c.id = e.course_id JOIN users cr ON cr.id = c.creator_id
     WHERE e.user_id = ? ORDER BY e.enrolled_at DESC",
    [$id]
);
$payments = db_all(
    'SELECT p.*, u.name AS payer_name, u.email AS payer_email, c.title AS course_title, b.title AS bundle_title, COALESCE(sc.school_name, sc.name) AS school_label '
    . ADMIN_PAYMENT_FROM . ' WHERE p.user_id = ? ORDER BY p.created_at DESC, p.id DESC LIMIT 20',
    [$id]
);
$plans = db_all(
    "SELECT ip.*, c.title AS course_title FROM installment_plans ip JOIN courses c ON c.id = ip.course_id WHERE ip.learner_id = ? ORDER BY ip.created_at DESC",
    [$id]
);
$subs = db_all(
    "SELECT ss.*, COALESCE(cr.school_name, cr.name) AS creator_label FROM school_subscriptions ss JOIN users cr ON cr.id = ss.creator_id WHERE ss.learner_id = ? ORDER BY ss.current_period_ends_at DESC",
    [$id]
);
$spent = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM payments WHERE user_id = ? AND status = 'SUCCESS'", [$id])['n'] ?? 0);
$failedCount = (int) db_one("SELECT COUNT(*) AS n FROM payments WHERE user_id = ? AND status = 'FAILED'", [$id])['n'];
$courseCount = (int) db_one('SELECT COUNT(*) AS n FROM courses WHERE creator_id = ?', [$id])['n'];

$grantable = db_all(
    "SELECT c.id, c.title FROM courses c
     WHERE c.status = 'PUBLISHED' AND c.creator_id <> ? AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.user_id = ? AND e.course_id = c.id)
     ORDER BY c.title",
    [$id, $id]
);

$wa = whatsapp_number($u['phone']);
$badge = ['SUCCESS' => 'badge-published', 'FAILED' => 'badge-rejected', 'PENDING' => 'badge-pending', 'ACTIVE' => 'badge-published', 'GRACE' => 'badge-pending', 'COMPLETED' => 'badge-draft', 'DEFAULTED' => 'badge-rejected', 'EXPIRED' => 'badge-rejected', 'CANCELED' => 'badge-draft'];
$roleLabel = ['LEARNER' => 'Learner', 'CREATOR' => 'Creator', 'ADMIN' => 'Admin'][$u['role']] ?? $u['role'];

$pageTitle = $u['name'] . ' — User — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<p class="small" style="margin-bottom:10px;"><a href="<?= e(base_url('dashboard/admin/users.php')) ?>" style="color:var(--muted);">&larr; All users</a></p>

<div class="card card-pad cm-head">
  <span class="cm-avatar cm-avatar-lg"><?php if (!empty($u['avatar_url'])): ?><img src="<?= e(asset_src($u['avatar_url'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?><?php endif; ?></span>
  <div style="min-width:0; flex:1;">
    <h1 class="h2" style="margin:0;"><?= e($u['name']) ?> <span class="badge badge-draft" style="vertical-align:middle;"><?= e($roleLabel) ?></span></h1>
    <p class="muted" style="margin-top:4px;">Joined <?= e(format_date($u['created_at'])) ?> · <?= $lastLogin ? 'last signed in ' . e(time_ago($lastLogin['logged_in_at'])) . ' (' . e(ucfirst($lastLogin['device_type'])) . ($lastLogin['city'] ? ', ' . e($lastLogin['city']) : '') . ')' : 'never signed in' ?></p>
    <p class="small muted" style="margin-top:6px;">
      <a href="mailto:<?= e($u['email']) ?>" style="color:inherit;"><?= e($u['email']) ?></a>
      <?php if (!empty($u['phone'])): ?>· <?= e($u['phone']) ?><?= $wa ? ' · <a href="https://wa.me/' . e($wa) . '" target="_blank" rel="noopener noreferrer" style="color:var(--dash-good);">WhatsApp</a>' : '' ?><?php endif; ?>
    </p>
  </div>
  <?php if ($courseCount > 0 || $u['role'] === 'CREATOR'): ?>
    <a class="btn btn-outline btn-sm" href="<?= e(base_url('dashboard/admin/creator.php?id=' . $id)) ?>">Open creator page</a>
  <?php endif; ?>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:18px; gap:14px;">
  <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= e(format_money($spent)) ?></span><span class="mini-stat-label">Paid in total</span></div>
  <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= count($enrollments) ?></span><span class="mini-stat-label">Courses they can open</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= $failedCount ?></span><span class="mini-stat-label">Failed payment attempts</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= count($plans) + count($subs) ?></span><span class="mini-stat-label">Payment plans and subscriptions</span></div>
</div>

<h3 class="dash-section-label" style="margin-top:28px;">Give access to a course</h3>
<form method="post" class="card card-pad" style="margin-top:12px; max-width:720px;" data-confirm="Give this person free access to the selected course? This is recorded in the audit log.">
  <?= csrf_field() ?><input type="hidden" name="_action" value="grant">
  <p class="small muted" style="margin:0 0 12px;">For support cases, such as a payment that was confirmed outside the app. It gives access only: no payment and no creator earning are recorded.</p>
  <?php if (!$grantable): ?>
    <p class="muted">There is no published course left to give this person.</p>
  <?php else: ?>
    <div class="row gap-2 wrap" style="align-items:flex-end;">
      <label class="field" style="flex:1 1 280px; margin:0;"><span class="small" style="font-weight:700;">Course</span>
        <select name="courseId" required><option value="">Choose a course</option><?php foreach ($grantable as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e(mb_strimwidth($g['title'], 0, 70, '…')) ?></option><?php endforeach; ?></select></label>
      <label class="field" style="flex:1 1 280px; margin:0;"><span class="small" style="font-weight:700;">Why</span>
        <input name="reason" type="text" required minlength="5" maxlength="300" placeholder="e.g. Paid by MTN, confirmed on statement ref 8841"></label>
      <button class="btn btn-primary" type="submit">Give access</button>
    </div>
  <?php endif; ?>
</form>

<h3 class="dash-section-label" style="margin-top:28px;">Courses they can open</h3>
<?php if (!$enrollments): ?>
  <div class="card card-pad" style="margin-top:12px; border-style:dashed; text-align:center;"><p class="muted">No courses yet.</p></div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>Course</th><th>Since</th><th class="num">Progress</th><th>Access until</th></tr></thead>
      <tbody>
        <?php foreach ($enrollments as $en): ?>
          <tr>
            <td data-label="Course"><div class="cm-course"><strong><?= e($en['title']) ?></strong><small>by <?= e($en['creator_label']) ?><?= $en['source'] === 'SUBSCRIPTION' ? ' · via subscription' : '' ?></small></div></td>
            <td data-label="Since"><?= e(format_date($en['enrolled_at'])) ?></td>
            <td class="num" data-label="Progress"><?= round((float) $en['progress']) ?>%</td>
            <td data-label="Access until"><?= $en['expires_at'] ? e(format_date($en['expires_at'])) : 'No end date' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h3 class="dash-section-label" style="margin-top:28px;">Payments</h3>
<?php if (!$payments): ?>
  <div class="card card-pad" style="margin-top:12px; border-style:dashed; text-align:center;"><p class="muted">No payments from this person.</p></div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>When</th><th>For</th><th class="num">Amount</th><th>Phone</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td data-label="When"><?= e(format_date($p['created_at'])) ?><small class="cm-sub">#<?= (int) $p['id'] ?></small></td>
            <td data-label="For"><?= e(admin_payment_what($p)) ?></td>
            <td class="num" data-label="Amount"><?= e(format_money((float) $p['amount'])) ?></td>
            <td data-label="Phone"><?= e($p['phone']) ?></td>
            <td data-label="Status"><span class="badge <?= $badge[$p['status']] ?? 'badge-draft' ?>"><?= e(ucfirst(strtolower($p['status']))) ?></span><?php if ($p['status_message'] && $p['status'] === 'FAILED'): ?><small class="cm-sub" style="white-space:normal; max-width:220px;"><?= e(mb_strimwidth($p['status_message'], 0, 90, '…')) ?></small><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small muted" style="margin-top:8px;"><a href="<?= e(base_url('dashboard/admin/payments.php?q=' . urlencode($u['email']))) ?>" style="color:var(--accent);">See all of this person's payments in Payments</a></p>
<?php endif; ?>

<?php if ($plans || $subs): ?>
  <h3 class="dash-section-label" style="margin-top:28px;">Plans and subscriptions</h3>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>What</th><th>Progress</th><th>Next date</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($plans as $pl): ?>
          <tr>
            <td data-label="What">Installments: <?= e($pl['course_title']) ?></td>
            <td data-label="Progress"><?= (int) $pl['installments_paid'] ?> of <?= (int) $pl['installment_count'] ?> paid · <?= e(format_money((float) $pl['installment_amount'])) ?> each</td>
            <td data-label="Next date"><?= in_array($pl['status'], ['ACTIVE', 'GRACE'], true) ? e(format_date($pl['next_due_at'])) : '—' ?></td>
            <td data-label="Status"><span class="badge <?= $badge[$pl['status']] ?? 'badge-draft' ?>"><?= e(ucfirst(strtolower($pl['status']))) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($subs as $s): ?>
          <tr>
            <td data-label="What">Subscription: <?= e($s['creator_label']) ?></td>
            <td data-label="Progress"><?= e(format_money((float) $s['price'])) ?>/month</td>
            <td data-label="Next date"><?= e(format_date($s['current_period_ends_at'])) ?></td>
            <td data-label="Status"><span class="badge <?= $badge[$s['status']] ?? 'badge-draft' ?>"><?= e(ucfirst(strtolower($s['status']))) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<script>document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
