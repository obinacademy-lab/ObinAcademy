<?php
require_once __DIR__ . '/email.php';

/**
 * Broadcasts "a new course just went live" the moment an admin approves it
 * (see dashboard/admin/course-review.php) — sent synchronously, not queued,
 * because "immediately" was the explicit requirement. At this platform's
 * current scale (tens of users) that's a handful of Resend API calls and
 * finishes well within a normal request; if the user base grows into the
 * hundreds this should move to the cron sweep (same pattern as the lead
 * drip sequence / retention nudges) instead of running inline here.
 */

/**
 * Signed, stateless unsubscribe token for new-course-announcement emails —
 * its own token space (a 'c' prefix, its own HMAC salt), independent of
 * retention_unsubscribe_token() and unsubscribe_token() (leads), since a
 * user can opt out of each of those three email categories separately.
 */
function new_course_unsubscribe_token(int $userId): string {
    return 'c' . $userId . '.' . hash_hmac('sha256', 'new_course:' . $userId, APP_SECRET);
}

function new_course_unsubscribe_token_user_id(string $token): ?int {
    if (!str_starts_with($token, 'c')) return null;
    $parts = explode('.', substr($token, 1), 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) return null;
    [$userId, $signature] = $parts;
    if (!hash_equals(hash_hmac('sha256', 'new_course:' . $userId, APP_SECRET), $signature)) return null;
    return (int) $userId;
}

/**
 * $course needs id, title, slug, summary, creator_id, creator_name,
 * creator_email — exactly what course-review.php's approval query already
 * selects (with creator_email added to it).
 */
function notify_new_course_published(array $course): void {
    $courseUrl = base_url('courses/view.php?slug=' . $course['slug']);

    if ($course['creator_email']) {
        send_course_live_email_to_creator($course['creator_email'], $course['creator_name'], $course['title'], $courseUrl);
    }

    // Everyone else — every learner and creator except the course's own
    // creator (who already got the email above), and anyone who opted out.
    $recipients = db_all(
        "SELECT id, name, email FROM users
         WHERE role IN ('LEARNER', 'CREATOR') AND id != ? AND new_course_emails_opt_out = 0",
        [$course['creator_id']]
    );
    foreach ($recipients as $r) {
        $unsubscribeUrl = base_url('unsubscribe.php?token=' . new_course_unsubscribe_token((int) $r['id']));
        send_new_course_announcement_email($r['email'], $r['name'], $course['title'], $course['summary'], $course['creator_name'], $courseUrl, $unsubscribeUrl);
    }
}
