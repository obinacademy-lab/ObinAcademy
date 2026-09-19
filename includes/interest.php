<?php
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/functions.php';

/**
 * One-time "you were interested, still want in?" nudge for a learner who
 * clicked "Keep me updated" on a course (course_interest) but never
 * enrolled — distinct from the creator's own manual outreach to that same
 * list (get_interested_learners() in data.php). A single send per row,
 * guarded by course_interest.reminder_sent_at, not a multi-stage sequence
 * like leads.php/retention.php — there's only ever one thing to say here.
 */

/**
 * Signed, stateless unsubscribe token — its own 'w' (wishlist) prefix and
 * HMAC salt, independent of retention_unsubscribe_token() ('u') and
 * new_course_unsubscribe_token() ('c'), so a learner can opt out of this
 * category without touching the other two.
 */
function interest_unsubscribe_token(int $userId): string {
    return 'w' . $userId . '.' . hash_hmac('sha256', 'interest:' . $userId, APP_SECRET);
}

function interest_unsubscribe_token_user_id(string $token): ?int {
    if (!str_starts_with($token, 'w')) return null;
    $parts = explode('.', substr($token, 1), 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) return null;
    [$userId, $signature] = $parts;
    if (!hash_equals(hash_hmac('sha256', 'interest:' . $userId, APP_SECRET), $signature)) return null;
    return (int) $userId;
}

/**
 * Interest rows at least 48h old, not yet reminded, where the learner still
 * hasn't enrolled in that exact course and hasn't opted out. 48h (not a
 * shorter window) gives a real chance the learner enrolls on their own —
 * and matches the same "let it breathe first" spirit as retention's 5h
 * floor, just longer since there was never any progress to lose here.
 */
function get_due_interest_reminders(): array {
    return db_all(
        "SELECT ci.id AS interest_id, ci.user_id, ci.course_id,
                u.name AS learner_name, u.email AS learner_email,
                c.title, c.slug, c.price, c.sale_price, c.sale_ends_at,
                cu.name AS creator_name
         FROM course_interest ci
         JOIN users u ON u.id = ci.user_id
         JOIN courses c ON c.id = ci.course_id
         JOIN users cu ON cu.id = c.creator_id
         WHERE ci.reminder_sent_at IS NULL
           AND ci.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)
           AND u.interest_emails_opt_out = 0
           AND c.status = 'PUBLISHED'
           AND NOT EXISTS (
             SELECT 1 FROM enrollments e WHERE e.user_id = ci.user_id AND e.course_id = ci.course_id
           )"
    );
}

function send_one_interest_reminder(array $row): void {
    $courseUrl = base_url('courses/view.php?slug=' . $row['slug']);
    $hasSale = course_has_active_sale($row);
    $unsubscribeUrl = base_url('unsubscribe.php?token=' . interest_unsubscribe_token((int) $row['user_id']));

    send_course_interest_reminder_email(
        $row['learner_email'],
        $row['learner_name'],
        $row['title'],
        $row['creator_name'],
        $courseUrl,
        $hasSale ? (float) $row['sale_price'] : null,
        (float) $row['price'],
        $unsubscribeUrl
    );

    db_run('UPDATE course_interest SET reminder_sent_at = NOW() WHERE id = ?', [$row['interest_id']]);
}

/** Called from cron/track-maintenance.php. Returns the count sent, for the cron log line. */
function send_due_interest_reminders(): int {
    $due = get_due_interest_reminders();
    foreach ($due as $row) {
        send_one_interest_reminder($row);
    }
    return count($due);
}
