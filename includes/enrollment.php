<?php
require_once __DIR__ . '/certificates.php';

/** @throws RuntimeException */
function enroll_in_course(int $userId, int $courseId): void {
    $course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
    if (!$course || $course['status'] !== 'PUBLISHED') {
        throw new RuntimeException('Course not found.');
    }
    if ((int) $course['creator_id'] === $userId) {
        throw new RuntimeException('Creators cannot enroll in their own course.');
    }

    $existing = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    if ($existing) return;

    $split = split_sale((float) $course['price']);
    $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);

    db()->beginTransaction();
    try {
        db_insert('INSERT INTO enrollments (user_id, course_id, expires_at) VALUES (?, ?, ?)', [$userId, $courseId, $expiresAt]);
        db_insert(
            'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, ?, ?, ?)',
            [$course['creator_id'], $courseId, $split['net'], $split['gross'], $split['fee']]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

/** @return array|null the newly-issued (or already-existing) certificate, if this update completed the course. */
function update_lesson_progress(?int $userId, int $courseId, float $progress, ?string $guestToken = null): ?array {
    $progress = max(0, min(100, $progress));
    if ($userId !== null) {
        db_run('UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?', [$progress, $userId, $courseId]);
        $enrollment = db_one('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    } elseif ($guestToken) {
        db_run('UPDATE enrollments SET progress = ? WHERE course_id = ? AND access_token_hash = ? AND user_id IS NULL', [$progress, $courseId, hash('sha256', $guestToken)]);
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
 * Guest checkout for a free course — no account required. Grants access via
 * a token (emailed, and handed back to the caller to store in-session for
 * immediate access). Re-submitting the same email for the same course
 * reissues a fresh token on the existing row rather than duplicating it.
 * @throws RuntimeException
 * @return array{token: string, course_slug: string, course_title: string}
 */
function enroll_guest_in_course(string $guestName, string $guestEmail, int $courseId): array {
    $course = db_one('SELECT * FROM courses WHERE id = ?', [$courseId]);
    if (!$course || $course['status'] !== 'PUBLISHED') {
        throw new RuntimeException('Course not found.');
    }
    if ((float) $course['price'] > 0) {
        throw new RuntimeException('This course requires payment — use the mobile money option.');
    }

    [$token, $tokenHash] = make_access_token();
    $expiresAt = compute_expires_at($course['access_duration_days'] !== null ? (int) $course['access_duration_days'] : null);
    $existing = db_one('SELECT id FROM enrollments WHERE course_id = ? AND guest_email = ? AND user_id IS NULL', [$courseId, $guestEmail]);

    db()->beginTransaction();
    try {
        if ($existing) {
            db_run('UPDATE enrollments SET access_token_hash = ?, guest_name = ? WHERE id = ?', [$tokenHash, $guestName, $existing['id']]);
        } else {
            db_insert(
                'INSERT INTO enrollments (user_id, guest_name, guest_email, access_token_hash, course_id, expires_at) VALUES (NULL, ?, ?, ?, ?, ?)',
                [$guestName, $guestEmail, $tokenHash, $courseId, $expiresAt]
            );
            db_insert(
                'INSERT INTO earnings (creator_id, course_id, amount, gross_amount, platform_fee) VALUES (?, ?, 0, 0, 0)',
                [$course['creator_id'], $courseId]
            );
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return ['token' => $token, 'course_slug' => $course['slug'], 'course_title' => $course['title']];
}

/** Looks up a guest's enrollment for one course from their session-held token, if any. */
function guest_enrollment_for_course(int $courseId): ?array {
    $token = $_SESSION['guest_course_tokens'][$courseId] ?? null;
    if (!$token) return null;
    return db_one('SELECT * FROM enrollments WHERE course_id = ? AND access_token_hash = ? AND user_id IS NULL', [$courseId, hash('sha256', $token)]);
}
