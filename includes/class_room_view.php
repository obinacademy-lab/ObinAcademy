<?php
/**
 * The class room view inside public/campus-learn.php (?view=community). Included from that page, so it
 * uses its variables ($campus, $user, $isAdmin, $isProf, $self, $svg, $photo). Prints the chat shell
 * and #cr-config for assets/js/class-room.js; styles in assets/css/class-room.css.
 */
$crId = (int) $campus['id'];
$crRows = class_room_fetch($crId);
$crMsgs = class_room_serialize($crRows, $campus, $user, $isAdmin);
if ($crMsgs) class_room_mark_read($crId, (int) $user['id'], end($crMsgs)['id']);
$crPeople = count(class_room_people($campus));
$crReady = class_room_ready();
$crWinsMonth = $crReady ? (int) db_one("SELECT COUNT(*) AS n FROM campus_posts WHERE campus_id = ? AND kind = 'WIN' AND deleted_at IS NULL AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$crId])['n'] : 0;
$crMyWins = $crReady ? (int) db_one("SELECT COUNT(*) AS n FROM campus_posts WHERE campus_id = ? AND kind = 'WIN' AND deleted_at IS NULL AND user_id = ?", [$crId, $user['id']])['n'] : 0;
$crWeek = (int) db_one('SELECT COUNT(*) AS n FROM campus_posts WHERE campus_id = ? AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [$crId])['n'];
$crTop = $crReady ? db_all(
    "SELECT p.*, u.name, u.role, (SELECT COUNT(*) FROM campus_post_reactions r WHERE r.post_id = p.id) AS cheers
     FROM campus_posts p JOIN users u ON u.id = p.user_id
     WHERE p.campus_id = ? AND p.kind = 'WIN' AND p.deleted_at IS NULL AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY cheers DESC, p.id DESC LIMIT 3", [$crId]) : [];
$crPin = db_one(
    'SELECT p.*, u.name, u.role FROM campus_posts p JOIN users u ON u.id = p.user_id WHERE p.campus_id = ? AND p.deleted_at IS NULL'
    . ($crReady ? " AND p.parent_id IS NULL AND p.kind = 'TEXT'" : '') . ' AND (p.user_id = ? OR u.role = \'ADMIN\') AND p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) ORDER BY p.id DESC LIMIT 1',
    [$crId, (int) ($campus['professor_user_id'] ?? 0)]
);
$crProf = trim((string) $campus['professor_name']);
$crBell = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>';
$crBellOff = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.7 21a2 2 0 0 1-3.4 0M18.6 13.5c.3 2.2 1.4 3.5 2.4 3.5H7M6.3 6.3A6 6 0 0 0 6 8c0 7-3 9-3 9M9.7 2.6A6 6 0 0 1 18 8"/><path d="m2 2 20 20"/></svg>';
$crSound = class_room_sound_on($user);
$crConfig = [
    'api' => base_url('api/class-room.php'), 'campus' => $campus['slug'], 'csrf' => csrf_token(),
    'me' => (int) $user['id'], 'staff' => $isAdmin, 'sound' => $crSound,
    'today' => date('Y-m-d'), 'yesterday' => date('Y-m-d', strtotime('-1 day')),
    'stickers' => CLASS_ROOM_STICKERS, 'messages' => $crMsgs, 'more' => count($crRows) >= CLASS_ROOM_PAGE,
    'openWin' => (int) query_param('win') ?: null, 'icons' => ['bell' => $crBell, 'bellOff' => $crBellOff],
];
$crX = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
$crSend = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>';
?>
<main class="cl-page cl-chatpage">
<div class="cr" data-cr>
  <section class="cr-chat" aria-label="<?= e($campus['name']) ?> class room">
    <div class="cr-h">
      <a class="cr-back" href="<?= e($self()) ?>" aria-label="Back to my campus"><?= $svg('back') ?></a>
      <span class="cr-av"><?php if ($photo): ?><img src="<?= e($photo) ?>" alt=""><?php else: ?><?= campus_icon($campus['icon'] ?: 'book', 1.8) ?><?php endif; ?></span>
      <span class="cr-t"><b><?= e($campus['name']) ?> · Class room</b><small><?= $crProf !== '' ? 'Prof. ' . e($crProf) . ' and ' : '' ?><?= max(0, $crPeople - ($crProf !== '' ? 1 : 0)) ?> member<?= $crPeople === 1 ? '' : 's' ?></small></span>
      <?php if ($crReady): ?><span class="cr-tabs" role="group" aria-label="Show"><span class="knob"></span><button type="button" aria-pressed="true" data-f="all">All</button><button type="button" aria-pressed="false" data-f="win">🏆 Wins</button><button type="button" aria-pressed="false" data-f="prof">Professor</button></span><?php endif; ?>
      <button type="button" class="cr-bell" data-sound aria-label="Notification sound"><?= $crSound ? $crBell : $crBellOff ?></button>
    </div>
    <?php if ($crPin): [$pinWho] = class_room_poster($campus, $crPin); ?>
      <button type="button" class="cr-pin" data-jump="<?= (int) $crPin['id'] ?>"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 3l5 5-3 1-4 4 1 5-2 2-4-4-5 5-1-1 5-5-4-4 2-2 5 1 4-4z"/></svg><span><b><?= e($pinWho) ?>:</b> <?= e(class_room_preview($crPin)) ?></span></button>
    <?php endif; ?>
    <div class="cr-msgs" aria-live="polite"></div>
    <div class="cr-comp">
      <p class="cr-err" id="cr-err" role="alert" hidden></p>
      <div class="cr-reply" id="cr-reply"><div><b id="cr-reply-who"></b><span id="cr-reply-tx"></span></div><button type="button" id="cr-reply-x" aria-label="Cancel reply"><?= $crX ?></button></div>
      <div class="cr-row">
        <button type="button" class="cr-ib" id="cr-emoji" aria-label="Emoji and stickers" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.3 2 3.5 2 3.5-2 3.5-2"/><circle cx="9" cy="10" r=".9" fill="currentColor"/><circle cx="15" cy="10" r=".9" fill="currentColor"/></svg></button>
        <?php if ($crReady): ?><button type="button" class="cr-ib win-b" data-share-win aria-label="Share a win"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/></svg></button><?php endif; ?>
        <div class="cr-field"><textarea id="cr-input" rows="1" maxlength="<?= CLASS_ROOM_MAX_TEXT ?>" placeholder="Message the class" aria-label="Message"></textarea><?php if ($crReady): ?><button type="button" class="cr-ib" id="cr-photo" aria-label="Send a photo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="12" cy="12" r="3.2"/><path d="M8 5l1.5-2h5L16 5"/></svg></button><?php endif; ?></div>
        <input type="file" id="cr-photo-file" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
        <button type="button" class="cr-send" id="cr-send" aria-label="Send"><?= $crSend ?></button>
      </div>
      <div class="cr-drawer" id="cr-drawer"><div>
        <div class="cr-dtabs"><button type="button" aria-pressed="true" data-d="emoji">😀 Emoji</button><?php if ($crReady): ?><button type="button" aria-pressed="false" data-d="stk">Stickers</button><?php endif; ?></div>
        <div id="cr-d-emoji"><div class="cr-dcats" id="cr-dcats"></div><div class="cr-egrid" id="cr-egrid"></div></div>
        <div id="cr-d-stk" hidden><div class="cr-sgrid" id="cr-sgrid"></div></div>
      </div></div>
    </div>
  </section>

  <aside class="cr-side">
    <div class="cr-dark">
      <span class="cr-eb">Class room</span>
      <h3>Learn together. <em>Win together.</em></h3>
      <div class="cr-mem"><i style="background:#3b33ff"><?= e(mb_strtoupper(mb_substr($crProf ?: 'O', 0, 1))) ?></i><i style="background:#0f766e">·</i><i style="background:#be185d">·</i><span><?= $crPeople ?> in this class room</span></div>
      <div class="cr-stat3"><div><b><?= $crWinsMonth ?></b><small>wins this month</small></div><div><b><?= $crWeek ?></b><small>messages this week</small></div><div><b><?= $crMyWins ?></b><small>your wins</small></div></div>
      <?php if ($crReady): ?><button type="button" class="cr-btn lime full" data-share-win>🏆 Share a win</button><?php endif; ?>
    </div>
    <?php if ($crTop): ?>
      <div class="cr-white"><h4>Top <em>wins</em> this week</h4>
        <?php foreach ($crTop as $w): [$ww] = class_room_poster($campus, $w); ?>
          <button type="button" class="cr-tw" data-open-win="<?= (int) $w['id'] ?>"><span class="im"><?php if ($w['image_url']): ?><img src="<?= e(asset_src($w['image_url'])) ?>" alt=""><?php else: ?>🏆<?php endif; ?></span><span><b><?= e($w['title']) ?></b><small><?= e($ww) ?></small></span><span class="n">🔥 <?= (int) $w['cheers'] ?></span></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="cr-white">
      <div class="cr-snd"><span><b style="display:block;color:var(--cr-ink)">Notification sound</b>A short sound when the class posts</span><button type="button" class="cr-switch" role="switch" aria-checked="<?= $crSound ? 'true' : 'false' ?>" aria-label="Notification sound" data-sound></button></div>
      <p class="cr-rules">Be kind, help classmates and keep it about the campus. Your professor can remove messages.</p>
    </div>
  </aside>

  <div class="cr-scrim"></div>
  <div class="cr-modal" role="dialog" aria-modal="true" aria-labelledby="cr-win-h">
    <div class="cr-mh"><div><h3 id="cr-win-h">Share a <em>win</em> 🏆</h3><p>Big or small, it counts. Your class will cheer you on.</p></div><button type="button" class="cr-x" data-close aria-label="Close"><?= $crX ?></button></div>
    <div class="cr-mb">
      <label class="cr-drop"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="10" r="1.6"/><path d="m21 16-5-5-8 8"/></svg>Add a photo or screenshot (optional)<input type="file" id="cr-win-file" accept="image/png,image/jpeg,image/webp,image/gif"></label>
      <label>What did you achieve?<input type="text" id="cr-win-title" maxlength="120" placeholder="e.g. Landed my first paying client"></label>
      <label>Tell the class a little more<textarea id="cr-win-text" maxlength="500" placeholder="How did you do it? What helped?"></textarea></label>
      <button type="button" class="cr-btn blue full" id="cr-win-post">Post my win</button>
    </div>
  </div>
  <aside class="cr-thread" aria-label="Win comments">
    <div class="cr-th-h"><button type="button" class="cr-x" data-close aria-label="Close"><?= $svg('back') ?></button><b>Win · comments</b></div>
    <div class="cr-th-b"></div>
    <div class="cr-comp"><div class="cr-row"><div class="cr-field"><textarea id="cr-th-input" rows="1" maxlength="<?= CLASS_ROOM_MAX_TEXT ?>" placeholder="Cheer them on…" aria-label="Comment"></textarea></div><button type="button" class="cr-send" id="cr-th-send" aria-label="Send comment"><?= $crSend ?></button></div></div>
  </aside>
  <div class="cr-view" role="dialog" aria-label="Photo"><img src="" alt=""></div>
  <div class="cr-toast" role="status"></div>
</div>
</main>
<script type="application/json" id="cr-config"><?= json_encode($crConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= e(versioned_asset('assets/js/class-room.js')) ?>" defer></script>
