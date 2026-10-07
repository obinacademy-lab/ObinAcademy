<?php
/**
 * Creator: who has been looking at my courses, and how to reach them.
 *
 * One card per person. Members (accounts) show name, phone, email and one-tap WhatsApp / call /
 * email — unless they switched visibility off in Settings, when they appear as "Private member".
 * People without an account (who accepted cookies) appear as "Guest viewer" with only device,
 * rough location and where they came from: there are no contact details to show.
 *
 * Four tabs double as the summary: To follow up (members who haven't bought what they viewed),
 * Guest viewers, Already enrolled, Everyone. The WhatsApp / email message comes from a template
 * the creator can edit (kept in their browser); viewers.js applies it to the links.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/course_viewers.php';
$user = require_role(['CREATOR', 'ADMIN']);

$ready = course_viewers_ready();
$tab = query_param('tab', 'follow');
$sort = query_param('sort', 'recent');
$q = query_param('q');
$courseFilter = (int) query_param('course');
if (!in_array($tab, ['follow', 'guests', 'enrolled', 'all'], true)) $tab = 'follow';
if (!in_array($sort, ['recent', 'views', 'hot'], true)) $sort = 'recent';

$myCourses = db_all("SELECT id, title FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);
if ($courseFilter && !in_array($courseFilter, array_map('intval', array_column($myCourses, 'id')), true)) $courseFilter = 0;

// ---- gather: one entry per person (member by account, guest by cookie) ----------------------
$people = [];
if ($ready) {
    foreach (get_course_viewers((int) $user['id'], ['course_id' => $courseFilter, 'q' => $q]) as $r) {
        $key = $r['is_member'] ? 'u' . $r['user_id'] : 'g' . $r['visitor_id'];
        if (!isset($people[$key])) {
            $people[$key] = [
                'member' => $r['is_member'], 'private' => $r['private'],
                'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'], 'avatar' => $r['avatar_url'],
                'device' => $r['device_type'], 'country' => $r['country'], 'city' => $r['city'], 'source' => $r['referrer_source'],
                'last' => $r['last_viewed_at'], 'lines' => [],
            ];
        }
        $people[$key]['lines'][] = [
            'id' => (int) $r['course_id'], 'title' => $r['course_title'], 'slug' => $r['course_slug'],
            'views' => (int) $r['view_count'], 'last' => $r['last_viewed_at'],
            'enrolled' => $r['enrolled'], 'interested' => $r['interested'],
        ];
        if ($r['last_viewed_at'] > $people[$key]['last']) $people[$key]['last'] = $r['last_viewed_at'];
    }
}

// ---- tabs: each tab shows the lines that matter for it ---------------------------------------
$tabs = ['follow' => [], 'guests' => [], 'enrolled' => [], 'all' => []];
foreach ($people as $p) {
    if (!$p['member']) {
        $tabs['guests'][] = $p;
        $tabs['all'][] = $p;
        continue;
    }
    $open = array_values(array_filter($p['lines'], fn($l) => !$l['enrolled']));
    $done = array_values(array_filter($p['lines'], fn($l) => $l['enrolled']));
    if ($open) $tabs['follow'][] = array_merge($p, ['lines' => $open]);
    if ($done) $tabs['enrolled'][] = array_merge($p, ['lines' => $done]);
    $tabs['all'][] = $p;
}

$viewsOf = fn(array $p): int => array_sum(array_column($p['lines'], 'views'));
$isHot = fn(array $p): bool => $p['member'] && array_filter($p['lines'], fn($l) => !$l['enrolled'] && ($l['views'] >= 3 || $l['interested'])) !== [];
$shown = $tabs[$tab];
usort($shown, function ($a, $b) use ($sort, $viewsOf, $isHot) {
    return match ($sort) {
        'views' => $viewsOf($b) <=> $viewsOf($a),
        'hot' => [(int) $isHot($b), $viewsOf($b)] <=> [(int) $isHot($a), $viewsOf($a)],
        default => strcmp($b['last'], $a['last']),
    };
});

$schoolName = $user['school_name'] ?: '';
$defaultTemplate = 'Hi {name}, this is ' . $user['name'] . ($schoolName ? ' from ' . $schoolName : '')
    . '. I noticed you checked out "{course}" on Obin Academy. Do you have any questions I can help with?';
$sourceLabel = ['google' => 'Google', 'social' => 'social media', 'direct' => 'a direct visit', 'other' => 'another site'];
$deviceLabel = ['mobile' => 'Phone', 'desktop' => 'Computer', 'tablet' => 'Tablet'];

$url = function (array $over = []) use ($tab, $sort, $q, $courseFilter): string {
    $p = array_merge(['tab' => $tab === 'follow' ? '' : $tab, 'sort' => $sort === 'recent' ? '' : $sort, 'q' => $q, 'course' => $courseFilter ?: ''], $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/creator/viewers.php' . ($p ? '?' . http_build_query($p) : ''));
};
$tabMeta = ['follow' => 'To follow up', 'guests' => 'Guest viewers', 'enrolled' => 'Already enrolled', 'all' => 'Everyone'];

$copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
$waIcon = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11.9 11.9 0 0 0 12 0C5.4 0 .1 5.3.1 11.9c0 2.1.6 4.2 1.6 6L0 24l6.3-1.7a12 12 0 0 0 5.7 1.5c6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.4-8.4ZM12 21.8a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4a9.8 9.8 0 0 1-1.5-5.3C2.2 6.4 6.6 2 12 2c2.6 0 5.1 1 6.9 2.9a9.7 9.7 0 0 1 2.9 6.9c0 5.4-4.4 10-9.8 10Zm5.4-7.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1l-1 1.2c-.2.2-.4.2-.7.1a8 8 0 0 1-4-3.5c-.3-.5.3-.5.9-1.5.1-.2.1-.4 0-.5l-.9-2.1c-.2-.5-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3 1.8.8 2.5.8 3.4.7.5-.1 1.8-.7 2-1.5.3-.7.3-1.3.2-1.5-.1-.1-.3-.2-.6-.3Z"/></svg>';
$phoneIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg>';
$mailIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
$userIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>';
$lockIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
$infoIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';

$config = ['creatorId' => (int) $user['id'], 'defaultTemplate' => $defaultTemplate];

$pageTitle = 'Course Viewers — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="vw-top">
  <div>
    <h1 class="h2">Course Viewers</h1>
    <p class="muted" style="margin-top:4px;">People who looked at your courses. Start with the ones who haven't bought yet.</p>
  </div>
  <?php if ($ready): ?>
    <button class="vw-ghost" id="vw-how-btn" type="button" aria-expanded="false" aria-controls="vw-how"><?= $infoIcon ?><span>How details are shared</span></button>
  <?php endif; ?>
</div>

<?php if (!$ready): ?>
  <div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;">
    <p class="muted">This page is being set up. Check back in a few minutes.</p>
  </div>
<?php else: ?>
  <div class="vw-note" id="vw-how" hidden>
    <strong>Who you can contact</strong>
    <ul>
      <li><strong>Members</strong> have an Obin Academy account. You see their name, phone and email unless they switched that off in their settings. Those appear as "Private member".</li>
      <li><strong>Guest viewers</strong> have no account. We only know their device, rough location and where they came from, so there is nothing to contact.</li>
      <li>Use these details only to follow up about the course they looked at. Never share or sell them.</li>
    </ul>
  </div>

  <nav class="vw-strip" aria-label="Viewer groups">
    <?php foreach ($tabMeta as $key => $label): ?>
      <a class="vw-tab" href="<?= e($url(['tab' => $key === 'follow' ? '' : $key])) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
        <span class="vw-tab-n"><?= number_format(count($tabs[$key])) ?></span><span class="vw-tab-l"><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <form method="get" class="vw-bar">
    <?php if ($tab !== 'follow'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
    <label class="vw-field vw-grow">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone" aria-label="Search viewers" autocomplete="off">
    </label>
    <label class="vw-field"><select name="course" aria-label="Course" data-autosubmit>
      <option value="">All my courses</option>
      <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $courseFilter === (int) $c['id'] ? ' selected' : '' ?>><?= e(mb_strimwidth($c['title'], 0, 40, '…')) ?></option><?php endforeach; ?>
    </select></label>
    <label class="vw-field"><select name="sort" aria-label="Sort" data-autosubmit>
      <option value="recent"<?= $sort === 'recent' ? ' selected' : '' ?>>Most recent</option>
      <option value="views"<?= $sort === 'views' ? ' selected' : '' ?>>Most views</option>
      <option value="hot"<?= $sort === 'hot' ? ' selected' : '' ?>>Hottest first</option>
    </select></label>
    <button class="vw-ghost" id="vw-tpl-btn" type="button" aria-expanded="false" aria-controls="vw-tpl">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Your message</span>
    </button>
    <noscript><button class="btn btn-outline btn-sm" type="submit">Apply</button></noscript>
  </form>

  <div class="vw-tpl" id="vw-tpl" hidden>
    <h3>The message that opens in WhatsApp and email</h3>
    <p class="muted small">Write it once. <strong>{name}</strong> becomes their first name and <strong>{course}</strong> the course they viewed. It is saved in this browser.</p>
    <textarea id="vw-tpl-text" aria-label="Message template"></textarea>
    <div class="vw-tpl-pv" id="vw-tpl-pv"></div>
    <div class="vw-tpl-row">
      <button class="btn btn-primary btn-sm" id="vw-tpl-save" type="button">Save message</button>
      <button class="btn btn-outline btn-sm" id="vw-tpl-reset" type="button">Use the default</button>
      <span class="vw-saved" id="vw-tpl-saved" hidden>Saved</span>
    </div>
  </div>

  <?php if ($tab === 'guests' && $shown): ?>
    <div class="vw-guestnote">
      <?= $infoIcon ?>
      <p><strong>These visitors don't have an account yet.</strong> You can't contact them, but you can see which courses and channels bring them in. When they sign up, they move to the members lists.</p>
    </div>
  <?php endif; ?>

  <div class="vw-list">
    <?php if (!$shown): ?>
      <?php
        $empty = [
          'follow' => ['Nobody to follow up right now', 'When someone with an account views a course and hasn\'t bought it, they appear here.'],
          'guests' => ['No guest viewers yet', 'Visitors without an account appear here once they have viewed a course.'],
          'enrolled' => ['Nobody here yet', 'Viewers who go on to enrol appear here.'],
          'all' => ['No viewers yet', 'New views will appear here as people look at your courses.'],
        ][$tab];
        if ($q !== '' || $courseFilter) $empty = ['Nothing matches', 'Try another course, or clear the search.'];
      ?>
      <div class="vw-empty"><strong><?= e($empty[0]) ?></strong><?= e($empty[1]) ?></div>
    <?php endif; ?>

    <?php foreach ($shown as $p):
        $hot = $isHot($p);
        $anyInterested = (bool) array_filter($p['lines'], fn($l) => $l['interested']);
        $allEnrolled = $p['member'] && !array_filter($p['lines'], fn($l) => !$l['enrolled']);
        $topLine = $p['lines'][0];
        foreach ($p['lines'] as $l) if ($l['views'] > $topLine['views']) $topLine = $l;
        $first = $p['name'] ? explode(' ', trim($p['name']))[0] : '';
        $wa = $p['phone'] ? whatsapp_number($p['phone']) : null;
    ?>
      <article class="vw-card">
        <div class="vw-who">
          <?php if ($p['member'] && !$p['private']): ?>
            <span class="vw-av"><?php if (!empty($p['avatar'])): ?><img src="<?= e(asset_src($p['avatar'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?><?php endif; ?></span>
            <div class="vw-id">
              <div class="vw-nm"><strong><?= e($p['name']) ?></strong>
                <?php if ($allEnrolled): ?><span class="vw-chip vw-enr">Enrolled</span><?php endif; ?>
                <?php if ($hot): ?><span class="vw-chip vw-hot">Hot lead</span><?php endif; ?>
                <?php if ($anyInterested && !$allEnrolled): ?><span class="vw-chip vw-int">Tapped Interested</span><?php endif; ?>
              </div>
              <div class="vw-ct">
                <?php if ($p['phone']): ?><span><?= e($p['phone']) ?><button class="vw-cp" type="button" data-copy="<?= e($p['phone']) ?>" aria-label="Copy phone number"><?= $copyIcon ?></button></span>
                <?php else: ?><span>No phone number</span><?php endif; ?>
                <?php if ($p['email']): ?><span><?= e($p['email']) ?><button class="vw-cp" type="button" data-copy="<?= e($p['email']) ?>" aria-label="Copy email"><?= $copyIcon ?></button></span><?php endif; ?>
              </div>
            </div>
          <?php elseif ($p['private']): ?>
            <span class="vw-av vw-av-g"><?= $lockIcon ?></span>
            <div class="vw-id"><div class="vw-nm"><strong>Private member</strong><span class="vw-chip vw-priv">Private</span></div>
              <div class="vw-ct"><span>Chose to keep their details private</span></div></div>
          <?php else: ?>
            <span class="vw-av vw-av-g"><?= $userIcon ?></span>
            <div class="vw-id"><div class="vw-nm"><strong>Guest viewer</strong></div>
              <div class="vw-ct"><?php
                $place = trim(($p['city'] ? $p['city'] . ', ' : '') . ($p['country'] ?? ''), ', ');
                $bits = array_filter([
                    $p['device'] ? ($deviceLabel[$p['device']] ?? ucfirst($p['device'])) . ($place !== '' ? ' · ' . $place : '') : $place,
                    $p['source'] ? 'via ' . ($sourceLabel[$p['source']] ?? $p['source']) : '',
                ]);
                foreach ($bits as $b) echo '<span>' . e($b) . '</span>';
                if (!$bits) echo '<span>No account</span>';
              ?></div></div>
          <?php endif; ?>
        </div>

        <div class="vw-courses">
          <?php foreach ($p['lines'] as $l): ?>
            <div class="vw-cr">
              <a class="vw-ct-title" href="<?= e(base_url('courses/view.php?slug=' . $l['slug'])) ?>" target="_blank" rel="noopener" title="<?= e($l['title']) ?>"><?= e($l['title']) ?></a>
              <span class="vw-v"><?= $tab === 'all' && $p['member'] && $l['enrolled'] ? 'enrolled · ' : '' ?><?= (int) $l['views'] ?> view<?= (int) $l['views'] === 1 ? '' : 's' ?></span>
            </div>
          <?php endforeach; ?>
          <div class="vw-when">Last viewed <?= e(time_ago($p['last'])) ?></div>
        </div>

        <div class="vw-acts">
          <?php if ($p['member'] && !$p['private']): ?>
            <?php if ($wa): ?>
              <a class="vw-wa" data-wa="<?= e($wa) ?>" data-name="<?= e($first) ?>" data-course="<?= e($topLine['title']) ?>" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode(str_replace(['{name}', '{course}'], [$first ?: 'there', $topLine['title']], $defaultTemplate))) ?>" target="_blank" rel="noopener noreferrer"><?= $waIcon ?>WhatsApp</a>
              <a class="vw-ic" href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['phone'])) ?>" aria-label="Call <?= e($first) ?>" title="Call"><?= $phoneIcon ?></a>
            <?php elseif ($p['phone']): ?>
              <a class="vw-ic" href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['phone'])) ?>" aria-label="Call <?= e($first) ?>" title="Call"><?= $phoneIcon ?></a>
            <?php endif; ?>
            <?php if ($p['email']): ?>
              <a class="vw-ic" data-mail="<?= e($p['email']) ?>" data-name="<?= e($first) ?>" data-course="<?= e($topLine['title']) ?>" href="mailto:<?= e($p['email']) ?>?subject=<?= e(rawurlencode('About ' . $topLine['title'])) ?>&amp;body=<?= e(rawurlencode(str_replace(['{name}', '{course}'], [$first ?: 'there', $topLine['title']], $defaultTemplate))) ?>" aria-label="Email <?= e($first) ?>" title="Email"><?= $mailIcon ?></a>
            <?php endif; ?>
          <?php elseif ($p['private']): ?>
            <span class="vw-none">Contact hidden by the member</span>
          <?php else: ?>
            <span class="vw-none">No account, so no contact details</span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($shown): ?>
    <p class="vw-foot">Showing <?= count($shown) ?> of <?= count($tabs[$tab]) ?>, <?= ['recent' => 'most recent first', 'views' => 'most views first', 'hot' => 'hottest first'][$sort] ?>. Repeat visits within 30 minutes count once. Guests are only counted if they accepted cookies.</p>
  <?php endif; ?>
  <div class="vw-toast" id="vw-toast" role="status"></div>
  <script id="vw-config" type="application/json"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
  <script src="<?= e(versioned_asset('assets/js/viewers.js')) ?>"></script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
