<?php
/**
 * Email through Resend: account emails, the admin payment alert and the lead welcome sequence. Campus
 * emails (welcome, renewal reminders, certificates, professor statements) are written in campuses.php
 * with campus_email_html().
 */

/**
 * Sends one email through Resend. Returns true only when Resend accepted it. Existing callers ignore the result
 * (a failed send is logged, never fatal); the admin outreach buttons use it to tell the admin the truth.
 * $opts: 'bcc' (a copy to this address) and 'reply_to' (where the recipient's reply should go).
 */
function resend_send(string $to, string $subject, string $html, array $opts = []): bool {
    if (!RESEND_API_KEY) {
        error_log("[email] RESEND_API_KEY is not set — skipping send to $to. Subject: $subject");
        return false;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(array_filter([
            'from' => EMAIL_FROM,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'bcc' => $opts['bcc'] ?? null,
            'reply_to' => $opts['reply_to'] ?? null,
        ])),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        error_log("[email] Resend rejected the email to $to ($status): $body");
        return false;
    }
    return true;
}

function send_password_reset_email(string $to, string $resetUrl): void {
    resend_send($to, 'Reset your Obin Academy password', <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #0a0f2c;">Reset your password</h2>
          <p>We received a request to reset the password for your Obin Academy account.</p>
          <p>
            <a href="{$resetUrl}" style="display: inline-block; background: #0a0f2c; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
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

/**
 * Tells every admin that a payment just landed. $buyerLabel is a display name only (never an email);
 * $grossAmount is what was actually paid. $sellerName is kept for the call signature and ignored.
 */
function send_admin_sale_notification_email(string $itemTitle, string $itemLabel, string $buyerLabel, float $grossAmount, string $sellerName = ''): void {
    $admins = db_all("SELECT email FROM users WHERE role = 'ADMIN' AND email IS NOT NULL AND email != ''");
    if (!$admins) return;

    $amount = format_money($grossAmount);
    $title = e($itemTitle); $label = e($itemLabel); $buyer = e($buyerLabel);
    $html = <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
          <h2 style="color: #0a0f2c;">New payment</h2>
          <p><strong>{$buyer}</strong> just paid for the <strong>{$title}</strong> ({$label}).</p>
          <table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px;">
            <tr><td style="padding: 8px 0; color: #5b6670;">Amount paid</td><td style="padding: 8px 0; text-align: right; font-weight: 700;">{$amount}</td></tr>
          </table>
          <p style="color: #5b6670; font-size: 12.5px; margin-top: 20px;">Automatic payment alert, sent to every admin account.</p>
        </div>
        HTML;
    foreach ($admins as $admin) {
        resend_send($admin['email'], "New payment: {$itemTitle} — {$amount}", $html);
    }
}

// ---------------------------------------------------------------- lead welcome sequence

/** The shared frame of every lead email: a heading, the body, one button and the unsubscribe line. */
function lead_email_html(string $heading, string $bodyHtml, string $cta, string $url, string $unsubscribeUrl): string {
    $h = e($heading); $c = e($cta); $u = e($url); $unsub = e($unsubscribeUrl);
    return <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; color: #0e1124; line-height: 1.55;">
          <h2 style="color: #0a0f2c; font-family: Georgia, serif; font-weight: 500;">{$h}</h2>
          {$bodyHtml}
          <p style="margin-top: 22px;"><a href="{$u}" style="display: inline-block; background: #0a0f2c; color: #fff; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 600;">{$c}</a></p>
          <p style="color: #5b6670; font-size: 12px; text-align: center; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
            You're receiving this because you asked to hear from us on obinacademy.site.
            <a href="{$unsub}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML;
}

/** Campus rows for a lead email: name, one line, and the monthly price. */
function lead_email_campus_rows(array $campuses): string {
    $rows = '';
    foreach (array_slice($campuses, 0, 7) as $c) {
        $url = e(base_url('campus.php?slug=' . $c['slug']));
        $name = e($c['name']);
        $line = e($c['tagline']);
        $price = e(format_money((float) $c['monthly_price']));
        $rows .= <<<HTML
            <tr><td style="padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
              <a href="{$url}" style="color: #0e1124; text-decoration: none; font-weight: 700; font-size: 14px;">{$name} Campus</a>
              <div style="color: #5b6670; font-size: 12.5px; margin-top: 2px;">{$line} &middot; from {$price} a month</div>
            </td></tr>
            HTML;
    }
    return $rows ? '<table role="presentation" style="width: 100%; border-collapse: collapse; margin-top: 8px;">' . $rows . '</table>' : '';
}

/** Sent right after the lead popup is submitted: welcome, plus the campuses that are open. */
function send_lead_welcome_email(string $to, string $name, array $campuses, string $unsubscribeUrl): void {
    $first = e(trim(explode(' ', trim($name))[0] ?? '') ?: 'there');
    resend_send($to, 'Welcome to Obin Academy', lead_email_html(
        'Welcome, ' . (trim(explode(' ', trim($name))[0] ?? '') ?: 'there'),
        "<p>Thanks for your interest, {$first}. Obin Academy runs year-long campuses: one stage of lessons each month, a live class every week and a community of people learning the same skill.</p>" . lead_email_campus_rows($campuses),
        'See the campuses', base_url('index.php#campuses'), $unsubscribeUrl
    ));
}

/** Day 3: how a campus year works. */
function send_lead_day3_email(string $to, string $name, string $unsubscribeUrl): void {
    $first = trim(explode(' ', trim($name))[0] ?? '') ?: 'there';
    resend_send($to, 'How a year at an Obin Academy campus works', lead_email_html(
        "Hi {$first}, here is how it works",
        '<ul style="padding-left: 20px; font-size: 14px;">'
        . '<li style="margin-bottom:10px;">Each month a new stage of short video lessons and PDF guides opens.</li>'
        . '<li style="margin-bottom:10px;">Every week your professor teaches a live class, with time for questions.</li>'
        . '<li style="margin-bottom:10px;">Pay monthly or for the year with MTN or Airtel Mobile Money. Nothing renews by itself.</li>'
        . '<li style="margin-bottom:10px;">Finish the twelve stages and you earn your certificate.</li></ul>',
        'Choose your campus', base_url('index.php#campuses'), $unsubscribeUrl
    ));
}

/** Day 5: the campuses again, with prices. */
function send_lead_day5_email(string $to, string $name, array $campuses, string $unsubscribeUrl): void {
    $first = trim(explode(' ', trim($name))[0] ?? '') ?: 'there';
    resend_send($to, 'Which skill will you master this year?', lead_email_html(
        "Still deciding, {$first}?",
        '<p>Here are the campuses open right now. Each one is a full year, taught live.</p>' . lead_email_campus_rows($campuses),
        'See the campuses', base_url('index.php#campuses'), $unsubscribeUrl
    ));
}

/** Day 7: the last touch, with no pressure. */
function send_lead_day7_email(string $to, string $name, string $unsubscribeUrl): void {
    $first = trim(explode(' ', trim($name))[0] ?? '') ?: 'there';
    resend_send($to, "Whenever you're ready, {$first}", lead_email_html(
        "No pressure, {$first}",
        '<p>If now isn\'t the right time, that is fine. When you are ready, your campus will be here, and you can start any day: your year begins the day you join.</p>',
        'See the campuses', base_url('index.php#campuses'), $unsubscribeUrl
    ));
}
