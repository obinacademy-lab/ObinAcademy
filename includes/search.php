<?php
/**
 * Community module — Phase 5 (basic search). Plain LIKE queries — the plan
 * explicitly scopes v1 to "basic search across posts/communities/members",
 * not full-text ranking or a dedicated search index.
 */

/** Posts matching $q across every community (unlike get_posts_by_hashtag(), which is scoped to one community's #tag). */
function search_posts(string $q, int $limit = 20): array {
    $limit = max(1, min(100, $limit));
    return db_all(
        "SELECT p.id, p.body, p.created_at, p.community_id,
                u.name AS author_name, u.avatar_url AS author_avatar_url,
                c.name AS community_name, c.slug AS community_slug
         FROM community_posts p
         JOIN users u ON u.id = p.author_id
         JOIN communities c ON c.id = p.community_id
         WHERE p.body LIKE ?
         ORDER BY p.created_at DESC LIMIT $limit",
        ["%$q%"]
    );
}

/** Users matching $q by name or headline — a platform-wide "find a member" search, not scoped to one community (search_community_members() in profiles.php stays the per-community directory version). */
function search_users(string $q, int $limit = 20): array {
    $limit = max(1, min(100, $limit));
    return db_all(
        "SELECT id, name, avatar_url, headline, role
         FROM users
         WHERE name LIKE ? OR headline LIKE ?
         ORDER BY name ASC LIMIT $limit",
        ["%$q%", "%$q%"]
    );
}
