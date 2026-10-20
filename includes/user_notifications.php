<?php
/**
 * In-app notifications (the bell), campus only. Types in use:
 *   members:    campus_joined, campus_renewal, campus_month_open, campus_year_done, campus_certificate
 *   class room: campus_chat (one row per campus that updates), campus_reply, campus_comment
 *   professors: professor_earning, professor_paid, payout_declined
 * Notifications from the old course platform were removed (migration/remove-old-notifications.sql).
 * The header bell polls api/get-notifications.php; class room alerts with sound: assets/js/chat-alerts.js.
 */

/** Never notifies a user about their own action — every call site passes its actor and skips the call itself when actor === recipient, but this is a second real guard, not just a convention. */
function create_user_notification(int $recipientUserId, ?int $actorUserId, string $type, string $message, ?string $linkUrl = null): void {
    if ($actorUserId !== null && $actorUserId === $recipientUserId) return;
    db_insert(
        'INSERT INTO user_notifications (type, message, link_url, recipient_user_id, actor_user_id) VALUES (?, ?, ?, ?, ?)',
        [$type, substr($message, 0, 500), $linkUrl, $recipientUserId, $actorUserId]
    );
}

function get_user_notifications(int $userId, int $limit = 20): array {
    return db_all(
        'SELECT * FROM user_notifications WHERE recipient_user_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(50, $limit)),
        [$userId]
    );
}

function get_unread_user_notification_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM user_notifications WHERE recipient_user_id = ? AND is_read = 0', [$userId])['n'];
}

function mark_user_notifications_read(int $userId): void {
    db_run('UPDATE user_notifications SET is_read = 1 WHERE recipient_user_id = ? AND is_read = 0', [$userId]);
}
