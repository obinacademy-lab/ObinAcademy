<?php
/**
 * Community module — Phase 6 (report-content review flow). A basic
 * list + resolve flow per the plan, not a full moderation queue UI —
 * that's explicitly deferred past v1.
 */

const REPORTABLE_TYPES = ['post', 'comment'];

/** One pending report per (content, reporter) — reporting the same thing twice while it's still pending is a silent no-op, not a duplicate row. */
function report_content(string $reportableType, int $reportableId, int $reporterId, string $reason): bool {
    if (!in_array($reportableType, REPORTABLE_TYPES, true)) return false;
    if (trim($reason) === '') return false;

    $existing = db_one(
        "SELECT id FROM community_reports WHERE reportable_type = ? AND reportable_id = ? AND reporter_id = ? AND status = 'pending'",
        [$reportableType, $reportableId, $reporterId]
    );
    if ($existing) return false;

    db_insert(
        'INSERT INTO community_reports (reportable_type, reportable_id, reporter_id, reason) VALUES (?, ?, ?, ?)',
        [$reportableType, $reportableId, $reporterId, trim($reason)]
    );
    return true;
}

function get_pending_reports(int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    return db_all(
        "SELECT r.*, u.name AS reporter_name
         FROM community_reports r JOIN users u ON u.id = r.reporter_id
         WHERE r.status = 'pending' ORDER BY r.created_at ASC LIMIT $limit"
    );
}

function get_pending_report_count(): int {
    return (int) db_one("SELECT COUNT(*) AS n FROM community_reports WHERE status = 'pending'")['n'];
}

/** Resolves a report's underlying post/comment into a preview (body, author, a link to it) regardless of type — null if the content is already gone. */
function get_report_target(array $report): ?array {
    if ($report['reportable_type'] === 'post') {
        $post = db_one(
            "SELECT p.id, p.body, p.community_id, u.name AS author_name
             FROM community_posts p JOIN users u ON u.id = p.author_id
             WHERE p.id = ?",
            [$report['reportable_id']]
        );
        if (!$post) return null;
        return [
            'body' => $post['body'],
            'author_name' => $post['author_name'],
            'community_id' => (int) $post['community_id'],
            'link' => '/community/post.php?id=' . $post['id'],
        ];
    }

    $comment = db_one(
        "SELECT cm.id, cm.body, cm.post_id, u.name AS author_name, p.community_id
         FROM community_comments cm JOIN users u ON u.id = cm.author_id JOIN community_posts p ON p.id = cm.post_id
         WHERE cm.id = ?",
        [$report['reportable_id']]
    );
    if (!$comment) return null;
    return [
        'body' => $comment['body'],
        'author_name' => $comment['author_name'],
        'community_id' => (int) $comment['community_id'],
        'link' => '/community/post.php?id=' . $comment['post_id'] . '#comment-' . $comment['id'],
    ];
}

function dismiss_report(int $reportId): bool {
    return db_run("UPDATE community_reports SET status = 'dismissed' WHERE id = ? AND status = 'pending'", [$reportId]) > 0;
}

/** Removes the reported content (delete_post()/delete_comment() already treat an ADMIN as able to moderate any community) and marks the report reviewed. */
function resolve_report_remove_content(int $reportId, int $actingAdminId): bool {
    $report = db_one("SELECT * FROM community_reports WHERE id = ? AND status = 'pending'", [$reportId]);
    if (!$report) return false;

    // If the content is already gone (get_report_target() -> null), there's
    // nothing left to remove — that still counts as resolved. Otherwise the
    // deletion must actually succeed before the report is marked reviewed,
    // so a permission failure never gets silently reported as "handled".
    $target = get_report_target($report);
    if ($target) {
        $deleted = $report['reportable_type'] === 'post'
            ? delete_post((int) $report['reportable_id'], $target['community_id'], $actingAdminId)
            : delete_comment((int) $report['reportable_id'], $target['community_id'], $actingAdminId);
        if (!$deleted) return false;
    }

    db_run("UPDATE community_reports SET status = 'reviewed' WHERE id = ?", [$reportId]);
    return true;
}
