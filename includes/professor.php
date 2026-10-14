<?php
/**
 * Professors. An account becomes a professor account when the admin links it to a campus (Admin,
 * Campuses, Professor tab: campuses.professor_user_id), and stops being one when it is unlinked.
 * Professors get their own dashboard (includes/professor_header.php: a top dock instead of the
 * student sidebar) with Overview, Campus Studio, Students, Community, My Pay and Settings, and none of
 * the student pages. They see their students' names and progress only, never contact details.
 */
require_once __DIR__ . '/campuses.php';

/** The campuses this account teaches (empty for everyone else, and for admins). */
function professor_campuses(?array $user): array {
    static $cache = [];
    if (!$user || $user['role'] === 'ADMIN') return [];
    $id = (int) $user['id'];
    if (!isset($cache[$id])) {
        try { $cache[$id] = get_professor_campuses($id); } catch (Throwable $e) { $cache[$id] = []; }
    }
    return $cache[$id];
}

function is_professor(?array $user): bool {
    return (bool) professor_campuses($user);
}

/** The campus being looked at (?campus=id), defaulting to the first one they teach. */
function professor_current_campus(array $user): ?array {
    $mine = professor_campuses($user);
    foreach ($mine as $c) if ((int) $c['id'] === (int) query_param('campus')) return $c;
    return $mine[0] ?? null;
}

/** Community messages from others since this professor last posted in the campus. */
function professor_unread_messages(int $campusId, int $userId): int {
    try {
        $last = db_one('SELECT MAX(created_at) AS t FROM campus_posts WHERE campus_id = ? AND user_id = ? AND deleted_at IS NULL', [$campusId, $userId])['t'] ?? null;
        return (int) (db_one(
            'SELECT COUNT(*) AS n FROM campus_posts WHERE campus_id = ? AND user_id <> ? AND deleted_at IS NULL' . ($last ? ' AND created_at > ?' : ''),
            $last ? [$campusId, $userId, $last] : [$campusId, $userId]
        )['n'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * The class's students: name, plan, when they joined, whether access is open, lessons done out of the
 * lessons in the months they can open, the month they are on, and their last finished lesson. No phone
 * numbers or emails.
 */
function professor_students(array $campus, string $show = 'active', string $q = '', int $limit = 500): array {
    $id = (int) $campus['id'];
    // Lessons in each month, added up, so progress counts the months each student can open.
    $perMonth = array_map('count', get_campus_lessons_by_month($id));
    $cum = [];
    $sum = 0;
    for ($m = 1; $m <= CAMPUS_YEAR_MONTHS; $m++) { $sum += $perMonth[$m] ?? 0; $cum[$m] = $sum; }
    $groups = campus_groups_ready();
    $where = ['m.campus_id = ?'];
    $params = [$id];
    if ($show === 'active') $where[] = 'm.access_until > NOW()';
    if ($show === 'ended') $where[] = 'm.access_until <= NOW()';
    if ($q !== '') { $where[] = 'u.name LIKE ?'; $params[] = '%' . $q . '%'; }
    $rows = db_all(
        'SELECT m.user_id, m.plan, m.started_at, m.access_until, m.certificate_code, u.name,' . ($groups ? ' m.month_open, m.next_month_at,' : '') . '
                (SELECT COUNT(*) FROM campus_lesson_progress p JOIN campus_lessons l ON l.id = p.lesson_id WHERE p.user_id = m.user_id AND l.campus_id = m.campus_id) AS done,
                (SELECT MAX(p.completed_at) FROM campus_lesson_progress p JOIN campus_lessons l ON l.id = p.lesson_id WHERE p.user_id = m.user_id AND l.campus_id = m.campus_id) AS last_done
         FROM campus_memberships m JOIN users u ON u.id = m.user_id
         WHERE ' . implode(' AND ', $where) . ' ORDER BY m.started_at DESC LIMIT ' . (int) $limit,
        $params
    );
    foreach ($rows as &$r) {
        $r['month'] = $groups ? campus_member_month($r) : campus_open_month($campus);
        $r['reached'] = $cum[$r['month']] ?? 0;
        $r['open'] = strtotime($r['access_until']) > time();
        $r['pct'] = $r['reached'] ? min(100, (int) round((int) $r['done'] / $r['reached'] * 100)) : 0;
    }
    return $rows;
}

/** Active students at the end of each of the last $weeks weeks, oldest first (for the sparkline). */
function professor_student_trend(int $campusId, int $weeks = 8): array {
    $out = [];
    for ($w = $weeks - 1; $w >= 0; $w--) {
        $at = date('Y-m-d H:i:s', strtotime("-$w weeks"));
        $out[] = (int) (db_one('SELECT COUNT(*) AS n FROM campus_memberships WHERE campus_id = ? AND started_at <= ? AND access_until > ?', [$campusId, $at, $at])['n'] ?? 0);
    }
    return $out;
}

function professor_initials(string $name): string {
    return mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2)))) ?: '?';
}
