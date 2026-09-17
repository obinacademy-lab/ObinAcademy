<?php
require_once __DIR__ . '/school_subscriptions.php';

// A logged-in buyer pays for one course as a gift for someone else by
// email — the buyer's own account never gets access to it. On payment
// success a course_gifts row is created (PENDING) and the recipient is
// emailed a claim link carrying a bearer token (same make_access_token()
// pattern as a guest's access link — only the hash is stored). Claiming
// requires an account (creating one if needed) so the course lands in a
// real learner dashboard rather than staying an anonymous access token.
//
// Two kinds share this same flow (see migration/add-subscription-gifts.sql):
// COURSE is a normal one-time purchase gifted to someone else. SUBSCRIPTION
// is a fixed number of months of access to one course from a
// MONTHLY_SUBSCRIPTION school — claiming it creates/extends a real
// school_subscriptions row for the recipient, exactly as if they'd
// subscribed and paid for those months themselves.
const GIFT_SUBSCRIPTION_MONTH_OPTIONS = [1, 3, 6, 12];

/**
 * Whether $course (a get_course_by_slug()-shaped row, with the
 * creator_pricing_model/creator_school_monthly_price/subscription_included
 * columns that join adds) can be gifted at all, and which kind. Shared by
 * enroll_panel.php (gifting the one course already on screen) and
 * gift.php (gifting any course picked from a platform-wide browser) so
 * both gate on the exact same rule.
 */
function gift_eligibility_for_course(array $course): array {
    $schoolHasSubscription = ($course['creator_pricing_model'] ?? 'PER_COURSE') === 'MONTHLY_SUBSCRIPTION'
        && (float) ($course['creator_school_monthly_price'] ?? 0) > 0;
    $isSubscriptionIncluded = $schoolHasSubscription && (int) ($course['subscription_included'] ?? 1) === 1;
    $monthlyPrice = (float) ($course['creator_school_monthly_price'] ?? 0);
    $price = (float) $course['price'];
    $canGiftSubscription = $isSubscriptionIncluded && $monthlyPrice > 0;
    $canGiftCourse = !$isSubscriptionIncluded && $price > 0;
    return [
        'available' => $canGiftCourse || $canGiftSubscription,
        'isSubscription' => $canGiftSubscription,
        'monthlyPrice' => $monthlyPrice,
        'price' => $price,
    ];
}

/**
 * Renders the gift-a-course card (recipient fields, personal message,
 * month picker or Continue button, phone step, success/failed states) —
 * the same premium component either way. $collapsed = true wraps it behind
 * the "Gift this course" teaser toggle used inline on a course's own
 * enroll panel; false renders it already open, for gift.php where the
 * course was just explicitly picked, so there's nothing left to reveal.
 */
function render_gift_panel(array $course, bool $collapsed = true): void {
    $eligibility = gift_eligibility_for_course($course);
    if (!$eligibility['available']) return;
    $canGiftSubscription = $eligibility['isSubscription'];
    $monthlyPrice = $eligibility['monthlyPrice'];
    $price = $eligibility['price'];
    ?>
    <div class="gift-box" style="margin-top:16px;">
      <?php if ($collapsed): ?>
        <button type="button" class="gift-teaser" data-gift-toggle aria-expanded="false">
          <span class="gift-icon-chip"><?php dash_icon('gift'); ?></span>
          <span class="gift-teaser-text">
            <strong>Gift this course</strong>
            <span>Pay once — they get instant access</span>
          </span>
          <span class="chev"><?php dash_icon('chevron-right'); ?></span>
        </button>
      <?php endif; ?>
      <div class="<?= $collapsed ? 'hidden ' : '' ?>gift-card" data-gift-row style="margin-top:<?= $collapsed ? '12px' : '0' ?>;" data-payment-widget
           data-course-id="<?= (int) $course['id'] ?>"
           data-initiate-url="<?= e(base_url('api/initiate-gift-payment.php')) ?>"
           data-success-redirect="<?= e(base_url('dashboard/gifts.php')) ?>">
        <div class="gift-card-top"></div>
        <div class="gift-card-body">
          <div data-state="idle">
            <div class="gift-card-head">
              <span class="gift-icon-chip"><?php dash_icon('gift'); ?></span>
              <div>
                <h4>Give the gift of learning</h4>
                <p><?= $canGiftSubscription ? 'This school is subscription-based — choose how many months.' : "They get instant access. You cover the cost." ?></p>
              </div>
            </div>

            <div class="gift-field">
              <label class="gift-label">Recipient's name</label>
              <div class="field-icon"><?php dash_icon('user-plus'); ?><input data-recipient-name-input placeholder="Jane Auma"></div>
            </div>
            <div class="gift-field">
              <label class="gift-label">Recipient's email</label>
              <div class="field-icon"><?php dash_icon('mail'); ?><input data-recipient-email-input type="email" placeholder="jane@email.com"></div>
            </div>
            <div class="gift-field">
              <label class="gift-label">Personal message <span class="opt">(optional)</span></label>
              <div class="field-icon for-textarea"><?php dash_icon('message-square'); ?><textarea data-gift-message-input rows="2" placeholder="Happy birthday! Thought you'd love this one."></textarea></div>
            </div>

            <?php if ($canGiftSubscription): ?>
              <div class="gift-field">
                <label class="gift-label">Months to gift</label>
                <div class="month-pills" data-month-pills>
                  <?php foreach (GIFT_SUBSCRIPTION_MONTH_OPTIONS as $m): ?>
                    <button type="button" class="month-pill" data-action="start" data-months="<?= $m ?>" data-amount="<?= e(format_money($monthlyPrice * $m)) ?>">
                      <span class="m"><?= $m ?> Month<?= $m > 1 ? 's' : '' ?></span>
                      <span class="p"><?= e(format_money($monthlyPrice * $m)) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <button class="btn btn-gold btn-block btn-lg" style="margin-top:18px;" data-action="start">Continue</button>
            <?php endif; ?>
          </div>
          <div data-state="phone" class="hidden guest-form">
            <div class="gift-recap">
              <span class="gift-icon-chip sm"><?php dash_icon('gift'); ?></span>
              <div>
                <strong data-recap-item-label data-base-label="<?= e($course['title']) ?>"><?= e($course['title']) ?></strong>
                <span data-recap-recipient>for someone</span>
              </div>
            </div>
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
          <div data-state="success" class="hidden gift-success">
            <span class="gift-icon-chip"><?php dash_icon('check-circle'); ?></span>
            <h4>Gift sent</h4>
            <p>We've emailed <strong data-success-recipient-name>them</strong> a link to claim<br><strong data-success-item-label data-base-label="<?= e($course['title']) ?>"><?= e($course['title']) ?></strong>.</p>
            <a href="<?= e(base_url('dashboard/gifts.php')) ?>">View your gifts sent <?php dash_icon('arrow-right'); ?></a>
          </div>
          <div data-state="failed" class="hidden pay-failed">
            <p style="font-weight:700;">Payment not completed</p>
            <p class="small muted" data-fail-text></p>
            <button class="btn btn-primary btn-sm" data-action="retry">Try Again</button>
          </div>
          <p class="error-text hidden" data-error></p>
        </div>
      </div>
    </div>
    <script>
      (() => {
        const box = document.currentScript.previousElementSibling;
        <?php if ($collapsed): ?>
        const toggle = box.querySelector('[data-gift-toggle]');
        const row = box.querySelector('[data-gift-row]');
        if (toggle && row) {
          toggle.addEventListener('click', () => {
            const nowHidden = row.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', nowHidden ? 'false' : 'true');
          });
        }
        <?php endif; ?>
        box.querySelectorAll('[data-month-pills] .month-pill').forEach((pill) =>
          pill.addEventListener('click', () => {
            box.querySelectorAll('[data-month-pills] .month-pill').forEach((p) => p.classList.remove('selected'));
            pill.classList.add('selected');
          })
        );
      })();
    </script>
    <?php
}

function get_gift_by_token(string $token): ?array {
    return db_one(
        "SELECT cg.*, c.title AS course_title, c.slug AS course_slug, c.access_duration_days,
                u.name AS buyer_name
         FROM course_gifts cg
         JOIN courses c ON c.id = cg.course_id
         JOIN users u ON u.id = cg.buyer_id
         WHERE cg.claim_token_hash = ?",
        [hash('sha256', $token)]
    );
}

/**
 * A course card for gift.php's browse grid — visually the same as
 * render_course_card() (includes/course_card.php), but links to picking
 * this course for a gift instead of the course's own detail page, and
 * shows what the gift actually costs (a month's subscription price where
 * that applies) instead of a plain course price. Expects $c in the same
 * get_course_cards()-joined shape render_course_card() does.
 */
function render_gift_course_card(array $c): void {
    $eligibility = gift_eligibility_for_course($c);
    ?>
    <a href="<?= e(base_url('gift.php?slug=' . $c['slug'])) ?>" class="course-card reveal">
      <div class="thumb">
        <?php if (!empty($c['thumbnail_url'])): ?>
          <img src="<?= e(asset_src($c['thumbnail_url'])) ?>" alt="" loading="lazy">
        <?php else: ?>
          <div class="placeholder">Obin Academy</div>
        <?php endif; ?>
      </div>
      <div class="body">
        <div class="creator-row">
          <div class="avatar">
            <?php if (!empty($c['creator_avatar_url'])): ?>
              <img src="<?= e(asset_src($c['creator_avatar_url'])) ?>" alt="">
            <?php else: ?><?= e(mb_substr($c['creator_name'], 0, 1)) ?><?php endif; ?>
          </div>
          <span><?= e($c['creator_name']) ?></span>
        </div>

        <h3><?= e($c['title']) ?></h3>
        <p class="desc"><?= e($c['summary']) ?></p>

        <div class="price-row">
          <span class="price">
            <?php if ($eligibility['isSubscription']): ?>
              <span class="currency">UGX</span><?= number_format($eligibility['monthlyPrice']) ?><span style="font-size:0.6em; font-weight:600;">/mo</span>
            <?php else: ?>
              <span class="currency">UGX</span><?= number_format($eligibility['price']) ?>
            <?php endif; ?>
          </span>
          <span class="small" style="font-weight:700; color:var(--accent);"><?php dash_icon('gift'); ?> Gift this</span>
        </div>
      </div>
    </a>
    <?php
}

/** Every gift a buyer has sent, newest first, with the course title joined on. */
function get_gifts_sent_by_buyer(int $buyerId): array {
    return db_all(
        "SELECT cg.*, c.title AS course_title, c.slug AS course_slug
         FROM course_gifts cg JOIN courses c ON c.id = cg.course_id
         WHERE cg.buyer_id = ? ORDER BY cg.created_at DESC",
        [$buyerId]
    );
}

function fetch_payment_with_gift(int $paymentId): ?array {
    return db_one(
        "SELECT p.*, c.title AS course_title, c.slug AS course_slug, c.access_duration_days,
                u.name AS buyer_name, u.email AS buyer_email
         FROM payments p
         JOIN courses c ON c.id = p.course_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?",
        [$paymentId]
    );
}

/**
 * Starts a mobile-money collection for $courseId, paid by $buyerId, as a
 * gift for $recipientEmail. Logged-in buyers only — the buyer's own
 * account is what the receipt and payment history hang off of. $months is
 * read only when the course's school is on MONTHLY_SUBSCRIPTION pricing
 * (ignored otherwise) and must be one of GIFT_SUBSCRIPTION_MONTH_OPTIONS.
 * @return array{paymentId?: int, error?: string}
 */
function initiate_course_gift(int $buyerId, int $courseId, string $recipientName, string $recipientEmail, string $message, string $phone, ?int $months = null): array {
    if (!validate_phone($phone)) return ['error' => 'Enter a valid phone number.'];
    $recipientName = trim($recipientName);
    if (strlen($recipientName) < 2) return ['error' => "Enter the recipient's name."];
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) return ['error' => "Enter a valid email address for the recipient."];

    $course = db_one(
        "SELECT c.*, u.pricing_model AS creator_pricing_model, u.school_monthly_price AS creator_school_monthly_price
         FROM courses c JOIN users u ON u.id = c.creator_id
         WHERE c.id = ? AND c.status = 'PUBLISHED'",
        [$courseId]
    );
    if (!$course) return ['error' => 'Course not found.'];
    if ((int) $course['creator_id'] === $buyerId) return ['error' => 'Creators cannot gift their own course.'];

    $schoolHasSubscription = $course['creator_pricing_model'] === 'MONTHLY_SUBSCRIPTION' && (float) $course['creator_school_monthly_price'] > 0;
    $isSubscriptionCourse = $schoolHasSubscription && (int) $course['subscription_included'] === 1;

    if ($isSubscriptionCourse) {
        if (!in_array($months, GIFT_SUBSCRIPTION_MONTH_OPTIONS, true)) return ['error' => 'Choose how many months to gift.'];
        $amount = (float) $course['creator_school_monthly_price'] * $months;
    } else {
        if ((float) $course['price'] <= 0) return ['error' => 'This course is already free — just share the link.'];
        $amount = (float) $course['price'];
        $months = null;
    }

    $message = trim($message);
    $paymentId = db_insert(
        "INSERT INTO payments (user_id, course_id, amount, phone, type, status, gift_recipient_name, gift_recipient_email, gift_message, gift_subscription_months)
         VALUES (?, ?, ?, ?, 'COURSE_GIFT', 'PENDING', ?, ?, ?, ?)",
        [$buyerId, $courseId, $amount, $phone, $recipientName, $recipientEmail, $message !== '' ? $message : null, $months]
    );

    $label = $isSubscriptionCourse ? "Obin Academy - Gift: {$months}mo of {$course['title']}" : "Obin Academy - Gift: {$course['title']}";
    try {
        $result = iotec_initiate_collection($amount, $phone, (string) $paymentId, substr($label, 0, 100));
        db_run('UPDATE payments SET iotec_transaction_id = ? WHERE id = ?', [$result['transactionId'], $paymentId]);
    } catch (Throwable $e) {
        error_log('[iotec] initiateCollection failed for gift payment ' . $paymentId . ': ' . $e->getMessage());
        db_run("UPDATE payments SET status = 'FAILED', status_message = ? WHERE id = ?", [$e->getMessage(), $paymentId]);
        return ['error' => "We couldn't start the mobile money payment. Please try again."];
    }

    return ['paymentId' => $paymentId];
}

/**
 * Creates the course_gifts row for a successful COURSE_GIFT payment and
 * emails the recipient their claim link. Called only from
 * resolve_payment_with_iotec() — never creates an enrollment itself, that
 * only happens once the recipient actually claims it (claim_course_gift()).
 */
function apply_course_gift_payment_success(array $payment): void {
    [$token, $tokenHash] = make_access_token();
    $kind = $payment['gift_subscription_months'] !== null ? 'SUBSCRIPTION' : 'COURSE';

    db()->beginTransaction();
    try {
        $giftId = db_insert(
            "INSERT INTO course_gifts (recipient_name, recipient_email, message, claim_token_hash, buyer_id, course_id, kind, subscription_months, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$payment['gift_recipient_name'], $payment['gift_recipient_email'], $payment['gift_message'], $tokenHash, $payment['user_id'], $payment['course_id'], $kind, $payment['gift_subscription_months'], $payment['phone']]
        );
        db_run('UPDATE payments SET gift_id = ? WHERE id = ?', [$giftId, $payment['id']]);

        // The creator earns exactly what a direct purchase would, regardless
        // of who ends up with access to the course.
        $course = db_one('SELECT creator_id FROM courses WHERE id = ?', [$payment['course_id']]);
        $split = split_sale((float) $payment['amount']);
        db_insert(
            'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
            [$course['creator_id'], $payment['course_id'], $split['net'], $split['gross'], $split['fee']]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    $claimUrl = base_url('claim-gift.php?token=' . $token);
    send_gift_claim_email($payment['gift_recipient_email'], $payment['gift_recipient_name'], $payment['buyer_name'], $payment['course_title'], $payment['gift_message'], $claimUrl, $payment['gift_subscription_months'] !== null ? (int) $payment['gift_subscription_months'] : null);
    if ($payment['buyer_email']) {
        send_gift_purchase_receipt_email($payment);
    }
}

/**
 * Grants $userId access to a claimed gift. A COURSE-kind gift creates a
 * normal PURCHASE-sourced enrollment, same as buying it directly. A
 * SUBSCRIPTION-kind gift creates or extends a real school_subscriptions row
 * for $userId + the gift's creator, unlocking the gift's course for
 * subscription_months periods — exactly as if they'd subscribed and paid
 * for those months themselves (switching which course unlocks if they
 * already held a subscription to a different course from this creator, per
 * the normal one-course-at-a-time-per-creator rule). Idempotent: a gift
 * already CLAIMED by someone else, or a recipient who happens to already
 * own the course, both fail/no-op gracefully rather than error.
 * @return array{ok?: bool, error?: string}
 */
function claim_course_gift(int $userId, int $giftId): array {
    $gift = db_one('SELECT * FROM course_gifts WHERE id = ?', [$giftId]);
    if (!$gift) return ['error' => 'Gift not found.'];
    if ($gift['status'] === 'CLAIMED') return ['error' => 'This gift has already been claimed.'];

    db()->beginTransaction();
    try {
        db_run("UPDATE course_gifts SET status = 'CLAIMED', claimed_by_user_id = ?, claimed_at = NOW() WHERE id = ?", [$userId, $giftId]);

        if ($gift['kind'] === 'SUBSCRIPTION') {
            $course = db_one('SELECT creator_id FROM courses WHERE id = ?', [$gift['course_id']]);
            $creatorId = (int) $course['creator_id'];
            // Live current price, same as a normal renewal would charge —
            // not whatever the buyer happened to pay per month at gift time.
            $monthlyPrice = (float) (db_one('SELECT school_monthly_price FROM users WHERE id = ?', [$creatorId])['school_monthly_price'] ?? 0);
            $periodEndsAt = date('Y-m-d H:i:s', strtotime('+' . ((int) $gift['subscription_months'] * SCHOOL_SUBSCRIPTION_PERIOD_DAYS) . ' days'));

            $existingSub = db_one('SELECT id FROM school_subscriptions WHERE learner_id = ? AND creator_id = ?', [$userId, $creatorId]);
            if ($existingSub) {
                db_run(
                    "UPDATE school_subscriptions SET status='ACTIVE', price=?, phone=?, course_id=?, current_period_ends_at=?, grace_ends_at=NULL, renewal_attempts_made=0, last_charge_attempt_at=NULL, reminder_sent_at=NULL, canceled_at=NULL WHERE id=?",
                    [$monthlyPrice, $gift['phone'], $gift['course_id'], $periodEndsAt, $existingSub['id']]
                );
            } else {
                db_insert(
                    "INSERT INTO school_subscriptions (status, price, phone, current_period_ends_at, learner_id, creator_id, course_id) VALUES ('ACTIVE', ?, ?, ?, ?, ?, ?)",
                    [$monthlyPrice, $gift['phone'], $periodEndsAt, $userId, $creatorId, $gift['course_id']]
                );
            }
        } else {
            $existing = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $gift['course_id']]);
            if (!$existing) {
                $course = db_one('SELECT access_duration_days FROM courses WHERE id = ?', [$gift['course_id']]);
                $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);
                db_insert('INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)', [$userId, $gift['course_id'], $expiresAt]);
            }
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return ['ok' => true];
}
