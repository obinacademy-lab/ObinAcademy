<?php
require_once __DIR__ . '/moderation.php';

/**
 * Posting a comment needs an account, but never enrollment/a ticket — that's
 * what separates it from reviews (includes/functions.php has no equivalent
 * gate here). A comment containing blocked language is stored HIDDEN rather
 * than rejected outright, so the poster doesn't see it and an admin can
 * still review/restore a false positive later.
 * @param ?int $parentId set to reply to another comment. Threads are capped
 *   at two levels — replying to a reply re-parents onto that reply's own
 *   parent instead of nesting further.
 * @return array{ok?: bool, hidden?: bool, id?: int, error?: string}
 */
function add_comment(int $userId, int $courseId, string $body, ?int $parentId = null): array {
    $body = trim($body);
    if ($body === '') return ['error' => 'Write a comment before posting.'];
    if (mb_strlen($body) < 2) return ['error' => 'Comment is too short.'];
    if (mb_strlen($body) > 2000) return ['error' => 'Comment is too long (2000 characters max).'];

    $course = db_one('SELECT id FROM courses WHERE id = ?', [$courseId]);
    if (!$course) return ['error' => 'Course not found.'];

    if ($parentId !== null) {
        $parent = db_one("SELECT id, parent_id FROM comments WHERE id = ? AND course_id = ? AND status = 'VISIBLE'", [$parentId, $courseId]);
        if (!$parent) return ['error' => 'The comment you\'re replying to no longer exists.'];
        $parentId = $parent['parent_id'] !== null ? (int) $parent['parent_id'] : (int) $parent['id'];
    }

    $blockedWord = comment_contains_blocked_language($body);
    $status = $blockedWord !== null ? 'HIDDEN' : 'VISIBLE';
    $hiddenReason = $blockedWord !== null ? 'Automatically hidden — contains blocked language.' : null;

    try {
        $id = db_insert(
            'INSERT INTO comments (body, status, hidden_reason, user_id, course_id, parent_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$body, $status, $hiddenReason, $userId, $courseId, $parentId]
        );
    } catch (Throwable $e) {
        return ['error' => 'Comments aren\'t available right now — please try again shortly.'];
    }

    return ['ok' => true, 'hidden' => $status === 'HIDDEN', 'id' => $id];
}

/**
 * Publicly visible comments for one course/event's detail page, as a tree —
 * each top-level comment (newest first) carries its replies (oldest first)
 * in a 'replies' key. Defensive: this runs on every single course/event
 * page view, so a missing `comments`/`parent_id` column (deploy landed
 * before its migration ran) must not 500 the entire site's course/event
 * pages — just show no comments until it exists.
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
        $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        if ($parentId !== null && isset($byId[$parentId])) {
            $byId[$parentId]['replies'][] = $row;
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
