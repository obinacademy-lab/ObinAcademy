<?php
/**
 * "Who viewed my course" for creators (dashboard/creator/viewers.php).
 *
 * Members (logged-in accounts) are recorded by user_id and creators can see their
 * name and contact details unless the member switched that off in Settings
 * (users.contact_visible_to_creators). Anonymous visitors are recorded by their
 * oa_visitor cookie, which only exists once they accepted cookies, and are shown
 * to creators as "Guest viewer" with no contact details at all — we have none.
 *
 * Everything here fails quietly if the course_views table isn't there yet, so a
 * deploy that lands before its migration (migration/add-course-viewers.sql)
 * never breaks a course page.
 */

/** Notes one view of a published course. Same person within 30 minutes counts once. */
function record_course_view(int $courseId, ?int $userId, ?string $visitorId): void {
    try {
        $visitorId = ($visitorId !== null && preg_match('/^[a-f0-9]{32}$/', $visitorId)) ? $visitorId : null;
        if ($userId) {
            db_run(
                'INSERT INTO course_views (course_id, user_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE
                   view_count = view_count + IF(last_viewed_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, 0),
                   last_viewed_at = NOW()',
                [$courseId, $userId]
            );
            // Same person, now signed in: the earlier anonymous row is theirs, so fold it in.
            if ($visitorId) {
                db_run('DELETE FROM course_views WHERE course_id = ? AND visitor_id = ? AND user_id IS NULL', [$courseId, $visitorId]);
            }
        } elseif ($visitorId && !is_bot_user_agent($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            db_run(
                'INSERT INTO course_views (course_id, visitor_id) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE
                   view_count = view_count + IF(last_viewed_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, 0),
                   last_viewed_at = NOW()',
                [$courseId, $visitorId]
            );
        }
    } catch (Throwable $e) {
        // Viewer tracking must never break a course page.
    }
}

/** True once the course_views table exists (migration applied). */
function course_viewers_ready(): bool {
    try {
        db_one('SELECT 1 FROM course_views LIMIT 1');
        db_one('SELECT contact_visible_to_creators FROM users LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Viewers of a creator's courses, newest first. Filters: course_id, who ('members'|'guests'),
 * status ('open' = not enrolled yet, 'enrolled'), q (name / email). Contact fields are blanked for
 * members who switched visibility off; their name is withheld too.
 */
function get_course_viewers(int $creatorId, array $f = [], int $limit = 400): array {
    $where = ['c.creator_id = ?'];
    $params = [$creatorId];
    if (!empty($f['course_id'])) { $where[] = 'v.course_id = ?'; $params[] = (int) $f['course_id']; }
    if (($f['who'] ?? '') === 'members') $where[] = 'v.user_id IS NOT NULL';
    if (($f['who'] ?? '') === 'guests') $where[] = 'v.user_id IS NULL';
    if (($f['q'] ?? '') !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like);
    }
    $rows = db_all(
        "SELECT v.id, v.course_id, v.user_id, v.visitor_id, v.view_count, v.first_viewed_at, v.last_viewed_at,
                c.title AS course_title, c.slug AS course_slug,
                u.name, u.email, u.phone, u.avatar_url, u.contact_visible_to_creators AS contact_ok,
                (u.id IS NOT NULL AND EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = v.course_id AND e.user_id = v.user_id)) AS enrolled,
                (u.id IS NOT NULL AND EXISTS (SELECT 1 FROM course_interest ci WHERE ci.course_id = v.course_id AND ci.user_id = v.user_id)) AS interested,
                s.device_type, s.country, s.city, s.referrer_source
         FROM course_views v
         JOIN courses c ON c.id = v.course_id
         LEFT JOIN users u ON u.id = v.user_id
         LEFT JOIN visitor_sessions s ON s.id = (SELECT MAX(s2.id) FROM visitor_sessions s2 WHERE s2.visitor_id = v.visitor_id)
         WHERE " . implode(' AND ', $where) . '
         ORDER BY v.last_viewed_at DESC, v.id DESC
         LIMIT ' . (int) $limit,
        $params
    );
    $status = $f['status'] ?? '';
    $out = [];
    foreach ($rows as $r) {
        $isMember = $r['user_id'] !== null;
        $r['is_member'] = $isMember;
        $r['enrolled'] = (bool) $r['enrolled'];
        $r['interested'] = (bool) $r['interested'];
        // A guest can't be "enrolled" here (a guest who bought is a student, not a viewer row), so
        // the "not enrolled yet" view keeps guests and only drops members who already enrolled.
        if ($status === 'open' && $r['enrolled']) continue;
        if ($status === 'enrolled' && !$r['enrolled']) continue;
        $r['private'] = $isMember && $r['contact_ok'] !== null && (int) $r['contact_ok'] === 0;
        if ($r['private']) { $r['name'] = null; $r['email'] = null; $r['phone'] = null; }
        $out[] = $r;
    }
    return $out;
}
