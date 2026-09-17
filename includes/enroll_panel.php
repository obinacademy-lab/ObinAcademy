<?php
require_once __DIR__ . '/school_subscriptions.php';
require_once __DIR__ . '/installments.php';
require_once __DIR__ . '/gifts.php';

/**
 * Renders the enroll / continue-learning / pay / subscribe panel for a
 * course detail page. Expects $course (get_course_by_slug result) in
 * scope, including the creator_pricing_model/creator_school_monthly_price/
 * creator_school_name columns that join adds. $isInterested reflects an
 * existing opt-in row in course_interest — only shown as a toggle to a
 * logged-in, non-owner learner who hasn't bought the course yet.
 */
function render_enroll_panel(array $course, ?array $user, bool $isOwner, bool $isEnrolled, bool $isInterested = false): void {
    $price = (float) $course['price'];
    $hasSale = course_has_active_sale($course);
    $displayPrice = $hasSale ? (float) $course['sale_price'] : $price;
    $saleDaysLeft = $hasSale ? course_sale_days_left($course) : null;
    $isPublished = $course['status'] === 'PUBLISHED';

    // Once a creator's school is in subscription mode, every course they
    // publish is subscription-gated by default — no more ad-hoc free/paid
    // per course, even if this course's own price/sale fields are still set
    // from before they switched. A creator can still opt a specific course
    // OUT of the subscription (courses.subscription_included = 0) to sell
    // it separately at its own price on top of the base subscription — see
    // dashboard/creator/course-manage.php. A learner who already owns this
    // course from before the switch keeps it via $isEnrolled, checked first
    // below, regardless of which mode it's in now.
    $schoolHasSubscription = ($course['creator_pricing_model'] ?? 'PER_COURSE') === 'MONTHLY_SUBSCRIPTION'
        && (float) ($course['creator_school_monthly_price'] ?? 0) > 0;
    $isSubscriptionIncluded = $schoolHasSubscription && (int) ($course['subscription_included'] ?? 1) === 1;
    $isSubscribed = $user && $isSubscriptionIncluded && learner_has_active_school_subscription((int) $user['id'], (int) $course['creator_user_id'], (int) $course['id']);
    $hasAccess = $isEnrolled || $isSubscribed;
    // An enrollment created by an installment plan's first payment still
    // exists as a normal row once the plan defaults — access itself is
    // gated live here, not by deleting that row, so paying again later just
    // picks back up. Never touches an enrollment created any other way.
    $installmentPlan = $user ? get_installment_plan((int) $user['id'], (int) $course['id']) : null;
    $installmentAccessBlocked = $installmentPlan && !learner_has_installment_access($installmentPlan);
    if ($installmentAccessBlocked) $hasAccess = false;
    $courseSupportsInstallments = (int) ($course['installments_enabled'] ?? 0) === 1 && (int) ($course['installment_count'] ?? 0) >= 2;
    $schoolLabel = $course['creator_school_name'] ?: $course['creator_name'];
    $monthlyPrice = (float) ($course['creator_school_monthly_price'] ?? 0);
    // A subscription unlocks only ONE course at a time per creator — if this
    // learner already has an active subscription to a different course from
    // this same creator, subscribing here switches their access to this
    // course instead (the other one locks again). Surfaced so they don't
    // pay expecting to keep both.
    $otherActiveSub = $user && $isSubscriptionIncluded && !$isSubscribed
        ? db_one(
            "SELECT c.title FROM school_subscriptions ss JOIN courses c ON c.id = ss.course_id
             WHERE ss.learner_id = ? AND ss.creator_id = ? AND ss.status IN ('ACTIVE','GRACE') LIMIT 1",
            [(int) $user['id'], (int) $course['creator_user_id']]
        )
        : null;

    $showPaidFlow = $user && !$hasAccess && !$isOwner && $isPublished && !$isSubscriptionIncluded && $price > 0;
    $showSubscribeFlow = $user && !$hasAccess && !$isOwner && $isPublished && $isSubscriptionIncluded;
    $loginUrl = base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']));
    ?>
    <div class="enroll-panel reveal reveal-delay-2">
      <div class="thumb">
        <?php if (!empty($course['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($course['thumbnail_url'])) ?>" alt="">
        <?php else: ?>
          <div class="placeholder"><?php dash_icon('graduation-cap'); ?><span>Obin Academy</span></div>
        <?php endif; ?>
        <?php if (!$isSubscriptionIncluded && $hasSale): ?><span class="badge-pill badge-sale">🔥 <?= $saleDaysLeft !== null ? $saleDaysLeft . ' day' . ($saleDaysLeft === 1 ? '' : 's') . ' left' : 'On Sale' ?></span><?php endif; ?>
      </div>
      <div class="pad">
        <?php if ($isSubscriptionIncluded): ?>
          <div class="price-row">
            <div class="price"><?= e(format_money($monthlyPrice)) ?></div>
            <span class="price-note" data-price-note>per month</span>
          </div>
          <div class="access-note">
            <?php dash_icon('graduation-cap'); ?>
            Access to this course while subscribed
          </div>
          <?php if ($otherActiveSub): ?>
            <p class="small muted" style="margin-top:10px;">
              You're currently subscribed to <strong><?= e($otherActiveSub['title']) ?></strong>. Subscribing here switches your access to this course instead — one course at a time per school.
            </p>
          <?php endif; ?>
        <?php else: ?>
          <div class="price-row">
            <div class="price">
              <?php if ($hasSale): ?><span class="price-strike"><?= e(format_money($price)) ?></span><?php endif; ?>
              <span data-top-price-amount data-unit-price="<?= (int) $displayPrice ?>"><?= $price > 0 ? e(format_money($displayPrice)) : 'Free' ?></span>
            </div>
            <?php if ($price > 0): ?><span class="price-note" data-price-note>one-time payment</span><?php endif; ?>
          </div>
          <?php if ($hasSale && $saleDaysLeft !== null): ?>
            <div class="sale-countdown">
              <?php dash_icon('clock'); ?>
              Price goes back to <?= e(format_money($price)) ?> in <?= $saleDaysLeft ?> day<?= $saleDaysLeft === 1 ? '' : 's' ?>
            </div>
          <?php endif; ?>
          <div class="access-note">
            <?php dash_icon('clock'); ?>
            <?= $course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access after purchase' : 'Lifetime access' ?>
          </div>
          <?php if ($schoolHasSubscription): ?>
            <p class="small muted" style="margin-top:10px;">Sold separately — not included in the <?= e($schoolLabel) ?> subscription.</p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($isOwner): ?>
          <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $course['id'])) ?>" class="btn btn-dark btn-block btn-lg" style="margin-top:20px;">Manage Course</a>
        <?php elseif ($hasAccess): ?>
          <a href="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">▶ Continue Learning</a>
        <?php elseif (!$isPublished): ?>
          <button class="btn btn-outline btn-block btn-lg" disabled style="margin-top:20px;">Not Yet Available</button>
        <?php elseif ($isSubscriptionIncluded && !$user): ?>
          <a href="<?= e(base_url('signup.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" class="btn btn-gold btn-block btn-lg shine" style="margin-top:20px;">Sign Up to Subscribe</a>
          <p class="guest-note">A subscription to <?= e($schoolLabel) ?> needs a free account first — that's where your receipt, access, and subscription live. <a href="<?= e($loginUrl) ?>">Already have an account? Log in</a></p>
        <?php elseif ($showSubscribeFlow): ?>
          <div style="margin-top:20px;" data-payment-widget
               data-creator-id="<?= (int) $course['creator_user_id'] ?>"
               data-course-id="<?= (int) $course['id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-school-subscription.php')) ?>"
               data-success-redirect="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>">
            <div data-state="idle">
              <button class="btn btn-gold btn-block btn-lg shine" data-action="start">
                <span class="pay-logo-pair">
                  <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN"></span>
                  <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel"></span>
                </span>
                Subscribe to This Course
              </button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary btn-block" data-action="pay">Subscribe <?= e(format_money($monthlyPrice)) ?>/mo</button>
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
        <?php elseif (!$user && $price <= 0): ?>
          <form method="post" action="<?= e(base_url('api/enroll-guest.php')) ?>" class="guest-form" style="margin-top:20px;">
            <input type="hidden" name="courseId" value="<?= (int) $course['id'] ?>">
            <?= csrf_field() ?>
            <div class="field-icon">
              <?php dash_icon('user-plus'); ?>
              <input name="name" required placeholder="Your name">
            </div>
            <div class="field-icon">
              <?php dash_icon('scroll-text'); ?>
              <input name="email" type="email" required placeholder="Email address">
            </div>
            <button type="submit" class="btn btn-gold btn-block btn-lg shine">Get Free Access</button>
          </form>
          <p class="guest-note">We'll send you a link to access this course — no account needed. <a href="<?= e($loginUrl) ?>">Have an account? Log in</a></p>
        <?php elseif (!$user): ?>
          <a href="<?= e(base_url('signup.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" class="btn btn-gold btn-block btn-lg shine" style="margin-top:20px;">Sign Up to Enroll</a>
          <p class="guest-note">Paid courses need a free account first — that's where your receipt, access, and certificate live. <a href="<?= e($loginUrl) ?>">Already have an account? Log in</a></p>
        <?php elseif ($installmentAccessBlocked): ?>
          <div class="alert alert-error" style="margin-top:20px;">Access paused — a payment plan installment was missed.</div>
          <div style="margin-top:14px;" data-payment-widget
               data-course-id="<?= (int) $course['id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-installment-payment.php')) ?>"
               data-success-redirect="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>">
            <div data-state="idle">
              <button class="btn btn-gold btn-block btn-lg shine" data-action="start">Pay Next Installment to Resume</button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary btn-block" data-action="pay">Pay <?= e(format_money((float) $installmentPlan['installment_amount'])) ?></button>
            </div>
            <div data-state="waiting" class="hidden pay-waiting">
              <div class="spinner"></div>
              <p style="font-weight:700;">Waiting for approval...</p>
              <p class="small muted" data-status-text></p>
            </div>
            <div data-state="success" class="hidden pay-success">
              <p style="font-weight:700;">✓ Access restored!</p>
            </div>
            <div data-state="failed" class="hidden pay-failed">
              <p style="font-weight:700;">Payment not completed</p>
              <p class="small muted" data-fail-text></p>
              <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
            </div>
            <p class="error-text hidden" data-error></p>
          </div>
        <?php elseif ($showPaidFlow): ?>
          <div class="coupon-box" data-coupon-box data-preview-url="<?= e(base_url('api/preview-coupon.php')) ?>" data-course-id="<?= (int) $course['id'] ?>" style="margin-top:14px;">
            <button type="button" class="coupon-toggle" data-coupon-toggle>Have a coupon code?</button>
            <div class="coupon-apply-row hidden" data-coupon-row>
              <input type="text" placeholder="Coupon code" data-coupon-input>
              <button type="button" class="btn btn-outline btn-sm" data-coupon-apply>Apply</button>
            </div>
            <p class="coupon-msg" data-coupon-msg hidden></p>
          </div>
          <div style="margin-top:14px;" data-payment-widget
               data-course-id="<?= (int) $course['id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-payment.php')) ?>"
               data-success-redirect="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>">
            <div data-state="idle">
              <button class="btn btn-gold btn-block btn-lg shine" data-action="start">
                <span class="pay-logo-pair">
                  <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN"></span>
                  <span class="pay-logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel"></span>
                </span>
                Pay with Mobile Money
              </button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary btn-block" data-action="pay">Pay <span data-pay-amount data-unit-price="<?= (int) $displayPrice ?>"><?= e(format_money($displayPrice)) ?></span></button>
            </div>
            <div data-state="waiting" class="hidden pay-waiting">
              <div class="spinner"></div>
              <p style="font-weight:700;">Waiting for approval...</p>
              <p class="small muted" data-status-text></p>
            </div>
            <div data-state="success" class="hidden pay-success">
              <p style="font-weight:700;">✓ Payment successful!</p>
            </div>
            <div data-state="failed" class="hidden pay-failed">
              <p style="font-weight:700;">Payment not completed</p>
              <p class="small muted" data-fail-text></p>
              <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
            </div>
            <p class="error-text hidden" data-error></p>
          </div>
          <?php if ($courseSupportsInstallments && !$installmentPlan):
            // Installments always split the regular price, same as
            // initiate_installment_payment() — not the current sale price,
            // which could change or expire between installments.
            $installmentAmount = round($price / (int) $course['installment_count'], 2);
          ?>
            <div style="margin-top:14px;" data-payment-widget
                 data-course-id="<?= (int) $course['id'] ?>"
                 data-initiate-url="<?= e(base_url('api/initiate-installment-payment.php')) ?>"
                 data-success-redirect="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>">
              <div data-state="idle">
                <button class="btn btn-outline btn-block" data-action="start">Or pay in <?= (int) $course['installment_count'] ?> installments of <?= e(format_money($installmentAmount)) ?></button>
              </div>
              <div data-state="phone" class="hidden guest-form">
                <div class="field-icon">
                  <?php dash_icon('wallet'); ?>
                  <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
                </div>
                <button class="btn btn-primary btn-block" data-action="pay">Pay First Installment: <?= e(format_money($installmentAmount)) ?></button>
              </div>
              <div data-state="waiting" class="hidden pay-waiting">
                <div class="spinner"></div>
                <p style="font-weight:700;">Waiting for approval...</p>
                <p class="small muted" data-status-text></p>
              </div>
              <div data-state="success" class="hidden pay-success">
                <p style="font-weight:700;">✓ First installment paid — you're in!</p>
              </div>
              <div data-state="failed" class="hidden pay-failed">
                <p style="font-weight:700;">Payment not completed</p>
                <p class="small muted" data-fail-text></p>
                <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
              </div>
              <p class="error-text hidden" data-error></p>
            </div>
            <p class="small muted" style="margin-top:8px;">Full access unlocks after the first payment. Remaining installments are collected every <?= INSTALLMENT_INTERVAL_DAYS ?> days.</p>
          <?php endif; ?>
        <?php else: ?>
          <form method="post" action="<?= e(base_url('api/enroll-redirect.php')) ?>" style="margin-top:20px;">
            <input type="hidden" name="courseId" value="<?= (int) $course['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-gold btn-block btn-lg shine">Enroll Now</button>
          </form>
        <?php endif; ?>

        <?php if ($user && !$isOwner && !$hasAccess && $isPublished): ?>
          <button type="button" class="interest-toggle <?= $isInterested ? 'is-interested' : '' ?>" data-interest-toggle data-course-id="<?= (int) $course['id'] ?>" data-toggle-url="<?= e(base_url('api/toggle-course-interest.php')) ?>">
            <?php dash_icon($isInterested ? 'check-circle' : 'sparkle'); ?>
            <span data-interest-label><?= $isInterested ? "You're on the list — the creator can reach out" : 'Not ready to buy? Keep me updated' ?></span>
          </button>
        <?php endif; ?>

        <ul class="perks">
          <li><?php dash_icon('check-circle'); ?><?= $isSubscriptionIncluded ? 'Access while subscribed' : ($course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access' : 'Lifetime access') ?></li>
          <li><?php dash_icon('check-circle'); ?>Stream video lessons and PDFs anytime</li>
          <li><?php dash_icon('check-circle'); ?><?= !empty($course['premium_price']) ? 'Downloads available with Premium (' . e(format_money((float) $course['premium_price'])) . ')' : 'Certificate of completion' ?></li>
          <li><?php dash_icon('check-circle'); ?>Learn on any device</li>
        </ul>

        <?php
          $canGiftSubscription = $isSubscriptionIncluded && $monthlyPrice > 0;
          $canGiftCourse = !$isSubscriptionIncluded && $price > 0;
        ?>
        <?php if ($user && !$isOwner && $isPublished && ($canGiftCourse || $canGiftSubscription)): ?>
          <div class="gift-box" style="margin-top:16px;">
            <button type="button" class="coupon-toggle" data-gift-toggle><?php dash_icon('gift'); ?> Gift this course to someone</button>
            <div class="hidden" data-gift-row style="margin-top:10px;" data-payment-widget
                 data-course-id="<?= (int) $course['id'] ?>"
                 data-initiate-url="<?= e(base_url('api/initiate-gift-payment.php')) ?>"
                 data-success-redirect="<?= e(base_url('dashboard/gifts.php')) ?>">
              <div data-state="idle">
                <div class="field-icon"><input data-recipient-name-input placeholder="Recipient's name"></div>
                <div class="field-icon" style="margin-top:8px;"><input data-recipient-email-input type="email" placeholder="Recipient's email"></div>
                <?php if ($canGiftSubscription): ?>
                  <p class="small muted" style="margin-top:10px;">This school is subscription-based — choose how many months to gift:</p>
                  <div class="stack gap-2" style="margin-top:8px;">
                    <?php foreach (GIFT_SUBSCRIPTION_MONTH_OPTIONS as $m): ?>
                      <button class="btn btn-outline btn-block" data-action="start" data-months="<?= $m ?>" data-amount="<?= e(format_money($monthlyPrice * $m)) ?>">
                        <?= $m ?> Month<?= $m > 1 ? 's' : '' ?> — <?= e(format_money($monthlyPrice * $m)) ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <button class="btn btn-primary btn-block" style="margin-top:10px;" data-action="start">Continue</button>
                <?php endif; ?>
              </div>
              <div data-state="phone" class="hidden guest-form">
                <div class="field-icon">
                  <?php dash_icon('wallet'); ?>
                  <input type="tel" placeholder="Your mobile money phone e.g. 0772 123 456" data-phone-input>
                </div>
                <button class="btn btn-gold btn-block" data-action="pay">Pay <span data-pay-amount><?= $canGiftSubscription ? e(format_money($monthlyPrice * GIFT_SUBSCRIPTION_MONTH_OPTIONS[0])) : e(format_money($price)) ?></span> as a Gift</button>
              </div>
              <div data-state="waiting" class="hidden pay-waiting">
                <div class="spinner"></div>
                <p style="font-weight:700;">Waiting for approval...</p>
                <p class="small muted" data-status-text></p>
              </div>
              <div data-state="success" class="hidden pay-success">
                <p style="font-weight:700;">✓ Gift sent! We emailed them a claim link.</p>
              </div>
              <div data-state="failed" class="hidden pay-failed">
                <p style="font-weight:700;">Payment not completed</p>
                <p class="small muted" data-fail-text></p>
                <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
              </div>
              <p class="error-text hidden" data-error></p>
            </div>
          </div>
          <script>
            (() => {
              const toggle = document.currentScript.previousElementSibling.querySelector('[data-gift-toggle]');
              const row = document.currentScript.previousElementSibling.querySelector('[data-gift-row]');
              if (!toggle || !row) return;
              toggle.addEventListener('click', () => row.classList.toggle('hidden'));
            })();
          </script>
        <?php endif; ?>

        <div class="enroll-trust">
          <div class="pay-badges">
            <span class="pay-badge pay-badge-mtn"><span class="logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-mtn-logo.jpg')) ?>" alt="MTN"></span>MTN Mobile Money</span>
            <span class="pay-badge pay-badge-airtel"><span class="logo-chip"><img src="<?= e(versioned_asset('assets/img/trust-airtel-logo.png')) ?>" alt="Airtel"></span>Airtel Money</span>
          </div>
          <div class="secure-note"><?php dash_icon('shield'); ?>Secure checkout</div>
        </div>
      </div>
    </div>
    <?php
}
