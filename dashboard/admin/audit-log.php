<?php
/**
 * Admin: audit log, as an audit trail. A dark console with the last 30 days (actions, today, which
 * admins acted, how many were sensitive: deletions, role changes, exports) and a 30-day activity chart
 * with the split by area; below, every logged action as a plain sentence on a day-by-day timeline,
 * filtered by area, admin, dates and search, with a CSV export. Read-only: nothing here changes data.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-audit.css. Motion: assets/js/admin-pay.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
$user = require_role(['ADMIN']);

$area = isset(AUDIT_AREAS[query_param('area')]) ? query_param('area') : '';
$q = trim(mb_substr(query_param('q'), 0, 100));
$who = (int) query_param('who');
$isDate = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
$from = $isDate(query_param('from'));
$to = $isDate(query_param('to'));
$perPage = 60;
$page = max(1, (int) query_param('page', '1'));

$where = [];
$params = [];
if ($area !== '') { [$sql, $p] = audit_area_sql($area); $where[] = $sql; $params = array_merge($params, $p); }
if ($q !== '') {
    $where[] = '(actor_name LIKE ? OR target_label LIKE ? OR target_type LIKE ? OR detail LIKE ? OR action LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($who) { $where[] = 'actor_id = ?'; $params[] = $who; }
if ($from !== '') { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$url = function (array $over = []) use ($area, $q, $who, $from, $to): string {
    $p = array_filter(array_merge(['area' => $area, 'q' => $q, 'who' => $who ?: '', 'from' => $from, 'to' => $to, 'page' => ''], $over), fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/admin/audit-log.php' . ($p ? '?' . http_build_query($p) : ''));
};

// CSV of the current filter (cells that start with = + - @ are neutralised so a spreadsheet won't run them).
if (query_param('export') === 'csv') {
    $rows = db_all('SELECT * FROM audit_log' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT 10000', $params);
    log_admin_action((int) $user['id'], $user['name'], 'audit.exported', 'Audit log', count($rows) . ' rows');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="obin-audit-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;
    fputcsv($out, ['When', 'Admin', 'Area', 'Did', 'What', 'Which', 'Detail', 'Raw action']);
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [$r['created_at'], $r['actor_name'], AUDIT_AREAS[audit_area($r['action'])][0], audit_verb($r['action']), $r['target_type'], $r['target_label'], $r['detail'] ?? '', $r['action']]));
    }
    fclose($out);
    exit;
}

$total = (int) db_one('SELECT COUNT(*) AS n FROM audit_log' . $whereSql, $params)['n'];
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$logs = db_all('SELECT * FROM audit_log' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);

// Console: the last 30 days, whatever the filter.
$recent = db_all('SELECT action, actor_id, actor_name, created_at FROM audit_log WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) ORDER BY created_at DESC');
$today = date('Y-m-d');
$todayCount = count(array_filter($recent, fn($r) => str_starts_with($r['created_at'], $today)));
$admins30 = count(array_unique(array_map(fn($r) => $r['actor_id'] ?: $r['actor_name'], $recent)));
$sensitive30 = count(array_filter($recent, fn($r) => in_array(audit_tone($r['action']), ['bad', 'watch'], true)));
$daily = [];
for ($i = 29; $i >= 0; $i--) $daily[date('Y-m-d', strtotime("-$i days"))] = 0;
$byArea30 = [];
foreach ($recent as $r) {
    $d = substr($r['created_at'], 0, 10);
    if (isset($daily[$d])) $daily[$d]++;
    $a = audit_area($r['action']);
    $byArea30[$a] = ($byArea30[$a] ?? 0) + 1;
}
arsort($byArea30);
$dayMax = max(1, max($daily));
$last = db_one('SELECT actor_name, created_at FROM audit_log ORDER BY created_at DESC, id DESC LIMIT 1');

// Area switch counts (all time) and the admins who appear in the log.
$areaCounts = array_fill_keys(array_keys(AUDIT_AREAS), 0);
foreach (db_all('SELECT action, COUNT(*) AS n FROM audit_log GROUP BY action') as $r) $areaCounts[audit_area($r['action'])] += (int) $r['n'];
$allCount = array_sum($areaCounts);
$actors = db_all('SELECT actor_id, MAX(actor_name) AS actor_name, COUNT(*) AS n FROM audit_log WHERE actor_id IS NOT NULL GROUP BY actor_id ORDER BY n DESC');
$hasFilters = $area !== '' || $q !== '' || $who || $from !== '' || $to !== '';

// Day headings for the timeline.
$dayLabel = function (string $d) use ($today): string {
    if ($d === $today) return 'Today';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday';
    return date('D j M' . (substr($d, 0, 4) !== date('Y') ? ' Y' : ''), strtotime($d));
};
$groups = [];
foreach ($logs as $l) $groups[substr($l['created_at'], 0, 10)][] = $l;
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2)))) ?: '?';

$pageTitle = 'Audit Log — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-audit.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx au" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Audit Log</h1><p>Every action an admin took, who took it and when. Nothing here can be changed or deleted.</p></div>
    <div class="acx-acts"><a class="acx-btn" href="<?= e($url(['export' => 'csv'])) ?>" title="Download what you see now as a spreadsheet"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>Export CSV</a></div>
  </div>

  <section class="lc-console au-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow <?= $last ? '' : 'calm' ?>"><i></i><?= $last ? 'Last action ' . e(time_ago($last['created_at'])) : 'Nothing logged yet' ?></span>
      <div class="lc-big"><b><span data-scramble="<?= count($recent) ?>"><?= count($recent) ?></span></b><span>admin action<?= count($recent) === 1 ? '' : 's' ?><br>in 30 days</span></div>
      <p class="lc-copy"><?= $last ? 'Most recent by ' . e($last['actor_name']) . ', ' . e(format_date($last['created_at'])) . '.' : 'Publishing campuses, paying professors, contacting leads and changing users all appear here.' ?></p>
      <div class="lc-stats">
        <div><span>Today</span><b data-scramble="<?= $todayCount ?>"><?= $todayCount ?></b></div>
        <div><span>Admins who acted</span><b><?= $admins30 ?></b></div>
        <div><span>Sensitive actions</span><b class="<?= $sensitive30 ? 'red' : '' ?>" title="Deletions, role changes, content removed and exports"><?= $sensitive30 ?></b></div>
        <div><span>All time</span><b data-scramble="<?= $allCount ?>"><?= $allCount ?></b></div>
      </div>
    </div>
    <div>
      <div class="lc-rx-h"><b>Activity · 30 days</b><span><?= $recent ? 'busiest day ' . $dayMax . ' action' . ($dayMax === 1 ? '' : 's') : 'quiet month' ?></span></div>
      <div class="au-bars" role="img" aria-label="Admin actions per day over the last 30 days">
        <?php $k = 0; foreach ($daily as $d => $n): ?>
          <span class="au-day <?= $d === $today ? 'now' : '' ?>" data-t="<?= e(date('j M', strtotime($d))) ?> · <?= $n ?>" style="--k:<?= $k++ ?>"><i class="<?= $n ? '' : 'zero' ?>" style="height:<?= $n ? max(6, round($n / $dayMax * 100)) : 3 ?>%"></i></span>
        <?php endforeach; ?>
      </div>
      <div class="au-axis"><span><?= e(date('j M', strtotime('-29 days'))) ?></span><span><?= e(date('j M', strtotime('-15 days'))) ?></span><span>Today</span></div>
      <div class="au-split">
        <?php if ($byArea30): foreach ($byArea30 as $a => $n): ?>
          <a href="<?= e($url(['area' => $a, 'page' => ''])) ?>#trail" style="--w:<?= round($n / max(1, count($recent)) * 100, 1) ?>"><span><?php dash_icon(AUDIT_AREAS[$a][1]); ?><?= e(AUDIT_AREAS[$a][0]) ?></span><b><?= $n ?></b></a>
        <?php endforeach; else: ?>
          <p class="au-quiet">No admin actions in the last 30 days.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="acx-sec acx-slam" style="--i:2" id="trail">
    <div class="au-h">
      <div><h2>Trail</h2><p><?= $total ?> action<?= $total === 1 ? '' : 's' ?><?= $hasFilters ? ' match' . ($total === 1 ? 'es' : '') . ' your filters' : ', newest first' ?>.</p></div>
      <nav class="au-areas" aria-label="Show one area">
        <a href="<?= e($url(['area' => '', 'page' => ''])) ?>#trail" class="<?= $area === '' ? 'on' : '' ?>">All<em><?= $allCount ?></em></a>
        <?php foreach (AUDIT_AREAS as $key => [$label, $icon]): if (!$areaCounts[$key] && $area !== $key) continue; ?>
          <a href="<?= e($url(['area' => $key, 'page' => ''])) ?>#trail" class="<?= $area === $key ? 'on' : '' ?> <?= $key === 'old' ? 'old' : '' ?>"><?= e($label) ?><em><?= $areaCounts[$key] ?></em></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <form class="au-tools" method="get" action="<?= e(base_url('dashboard/admin/audit-log.php')) ?>#trail">
      <?php if ($area): ?><input type="hidden" name="area" value="<?= e($area) ?>"><?php endif; ?>
      <label class="au-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search a name, campus, payment # or detail" aria-label="Search the log"></label>
      <?php if (count($actors) > 1): ?>
        <label class="au-field"><span>Admin</span><select name="who"><option value="">Everyone</option><?php foreach ($actors as $a): ?><option value="<?= (int) $a['actor_id'] ?>" <?= $who === (int) $a['actor_id'] ? 'selected' : '' ?>><?= e($a['actor_name']) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <label class="au-field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
      <label class="au-field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
      <button class="au-go" type="submit">Apply</button>
      <?php if ($hasFilters): ?><a class="au-clear" href="<?= e(base_url('dashboard/admin/audit-log.php')) ?>#trail">Clear</a><?php endif; ?>
    </form>

    <?php if (!$logs): ?>
      <p class="au-none"><?= $hasFilters ? 'Nothing matches these filters.' : 'No admin actions have been logged yet.' ?></p>
    <?php else: $j = 0; ?>
      <div class="au-trail">
        <?php foreach ($groups as $d => $rows): ?>
          <div class="au-dayhead acx-whip" style="--j:<?= min($j++, 14) ?>"><b><?= e($dayLabel($d)) ?></b><span><?= count($rows) ?> action<?= count($rows) === 1 ? '' : 's' ?></span></div>
          <?php foreach ($rows as $l): $a = audit_area($l['action']); $tone = audit_tone($l['action']); ?>
            <div class="au-row acx-whip tone-<?= $tone ?: 'none' ?>" style="--j:<?= min($j++, 14) ?>">
              <time datetime="<?= e(date('c', strtotime($l['created_at']))) ?>" title="<?= e(format_date($l['created_at'])) ?>"><?= e(date('H:i', strtotime($l['created_at']))) ?></time>
              <span class="au-node area-<?= e($a) ?>" title="<?= e(AUDIT_AREAS[$a][0]) ?>"><?php dash_icon(AUDIT_AREAS[$a][1]); ?></span>
              <div class="au-what">
                <p><span class="au-av" aria-hidden="true"><?= e($initials($l['actor_name'])) ?></span><b><?= e($l['actor_name'] ?: 'Someone') ?></b> <?= e(audit_verb($l['action'])) ?> <span class="au-target"><small><?= e($l['target_type']) ?></small><?= e($l['target_label'] !== '' ? $l['target_label'] : '—') ?></span></p>
                <?php if (!empty($l['detail'])): ?><small class="au-detail"><?= e($l['detail']) ?></small><?php endif; ?>
              </div>
              <div class="au-tags">
                <?php if ($tone === 'bad'): ?><span class="au-tag bad">Sensitive</span><?php elseif ($tone === 'watch'): ?><span class="au-tag watch">Data exported</span><?php endif; ?>
                <span class="au-tag area"><?= e(AUDIT_AREAS[$a][0]) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <div class="au-foot">
          <span>Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($total, $page * $perPage) ?> of <?= $total ?></span>
          <nav class="au-pager" aria-label="Pages">
            <?php if ($page > 1): ?><a href="<?= e($url(['page' => $page - 1])) ?>#trail" aria-label="Newer">←</a><?php endif; ?>
            <span><?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= e($url(['page' => $page + 1])) ?>#trail" aria-label="Older">→</a><?php endif; ?>
          </nav>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
