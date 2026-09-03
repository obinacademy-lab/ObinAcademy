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

    $discountRow = '';
    if (!empty($payment['original_amount'])) {
        $savings = format_money((float) $payment['original_amount'] - (float) $payment['amount']);
        $discountRow = '<tr><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; color: #16a34a;">Discount Applied</td><td style="padding: 10px 0; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 600; color: #16a34a;">-' . $savings . '</td></tr>';
    }

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
              View Your Course
            </a>
          </p>

          <p style="color: #5b6670; font-size: 12.5px; text-align: center; margin-top: 28px;">
            Keep this receipt for your records. Questions about this payment? Reply to this email or reach us at support@obinacademy.com.
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
        $price = !empty($c['sale_price']) && (float) $c['sale_price'] > 0 && (float) $c['sale_price'] < (float) $c['price']
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
