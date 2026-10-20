<?php
/**
 * The campus class room (assets/js/class-room.js). No WebSocket server on this host, so the page polls.
 * GET  ?campus=slug&after=ID&since=ID   new messages after ID, and reactions/comments/deletes for ids >= since
 * GET  ?campus=slug&before=ID           older messages, for "Load earlier"
 * GET  ?campus=slug&thread=ID           a win and its comments
 * POST action=send|react|delete|sound|read (multipart for photos, otherwise JSON), with csrf_token.
 * Rules live in includes/class_room.php.
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/campuses.php';
require __DIR__ . '/../includes/class_room.php';

$user = api_require_login();
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$in = $isPost ? ($_POST ?: json_body()) : $_GET;
$campus = get_campus_by_slug((string) ($in['campus'] ?? ''));
if (!$campus) json_response(['error' => 'Campus not found.'], 404);
$access = class_room_access($user, $campus);
if (!$access['ok']) json_response(['error' => 'Join this campus to use its class room.'], 403);
$cid = (int) $campus['id'];
$out = fn(array $rows) => class_room_serialize($rows, $campus, $user, $access['staff']);

if ($isPost) {
    api_csrf_verify($in);
    try {
        switch ($in['action'] ?? '') {
            case 'send':
                $id = class_room_post($user, $campus, $in, $_FILES['photo'] ?? null);
                $row = db_one('SELECT p.*, u.name, u.role, u.avatar_url FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$id]);
                json_response(['ok' => true, 'message' => $out([$row])[0]]);
            case 'react':
                class_room_react($user, $campus, (int) ($in['id'] ?? 0), (string) ($in['emoji'] ?? ''));
                json_response(['ok' => true, 'state' => class_room_state([(int) $in['id']], (int) $user['id'])]);
            case 'delete':
                class_room_delete($user, $campus, (int) ($in['id'] ?? 0), $access['staff']);
                json_response(['ok' => true]);
            case 'sound':
                class_room_set_sound((int) $user['id'], !empty($in['on']) && $in['on'] !== '0' && $in['on'] !== 'false');
                json_response(['ok' => true]);
            case 'read':
                class_room_mark_read($cid, (int) $user['id'], (int) ($in['id'] ?? 0));
                json_response(['ok' => true]);
        }
    } catch (RuntimeException $e) {
        json_response(['error' => $e->getMessage()], 422);
    }
    json_response(['error' => 'Unknown action.'], 400);
}

if (!empty($in['thread'])) {
    $win = db_one('SELECT p.*, u.name, u.role, u.avatar_url FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.campus_id = ?', [(int) $in['thread'], $cid]);
    if (!$win || ($win['kind'] ?? '') !== 'WIN') json_response(['error' => 'That win is no longer here.'], 404);
    $comments = class_room_fetch($cid, (int) $win['id'], (int) ($in['after'] ?? 0), 0, 200);
    json_response(['win' => $out([$win])[0], 'messages' => $out($comments)]);
}

if (!empty($in['before'])) {
    $rows = class_room_fetch($cid, null, 0, (int) $in['before']);
    json_response(['messages' => $out($rows), 'more' => count($rows) >= CLASS_ROOM_PAGE]);
}

$after = (int) ($in['after'] ?? 0);
$rows = class_room_fetch($cid, null, $after, 0, 100);
$since = (int) ($in['since'] ?? 0);
$state = [];
if ($since) {
    $ids = array_map('intval', array_column(db_all('SELECT id FROM campus_posts WHERE campus_id = ? AND id >= ? AND id <= ? AND parent_id IS NULL', [$cid, $since, $after]), 'id'));
    $state = class_room_state(array_slice($ids, -120), (int) $user['id']);
}
$newRows = $out($rows);
if ($newRows && !empty($in['seen'])) class_room_mark_read($cid, (int) $user['id'], end($newRows)['id']);
json_response(['messages' => $newRows, 'state' => (object) $state]);
