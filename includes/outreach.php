<?php
/**
 * Admin outreach: one-tap follow-ups to leads and to buyers whose payment failed.
 *
 * WhatsApp has no bulk API on this platform, so a "WhatsApp" action opens the admin's own WhatsApp with the
 * message already written (wa.me link) and records that it was done. Email is sent through Resend. Both are
 * logged (migration/add-outreach-log.sql) so the next admin can see who has already been contacted; the log
 * is written inside try/catch so everything still works before that migration has been run.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/leads.php';

/** Ready-made lead messages. {name} is the lead's first name, {link} a page to send them to. */
function lead_message_templates(): array {
    $creatorShare = (int) round((1 - PLATFORM_FEE_RATE) * 100);
    return [
        'welcome' => [
            'label' => 'Welcome',
            'subject' => 'Welcome to Obin Academy',
            'text' => "Hi {name}, this is Obin Academy. Thanks for signing up with us.\n\nHave you found a course you like yet? Tell me what you want to learn and I will point you to the right one.\n\nBrowse courses: {link}",
        ],
        'follow_up' => [
            'label' => 'Follow-up',
            'subject' => 'Can we help you get started?',
            'text' => "Hi {name}, just checking in from Obin Academy.\n\nIs anything stopping you from starting your first course? If you are stuck on payment or choosing a course, reply here and we will sort it out with you.\n\nCourses: {link}",
        ],
        'courses' => [
            'label' => 'Recommend courses',
            'subject' => 'Courses people are learning right now',
            'text' => "Hi {name}, here are the courses people on Obin Academy are learning right now. You can pay with mobile money and start straight away.\n\n{link}",
        ],
        'creator' => [
            'label' => 'Become a creator',
            'subject' => 'Teach on Obin Academy',
            'text' => "Hi {name}, thanks for your interest in teaching on Obin Academy. You can open your own school, set your own prices and keep {$creatorShare}% of every sale, paid to your mobile money.\n\nWant me to walk you through it? Start here: {link}",
        ],
    ];
}

function lead_first_name(array $lead): string {
    return trim(explode(' ', trim((string) $lead['name']))[0] ?? '') ?: 'there';
}

function lead_message_default_for(array $lead): string {
    return $lead['lead_type'] === 'creator' ? 'creator' : 'welcome';
}

function lead_message_link(array $lead, string $template): string {
    return $template === 'creator' ? base_url('become-creator.php') : base_url('courses/index.php');
}

function lead_message_render(string $text, array $lead, string $template): string {
    return strtr($text, ['{name}' => lead_first_name($lead), '{link}' => lead_message_link($lead, $template)]);
}

/** Records that an admin reached out, and moves a still-NEW lead to CONTACTED. Never throws. */
function log_lead_contact(int $leadId, int $adminId, string $channel, string $template): void {
    try {
        db_run('INSERT INTO lead_contacts (lead_id, admin_id, channel, template) VALUES (?, ?, ?, ?)', [$leadId, $adminId, $channel, $template]);
    } catch (Throwable $e) {
        error_log('[outreach] lead_contacts not written: ' . $e->getMessage());
    }
    db_run("UPDATE leads SET status = 'CONTACTED' WHERE id = ? AND status = 'NEW'", [$leadId]);
}

/** Newest first. Empty when the log table doesn't exist yet. */
function get_lead_contacts(int $leadId, int $limit = 20): array {
    try {
        return db_all(
            'SELECT lc.*, u.name AS admin_name FROM lead_contacts lc LEFT JOIN users u ON u.id = lc.admin_id WHERE lc.lead_id = ? ORDER BY lc.created_at DESC, lc.id DESC LIMIT ' . (int) $limit,
            [$leadId]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function send_lead_message_email(array $lead, string $subject, string $bodyText): void {
    $unsubscribeUrl = base_url('unsubscribe.php?token=' . unsubscribe_token((int) $lead['id']));
    $body = nl2br(e($bodyText));
    $unsub = e($unsubscribeUrl);
    resend_send($lead['email'], $subject, <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto; line-height: 1.55; color: #16181a;">
          <p>{$body}</p>
          <p style="color: #5b6670; font-size: 12px; margin-top: 28px;">
            You are getting this because you signed up on Obin Academy.
            <a href="{$unsub}" style="color: #5b6670;">Unsubscribe from marketing emails</a>.
          </p>
        </div>
        HTML);
}

// ---------------------------------------------------------------- failed / stuck payments

/**
 * Who to remind about a payment, or null when a reminder makes no sense (not a first-time course or bundle
 * purchase, already successful, or the buyer has since got the course by another payment).
 * Expects a row from admin_get_payments() (needs course_slug / bundle_slug).
 */
function payment_reminder_target(array $p): ?array {
    if (!in_array($p['status'], ['FAILED', 'PENDING'], true)) return null;
    if ($p['status'] === 'PENDING' && strtotime($p['created_at']) > time() - ADMIN_STUCK_PAYMENT_MINUTES * 60) return null;

    if ($p['type'] === 'COURSE_PURCHASE' && $p['course_id'] && !empty($p['course_slug'])) {
        $title = $p['course_title'];
        $url = base_url('courses/view.php?slug=' . $p['course_slug']);
        $owned = db_one(
            'SELECT 1 FROM enrollments WHERE course_id = ? AND ((? IS NOT NULL AND user_id = ?) OR (? IS NULL AND guest_email = ?)) LIMIT 1',
            [$p['course_id'], $p['user_id'], $p['user_id'], $p['user_id'], $p['guest_email']]
        );
        if ($owned) return null;
    } elseif ($p['type'] === 'BUNDLE_PURCHASE' && $p['bundle_id'] && !empty($p['bundle_slug'])) {
        $title = $p['bundle_title'];
        $url = base_url('bundle.php?slug=' . $p['bundle_slug']);
    } else {
        return null;
    }

    return [
        'name' => $p['user_id'] ? $p['payer_name'] : ($p['guest_name'] ?: 'there'),
        'email' => $p['user_id'] ? $p['payer_email'] : $p['guest_email'],
        'wa' => whatsapp_number($p['phone']),
        'title' => $title,
        'url' => $url,
        'kind' => $p['type'] === 'BUNDLE_PURCHASE' ? 'bundle' : 'course',
    ];
}

function payment_reminder_whatsapp_text(array $target): string {
    $first = trim(explode(' ', trim((string) $target['name']))[0] ?? '') ?: 'there';
    return "Hi {$first}, this is Obin Academy. Your payment for \"{$target['title']}\" did not go through, and nothing was charged.\n\n"
        . "You can try again here: {$target['url']}\n\nIf it keeps failing, reply here and we will help you finish.";
}

function log_payment_reminder(int $paymentId, int $adminId, string $channel): void {
    try {
        db_run('INSERT INTO payment_reminders (payment_id, admin_id, channel) VALUES (?, ?, ?)', [$paymentId, $adminId, $channel]);
    } catch (Throwable $e) {
        error_log('[outreach] payment_reminders not written: ' . $e->getMessage());
    }
}

/** @return array<int, array{n:int,last:string}> keyed by payment id */
function get_payment_reminder_summary(array $paymentIds): array {
    $paymentIds = array_values(array_unique(array_map('intval', $paymentIds)));
    if (!$paymentIds) return [];
    try {
        $in = implode(',', array_fill(0, count($paymentIds), '?'));
        $rows = db_all("SELECT payment_id, COUNT(*) AS n, MAX(created_at) AS last FROM payment_reminders WHERE payment_id IN ($in) GROUP BY payment_id", $paymentIds);
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) $out[(int) $r['payment_id']] = ['n' => (int) $r['n'], 'last' => $r['last']];
    return $out;
}
