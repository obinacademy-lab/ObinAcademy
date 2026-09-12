<?php
require_once __DIR__ . '/moderation.php';

/**
 * Posting a comment needs an account, but never enrollment/a ticket — that's
 * what separates it from reviews (includes/functions.php has no equivalent
 * gate here). A comment containing blocked language is stored HIDDEN rather
 * than rejected outright, so the poster doesn't see it and an admin can
 * still review/restore a false positive later.
 * @param ?int $replyToId the exact comment being replied to — any comment
 *   or reply, so "reply to anyone" isn't limited to top-level comments.
 *   Stored as-is in reply_to_comment_id for the "Replying to X" label;
 *   separately, the *structural* parent_id always resolves up to that
 *   comment's top-level ancestor, since visual nesting is capped at two
 *   levels regardless of how deep the conversation actually goes.
 * @return array{ok?: bool, hidden?: bool, id?: int, error?: string}
 */
function add_comment(int $userId, int $courseId, string $body, ?int $replyToId = null): array {
    $body = trim($body);
    if ($body === '') return ['error' => 'Write a comment before posting.'];
    if (mb_strlen($body) < 2) return ['error' => 'Comment is too short.'];
    if (mb_strlen($body) > 2000) return ['error' => 'Comment is too long (2000 characters max).'];

    $course = db_one('SELECT id FROM courses WHERE id = ?', [$courseId]);
    if (!$course) return ['error' => 'Course not found.'];

    $parentId = null;
    $replyToCommentId = null;
    if ($replyToId !== null) {
        $target = db_one("SELECT id, parent_id FROM comments WHERE id = ? AND course_id = ? AND status = 'VISIBLE'", [$replyToId, $courseId]);
        if (!$target) return ['error' => 'The comment you\'re replying to no longer exists.'];
        $replyToCommentId = (int) $target['id'];
        $parentId = $target['parent_id'] !== null ? (int) $target['parent_id'] : (int) $target['id'];
    }

    $blockedWord = comment_contains_blocked_language($body);
    $status = $blockedWord !== null ? 'HIDDEN' : 'VISIBLE';
    $hiddenReason = $blockedWord !== null ? 'Automatically hidden — contains blocked language.' : null;

    try {
        $id = db_insert(
            'INSERT INTO comments (body, status, hidden_reason, user_id, course_id, parent_id, reply_to_comment_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$body, $status, $hiddenReason, $userId, $courseId, $parentId, $replyToCommentId]
        );
    } catch (Throwable $e) {
        return ['error' => 'Comments aren\'t available right now — please try again shortly.'];
    }

    return ['ok' => true, 'hidden' => $status === 'HIDDEN', 'id' => $id];
}

/**
 * Publicly visible comments for one course/event's detail page, as a tree —
 * each top-level comment (newest first) carries its replies (oldest first)
 * in a 'replies' key, and every row carries 'reply_to_author_name' (who it
 * was directly addressed to, which for a reply-to-a-reply is someone other
 * than the top-level comment's author). Defensive: this runs on every
 * single course/event page view, so a missing `comments` table/column
 * (deploy landed before its migration ran) must not 500 the entire site's
 * course/event pages — just show no comments until it exists.
 */
function get_visible_comments(int $courseId): array {
    try {
        $rows = db_all(
            "SELECT c.*, u.name AS author_name, u.avatar_url AS author_avatar_url
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.course_id = ? AND c.status = 'VISIBLE'
             ORDER BY c.created_at ASC",
            [$courseId]
        );
    } catch (Throwable $e) {
        return [];
    }

    $byId = [];
    foreach ($rows as $row) {
        $row['replies'] = [];
        $byId[(int) $row['id']] = $row;
    }
    foreach ($byId as $id => $row) {
        $replyToId = $row['reply_to_comment_id'] !== null ? (int) $row['reply_to_comment_id'] : null;
        $byId[$id]['reply_to_author_name'] = ($replyToId !== null && isset($byId[$replyToId])) ? $byId[$replyToId]['author_name'] : null;
    }
    foreach ($byId as $id => $row) {
        $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        if ($parentId !== null && isset($byId[$parentId])) {
            $byId[$parentId]['replies'][] = $byId[$id];
        }
    }

    $topLevel = array_values(array_filter($byId, fn(array $row): bool => $row['parent_id'] === null));
    return array_reverse($topLevel);
}

/** @param bool $canModerate true for the comment's own author, the course's creator, or an admin. */
function delete_comment(int $commentId, bool $canModerate): bool {
    if (!$canModerate) return false;
    $comment = db_one('SELECT id FROM comments WHERE id = ?', [$commentId]);
    if (!$comment) return false;
    db_run('DELETE FROM comments WHERE id = ?', [$commentId]);
    return true;
}
