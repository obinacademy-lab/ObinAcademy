<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/subscriptions.php';

$user = current_user();
$mySubscription = $user ? get_subscription_for_user((int) $user['id']) : null;
if ($mySubscription && in_array($mySubscription['status'], ['ACTIVE', 'GRACE'], true)) {
    redirect('/dashboard/subscription.php');
}

$stats = get_platform_stats();

// Both tiers unlock the exact identical catalog — Pro is a higher monthly
// amount for members who want to support creators more directly, so its
// checklist gets one honest extra line, not a fake feature difference.
$planCopy = [
    'GO' => [
        'blurb' => 'Unlock every course on Obin Academy and start learning today — one plan, no upsells, no per-course pricing.',
        'perks' => [
            'Full access to every course, every category',
            'New courses included the moment they publish',
            'Certificate of completion on every course',
            'Stream video and PDF lessons on any device',
            'Pay and renew by MTN or Airtel Mobile Money',
            'Cancel anytime — no long contract',
        ],
    ],
    'PRO' => [
        'blurb' => 'The exact same full catalog as Go, at a higher monthly amount for members who want to back creators more directly.',
        'perks' => [
            'Full access to every course, every category',
            'New courses included the moment they publish',
            'Certificate of completion on every course',
            'Stream video and PDF lessons on any device',
            'Pay and renew by MTN or Airtel Mobile Money',
            'Cancel anytime — no long contract',
            'Directly supports the creators you learn from',
        ],
    ],
];

$pageTitle = 'Subscribe — Obin Academy';
$pageDescription = 'One monthly payment, full access to every course on Obin Academy. Choose Go, Plus, or Pro.';
require __DIR__ . '/includes/header.php';
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
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">Why Subscribe</span>
      <h2 class="h2" style="margin-top:10px;">One Plan Beats a Pile of Receipts</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Here's what members get that a one-off course purchase never could.</p>
    </div>
    <div class="why-grid">
      <div class="why-card reveal reveal-delay-1">
        <div class="n">📚</div>
        <h3>Every Course, Not Just One</h3>
        <p>Stop paying per course. One subscription unlocks the entire catalog — today's courses and every one a creator adds next month.</p>
      </div>
      <div class="why-card reveal reveal-delay-2">
        <div class="n">📱</div>
        <h3>Pay the Way You Already Do</h3>
        <p>MTN or Airtel Mobile Money, approved on your phone in seconds — no card, no bank account, no foreign currency.</p>
      </div>
      <div class="why-card reveal reveal-delay-3">
        <div class="n">🏆</div>
        <h3>Proof You Can Show</h3>
        <p>Finish a course and get a certificate of completion — something to attach to a CV or share with an employer.</p>
      </div>
      <div class="why-card reveal reveal-delay-1">
        <div class="n">🔓</div>
        <h3>No Long Contract</h3>
        <p>Cancel from your dashboard whenever you want. No call to make, no form to fill, no penalty for stopping.</p>
      </div>
      <div class="why-card reveal reveal-delay-2">
        <div class="n">💬</div>
        <h3>Ask, Don't Just Watch</h3>
        <p>Comment on any lesson and get answers from the creator or other learners — you're never stuck alone on a hard topic.</p>
      </div>
      <div class="why-card reveal reveal-delay-3">
        <div class="n">🌍</div>
        <h3>Built By People Who Get It</h3>
        <p>Every course is taught by an East African creator solving problems they've actually faced — not a generic import.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:var(--surface);">
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
      <div class="plans-grid">
        <?php foreach (SUBSCRIPTION_TIERS as $tierKey => $tierInfo): $plan = $planCopy[$tierKey]; ?>
          <div class="plan-card reveal">
            <div class="plan-card-glow" aria-hidden="true"></div>
            <span class="plan-tag"><?php dash_icon('crown'); ?><?= e($tierInfo['label']) ?></span>
            <div class="plan-price">
              <span class="amount"><?= e(format_money((float) $tierInfo['price'])) ?></span>
              <span class="period">/ month</span>
            </div>
            <p class="plan-billing-note">Billed Monthly</p>
            <p class="plan-blurb"><?= e($plan['blurb']) ?></p>

            <ul class="check-list">
              <?php foreach ($plan['perks'] as $perk): ?>
                <li>
                  <span class="check-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg></span>
                  <span class="label-text"><?= e($perk) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <div data-payment-widget
                 data-tier="<?= e($tierKey) ?>"
                 data-initiate-url="<?= e(base_url('api/initiate-subscription.php')) ?>"
                 data-success-redirect="<?= e(base_url('dashboard/subscription.php')) ?>">
              <div data-state="idle">
                <button class="btn btn-gold btn-block btn-lg" data-action="start">📱 Get <?= e($tierInfo['label']) ?> Access Now</button>
              </div>
              <div data-state="phone" class="hidden guest-form">
                <div class="field-icon">
                  <?php dash_icon('wallet'); ?>
                  <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
                </div>
                <button class="btn btn-gold btn-block" data-action="pay">Pay <?= e(format_money((float) $tierInfo['price'])) ?></button>
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
                <button class="btn btn-gold btn-sm" data-action="retry">Try Again</button>
              </div>
              <p class="error-text hidden" data-error></p>
            </div>
            <p class="plan-disclaimer">Pay right here on the page with MTN or Airtel Mobile Money — no redirect, no card needed.</p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<script src="<?= e(versioned_asset('assets/js/payment.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
