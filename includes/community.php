<?php
/**
 * Community module core (Phase 1 — Foundation): creating course/creator
 * communities at their real trigger points, membership, and the basic
 * homepage/directory queries. Feed, categories' post usage, profiles,
 * study groups, messaging, and notifications are later phases — see
 * C:\Users\hp\.claude\plans\iterative-scribbling-turing.md.
 */

require_once __DIR__ . '/community_feed.php';
require_once __DIR__ . '/profiles.php';

const DEFAULT_COMMUNITY_CATEGORIES = [
    ['📢', 'Announcements'],
    ['❓', 'Questions & Answers'],
    ['💡', 'Tips & Strategies'],
    ['🎯', 'Weekly Challenges'],
    ['🏆', 'Student Success Stories'],
    ['📂', 'Resources'],
    ['🎥', 'Live Class Replays'],
    ['📚', 'Assignments'],
    ['💬', 'General Discussion'],
];

/** Base slug + incrementing suffix until unique — same convention as course slugs (course-new.php). */
function unique_community_slug(string $base): string {
    $base = slugify($base) ?: 'community';
    $slug = $base;
    $n = 1;
    while (db_one('SELECT id FROM communities WHERE slug = ?', [$slug])) {
        $slug = "$base-" . $n++;
    }
    return $slug;
}

function seed_default_categories(int $communityId): void {
    foreach (DEFAULT_COMMUNITY_CATEGORIES as $i => [$icon, $name]) {
        db_insert(
            'INSERT INTO community_categories (community_id, name, icon, slug, is_default, sort_order) VALUES (?, ?, ?, ?, 1, ?)',
            [$communityId, $name, $icon, slugify($name), $i]
        );
    }
}

/**
 * Idempotent — safe to call from a re-approval or any retry path. Returns
 * the existing community's id if one already exists for this course rather
 * than throwing on the UNIQUE(course_id) constraint.
 */
function create_course_community(int $courseId): int {
    $existing = db_one('SELECT id FROM communities WHERE course_id = ?', [$courseId]);
    if ($existing) return (int) $existing['id'];

    $course = db_one('SELECT title, thumbnail_url FROM courses WHERE id = ?', [$courseId]);
    if (!$course) throw new InvalidArgumentException("No course $courseId");

    $name = $course['title'] . ' Community';
    $communityId = db_insert(
        'INSERT INTO communities (type, course_id, name, slug, description, banner_url) VALUES (?, ?, ?, ?, ?, ?)',
        ['course', $courseId, $name, unique_community_slug($name), 'The discussion space for everyone learning ' . $course['title'] . '.', $course['thumbnail_url']]
    );
    seed_default_categories($communityId);
    return $communityId;
}

/** Idempotent, same reasoning as create_course_community(). */
function create_creator_community(int $creatorId): int {
    $existing = db_one('SELECT id FROM communities WHERE creator_id = ?', [$creatorId]);
    if ($existing) return (int) $existing['id'];

    $creator = db_one('SELECT name, avatar_url FROM users WHERE id = ?', [$creatorId]);
    if (!$creator) throw new InvalidArgumentException("No user $creatorId");

    $name = $creator['name'] . "'s Community";
    $communityId = db_insert(
        'INSERT INTO communities (type, creator_id, name, slug, description, banner_url) VALUES (?, ?, ?, ?, ?, ?)',
        ['creator', $creatorId, $name, unique_community_slug($name), 'Follow ' . $creator['name'] . ' for announcements, Q&A, and what\'s coming next.', $creator['avatar_url']]
    );
    seed_default_categories($communityId);
    // The creator is automatically an owner of their own community.
    join_community($communityId, $creatorId, 'owner');
    return $communityId;
}

/**
 * Idempotent membership — a repeat call (e.g. re-enrolling) is a no-op, not
 * an error, and member_count only moves on an actual new row.
 */
function join_community(int $communityId, int $userId, string $role = 'member'): bool {
    $existing = db_one('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?', [$communityId, $userId]);
    if ($existing) return false;

    db_insert('INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, ?)', [$communityId, $userId, $role]);
    db_run('UPDATE communities SET member_count = member_count + 1 WHERE id = ?', [$communityId]);
    return true;
}

function leave_community(int $communityId, int $userId): bool {
    $deleted = db_run('DELETE FROM community_members WHERE community_id = ? AND user_id = ?', [$communityId, $userId]);
    if ($deleted > 0) db_run('UPDATE communities SET member_count = member_count - 1 WHERE id = ?', [$communityId]);
    return $deleted > 0;
}

function is_community_member(int $communityId, int $userId): bool {
    return (bool) db_one('SELECT id FROM community_members WHERE community_id = ? AND user_id = ?', [$communityId, $userId]);
}

function get_community_by_slug(string $slug): ?array {
    return db_one('SELECT * FROM communities WHERE slug = ?', [$slug]);
}

function get_community_by_course(int $courseId): ?array {
    return db_one('SELECT * FROM communities WHERE course_id = ?', [$courseId]);
}

function get_community_by_creator(int $creatorId): ?array {
    return db_one('SELECT * FROM communities WHERE creator_id = ?', [$creatorId]);
}

/** Members of a community, most recently joined first — the community's own directory/sidebar. */
function get_community_members(int $communityId, int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    return db_all(
        "SELECT cm.role, cm.joined_at, u.id, u.name, u.avatar_url, u.headline
         FROM community_members cm JOIN users u ON u.id = cm.user_id
         WHERE cm.community_id = ? ORDER BY cm.joined_at DESC LIMIT $limit",
        [$communityId]
    );
}

function get_community_categories(int $communityId): array {
    return db_all('SELECT * FROM community_categories WHERE community_id = ? ORDER BY sort_order ASC, id ASC', [$communityId]);
}

/** Largest communities by member count — the homepage's "Featured" row. */
function get_featured_communities(int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    return db_all(
        "SELECT c.*, co.slug AS course_slug, cr.name AS creator_name, cr.avatar_url AS creator_avatar_url
         FROM communities c
         LEFT JOIN courses co ON co.id = c.course_id
         LEFT JOIN users cr ON cr.id = c.creator_id
         ORDER BY c.member_count DESC, c.created_at DESC LIMIT $limit"
    );
}

/** Most recently created communities — the homepage's "New" row. */
function get_new_communities(int $limit = 6): array {
    $limit = max(1, min(20, $limit));
    return db_all(
        "SELECT c.*, co.slug AS course_slug, cr.name AS creator_name, cr.avatar_url AS creator_avatar_url
         FROM communities c
         LEFT JOIN courses co ON co.id = c.course_id
         LEFT JOIN users cr ON cr.id = c.creator_id
         ORDER BY c.created_at DESC LIMIT $limit"
    );
}

/**
 * Called from every point that grants a LOGGED-IN learner enrollment
 * (enrollment.php's enroll_in_course(), and payments.php's paid-success
 * path) — never from a guest path, since a guest has no persistent user_id
 * a community_members row could reference.
 *
 * create_course_community() is idempotent and lazily creates the community
 * if it doesn't exist yet — this doubles as the backfill for every course
 * that was already PUBLISHED before this feature existed, with no separate
 * migration script needed. The very first join after deploy creates it.
 */
function auto_join_course_community(int $userId, int $courseId): void {
    $communityId = create_course_community($courseId);
    join_community($communityId, $userId);
}

/** Every community a user belongs to — used on the homepage ("Your Communities") and profile. */
function get_user_communities(int $userId): array {
    return db_all(
        "SELECT c.*, co.slug AS course_slug, cr.name AS creator_name, cm.role
         FROM community_members cm
         JOIN communities c ON c.id = cm.community_id
         LEFT JOIN courses co ON co.id = c.course_id
         LEFT JOIN users cr ON cr.id = c.creator_id
         WHERE cm.user_id = ? ORDER BY cm.joined_at DESC",
        [$userId]
    );
}

/** Full directory (search/browse) — every community, newest first, optional name search. */
function search_communities(string $q = '', int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE c.name LIKE ?';
        $params[] = "%$q%";
    }
    return db_all(
        "SELECT c.*, co.slug AS course_slug, cr.name AS creator_name, cr.avatar_url AS creator_avatar_url
         FROM communities c
         LEFT JOIN courses co ON co.id = c.course_id
         LEFT JOIN users cr ON cr.id = c.creator_id
         $where ORDER BY c.member_count DESC LIMIT $limit",
        $params
    );
}
