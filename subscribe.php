<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/subscriptions.php';

$user = current_user();
$mySubscription = $user ? get_subscription_for_user((int) $user['id']) : null;
if ($mySubscription && in_array($mySubscription['status'], ['ACTIVE', 'GRACE'], true)) {
    redirect('/dashboard/subscription.php');
}

$stats = get_platform_stats();

$pageTitle = 'Subscribe — Obin Academy';
$pageDescription = 'One monthly payment, full access to every course on Obin Academy. Choose Go, Plus, or Pro.';
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero course-hero-centered">
  <div class="container">
    <span class="pill"><?php dash_icon('sparkle'); ?>Unlimited Learning</span>
    <h1>One Subscription. Every Course.</h1>
    <p class="summary">Pick a plan and get full access to every course on Obin Academy for 30 days — renews automatically by mobile money, cancel any time.</p>
  </div>
</section>

<?php render_stat_strip($stats); ?>

<section class="section">
  <div class="container">
    <?php if (!$user): ?>
      <div class="card card-pad" style="max-width:480px; margin:0 auto; text-align:center;">
        <p class="muted">Log in or create a free account first to subscribe.</p>
        <div class="row gap-2 center" style="margin-top:14px;">
          <a href="<?= e(base_url('login.php?redirect=/subscribe.php')) ?>" class="btn btn-outline">Log In</a>
          <a href="<?= e(base_url('signup.php?redirect=/subscribe.php')) ?>" class="btn btn-primary">Sign Up</a>
        </div>
      </div>
    <?php else: ?>
      <div class="grid md:grid-3" style="gap:24px; max-width:960px; margin:0 auto;">
        <?php foreach (SUBSCRIPTION_TIERS as $tierKey => $tierInfo): ?>
          <div class="card card-pad">
            <h2 class="h3"><?= e($tierInfo['label']) ?></h2>
            <p class="muted small" style="margin-top:6px;"><?= e($tierInfo['tagline']) ?></p>
            <div style="margin-top:16px;">
              <span style="font-size:32px; font-weight:800;"><?= e(format_money((float) $tierInfo['price'])) ?></span>
              <span class="muted small">/ month</span>
            </div>

            <div style="margin-top:20px;" data-payment-widget
                 data-tier="<?= e($tierKey) ?>"
                 data-initiate-url="<?= e(base_url('api/initiate-subscription.php')) ?>"
                 data-success-redirect="<?= e(base_url('dashboard/subscription.php')) ?>">
              <div data-state="idle">
                <button class="btn btn-primary btn-block btn-lg" data-action="start">📱 Subscribe with Mobile Money</button>
              </div>
              <div data-state="phone" class="hidden guest-form">
                <div class="field-icon">
                  <?php dash_icon('wallet'); ?>
                  <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
                </div>
                <button class="btn btn-primary btn-block" data-action="pay">Pay <?= e(format_money((float) $tierInfo['price'])) ?></button>
              </div>
              <div data-state="waiting" class="hidden pay-waiting">
                <div class="spinner"></div>
                <p style="font-weight:700;">Waiting for approval...</p>
                <p class="small muted" data-status-text></p>
              </div>
              <div data-state="success" class="hidden pay-success">
                <p style="font-weight:700;">✓ Subscribed!</p>
              </div>
              <div data-state="failed" class="hidden pay-failed">
                <p style="font-weight:700;">Payment not completed</p>
                <p class="small muted" data-fail-text></p>
                <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
              </div>
              <p class="error-text hidden" data-error></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
