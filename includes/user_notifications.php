<?php
/**
 * User-facing social notifications (likes, comments, mentions, follows,
 * replies, messages) — deliberately separate from includes/notifications.php
 * (admin_notifications, a platform-ops concern), per the Community Module
 * plan. The bell/inbox UI lands in a later phase; for now this just
 * persists rows so that UI has real data to show once built.
 */

function create_user_notification(int $userId, string $type, string $message, ?string $linkUrl = null, ?int $relatedId = null): void {
    db_insert(
        'INSERT INTO user_notifications (user_id, type, message, link_url, related_id) VALUES (?, ?, ?, ?, ?)',
        [$userId, $type, $message, $linkUrl, $relatedId]
    );
}

function get_unread_user_notification_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM user_notifications WHERE user_id = ? AND is_read = 0', [$userId])['n'];
}

function get_user_notifications(int $userId, int $limit = 20): array {
    $limit = max(1, min(100, $limit));
    return db_all("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit", [$userId]);
}

function mark_all_notifications_read(int $userId): void {
    db_run('UPDATE user_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
}
