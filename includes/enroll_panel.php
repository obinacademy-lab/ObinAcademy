<?php
/**
 * Renders the enroll / continue-learning / pay panel for a course detail page.
 * Expects $course (get_course_by_slug result), $user (current_user() or null),
 * $isOwner, $isEnrolled in scope.
 */
function render_enroll_panel(array $course, ?array $user, bool $isOwner, bool $isEnrolled): void {
    $price = (float) $course['price'];
    $hasSale = course_has_active_sale($course);
    $displayPrice = $hasSale ? (float) $course['sale_price'] : $price;
    $saleDaysLeft = $hasSale ? course_sale_days_left($course) : null;
    $isPublished = $course['status'] === 'PUBLISHED';
    $isEvent = $course['type'] === 'EVENT';
    $ticketsSold = $isEvent ? (int) $course['student_count'] : 0;
    $hasPassed = $isEvent && event_has_passed($course);
    $soldOut = $isEvent && event_is_sold_out($course, $ticketsSold);
    $showPaidFlow = $user && !$isEnrolled && !$isOwner && $isPublished && $price > 0 && !$hasPassed && !$soldOut;
    $loginUrl = base_url('login.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']));
    // Events require an account to pay (see api/initiate-payment.php), so
    // the buyer is always logged in the moment checkout succeeds — the
    // session-authenticated ticket.php?slug=... link works immediately,
    // no token needs to travel through the payment-success response.
    $ticketUrl = base_url('ticket.php?slug=' . $course['slug']);
    ?>
    <div class="enroll-panel reveal reveal-delay-2">
      <div class="thumb <?= $isEvent ? 'thumb-portrait' : '' ?>">
        <?php if (!empty($course['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($course['thumbnail_url'])) ?>" alt="">
        <?php else: ?>
          <div class="placeholder"><?php dash_icon('graduation-cap'); ?><span>Obin Academy</span></div>
        <?php endif; ?>
        <?php if ($hasSale): ?><span class="badge-pill badge-sale">🔥 <?= $saleDaysLeft !== null ? $saleDaysLeft . ' day' . ($saleDaysLeft === 1 ? '' : 's') . ' left' : 'On Sale' ?></span><?php endif; ?>
      </div>
      <div class="pad">
        <div class="price-row">
          <div class="price">
            <?php if ($hasSale): ?><span class="price-strike"><?= e(format_money($price)) ?></span><?php endif; ?>
            <?= $price > 0 ? e(format_money($displayPrice)) : 'Free' ?>
          </div>
          <?php if ($price > 0): ?><span class="price-note">one-time payment</span><?php endif; ?>
        </div>
        <?php if ($hasSale && $saleDaysLeft !== null): ?>
          <div class="sale-countdown">
            <?php dash_icon('clock'); ?>
            Price goes back to <?= e(format_money($price)) ?> in <?= $saleDaysLeft ?> day<?= $saleDaysLeft === 1 ? '' : 's' ?>
          </div>
        <?php endif; ?>
        <div class="access-note">
          <?php if ($isEvent): ?>
            <?php dash_icon('calendar'); ?>
            <?= $course['event_starts_at'] ? e(format_date($course['event_starts_at'])) . ' at ' . e(date('g:i A', strtotime($course['event_starts_at']))) : 'Date to be announced' ?>
          <?php else: ?>
            <?php dash_icon('clock'); ?>
            <?= $course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access after purchase' : 'Lifetime access' ?>
          <?php endif; ?>
        </div>

        <?php if ($isOwner): ?>
          <a href="<?= e(base_url('dashboard/creator/course-manage.php?id=' . $course['id'])) ?>" class="btn btn-dark btn-block btn-lg" style="margin-top:20px;">Manage <?= $isEvent ? 'Event' : 'Course' ?></a>
        <?php elseif ($isEnrolled): ?>
          <a href="<?= e($isEvent ? $ticketUrl : base_url('learn.php?slug=' . $course['slug'])) ?>" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;"><?= $isEvent ? '🎟 View My Ticket' : '▶ Continue Learning' ?></a>
        <?php elseif (!$isPublished): ?>
          <button class="btn btn-outline btn-block btn-lg" disabled style="margin-top:20px;">Not Yet Available</button>
        <?php elseif ($hasPassed): ?>
          <button class="btn btn-outline btn-block btn-lg" disabled style="margin-top:20px;">Event Has Ended</button>
        <?php elseif ($soldOut): ?>
          <button class="btn btn-outline btn-block btn-lg" disabled style="margin-top:20px;">Sold Out</button>
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
          <a href="<?= e(base_url('signup.php?redirect=' . urlencode('/courses/view.php?slug=' . $course['slug']))) ?>" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">Sign Up to Enroll</a>
          <p class="guest-note">Paid courses need a free account first — that's where your receipt, access, and certificate live. <a href="<?= e($loginUrl) ?>">Already have an account? Log in</a></p>
        <?php elseif ($showPaidFlow): ?>
          <div style="margin-top:20px;" data-payment-widget
               data-course-id="<?= (int) $course['id'] ?>"
               data-initiate-url="<?= e(base_url('api/initiate-payment.php')) ?>"
               data-success-redirect="<?= e($isEvent ? $ticketUrl : base_url('learn.php?slug=' . $course['slug'])) ?>">
            <div data-state="idle">
              <button class="btn btn-primary btn-block btn-lg" data-action="start">📱 Pay with Mobile Money</button>
            </div>
            <div data-state="phone" class="hidden guest-form">
              <div class="field-icon">
                <?php dash_icon('wallet'); ?>
                <input type="tel" placeholder="Mobile money phone e.g. 0772 123 456" data-phone-input>
              </div>
              <button class="btn btn-primary btn-block" data-action="pay">Pay <?= e(format_money($displayPrice)) ?></button>
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
          <?php if ($isEvent): ?>
            <li><?php dash_icon('check-circle'); ?>Instant e-ticket, emailed to you</li>
            <li><?php dash_icon('check-circle'); ?><?= $course['event_online_url'] ? 'Join from anywhere — online event' : e($course['event_location'] ?: 'In-person event') ?></li>
            <li><?php dash_icon('check-circle'); ?><?= $course['ticket_capacity'] !== null ? (int) $course['ticket_capacity'] . ' tickets total' : 'Open capacity' ?></li>
          <?php else: ?>
            <li><?php dash_icon('check-circle'); ?><?= $course['access_duration_days'] ? (int) $course['access_duration_days'] . ' days of access' : 'Lifetime access' ?></li>
            <li><?php dash_icon('check-circle'); ?>Stream video lessons and PDFs anytime</li>
            <li><?php dash_icon('check-circle'); ?><?= !empty($course['premium_price']) ? 'Downloads available with Premium (' . e(format_money((float) $course['premium_price'])) . ')' : 'Certificate of completion' ?></li>
            <li><?php dash_icon('check-circle'); ?>Learn on any device</li>
          <?php endif; ?>
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
