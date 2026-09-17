<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/installments.php';
$user = require_login();

$plans = get_installment_plans_for_learner((int) $user['id']);

$statusBadge = ['ACTIVE' => 'badge-published', 'GRACE' => 'badge-pending', 'COMPLETED' => 'badge-published', 'DEFAULTED' => 'badge-rejected'];
$statusLabel = ['ACTIVE' => 'Active', 'GRACE' => 'Pay Now', 'COMPLETED' => 'Paid Off', 'DEFAULTED' => 'Paused'];

$pageTitle = 'Payment Plans — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Payment Plans</h1>
<p class="muted" style="margin-top:6px;">Courses you're paying for in installments — full access unlocked after the first payment.</p>

<?php if (!$plans): ?>
  <div class="card card-pad" style="text-align:center; margin-top:24px; border-style:dashed;">
    <p class="muted">You don't have any payment plans yet.</p>
    <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Explore Courses</a>
  </div>
<?php else: ?>
  <div class="stack gap-3" style="margin-top:24px; max-width:640px;">
    <?php foreach ($plans as $plan):
      $schoolLabel = $plan['creator_school_name'] ?: $plan['creator_name'];
      $needsPayment = in_array($plan['status'], ['GRACE', 'DEFAULTED'], true);
      $remaining = (int) $plan['installment_count'] - (int) $plan['installments_paid'];
      $nextInstallmentNumber = (int) $plan['installments_paid'] + 1;
      $isLastInstallment = $nextInstallmentNumber >= (int) $plan['installment_count'];
      $nextAmount = $isLastInstallment
          ? round((float) $plan['total_amount'] - ((float) $plan['installment_amount'] * $plan['installments_paid']), 2)
          : (float) $plan['installment_amount'];
    ?>
      <div class="card card-pad">
        <div class="row between wrap gap-2">
          <div>
            <a href="<?= e(base_url('courses/view.php?slug=' . $plan['course_slug'])) ?>" style="font-weight:700; color:var(--ink);"><?= e($plan['course_title']) ?></a>
            <p class="small muted" style="margin-top:2px;"><?= e($schoolLabel) ?> &middot; <?= (int) $plan['installments_paid'] ?> of <?= (int) $plan['installment_count'] ?> paid</p>
          </div>
          <span class="badge <?= e($statusBadge[$plan['status']] ?? 'badge-draft') ?>"><?= e($statusLabel[$plan['status']] ?? $plan['status']) ?></span>
        </div>

        <div class="progress-track" style="margin-top:14px;"><div class="progress-fill" style="width:<?= round(((int) $plan['installments_paid'] / (int) $plan['installment_count']) * 100) ?>%;"></div></div>

        <p class="small muted" style="margin-top:14px;">
          <?php if ($plan['status'] === 'COMPLETED'): ?>
            Fully paid off on <?= e(format_date($plan['completed_at'])) ?> — this course is yours for good.
          <?php elseif ($plan['status'] === 'ACTIVE'): ?>
            Next payment (<?= (int) $remaining ?> left) due <?= e(format_date($plan['next_due_at'])) ?>.
          <?php elseif ($plan['status'] === 'GRACE'): ?>
            Payment was due <?= e(format_date($plan['next_due_at'])) ?> — pay before <?= e(format_date($plan['grace_ends_at'])) ?> to keep your access.
          <?php else: ?>
            Access is paused — pay your next installment any time to resume right where you left off.
          <?php endif; ?>
        </p>

        <?php if ($plan['status'] !== 'COMPLETED'): ?>
          <div style="margin-top:16px;" data-payment-widget
               data-course-id="<?= (int) $plan['course_id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-installment-payment.php')) ?>"
               data-success-redirect="<?= e(base_url('dashboard/learner/payment-plans.php')) ?>">
            <div data-state="idle">
              <button class="btn <?= $needsPayment ? 'btn-gold shine' : 'btn-outline' ?>" data-action="start">Pay Installment <?= $nextInstallmentNumber ?> of <?= (int) $plan['installment_count'] ?></button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary" data-action="pay">Pay <?= e(format_money($nextAmount)) ?></button>
            </div>
            <div data-state="waiting" class="hidden pay-waiting">
              <div class="spinner"></div>
              <p style="font-weight:700;">Waiting for approval...</p>
              <p class="small muted" data-status-text></p>
            </div>
            <div data-state="success" class="hidden pay-success">
              <p style="font-weight:700;">✓ Paid!</p>
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
