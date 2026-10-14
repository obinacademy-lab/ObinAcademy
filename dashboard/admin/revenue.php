<?php
/**
 * Admin: revenue, as a revenue desk. A dark console with the last 30 days (collected, kept, the
 * professors' share, professor pay sent and owed) and a daily chart of collected and kept; below, a
 * day lookup with that day's totals and every campus payment made on it.
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-revenue.css. Chart and figures:
 * assets/js/admin-revenue.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/data.php';
require __DIR__ . '/../../includes/campuses.php';
$user = require_role(['ADMIN']);

$today = date('Y-m-d');
$selectedDate = query_param('date') ?: $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || $selectedDate > $today || !strtotime($selectedDate)) {
    $selectedDate = $today;
}
$daySummary = get_day_collection_summary($selectedDate);
$prevDay = date('Y-m-d', strtotime($selectedDate . ' -1 day'));
$nextDay = date('Y-m-d', strtotime($selectedDate . ' +1 day'));

// Professor pay: what has been sent (campus_professor_payouts) and what is still owed (unpaid earnings).
try {
    $totalPaidProfessors = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM campus_professor_payouts')['n'] ?? 0);
    $owedProfessors = (float) (db_one('SELECT COALESCE(SUM(amount),0) AS n FROM campus_professor_earnings WHERE payout_id IS NULL')['n'] ?? 0);
} catch (Throwable $e) {
    $totalPaidProfessors = 0.0; $owedProfessors = 0.0;
}

// The last 30 days, per day: collected, the professors' share, and what Obin Academy kept.
$series = get_daily_collections_series(30);
$share = campus_professor_share_by_day($series[0]['date']);
$days = array_map(fn($r) => ['d' => $r['date'], 'c' => (float) $r['collected'], 'p' => (float) ($share[$r['date']] ?? 0)], $series);
$collected30 = array_sum(array_column($days, 'c'));
$share30 = array_sum(array_column($days, 'p'));
$kept30 = max(0.0, $collected30 - $share30);
$dailyCollected = array_column($days, 'c');
$bestDay = $dailyCollected ? max($dailyCollected) : 0.0;
$last7 = array_sum(array_slice($dailyCollected, -7));
$prev7 = array_sum(array_slice($dailyCollected, -14, 7));
$trendPct = $prev7 > 0 ? (int) round(($last7 - $prev7) / $prev7 * 100) : null;

// Campus photos for the day's payments, matched by campus name.
$look = [];
$colors = ['#2b22ff', '#0f766e', '#b45309', '#be185d', '#6d28d9', '#15803d', '#475569', '#0369a1'];
foreach (get_all_campuses() as $k => $c) $look[$c['name']] = ['photo' => campus_photo($c), 'color' => $colors[$k % count($colors)], 'icon' => $c['icon'] ?: 'book'];
$dayUrl = fn(string $d) => base_url('dashboard/admin/revenue.php' . ($d === $today ? '' : '?date=' . $d)) . '#day';
$dayLabel = $selectedDate === $today ? 'Today' : ($selectedDate === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('l', strtotime($selectedDate)));

$pageTitle = 'Revenue — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-revenue.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx rv" data-rv>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Revenue</h1><p>What the campuses collected, what professors earned from it, and what Obin Academy kept.</p></div>
    <div class="acx-acts"><a class="acx-btn dark" href="<?= e(base_url('dashboard/admin/professor-pay.php')) ?>"><svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>Professor pay</a></div>
  </div>

  <section class="lc-console rv-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow"><i></i>Last 30 days</span>
      <div class="rv-big"><small>UGX</small><b data-scramble="<?= (int) round($collected30) ?>"><?= e(number_format($collected30)) ?></b></div>
      <p class="lc-copy">Collected from campus payments<?php if ($trendPct !== null): ?> · <span class="rv-trend <?= $trendPct >= 0 ? 'up' : 'down' ?>"><?= $trendPct >= 0 ? '▲' : '▼' ?> <?= abs($trendPct) ?>% on the week before</span><?php endif; ?></p>
      <div class="lc-stats">
        <div><span>Kept by Obin Academy</span><b class="lime"><small>UGX</small><span data-scramble="<?= (int) round($kept30) ?>"><?= e(number_format($kept30)) ?></span></b></div>
        <div><span>Professors' share</span><b><small>UGX</small><span data-scramble="<?= (int) round($share30) ?>"><?= e(number_format($share30)) ?></span></b></div>
        <div><span>Paid to professors · all time</span><b><small>UGX</small><span data-scramble="<?= (int) round($totalPaidProfessors) ?>"><?= e(number_format($totalPaidProfessors)) ?></span></b></div>
        <div><span>Owed to professors now</span><b class="<?= $owedProfessors > 0 ? 'red' : '' ?>"><small>UGX</small><span data-scramble="<?= (int) round($owedProfessors) ?>"><?= e(number_format($owedProfessors)) ?></span></b></div>
      </div>
    </div>
    <div class="rv-chart-wrap">
      <div class="lc-rx-h"><b>Every day, last 30 days</b><span class="rv-legend"><i class="c"></i>Collected <i class="k"></i>Kept</span></div>
      <div class="rv-chart" data-chart role="img" aria-label="Money collected and kept each day for the last 30 days"></div>
      <div class="rv-mini">
        <div><span>Best day</span><b>UGX <?= e(number_format($bestDay)) ?></b></div>
        <div><span>Daily average</span><b>UGX <?= e(number_format($collected30 / 30)) ?></b></div>
      </div>
    </div>
  </section>

  <section class="acx-sec acx-slam" style="--i:2" id="day">
    <div class="rv-day-h">
      <div><h2><?= e($dayLabel) ?><span><?= e(date('j F Y', strtotime($selectedDate))) ?></span></h2><p>Every campus payment that landed on this day</p></div>
      <form method="get" class="rv-pick" action="<?= e(base_url('dashboard/admin/revenue.php')) ?>#day">
        <a class="rv-step" href="<?= e($dayUrl($prevDay)) ?>" aria-label="Previous day" title="Previous day">‹</a>
        <label class="rv-date"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><input type="date" name="date" value="<?= e($selectedDate) ?>" max="<?= e($today) ?>" aria-label="Date" onchange="this.form.submit()"></label>
        <?php if ($nextDay <= $today): ?><a class="rv-step" href="<?= e($dayUrl($nextDay)) ?>" aria-label="Next day" title="Next day">›</a><?php else: ?><span class="rv-step off" aria-hidden="true">›</span><?php endif; ?>
        <?php if ($selectedDate !== $today): ?><a class="rv-today" href="<?= e($dayUrl($today)) ?>">Today</a><?php endif; ?>
      </form>
    </div>
    <div class="rv-totals">
      <div><span>Collected</span><b><small>UGX</small><?= e(number_format($daySummary['collected'])) ?></b></div>
      <div><span>Professors' share</span><b><small>UGX</small><?= e(number_format($daySummary['professors'])) ?></b></div>
      <div><span>Kept by Obin Academy</span><b class="kept"><small>UGX</small><?= e(number_format($daySummary['kept'])) ?></b></div>
      <div><span>Payments</span><b><?= (int) $daySummary['count'] ?></b></div>
    </div>
    <?php if ($daySummary['payments']): ?>
      <div class="rv-grid rv-th"><span>Time</span><span>Campus</span><span>Member</span><span>Plan</span><span class="r">Amount</span></div>
      <?php foreach ($daySummary['payments'] as $j => $p): $l = $look[$p['campus_name']] ?? null; ?>
        <div class="rv-grid lc-row acx-whip" style="--j:<?= min($j, 14) ?>">
          <span class="rv-time"><?= e(date('g:i a', strtotime($p['created_at']))) ?></span>
          <span class="rv-camp">
            <?php if ($l && $l['photo']): ?><i style="background-image:url('<?= e($l['photo']) ?>')"></i><?php elseif ($l): ?><i class="badge" style="background:<?= e($l['color']) ?>"><?= campus_icon($l['icon'], 1.8) ?></i><?php else: ?><i class="badge" style="background:#475569"></i><?php endif; ?>
            <b><?= e($p['campus_name'] ?: 'A campus') ?></b>
          </span>
          <span class="rv-member"><?= e($p['learner_name'] ?: 'A member') ?></span>
          <span><span class="rv-plan <?= $p['campus_plan'] === 'YEARLY' ? 'y' : '' ?>"><?= $p['campus_plan'] === 'YEARLY' ? 'Yearly' : 'Monthly' ?></span></span>
          <span class="rv-amt r"><small>UGX</small><?= e(number_format((float) $p['amount'])) ?></span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="rv-none">No campus payments on <?= e(date('j F Y', strtotime($selectedDate))) ?>.</p>
    <?php endif; ?>
  </section>
</div>
<script type="application/json" id="rv-data"><?= json_encode(['days' => $days], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(versioned_asset('assets/js/admin-revenue.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
