<?php
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/user_notifications.php';
require_once __DIR__ . '/giphy.php';

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
 * @param ?string $gifUrl an attached GIF sticker's URL, as returned by
 *   includes/giphy.php — rides along with the required body text, never a
 *   replacement for it (a GIF/emoji alone is not a postable comment). Any
 *   URL not actually on Giphy's media CDN (someone bypassing the picker UI
 *   and POSTing a crafted value directly) is silently dropped rather than
 *   rejecting the whole comment, same defensive stance as elsewhere here.
 * @return array{ok?: bool, hidden?: bool, id?: int, error?: string}
 */
function add_comment(int $userId, int $courseId, string $body, ?int $replyToId = null, ?string $gifUrl = null): array {
    $body = trim($body);
    if (!gif_url_is_trusted($gifUrl)) $gifUrl = null;
    if ($body === '') return ['error' => 'Write a comment before posting.'];
    if (mb_strlen($body) < 2) return ['error' => 'Comment is too short.'];
    if (mb_strlen($body) > 2000) return ['error' => 'Comment is too long (2000 characters max).'];

    $course = db_one('SELECT id, title, slug, creator_id FROM courses WHERE id = ?', [$courseId]);
    if (!$course) return ['error' => 'Course not found.'];

    $parentId = null;
    $replyToCommentId = null;
    $replyToAuthorId = null;
    if ($replyToId !== null) {
        $target = db_one("SELECT id, parent_id, user_id FROM comments WHERE id = ? AND course_id = ? AND status = 'VISIBLE'", [$replyToId, $courseId]);
        if (!$target) return ['error' => 'The comment you\'re replying to no longer exists.'];
        $replyToCommentId = (int) $target['id'];
        $replyToAuthorId = (int) $target['user_id'];
        $parentId = $target['parent_id'] !== null ? (int) $target['parent_id'] : (int) $target['id'];
    }

    $blockedWord = comment_contains_blocked_language($body);
    $status = $blockedWord !== null ? 'HIDDEN' : 'VISIBLE';
    $hiddenReason = $blockedWord !== null ? 'Automatically hidden — contains blocked language.' : null;

    try {
        $id = db_insert(
            'INSERT INTO comments (body, gif_url, status, hidden_reason, user_id, course_id, parent_id, reply_to_comment_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$body, $gifUrl, $status, $hiddenReason, $userId, $courseId, $parentId, $replyToCommentId]
        );
    } catch (Throwable $e) {
        return ['error' => 'Comments aren\'t available right now — please try again shortly.'];
    }

    // A blocked-language comment is hidden from everyone including the
    // course creator, so nobody gets notified about something nobody but
    // an admin will ever see. Wrapped in its own try/catch, same defensive
    // reasoning as get_visible_comments()'s: a missing user_notifications
    // table (deploy landed before its migration ran) must not break
    // posting a comment — the comment itself already succeeded above, and
    // a lost notification is a much smaller problem than a 500 on submit.
    if ($status === 'VISIBLE') {
        try {
            $authorName = db_one('SELECT name FROM users WHERE id = ?', [$userId])['name'] ?? 'Someone';
            $link = base_url('courses/view.php?slug=' . $course['slug'] . '#comments');
            if ($replyToAuthorId !== null) {
                create_user_notification($replyToAuthorId, $userId, 'COMMENT_REPLY', "{$authorName} replied to your comment on \"{$course['title']}\".", $link);
            } else {
                create_user_notification((int) $course['creator_id'], $userId, 'NEW_COMMENT', "{$authorName} commented on your course \"{$course['title']}\".", $link);
            }
        } catch (Throwable $e) {
            // Notification failed — the comment itself still posted fine above.
        }
    }

    return ['ok' => true, 'hidden' => $status === 'HIDDEN', 'id' => $id];
}

/**
 * A short quoted preview of a comment — the actual text (truncated), or a
 * "GIF" placeholder for a sticker-only comment — used two ways: as the
 * in-bubble quote on whatever reply points at this comment, and as the
 * preview shown in the composer's reply chip when this comment itself
 * becomes the reply target (tapped or swiped).
 */
function comment_quote_snippet(array $row): string {
    $body = trim((string) ($row['body'] ?? ''));
    if ($body !== '') {
        return mb_strlen($body) > 80 ? mb_substr($body, 0, 80) . '…' : $body;
    }
    return !empty($row['gif_url']) ? 'GIF' : '';
}

/**
 * Publicly visible comments for one course/event's detail page, as a single
 * flat chronological list (newest first, matching the rest of the site) —
 * a WhatsApp-group-style chat rather than the old nested-under-its-thread
 * layout, so a reply doesn't get visually grouped under its parent; instead
 * every row carries 'reply_to_author_name' and 'reply_to_snippet' (a quoted
 * preview of whatever it's replying to, or both null for a top-level
 * comment) so the template can render that quote inline in the bubble.
 * Defensive: this runs on every single course/event page view, so a
 * missing `comments` table/column (deploy landed before its migration ran)
 * must not 500 the entire site's course/event pages — just show no
 * comments until it exists.
 * @param ?int $viewerUserId the logged-in visitor, if any — each row's
 *   'liked_by_me' reflects THEIR like state; a guest sees like counts but
 *   never a filled heart.
 */
function get_visible_comments(int $courseId, ?int $viewerUserId = null): array {
    try {
        $rows = db_all(
            "SELECT c.*, u.name AS author_name, u.avatar_url AS author_avatar_url,
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count,
                    " . ($viewerUserId !== null
                        ? "EXISTS(SELECT 1 FROM comment_likes cl2 WHERE cl2.comment_id = c.id AND cl2.user_id = ?) AS liked_by_me"
                        : "0 AS liked_by_me") . "
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.course_id = ? AND c.status = 'VISIBLE'
             ORDER BY c.created_at ASC",
            $viewerUserId !== null ? [$viewerUserId, $courseId] : [$courseId]
        );
    } catch (Throwable $e) {
        return [];
    }

    $byId = [];
    foreach ($rows as $row) {
        $row['like_count'] = (int) $row['like_count'];
        $row['liked_by_me'] = (bool) $row['liked_by_me'];
        $row['snippet'] = comment_quote_snippet($row);
        $byId[(int) $row['id']] = $row;
    }

    $flat = [];
    foreach ($byId as $id => $row) {
        $replyToId = $row['reply_to_comment_id'] !== null ? (int) $row['reply_to_comment_id'] : null;
        if ($replyToId !== null && isset($byId[$replyToId])) {
            $row['reply_to_author_name'] = $byId[$replyToId]['author_name'];
            $row['reply_to_user_id'] = (int) $byId[$replyToId]['user_id'];
            $row['reply_to_snippet'] = $byId[$replyToId]['snippet'];
        } else {
            $row['reply_to_author_name'] = null;
            $row['reply_to_user_id'] = null;
            $row['reply_to_snippet'] = null;
        }
        $flat[] = $row;
    }

    return array_reverse($flat);
}

/** @param bool $canModerate true for the comment's own author, the course's creator, or an admin. */
function delete_comment(int $commentId, bool $canModerate): bool {
    if (!$canModerate) return false;
    $comment = db_one('SELECT id FROM comments WHERE id = ?', [$commentId]);
    if (!$comment) return false;
    db_run('DELETE FROM comments WHERE id = ?', [$commentId]);
    return true;
}

/**
 * Toggles the current user's like on a comment or reply — inserts if not
 * already liked, removes if it is. The unique key on (comment_id, user_id)
 * is what actually prevents a double-like from two rapid clicks landing at
 * once; the try/catch just means that race ends in the same "liked" state
 * instead of a fatal error, and the final COUNT always reflects true state
 * either way.
 * @return array{liked: bool, count: int}
 */
function toggle_comment_like(int $userId, int $commentId): array {
    $comment = db_one(
        "SELECT c.id, c.user_id, co.title AS course_title, co.slug AS course_slug
         FROM comments c JOIN courses co ON co.id = c.course_id
         WHERE c.id = ? AND c.status = 'VISIBLE'",
        [$commentId]
    );
    if (!$comment) return ['liked' => false, 'count' => 0];

    $existing = db_one('SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?', [$commentId, $userId]);
    if ($existing) {
        db_run('DELETE FROM comment_likes WHERE id = ?', [$existing['id']]);
        $liked = false;
    } else {
        try {
            db_insert('INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)', [$commentId, $userId]);
            // Only on the like itself, never the unlike — an unlike undoing a
            // moment ago isn't news, and re-notifying on every toggle would
            // just spam whoever posted the comment.
            $likerName = db_one('SELECT name FROM users WHERE id = ?', [$userId])['name'] ?? 'Someone';
            $link = base_url('courses/view.php?slug=' . $comment['course_slug'] . '#comments');
            create_user_notification((int) $comment['user_id'], $userId, 'COMMENT_LIKE', "{$likerName} liked your comment on \"{$comment['course_title']}\".", $link);
        } catch (Throwable $e) {
            // Unique-key collision from a concurrent click (already liked, fine)
            // or a missing user_notifications table (deploy landed before its
            // migration ran) — either way the like itself is already recorded
            // above, so this is never worse than a lost notification.
        }
        $liked = true;
    }

    $count = (int) db_one('SELECT COUNT(*) AS n FROM comment_likes WHERE comment_id = ?', [$commentId])['n'];
    return ['liked' => $liked, 'count' => $count];
}
