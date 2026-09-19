<?php
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/functions.php';

/**
 * One-time "how was it?" review request, fired once a learner's enrollment
 * reaches 100% completion — closes the loop on the reviews feature
 * (courses/view.php's .rform) by actually pointing finishers at it, since
 * nothing else on the platform prompts for a review. Single send, guarded
 * by enrollments.review_nudge_sent_at — one thing to say, not a multi-stage
 * sequence like leads.php/retention.php.
 *
 * Guest enrollments (user_id NULL) are excluded — reviews.author_id is a
 * NOT NULL FK to users, so a guest has no account to attribute a review to
 * and the review form itself only ever renders for a logged-in learner.
 */
function get_due_review_nudges(): array {
    return db_all(
        "SELECT e.id AS enrollment_id, e.user_id,
                u.name AS learner_name, u.email AS learner_email,
                c.slug, c.title
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         JOIN courses c ON c.id = e.course_id
         WHERE e.progress >= 100
           AND e.review_nudge_sent_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.course_id = e.course_id AND r.author_id = e.user_id)"
    );
}

function send_one_review_nudge(array $row): void {
    $reviewUrl = base_url('courses/view.php?slug=' . $row['slug'] . '#reviews');
    send_review_nudge_email($row['learner_email'], $row['learner_name'], $row['title'], $reviewUrl);
    db_run('UPDATE enrollments SET review_nudge_sent_at = NOW() WHERE id = ?', [$row['enrollment_id']]);
}

/** Called from cron/track-maintenance.php. Returns the count sent, for the cron log line. */
function send_due_review_nudges(): int {
    $due = get_due_review_nudges();
    foreach ($due as $row) {
        send_one_review_nudge($row);
    }
    return count($due);
}
