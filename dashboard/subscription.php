<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/subscriptions.php';
$user = require_login();

$subscription = get_subscription_for_user((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('formName') === 'cancel') {
    csrf_verify();
    cancel_subscription((int) $user['id']);
    flash_set('success', 'Your subscription has been canceled. You\'ll keep access until your current period ends.');
    redirect('/dashboard/subscription.php');
}

$payments = db_all(
    "SELECT * FROM payments WHERE user_id = ? AND type = 'SUBSCRIPTION' ORDER BY created_at DESC LIMIT 20",
    [$user['id']]
);

$statusLabel = [
    'ACTIVE' => 'Active',
    'GRACE' => 'Payment Pending',
    'EXPIRED' => 'Expired',
    'CANCELED' => 'Canceled',
];
$statusBadgeClass = [
    'ACTIVE' => 'badge-published',
    'GRACE' => 'badge-pending',
    'EXPIRED' => 'badge-rejected',
    'CANCELED' => 'badge-rejected',
];

$pageTitle = 'My Subscription — Obin Academy';
require __DIR__ . '/../includes/dashboard_header.php';
?>
<h1 class="h2 reveal">My Subscription</h1>

<?php if (!$subscription): ?>
  <div class="card card-pad reveal" style="margin-top:24px; text-align:center;">
    <p class="muted">You don't have a subscription yet — one payment unlocks every course on Obin Academy for 30 days.</p>
    <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-primary" style="margin-top:14px;">View Plans</a>
  </div>
<?php else: ?>
  <?php if ($subscription['status'] === 'GRACE'): ?>
    <div class="alert alert-error reveal" style="margin-top:20px;">
      We couldn't reach your mobile money account for this renewal yet — we'll keep retrying for a few more days.
      Your access continues in the meantime. Make sure <?= e($subscription['phone']) ?> can receive a mobile money prompt.
    </div>
  <?php endif; ?>

  <div class="grid md:grid-3" style="margin-top:24px;">
    <div class="stat-card reveal" data-hoverable="true" style="--hover-color:#8b5cf6;">
      <div class="icon"><?php dash_icon('crown'); ?></div>
      <div class="value"><?= e(SUBSCRIPTION_TIERS[$subscription['tier']]['label']) ?></div>
      <div class="label">Plan &middot; <span class="badge <?= $statusBadgeClass[$subscription['status']] ?>"><?= e($statusLabel[$subscription['status']]) ?></span></div>
    </div>
    <div class="stat-card reveal reveal-delay-1" data-hoverable="true" style="--hover-color:#f5b301;">
      <div class="icon"><?php dash_icon('banknote'); ?></div>
      <div class="value"><?= e(format_money((float) $subscription['price'])) ?></div>
      <div class="label">Per month</div>
    </div>
    <div class="stat-card reveal reveal-delay-2" data-hoverable="true" style="--hover-color:#34d399;">
      <div class="icon"><?php dash_icon('clock'); ?></div>
      <div class="value"><?= e(format_date($subscription['current_period_ends_at'])) ?></div>
      <div class="label"><?= $subscription['status'] === 'CANCELED' ? 'Access ends' : 'Next billing date' ?></div>
    </div>
  </div>

  <?php if (in_array($subscription['status'], ['ACTIVE', 'GRACE'], true)): ?>
    <div class="card card-pad reveal" style="margin-top:24px; max-width:480px;">
      <h3 style="font-size:15px; font-weight:700;">Cancel Subscription</h3>
      <p class="muted small" style="margin-top:6px;">You'll keep full access until <?= e(format_date($subscription['current_period_ends_at'])) ?> — no further charge attempts after that.</p>
      <form method="post" style="margin-top:14px;">
        <?= csrf_field() ?>
        <input type="hidden" name="formName" value="cancel">
        <button type="submit" class="btn btn-outline">Cancel Subscription</button>
      </form>
    </div>
  <?php elseif ($subscription['status'] === 'EXPIRED'): ?>
    <div class="card card-pad reveal" style="margin-top:24px; max-width:480px; text-align:center;">
      <p class="muted">Your subscription has ended.</p>
      <a href="<?= e(base_url('subscribe.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Subscribe Again</a>
    </div>
  <?php endif; ?>

  <h2 class="h3" style="margin-top:36px;">Payment History</h2>
  <div class="table-wrap reveal" style="margin-top:14px;">
    <table>
      <thead><tr><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e(format_money((float) $p['amount'])) ?></td>
            <td><span class="badge <?= $p['status'] === 'SUCCESS' ? 'badge-published' : ($p['status'] === 'FAILED' ? 'badge-rejected' : 'badge-pending') ?>"><?= e($p['status']) ?></span></td>
            <td><?= e(format_date($p['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="3" class="muted">No payments yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
