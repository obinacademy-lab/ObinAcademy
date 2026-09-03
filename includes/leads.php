<?php
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/data.php';

/** Signed, stateless unsubscribe token — no separate token table needed. */
function unsubscribe_token(int $leadId): string {
    return $leadId . '.' . hash_hmac('sha256', (string) $leadId, APP_SECRET);
}

function unsubscribe_token_lead_id(string $token): ?int {
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) return null;
    [$leadId, $signature] = $parts;
    if (!hash_equals(hash_hmac('sha256', $leadId, APP_SECRET), $signature)) return null;
    return (int) $leadId;
}

/**
 * Validates and stores a voluntary lead-capture form submission — never
 * called from tracking data alone. Upserts by email (a repeat submission
 * just refreshes visit info rather than erroring or duplicating).
 * @return array{ok?: bool, leadType?: string, whatsappUrl?: string, error?: string}
 */
function capture_lead(array $data, ?string $visitorId, string $referrerSource): array {
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $phone = trim((string) ($data['phone'] ?? ''));
    $phone = $phone !== '' ? $phone : null;
    $leadType = ($data['leadType'] ?? '') === 'creator' ? 'creator' : 'learner';
    $consent = !empty($data['consent']);

    if ($name === '') return ['error' => 'Enter your name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Enter a valid email address.'];
    if ($leadType === 'creator' && !$phone) return ['error' => 'Enter your phone number.'];
    if (!$consent) return ['error' => 'Please accept to receive emails to continue.'];

    $existing = db_one('SELECT id FROM leads WHERE email = ?', [$email]);
    if ($existing) {
        $leadId = (int) $existing['id'];
        db_run(
            'UPDATE leads SET last_visit_at = NOW(), visit_count = visit_count + 1, phone = COALESCE(?, phone), visitor_id = COALESCE(visitor_id, ?) WHERE id = ?',
            [$phone, $visitorId, $leadId]
        );
        $isNewLead = false;
    } else {
        $leadId = db_insert(
            'INSERT INTO leads (name, email, phone, lead_type, source, visitor_id, consent_marketing) VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$name, $email, $phone, $leadType, $referrerSource, $visitorId]
        );
        $isNewLead = true;
    }

    if ($isNewLead) {
        $unsubscribeUrl = base_url('unsubscribe.php?token=' . unsubscribe_token($leadId));
        send_lead_welcome_email($email, $name, get_trending_courses(3), $unsubscribeUrl);
        create_admin_notification('new_lead', "New lead: {$name} ({$email})", $leadId);

        if ($leadType === 'creator') {
            send_lead_creator_invitation_email($email, $name, $unsubscribeUrl);
            create_admin_notification('creator_request', "{$name} wants to become a creator", $leadId);
        }
    }

    return ['ok' => true, 'leadType' => $leadType, 'whatsappUrl' => lead_whatsapp_url($name, $leadType)];
}

function lead_whatsapp_url(string $name, string $leadType): string {
    $message = $leadType === 'creator'
        ? "Hi! I'm {$name} — I just signed up on Obin Academy and I'm interested in becoming a creator."
        : "Hi! I'm {$name} — I just signed up on Obin Academy and I'd love some help getting started.";
    return 'https://wa.me/256775361998?text=' . urlencode($message);
}
