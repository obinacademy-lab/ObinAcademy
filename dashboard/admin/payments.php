<?php
/**
 * Admin: payments, as a payments desk. A dark console with the last 30 days (collected, how many go
 * through, failed and stuck) and a 14-day chart of payments that succeeded and failed; below, every
 * campus payment with search, dates, status, a CSV export, a "re-check with the provider" button for
 * payments stuck on PENDING, and WhatsApp / email reminders for failed and stuck ones.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-payments.css. Motion: assets/js/admin-pay.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/admin_payments.php';
require __DIR__ . '/../../includes/payments.php'; // fetch_payment_by_id() and resolve_payment_with_iotec() for the re-check button
require __DIR__ . '/../../includes/outreach.php'; // one-tap reminders for failed and stuck payments
require_once __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

$filters = admin_payment_filters($_GET);
$perPage = 40;
$page = max(1, (int) query_param('page', '1'));

// Keeps the filters when an action sends the admin back to the list.
$backQuery = array_filter(['status' => $filters['status'], 'q' => $filters['q'], 'from' => $filters['from'], 'to' => $filters['to'], 'page' => $page > 1 ? $page : ''], fn($v) => $v !== '');
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
            flash_set('error', 'There is nothing to remind about for that payment. It may have succeeded, or they have since joined that campus.');
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
                if (send_payment_reminder_email($target, (float) $row['amount'], ['bcc' => $user['email'], 'reply_to' => $user['email']])) {
                    log_payment_reminder((int) $row['id'], (int) $user['id'], 'email');
                    log_admin_action((int) $user['id'], $user['name'], 'payment.reminded', 'Payment', '#' . $row['id'], 'email');
                    flash_set('success', 'Reminder emailed to ' . $target['email'] . '. A copy is in your inbox (' . $user['email'] . ').');
                } else {
                    flash_set('error', 'The reminder was not sent. The email service refused it, so nothing was recorded. Try again, or use WhatsApp.');
                }
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
    fputcsv($out, ['Payment #', 'Date', 'Status', 'For', 'Amount (UGX)', 'Phone', 'Buyer', 'Buyer email', 'Provider reference', 'Provider message']);
    foreach ($rows as $p) {
        fputcsv($out, array_map($safe, [
            $p['id'], $p['created_at'], $p['status'], admin_payment_what($p), number_format((float) $p['amount'], 0, '.', ''),
            $p['phone'], $p['payer_name'] ?: '', $p['payer_email'] ?: '', $p['iotec_transaction_id'] ?: '', $p['status_message'] ?: '',
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
$reminders = $payments ? get_payment_reminder_summary(array_column($payments, 'id')) : [];

// Counts for the status switch, and the last 14 days of payments that went through or failed.
$statusCounts = array_map('intval', array_column(db_all('SELECT status, COUNT(*) AS n FROM payments GROUP BY status'), 'n', 'status'));
$statusCounts[''] = array_sum($statusCounts);
$statusCounts['STUCK'] = (int) $health['stuck'];
$daily = [];
for ($i = 13; $i >= 0; $i--) $daily[date('Y-m-d', strtotime("-$i days"))] = ['SUCCESS' => 0, 'FAILED' => 0];
foreach (db_all("SELECT DATE(created_at) AS d, status, COUNT(*) AS n FROM payments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND status IN ('SUCCESS', 'FAILED') GROUP BY DATE(created_at), status") as $r) {
    if (isset($daily[$r['d']])) $daily[$r['d']][$r['status']] = (int) $r['n'];
}
$dayMax = max(1, max(array_map(fn($d) => $d['SUCCESS'] + $d['FAILED'], $daily)));
$days14 = array_sum(array_map(fn($d) => $d['SUCCESS'] + $d['FAILED'], $daily));

$url = function (array $over = []) use ($filters): string {
    $p = array_filter(array_merge(['status' => $filters['status'], 'q' => $filters['q'], 'from' => $filters['from'], 'to' => $filters['to'], 'page' => ''], $over), fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/admin/payments.php' . ($p ? '?' . http_build_query($p) : ''));
};
$statusLabels = ['' => 'All', 'SUCCESS' => 'Successful', 'PENDING' => 'Pending', 'STUCK' => 'Stuck', 'FAILED' => 'Failed'];
$hasFilters = $filters['status'] !== '' || $filters['q'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';
$look = [];
$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
foreach (get_all_campuses() as $k => $c) $look[(int) $c['id']] = ['photo' => campus_photo($c), 'color' => $colors[$k % count($colors)], 'icon' => $c['icon'] ?: 'book'];
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2))));
$waIcon = '<svg class="wa-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.91-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.08-.13-.27-.2-.57-.35zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88zm8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89a11.82 11.82 0 0 0-3.48-8.42z"/></svg>';

$pageTitle = 'Payments — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-payments.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx px" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Payments</h1><p>Every mobile money payment for a campus. Use it to answer "I paid but I have no access".</p></div>
    <div class="acx-acts"><a class="acx-btn" href="<?= e($url(['export' => 'csv'])) ?>" title="Download what you see now as a spreadsheet"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>Export CSV</a></div>
  </div>

  <section class="lc-console px-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow"><i></i>Last 30 days</span>
      <div class="px-big"><small>UGX</small><b data-scramble="<?= (int) round($health['collected_30d']) ?>"><?= e(number_format($health['collected_30d'])) ?></b></div>
      <p class="lc-copy">Collected from <?= number_format($health['success_30d']) ?> successful payment<?= (int) $health['success_30d'] === 1 ? '' : 's' ?></p>
      <div class="lc-stats">
        <div><span>Payments that go through</span><b class="lime"><?= $health['success_rate'] !== null ? '<span data-scramble="' . (int) $health['success_rate'] . '">' . (int) $health['success_rate'] . '</span>%' : '—' ?></b></div>
        <div><span>Pending now</span><b data-scramble="<?= (int) $health['pending_now'] ?>"><?= number_format($health['pending_now']) ?></b></div>
        <div><span>Failed · last 24 hours</span><b class="<?= $health['failed_24h'] ? 'red' : '' ?>" data-scramble="<?= (int) $health['failed_24h'] ?>"><?= number_format($health['failed_24h']) ?></b></div>
        <div><span>Stuck over <?= ADMIN_STUCK_PAYMENT_MINUTES ?> minutes</span><b class="<?= $health['stuck'] ? 'red' : '' ?>" data-scramble="<?= (int) $health['stuck'] ?>"><?= number_format($health['stuck']) ?></b></div>
      </div>
    </div>
    <div>
      <div class="lc-rx-h"><b>Last 14 days</b><span class="px-legend"><i class="ok"></i>Went through <i class="no"></i>Failed</span></div>
      <div class="px-bars" role="img" aria-label="Payments that went through and failed, per day, last 14 days">
        <?php $k = 0; foreach ($daily as $d => $c): $sum = $c['SUCCESS'] + $c['FAILED']; ?>
          <div class="px-day <?= $d === date('Y-m-d') ? 'now' : '' ?>" style="--h:<?= $k++ ?>" data-t="<?= e(date('D j M', strtotime($d))) ?> · <?= $c['SUCCESS'] ?> through · <?= $c['FAILED'] ?> failed">
            <i class="no" style="height:<?= $c['FAILED'] ? max(4, round($c['FAILED'] / $dayMax * 100)) : 0 ?>%"></i>
            <i class="ok <?= $sum ? '' : 'zero' ?>" style="height:<?= $c['SUCCESS'] ? max(4, round($c['SUCCESS'] / $dayMax * 100)) : ($sum ? 0 : 3) ?>%"></i>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (!$days14): ?><p class="px-quiet">No payments in the last 14 days</p><?php endif; ?>
      <div class="px-days" aria-hidden="true"><?php $k = 0; foreach ($daily as $d => $c): ?><span><?= $k++ % 2 === 0 ? e(date('j', strtotime($d))) : '' ?></span><?php endforeach; ?></div>
    </div>
  </section>

  <section class="acx-sec acx-slam" style="--i:2" id="all">
    <div class="px-h">
      <div><h2>All payments</h2><p><?= number_format($total) ?> payment<?= $total === 1 ? '' : 's' ?><?= $hasFilters ? ' match this view' : ', newest first' ?></p></div>
      <nav class="px-status" aria-label="Filter by status">
        <?php foreach ($statusLabels as $val => $label): ?>
          <a href="<?= e($url(['status' => $val])) ?>#all" class="<?= $filters['status'] === $val ? 'on' : '' ?> s-<?= strtolower($val ?: 'all') ?>"><?= e($label) ?><em><?= number_format($statusCounts[$val] ?? 0) ?></em></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <form method="get" class="px-tools" action="<?= e(base_url('dashboard/admin/payments.php')) ?>#all">
      <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
      <label class="px-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, email, phone, campus or reference" aria-label="Search payments" autocomplete="off"></label>
      <label class="px-date"><span>From</span><input type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From date" data-autosubmit></label>
      <label class="px-date"><span>To</span><input type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To date" data-autosubmit></label>
      <button class="px-go" type="submit">Search</button>
      <?php if ($hasFilters): ?><a class="px-clear" href="<?= e(base_url('dashboard/admin/payments.php')) ?>#all">Clear</a><?php endif; ?>
    </form>

    <?php if (!$payments): ?>
      <p class="px-none">No payments match this view.</p>
    <?php else: ?>
      <div class="px-grid px-th"><span>When</span><span>Buyer</span><span>Campus</span><span>Payment</span><span class="r">Actions</span></div>
      <?php foreach ($payments as $j => $p):
          $isStuck = $p['status'] === 'PENDING' && strtotime($p['created_at']) < time() - ADMIN_STUCK_PAYMENT_MINUTES * 60;
          $state = $isStuck ? 'stuck' : strtolower($p['status']);
          $canRecheck = $p['status'] === 'PENDING' && !empty($p['iotec_transaction_id']);
          $remindTarget = payment_reminder_target($p);
          $reminded = $reminders[(int) $p['id']] ?? null;
          $l = $look[(int) $p['campus_id']] ?? null;
      ?>
        <div class="px-grid lc-row acx-whip st-<?= $state ?>" style="--j:<?= min($j, 14) ?>">
          <div class="px-when"><b><?= e(date('j M Y', strtotime($p['created_at']))) ?></b><small><?= e(date('g:i a', strtotime($p['created_at']))) ?> · #<?= (int) $p['id'] ?></small></div>
          <div class="px-buyer">
            <span class="px-av"><?= e($initials($p['payer_name'] ?: '?')) ?></span>
            <span>
              <?php if ($p['user_id'] && $p['payer_name']): ?><a href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $p['user_id'])) ?>"><?= e($p['payer_name']) ?></a><?php else: ?><b>Deleted account</b><?php endif; ?>
              <small class="px-mail" title="<?= e($p['payer_email'] ?: '') ?>"><?= e($p['payer_email'] ?: '') ?></small>
              <small class="px-phone"><?= e($p['phone']) ?></small>
            </span>
          </div>
          <div class="px-campus">
            <?php if ($l && $l['photo']): ?><i style="background-image:url('<?= e($l['photo']) ?>')"></i><?php elseif ($l): ?><i class="badge" style="background:<?= e($l['color']) ?>"><?= campus_icon($l['icon'], 1.8) ?></i><?php else: ?><i class="badge" style="background:#475569"></i><?php endif; ?>
            <span><b><?= e($p['campus_name'] ?: 'A campus') ?></b><?php if ($p['campus_plan']): ?><em class="px-plan <?= $p['campus_plan'] === 'YEARLY' ? 'y' : '' ?>"><?= $p['campus_plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></em><?php endif; ?></span>
          </div>
          <div class="px-st">
            <div class="px-amt"><small>UGX</small><?= e(number_format((float) $p['amount'])) ?></div>
            <span class="px-pill <?= $state ?>"><?= $isStuck ? 'Stuck' : ($p['status'] === 'SUCCESS' ? 'Successful' : ucfirst(strtolower($p['status']))) ?></span>
            <?php if ($p['status_message'] && $p['status'] !== 'SUCCESS'): ?><small title="<?= e($p['status_message']) ?>"><?= e(mb_strimwidth($p['status_message'], 0, 70, '…')) ?></small><?php endif; ?>
            <?php if ($p['iotec_transaction_id']): ?><small class="px-ref" title="Provider reference"><?= e(mb_strimwidth($p['iotec_transaction_id'], 0, 22, '…')) ?></small><?php endif; ?>
          </div>
          <div class="px-act">
            <div class="px-btns">
              <?php if ($canRecheck): ?>
                <form method="post" data-confirm="Ask the provider whether payment #<?= (int) $p['id'] ?> went through? If it did, the buyer is given access.">
                  <?= csrf_field() ?><input type="hidden" name="_action" value="recheck"><input type="hidden" name="paymentId" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($listUrl) ?>">
                  <button class="px-btn dark" type="submit">Re-check</button>
                </form>
              <?php endif; ?>
              <?php if ($remindTarget && $remindTarget['wa']): ?>
                <form method="post" target="_blank">
                  <?= csrf_field() ?><input type="hidden" name="_action" value="remind_whatsapp"><input type="hidden" name="paymentId" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($listUrl) ?>">
                  <button class="px-wa" type="submit" title="Opens WhatsApp with the reminder written for you" aria-label="Remind on WhatsApp"><?= $waIcon ?></button>
                </form>
              <?php endif; ?>
              <?php if ($remindTarget && $remindTarget['email']): ?>
                <form method="post" data-confirm="Email a reminder to <?= e($remindTarget['email']) ?>?">
                  <?= csrf_field() ?><input type="hidden" name="_action" value="remind_email"><input type="hidden" name="paymentId" value="<?= (int) $p['id'] ?>"><input type="hidden" name="back" value="<?= e($listUrl) ?>">
                  <button class="px-btn" type="submit">Email</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($remindTarget): ?><small><?= $reminded ? 'Reminded ' . (int) $reminded['n'] . '× · ' . e(time_ago($reminded['last'])) : 'Not reminded yet' ?></small><?php elseif (!$canRecheck): ?><span class="px-done">—</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="px-foot">
        <span>"Stuck" means still pending after <?= ADMIN_STUCK_PAYMENT_MINUTES ?> minutes. Re-check asks MTN or Airtel what happened and, if the money arrived, gives access as if the buyer had waited.</span>
        <?php if ($pages > 1): ?>
          <nav class="px-pager" aria-label="Pages">
            <?php if ($page > 1): ?><a href="<?= e($url(['page' => $page - 1])) ?>#all" aria-label="Previous">‹</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= e($url(['page' => $page + 1])) ?>#all" aria-label="Next">›</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<script>document.querySelectorAll('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });</script>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
