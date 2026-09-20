<?php

function resend_send(string $to, string $subject, string $html): void {
    if (!RESEND_API_KEY) {
        error_log("[email] RESEND_API_KEY is not set — skipping send to $to. Subject: $subject");
        return;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => EMAIL_FROM,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log("[email] Resend rejected the email to $to ($status): $body");
    }
}

function send_password_reset_email(string $to, string $resetUrl): void {
    resend_send($to, 'Reset your Obin Academy password', <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Reset your password</h2>
          <p>We received a request to reset the password for your Obin Academy account.</p>
          <p>
            <a href="{$resetUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Reset Password
            </a>
          </p>
          <p style="color: #5b6670; font-size: 14px;">
            This link expires in 1 hour. If you didn't request this, you can safely ignore this email.
          </p>
          <p style="color: #5b6670; font-size: 12px;">
            Or copy and paste this link into your browser:<br>{$resetUrl}
          </p>
        </div>
        HTML);
}

function send_withdrawal_approved_email(string $to, float $amount): void {
    $formatted = format_money($amount);
    resend_send($to, 'Your Obin Academy Withdrawal Has Been Approved', <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Withdrawal Approved</h2>
          <p>
            Your withdrawal of <strong>{$formatted}</strong> has been approved. You will
            receive your earnings in less than 30 minutes.
          </p>
          <p style="margin-top: 24px;">
            Thank you for using Obin Academy to share your knowledge and expertise with others.
          </p>
        </div>
        HTML);
}

function send_guest_access_email(string $to, string $name, string $courseTitle, string $accessUrl): void {
    resend_send($to, "Your Access Link for \"{$courseTitle}\" — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">You're in, {$name}!</h2>
          <p>
            Thanks for getting <strong>{$courseTitle}</strong> on Obin Academy. Use the
            button below any time to get back into your course — no account or
            password needed.
          </p>
          <p>
            <a href="{$accessUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Access Your Course
            </a>
          </p>
          <p style="color: #5b6670; font-size: 14px;">
            Save this email — this link is how you'll return to your course. Or copy
            and paste it into your browser:<br>{$accessUrl}
          </p>
        </div>
        HTML);
}

/**
 * A few days before a school subscription's current period ends — mobile
 * money has no saved-token auto-renew, so this is the only heads-up a
 * learner gets before their access pauses (see
 * cron/track-maintenance.php's school_subscription_renewal_reminders
 * section and includes/school_subscriptions.php).
 */
function send_school_subscription_renewal_email(string $to, string $name, string $schoolLabel, string $courseTitle, string $renewUrl): void {
    resend_send($to, "Your subscription to {$courseTitle} renews soon — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Hi {$name}, your subscription is ending soon</h2>
          <p>
            Your monthly subscription to <strong>{$courseTitle}</strong> ({$schoolLabel}) on
            Obin Academy is about to end. Mobile money can't renew automatically — approve a
            new payment below to keep your access to this course.
          </p>
          <p>
            <a href="{$renewUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Renew Your Subscription
            </a>
          </p>
          <p style="color: #5b6670; font-size: 14px;">
            If you don't renew, your access to {$courseTitle} will pause after a short grace
            period — anything you bought individually stays yours either way.
          </p>
        </div>
        HTML);
}

/**
 * Emailed right after a payment resolves to SUCCESS (course purchase or
 * premium upgrade), to both guest and logged-in learners — a receipt is
 * proof of payment independent of whatever access flow the learner uses.
 * $itemLabel distinguishes a full course purchase from a premium upgrade.
 */
function send_payment_receipt_email(array $payment, bool $isGuestPayment, string $itemLabel): void {
    $to = $isGuestPayment ? $payment['guest_email'] : $payment['learner_email'];
    if (!$to) return;

    $name = $isGuestPayment ? $payment['guest_name'] : $payment['learner_name'];
    $amount = format_money((float) $payment['amount']);
    $courseTitle = $payment['course_title'];
    $receiptNo = 'OA-' . str_pad((string) $payment['id'], 6, '0', STR_PAD_LEFT);
    $date = date('F j, Y \a\t g:i A');
    $courseUrl = base_url('courses/view.php?slug=' . $payment['course_slug']);
    $ctaLabel = 'View Your Course';

    $discountRow = '';
    if (!empty($payment['original_amount'])) {
        $savings = format_money((float) $payment['original_amount'] - (float) $payment['amount']);
        $discountRow = '<tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #16a34a;">Discount Applied</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600; color: #16a34a;">-' . $savings . '</td></tr>';
    }

    $crossSellHtml = receipt_cross_sell_html(
        !empty($payment['course_id']) ? (int) $payment['course_id'] : null,
        !empty($payment['course_category_id']) ? (int) $payment['course_category_id'] : null,
        $isGuestPayment || empty($payment['user_id']) ? null : (int) $payment['user_id']
    );

    resend_send($to, "Receipt for \"{$courseTitle}\" — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <div style="text-align: center; padding-bottom: 20px; border-bottom: 3px solid #2563eb;">
            <table role="presentation" style="margin: 0 auto;"><tr>
              <td style="vertical-align: middle; padding-right: 8px;">
                <div style="width: 34px; height: 34px; border-radius: 9px; background: #1e3a8a; display: flex; align-items: center; justify-content: center;">
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>
                </div>
              </td>
              <td style="vertical-align: middle;"><span style="font-size: 19px; font-weight: 800; color: #14181b;">Obin <span style="color: #2563eb;">Academy</span></span></td>
            </tr></table>
          </div>

          <h2 style="color: #1e3a8a; text-align: center; margin-top: 24px;">Payment Receipt</h2>
          <p style="text-align: center; color: #5b6670;">Thanks, {$name} — here's your receipt for this purchase.</p>

          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;">
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Receipt No.</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$receiptNo}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Date</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$date}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Item</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$courseTitle}<br><span style="font-weight: 400; color: #5b6670; font-size: 12.5px;">{$itemLabel}</span></td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Payment Method</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">Mobile Money</td></tr>
            {$discountRow}
            <tr><td style="padding: 14px 0 0; color: #14181b; font-weight: 800; font-size: 16px;">Amount Paid</td><td style="padding: 14px 0 0; text-align: right; color: #1e3a8a; font-weight: 800; font-size: 16px;">{$amount}</td></tr>
          </table>

          <p style="text-align: center; margin-top: 28px;">
            <a href="{$courseUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              {$ctaLabel}
            </a>
          </p>

          {$crossSellHtml}

          <p style="color: #5b6670; font-size: 12.5px; text-align: center; margin-top: 28px;">
            Keep this receipt for your records. Questions about this payment? Reply to this email or reach us at info@obinacademy.site.
          </p>
        </div>
        HTML);
}

/**
 * "You might also like" — up to 2 same-category course suggestions appended
 * to a purchase receipt, buying intent being highest right after a purchase
 * actually completes. Returns '' (never a broken/empty section) whenever
 * $courseId or $categoryId is missing (a receipt type with no single course
 * behind it, e.g. a school subscription) or nothing qualifies.
 */
function receipt_cross_sell_html(?int $courseId, ?int $categoryId, ?int $excludeUserId): string {
    if ($courseId === null || $categoryId === null) return '';

    // A self-contained query rather than reusing get_related_courses()/
    // get_course_cards() (includes/data.php) — most page files plain-
    // `require` data.php themselves rather than require_once, on the
    // assumption it's never already loaded. email.php is pulled in by
    // bootstrap.php on every single request (via affiliates.php), so
    // require_once-ing data.php from here would make it load twice — a
    // fatal "cannot redeclare" on every one of those pages.
    $where = "c.id != ? AND c.category_id = ? AND c.status = 'PUBLISHED'";
    $params = [$courseId, $categoryId];
    if ($excludeUserId !== null) {
        $where .= ' AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.user_id = ?)';
        $params[] = $excludeUserId;
    }
    $suggestions = db_all(
        "SELECT c.title, c.slug, c.price, c.sale_price, c.sale_ends_at, u.name AS creator_name
         FROM courses c JOIN users u ON u.id = c.creator_id
         WHERE $where
         ORDER BY c.view_count DESC, (SELECT COUNT(*) FROM enrollments e2 WHERE e2.course_id = c.id) DESC, c.created_at DESC
         LIMIT 2",
        $params
    );
    if (!$suggestions) return '';

    $rows = '';
    foreach ($suggestions as $s) {
        $url = base_url('courses/view.php?slug=' . $s['slug']);
        $hasSale = course_has_active_sale($s);
        $price = $hasSale ? (float) $s['sale_price'] : (float) $s['price'];
        $priceLabel = $price > 0 ? format_money($price) : 'Free';
        $rows .= <<<HTML
            <tr>
              <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                <a href="{$url}" style="color: #14181b; text-decoration: none; font-weight: 700; font-size: 14px;">{$s['title']}</a>
                <div style="color: #5b6670; font-size: 12.5px; margin-top: 2px;">by {$s['creator_name']} &middot; {$priceLabel}</div>
              </td>
            </tr>
            HTML;
    }

    return <<<HTML
        <div style="margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
          <p style="font-weight: 700; font-size: 14px; margin-bottom: 4px;">You Might Also Like</p>
          <table role="presentation" style="width: 100%; border-collapse: collapse;">{$rows}</table>
        </div>
        HTML;
}

/**
 * Notifies every admin account that a sale just happened, platform-wide.
 * $buyerLabel is a display name only (never an email) — this is a quick
 * heads-up, not a receipt. $grossAmount is what the buyer actually paid.
 */
function send_admin_sale_notification_email(string $itemTitle, string $itemLabel, string $buyerLabel, float $grossAmount, string $creatorName): void {
    $admins = db_all("SELECT email FROM users WHERE role = 'ADMIN' AND email IS NOT NULL AND email != ''");
    if (!$admins) return;

    $amount = format_money($grossAmount);
    $subject = "New Sale: {$itemTitle} — {$amount}";
    $html = <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">New Sale</h2>
          <p><strong>{$buyerLabel}</strong> just bought <strong>{$itemTitle}</strong> ({$itemLabel}) from <strong>{$creatorName}</strong>.</p>
          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px;">
            <tr><td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Amount Paid</td><td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 700;">{$amount}</td></tr>
            <tr><td style="padding: 8px 0; color: #5b6670;">Creator</td><td style="padding: 8px 0; text-align: right; font-weight: 700;">{$creatorName}</td></tr>
          </table>
          <p style="color: #5b6670; font-size: 12.5px; margin-top: 20px;">
            Automatic platform-wide sale notification, sent to every admin account.
          </p>
        </div>
        HTML;

    foreach ($admins as $admin) {
        resend_send($admin['email'], $subject, $html);
    }
}

/**
 * Notifies a course's creator that they just made a sale. $netEarning is
 * their cut after the platform fee — the same number that lands on their
 * Earnings page, not the buyer's gross payment.
 */
function send_creator_sale_notification_email(string $creatorEmail, string $creatorName, string $itemTitle, string $itemLabel, string $buyerLabel, float $netEarning): void {
    if (!$creatorEmail) return;

    $amount = format_money($netEarning);
    $dashboardUrl = base_url('dashboard/creator/earnings.php');
    resend_send($creatorEmail, "You Made a Sale: {$itemTitle}", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">You Made a Sale!</h2>
          <p>Hi {$creatorName},</p>
          <p><strong>{$buyerLabel}</strong> just bought <strong>"{$itemTitle}"</strong> ({$itemLabel}).</p>
          <p style="margin-top: 18px;">
            <span style="display: inline-block; background: #ecfdf5; color: #16a34a; padding: 12px 20px; border-radius: 12px; font-weight: 800; font-size: 18px;">+{$amount}</span>
          </p>
          <p style="color: #5b6670; font-size: 13px;">This is your net earning after the platform fee — it's already added to your Earnings balance.</p>
          <p style="margin-top: 24px;">
            <a href="{$dashboardUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              View Earnings
            </a>
          </p>
        </div>
        HTML);
}

/** Same receipt template as send_payment_receipt_email(), pointed at a
 * bundle's title/slug instead of a single course's — payments.type
 * BUNDLE_PURCHASE joins bundle_title/bundle_slug (see
 * includes/bundles.php's fetch_payment_with_bundle()), not
 * course_title/course_slug, so it can't reuse that function as-is. */
function send_bundle_receipt_email(array $payment, bool $isGuestPayment, string $itemLabel): void {
    $to = $isGuestPayment ? $payment['guest_email'] : $payment['learner_email'];
    if (!$to) return;

    $name = $isGuestPayment ? $payment['guest_name'] : $payment['learner_name'];
    $amount = format_money((float) $payment['amount']);
    $bundleTitle = $payment['bundle_title'];
    $receiptNo = 'OA-' . str_pad((string) $payment['id'], 6, '0', STR_PAD_LEFT);
    $date = date('F j, Y \a\t g:i A');
    $bundleUrl = base_url('bundle.php?slug=' . $payment['bundle_slug']);
    $ctaLabel = 'View Your Courses';

    resend_send($to, "Receipt for \"{$bundleTitle}\" — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <div style="text-align: center; padding-bottom: 20px; border-bottom: 3px solid #2563eb;">
            <table role="presentation" style="margin: 0 auto;"><tr>
              <td style="vertical-align: middle; padding-right: 8px;">
                <div style="width: 34px; height: 34px; border-radius: 9px; background: #1e3a8a; display: flex; align-items: center; justify-content: center;">
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>
                </div>
              </td>
              <td style="vertical-align: middle;"><span style="font-size: 19px; font-weight: 800; color: #14181b;">Obin <span style="color: #2563eb;">Academy</span></span></td>
            </tr></table>
          </div>

          <h2 style="color: #1e3a8a; text-align: center; margin-top: 24px;">Payment Receipt</h2>
          <p style="text-align: center; color: #5b6670;">Thanks, {$name} — here's your receipt for this purchase.</p>

          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;">
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Receipt No.</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$receiptNo}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Date</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$date}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Item</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$bundleTitle}<br><span style="font-weight: 400; color: #5b6670; font-size: 12.5px;">{$itemLabel}</span></td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Payment Method</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">Mobile Money</td></tr>
            <tr><td style="padding: 14px 0 0; color: #14181b; font-weight: 800; font-size: 16px;">Amount Paid</td><td style="padding: 14px 0 0; text-align: right; color: #1e3a8a; font-weight: 800; font-size: 16px;">{$amount}</td></tr>
          </table>

          <p style="text-align: center; margin-top: 28px;">
            <a href="{$bundleUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              {$ctaLabel}
            </a>
          </p>

          <p style="color: #5b6670; font-size: 12.5px; text-align: center; margin-top: 28px;">
            Keep this receipt for your records. Questions about this payment? Reply to this email or reach us at info@obinacademy.site.
          </p>
        </div>
        HTML);
}

/**
 * Emailed right after an installment payment resolves to SUCCESS. Not a
 * reuse of send_payment_receipt_email() — that function hard-references
 * course_title/course_slug from a plain course purchase's joined row, but
 * says nothing about which installment this was, which fetch_payment_with_installment()
 * carries instead (installment_count/installments_paid, no guest support —
 * a payment plan requires an account, see includes/installments.php).
 */
function send_installment_receipt_email(array $payment): void {
    $to = $payment['learner_email'];
    if (!$to) return;

    // Re-read the plan rather than trust the columns fetch_payment_with_installment()
    // joined onto $payment — those were read before apply_installment_payment_success()
    // incremented installments_paid, so they'd otherwise show last installment's count.
    $plan = db_one('SELECT installments_paid, installment_count, installment_interval_days FROM installment_plans WHERE id = ?', [$payment['installment_plan_id']]);
    $name = $payment['learner_name'];
    $amount = format_money((float) $payment['amount']);
    $courseTitle = $payment['course_title'];
    $installmentNumber = (int) $plan['installments_paid'];
    $installmentCount = (int) $plan['installment_count'];
    $isFinal = $installmentNumber >= $installmentCount;
    $receiptNo = 'OA-' . str_pad((string) $payment['id'], 6, '0', STR_PAD_LEFT);
    $date = date('F j, Y \a\t g:i A');
    $courseUrl = base_url('learn.php?slug=' . $payment['course_slug']);
    $ctaLabel = 'Continue Learning';

    $progressNote = $isFinal
        ? 'This was your last installment — the course is fully paid off.'
        : "Installment {$installmentNumber} of {$installmentCount} paid. Your next one is due in " . (int) $plan['installment_interval_days'] . ' days.';

    resend_send($to, "Receipt for \"{$courseTitle}\" — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <div style="text-align: center; padding-bottom: 20px; border-bottom: 3px solid #2563eb;">
            <table role="presentation" style="margin: 0 auto;"><tr>
              <td style="vertical-align: middle; padding-right: 8px;">
                <div style="width: 34px; height: 34px; border-radius: 9px; background: #1e3a8a; display: flex; align-items: center; justify-content: center;">
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>
                </div>
              </td>
              <td style="vertical-align: middle;"><span style="font-size: 19px; font-weight: 800; color: #14181b;">Obin <span style="color: #2563eb;">Academy</span></span></td>
            </tr></table>
          </div>

          <h2 style="color: #1e3a8a; text-align: center; margin-top: 24px;">Payment Receipt</h2>
          <p style="text-align: center; color: #5b6670;">Thanks, {$name} — here's your receipt for this installment.</p>

          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;">
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Receipt No.</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$receiptNo}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Date</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$date}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Item</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$courseTitle}<br><span style="font-weight: 400; color: #5b6670; font-size: 12.5px;">Installment {$installmentNumber} of {$installmentCount}</span></td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Payment Method</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">Mobile Money</td></tr>
            <tr><td style="padding: 14px 0 0; color: #14181b; font-weight: 800; font-size: 16px;">Amount Paid</td><td style="padding: 14px 0 0; text-align: right; color: #1e3a8a; font-weight: 800; font-size: 16px;">{$amount}</td></tr>
          </table>

          <p style="text-align: center; color: #5b6670; margin-top: 20px; font-size: 14px;">{$progressNote}</p>

          <p style="text-align: center; margin-top: 20px;">
            <a href="{$courseUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              {$ctaLabel}
            </a>
          </p>

          <p style="color: #5b6670; font-size: 12.5px; text-align: center; margin-top: 28px;">
            Keep this receipt for your records. Questions about this payment? Reply to this email or reach us at info@obinacademy.site.
          </p>
        </div>
        HTML);
}

/**
 * Emailed when a learner's next installment is coming due (see
 * cron/track-maintenance.php's installment_reminders section and
 * includes/installments.php).
 */
function send_installment_reminder_email(string $to, string $name, string $courseTitle, int $nextNumber, int $installmentCount, float $amount, string $payUrl): void {
    $amountLabel = format_money($amount);
    resend_send($to, "Your next payment for \"{$courseTitle}\" is due soon — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Hi {$name}, your next installment is due soon</h2>
          <p>
            Installment {$nextNumber} of {$installmentCount} ({$amountLabel}) for <strong>{$courseTitle}</strong> on
            Obin Academy is coming up. Mobile money can't charge you automatically — approve a
            new payment below to keep your access.
          </p>
          <p>
            <a href="{$payUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Pay Next Installment
            </a>
          </p>
          <p style="color: #5b6670; font-size: 14px;">
            If it's missed, your access to {$courseTitle} will pause after a short grace period —
            paying any time afterward picks up right where you left off.
          </p>
        </div>
        HTML);
}

/**
 * Emailed to the RECIPIENT of a gifted course, right after the buyer's
 * payment succeeds — this is the claim link itself, not a receipt (the
 * buyer gets their own receipt separately, see send_gift_purchase_receipt_email()).
 * No account is assumed to exist yet; claim-gift.php handles sign-up/login.
 */
function send_gift_claim_email(string $to, string $recipientName, string $buyerName, string $courseTitle, ?string $message, string $claimUrl, ?int $subscriptionMonths = null): void {
    $messageBlock = $message
        ? '<div style="background:#f3f4f6; border-radius:10px; padding:14px 16px; margin-top:16px; font-size:14px; color:#374151;">"' . e($message) . '"</div>'
        : '';
    $accessLine = $subscriptionMonths
        ? "paid for {$subscriptionMonths} month" . ($subscriptionMonths === 1 ? '' : 's') . " of your access to <strong>{$courseTitle}</strong> on Obin Academy"
        : "paid for you to take <strong>{$courseTitle}</strong> on Obin Academy";

    resend_send($to, "{$buyerName} gifted you a course on Obin Academy!", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; text-align: center;">
          <div style="font-size: 40px;">🎁</div>
          <h2 style="color: #1e3a8a; margin-top: 10px;">Hi {$recipientName}, you've been gifted a course!</h2>
          <p>
            <strong>{$buyerName}</strong> {$accessLine}.
            Claim it below to start learning — you'll need a free account first if you don't already have one.
          </p>
          {$messageBlock}
          <p style="margin-top: 24px;">
            <a href="{$claimUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Claim Your Course
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12.5px; margin-top: 28px;">
            This link is yours alone — don't share it. Questions? Reply to this email or reach us at info@obinacademy.site.
          </p>
        </div>
        HTML);
}

/**
 * Emailed to the BUYER right after a gift payment succeeds — proof of
 * payment, since they'll never see the course in their own dashboard (the
 * recipient claims it instead). Not a reuse of send_payment_receipt_email(),
 * which points its CTA at the buyer's own course access.
 */
function send_gift_purchase_receipt_email(array $payment): void {
    $to = $payment['buyer_email'];
    if (!$to) return;

    $name = $payment['buyer_name'];
    $amount = format_money((float) $payment['amount']);
    $courseTitle = $payment['course_title'];
    $recipientName = $payment['gift_recipient_name'];
    $receiptNo = 'OA-' . str_pad((string) $payment['id'], 6, '0', STR_PAD_LEFT);
    $date = date('F j, Y \a\t g:i A');
    $itemLabel = $payment['gift_subscription_months']
        ? 'Gift for ' . $recipientName . ' — ' . (int) $payment['gift_subscription_months'] . ' month' . ((int) $payment['gift_subscription_months'] === 1 ? '' : 's')
        : 'Gift for ' . $recipientName;

    resend_send($to, "Receipt for your gift of \"{$courseTitle}\" — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <div style="text-align: center; padding-bottom: 20px; border-bottom: 3px solid #2563eb;">
            <table role="presentation" style="margin: 0 auto;"><tr>
              <td style="vertical-align: middle; padding-right: 8px;">
                <div style="width: 34px; height: 34px; border-radius: 9px; background: #1e3a8a; display: flex; align-items: center; justify-content: center;">
                  <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>
                </div>
              </td>
              <td style="vertical-align: middle;"><span style="font-size: 19px; font-weight: 800; color: #14181b;">Obin <span style="color: #2563eb;">Academy</span></span></td>
            </tr></table>
          </div>

          <h2 style="color: #1e3a8a; text-align: center; margin-top: 24px;">Gift Receipt</h2>
          <p style="text-align: center; color: #5b6670;">Thanks, {$name} — here's your receipt for this gift.</p>

          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;">
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Receipt No.</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$receiptNo}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Date</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$date}</td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Item</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">{$courseTitle}<br><span style="font-weight: 400; color: #5b6670; font-size: 12.5px;">{$itemLabel}</span></td></tr>
            <tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #5b6670;">Payment Method</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600;">Mobile Money</td></tr>
            <tr><td style="padding: 14px 0 0; color: #14181b; font-weight: 800; font-size: 16px;">Amount Paid</td><td style="padding: 14px 0 0; text-align: right; color: #1e3a8a; font-weight: 800; font-size: 16px;">{$amount}</td></tr>
          </table>

          <p style="text-align: center; color: #5b6670; margin-top: 20px; font-size: 14px;">We've emailed {$recipientName} their claim link.</p>

          <p style="color: #5b6670; font-size: 12.5px; text-align: center; margin-top: 28px;">
            Keep this receipt for your records. Questions about this payment? Reply to this email or reach us at info@obinacademy.site.
          </p>
        </div>
        HTML);
}

/** Sent the moment a course is completed (100% progress) — a proactive copy of the certificate.php link. */
function send_certificate_email(string $to, string $name, string $courseTitle, string $certificateUrl): void {
    resend_send($to, "You Earned a Certificate for \"{$courseTitle}\"! — Obin Academy", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; text-align: center;">
          <div style="font-size: 40px;">🎓</div>
          <h2 style="color: #1e3a8a; margin-top: 10px;">Congratulations, {$name}!</h2>
          <p>
            You've completed <strong>{$courseTitle}</strong> on Obin Academy. Your Certificate
            of Completion is ready — view it, download it, or add it straight to your LinkedIn profile.
          </p>
          <p style="margin-top: 20px;">
            <a href="{$certificateUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              View Your Certificate
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12.5px; margin-top: 24px;">
            Or copy and paste this link into your browser:<br>{$certificateUrl}
          </p>
        </div>
        HTML);
}

/**
 * Sent immediately after a Learner lead popup is submitted — welcome plus a
 * short, real course-recommendations list (get_trending_courses(), the same
 * data the homepage spotlight uses — never invented content).
 */
function send_lead_welcome_email(string $to, string $name, array $courses, string $unsubscribeUrl): void {
    $exploreUrl = base_url('courses/index.php');
    $courseRows = '';
    foreach ($courses as $c) {
        $url = base_url('courses/view.php?slug=' . $c['slug']);
        $price = course_has_active_sale($c)
            ? format_money((float) $c['sale_price']) . ' <span style="color:#9ca3af; text-decoration:line-through; font-weight:400;">' . format_money((float) $c['price']) . '</span>'
            : format_money((float) $c['price']);
        $courseRows .= <<<HTML
            <tr>
              <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                <a href="{$url}" style="color: #14181b; text-decoration: none; font-weight: 700; font-size: 14px;">{$c['title']}</a>
                <div style="color: #5b6670; font-size: 12.5px; margin-top: 2px;">by {$c['creator_name']} &middot; {$price}</div>
              </td>
            </tr>
            HTML;
    }

    resend_send($to, "Welcome to Obin Academy, {$name}!", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Welcome, {$name}! 🎓</h2>
          <p>
            Thanks for your interest in Obin Academy — East Africa's learning marketplace for
            practical, real-world skills. As promised, here's a head start: a few courses learners
            like you are enjoying right now.
          </p>
          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 8px;">
            {$courseRows}
          </table>
          <p style="margin-top: 24px;">
            <a href="{$exploreUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Explore All Courses
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're receiving this because you asked to hear from us on obinacademy.site.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML);
}

/** Sent immediately (alongside the welcome email) when a Creator lead popup is submitted. */
function send_lead_creator_invitation_email(string $to, string $name, string $unsubscribeUrl): void {
    $applyUrl = base_url('become-creator.php');
    resend_send($to, "Let's get you set up as a creator, {$name}", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Ready to teach, {$name}? 🚀</h2>
          <p>
            You told us you're interested in becoming a creator on Obin Academy — share what you know,
            earn income from mobile money payments, and build a real following of learners across
            East Africa.
          </p>
          <p>
            The next step is a short creator application so we can get your first course live.
          </p>
          <p style="margin-top: 20px;">
            <a href="{$applyUrl}" style="display: inline-block; background: #f5b301; color: #1e1400; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 700;">
              Apply to Become a Creator
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're receiving this because you asked to hear from us on obinacademy.site.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML);
}

/** Day 3 of the lead drip sequence — why the platform is worth their time, branched by lead type. */
function send_lead_day3_email(string $to, string $name, string $leadType, string $unsubscribeUrl): void {
    $isCreator = $leadType === 'creator';
    $subject = $isCreator ? "Why creators are choosing Obin Academy" : "3 reasons learners love Obin Academy";
    $bullets = $isCreator
        ? "<li style=\"margin-bottom:10px;\">Keep 90% of every sale — we take a 10% platform fee, nothing hidden</li>"
          . "<li style=\"margin-bottom:10px;\">Get paid instantly by mobile money — no waiting on bank transfers</li>"
          . "<li style=\"margin-bottom:10px;\">Reach learners across East Africa without building your own website</li>"
        : "<li style=\"margin-bottom:10px;\">Practical courses in finance, tech, business and more — taught by real African creators</li>"
          . "<li style=\"margin-bottom:10px;\">Pay instantly with MTN or Airtel Mobile Money — no card needed</li>"
          . "<li style=\"margin-bottom:10px;\">Earn a real Certificate of Completion for every course you finish</li>";
    $ctaUrl = $isCreator ? base_url('become-creator.php') : base_url('courses/index.php');
    $ctaLabel = $isCreator ? 'Apply to Become a Creator' : 'Browse Courses';

    resend_send($to, $subject, <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Hey {$name}, quick follow-up 👋</h2>
          <p>Here's what makes Obin Academy worth a closer look:</p>
          <ul style="padding-left: 20px; color: #14181b; font-size: 14px;">{$bullets}</ul>
          <p style="margin-top: 20px;">
            <a href="{$ctaUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">{$ctaLabel}</a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're receiving this because you asked to hear from us on obinacademy.site.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML);
}

/** Day 5 — a fresh set of popular courses (same real trending data the homepage uses). */
function send_lead_day5_email(string $to, string $name, array $courses, string $unsubscribeUrl): void {
    $courseRows = '';
    foreach ($courses as $c) {
        $url = base_url('courses/view.php?slug=' . $c['slug']);
        $courseRows .= <<<HTML
            <tr>
              <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                <a href="{$url}" style="color: #14181b; text-decoration: none; font-weight: 700; font-size: 14px;">{$c['title']}</a>
                <div style="color: #5b6670; font-size: 12.5px; margin-top: 2px;">by {$c['creator_name']} &middot; {$c['student_count']} students</div>
              </td>
            </tr>
            HTML;
    }
    $exploreUrl = base_url('courses/index.php');

    resend_send($to, "What other learners are taking right now", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Still deciding, {$name}?</h2>
          <p>Here's what's popular on Obin Academy this week:</p>
          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 8px;">{$courseRows}</table>
          <p style="margin-top: 24px;">
            <a href="{$exploreUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">See All Courses</a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're receiving this because you asked to hear from us on obinacademy.site.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML);
}

/**
 * Day 7 — the final drip touch. Only ever surfaces courses that are
 * genuinely on sale (a real sale_price the creator set) — if nothing is
 * actually on sale right now, $onSaleCourses is empty and the caller sends
 * the plain "still exploring" variant instead of inventing urgency.
 */
function send_lead_day7_email(string $to, string $name, array $onSaleCourses, string $unsubscribeUrl): void {
    $exploreUrl = base_url('courses/index.php');

    if (!$onSaleCourses) {
        resend_send($to, "Still thinking it over, {$name}?", <<<HTML
            <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
              <h2 style="color: #1e3a8a;">No pressure, {$name} — we'll be here</h2>
              <p>
                If now isn't the right time, that's completely fine. Whenever you're ready, Obin Academy's
                courses are just a click away.
              </p>
              <p style="margin-top: 20px;">
                <a href="{$exploreUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">Browse Courses</a>
              </p>
              <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
                You're receiving this because you asked to hear from us on obinacademy.site.
                <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
              </p>
            </div>
            HTML);
        return;
    }

    $courseRows = '';
    foreach ($onSaleCourses as $c) {
        $url = base_url('courses/view.php?slug=' . $c['slug']);
        $wasPrice = format_money((float) $c['price']);
        $nowPrice = format_money((float) $c['sale_price']);
        $courseRows .= <<<HTML
            <tr>
              <td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
                <a href="{$url}" style="color: #14181b; text-decoration: none; font-weight: 700; font-size: 14px;">{$c['title']}</a>
                <div style="margin-top: 2px; font-size: 12.5px;">
                  <span style="color: #9ca3af; text-decoration: line-through;">{$wasPrice}</span>
                  <span style="color: #16a34a; font-weight: 700;"> {$nowPrice}</span>
                </div>
              </td>
            </tr>
            HTML;
    }

    resend_send($to, "These courses are on sale right now", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">A few courses are on sale, {$name}</h2>
          <p>These are genuinely discounted right now — not a countdown gimmick, just real pricing from the creators:</p>
          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 8px;">{$courseRows}</table>
          <p style="margin-top: 24px;">
            <a href="{$exploreUrl}" style="display: inline-block; background: #f5b301; color: #1e1400; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 700;">See These Courses</a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're receiving this because you asked to hear from us on obinacademy.site.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML);
}

/**
 * A short re-engagement nudge for a learner inactive 5h+ — the emoji/headline/
 * body/cta come from retention.php's template pool, already personalized
 * with the learner's course, progress, and next lesson where known. Kept
 * visually spare (no receipt-style table, no course list) since the whole
 * point is a quick "come back" prompt, not another thing to read.
 */
function send_retention_nudge_email(string $to, string $subject, string $emoji, string $headline, string $body, string $ctaLabel, string $ctaUrl, string $unsubscribeUrl): void {
    resend_send($to, $subject, <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; text-align: center;">
          <div style="font-size: 34px;">{$emoji}</div>
          <h2 style="color: #1e3a8a; margin-top: 8px;">{$headline}</h2>
          <p style="color: #14181b; font-size: 15px; line-height: 1.6;">{$body}</p>
          <p style="margin-top: 22px;">
            <a href="{$ctaUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              {$ctaLabel}
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're getting this because you have a course in progress on Obin Academy.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from these reminders</a>.
          </p>
        </div>
        HTML);
}

/**
 * One-time follow-up for a learner who marked "Keep me updated" on a course
 * but never enrolled — see includes/interest.php. $salePrice is only
 * non-null when the course has a genuinely active sale right now (checked
 * by the caller via course_has_active_sale()); otherwise the email just
 * shows the regular price, never an invented discount.
 */
function send_course_interest_reminder_email(string $to, string $name, string $courseTitle, string $creatorName, string $courseUrl, ?float $salePrice, float $price, string $unsubscribeUrl): void {
    $firstName = trim(explode(' ', $name)[0] ?? '') ?: 'there';
    $priceHtml = $salePrice !== null
        ? '<span style="color:#9aa1ab; text-decoration:line-through; margin-right:8px;">' . format_money($price) . '</span><strong style="color:#16a34a;">' . format_money($salePrice) . '</strong>'
        : '<strong>' . format_money($price) . '</strong>';
    $subject = $salePrice !== null
        ? "{$courseTitle} is now on sale"
        : "Still thinking about {$courseTitle}?";

    resend_send($to, $subject, <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Hey {$firstName},</h2>
          <p>
            You asked to be kept updated on <strong>{$courseTitle}</strong> by {$creatorName},
            but you haven't enrolled yet. It's still right there waiting for you.
          </p>
          <p style="background: #f7f6f2; border-radius: 12px; padding: 14px 16px; font-size: 15px;">
            {$priceHtml}
          </p>
          <p style="margin-top: 20px;">
            <a href="{$courseUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              View Course
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're getting this because you marked interest in a course on Obin Academy.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from these reminders</a>.
          </p>
        </div>
        HTML);
}

/**
 * Sent once a stale PENDING mobile-money payment is confirmed FAILED — see
 * includes/payment_recovery.php. $itemType is 'course' or 'bundle', only
 * ever used for the one word in the body copy.
 */
function send_payment_recovery_email(string $to, string $name, string $itemTitle, string $itemType, string $resumeUrl, float $amount): void {
    $firstName = trim(explode(' ', $name)[0] ?? '') ?: 'there';
    $label = $itemType === 'bundle' ? 'bundle' : 'course';
    $amountLabel = format_money($amount);

    resend_send($to, "Complete your purchase of {$itemTitle}", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Hey {$firstName}, your payment didn't go through</h2>
          <p>
            You started buying the {$label} <strong>{$itemTitle}</strong> ({$amountLabel}), but the mobile
            money payment wasn't completed — this usually means the prompt timed out, the PIN was wrong, or
            there wasn't enough balance at that moment. Nothing was charged.
          </p>
          <p style="margin-top: 20px;">
            <a href="{$resumeUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Try Again
            </a>
          </p>
          <p style="color: #5b6670; font-size: 13px; margin-top: 20px;">
            If you keep having trouble, reach us on WhatsApp and we'll help you sort it out.
          </p>
        </div>
        HTML);
}

/**
 * Sent once, the first time an enrollment reaches 100% completion — see
 * includes/review_nudges.php. $reviewUrl points straight at the course's
 * #reviews section, where the review form already renders for an enrolled,
 * non-owner learner.
 */
function send_review_nudge_email(string $to, string $name, string $courseTitle, string $reviewUrl): void {
    $firstName = trim(explode(' ', $name)[0] ?? '') ?: 'there';

    resend_send($to, "How was {$courseTitle}?", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; text-align: center;">
          <div style="font-size: 34px;">🎉</div>
          <h2 style="color: #1e3a8a; margin-top: 8px;">You finished the course!</h2>
          <p style="color: #14181b; font-size: 15px; line-height: 1.6;">
            Nice work, {$firstName} — you've completed <strong>{$courseTitle}</strong>. Got a minute to share what
            you thought? It genuinely helps other learners decide, and helps the creator know what's working.
          </p>
          <p style="margin-top: 22px;">
            <a href="{$reviewUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Leave a Review
            </a>
          </p>
        </div>
        HTML);
}

/** Sent the moment an affiliate application is approved — the affiliate link already exists by the time this lands, since approve_affiliate_application() creates it in the same transaction. */
function send_affiliate_application_approved_email(string $to, string $name, string $refCode): void {
    $dashboardUrl = base_url('login.php?redirect=' . urlencode('/dashboard/affiliate.php'));
    $shareUrl = base_url('') . '?aff=' . $refCode;
    resend_send($to, "You're Approved as an Obin Academy Affiliate Partner!", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Congratulations, {$name}!</h2>
          <p>
            Your application to become an affiliate partner on Obin Academy has been approved.
            Your affiliate link is ready right now — share it anywhere, and you'll earn 2%
            commission whenever someone buys any course, from any creator, through it.
          </p>
          <p style="text-align: center; background: #f7f6f2; border-radius: 12px; padding: 14px; font-weight: 700; word-break: break-all; margin: 20px 0;">
            {$shareUrl}
          </p>
          <p>
            <a href="{$dashboardUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Sign In &amp; View Your Affiliate Dashboard
            </a>
          </p>
          <p style="margin-top: 24px;">
            We're excited to grow together. Welcome aboard!
          </p>
        </div>
        HTML);
}

function send_creator_application_approved_email(string $to, string $name): void {
    $loginUrl = base_url('login.php?redirect=' . urlencode('/dashboard/creator/index.php'));
    resend_send($to, "You're Approved as an Obin Academy Creator!", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #1e3a8a;">Congratulations, {$name}!</h2>
          <p>
            Your application to become a creator on Obin Academy has been approved. You can now
            start building courses and sharing your knowledge with learners.
          </p>
          <p>
            <a href="{$loginUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Sign In &amp; Start Creating
            </a>
          </p>
          <p style="color: #5b6670; font-size: 14px;">
            Or copy and paste this link into your browser:<br>{$loginUrl}
          </p>
          <p style="margin-top: 24px;">
            We're excited to see what you'll teach. Welcome aboard!
          </p>
        </div>
        HTML);
}

/** Sent to a creator's own course the moment an admin approves it — see includes/course_notify.php. */
function send_course_live_email_to_creator(string $to, string $name, string $courseTitle, string $courseUrl): void {
    resend_send($to, "Your Course \"{$courseTitle}\" Is Now Live!", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; text-align: center;">
          <div style="font-size: 34px;">🎉</div>
          <h2 style="color: #1e3a8a; margin-top: 8px;">You're live, {$name}!</h2>
          <p style="color: #14181b; font-size: 15px; line-height: 1.6;">
            <strong>{$courseTitle}</strong> has been approved and is now published on Obin Academy —
            learners can find it and enroll right now.
          </p>
          <p style="margin-top: 22px;">
            <a href="{$courseUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              View Your Course
            </a>
          </p>
        </div>
        HTML);
}

/**
 * Sent to every other learner/creator on the platform (not the course's own
 * creator, who gets send_course_live_email_to_creator() instead) — see
 * includes/course_notify.php for who's actually on this list.
 */
function send_new_course_announcement_email(string $to, string $name, string $courseTitle, string $courseSummary, string $creatorName, string $courseUrl, string $unsubscribeUrl): void {
    $firstName = trim(explode(' ', $name)[0] ?? '') ?: 'there';
    resend_send($to, "New on Obin Academy: \"{$courseTitle}\"", <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <div style="text-align: center; font-size: 30px;">🆕</div>
          <h2 style="color: #1e3a8a; text-align: center; margin-top: 8px;">A new course just went live</h2>
          <p style="color: #14181b; font-size: 15px; line-height: 1.6;">
            Hey {$firstName} — <strong>{$courseTitle}</strong> by {$creatorName} just published on Obin Academy.
          </p>
          <p style="color: #5b6670; font-size: 14px; line-height: 1.6;">{$courseSummary}</p>
          <p style="text-align: center; margin-top: 22px;">
            <a href="{$courseUrl}" style="display: inline-block; background: #2563eb; color: #fff; padding: 12px 24px; border-radius: 999px; text-decoration: none; font-weight: 600;">
              Check It Out
            </a>
          </p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're getting this because you have an account on Obin Academy.
            <a href="{$unsubscribeUrl}" style="color: #5b6670;">Unsubscribe from new course announcements</a>.
          </p>
        </div>
        HTML);
}
