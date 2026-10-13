<?php
/**
 * A creator's potential buyers: people who showed real intent towards their school but haven't bought yet.
 *
 * Four signals, strongest first:
 *   paid      tried to pay for a course with mobile money and it failed or never completed (last 90 days)
 *   interest  tapped "Keep me updated" on a course
 *   views     a member who opened the same course 3 or more times
 *   follow    follows the school but owns none of its courses
 *
 * One entry per person (members by account, guest buyers by email). Members who switched
 * "show my contact to creators" off in Settings come back with their name and contact blanked.
 * A guest who tried to pay gave their name, email and phone to buy that creator's course, so the
 * creator sees them, the same way they see a guest who did buy (on My Students).
 * Each source sits in its own try/catch so a missing table never breaks the page.
 */

const CREATOR_LEAD_SIGNALS = ['paid' => 4, 'interest' => 3, 'views' => 2, 'follow' => 1];

function get_creator_leads(int $creatorId): array {
    $people = [];
    $add = function (string $key, array $who, string $signal, ?array $course, string $when, string $note = '') use (&$people) {
        if (!isset($people[$key])) {
            $people[$key] = $who + ['signals' => [], 'lines' => [], 'last' => $when];
        }
        $people[$key]['signals'][$signal] = true;
        if ($course) {
            $id = (int) $course['id'];
            if (!isset($people[$key]['lines'][$id])) {
                $people[$key]['lines'][$id] = ['id' => $id, 'title' => $course['title'], 'slug' => $course['slug'], 'notes' => []];
            }
            if ($note !== '') $people[$key]['lines'][$id]['notes'][$signal] = $note;
        }
        if ($when > $people[$key]['last']) $people[$key]['last'] = $when;
    };
    $member = function (array $r): array {
        $private = $r['contact_ok'] !== null && (int) $r['contact_ok'] === 0;
        return [
            'member' => true, 'private' => $private,
            'name' => $private ? null : $r['name'], 'email' => $private ? null : $r['email'],
            'phone' => $private ? null : $r['phone'], 'avatar' => $private ? null : $r['avatar_url'],
        ];
    };
    $owns = 'EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = c.id AND e.user_id = u.id)';

    // 1. Tried to pay and it didn't go through.
    try {
        $rows = db_all(
            "SELECT p.user_id, p.guest_name, p.guest_email, p.phone AS pay_phone, p.status, p.created_at,
                    c.id, c.title, c.slug,
                    u.name, u.email, u.phone, u.avatar_url, u.contact_visible_to_creators AS contact_ok
             FROM payments p
             JOIN courses c ON c.id = p.course_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE c.creator_id = ? AND p.type = 'COURSE_PURCHASE'
               AND (p.status = 'FAILED' OR (p.status = 'PENDING' AND p.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)))
               AND p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
               AND NOT EXISTS (
                 SELECT 1 FROM enrollments e WHERE e.course_id = p.course_id
                   AND ((p.user_id IS NOT NULL AND e.user_id = p.user_id) OR (p.user_id IS NULL AND e.guest_email = p.guest_email))
               )
             ORDER BY p.created_at DESC",
            [$creatorId]
        );
        foreach ($rows as $r) {
            if ($r['user_id']) {
                $who = $member($r);
                if (!$who['private'] && !$who['phone']) $who['phone'] = $r['pay_phone'];
                $key = 'u' . $r['user_id'];
            } else {
                $email = strtolower(trim((string) $r['guest_email']));
                $key = 'g' . ($email !== '' ? $email : preg_replace('/\D/', '', (string) $r['pay_phone']));
                $who = ['member' => false, 'private' => false, 'name' => $r['guest_name'] ?: 'Guest buyer', 'email' => $r['guest_email'], 'phone' => $r['pay_phone'], 'avatar' => null];
            }
            $add($key, $who, 'paid', $r, $r['created_at'], $r['status'] === 'FAILED' ? 'payment failed' : 'payment not completed');
        }
    } catch (Throwable $e) {}

    // 2. Asked to be kept updated.
    try {
        $rows = db_all(
            "SELECT ci.created_at, c.id, c.title, c.slug, u.id AS user_id, u.name, u.email, u.phone, u.avatar_url, u.contact_visible_to_creators AS contact_ok
             FROM course_interest ci JOIN courses c ON c.id = ci.course_id JOIN users u ON u.id = ci.user_id
             WHERE c.creator_id = ? AND u.id <> ? AND NOT $owns
             ORDER BY ci.created_at DESC",
            [$creatorId, $creatorId]
        );
        foreach ($rows as $r) $add('u' . $r['user_id'], $member($r), 'interest', $r, $r['created_at'], 'asked for updates');
    } catch (Throwable $e) {}

    // 3. Keeps coming back to the same course.
    try {
        $rows = db_all(
            "SELECT v.view_count, v.last_viewed_at, c.id, c.title, c.slug, u.id AS user_id, u.name, u.email, u.phone, u.avatar_url, u.contact_visible_to_creators AS contact_ok
             FROM course_views v JOIN courses c ON c.id = v.course_id JOIN users u ON u.id = v.user_id
             WHERE c.creator_id = ? AND u.id <> ? AND v.view_count >= 3 AND NOT $owns
             ORDER BY v.last_viewed_at DESC",
            [$creatorId, $creatorId]
        );
        foreach ($rows as $r) $add('u' . $r['user_id'], $member($r), 'views', $r, $r['last_viewed_at'], 'viewed ' . (int) $r['view_count'] . ' times');
    } catch (Throwable $e) {}

    // 4. Follows the school but owns none of its courses.
    try {
        $rows = db_all(
            "SELECT sf.created_at, u.id AS user_id, u.name, u.email, u.phone, u.avatar_url, u.contact_visible_to_creators AS contact_ok
             FROM school_follows sf JOIN users u ON u.id = sf.learner_id
             WHERE sf.creator_id = ? AND NOT EXISTS (
               SELECT 1 FROM enrollments e JOIN courses c2 ON c2.id = e.course_id WHERE c2.creator_id = sf.creator_id AND e.user_id = u.id
             )
             ORDER BY sf.created_at DESC",
            [$creatorId]
        );
        foreach ($rows as $r) $add('u' . $r['user_id'], $member($r), 'follow', null, $r['created_at']);
    } catch (Throwable $e) {}

    foreach ($people as &$p) {
        $p['lines'] = array_values($p['lines']);
        $p['score'] = max(array_map(fn($s) => CREATOR_LEAD_SIGNALS[$s], array_keys($p['signals'])));
    }
    unset($p);
    uasort($people, fn($a, $b) => [$b['score'], $b['last']] <=> [$a['score'], $a['last']]);
    return array_values($people);
}
