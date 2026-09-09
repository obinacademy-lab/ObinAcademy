<?php
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/enrollment.php';

/**
 * Learner re-engagement nudges — one short email per inactivity "episode",
 * escalating through three stages (5h / 24h / 72h since last learning
 * activity), each with its own pool of message variants so a learner never
 * sees the same wording twice in a row. Modeled directly on the lead
 * drip-sequence in includes/leads.php: range-based eligibility (not exact-
 * hour equality, so a missed cron run never permanently skips a stage), and
 * a log table (retention_notifications) as the only send guard.
 *
 * The "reset the timer on return" requirement needs no separate code path:
 * every stage's eligibility check requires last_activity_at <= the stage
 * threshold AND no retention_notifications row for that stage newer than
 * last_activity_at. The moment a learner resumes a lesson,
 * update_lesson_progress() bumps last_activity_at forward, which makes every
 * past notification row read as "before their last activity" again — so all
 * three stages become eligible again next time they go quiet, and none fire
 * early because last_activity_at itself no longer satisfies the threshold.
 */

/** @return array{title:string, url:string} pointing at where "Continue Learning" should land. */
function retention_course_target(array $enrollment): array {
    $course = db_one('SELECT title, slug FROM courses WHERE id = ?', [$enrollment['course_id']]);
    return [
        'title' => $course['title'] ?? 'your course',
        'url' => base_url('courses/view.php?slug=' . ($course['slug'] ?? '')),
    ];
}

/**
 * Each entry builds one message variant from whatever personalization data
 * is available. $lesson may be null (course has no lessons yet, or — for a
 * freshly enrolled learner — progress is 0) so every variant degrades
 * gracefully without a lesson name rather than skip learners who lack one.
 * @return array<string, array{subject: string, emoji: string, headline: string, body: string, cta: string}>
 */
function retention_template_pool(string $name, string $courseTitle, ?string $lesson, float $progress): array {
    $firstName = trim(explode(' ', $name)[0] ?? '') ?: 'there';
    $progressInt = (int) round($progress);
    $lessonClause = $lesson ? "\"{$lesson}\"" : 'your next lesson';

    return [
        'momentum' => [
            'subject' => 'Your momentum is waiting, ' . $firstName,
            'emoji' => '🔥',
            'headline' => 'Keep your momentum going',
            'body' => "Your next lesson in <strong>{$courseTitle}</strong> is waiting. You've already started — come back and keep the momentum going.",
            'cta' => 'Continue Learning',
        ],
        'progress' => [
            'subject' => "Don't stop now, {$firstName}",
            'emoji' => '🎯',
            'headline' => "Don't stop now",
            'body' => "You're {$progressInt}% through <strong>{$courseTitle}</strong>. Come back and complete your next lesson.",
            'cta' => 'Continue Learning',
        ],
        'five_hours' => [
            'subject' => 'Your course is still waiting',
            'emoji' => '📚',
            'headline' => 'A few hours away is enough',
            'body' => "<strong>{$courseTitle}</strong> is still right where you left it. Resume your learning and keep moving forward.",
            'cta' => 'Resume Lesson',
        ],
        'closer' => [
            'subject' => "You're closer than you think",
            'emoji' => '🚀',
            'headline' => "You're closer than you think",
            'body' => "You're {$progressInt}% done with <strong>{$courseTitle}</strong>. Come back and continue from exactly where you stopped.",
            'cta' => 'Get Back to Your Course',
        ],
        'keep_alive' => [
            'subject' => 'Keep your progress alive',
            'emoji' => '🏆',
            'headline' => 'Keep your progress alive',
            'body' => "Your next lesson, {$lessonClause}, is ready in <strong>{$courseTitle}</strong>. Jump back in and continue learning.",
            'cta' => 'Continue Learning',
        ],
        'still_there' => [
            'subject' => "{$courseTitle} hasn't gone anywhere",
            'emoji' => '⏳',
            'headline' => "It's exactly where you left it",
            'body' => "No rush, {$firstName} — <strong>{$courseTitle}</strong> and your {$progressInt}% progress are saved and waiting whenever you're ready.",
            'cta' => 'Resume Lesson',
        ],
    ];
}

/**
 * Learners whose most-recently-touched, still-incomplete course has been
 * quiet for at least $minHours, and haven't already been sent this stage
 * since that course's last_activity_at. "Most-recently-touched incomplete
 * enrollment" is what get_next_lesson_for_course()/the email personalize
 * around — a learner with three courses only gets nudged about the one they
 * were actually last working on, not all three at once.
 */
function get_due_retention_learners(int $minHours, string $stage): array {
    return db_all(
        "SELECT e.*, u.name AS learner_name, u.email AS learner_email
         FROM enrollments e
         JOIN users u ON u.id = e.user_id
         WHERE e.user_id IS NOT NULL
           AND e.progress < 100
           AND e.last_activity_at = (
             SELECT MAX(e2.last_activity_at) FROM enrollments e2
             WHERE e2.user_id = e.user_id AND e2.progress < 100
           )
           AND e.last_activity_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
           AND NOT EXISTS (
             SELECT 1 FROM retention_notifications r
             WHERE r.enrollment_id = e.id AND r.stage = ? AND r.sent_at >= e.last_activity_at
           )",
        [$minHours, $stage]
    );
}

/** The template_key this learner saw last time this exact stage fired, if any — excluded from this pick so the wording changes between episodes. */
function retention_last_template_for_stage(int $enrollmentId, string $stage): ?string {
    $row = db_one(
        'SELECT template_key FROM retention_notifications WHERE enrollment_id = ? AND stage = ? ORDER BY sent_at DESC LIMIT 1',
        [$enrollmentId, $stage]
    );
    return $row['template_key'] ?? null;
}

function send_one_retention_notification(array $enrollment, string $stage): void {
    $target = retention_course_target($enrollment);
    $lesson = get_next_lesson_for_course((int) $enrollment['course_id'], (float) $enrollment['progress']);
    $pool = retention_template_pool($enrollment['learner_name'], $target['title'], $lesson, (float) $enrollment['progress']);

    $lastKey = retention_last_template_for_stage((int) $enrollment['id'], $stage);
    $choices = array_diff_key($pool, [$lastKey => true]) ?: $pool;
    $templateKey = array_rand($choices);
    $t = $pool[$templateKey];

    send_retention_nudge_email($enrollment['learner_email'], $t['subject'], $t['emoji'], $t['headline'], $t['body'], $t['cta'], $target['url']);

    db_insert(
        'INSERT INTO retention_notifications (stage, template_key, user_id, enrollment_id) VALUES (?, ?, ?, ?)',
        [$stage, $templateKey, $enrollment['user_id'], $enrollment['id']]
    );
}

/** Called from cron/track-maintenance.php. Returns counts sent per stage, for the cron log line. */
function send_due_retention_notifications(): array {
    $counts = [];
    foreach (['5h' => 5, '24h' => 24, '72h' => 72] as $stage => $minHours) {
        $due = get_due_retention_learners($minHours, $stage);
        foreach ($due as $enrollment) {
            send_one_retention_notification($enrollment, $stage);
        }
        $counts[$stage] = count($due);
    }
    return $counts;
}
