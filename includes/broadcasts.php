<?php
require_once __DIR__ . '/follows.php';

// A creator's WhatsApp broadcast composer. There's no WhatsApp Business API
// connected yet — mobile money aside, sending a real WhatsApp message in
// bulk needs that API (Meta Cloud API, Twilio, or a local aggregator), none
// of which the platform has set up. So instead of a fake "sent" button,
// this generates a wa.me deep link per follower, prefilled with the
// composed message, that the creator clicks through one at a time in their
// own WhatsApp — the same manual-broadcast pattern small businesses already
// use today. See migration/add-broadcasts.sql.

/**
 * A creator's followers with a usable WhatsApp number attached —
 * get_school_followers() minus anyone whatsapp_number() can't format (no
 * phone on file, or something unparseable). $wa is the wa.me-ready digits.
 */
function get_broadcast_recipients(int $creatorId): array {
    $followers = get_school_followers($creatorId);
    $recipients = [];
    foreach ($followers as $follower) {
        $wa = whatsapp_number($follower['phone']);
        if ($wa === null) continue;
        $follower['wa'] = $wa;
        $recipients[] = $follower;
    }
    return $recipients;
}

function record_broadcast(int $creatorId, string $message, int $recipientCount): int {
    return db_insert(
        'INSERT INTO broadcasts (message, recipient_count, creator_id) VALUES (?, ?, ?)',
        [$message, $recipientCount, $creatorId]
    );
}

/** A creator's past broadcasts, newest first. */
function get_broadcasts_for_creator(int $creatorId, int $limit = 20): array {
    $limit = max(1, min(50, $limit));
    return db_all("SELECT * FROM broadcasts WHERE creator_id = ? ORDER BY created_at DESC LIMIT $limit", [$creatorId]);
}
