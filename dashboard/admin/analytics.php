<?php
/**
 * Admin: visitors. Traffic (unique visitors and visits per day, with the key averages), where visitors
 * come from, what they use, where visits start and end, the most-viewed campuses, and towns and cities.
 *
 * Styles: assets/css/admin-visitors.css (scoped under .vx). Chart, ring and motion: assets/js/admin-visitors.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

$allowedRanges = [7 => '7D', 30 => '30D', 90 => '90D'];
$days = (int) (query_param('days') ?: 30);
if (!isset($allowedRanges[$days])) $days = 30;

$summary = get_visit_summary($days);
$series = get_daily_visits_series($days);
$entryPages = get_top_landing_pages($days, 6);
$exitPages = get_top_exit_pages($days, 6);
$campusViews = get_most_viewed_campuses($days, 6);
$cities = get_top_cities($days, 8);
$devices = get_breakdown('device_type', $days);
$browsers = array_slice(get_breakdown('browser', $days), 0, 5, true);
$systems = array_slice(get_breakdown('os', $days), 0, 5, true);

// Unique visitors this week against the week before.
$dailyUnique = array_column($series, 'unique_visitors');
$last7 = array_sum(array_slice($dailyUnique, -7));
$prev7 = array_sum(array_slice($dailyUnique, -14, 7));
$trendPct = $days > 7 && $prev7 > 0 ? round(($last7 - $prev7) / $prev7 * 100) : null;

$sourceMeta = [
    'direct' => ['Direct or shared link', 'Typed address, bookmarks, links shared in chats', '#2b22ff', '#eeedff', '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>'],
    'social' => ['Social media', 'Facebook, Instagram, TikTok, X', '#6aa81e', '#eef6e4', '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>'],
    'google' => ['Google and search', 'People searching for a topic', '#e0a20f', '#fdf3db', '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>'],
    'other' => ['Other websites', 'Links from other sites', '#9a9db8', '#f3f4f8', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>'],
];
$sources = $summary['sources'];
arsort($sources);
$sourceTotal = array_sum($sources);
$topSource = $sourceTotal ? array_key_first($sources) : null;

$deviceMeta = ['mobile' => ['Mobile', '#2b22ff'], 'desktop' => ['Desktop', '#6aa81e'], 'tablet' => ['Tablet', '#e0a20f']];
$deviceTotal = array_sum($devices);
// The ring's centre names the device most visits came from.
$topDevice = 'mobile';
foreach ($deviceMeta as $key => $m) if (($devices[$key] ?? 0) > ($devices[$topDevice] ?? 0)) $topDevice = $key;
$topDeviceShare = $deviceTotal ? round(($devices[$topDevice] ?? 0) / $deviceTotal * 100) : 0;
$topDeviceWords = ['mobile' => 'on a phone', 'desktop' => 'on a computer', 'tablet' => 'on a tablet'];

// Campus icons and colours for the most-viewed list.
$campusLook = [];
$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
if (campuses_ready()) {
    foreach (get_all_campuses() as $k => $c) $campusLook[$c['slug']] = [$c['icon'] ?: 'book', $colors[$k % count($colors)], $c['name']];
}

$pct = fn(int $n, int $of) => $of > 0 ? round($n / $of * 100) : 0;
$vxData = ['series' => array_map(fn($r) => ['d' => $r['date'], 'u' => (int) $r['unique_visitors'], 'v' => (int) $r['visits']], $series)];
$ring = [];
foreach ($deviceMeta as $key => [$label, $color]) $ring[] = ['p' => $deviceTotal ? ($devices[$key] ?? 0) / $deviceTotal : 0, 'c' => $color];
$vxData['ring'] = $ring;

$pageTitle = 'Visitors — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-visitors.css'];
require __DIR__ . '/../../includes/dashboard_header.php';

/** One ranked row with a bar: label on the left, count on the right. */
function vx_rank_row(string $labelHtml, int $n, int $max, int $j): void {
    $w = $max > 0 ? max(2, round($n / $max * 100)) : 0;
    echo '<div class="vx-rk vx-whip" style="--j:' . $j . '"><span>' . $labelHtml . '</span><b>' . number_format($n) . '</b><div class="vx-bar"><i class="vx-snap" style="width:' . $w . '%;--j:' . $j . '"></i></div></div>';
}
?>
<div class="ov vx" data-vx>
  <div class="vx-head vx-slam" style="--i:0">
    <div><h1>Visitors</h1><p>How many people find Obin Academy, where they come from, and what they look at.</p></div>
    <nav class="vx-seg" aria-label="Date range"><span class="vx-pill" aria-hidden="true"></span>
      <?php foreach ($allowedRanges as $val => $label): ?>
        <a href="<?= e(base_url('dashboard/admin/analytics.php?days=' . $val)) ?>" class="<?= $days === $val ? 'on' : '' ?>" <?= $days === $val ? 'aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="vx-kicker vx-type" style="--i:1"><span>Traffic</span></div>
  <section class="vx-sec vx-traffic vx-slam" style="--i:1">
    <div class="vx-tr-main">
      <div class="vx-big">
        <b data-scramble="<?= (int) $summary['unique_visitors'] ?>"><?= number_format($summary['unique_visitors']) ?></b>
        <span>unique visitors in the last <?= $days ?> days</span>
        <?php if ($trendPct !== null): ?><span class="vx-chip <?= $trendPct > 0 ? 'up' : ($trendPct < 0 ? 'down' : 'flat') ?>"><?= $trendPct > 0 ? '▲ ' : ($trendPct < 0 ? '▼ ' : '') ?><?= abs($trendPct) ?>% vs previous week</span><?php endif; ?>
      </div>
      <div class="vx-legend"><span><i style="background:var(--ov-brand)"></i>Unique visitors</span><span><i style="background:var(--ov-kept)"></i>Visits (sessions)</span></div>
      <div class="vx-chart" data-chart></div>
    </div>
    <div class="vx-tr-side">
      <div class="vx-met"><span>Visits</span><b data-scramble="<?= (int) $summary['visits'] ?>"><?= number_format($summary['visits']) ?></b><small>Sessions started</small></div>
      <div class="vx-met"><span>New visitors</span><b data-scramble="<?= (int) $summary['new_visitors'] ?>"><?= number_format($summary['new_visitors']) ?></b><small>First time on the site</small><div class="vx-bar"><i class="vx-snap" style="width:<?= $pct($summary['new_visitors'], max(1, $summary['unique_visitors'])) ?>%;--j:1"></i></div></div>
      <div class="vx-met"><span>Returning visitors</span><b data-scramble="<?= (int) $summary['returning_visitors'] ?>"><?= number_format($summary['returning_visitors']) ?></b><small>Came back more than once</small><div class="vx-bar"><i class="vx-snap" style="width:<?= $pct($summary['returning_visitors'], max(1, $summary['unique_visitors'])) ?>%;--j:2;background:var(--ov-kept)"></i></div></div>
      <div class="vx-met"><span>Time on site</span><b><?= e(format_duration_short($summary['avg_session_duration'])) ?></b><small>Average visit</small></div>
      <div class="vx-met"><span>Pages per visit</span><b><?= e(number_format($summary['avg_pages_per_session'], 1)) ?></b><small>Average</small></div>
    </div>
  </section>

  <div class="vx-kicker vx-type" style="--i:2"><span>Audience</span></div>
  <div class="vx-two">
    <section class="vx-sec vx-slam" style="--i:2">
      <div class="vx-sec-h"><div><h2>Where they come from</h2><p>Share of unique visitors by source</p></div></div>
      <div class="vx-src">
        <div class="vx-stack">
          <?php $j = 0; foreach ($sources as $key => $n): if (!$n) continue; ?><i class="vx-snap" style="width:<?= $n / max(1, $sourceTotal) * 100 ?>%;background:<?= $sourceMeta[$key][2] ?>;--j:<?= $j++ ?>"></i><?php endforeach; ?>
          <?php if (!$sourceTotal): ?><i style="width:100%;background:var(--ov-line-soft)"></i><?php endif; ?>
        </div>
        <div class="vx-src-list">
          <?php $j = 0; foreach ($sources as $key => $n): [$label, $hint, $color, $tint, $icon] = $sourceMeta[$key]; ?>
            <div class="vx-src-row vx-whip" style="--j:<?= $j++ ?>">
              <span class="vx-src-ic" style="background:<?= $tint ?>;color:<?= $color ?>"><svg viewBox="0 0 24 24"><?= $icon ?></svg></span>
              <div><b><?= e($label) ?></b><small><?= e($hint) ?></small></div>
              <span class="n" data-scramble="<?= $n ?>"><?= number_format($n) ?></span>
              <span class="p"><?= $pct($n, $sourceTotal) ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="vx-insight">
          <?php if ($topSource && $sources[$topSource] > 0): ?>
            <span><b><?= $pct($sources[$topSource], $sourceTotal) ?>%</b> of your visitors arrive from <b><?= e(strtolower($sourceMeta[$topSource][0])) ?></b>, your biggest source this period.</span>
          <?php else: ?>
            <span>No visits recorded in this period yet.</span>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="vx-sec vx-slam" style="--i:3">
      <div class="vx-sec-h"><div><h2>What they use</h2><p>Device, browser and system, by visit</p></div></div>
      <div class="vx-dev">
        <div class="vx-dev-top">
          <div class="vx-ring"><svg viewBox="0 0 150 150" data-ring aria-hidden="true"></svg><div class="c"><div><b><?= $deviceTotal ? $topDeviceShare . '%' : '—' ?></b><span><?= $deviceTotal ? $topDeviceWords[$topDevice] : 'no visits yet' ?></span></div></div></div>
          <div class="vx-dlist">
            <?php $j = 0; foreach ($deviceMeta as $key => [$label, $color]): $n = (int) ($devices[$key] ?? 0); ?>
              <div class="vx-whip" style="--j:<?= $j++ ?>"><i style="background:<?= $color ?>"></i><span><?= e($label) ?></span><b><?= number_format($n) ?><em><?= $pct($n, $deviceTotal) ?>%</em></b></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div>
          <div class="vx-tabs" data-tabs><span class="vx-tbar" aria-hidden="true"></span>
            <button type="button" class="on" data-tab="br">Browser</button>
            <button type="button" data-tab="os">Operating system</button>
          </div>
          <?php foreach (['br' => $browsers, 'os' => $systems] as $key => $list): ?>
            <div class="vx-pane <?= $key === 'br' ? 'on' : '' ?>" data-pane="<?= $key ?>">
              <?php if (!$list): ?><p class="vx-none">No data yet in this range.</p><?php endif; ?>
              <?php $max = $list ? max($list) : 0; $j = 0; foreach ($list as $label => $n) vx_rank_row(e($label), $n, $max, $j++); ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="vx-kicker vx-type" style="--i:4"><span>Journey</span></div>
  <section class="vx-sec vx-slam" style="--i:4">
    <div class="vx-three">
      <?php foreach ([['Where visits start', 'The first page people land on', $entryPages, 'landing_path', 'visitors'], ['Where visits end', 'The last page before leaving', $exitPages, 'exit_path', 'n']] as [$title, $sub, $rows, $pathKey, $countKey]): ?>
        <div>
          <h3><?= e($title) ?><span>visits</span></h3><p class="vx-sub"><?= e($sub) ?></p>
          <?php if (!$rows): ?><p class="vx-none">No visits recorded yet.</p><?php endif; ?>
          <?php foreach ($rows as $j => $p): ?>
            <div class="vx-pg vx-whip" style="--j:<?= $j ?>"><span class="ix"><?= $j + 1 ?></span><code title="<?= e($p[$pathKey]) ?>"><?= e($p[$pathKey]) ?></code><b><?= number_format((int) $p[$countKey]) ?></b></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div>
        <h3>Most-viewed campuses<span>views</span></h3><p class="vx-sub">Campus pages opened</p>
        <?php if (!$campusViews): ?><p class="vx-none">No campus page views in this range yet.</p><?php endif; ?>
        <?php foreach ($campusViews as $j => $c): [$icon, $color, $name] = $campusLook[$c['slug']] ?? ['book', '#475569', preg_replace('/ Campus$/', '', $c['title'])]; ?>
          <div class="vx-pg vx-whip" style="--j:<?= $j ?>"><span class="bdg" style="background:<?= e($color) ?>"><?= campus_icon($icon, 2) ?></span><span class="nm"><?= e($name) ?></span><b><?= number_format((int) $c['views']) ?></b></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="vx-kicker vx-type" style="--i:5"><span>Places</span></div>
  <section class="vx-sec vx-slam" style="--i:5">
    <div class="vx-sec-h"><div><h2>Where they are</h2><p>Towns and cities, by visit</p></div></div>
    <?php if ($cities): $max = (int) $cities[0]['n']; ?>
      <div class="vx-places">
        <?php foreach (array_chunk($cities, (int) ceil(count($cities) / 2)) as $ci => $half): ?>
          <div>
            <?php foreach ($half as $k => $c): vx_rank_row('<em class="cc" title="' . e(country_name($c['country'])) . '">' . e($c['country'] ?: '—') . '</em>' . e($c['city']), (int) $c['n'], $max, $ci * 4 + $k); endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="vx-none" style="padding:22px">Locations are worked out gradually in the background. Check back shortly.</p>
    <?php endif; ?>
    <div class="vx-privacy">Approximate, worked out from the internet address. Never a precise location, and the address itself isn't kept.</div>
  </section>
</div>
<script type="application/json" id="vx-data"><?= json_encode($vxData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(versioned_asset('assets/js/admin-visitors.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
