<?php
/**
 * Creator: My Leads. People who showed they want something from this school but haven't bought yet,
 * strongest signal first (tried to pay, asked for updates, keeps viewing a course, follows the school).
 * Same card layout and message template as Course Viewers (viewers.js), so a saved message carries over.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/creator_leads.php';
$user = require_role(['CREATOR', 'ADMIN']);

$tab = query_param('tab', 'all');
$q = trim(query_param('q'));
$courseFilter = (int) query_param('course');
if (!in_array($tab, ['all', 'paid', 'interest', 'views', 'follow'], true)) $tab = 'all';

$myCourses = db_all("SELECT id, title FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);
if ($courseFilter && !in_array($courseFilter, array_map('intval', array_column($myCourses, 'id')), true)) $courseFilter = 0;

$all = get_creator_leads((int) $user['id']);
if ($q !== '') {
    $needle = mb_strtolower($q);
    $all = array_values(array_filter($all, fn($p) => str_contains(mb_strtolower(($p['name'] ?? '') . ' ' . ($p['email'] ?? '') . ' ' . ($p['phone'] ?? '')), $needle)));
}
if ($courseFilter) {
    $all = array_values(array_filter(array_map(function ($p) use ($courseFilter) {
        $p['lines'] = array_values(array_filter($p['lines'], fn($l) => $l['id'] === $courseFilter));
        return $p;
    }, $all), fn($p) => $p['lines'] !== []));
}

$tabs = ['all' => $all, 'paid' => [], 'interest' => [], 'views' => [], 'follow' => []];
foreach ($all as $p) foreach (array_keys($p['signals']) as $s) $tabs[$s][] = $p;
$shown = $tabs[$tab];

$tabMeta = ['all' => 'All leads', 'paid' => 'Tried to pay', 'interest' => 'Asked for updates', 'views' => 'Keep viewing', 'follow' => 'Follow your school'];
$signalChip = ['paid' => ['Tried to pay', 'vw-hot'], 'interest' => ['Asked for updates', 'vw-int'], 'views' => ['Keeps viewing', 'vw-int'], 'follow' => ['Follows you', 'vw-priv']];

$schoolName = $user['school_name'] ?: '';
$defaultTemplate = 'Hi {name}, this is ' . $user['name'] . ($schoolName ? ' from ' . $schoolName : '')
    . '. I saw you were interested in "{course}" on Obin Academy. Can I help you get started?';
$fallbackCourse = $schoolName !== '' ? $schoolName : 'my courses';

$url = function (array $over = []) use ($tab, $q, $courseFilter): string {
    $p = array_merge(['tab' => $tab === 'all' ? '' : $tab, 'q' => $q, 'course' => $courseFilter ?: ''], $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/creator/leads.php' . ($p ? '?' . http_build_query($p) : ''));
};

$copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
$waIcon = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11.9 11.9 0 0 0 12 0C5.4 0 .1 5.3.1 11.9c0 2.1.6 4.2 1.6 6L0 24l6.3-1.7a12 12 0 0 0 5.7 1.5c6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.4-8.4ZM12 21.8a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4a9.8 9.8 0 0 1-1.5-5.3C2.2 6.4 6.6 2 12 2c2.6 0 5.1 1 6.9 2.9a9.7 9.7 0 0 1 2.9 6.9c0 5.4-4.4 10-9.8 10Zm5.4-7.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1l-1 1.2c-.2.2-.4.2-.7.1a8 8 0 0 1-4-3.5c-.3-.5.3-.5.9-1.5.1-.2.1-.4 0-.5l-.9-2.1c-.2-.5-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3 1.8.8 2.5.8 3.4.7.5-.1 1.8-.7 2-1.5.3-.7.3-1.3.2-1.5-.1-.1-.3-.2-.6-.3Z"/></svg>';
$phoneIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg>';
$mailIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
$lockIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
$infoIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';

$config = ['creatorId' => (int) $user['id'], 'defaultTemplate' => $defaultTemplate];

$pageTitle = 'My Leads — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="vw-top">
  <div>
    <h1 class="h2">My Leads</h1>
    <p class="muted" style="margin-top:4px;">People who showed they want to learn from you but haven't bought yet. The strongest are at the top.</p>
  </div>
  <button class="vw-ghost" id="vw-how-btn" type="button" aria-expanded="false" aria-controls="vw-how"><?= $infoIcon ?><span>Where leads come from</span></button>
</div>

<div class="vw-note" id="vw-how" hidden>
  <strong>Who appears here</strong>
  <ul>
    <li><strong>Tried to pay:</strong> started paying for one of your courses with mobile money in the last 90 days, but the payment failed or was never completed. These are your warmest leads.</li>
    <li><strong>Asked for updates:</strong> tapped "Keep me updated" on one of your courses.</li>
    <li><strong>Keep viewing:</strong> members who opened the same course 3 or more times.</li>
    <li><strong>Follow your school:</strong> follow you but don't own any of your courses yet.</li>
    <li>Anyone who buys the course drops off this list. Members who keep their details private appear as "Private member". Use these details only to help them with your courses. Never share or sell them.</li>
  </ul>
</div>

<nav class="vw-strip lds-strip" aria-label="Lead groups">
  <?php foreach ($tabMeta as $key => $label): ?>
    <a class="vw-tab" href="<?= e($url(['tab' => $key === 'all' ? '' : $key])) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
      <span class="vw-tab-n"><?= number_format(count($tabs[$key])) ?></span><span class="vw-tab-l"><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<form method="get" class="vw-bar">
  <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
  <label class="vw-field vw-grow">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone" aria-label="Search leads" autocomplete="off">
  </label>
  <label class="vw-field"><select name="course" aria-label="Course" data-autosubmit>
    <option value="">All my courses</option>
    <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $courseFilter === (int) $c['id'] ? ' selected' : '' ?>><?= e(mb_strimwidth($c['title'], 0, 40, '…')) ?></option><?php endforeach; ?>
  </select></label>
  <button class="vw-ghost" id="vw-tpl-btn" type="button" aria-expanded="false" aria-controls="vw-tpl">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Your message</span>
  </button>
  <noscript><button class="btn btn-outline btn-sm" type="submit">Apply</button></noscript>
</form>

<div class="vw-tpl" id="vw-tpl" hidden>
  <h3>The message that opens in WhatsApp and email</h3>
  <p class="muted small">Write it once. <strong>{name}</strong> becomes their first name and <strong>{course}</strong> the course they were interested in. It is saved in this browser and also used on Course Viewers.</p>
  <textarea id="vw-tpl-text" aria-label="Message template"></textarea>
  <div class="vw-tpl-pv" id="vw-tpl-pv"></div>
  <div class="vw-tpl-row">
    <button class="btn btn-primary btn-sm" id="vw-tpl-save" type="button">Save message</button>
    <button class="btn btn-outline btn-sm" id="vw-tpl-reset" type="button">Use the default</button>
    <span class="vw-saved" id="vw-tpl-saved" hidden>Saved</span>
  </div>
</div>

<div class="vw-list">
  <?php if (!$shown): ?>
    <?php
      $empty = [
        'all' => ['No leads yet', 'When someone tries to pay, asks for updates, keeps viewing a course or follows your school without buying, they appear here.'],
        'paid' => ['Nobody here right now', 'People whose mobile money payment for your course failed or was not completed appear here.'],
        'interest' => ['Nobody here right now', 'People who tap "Keep me updated" on your courses appear here.'],
        'views' => ['Nobody here right now', 'Members who open the same course 3 or more times appear here.'],
        'follow' => ['Nobody here right now', 'Followers who don\'t own any of your courses yet appear here.'],
      ][$tab];
      if ($q !== '' || $courseFilter) $empty = ['Nothing matches', 'Try another course, or clear the search.'];
    ?>
    <div class="vw-empty"><strong><?= e($empty[0]) ?></strong><?= e($empty[1]) ?></div>
  <?php endif; ?>

  <?php foreach ($shown as $p):
      $first = $p['name'] ? explode(' ', trim($p['name']))[0] : '';
      $wa = $p['phone'] ? whatsapp_number($p['phone']) : null;
      $topCourse = $p['lines'][0]['title'] ?? $fallbackCourse;
      $signals = array_keys($p['signals']);
      usort($signals, fn($a, $b) => CREATOR_LEAD_SIGNALS[$b] <=> CREATOR_LEAD_SIGNALS[$a]);
  ?>
    <article class="vw-card">
      <div class="vw-who">
        <?php if (!$p['private']): ?>
          <span class="vw-av"><?php if (!empty($p['avatar'])): ?><img src="<?= e(asset_src($p['avatar'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($p['name'] ?: '?', 0, 1))) ?><?php endif; ?></span>
          <div class="vw-id">
            <div class="vw-nm"><strong><?= e($p['name'] ?: 'Unknown') ?></strong>
              <?php if (!$p['member']): ?><span class="vw-chip vw-priv">Guest</span><?php endif; ?>
              <?php foreach ($signals as $s): ?><span class="vw-chip <?= $signalChip[$s][1] ?>"><?= e($signalChip[$s][0]) ?></span><?php endforeach; ?>
            </div>
            <div class="vw-ct">
              <?php if ($p['phone']): ?><span><?= e($p['phone']) ?><button class="vw-cp" type="button" data-copy="<?= e($p['phone']) ?>" aria-label="Copy phone number"><?= $copyIcon ?></button></span>
              <?php else: ?><span>No phone number</span><?php endif; ?>
              <?php if ($p['email']): ?><span><?= e($p['email']) ?><button class="vw-cp" type="button" data-copy="<?= e($p['email']) ?>" aria-label="Copy email"><?= $copyIcon ?></button></span><?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <span class="vw-av vw-av-g"><?= $lockIcon ?></span>
          <div class="vw-id"><div class="vw-nm"><strong>Private member</strong>
              <?php foreach ($signals as $s): ?><span class="vw-chip <?= $signalChip[$s][1] ?>"><?= e($signalChip[$s][0]) ?></span><?php endforeach; ?></div>
            <div class="vw-ct"><span>Chose to keep their details private</span></div></div>
        <?php endif; ?>
      </div>

      <div class="vw-courses">
        <?php foreach ($p['lines'] as $l): ?>
          <div class="vw-cr">
            <a class="vw-ct-title" href="<?= e(base_url('courses/view.php?slug=' . $l['slug'])) ?>" target="_blank" rel="noopener" title="<?= e($l['title']) ?>"><?= e($l['title']) ?></a>
            <span class="vw-v"><?= e(implode(' · ', $l['notes'])) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (!$p['lines']): ?><div class="vw-cr"><span class="vw-ct-title" style="color:var(--muted);">Follows your school</span></div><?php endif; ?>
        <div class="vw-when">Last activity <?= e(time_ago($p['last'])) ?></div>
      </div>

      <div class="vw-acts">
        <?php if (!$p['private']): ?>
          <?php if ($wa): ?>
            <a class="vw-wa" data-wa="<?= e($wa) ?>" data-name="<?= e($first) ?>" data-course="<?= e($topCourse) ?>" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode(str_replace(['{name}', '{course}'], [$first ?: 'there', $topCourse], $defaultTemplate))) ?>" target="_blank" rel="noopener noreferrer"><?= $waIcon ?>WhatsApp</a>
          <?php endif; ?>
          <?php if ($p['phone']): ?>
            <a class="vw-ic" href="tel:<?= e(preg_replace('/[^\d+]/', '', $p['phone'])) ?>" aria-label="Call <?= e($first) ?>" title="Call"><?= $phoneIcon ?></a>
          <?php endif; ?>
          <?php if ($p['email']): ?>
            <a class="vw-ic" data-mail="<?= e($p['email']) ?>" data-name="<?= e($first) ?>" data-course="<?= e($topCourse) ?>" href="mailto:<?= e($p['email']) ?>?subject=<?= e(rawurlencode('About ' . $topCourse)) ?>&amp;body=<?= e(rawurlencode(str_replace(['{name}', '{course}'], [$first ?: 'there', $topCourse], $defaultTemplate))) ?>" aria-label="Email <?= e($first) ?>" title="Email"><?= $mailIcon ?></a>
          <?php endif; ?>
          <?php if (!$p['phone'] && !$p['email']): ?><span class="vw-none">No contact details on file</span><?php endif; ?>
        <?php else: ?>
          <span class="vw-none">Contact hidden by the member</span>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($shown): ?>
  <p class="vw-foot">Showing <?= count($shown) ?> lead<?= count($shown) === 1 ? '' : 's' ?>, strongest first. Anyone who buys drops off this list automatically.</p>
<?php endif; ?>
<div class="vw-toast" id="vw-toast" role="status"></div>
<script id="vw-config" type="application/json"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= e(versioned_asset('assets/js/viewers.js')) ?>"></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
