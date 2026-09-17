<?php
/** Following a school — a soft commitment separate from buying anything,
 * the audience a creator can later reach with a WhatsApp broadcast or a
 * "new course" notification. See migration/add-school-follows.sql. */

function toggle_school_follow(int $learnerId, int $creatorId): bool {
    $existing = db_one('SELECT id FROM school_follows WHERE learner_id = ? AND creator_id = ?', [$learnerId, $creatorId]);
    if ($existing) {
        db_run('DELETE FROM school_follows WHERE id = ?', [$existing['id']]);
        return false;
    }
    db_insert('INSERT INTO school_follows (learner_id, creator_id) VALUES (?, ?)', [$learnerId, $creatorId]);
    return true;
}

function is_following_school(int $learnerId, int $creatorId): bool {
    return (bool) db_one('SELECT id FROM school_follows WHERE learner_id = ? AND creator_id = ?', [$learnerId, $creatorId]);
}

function get_school_follower_count(int $creatorId): int {
    return (int) db_one('SELECT COUNT(*) AS n FROM school_follows WHERE creator_id = ?', [$creatorId])['n'];
}

/** Every school a learner follows, newest-first, with the creator's name/school branding joined on — for a learner-facing "Following" list. */
function get_followed_schools_for_learner(int $learnerId): array {
    return db_all(
        "SELECT sf.id, sf.created_at, u.id AS creator_id, u.name AS creator_name, u.school_name, u.avatar_url AS creator_avatar_url,
                (SELECT COUNT(*) FROM courses c WHERE c.creator_id = u.id AND c.status = 'PUBLISHED') AS course_count
         FROM school_follows sf JOIN users u ON u.id = sf.creator_id
         WHERE sf.learner_id = ? ORDER BY sf.created_at DESC",
        [$learnerId]
    );
}

/** Every learner following $creatorId with an email on file — the recipient list for a broadcast composer. */
function get_school_followers(int $creatorId): array {
    return db_all(
        "SELECT u.id, u.name, u.email, u.phone
         FROM school_follows sf JOIN users u ON u.id = sf.learner_id
         WHERE sf.creator_id = ? ORDER BY sf.created_at DESC",
        [$creatorId]
    );
}
