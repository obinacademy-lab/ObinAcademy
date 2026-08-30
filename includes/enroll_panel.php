<?php
/**
 * Renders the enroll / continue-learning / pay panel for a course detail page.
 * Expects $course (get_course_by_slug result), $user (current_user() or null),
 * $isOwner, $isEnrolled in scope.
 */
function render_enroll_panel(array $course, ?array $user, bool $isOwner, bool $isEnrolled): void {
    $price = (float) $course['price'];
    $isPublished = $course['status'] === 'PUBLISHED';
    $showPaidFlow = $user && !$isEnrolled && !$isOwner && $isPublished && $price > 0;
    $loginUrl = base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']));
    ?>
    <div class="enroll-panel reveal reveal-delay-2">
      <div class="thumb">
        <?php if (!empty($course['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($course['thumbnail_url'])) ?>" alt="">
        <?php else: ?>
          <div class="placeholder"><?php dash_icon('graduation-cap'); ?><span>Obin Academy</span></div>
        <?php endif; ?>
      </div>
      <div class="pad">
        <div class="price-row">
          <div class="price"><?= $price > 0 ? e(format_money($price)) : 'Free' ?></div>
          <?php if ($price > 0): ?><span class="price-note">one-time payment</span><?php endif; ?>
        </div>
        <div class="access-note">
          <?php dash_icon('clock'); ?>
          <?= $course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access after purchase' : 'Lifetime access' ?>
        </div>

        <?php if ($isOwner): ?>
          <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $course['id'])) ?>" class="btn btn-dark btn-block btn-lg" style="margin-top:20px;">Manage Course</a>
        <?php elseif ($isEnrolled): ?>
          <a href="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">▶ Continue Learning</a>
        <?php elseif (!$isPublished): ?>
          <button class="btn btn-outline btn-block btn-lg" disabled style="margin-top:20px;">Not Yet Available</button>
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
            <button type="submit" class="btn btn-primary btn-block btn-lg">Get Free Access</button>
          </form>
          <p class="guest-note">We'll send you a link to access this course — no account needed. <a href="<?= e($loginUrl) ?>">Have an account? Log in</a></p>
        <?php elseif (!$user): ?>
          <div style="margin-top:20px;" data-payment-widget data-guest="1"
               data-course-id="<?= (int) $course['id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-payment.php')) ?>">
            <div data-state="idle">
              <button class="btn btn-primary btn-block btn-lg" data-action="start">📱 Pay with Mobile Money</button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('user-plus'); ?>
                <input placeholder="Your name" data-name-input>
              </div>
              <div class="field-icon">
                <?php dash_icon('scroll-text'); ?>
                <input type="email" placeholder="Email address" data-email-input>
              </div>
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary btn-block" data-action="pay">Pay <?= e(format_money($price)) ?></button>
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
          <p class="guest-note">We'll send you a link to access this course — no account needed. <a href="<?= e($loginUrl) ?>">Have an account? Log in</a></p>
        <?php elseif ($showPaidFlow): ?>
          <div style="margin-top:20px;" data-payment-widget
               data-course-id="<?= (int) $course['id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-payment.php')) ?>"
               data-success-redirect="<?= e(base_url('learn.php?slug=' . $course['slug'])) ?>">
            <div data-state="idle">
              <button class="btn btn-primary btn-block btn-lg" data-action="start">📱 Pay with Mobile Money</button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary btn-block" data-action="pay">Pay <?= e(format_money($price)) ?></button>
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
        <?php else: ?>
          <form method="post" action="<?= e(base_url('api/enroll-redirect.php')) ?>" style="margin-top:20px;">
            <input type="hidden" name="courseId" value="<?= (int) $course['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Enroll Now</button>
          </form>
        <?php endif; ?>

        <ul class="perks">
          <li><?php dash_icon('check-circle'); ?><?= $course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access' : 'Lifetime access' ?></li>
          <li><?php dash_icon('check-circle'); ?>Stream video lessons and PDFs anytime</li>
          <li><?php dash_icon('check-circle'); ?><?= !empty($course['premium_price']) ? 'Downloads available with Premium (' . e(format_money((float) $course['premium_price'])) . ')' : 'Certificate of completion' ?></li>
          <li><?php dash_icon('check-circle'); ?>Learn on any device</li>
        </ul>

        <div class="enroll-trust">
          <div class="pay-badges">
            <span class="pay-badge pay-badge-mtn">MTN Mobile Money</span>
            <span class="pay-badge pay-badge-airtel">Airtel Money</span>
          </div>
          <div class="secure-note"><?php dash_icon('shield'); ?>Secure checkout</div>
        </div>
      </div>
    </div>
    <?php
}
