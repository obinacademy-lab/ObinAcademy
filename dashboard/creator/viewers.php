<?php
/**
 * Creator: who has been looking at my courses, and how to reach them.
 *
 * Members (accounts) show with name, WhatsApp / call / email — unless they switched
 * visibility off in Settings, in which case they appear as "Private member". People
 * without an account (and who accepted cookies) appear as "Guest viewer" with only
 * device, rough location and where they came from: there are no contact details.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/course_viewers.php';
$user = require_role(['CREATOR', 'ADMIN']);

$ready = course_viewers_ready();
$courseFilter = (int) query_param('course');
$status = query_param('status', 'open');
$who = query_param('who');
$q = query_param('q');
if (!in_array($status, ['open', 'enrolled', 'all'], true)) $status = 'open';
if (!in_array($who, ['', 'members', 'guests'], true)) $who = '';

$myCourses = db_all("SELECT id, title FROM courses WHERE creator_id = ? AND status = 'PUBLISHED' ORDER BY title", [$user['id']]);
if ($courseFilter && !in_array($courseFilter, array_map('intval', array_column($myCourses, 'id')), true)) $courseFilter = 0;

$viewers = [];
$everyone = [];
if ($ready) {
    // Counts always describe the chosen course (and search), not the status/who pills.
    $everyone = get_course_viewers((int) $user['id'], ['course_id' => $courseFilter, 'q' => $q]);
    $viewers = array_values(array_filter($everyone, function ($r) use ($status, $who) {
        if ($who === 'members' && !$r['is_member']) return false;
        if ($who === 'guests' && $r['is_member']) return false;
        if ($status === 'open' && $r['enrolled']) return false;
        if ($status === 'enrolled' && !$r['enrolled']) return false;
        return true;
    }));
}

$total = count($everyone);
$members = count(array_filter($everyone, fn($r) => $r['is_member']));
$guests = $total - $members;
$open = count(array_filter($everyone, fn($r) => $r['is_member'] && !$r['enrolled']));
$week = count(array_filter($everyone, fn($r) => strtotime($r['last_viewed_at']) >= strtotime('-7 days')));

$schoolName = $user['school_name'] ?: $user['name'];
$sourceLabel = ['google' => 'Google', 'social' => 'social media', 'direct' => 'a direct visit', 'other' => 'another site'];
$deviceLabel = ['mobile' => 'Phone', 'desktop' => 'Computer', 'tablet' => 'Tablet'];

$link = function (array $over = []) use ($courseFilter, $status, $who, $q): string {
    $p = array_merge(['course' => $courseFilter ?: '', 'status' => $status === 'open' ? '' : $status, 'who' => $who, 'q' => $q], $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/creator/viewers.php' . ($p ? '?' . http_build_query($p) : ''));
};

$pageTitle = 'Course Viewers — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-hero reveal">
  <div>
    <h1 class="h2" style="color:#fff;">Course Viewers</h1>
    <p style="margin-top:6px; color:rgba(255,255,255,0.72);">People who have looked at your courses. Reach out to the ones who haven't bought yet.</p>
  </div>
</div>

<?php if (!$ready): ?>
  <div class="card card-pad" style="margin-top:24px; border-style:dashed; text-align:center;">
    <p class="muted">This page is being set up. Check back in a few minutes.</p>
  </div>
<?php else: ?>
  <div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px; gap:14px;">
    <div class="mini-stat" style="--tint:#34d399;"><span class="mini-stat-value"><?= number_format($open) ?></span><span class="mini-stat-label">Members who haven't bought yet</span></div>
    <div class="mini-stat" style="--tint:#60a5fa;"><span class="mini-stat-value"><?= number_format($members) ?></span><span class="mini-stat-label">Members who viewed</span></div>
    <div class="mini-stat" style="--tint:#94a3b8;"><span class="mini-stat-value"><?= number_format($guests) ?></span><span class="mini-stat-label">Guest viewers</span></div>
    <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= number_format($week) ?></span><span class="mini-stat-label">Active in the last 7 days</span></div>
  </div>

  <div class="card card-pad" style="margin-top:18px; font-size:13px; line-height:1.65; color:var(--muted);">
    <strong style="color:var(--ink);">How this works.</strong>
    Members who viewed your courses show their name and contact details unless they switched that off in their settings (those appear as "Private member").
    People without an account show as <strong>Guest viewers</strong>: we only know their device, rough location and where they came from, so there is nothing to contact.
    Please use these details only to follow up about the course they looked at. Never share or sell them.
  </div>

  <div class="chart-card" style="margin-top:18px; padding:18px 20px;">
    <form method="get" class="row gap-2 wrap" style="align-items:center;">
      <select name="course" onchange="this.form.submit()" style="width:auto; flex:1 1 auto; min-width:0; max-width:340px;">
        <option value="">All my courses</option>
        <?php foreach ($myCourses as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $courseFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone" style="width:auto; flex:1 1 200px; min-width:0; max-width:280px;">
      <?php if ($status !== 'open'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <?php if ($who): ?><input type="hidden" name="who" value="<?= e($who) ?>"><?php endif; ?>
      <button class="btn btn-outline btn-sm" type="submit">Search</button>
      <?php if ($courseFilter || $q !== ''): ?><a href="<?= e($link(['course' => '', 'q' => ''])) ?>" class="small muted">Clear</a><?php endif; ?>
    </form>
  </div>

  <div class="row gap-2 wrap" style="margin-top:14px;">
    <?php foreach (['open' => 'Not enrolled yet', 'enrolled' => 'Already enrolled', 'all' => 'Everyone'] as $val => $label): ?>
      <a href="<?= e($link(['status' => $val === 'open' ? '' : $val])) ?>" class="btn btn-sm <?= $status === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="row gap-2 wrap" style="margin-top:8px; align-items:center;">
    <span class="small muted">Show</span>
    <?php foreach (['' => 'Members and guests', 'members' => 'Members', 'guests' => 'Guest viewers'] as $val => $label): ?>
      <a href="<?= e($link(['who' => $val])) ?>" class="cm-sortpill<?= $who === $val ? ' is-on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$viewers): ?>
    <div class="card card-pad" style="margin-top:18px; border-style:dashed; text-align:center;">
      <p class="muted"><?= $total === 0
          ? 'No one has viewed your courses since this page started tracking. New views will appear here.'
          : 'Nobody matches this view. Try "Everyone" above.' ?></p>
    </div>
  <?php else: ?>
    <div class="card cm-wrap" style="margin-top:18px;">
      <table class="cm-table">
        <thead>
          <tr><th>Viewer</th><th>Course viewed</th><th class="num">Views</th><th>Last viewed</th><th>Contact</th></tr>
        </thead>
        <tbody>
          <?php foreach ($viewers as $v):
              $first = $v['name'] ? explode(' ', trim($v['name']))[0] : '';
              $msg = 'Hi ' . ($first ?: 'there') . ', this is ' . $user['name'] . ($user['school_name'] ? ' from ' . $user['school_name'] : '')
                   . '. I noticed you checked out "' . $v['course_title'] . '" on Obin Academy. Do you have any questions I can help with?';
              $wa = $v['phone'] ? whatsapp_number($v['phone']) : null;
              $courseUrl = base_url('courses/view.php?slug=' . $v['course_slug']);
          ?>
            <tr>
              <td data-label="Viewer">
                <div class="cm-who" style="cursor:default;">
                  <span class="cm-avatar"><?php if ($v['is_member'] && !$v['private'] && !empty($v['avatar_url'])): ?><img src="<?= e(asset_src($v['avatar_url'])) ?>" alt=""><?php elseif ($v['is_member'] && !$v['private']): ?><?= e(mb_substr($v['name'], 0, 1)) ?><?php else: ?>?<?php endif; ?></span>
                  <span class="cm-who-text">
                    <?php if ($v['is_member'] && !$v['private']): ?>
                      <strong><?= e($v['name']) ?></strong>
                      <small><?= e($v['email']) ?></small>
                    <?php elseif ($v['private']): ?>
                      <strong>Private member</strong>
                      <small>Chose to keep their details private</small>
                    <?php else: ?>
                      <strong>Guest viewer</strong>
                      <small><?php
                        $bits = [];
                        if (!empty($v['device_type'])) $bits[] = $deviceLabel[$v['device_type']] ?? ucfirst($v['device_type']);
                        $place = trim(($v['city'] ? $v['city'] . ', ' : '') . ($v['country'] ?? ''), ', ');
                        if ($place !== '') $bits[] = $place;
                        if (!empty($v['referrer_source'])) $bits[] = 'via ' . ($sourceLabel[$v['referrer_source']] ?? $v['referrer_source']);
                        echo e($bits ? implode(' · ', $bits) : 'No account');
                      ?></small>
                    <?php endif; ?>
                  </span>
                </div>
              </td>
              <td data-label="Course viewed">
                <a href="<?= e($courseUrl) ?>" target="_blank" rel="noopener" style="color:inherit; font-weight:600;"><?= e($v['course_title']) ?></a>
                <?php if ($v['enrolled']): ?><span class="badge badge-published cm-badge" style="margin:4px 0 0;">Enrolled</span>
                <?php elseif ($v['interested']): ?><span class="badge badge-pending cm-badge" style="margin:4px 0 0;">Tapped Interested</span><?php endif; ?>
              </td>
              <td class="num" data-label="Views"><?= number_format((int) $v['view_count']) ?></td>
              <td data-label="Last viewed"><?= e(time_ago($v['last_viewed_at'])) ?><small class="cm-sub">first <?= e(format_date($v['first_viewed_at'])) ?></small></td>
              <td data-label="Contact">
                <?php if ($v['is_member'] && !$v['private']): ?>
                  <div class="vw-actions">
                    <?php if ($wa): ?><a class="vw-btn vw-wa" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($msg)) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
                    <?php if ($v['phone']): ?><a class="vw-btn" href="tel:<?= e(preg_replace('/[^\d+]/', '', $v['phone'])) ?>">Call</a><?php endif; ?>
                    <?php if ($v['email']): ?><a class="vw-btn" href="mailto:<?= e($v['email']) ?>?subject=<?= e(rawurlencode('About ' . $v['course_title'])) ?>&amp;body=<?= e(rawurlencode($msg)) ?>">Email</a><?php endif; ?>
                  </div>
                  <?php if ($v['phone']): ?><small class="cm-sub" style="margin-top:4px;"><?= e($v['phone']) ?></small><?php endif; ?>
                <?php elseif ($v['private']): ?>
                  <span class="muted small">Hidden by the member</span>
                <?php else: ?>
                  <span class="muted small">No account, so no contact details</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small muted" style="margin-top:10px;">Showing <?= count($viewers) ?> viewer<?= count($viewers) === 1 ? '' : 's' ?>, most recent first. A visit counts once per 30 minutes. Guests are only counted if they accepted cookies.</p>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
