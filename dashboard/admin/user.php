<?php
/**
 * Admin: one person in full — contact, their campuses, payments and last sign-in. Giving someone campus
 * access by hand is done on the campus itself (Campuses, campus, Members), where it is audit-logged.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/admin_payments.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

$id = (int) query_param('id');
$u = db_one('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) { http_response_code(404); exit('User not found'); }

$lastLogin = db_one('SELECT logged_in_at, device_type, city, country FROM login_log WHERE user_id = ? ORDER BY logged_in_at DESC LIMIT 1', [$id]);
$memberships = campuses_ready() ? db_all(
    'SELECT m.*, c.name AS campus_name, c.slug AS campus_slug FROM campus_memberships m JOIN campuses c ON c.id = m.campus_id WHERE m.user_id = ? ORDER BY m.access_until DESC',
    [$id]
) : [];
$payments = db_all(
    'SELECT p.*, u.name AS payer_name, u.email AS payer_email, ca.name AS campus_name, ca.slug AS campus_slug '
    . ADMIN_PAYMENT_FROM . ' WHERE p.user_id = ? ORDER BY p.created_at DESC, p.id DESC LIMIT 20',
    [$id]
);
$spent = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM payments WHERE user_id = ? AND status = 'SUCCESS'", [$id])['n'] ?? 0);
$failedCount = (int) db_one("SELECT COUNT(*) AS n FROM payments WHERE user_id = ? AND status = 'FAILED'", [$id])['n'];
$openCount = count(array_filter($memberships, fn($m) => strtotime($m['access_until']) > time()));
$teaches = campuses_ready() && professor_pay_ready() ? db_all('SELECT id, name FROM campuses WHERE professor_user_id = ?', [$id]) : [];

$wa = whatsapp_number($u['phone']);
$badge = ['SUCCESS' => 'badge-published', 'FAILED' => 'badge-rejected', 'PENDING' => 'badge-pending'];
$roleLabel = $u['role'] === 'ADMIN' ? 'Admin' : 'Learner';

$pageTitle = $u['name'] . ' — User — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<p class="small" style="margin-bottom:10px;"><a href="<?= e(base_url('dashboard/admin/users.php')) ?>" style="color:var(--muted);">&larr; All users</a></p>

<div class="card card-pad cm-head">
  <span class="cm-avatar cm-avatar-lg"><?php if (!empty($u['avatar_url'])): ?><img src="<?= e(asset_src($u['avatar_url'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?><?php endif; ?></span>
  <div style="min-width:0; flex:1;">
    <h1 class="h2" style="margin:0;"><?= e($u['name']) ?> <span class="badge badge-draft" style="vertical-align:middle;"><?= e($roleLabel) ?></span><?php if ($teaches): ?> <span class="badge badge-published" style="vertical-align:middle;">Professor</span><?php endif; ?></h1>
    <p class="muted" style="margin-top:4px;">Joined <?= e(format_date($u['created_at'])) ?> · <?= $lastLogin ? 'last signed in ' . e(time_ago($lastLogin['logged_in_at'])) . ' (' . e(ucfirst($lastLogin['device_type'])) . ($lastLogin['city'] ? ', ' . e($lastLogin['city']) : '') . ')' : 'never signed in' ?></p>
    <p class="small muted" style="margin-top:6px;">
      <a href="mailto:<?= e($u['email']) ?>" style="color:inherit;"><?= e($u['email']) ?></a>
      <?php if (!empty($u['phone'])): ?>· <?= e($u['phone']) ?><?= $wa ? ' · <a href="https://wa.me/' . e($wa) . '" target="_blank" rel="noopener noreferrer" style="color:var(--dash-good);">WhatsApp</a>' : '' ?><?php endif; ?>
    </p>
    <?php if ($teaches): ?><p class="small muted" style="margin-top:6px;">Professor of <?= e(implode(', ', array_map(fn($c) => $c['name'] . ' Campus', $teaches))) ?></p><?php endif; ?>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:18px; gap:14px;">
  <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= e(format_money($spent)) ?></span><span class="mini-stat-label">Paid in total</span></div>
  <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= $openCount ?></span><span class="mini-stat-label">Campuses open now</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= $failedCount ?></span><span class="mini-stat-label">Failed payment attempts</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= count(array_filter($memberships, fn($m) => !empty($m['certificate_code']))) ?></span><span class="mini-stat-label">Certificates</span></div>
</div>

<h3 class="dash-section-label" style="margin-top:28px;">Campuses</h3>
<?php if (!$memberships): ?>
  <div class="card card-pad" style="margin-top:12px; border-style:dashed; text-align:center;"><p class="muted">Not a member of any campus. To give access by hand, open the campus and use Members.</p></div>
<?php else: ?>
  <div class="card cm-wrap" style="margin-top:12px;">
    <table class="cm-table">
      <thead><tr><th>Campus</th><th>Plan</th><th>Joined</th><th>Access until</th></tr></thead>
      <tbody>
        <?php foreach ($memberships as $m): $isOpen = strtotime($m['access_until']) > time(); ?>
          <tr>
            <td data-label="Campus"><a href="<?= e(base_url('dashboard/admin/campus-edit.php?id=' . (int) $m['campus_id'] . '&tab=members')) ?>" style="color:inherit; font-weight:700;"><?= e($m['campus_name']) ?> Campus</a><?php if ($m['certificate_code']): ?><small class="cm-sub">Certificate <?= e($m['certificate_code']) ?></small><?php endif; ?></td>
            <td data-label="Plan"><?= $m['plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></td>
            <td data-label="Joined"><?= e(format_date($m['started_at'])) ?></td>
            <td data-label="Access until"><span class="badge <?= $isOpen ? 'badge-published' : 'badge-rejected' ?>"><?= $isOpen ? '' : 'Ended ' ?><?= e(format_date($m['access_until'])) ?></span></td>
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
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
