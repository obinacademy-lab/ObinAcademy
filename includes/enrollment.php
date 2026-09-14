<?php
require_once __DIR__ . '/certificates.php';

/** @return array|null the newly-issued (or already-existing) certificate, if this update completed the course. */
function update_lesson_progress(?int $userId, int $courseId, float $progress, ?string $guestToken = null): ?array {
    $progress = max(0, min(100, $progress));
    if ($userId !== null) {
        db_run('UPDATE enrollments SET progress = ?, last_activity_at = NOW() WHERE user_id = ? AND course_id = ?', [$progress, $userId, $courseId]);
        $enrollment = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    } elseif ($guestToken) {
        db_run('UPDATE enrollments SET progress = ?, last_activity_at = NOW() WHERE course_id = ? AND access_token_hash = ? AND user_id IS NULL', [$progress, $courseId, hash('sha256', $guestToken)]);
        $enrollment = db_one('SELECT id FROM enrollments WHERE course_id = ? AND access_token_hash = ? AND user_id IS NULL', [$courseId, hash('sha256', $guestToken)]);
    } else {
        return null;
    }

    if ($enrollment && $progress >= 100) {
        return issue_certificate_if_eligible((int) $enrollment['id']);
    }
    return null;
}

/**
 * Best-guess "next lesson" for a learner sitting at $progress% — there's no
 * per-lesson completion tracking (only the course-level progress percentage),
 * so this estimates a position by mapping progress onto the lesson list in
 * course order, same convention a progress bar already implies. Returns null
 * for a course with no lessons yet.
 */
function get_next_lesson_for_course(int $courseId, float $progress): ?string {
    $lessons = db_all(
        'SELECT l.title FROM lessons l JOIN modules m ON m.id = l.module_id
         WHERE m.course_id = ? ORDER BY m.sort_order, l.sort_order',
        [$courseId]
    );
    if (!$lessons) return null;

    $index = (int) floor(($progress / 100) * count($lessons));
    $index = max(0, min(count($lessons) - 1, $index));
    return $lessons[$index]['title'];
}

/**
 * Looks up a guest's enrollment for one course from their session-held
 * token, if any — the only way a guest reaches this now is a grandfathered
 * pre-subscription-era session (guest checkout itself was retired along
 * with enroll_guest_in_course()/access.php once every course required a
 * subscription), so this stays purely to honor an already-established one.
 */
function guest_enrollment_for_course(int $courseId): ?array {
    $token = $_SESSION['guest_course_tokens'][$courseId] ?? null;
    if (!$token) return null;
    return db_one('SELECT * FROM enrollments WHERE course_id = ? AND access_token_hash = ? AND user_id IS NULL', [$courseId, hash('sha256', $token)]);
}
