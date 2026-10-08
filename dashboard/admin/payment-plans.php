<?php
/**
 * Admin: installment plans and school subscriptions that are running, late, or defaulted. Late
 * payers can be nudged on WhatsApp straight from the row. Read-only; the lifecycle (reminders,
 * grace, default) is handled by the cron job and not touched here.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/admin_payments.php';
$user = require_role(['ADMIN']);

$tab = query_param('tab', 'installments');
if (!in_array($tab, ['installments', 'subscriptions'], true)) $tab = 'installments';
$view = query_param('view', $tab === 'installments' ? 'attention' : 'attention');
$validViews = $tab === 'installments' ? ['attention', 'active', 'completed', 'all'] : ['attention', 'active', 'ended', 'all'];
if (!in_array($view, $validViews, true)) $view = 'attention';

$health = admin_plan_health();
$plans = $tab === 'installments' ? admin_get_installment_plans($view) : [];
$subs = $tab === 'subscriptions' ? admin_get_school_subscriptions($view) : [];

$outstanding = (float) (db_one("SELECT COALESCE(SUM((installment_count - installments_paid) * installment_amount), 0) AS n FROM installment_plans WHERE status IN ('ACTIVE','GRACE')")['n'] ?? 0);

$url = fn(array $over = []): string => base_url('dashboard/admin/payment-plans.php?' . http_build_query(array_merge(['tab' => $tab, 'view' => $view], $over)));
$viewLabels = $tab === 'installments'
    ? ['attention' => 'Needs attention', 'active' => 'Running', 'completed' => 'Completed', 'all' => 'All']
    : ['attention' => 'In grace period', 'active' => 'Active', 'ended' => 'Ended', 'all' => 'All'];
$badge = ['ACTIVE' => 'badge-published', 'GRACE' => 'badge-pending', 'COMPLETED' => 'badge-draft', 'DEFAULTED' => 'badge-rejected', 'EXPIRED' => 'badge-rejected', 'CANCELED' => 'badge-draft'];

/** "2 days overdue" / "due in 3 days" for a due date. */
$dueText = function (string $due): array {
    $diff = strtotime($due) - time();
    $days = (int) floor(abs($diff) / 86400);
    if ($diff < 0) return [$days === 0 ? 'overdue today' : $days . ' day' . ($days === 1 ? '' : 's') . ' overdue', true];
    return [$days === 0 ? 'due today' : 'due in ' . $days . ' day' . ($days === 1 ? '' : 's'), false];
};
$waLink = function (?string $phone, string $text): ?string {
    $n = whatsapp_number($phone);
    return $n ? 'https://wa.me/' . $n . '?text=' . rawurlencode($text) : null;
};

$pageTitle = 'Payment Plans — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="vw-top">
  <div>
    <h1 class="h2">Payment Plans</h1>
    <p class="muted" style="margin-top:4px;">Installment plans and school subscriptions: who is paying on time, who is late, and what is still to come in.</p>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px; gap:14px;">
  <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= number_format($health['open_plans']) ?></span><span class="mini-stat-label">Installment plans running</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= number_format($health['overdue']) ?></span><span class="mini-stat-label">Past their due date · <?= number_format($health['defaulted']) ?> defaulted</span></div>
  <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= e(format_money($outstanding)) ?></span><span class="mini-stat-label">Still to be collected on plans</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= number_format($health['subs_live']) ?></span><span class="mini-stat-label">Live school subscriptions · <?= number_format($health['subs_in_grace']) ?> in grace</span></div>
</div>

<div class="row gap-2 wrap" style="margin-top:18px;">
  <a href="<?= e($url(['tab' => 'installments', 'view' => 'attention'])) ?>" class="btn btn-sm <?= $tab === 'installments' ? 'btn-primary' : 'btn-outline' ?>">Installment plans</a>
  <a href="<?= e($url(['tab' => 'subscriptions', 'view' => 'attention'])) ?>" class="btn btn-sm <?= $tab === 'subscriptions' ? 'btn-primary' : 'btn-outline' ?>">School subscriptions</a>
</div>
<div class="row gap-2 wrap" style="margin-top:8px; align-items:center;">
  <span class="small muted">Show</span>
  <?php foreach ($viewLabels as $val => $label): ?>
    <a href="<?= e($url(['view' => $val])) ?>" class="cm-sortpill<?= $view === $val ? ' is-on' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'installments'): ?>
  <?php if (!$plans): ?>
    <div class="card card-pad" style="margin-top:16px; border-style:dashed; text-align:center;"><p class="muted"><?= $view === 'attention' ? 'Nothing is late right now.' : 'No plans in this view.' ?></p></div>
  <?php else: ?>
    <div class="card cm-wrap" style="margin-top:16px;">
      <table class="cm-table">
        <thead><tr><th>Learner</th><th>Course</th><th class="num">Paid</th><th class="num">Each</th><th>Next payment</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p):
              $open = in_array($p['status'], ['ACTIVE', 'GRACE'], true);
              [$dueLabel, $late] = $dueText($p['next_due_at']);
              $first = explode(' ', trim($p['learner_name']))[0];
              $msg = "Hi $first, this is Obin Academy. Your next payment of " . format_money((float) $p['installment_amount']) . ' for "' . $p['course_title'] . '" is ' . ($late ? 'now due' : 'coming up') . '. You can pay it from your Payment Plans page when you are ready. Thank you!';
              $wa = ($open || $p['status'] === 'DEFAULTED') ? $waLink($p['phone'], $msg) : null;
          ?>
            <tr>
              <td data-label="Learner"><a href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $p['learner_id'])) ?>" style="color:inherit; font-weight:600;"><?= e($p['learner_name']) ?></a><small class="cm-sub"><?= e($p['learner_email']) ?></small></td>
              <td data-label="Course"><?= e($p['course_title']) ?><small class="cm-sub">by <?= e($p['creator_label']) ?></small></td>
              <td class="num" data-label="Paid"><?= (int) $p['installments_paid'] ?> of <?= (int) $p['installment_count'] ?></td>
              <td class="num" data-label="Each"><?= e(format_money((float) $p['installment_amount'])) ?></td>
              <td data-label="Next payment"><?php if ($open): ?><span style="<?= $late ? 'color:var(--dash-bad,#dc2626); font-weight:700;' : '' ?>"><?= e($dueLabel) ?></span><small class="cm-sub"><?= e(format_date($p['next_due_at'])) ?></small><?php else: ?><span class="muted">—</span><?php endif; ?></td>
              <td data-label="Status"><span class="badge <?= $badge[$p['status']] ?? 'badge-draft' ?>"><?= e(ucfirst(strtolower($p['status']))) ?></span></td>
              <td class="cm-go"><?php if ($wa): ?><a class="vw-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">Remind</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted" style="margin-top:10px;">Learners with a plan past due get a grace period before access pauses. "Remind" opens WhatsApp with a ready-to-send message.</p>
  <?php endif; ?>
<?php else: ?>
  <?php if (!$subs): ?>
    <div class="card card-pad" style="margin-top:16px; border-style:dashed; text-align:center;"><p class="muted"><?= $view === 'attention' ? 'No subscriptions are in their grace period.' : 'No subscriptions in this view.' ?></p></div>
  <?php else: ?>
    <div class="card cm-wrap" style="margin-top:16px;">
      <table class="cm-table">
        <thead><tr><th>Learner</th><th>School</th><th class="num">Price</th><th>Current period ends</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($subs as $s):
              $live = in_array($s['status'], ['ACTIVE', 'GRACE'], true);
              [$endLabel, $late] = $dueText($s['current_period_ends_at']);
              $first = explode(' ', trim($s['learner_name']))[0];
              $wa = $live ? $waLink($s['phone'], "Hi $first, this is Obin Academy. Your subscription to " . $s['creator_label'] . ' is ' . ($late ? 'due for renewal' : 'about to renew') . '. You can renew it from My Subscriptions. Thank you!') : null;
          ?>
            <tr>
              <td data-label="Learner"><a href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $s['learner_id'])) ?>" style="color:inherit; font-weight:600;"><?= e($s['learner_name']) ?></a><small class="cm-sub"><?= e($s['learner_email']) ?></small></td>
              <td data-label="School"><?= e($s['creator_label']) ?><?= $s['course_title'] ? '<small class="cm-sub">' . e($s['course_title']) . '</small>' : '' ?></td>
              <td class="num" data-label="Price"><?= e(format_money((float) $s['price'])) ?>/mo</td>
              <td data-label="Current period ends"><span style="<?= $live && $late ? 'color:var(--dash-bad,#dc2626); font-weight:700;' : '' ?>"><?= $live ? e($endLabel) : e(format_date($s['current_period_ends_at'])) ?></span><small class="cm-sub"><?= e(format_date($s['current_period_ends_at'])) ?></small></td>
              <td data-label="Status"><span class="badge <?= $badge[$s['status']] ?? 'badge-draft' ?>"><?= e(ucfirst(strtolower($s['status']))) ?></span></td>
              <td class="cm-go"><?php if ($wa): ?><a class="vw-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">Remind</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
