<?php
/**
 * The campus class room: a WhatsApp-style group chat per campus (public/campus-learn.php?view=community).
 * Messages are campus_posts rows: TEXT, STICKER, IMAGE or WIN (a win has a title, optional photo and
 * its own comment thread: posts whose parent_id is the win). reply_to quotes another message. One
 * reaction per person per message (campus_post_reactions). campus_chat_reads keeps the last message
 * each person has seen, for unread counts and alerts. Everyone in the campus is notified (one bell row
 * per campus that updates, plus a personal one for replies and win comments), and the alert sound can
 * be switched off in Settings (users.chat_sound).
 * Needs migration/add-class-room.sql; until it runs, class_room_ready() is false and the room shows
 * plain text messages only. Requires includes/campuses.php. API: public/api/class-room.php,
 * public/api/chat-alerts.php. Client: assets/js/class-room.js, assets/js/chat-alerts.js.
 */
require_once __DIR__ . '/user_notifications.php';
require_once __DIR__ . '/storage.php';

/** The Obin sticker pack, drawn in CSS (class-room.css): key => [shape and colour, emoji, words, tilt]. */
const CLASS_ROOM_STICKERS = [
    'lets-go' => ['badge lime', '🚀', "LET'S GO!", '-8deg'],
    'big-w' => ['tag navy', '🏆', 'BIG W', '5deg'],
    'thank-prof' => ['tag blue', '🙏', 'THANK YOU PROF', '-4deg'],
    'fire' => ['badge red', '🔥', 'ON FIRE', '6deg'],
    'mind-blown' => ['badge gold', '🤯', 'MIND = BLOWN', '-6deg'],
    'done' => ['tag lime', '✅', 'LESSON DONE', '4deg'],
    'grind' => ['badge navy', '💪', 'GRIND MODE', '-5deg'],
    'class-time' => ['tag gold', '⏰', 'CLASS TIME', '7deg'],
    'lol' => ['badge pink', '😂', "I CAN'T", '-7deg'],
    'hundred' => ['badge blue', '💯', '100%', '5deg'],
    'salute' => ['tag red', '🫡', 'RESPECT', '-3deg'],
    'money' => ['badge lime', '💸', 'FIRST PAY', '8deg'],
];
const CLASS_ROOM_MAX_TEXT = 2000;
const CLASS_ROOM_PAGE = 50;          // messages loaded at a time
const CLASS_ROOM_RATE = 20;          // messages a person can send per minute

function class_room_ready(): bool {
    static $ready = null;
    if ($ready === null) {
        try { db_one('SELECT kind, parent_id FROM campus_posts LIMIT 1'); db_one('SELECT 1 FROM campus_post_reactions LIMIT 1'); $ready = true; }
        catch (Throwable $e) { $ready = false; }
    }
    return $ready;
}

/** Can this person read and write in this campus's class room? Members with open access, its professor, admins. */
function class_room_access(array $user, array $campus): array {
    $isProf = campus_is_professor($user, $campus);
    $staff = $user['role'] === 'ADMIN' || $isProf;
    if ($staff) return ['ok' => true, 'staff' => true, 'prof' => $isProf];
    $m = get_campus_membership((int) $campus['id'], (int) $user['id']);
    return ['ok' => $campus['status'] === 'PUBLISHED' && campus_membership_open($m), 'staff' => false, 'prof' => false];
}

/** Who a message is from, as shown: [name, tag, staff]. The linked professor posts as themselves; admin posts show as the professor until one is linked, then as the Obin Academy team. */
function class_room_poster(array $campus, array $p): array {
    $profName = trim((string) $campus['professor_name']) ?: 'Obin Academy';
    if (!empty($campus['professor_user_id']) && (int) $p['user_id'] === (int) $campus['professor_user_id']) return [$profName, 'Professor', true];
    if (($p['role'] ?? '') === 'ADMIN') return empty($campus['professor_user_id']) ? [$profName, 'Team', true] : ['Obin Academy', 'Team', true];
    return [$p['name'], '', false];
}

/** A one-line preview of a message, for quotes, notifications and the campus home. */
function class_room_preview(array $p): string {
    if (!empty($p['deleted_at'])) return 'This message was deleted';
    switch ($p['kind'] ?? 'TEXT') {
        case 'STICKER': return 'Sticker' . (isset(CLASS_ROOM_STICKERS[$p['sticker']]) ? ' ' . CLASS_ROOM_STICKERS[$p['sticker']][1] : '');
        case 'IMAGE': return '📷 ' . (trim((string) $p['body']) !== '' ? mb_strimwidth(trim($p['body']), 0, 120, '…') : 'Photo');
        case 'WIN': return '🏆 ' . ($p['title'] ?: 'A win');
    }
    return mb_strimwidth(trim((string) $p['body']), 0, 140, '…');
}

/**
 * Messages from one room: the main room ($parent null) or one win's comments ($parent = win id).
 * $after: only newer than this id; $before: only older (for "Load earlier"). Oldest first.
 */
function class_room_fetch(int $campusId, ?int $parent = null, int $after = 0, int $before = 0, int $limit = CLASS_ROOM_PAGE): array {
    $sql = 'SELECT p.*, u.name, u.role, u.avatar_url FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.campus_id = ?';
    $args = [$campusId];
    if (class_room_ready()) { $sql .= $parent ? ' AND p.parent_id = ?' : ' AND p.parent_id IS NULL'; if ($parent) $args[] = $parent; }
    if ($after) { $sql .= ' AND p.id > ?'; $args[] = $after; }
    if ($before) { $sql .= ' AND p.id < ?'; $args[] = $before; }
    $rows = db_all($sql . ' ORDER BY p.id DESC LIMIT ' . max(1, min(200, $limit)), $args);
    return array_reverse($rows);
}

/** Reactions, comment counts and deleted state for a set of message ids: id => [reactions, comments, deleted]. */
function class_room_state(array $ids, int $viewerId): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $out = [];
    foreach ($ids as $id) $out[$id] = ['r' => [], 'c' => 0, 'x' => false];
    if (!$ids || !class_room_ready()) return $out;
    $in = implode(',', $ids);
    foreach (db_all("SELECT post_id, emoji, COUNT(*) AS n, MAX(user_id = ?) AS mine FROM campus_post_reactions WHERE post_id IN ($in) GROUP BY post_id, emoji ORDER BY MIN(created_at)", [$viewerId]) as $r) {
        $out[(int) $r['post_id']]['r'][] = ['e' => $r['emoji'], 'n' => (int) $r['n'], 'm' => (bool) $r['mine']];
    }
    foreach (db_all("SELECT parent_id, COUNT(*) AS n FROM campus_posts WHERE parent_id IN ($in) AND deleted_at IS NULL GROUP BY parent_id") as $r) $out[(int) $r['parent_id']]['c'] = (int) $r['n'];
    foreach (db_all("SELECT id FROM campus_posts WHERE id IN ($in) AND deleted_at IS NOT NULL") as $r) $out[(int) $r['id']]['x'] = true;
    return $out;
}

/** Messages as the class room script needs them. */
function class_room_serialize(array $rows, array $campus, array $viewer, bool $staff): array {
    if (!$rows) return [];
    $state = class_room_state(array_column($rows, 'id'), (int) $viewer['id']);
    $quoteIds = array_filter(array_map(fn($r) => (int) ($r['reply_to'] ?? 0), $rows));
    $quotes = [];
    if ($quoteIds) {
        foreach (db_all('SELECT p.*, u.name, u.role FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.id IN (' . implode(',', array_unique($quoteIds)) . ')') as $q) $quotes[(int) $q['id']] = $q;
    }
    $colors = ['#0f766e', '#be185d', '#b45309', '#6d28d9', '#15803d', '#0369a1', '#c2410c', '#4338ca'];
    $out = [];
    foreach ($rows as $p) {
        [$who, $tag, $isStaff] = class_room_poster($campus, $p);
        $id = (int) $p['id']; $deleted = !empty($p['deleted_at']) || $state[$id]['x'];
        $mine = (int) $p['user_id'] === (int) $viewer['id'];
        $q = null;
        if (!empty($p['reply_to']) && isset($quotes[(int) $p['reply_to']])) {
            $qp = $quotes[(int) $p['reply_to']];
            $q = ['id' => (int) $qp['id'], 'who' => (int) $qp['user_id'] === (int) $viewer['id'] ? 'You' : class_room_poster($campus, $qp)[0], 'text' => class_room_preview($qp)];
        }
        $t = strtotime($p['created_at']);
        $out[] = [
            'id' => $id, 'uid' => (int) $p['user_id'], 'who' => $who, 'tag' => $tag, 'staff' => $isStaff,
            'color' => $isStaff ? '#3b33ff' : $colors[abs(crc32((string) $p['user_id'])) % count($colors)],
            'avatar' => !$isStaff && !empty($p['avatar_url']) ? asset_src($p['avatar_url']) : null,
            'kind' => $deleted ? 'DELETED' : ($p['kind'] ?? 'TEXT'),
            'text' => $deleted ? '' : (string) $p['body'],
            'sticker' => $deleted ? null : ($p['sticker'] ?? null),
            'image' => !$deleted && !empty($p['image_url']) ? asset_src($p['image_url']) : null,
            'title' => $deleted ? null : ($p['title'] ?? null),
            'reply' => $deleted ? null : $q,
            'r' => $deleted ? [] : $state[$id]['r'], 'c' => $state[$id]['c'],
            'day' => date('Y-m-d', $t), 'time' => date('g:i A', $t),
            'mine' => $mine, 'del' => !$deleted && ($mine || $staff),
        ];
    }
    return $out;
}

/** Saves a chat photo (from $_FILES), shrunk to at most 1600px on the long side. Returns its web path. */
function class_room_save_photo(array $file): string {
    $path = save_upload($file, 'chat');
    $abs = public_uploads_root() . substr($path, strlen('/uploads'));
    if (function_exists('imagecreatefromstring') && ($info = @getimagesize($abs)) && max($info[0], $info[1]) > 1600 && $info['mime'] !== 'image/gif') {
        $img = @imagecreatefromstring((string) file_get_contents($abs));
        if ($img) {
            $k = 1600 / max($info[0], $info[1]);
            $small = imagescale($img, (int) round($info[0] * $k), (int) round($info[1] * $k));
            if ($small) {
                if ($info['mime'] === 'image/png') imagepng($small, $abs, 7);
                elseif ($info['mime'] === 'image/webp' && function_exists('imagewebp')) imagewebp($small, $abs, 82);
                else imagejpeg($small, $abs, 82);
            }
        }
    }
    return $path;
}

/**
 * Posts a message. $d: kind (TEXT, STICKER, IMAGE, WIN), text, sticker, title, reply_to, parent_id
 * (a win, for its comments); a photo comes from $file. Returns the new id.
 * @throws RuntimeException with a message to show.
 */
function class_room_post(array $user, array $campus, array $d, ?array $file = null): int {
    $cid = (int) $campus['id'];
    if (!class_room_ready()) {
        $text = trim(mb_substr((string) ($d['text'] ?? ''), 0, CLASS_ROOM_MAX_TEXT));
        if ($text === '') throw new RuntimeException('Write a message first.');
        return (int) db_insert('INSERT INTO campus_posts (campus_id, user_id, body) VALUES (?, ?, ?)', [$cid, $user['id'], $text]);
    }
    $recent = (int) db_one('SELECT COUNT(*) AS n FROM campus_posts WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)', [$user['id']])['n'];
    if ($recent >= CLASS_ROOM_RATE) throw new RuntimeException('You are sending messages very fast. Wait a moment and try again.');

    $kind = in_array($d['kind'] ?? 'TEXT', ['TEXT', 'STICKER', 'IMAGE', 'WIN'], true) ? $d['kind'] : 'TEXT';
    $text = trim(mb_substr(str_replace("\r", '', (string) ($d['text'] ?? '')), 0, CLASS_ROOM_MAX_TEXT));
    $sticker = null; $title = null; $image = null;
    $parent = (int) ($d['parent_id'] ?? 0) ?: null;
    $reply = (int) ($d['reply_to'] ?? 0) ?: null;

    if ($parent) {
        $win = db_one("SELECT id FROM campus_posts WHERE id = ? AND campus_id = ? AND kind = 'WIN' AND deleted_at IS NULL", [$parent, $cid]);
        if (!$win) throw new RuntimeException('That win is no longer here.');
        if ($kind === 'WIN') $kind = 'TEXT';
    }
    if ($reply && !db_one('SELECT id FROM campus_posts WHERE id = ? AND campus_id = ?', [$reply, $cid])) $reply = null;

    if ($kind === 'STICKER') {
        $sticker = (string) ($d['sticker'] ?? '');
        if (!isset(CLASS_ROOM_STICKERS[$sticker])) throw new RuntimeException('That sticker is not available.');
        $text = '';
    } elseif ($kind === 'WIN') {
        $title = trim(mb_substr((string) ($d['title'] ?? ''), 0, 120));
        if ($title === '') throw new RuntimeException('Say what you achieved.');
        $text = mb_substr($text, 0, 500);
    }
    if ($kind === 'IMAGE' || ($kind === 'WIN' && $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a photo to send.');
        $image = class_room_save_photo($file);
    }
    if ($kind === 'TEXT' && $text === '') throw new RuntimeException('Write a message first.');

    $id = (int) db_insert(
        'INSERT INTO campus_posts (campus_id, user_id, kind, reply_to, parent_id, body, sticker, image_url, title) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$cid, $user['id'], $kind, $reply, $parent, $text, $sticker, $image, $title]
    );
    if (!$parent) class_room_mark_read($cid, (int) $user['id'], $id);
    try { class_room_notify($campus, $user, $id); } catch (Throwable $e) {}
    return $id;
}

/** True for a single emoji (with skin tone, joiners or keycap): every character is an emoji-range code point. */
function class_room_is_emoji(string $e): bool {
    if ($e === '' || strlen($e) > 32 || !mb_check_encoding($e, 'UTF-8')) return false;
    $chars = mb_str_split($e);
    if (count($chars) > 8) return false;
    $hasEmoji = false;
    foreach ($chars as $ch) {
        $cp = mb_ord($ch);
        if ($cp === 0x200D || $cp === 0xFE0F || $cp === 0x20E3) continue;          // joiner, emoji style, keycap
        if ($cp >= 0x1F000 || ($cp >= 0x2190 && $cp <= 0x2BFF) || $cp === 0xA9 || $cp === 0xAE || ($cp >= 0x3030 && $cp <= 0x303D)) { $hasEmoji = true; continue; }
        if (strpos('#*0123456789', $ch) !== false) continue;                   // keycap digits
        return false;
    }
    return $hasEmoji || in_array(0x20E3, array_map('mb_ord', $chars), true);
}

/** One reaction per person per message: the same emoji again removes it, another replaces it. */
function class_room_react(array $user, array $campus, int $postId, string $emoji): void {
    $emoji = trim($emoji);
    if (!class_room_is_emoji($emoji)) throw new RuntimeException('Pick an emoji.');
    if (!db_one('SELECT id FROM campus_posts WHERE id = ? AND campus_id = ? AND deleted_at IS NULL', [$postId, (int) $campus['id']])) throw new RuntimeException('That message is no longer here.');
    $cur = db_one('SELECT emoji FROM campus_post_reactions WHERE post_id = ? AND user_id = ?', [$postId, $user['id']]);
    if ($cur && $cur['emoji'] === $emoji) db_run('DELETE FROM campus_post_reactions WHERE post_id = ? AND user_id = ?', [$postId, $user['id']]);
    else db_run('INSERT INTO campus_post_reactions (post_id, user_id, emoji) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = NOW()', [$postId, $user['id'], $emoji]);
}

/** Deletes a message: its author, the campus's professor or an admin. Shows as "This message was deleted". */
function class_room_delete(array $user, array $campus, int $postId, bool $staff): void {
    $p = db_one('SELECT user_id FROM campus_posts WHERE id = ? AND campus_id = ?', [$postId, (int) $campus['id']]);
    if (!$p) return;
    if (!$staff && (int) $p['user_id'] !== (int) $user['id']) throw new RuntimeException('You can only delete your own messages.');
    db_run('UPDATE campus_posts SET deleted_at = NOW() WHERE id = ?', [$postId]);
    if (class_room_ready()) db_run('DELETE FROM campus_post_reactions WHERE post_id = ?', [$postId]);
}

function class_room_mark_read(int $campusId, int $userId, int $lastId): void {
    if (!class_room_ready() || $lastId <= 0) return;
    db_run('INSERT INTO campus_chat_reads (campus_id, user_id, last_read_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, VALUES(last_read_id))', [$campusId, $userId, $lastId]);
}

/** Everyone who can be in a campus's class room: members with open access and the professor. */
function class_room_people(array $campus): array {
    $ids = array_map('intval', array_column(db_all('SELECT user_id FROM campus_memberships WHERE campus_id = ? AND access_until > NOW()', [(int) $campus['id']]), 'user_id'));
    if (!empty($campus['professor_user_id'])) $ids[] = (int) $campus['professor_user_id'];
    return array_values(array_unique($ids));
}

/**
 * Tells the class about a new message. The person replied to (or whose win got a comment) gets their
 * own notification; everyone else gets one bell row per campus that updates with the latest message
 * instead of piling up.
 */
function class_room_notify(array $campus, array $author, int $postId): void {
    $p = db_one('SELECT p.*, u.name, u.role FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$postId]);
    if (!$p) return;
    $who = class_room_poster($campus, $p)[0];
    $room = $campus['name'] . ' class room';
    $link = base_url('campus-learn.php?campus=' . $campus['slug'] . '&view=community' . ($p['parent_id'] ? '&win=' . (int) $p['parent_id'] : ''));
    $preview = class_room_preview($p);
    $people = array_values(array_diff(class_room_people($campus), [(int) $author['id']]));
    $personal = [];
    if ($p['reply_to']) {
        $to = (int) (db_one('SELECT user_id FROM campus_posts WHERE id = ?', [(int) $p['reply_to']])['user_id'] ?? 0);
        if ($to && $to !== (int) $author['id']) { create_user_notification($to, (int) $author['id'], 'campus_reply', mb_strimwidth($who . ' replied to you in the ' . $room . ': ' . $preview, 0, 480, '…'), $link); $personal[] = $to; }
    }
    if ($p['parent_id']) {
        $to = (int) (db_one('SELECT user_id FROM campus_posts WHERE id = ?', [(int) $p['parent_id']])['user_id'] ?? 0);
        if ($to && $to !== (int) $author['id'] && !in_array($to, $personal, true)) { create_user_notification($to, (int) $author['id'], 'campus_comment', mb_strimwidth($who . ' commented on your win: ' . $preview, 0, 480, '…'), $link); $personal[] = $to; }
    }
    if ($p['parent_id']) return; // win comments only notify the win's owner
    $everyone = array_values(array_diff($people, $personal));
    if (!$everyone) return;
    $roomLink = base_url('campus-learn.php?campus=' . $campus['slug'] . '&view=community');
    $msg = mb_strimwidth(($p['kind'] === 'WIN' ? '🏆 ' . $who . ' shared a win in the ' . $room . ': ' . ($p['title'] ?: '') : 'New in the ' . $room . ' · ' . $who . ': ' . $preview), 0, 480, '…');
    foreach (array_chunk($everyone, 200) as $chunk) {
        $in = implode(',', $chunk);
        db_run("UPDATE user_notifications SET message = ?, actor_user_id = ?, created_at = NOW() WHERE type = 'campus_chat' AND is_read = 0 AND link_url = ? AND recipient_user_id IN ($in)", [$msg, (int) $author['id'], $roomLink]);
        $have = array_map('intval', array_column(db_all("SELECT recipient_user_id FROM user_notifications WHERE type = 'campus_chat' AND is_read = 0 AND link_url = ? AND recipient_user_id IN ($in)", [$roomLink]), 'recipient_user_id'));
        foreach (array_diff($chunk, $have) as $uid) create_user_notification((int) $uid, (int) $author['id'], 'campus_chat', $msg, $roomLink);
    }
}

/** Campuses whose class room this person is in: open memberships, plus the campus they teach. */
function class_room_campuses_for(array $user): array {
    if (!campuses_ready()) return [];
    $rows = db_all("SELECT c.* FROM campuses c JOIN campus_memberships m ON m.campus_id = c.id WHERE m.user_id = ? AND m.access_until > NOW() AND c.status = 'PUBLISHED'", [$user['id']]);
    foreach (db_all('SELECT * FROM campuses WHERE professor_user_id = ?', [$user['id']]) as $c) $rows[] = $c;
    $seen = []; $out = [];
    foreach ($rows as $c) { if (!isset($seen[(int) $c['id']])) { $seen[(int) $c['id']] = true; $out[] = $c; } }
    return $out;
}

/** For the alert poller: unread counts per campus and the newest message from someone else. */
function class_room_alerts(array $user): array {
    $out = ['unread' => 0, 'rooms' => [], 'latest' => null, 'sound' => class_room_sound_on($user)];
    if (!class_room_ready()) return $out;
    foreach (class_room_campuses_for($user) as $c) {
        $cid = (int) $c['id'];
        $read = (int) (db_one('SELECT last_read_id FROM campus_chat_reads WHERE campus_id = ? AND user_id = ?', [$cid, $user['id']])['last_read_id'] ?? 0);
        $n = (int) db_one('SELECT COUNT(*) AS n FROM campus_posts WHERE campus_id = ? AND parent_id IS NULL AND deleted_at IS NULL AND user_id <> ? AND id > ?', [$cid, $user['id'], $read])['n'];
        $out['rooms'][$c['slug']] = $n; $out['unread'] += $n;
        $last = db_one('SELECT p.*, u.name, u.role FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.campus_id = ? AND p.deleted_at IS NULL AND p.user_id <> ? AND p.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY p.id DESC LIMIT 1', [$cid, $user['id']]);
        if ($last && (!$out['latest'] || (int) $last['id'] > $out['latest']['id'])) {
            $out['latest'] = [
                'id' => (int) $last['id'], 'campus' => $c['name'], 'slug' => $c['slug'],
                'who' => class_room_poster($c, $last)[0], 'text' => class_room_preview($last),
                'url' => base_url('campus-learn.php?campus=' . $c['slug'] . '&view=community' . ($last['parent_id'] ? '&win=' . (int) $last['parent_id'] : '')),
            ];
        }
    }
    return $out;
}

function class_room_sound_on(array $user): bool {
    return !array_key_exists('chat_sound', $user) || (int) $user['chat_sound'] === 1;
}

function class_room_set_sound(int $userId, bool $on): void {
    try { db_run('UPDATE users SET chat_sound = ? WHERE id = ?', [$on ? 1 : 0, $userId]); } catch (Throwable $e) {}
}
