<?php
require_once __DIR__ . '/moderation.php';

/**
 * Posting a comment needs an account, but never enrollment/a ticket — that's
 * what separates it from reviews (includes/functions.php has no equivalent
 * gate here). A comment containing blocked language is stored HIDDEN rather
 * than rejected outright, so the poster doesn't see it and an admin can
 * still review/restore a false positive later.
 * @return array{ok?: bool, hidden?: bool, id?: int, error?: string}
 */
function add_comment(int $userId, int $courseId, string $body): array {
    $body = trim($body);
    if ($body === '') return ['error' => 'Write a comment before posting.'];
    if (mb_strlen($body) < 2) return ['error' => 'Comment is too short.'];
    if (mb_strlen($body) > 2000) return ['error' => 'Comment is too long (2000 characters max).'];

    $course = db_one('SELECT id FROM courses WHERE id = ?', [$courseId]);
    if (!$course) return ['error' => 'Course not found.'];

    $blockedWord = comment_contains_blocked_language($body);
    $status = $blockedWord !== null ? 'HIDDEN' : 'VISIBLE';
    $hiddenReason = $blockedWord !== null ? 'Automatically hidden — contains blocked language.' : null;

    try {
        $id = db_insert(
            'INSERT INTO comments (body, status, hidden_reason, user_id, course_id) VALUES (?, ?, ?, ?, ?)',
            [$body, $status, $hiddenReason, $userId, $courseId]
        );
    } catch (Throwable $e) {
        return ['error' => 'Comments aren\'t available right now — please try again shortly.'];
    }

    return ['ok' => true, 'hidden' => $status === 'HIDDEN', 'id' => $id];
}

/**
 * Newest first — publicly visible comments for one course/event's detail
 * page. Defensive: this runs on every single course/event page view, so a
 * missing `comments` table (deploy landed before its migration ran) must
 * not 500 the entire site's course/event pages — just show no comments
 * until the table exists.
 */
function get_visible_comments(int $courseId): array {
    try {
        return db_all(
            "SELECT c.*, u.name AS author_name, u.avatar_url AS author_avatar_url
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.course_id = ? AND c.status = 'VISIBLE'
             ORDER BY c.created_at DESC",
            [$courseId]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** @param bool $canModerate true for the comment's own author, the course's creator, or an admin. */
function delete_comment(int $commentId, bool $canModerate): bool {
    if (!$canModerate) return false;
    $comment = db_one('SELECT id FROM comments WHERE id = ?', [$commentId]);
    if (!$comment) return false;
    db_run('DELETE FROM comments WHERE id = ?', [$commentId]);
    return true;
}
