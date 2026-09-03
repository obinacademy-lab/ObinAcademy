<?php
/**
 * Community module — Phase 3 (Member profiles & follow). See
 * C:\Users\hp\.claude\plans\iterative-scribbling-turing.md.
 *
 * "Looking for" is a fixed vocabulary (checkboxes on settings.php) rather
 * than free text, so a profile's networking intent stays scannable/filterable
 * later — the dedicated filter/discovery UI itself is deferred past v1.
 */

const LOOKING_FOR_OPTIONS = [
    'Job Opportunities', 'Clients', 'Business Partners', 'Mentorship', 'Co-founder', 'Networking', 'Freelance Work',
];

/** Splits a stored "a, b, c" tag string back into a clean list. */
function parse_csv_tags(?string $csv): array {
    if (!$csv) return [];
    return array_values(array_filter(array_map('trim', explode(',', $csv))));
}

/** Joins a list of tags (e.g. checked form values) back into the stored "a, b, c" form, or null if none. */
function tags_to_csv(array $tags): ?string {
    $tags = array_values(array_filter(array_map('trim', $tags)));
    return $tags ? implode(', ', $tags) : null;
}

function get_profile(int $userId): ?array {
    $u = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$u) return null;
    $u['skills_list'] = parse_csv_tags($u['skills'] ?? null);
    $u['looking_for_list'] = parse_csv_tags($u['looking_for'] ?? null);
    return $u;
}

function follow_user(int $followerId, int $followedId): bool {
    if ($followerId === $followedId) return false;
    $existing = db_one('SELECT id FROM user_follows WHERE follower_id = ? AND followed_id = ?', [$followerId, $followedId]);
    if ($existing) return false;
    db_insert('INSERT INTO user_follows (follower_id, followed_id) VALUES (?, ?)', [$followerId, $followedId]);
    return true;
}

function unfollow_user(int $followerId, int $followedId): bool {
    return db_run('DELETE FROM user_follows WHERE follower_id = ? AND followed_id = ?', [$followerId, $followedId]) > 0;
}

function is_following(int $followerId, int $followedId): bool {
    return (bool) db_one('SELECT id FROM user_follows WHERE follower_id = ? AND followed_id = ?', [$followerId, $followedId]);
}

function get_follower_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM user_follows WHERE followed_id = ?', [$userId])['n'];
}

function get_following_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM user_follows WHERE follower_id = ?', [$userId])['n'];
}

/** A learner's finished courses — enrollments.progress is a 0-100 percentage, same convention as the learner dashboard. */
function get_courses_completed_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM enrollments WHERE user_id = ? AND progress >= 100', [$userId])['n'];
}

function get_courses_teaching(int $userId, int $limit = 20): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT c.id, c.title, c.slug, c.thumbnail_url,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS student_count
         FROM courses c
         WHERE c.creator_id = ? AND c.status = 'PUBLISHED'
         ORDER BY c.created_at DESC LIMIT $limit",
        [$userId]
    );
}

function get_user_post_count(int $userId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM community_posts WHERE author_id = ?', [$userId])['n'];
}

/** Communities a user belongs to, for the profile page's compact list — same query as get_user_communities() in community.php, kept here to avoid a cross-require. */
function get_profile_communities(int $userId, int $limit = 12): array {
    $limit = max(1, min(50, $limit));
    return db_all(
        "SELECT c.name, c.slug, c.type, c.banner_url
         FROM community_members cm JOIN communities c ON c.id = cm.community_id
         WHERE cm.user_id = ? ORDER BY cm.joined_at DESC LIMIT $limit",
        [$userId]
    );
}

/** Full member directory for one community — optionally filtered by name. Used by community/members.php; get_community_members() in community.php stays the capped "sidebar preview" version. */
function search_community_members(int $communityId, string $q = '', int $limit = 100): array {
    $limit = max(1, min(300, $limit));
    $where = 'cm.community_id = ?';
    $params = [$communityId];
    if ($q !== '') {
        $where .= ' AND u.name LIKE ?';
        $params[] = "%$q%";
    }
    return db_all(
        "SELECT cm.role, cm.joined_at, u.id, u.name, u.avatar_url, u.headline, u.role AS user_role
         FROM community_members cm JOIN users u ON u.id = cm.user_id
         WHERE $where ORDER BY cm.joined_at DESC LIMIT $limit",
        $params
    );
}
