<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/school_subscriptions.php';
$user = require_login();

$subscriptions = get_school_subscriptions_for_learner((int) $user['id']);

$statusBadge = ['ACTIVE' => 'badge-published', 'GRACE' => 'badge-pending', 'EXPIRED' => 'badge-rejected', 'CANCELED' => 'badge-draft'];
$statusLabel = ['ACTIVE' => 'Active', 'GRACE' => 'Renew Now', 'EXPIRED' => 'Expired', 'CANCELED' => 'Canceled'];

$pageTitle = 'My Subscriptions — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">My Subscriptions</h1>
<p class="muted" style="margin-top:6px;">Monthly subscriptions to a creator's school — each one unlocks every course they publish, for as long as it stays active.</p>

<?php if (!$subscriptions): ?>
  <div class="card card-pad" style="text-align:center; margin-top:24px; border-style:dashed;">
    <p class="muted">You don't have any school subscriptions yet.</p>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Explore Courses</a>
  </div>
<?php else: ?>
  <div class="stack gap-3" style="margin-top:24px; max-width:640px;">
    <?php foreach ($subscriptions as $sub):
      $schoolLabel = $sub['school_name'] ?: ($sub['creator_name'] . "'s School");
      $needsRenewal = in_array($sub['status'], ['GRACE', 'EXPIRED'], true);
    ?>
      <div class="card card-pad">
        <div class="row between wrap gap-2">
          <div class="row gap-3" style="align-items:center;">
            <div class="profile-avatar" style="width:48px; height:48px; font-size:18px;">
              <?php if ($sub['creator_avatar_url']): ?><img src="<?= e(asset_src($sub['creator_avatar_url'])) ?>" alt="">
              <?php else: ?><?= e(mb_substr($sub['creator_name'], 0, 1)) ?><?php endif; ?>
            </div>
            <div>
              <a href="<?= e(base_url('profile.php?id=' . $sub['creator_id'])) ?>" style="font-weight:700; color:var(--ink);"><?= e($schoolLabel) ?></a>
              <p class="small muted" style="margin-top:2px;"><?= e(format_money((float) $sub['price'])) ?>/month</p>
            </div>
          </div>
          <span class="badge <?= e($statusBadge[$sub['status']] ?? 'badge-draft') ?>"><?= e($statusLabel[$sub['status']] ?? $sub['status']) ?></span>
        </div>

        <p class="small muted" style="margin-top:14px;">
          <?php if ($sub['status'] === 'ACTIVE'): ?>
            Renews on <?= e(format_date($sub['current_period_ends_at'])) ?>
          <?php elseif ($sub['status'] === 'GRACE'): ?>
            Your period ended <?= e(format_date($sub['current_period_ends_at'])) ?> — renew before <?= e(format_date($sub['grace_ends_at'])) ?> to keep your access.
          <?php elseif ($sub['status'] === 'EXPIRED'): ?>
            Ended <?= e(format_date($sub['current_period_ends_at'])) ?>. Anything you bought individually from this school is still yours.
          <?php else: ?>
            Canceled — access continued until <?= e(format_date($sub['current_period_ends_at'])) ?>.
          <?php endif; ?>
        </p>

        <?php if ($needsRenewal): ?>
          <div style="margin-top:16px;" data-payment-widget
               data-creator-id="<?= (int) $sub['creator_id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-school-subscription.php')) ?>"
               data-success-redirect="<?= e(base_url('dashboard/learner/subscriptions.php')) ?>">
            <div data-state="idle">
              <button class="btn btn-gold shine" data-action="start">Renew Now</button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary" data-action="pay">Renew <?= e(format_money((float) $sub['price'])) ?></button>
            </div>
            <div data-state="waiting" class="hidden pay-waiting">
              <div class="spinner"></div>
              <p style="font-weight:700;">Waiting for approval...</p>
              <p class="small muted" data-status-text></p>
            </div>
            <div data-state="success" class="hidden pay-success">
              <p style="font-weight:700;">✓ Renewed!</p>
            </div>
            <div data-state="failed" class="hidden pay-failed">
              <p style="font-weight:700;">Payment not completed</p>
              <p class="small muted" data-fail-text></p>
              <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
            </div>
            <p class="error-text hidden" data-error></p>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
