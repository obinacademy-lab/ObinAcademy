<?php
/**
 * Admin: every payment on the platform, with search, filters, a CSV export and a "re-check with the
 * provider" button for payments stuck on PENDING (buyer paid but the confirmation never arrived).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/admin_payments.php';
require __DIR__ . '/../../includes/payments.php'; // fetch_payment_by_id() and resolve_payment_with_iotec() for the re-check button
require __DIR__ . '/../../includes/outreach.php'; // one-tap reminders for failed and stuck payments
$user = require_role(['ADMIN']);

$filters = admin_payment_filters($_GET);
$perPage = 40;
$page = max(1, (int) query_param('page', '1'));

// Keeps the filters when an action sends the admin back to the list.
$backQuery = array_filter(['status' => $filters['status'], 'type' => $filters['type'], 'q' => $filters['q'], 'from' => $filters['from'], 'to' => $filters['to'], 'page' => $page > 1 ? $page : ''], fn($v) => $v !== '');
$listUrl = '/dashboard/admin/payments.php' . ($backQuery ? '?' . http_build_query($backQuery) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post('_action') === 'recheck') {
        $payment = fetch_payment_by_id((int) post('paymentId'));
        if (!$payment) {
            flash_set('error', 'That payment no longer exists.');
        } elseif ($payment['status'] !== 'PENDING') {
            flash_set('error', 'That payment is already ' . ($payment['status'] === 'SUCCESS' ? 'successful' : 'marked failed') . ', so there is nothing to re-check.');
        } elseif (empty($payment['iotec_transaction_id'])) {
            flash_set('error', 'This payment never reached the provider, so there is nothing to check.');
        } else {
            $result = resolve_payment_with_iotec($payment);
            log_admin_action((int) $user['id'], $user['name'], 'payment.rechecked', 'Payment', '#' . $payment['id'], 'provider says ' . $result['status']);
            flash_set(
                $result['status'] === 'SUCCESS' ? 'success' : 'error',
                $result['status'] === 'SUCCESS' ? 'Confirmed by the provider: payment #' . $payment['id'] . ' succeeded and access has been granted.'
                : ($result['status'] === 'FAILED' ? 'The provider says payment #' . $payment['id'] . ' failed. It is now marked failed.'
                : 'The provider has not confirmed payment #' . $payment['id'] . ' yet. It is still pending.')
            );
        }
    }
    if (in_array(post('_action'), ['remind_whatsapp', 'remind_email'], true)) {
        $row = admin_get_payment((int) post('paymentId'));
        $target = $row ? payment_reminder_target($row) : null;
        if (!$target) {
            flash_set('error', 'There is nothing to remind about for that payment. It may have succeeded, or the buyer already has the course.');
        } elseif (post('_action') === 'remind_whatsapp') {
            if (!$target['wa']) {
                flash_set('error', 'This payment has no usable phone number to open WhatsApp with.');
            } else {
                log_payment_reminder((int) $row['id'], (int) $user['id'], 'whatsapp');
                log_admin_action((int) $user['id'], $user['name'], 'payment.reminded', 'Payment', '#' . $row['id'], 'WhatsApp');
                redirect('https://wa.me/' . $target['wa'] . '?text=' . rawurlencode(payment_reminder_whatsapp_text($target)));
            }
        } else {
            if (!$target['email']) {
                flash_set('error', 'This buyer left no email address.');
            } else {
                send_payment_recovery_email($target['email'], $target['name'], $target['title'], $target['kind'], $target['url'], (float) $row['amount']);
                log_payment_reminder((int) $row['id'], (int) $user['id'], 'email');
                log_admin_action((int) $user['id'], $user['name'], 'payment.reminded', 'Payment', '#' . $row['id'], 'email');
                flash_set('success', 'Reminder emailed to ' . $target['email'] . '.');
            }
        }
    }
    redirect(safe_local_redirect_path(post('back'), '/dashboard/admin/payments.php'));
}

// CSV of the current filter (cells that start with = + - @ are neutralised so a spreadsheet won't run them).
if (query_param('export') === 'csv') {
    $rows = admin_get_payments($filters, 5000, 0);
    log_admin_action((int) $user['id'], $user['name'], 'payments.exported', 'Payments', count($rows) . ' rows', http_build_query($backQuery));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="obin-payments-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;
    fputcsv($out, ['Payment #', 'Date', 'Status', 'Type', 'For', 'Amount (UGX)', 'List price (UGX)', 'Phone', 'Buyer', 'Buyer email', 'Provider reference', 'Provider message']);
    foreach ($rows as $p) {
        fputcsv($out, array_map($safe, [
            $p['id'], $p['created_at'], $p['status'], ADMIN_PAYMENT_TYPES[$p['type']] ?? $p['type'], admin_payment_what($p),
            number_format((float) $p['amount'], 0, '.', ''), $p['original_amount'] !== null ? number_format((float) $p['original_amount'], 0, '.', '') : '',
            $p['phone'], $p['payer_name'] ?: ($p['guest_name'] ?: ''), $p['payer_email'] ?: ($p['guest_email'] ?: ''),
            $p['iotec_transaction_id'] ?: '', $p['status_message'] ?: '',
        ]));
    }
    fclose($out);
    exit;
}

$health = admin_payment_health();
$total = admin_count_payments($filters);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$payments = admin_get_payments($filters, $perPage, ($page - 1) * $perPage);

$url = function (array $over = []) use ($filters, $page): string {
    $p = array_filter(array_merge(['status' => $filters['status'], 'type' => $filters['type'], 'q' => $filters['q'], 'from' => $filters['from'], 'to' => $filters['to'], 'page' => ''], $over), fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/admin/payments.php' . ($p ? '?' . http_build_query($p) : ''));
};
$statusPills = ['' => 'All', 'SUCCESS' => 'Successful', 'FAILED' => 'Failed', 'PENDING' => 'Pending', 'STUCK' => 'Stuck (' . ADMIN_STUCK_PAYMENT_MINUTES . '+ min)'];
$badge = ['SUCCESS' => 'badge-published', 'FAILED' => 'badge-rejected', 'PENDING' => 'badge-pending'];
$hasFilters = $filters['status'] !== '' || $filters['type'] !== '' || $filters['q'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';

$pageTitle = 'Payments — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="vw-top">
  <div>
    <h1 class="h2">Payments</h1>
    <p class="muted" style="margin-top:4px;">Every mobile-money payment on the platform. Use it to answer "I paid but I have no access".</p>
  </div>
  <a class="vw-ghost" href="<?= e($url(['export' => 'csv'])) ?>" title="Download what you see now as a spreadsheet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-4-4m4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg><span>Download CSV</span></a>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px; gap:14px;">
  <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= e(format_money($health['collected_30d'])) ?></span><span class="mini-stat-label">Collected, last 30 days · <?= number_format($health['success_30d']) ?> payments</span></div>
  <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= $health['success_rate'] !== null ? $health['success_rate'] . '%' : '—' ?></span><span class="mini-stat-label">Payments that go through (30 days)</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= number_format($health['failed_24h']) ?></span><span class="mini-stat-label">Failed in the last 24 hours</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= number_format($health['stuck']) ?></span><span class="mini-stat-label">Stuck pending · <?= number_format($health['pending_now']) ?> pending in total</span></div>
</div>

<form method="get" class="vw-bar">
  <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
  <label class="vw-field vw-grow">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, email, phone, course or reference" aria-label="Search payments" autocomplete="off">
  </label>
  <label class="vw-field"><select name="type" aria-label="Type" data-autosubmit>
    <option value="">All types</option>
    <?php foreach (ADMIN_PAYMENT_TYPES as $val => $label): ?><option value="<?= e($val) ?>"<?= $filters['type'] === $val ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select></label>
  <label class="vw-field"><span class="small muted">From</span><input type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From date" data-autosubmit></label>
  <label class="vw-field"><span class="small muted">To</span><input type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To date" data-autosubmit></label>
  <button class="btn btn-outline btn-sm" type="submit">Search</button>
  <?php if ($hasFilters): ?><a class="small muted" href="<?= e(base_url('dashboard/admin/payments.php')) ?>">Clear</a><?php endif; ?>
</form>

<div class="row gap-2 wrap" style="margin-top:12px;">
  <?php foreach ($statusPills as $val => $label): ?>
    <a href="<?= e($url(['status' => $val])) ?>" class="btn btn-sm <?= $filters['status'] === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <span class="small muted" style="margin-left:auto; align-self:center;"><?= number_format($total) ?> payment<?= $total === 1 ? '' : 's' ?></span>
</div>

<?php if (!$payments): ?>
  <div class="card card-pad" style="margin-top:16px; border-style:dashed; text-align:center;"><p class="muted">No payments match this view.</p></div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:16px;">
    <table class="cm-table">
      <thead><tr><th>When</th><th>Buyer</th><th>For</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php $reminders = get_payment_reminder_summary(array_column($payments, 'id'));
        foreach ($payments as $p):
            $isStuck = $p['status'] === 'PENDING' && strtotime($p['created_at']) < time() - ADMIN_STUCK_PAYMENT_MINUTES * 60;
            $canRecheck = $p['status'] === 'PENDING' && !empty($p['iotec_transaction_id']);
            $remindTarget = payment_reminder_target($p);
            $reminded = $reminders[(int) $p['id']] ?? null;
        ?>
          <tr>
            <td data-label="When"><?= e(format_date($p['created_at'])) ?><small class="cm-sub"><?= e(date('g:i A', strtotime($p['created_at']))) ?> · #<?= (int) $p['id'] ?></small></td>
            <td data-label="Buyer">
              <?php if ($p['user_id']): ?>
                <a href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $p['user_id'])) ?>" style="color:inherit; font-weight:600;"><?= e($p['payer_name']) ?></a><small class="cm-sub"><?= e($p['payer_email']) ?></small><small class="cm-sub"><?= e($p['phone']) ?></small>
              <?php else: ?>
                <strong><?= e($p['guest_name'] ?: 'Guest') ?></strong> <span class="badge badge-draft cm-badge" style="display:inline; margin:0 0 0 4px;">Guest</span><small class="cm-sub"><?= e($p['guest_email']) ?></small><small class="cm-sub"><?= e($p['phone']) ?></small>
              <?php endif; ?>
            </td>
            <td data-label="For"><?= e(admin_payment_what($p)) ?><small class="cm-sub"><?= e(ADMIN_PAYMENT_TYPES[$p['type']] ?? $p['type']) ?><?= $p['coupon_id'] ? ' · coupon used' : '' ?></small></td>
            <td class="num" data-label="Amount"><?= e(format_money((float) $p['amount'])) ?><?php if ($p['original_amount'] !== null && (float) $p['original_amount'] > (float) $p['amount']): ?><small class="cm-sub">list <?= e(format_money((float) $p['original_amount'])) ?></small><?php endif; ?></td>
            <td data-label="Status">
              <span class="badge <?= $badge[$p['status']] ?? 'badge-draft' ?>"><?= e($isStuck ? 'Stuck' : ucfirst(strtolower($p['status']))) ?></span>
              <?php if ($p['status_message'] && $p['status'] !== 'SUCCESS'): ?><small class="cm-sub" style="white-space:normal; max-width:200px;"><?= e(mb_strimwidth($p['status_message'], 0, 90, '…')) ?></small><?php endif; ?>
              <?php if ($p['iotec_transaction_id']): ?><small class="cm-sub" title="Provider reference"><?= e(mb_strimwidth($p['iotec_transaction_id'], 0, 22, '…')) ?></small><?php endif; ?>
            </td>
            <td class="cm-go">
              <?php if ($canRecheck): ?>
                <form method="post" data-confirm="Ask the provider whether payment #<?= (int) $p['id'] ?> went through? If it did, the buyer is given access.">
                  <?= csrf_field() ?><input type="hidden" name="_action" value="recheck"><input type="hidden" name="paymentId" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($listUrl) ?>">
                  <button class="btn btn-outline btn-sm" type="submit">Re-check</button>
                </form>
              <?php endif; ?>
              <?php if ($remindTarget): ?>
                <div class="ot-actions">
                  <?php if ($remindTarget['wa']): ?>
                    <form method="post" target="_blank">
                      <?= csrf_field() ?><input type="hidden" name="_action" value="remind_whatsapp"><input type="hidden" name="paymentId" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($listUrl) ?>">
                      <button class="ot-btn wa" type="submit" title="Opens WhatsApp with the message written for you">WhatsApp</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($remindTarget['email']): ?>
                    <form method="post" data-confirm="Email a reminder to <?= e($remindTarget['email']) ?>?">
                      <?= csrf_field() ?><input type="hidden" name="_action" value="remind_email"><input type="hidden" name="paymentId" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($listUrl) ?>">
                      <button class="ot-btn" type="submit">Email</button>
                    </form>
                  <?php endif; ?>
                </div>
                <small class="cm-sub ot-seen"><?php
                  $auto = !empty($p['recovery_email_sent_at']);
                  if ($reminded) echo 'Reminded ' . $reminded['n'] . '&times; &middot; ' . e(time_ago($reminded['last']));
                  elseif ($auto) echo 'Auto email sent ' . e(time_ago($p['recovery_email_sent_at']));
                  else echo 'Not reminded yet';
                ?></small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="ad-pager" aria-label="Pages">
      <?php if ($page > 1): ?><a href="<?= e($url(['page' => $page - 1])) ?>">Previous</a><?php else: ?><span class="dis">Previous</span><?php endif; ?>
      <span class="on">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a href="<?= e($url(['page' => $page + 1])) ?>">Next</a><?php else: ?><span class="dis">Next</span><?php endif; ?>
    </nav>
  <?php endif; ?>
  <p class="small muted" style="margin-top:10px;">"Stuck" means still pending after <?= ADMIN_STUCK_PAYMENT_MINUTES ?> minutes. Re-check asks MTN or Airtel what actually happened and, if the money arrived, grants access exactly as if the buyer had waited. For failed or stuck course and bundle payments, WhatsApp opens your WhatsApp with a ready message and the link to try again, and Email sends the same reminder.</p>
<?php endif; ?>
<script>
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });
</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
