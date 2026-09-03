<?php
/**
 * Community module — Phase 5 (Messaging & notifications), the DM half.
 * See C:\Users\hp\.claude\plans\iterative-scribbling-turing.md.
 *
 * 1:1 only in v1 — every conversation has exactly 2 participants, enforced
 * by get_or_create_conversation() always reusing an existing 2-participant
 * conversation rather than ever creating a 3+-participant one. Group DMs are
 * explicitly deferred.
 */

/** Finds the existing 1:1 conversation between two users, or creates one. */
function get_or_create_conversation(int $userA, int $userB): int {
    if ($userA === $userB) throw new InvalidArgumentException('Cannot start a conversation with yourself.');

    $existing = db_one(
        "SELECT cp.conversation_id
         FROM conversation_participants cp
         WHERE cp.user_id = ?
           AND EXISTS (SELECT 1 FROM conversation_participants cp2 WHERE cp2.conversation_id = cp.conversation_id AND cp2.user_id = ?)
           AND (SELECT COUNT(*) FROM conversation_participants cp3 WHERE cp3.conversation_id = cp.conversation_id) = 2
         LIMIT 1",
        [$userA, $userB]
    );
    if ($existing) return (int) $existing['conversation_id'];

    $conversationId = db_insert('INSERT INTO conversations (created_at) VALUES (NOW())', []);
    db_insert('INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)', [$conversationId, $userA]);
    db_insert('INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)', [$conversationId, $userB]);
    return $conversationId;
}

function is_conversation_participant(int $conversationId, int $userId): bool {
    return (bool) db_one('SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?', [$conversationId, $userId]);
}

/** The other participant in a 1:1 conversation. */
function get_conversation_other_user(int $conversationId, int $viewerUserId): ?array {
    return db_one(
        "SELECT u.id, u.name, u.avatar_url, u.headline
         FROM conversation_participants cp JOIN users u ON u.id = cp.user_id
         WHERE cp.conversation_id = ? AND cp.user_id != ? LIMIT 1",
        [$conversationId, $viewerUserId]
    );
}

function send_message(int $conversationId, int $senderId, string $body): int {
    $messageId = db_insert('INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)', [$conversationId, $senderId, $body]);

    $recipient = get_conversation_other_user($conversationId, $senderId);
    if ($recipient) {
        $sender = db_one('SELECT name FROM users WHERE id = ?', [$senderId]);
        if ($sender) {
            create_user_notification((int) $recipient['id'], 'message', $sender['name'] . ' sent you a message', '/messages/view.php?id=' . $conversationId, $conversationId);
        }
    }
    return $messageId;
}

const MESSAGE_SELECT_SQL = "
    m.id, m.conversation_id, m.body, m.created_at, m.read_at,
    m.sender_id AS author_id, u.name AS author_name, u.avatar_url AS author_avatar_url
";

function get_message_by_id(int $messageId): ?array {
    return db_one("SELECT " . MESSAGE_SELECT_SQL . " FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.id = ?", [$messageId]);
}

/** Oldest-first message log, shaped like get_study_group_messages() (author_id/author_name/author_avatar_url) so render_chat_message() works unchanged for both. Pass $afterId (the highest id the client already has) for a polling delta instead of the full history. */
function get_conversation_messages(int $conversationId, ?int $afterId = null, int $limit = 100): array {
    $limit = max(1, min(200, $limit));
    if ($afterId !== null) {
        return db_all(
            "SELECT " . MESSAGE_SELECT_SQL . " FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT $limit",
            [$conversationId, $afterId]
        );
    }
    $rows = db_all(
        "SELECT " . MESSAGE_SELECT_SQL . " FROM messages m JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = ? ORDER BY m.id DESC LIMIT $limit",
        [$conversationId]
    );
    return array_reverse($rows);
}

/** Marks every message the OTHER participant sent as read, from this viewer's side. */
function mark_conversation_read(int $conversationId, int $viewerUserId): void {
    db_run('UPDATE messages SET read_at = NOW() WHERE conversation_id = ? AND sender_id != ? AND read_at IS NULL', [$conversationId, $viewerUserId]);
}

/** Every conversation a user is part of, with the other participant, last-message preview, and unread count — the inbox list. */
function get_user_conversations(int $userId): array {
    return db_all(
        "SELECT c.id AS conversation_id, u.id AS other_user_id, u.name AS other_user_name, u.avatar_url AS other_user_avatar_url,
                (SELECT body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.read_at IS NULL) AS unread_count
         FROM conversation_participants cp
         JOIN conversations c ON c.id = cp.conversation_id
         JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id != ?
         JOIN users u ON u.id = cp2.user_id
         WHERE cp.user_id = ?
         ORDER BY last_message_at IS NULL, last_message_at DESC",
        [$userId, $userId, $userId]
    );
}

function get_unread_message_count(int $userId): int {
    return (int) db_one(
        "SELECT COUNT(*) AS n FROM messages m
         JOIN conversation_participants cp ON cp.conversation_id = m.conversation_id
         WHERE cp.user_id = ? AND m.sender_id != ? AND m.read_at IS NULL",
        [$userId, $userId]
    )['n'];
}
