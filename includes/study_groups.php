<?php
/**
 * Community module — Phase 4 (Study groups). See
 * C:\Users\hp\.claude\plans\iterative-scribbling-turing.md.
 *
 * v1 simplification: "private" only hides a group from the public
 * directory listing (get_study_groups()) — anyone who has the direct link
 * can still join. A real invite/request-to-join approval flow is deferred;
 * this matches the plan's "polling group chat, stored meet/zoom links,
 * nothing heavier" scope for study groups specifically.
 */

/** Base slug + incrementing suffix until unique — same convention as unique_community_slug(). */
function unique_study_group_slug(string $base): string {
    $base = slugify($base) ?: 'group';
    $slug = $base;
    $n = 1;
    while (db_one('SELECT id FROM study_groups WHERE slug = ?', [$slug])) {
        $slug = "$base-" . $n++;
    }
    return $slug;
}

function create_study_group(
    string $name,
    string $description,
    string $privacy,
    int $ownerId,
    ?string $meetLink,
    ?string $zoomLink,
    ?string $scheduleText
): int {
    if (!in_array($privacy, ['public', 'private'], true)) $privacy = 'public';

    $groupId = db_insert(
        'INSERT INTO study_groups (name, slug, description, privacy, owner_id, meet_link, zoom_link, schedule_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$name, unique_study_group_slug($name), $description ?: null, $privacy, $ownerId, $meetLink ?: null, $zoomLink ?: null, $scheduleText ?: null]
    );
    join_study_group($groupId, $ownerId, 'owner');
    return $groupId;
}

/** Idempotent, same convention as join_community(). */
function join_study_group(int $groupId, int $userId, string $role = 'member'): bool {
    $existing = db_one('SELECT id FROM study_group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
    if ($existing) return false;

    db_insert('INSERT INTO study_group_members (group_id, user_id, role) VALUES (?, ?, ?)', [$groupId, $userId, $role]);
    db_run('UPDATE study_groups SET member_count = member_count + 1 WHERE id = ?', [$groupId]);
    return true;
}

/** The owner can't leave their own group (no ownership-transfer flow in v1) — they'd have to delete it instead. */
function leave_study_group(int $groupId, int $userId): bool {
    $member = db_one('SELECT role FROM study_group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
    if (!$member || $member['role'] === 'owner') return false;

    $deleted = db_run('DELETE FROM study_group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
    if ($deleted > 0) db_run('UPDATE study_groups SET member_count = member_count - 1 WHERE id = ?', [$groupId]);
    return $deleted > 0;
}

function is_study_group_member(int $groupId, int $userId): bool {
    return (bool) db_one('SELECT id FROM study_group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
}

function get_study_group_by_slug(string $slug): ?array {
    return db_one('SELECT * FROM study_groups WHERE slug = ?', [$slug]);
}

function get_study_group_members(int $groupId, int $limit = 100): array {
    $limit = max(1, min(300, $limit));
    return db_all(
        "SELECT sgm.role, sgm.joined_at, u.id, u.name, u.avatar_url, u.headline
         FROM study_group_members sgm JOIN users u ON u.id = sgm.user_id
         WHERE sgm.group_id = ? ORDER BY sgm.role = 'owner' DESC, sgm.joined_at ASC LIMIT $limit",
        [$groupId]
    );
}

/** Public directory (search/browse) — private groups are excluded; they're reachable only via their direct link. */
function get_study_groups(string $q = '', int $limit = 50): array {
    $limit = max(1, min(200, $limit));
    $where = "privacy = 'public'";
    $params = [];
    if ($q !== '') {
        $where .= ' AND name LIKE ?';
        $params[] = "%$q%";
    }
    return db_all("SELECT * FROM study_groups WHERE $where ORDER BY member_count DESC, created_at DESC LIMIT $limit", $params);
}

function get_user_study_groups(int $userId): array {
    return db_all(
        "SELECT sg.*, sgm.role FROM study_group_members sgm
         JOIN study_groups sg ON sg.id = sgm.group_id
         WHERE sgm.user_id = ? ORDER BY sgm.joined_at DESC",
        [$userId]
    );
}

function delete_study_group(int $groupId, int $actingUserId): bool {
    $group = db_one('SELECT owner_id FROM study_groups WHERE id = ?', [$groupId]);
    if (!$group || (int) $group['owner_id'] !== $actingUserId) return false;
    db_run('DELETE FROM study_groups WHERE id = ?', [$groupId]);
    return true;
}

function create_study_group_message(int $groupId, int $authorId, string $body): int {
    return db_insert('INSERT INTO study_group_messages (group_id, author_id, body) VALUES (?, ?, ?)', [$groupId, $authorId, $body]);
}

/** Oldest-first chat log for a group. Pass $afterId (the highest id the client already has) for a polling delta instead of the full history. */
function get_study_group_messages(int $groupId, ?int $afterId = null, int $limit = 100): array {
    $limit = max(1, min(200, $limit));
    if ($afterId !== null) {
        return db_all(
            "SELECT m.*, u.name AS author_name, u.avatar_url AS author_avatar_url
             FROM study_group_messages m JOIN users u ON u.id = m.author_id
             WHERE m.group_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT $limit",
            [$groupId, $afterId]
        );
    }
    // Most-recent $limit messages, returned oldest-first for top-to-bottom chat rendering.
    $rows = db_all(
        "SELECT m.*, u.name AS author_name, u.avatar_url AS author_avatar_url
         FROM study_group_messages m JOIN users u ON u.id = m.author_id
         WHERE m.group_id = ? ORDER BY m.id DESC LIMIT $limit",
        [$groupId]
    );
    return array_reverse($rows);
}

/** One chat bubble — used both for the server-rendered initial log and (mirrored in JS) for messages appended by polling. */
function render_chat_message(array $m, int $myUserId): void {
    $isMine = (int) $m['author_id'] === $myUserId;
    ?>
    <div class="chat-message<?= $isMine ? ' mine' : '' ?>" data-message-id="<?= (int) $m['id'] ?>">
      <?php if (!$isMine): ?>
        <div class="avatar-circle" style="width:28px; height:28px; font-size:11px; flex-shrink:0;">
          <?php if (!empty($m['author_avatar_url'])): ?><img src="<?= e(asset_src($m['author_avatar_url'])) ?>" alt="">
          <?php else: ?><?= e(mb_substr($m['author_name'], 0, 1)) ?><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="chat-bubble">
        <?php if (!$isMine): ?><div class="chat-author"><?= e($m['author_name']) ?></div><?php endif; ?>
        <div class="chat-text"><?= nl2br(e($m['body'])) ?></div>
        <div class="chat-time"><?= time_ago($m['created_at']) ?></div>
      </div>
    </div>
    <?php
}
