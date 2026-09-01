<?php
require_once __DIR__ . '/email.php';

/**
 * Issues a certificate the first time an enrollment reaches 100% progress,
 * and emails it to the learner (guest or logged-in) so they still have it
 * even if they close the tab before noticing the in-page banner. Safe to
 * call repeatedly — returns the existing certificate if one was already
 * issued, so callers don't need to check eligibility themselves.
 * @return array|null null if the enrollment isn't actually complete.
 */
function issue_certificate_if_eligible(int $enrollmentId): ?array {
    $existing = db_one('SELECT * FROM certificates WHERE enrollment_id = ?', [$enrollmentId]);
    if ($existing) return $existing;

    $enrollment = db_one('SELECT * FROM enrollments WHERE id = ?', [$enrollmentId]);
    if (!$enrollment || (float) $enrollment['progress'] < 100) return null;

    $code = 'OA-' . strtoupper(bin2hex(random_bytes(5)));
    $id = db_insert(
        'INSERT INTO certificates (code, enrollment_id, course_id) VALUES (?, ?, ?)',
        [$code, $enrollmentId, $enrollment['course_id']]
    );
    $cert = db_one('SELECT * FROM certificates WHERE id = ?', [$id]);

    $full = get_certificate_by_code($code);
    if ($full) {
        $to = $full['learner_email'] ?? $full['guest_email'] ?? null;
        $name = $full['learner_name'] ?? $full['guest_name'] ?? 'there';
        if ($to) {
            send_certificate_email($to, $name, $full['course_title'], base_url('certificate.php?code=' . $code));
        }
    }

    return $cert;
}

/** Full certificate details for display/email: learner name+email, course, creator, issue date. */
function get_certificate_by_code(string $code): ?array {
    return db_one(
        'SELECT cert.*, en.user_id, en.guest_name, en.guest_email,
            c.title AS course_title, c.slug AS course_slug,
            u.name AS learner_name, u.email AS learner_email,
            creator.name AS creator_name
         FROM certificates cert
         JOIN enrollments en ON en.id = cert.enrollment_id
         JOIN courses c ON c.id = cert.course_id
         JOIN users creator ON creator.id = c.creator_id
         LEFT JOIN users u ON u.id = en.user_id
         WHERE cert.code = ?',
        [$code]
    );
}
