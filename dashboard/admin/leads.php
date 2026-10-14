<?php
/**
 * Admin: leads. A dark "waiting on you" panel (leads nobody has contacted, and a shortcut to the warmest
 * one), the pipeline, leads captured per day, where leads come from, and every lead ranked by interest.
 * Opening a lead (?id=) slides its panel in over the list: status, the message composer (WhatsApp or
 * email, which marks the lead Contacted), campuses viewed, notes and browsing history.
 *
 * Styles: assets/css/admin-leads.css (scoped under .lx). Chart, counters and the panel: assets/js/admin-leads.js.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/leads.php';
require __DIR__ . '/../../includes/audit.php';
require __DIR__ . '/../../includes/outreach.php';
$user = require_role(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $leadId = (int) post('leadId');
    $action = post('_action');
    $lead = get_lead_by_id($leadId);

    if ($lead && $action === 'set_status') {
        $status = (string) post('status');
        if (set_lead_status($leadId, $status)) {
            log_admin_action((int) $user['id'], $user['name'], 'lead.status_changed', 'Lead', $lead['name'], "status -> $status");
        }
    } elseif ($lead && in_array($action, ['message_whatsapp', 'message_email'], true)) {
        // One-tap outreach: WhatsApp opens the admin's own WhatsApp with the text written; email is sent from the platform.
        $templates = lead_message_templates();
        $tplKey = isset($templates[post('template')]) ? post('template') : lead_message_default_for($lead);
        $text = trim(mb_substr((string) post('message'), 0, 1500));
        if ($text === '') {
            flash_set('error', 'Write a message first.');
        } elseif ($action === 'message_whatsapp') {
            $wa = whatsapp_number($lead['phone']);
            if (!$wa) {
                flash_set('error', 'This lead has no usable phone number, so WhatsApp cannot be opened.');
            } else {
                log_lead_contact($leadId, (int) $user['id'], 'whatsapp', $tplKey);
                log_admin_action((int) $user['id'], $user['name'], 'lead.contacted', 'Lead', $lead['name'], 'WhatsApp');
                redirect('https://wa.me/' . $wa . '?text=' . rawurlencode($text));
            }
        } elseif (!$lead['consent_marketing'] || $lead['unsubscribed']) {
            flash_set('error', 'This lead has not agreed to marketing email or has unsubscribed, so no email was sent.');
        } else {
            if (send_lead_message_email($lead, $templates[$tplKey]['subject'], $text, $user['email'])) {
                log_lead_contact($leadId, (int) $user['id'], 'email', $tplKey);
                log_admin_action((int) $user['id'], $user['name'], 'lead.contacted', 'Lead', $lead['name'], 'email');
                flash_set('success', 'Email sent to ' . $lead['email'] . '. A copy is in your inbox (' . $user['email'] . ') and their replies come to you there. The lead is now marked Contacted.');
            } else {
                flash_set('error', 'The email was not sent. The email service refused it, so nothing was recorded. Try again, or use WhatsApp.');
            }
        }
        redirect('/dashboard/admin/leads.php?id=' . $leadId);
    } elseif ($lead && $action === 'add_note') {
        add_lead_note($leadId, (int) $user['id'], (string) post('note'));
        log_admin_action((int) $user['id'], $user['name'], 'lead.note_added', 'Lead', $lead['name']);
    }
    redirect('/dashboard/admin/leads.php' . ($leadId ? "?id=$leadId" : ''));
}

$statusLabels = ['NEW' => 'New', 'CONTACTED' => 'Contacted', 'INTERESTED' => 'Interested', 'ENROLLED' => 'Joined', 'CREATOR' => 'Wants to teach', 'LOST' => 'Lost'];
$statusTint = ['NEW' => '#2b22ff', 'CONTACTED' => '#8b5cf6', 'INTERESTED' => '#e39a0b', 'ENROLLED' => '#12855a', 'CREATOR' => '#be185d', 'LOST' => '#94a3b8'];
$sourceLabels = ['direct' => 'Direct or shared link', 'social' => 'Social media', 'google' => 'Google and search', 'other' => 'Other websites'];
$sourceShort = ['direct' => 'Direct', 'social' => 'Social', 'google' => 'Google', 'other' => 'Other'];
$sourceTint = ['direct' => '#2b22ff', 'social' => '#6aa81e', 'google' => '#e39a0b', 'other' => '#b9bbd0'];

// -------------------- the list (always shown; a lead's panel opens over it) --------------------
$filters = [
    'q' => query_param('q'), 'status' => query_param('status'), 'type' => query_param('type'), 'source' => query_param('source'),
    'sort' => query_param('sort') === 'new' ? 'new' : 'hot',
];
$page = max(1, (int) (query_param('page') ?: 1));
$perPage = 25;
$result = get_leads($filters, $page, $perPage);
$leads = $result['rows'];
$totalLeads = $result['total'];
$totalPages = max(1, (int) ceil($totalLeads / $perPage));
$statCounts = db_one("SELECT COUNT(*) AS total, SUM(status = 'NEW') AS new_count, SUM(lead_type = 'creator') AS creator_count, SUM(status = 'ENROLLED') AS enrolled_count FROM leads");
$allLeads = (int) ($statCounts['total'] ?? 0);
$newCount = (int) ($statCounts['new_count'] ?? 0);
$creatorCount = (int) ($statCounts['creator_count'] ?? 0);
$enrolledCount = (int) ($statCounts['enrolled_count'] ?? 0);
$pctOf = fn(int $n): int => $allLeads ? (int) round($n / $allLeads * 100) : 0;
$series = get_leads_daily_series(30);
$daily = array_map('intval', array_column($series, 'count'));
$week = array_sum(array_slice($daily, -7));
$prevWeek = array_sum(array_slice($daily, -14, 7));
$trendPct = $prevWeek > 0 ? (int) round(($week - $prevWeek) / $prevWeek * 100) : null;
$statusBreakdown = get_lead_status_breakdown();
$sourceBreakdown = get_lead_source_breakdown();
$hottest = $newCount ? get_hottest_new_lead() : null;
$exportQuery = http_build_query(array_filter(array_diff_key($filters, ['sort' => 1])));
$leadsUrl = fn(array $over = []): string => base_url('dashboard/admin/leads.php' . (($p = array_filter(array_merge($filters, ['page' => ''], $over), fn($v) => $v !== '' && $v !== null && $v !== 'hot')) ? '?' . http_build_query($p) : ''));
$listUrl = $leadsUrl(['page' => $page > 1 ? $page : '']);
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2))));
$seen = function (string $at): string {
    $days = (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($at)))) / 86400);
    return $days <= 0 ? 'Today' : ($days === 1 ? 'Yesterday' : ($days < 30 ? $days . ' days ago' : date('j M Y', strtotime($at))));
};

// -------------------- one lead's panel --------------------
$detailId = (int) query_param('id');
$lead = $detailId ? get_lead_by_id($detailId) : null;
if ($detailId && !$lead) { flash_set('error', 'Lead not found.'); redirect('/dashboard/admin/leads.php'); }
if ($lead) {
    $notes = get_lead_notes($detailId);
    $pageHistory = get_lead_page_history($lead['visitor_id']);
    $campusesViewed = get_lead_campuses_viewed($lead['visitor_id']);
    $location = trim(($lead['city'] ?? '') . (!empty($lead['city']) && !empty($lead['country']) ? ', ' : '') . (!empty($lead['country']) ? country_name($lead['country']) : ''), ' ,');
    $msgTemplates = lead_message_templates();
    $msgDefault = lead_message_default_for($lead);
    $msgWa = whatsapp_number($lead['phone']);
    $msgCanEmail = $lead['consent_marketing'] && !$lead['unsubscribed'];
    $msgHistory = get_lead_contacts($detailId);
    $leadHeat = lead_heat($lead);
}

$lxData = ['daily' => $daily, 'dates' => array_column($series, 'date')];
$pageTitle = ($lead ? e($lead['name']) . ' — ' : '') . 'Leads — Admin — Obin Academy';
$dashClass = 'dash-ov' . ($lead ? ' lx-locked' : '');
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-leads.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov lx" data-lx>
  <div class="lx-head lx-slam" style="--i:0">
    <div><h1>Leads</h1><p>Everyone who shared their details. Reach them while they're warm.</p></div>
    <a class="lx-btn" href="<?= e(base_url('api/export-leads.php?' . $exportQuery)) ?>"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>Export CSV</a>
  </div>

  <section class="lx-void lx-slam" style="--i:1">
    <span class="lx-glow" aria-hidden="true"></span><span class="lx-scan" aria-hidden="true"></span>
    <div>
      <?php if ($newCount > 0): ?>
        <span class="lx-eyebrow"><i></i>Waiting on you</span>
        <div class="lx-num"><b data-scramble="<?= $newCount ?>" data-glitch><?= number_format($newCount) ?></b><span><?= $newCount === 1 ? 'lead has' : 'leads have' ?> not been contacted yet</span></div>
        <p class="lx-copy">They asked to hear from Obin Academy. The longer they wait, the colder they get. Start with the hottest: the ones who came back most recently.</p>
        <div class="lx-cta">
          <?php if ($hottest): ?><a class="lx-btn-lime" href="<?= e(base_url('dashboard/admin/leads.php?id=' . (int) $hottest['id'] . '#message')) ?>">Message <?= e(lead_first_name($hottest)) ?>, the hottest lead <span>→</span></a><?php endif; ?>
          <a class="lx-btn-ghost" href="<?= e(base_url('dashboard/admin/leads.php?status=NEW')) ?>">Show all <?= number_format($newCount) ?> new lead<?= $newCount === 1 ? '' : 's' ?></a>
        </div>
      <?php else: ?>
        <span class="lx-eyebrow calm"><i></i>All caught up</span>
        <div class="lx-num"><b>0</b><span>leads waiting. Everyone has been contacted.</span></div>
        <p class="lx-copy">New leads appear here the moment someone shares their details.</p>
      <?php endif; ?>
    </div>
    <div class="lx-vstats">
      <div><span>Total leads</span><b data-scramble="<?= $allLeads ?>"><?= number_format($allLeads) ?></b><em>since the start</em></div>
      <div><span>Captured this week</span><b data-scramble="<?= $week ?>"><?= number_format($week) ?></b><svg class="lx-vspark" data-spark aria-hidden="true"></svg></div>
      <div><span>Became students</span><b data-scramble="<?= $enrolledCount ?>"><?= number_format($enrolledCount) ?></b><em class="l"><?= $pctOf($enrolledCount) ?>% conversion</em></div>
      <div><span>Want to teach</span><b data-scramble="<?= $creatorCount ?>"><?= number_format($creatorCount) ?></b><em>Teacher leads</em></div>
    </div>
  </section>

  <section class="lx-sec lx-slam" style="--i:2">
    <div class="lx-sec-h"><div><h2>Pipeline</h2><p>Where every lead stands today. Click a stage to see those leads.</p></div></div>
    <div class="lx-pipe">
      <?php $j = 0; foreach (['NEW', 'CONTACTED', 'INTERESTED', 'ENROLLED', 'gap', 'CREATOR', 'LOST'] as $key):
          if ($key === 'gap') { echo '<span class="lx-gap" aria-hidden="true"></span>'; continue; }
          $c = (int) ($statusBreakdown[$key] ?? 0); ?>
        <a class="lx-stage <?= in_array($key, ['CREATOR', 'LOST'], true) ? 'side' : '' ?> <?= $filters['status'] === $key ? 'on' : '' ?>" href="<?= e(base_url('dashboard/admin/leads.php?status=' . $key)) ?>" style="--c:<?= $statusTint[$key] ?>">
          <div class="lb"><i></i><?= e($statusLabels[$key]) ?></div>
          <b data-scramble="<?= $c ?>"><?= number_format($c) ?></b>
          <small><?= $pctOf($c) ?>%<?= $key === 'NEW' ? ' of leads' : '' ?></small>
          <div class="lx-charge"><i class="lx-snap" style="width:<?= max($c ? 2 : 0, $pctOf($c)) ?>%;--j:<?= $j++ ?>"></i></div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="lx-two">
    <section class="lx-sec lx-slam" style="--i:3">
      <div class="lx-sec-h">
        <div><h2>Leads captured</h2><p>New leads per day, last 30 days</p></div>
        <?php if ($trendPct !== null): ?><span class="lx-chip <?= $trendPct > 0 ? 'up' : ($trendPct < 0 ? 'down' : '') ?>"><?= $trendPct > 0 ? '▲ ' : ($trendPct < 0 ? '▼ ' : '') ?><?= abs($trendPct) ?>% vs previous week</span><?php endif; ?>
      </div>
      <div class="lx-chart" data-chart></div>
    </section>
    <section class="lx-sec lx-slam" style="--i:4">
      <div class="lx-sec-h"><div><h2>Where leads come from</h2><p>Share of all <?= number_format($allLeads) ?> lead<?= $allLeads === 1 ? '' : 's' ?></p></div></div>
      <div class="lx-src">
        <div class="lx-stack">
          <?php $j = 0; foreach ($sourceLabels as $key => $label): $c = (int) ($sourceBreakdown[$key] ?? 0); if (!$c) continue; ?><i class="lx-snap" style="width:<?= $c / max(1, $allLeads) * 100 ?>%;background:<?= $sourceTint[$key] ?>;--j:<?= $j++ ?>"></i><?php endforeach; ?>
          <?php if (!$allLeads): ?><i style="width:100%;background:var(--ov-line-soft)"></i><?php endif; ?>
        </div>
        <?php $j = 0; foreach ($sourceLabels as $key => $label): $c = (int) ($sourceBreakdown[$key] ?? 0); ?>
          <a class="lx-srow lx-whip" style="--j:<?= $j++ ?>" href="<?= e(base_url('dashboard/admin/leads.php?source=' . $key)) ?>"><i style="background:<?= $sourceTint[$key] ?>"></i><span><?= e($label) ?></span><b><?= number_format($c) ?></b><em><?= $pctOf($c) ?>%</em></a>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <section class="lx-sec lx-slam" style="--i:5" id="all">
    <div class="lx-sec-h lx-list-h">
      <div><h2>All leads</h2><p><?= $filters['sort'] === 'hot' ? 'Hottest first: people who came back recently and often' : 'Newest first' ?></p></div>
      <div class="lx-sort">
        <a href="<?= e($leadsUrl(['sort' => 'hot'])) ?>#all" class="<?= $filters['sort'] === 'hot' ? 'on' : '' ?>">Hottest first</a>
        <a href="<?= e($leadsUrl(['sort' => 'new'])) ?>#all" class="<?= $filters['sort'] === 'new' ? 'on' : '' ?>">Newest first</a>
      </div>
    </div>
    <form method="get" class="lx-tools" action="<?= e(base_url('dashboard/admin/leads.php')) ?>#all">
      <?php if ($filters['sort'] === 'new'): ?><input type="hidden" name="sort" value="new"><?php endif; ?>
      <?php if ($filters['status']): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
      <label class="lx-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" name="q" placeholder="Search by name or email" aria-label="Search leads" value="<?= e($filters['q']) ?>"></label>
      <select name="type" aria-label="Type" onchange="this.form.submit()">
        <option value="">All types</option>
        <option value="learner" <?= $filters['type'] === 'learner' ? 'selected' : '' ?>>Learners</option>
        <option value="creator" <?= $filters['type'] === 'creator' ? 'selected' : '' ?>>Teachers</option>
      </select>
      <select name="source" aria-label="Source" onchange="this.form.submit()">
        <option value="">All sources</option>
        <?php foreach ($sourceLabels as $val => $label): ?><option value="<?= $val ?>" <?= $filters['source'] === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <?php if (array_filter(array_diff_key($filters, ['sort' => 1]))): ?><a class="lx-clear" href="<?= e(base_url('dashboard/admin/leads.php')) ?>#all">Clear</a><?php endif; ?>
    </form>
    <div class="lx-chips">
      <a href="<?= e($leadsUrl(['status' => ''])) ?>#all" class="<?= !$filters['status'] ? 'on' : '' ?>">All <em><?= number_format($allLeads) ?></em></a>
      <?php foreach ($statusLabels as $key => $label): ?>
        <a href="<?= e($leadsUrl(['status' => $key])) ?>#all" class="<?= $filters['status'] === $key ? 'on' : '' ?>"><?= e($label) ?> <em><?= number_format((int) ($statusBreakdown[$key] ?? 0)) ?></em></a>
      <?php endforeach; ?>
    </div>

    <?php if ($leads): ?>
      <div class="lx-grid lx-th"><span>Lead</span><span>Interest</span><span>Type</span><span>Source</span><span>Last seen</span><span>Status</span><span></span></div>
      <?php foreach ($leads as $j => $l): $h = lead_heat($l); $isTeacher = $l['lead_type'] === 'creator'; $open = $leadsUrl(['id' => (int) $l['id'], 'page' => $page > 1 ? $page : '']); $wa = whatsapp_number($l['phone']); ?>
        <div class="lx-grid lx-row lx-whip <?= $lead && (int) $lead['id'] === (int) $l['id'] ? 'on' : '' ?>" style="--j:<?= $j ?>">
          <a class="lx-who" href="<?= e($open) ?>"><span class="lx-av <?= $h['level'] === 'hot' ? 'hot' : '' ?>"><?= e($initials($l['name'])) ?></span><span><b><?= e($l['name']) ?></b><small><?= e($l['email']) ?></small></span></a>
          <div class="lx-heat <?= $h['level'] ?>" title="Worked out from how recently and how often they visit"><span class="t"><?= $h['level'] ?></span><div class="m"><?php for ($k = 0; $k < 5; $k++): ?><i class="<?= $k < $h['score'] ? 'on' : '' ?>"></i><?php endfor; ?></div></div>
          <span class="lx-type <?= $isTeacher ? 't' : '' ?>"><?= $isTeacher ? 'Teacher' : 'Learner' ?></span>
          <span class="lx-seen"><?= e($sourceShort[$l['source']] ?? $l['source']) ?></span>
          <span class="lx-seen"><b><?= e($seen($l['last_visit_at'])) ?></b><?= (int) $l['visit_count'] ?> visit<?= (int) $l['visit_count'] === 1 ? '' : 's' ?></span>
          <span class="lx-st" style="--c:<?= $statusTint[$l['status']] ?? '#94a3b8' ?>"><?= e($statusLabels[$l['status']] ?? $l['status']) ?></span>
          <span class="lx-act">
            <?php if ($wa): ?><a class="lx-ic wa" href="<?= e($leadsUrl(['id' => (int) $l['id']])) ?>#message" title="Message <?= e(lead_first_name($l)) ?> on WhatsApp"><svg class="wa-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.91-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.08-.13-.27-.2-.57-.35zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88zm8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89a11.82 11.82 0 0 0-3.48-8.42z"/></svg></a><?php endif; ?>
            <a class="lx-ic go" href="<?= e($open) ?>" title="Open <?= e(lead_first_name($l)) ?>"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></a>
          </span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="lx-none">No leads match these filters yet.</div>
    <?php endif; ?>
    <div class="lx-foot">
      <span><?= $totalLeads ? 'Showing ' . number_format(($page - 1) * $perPage + 1) . ' to ' . number_format(min($totalLeads, $page * $perPage)) . ' of ' . number_format($totalLeads) : 'No leads to show' ?></span>
      <?php if ($totalPages > 1):
          $shown = array_unique(array_filter([1, $page - 1, $page, $page + 1, $totalPages], fn($p) => $p >= 1 && $p <= $totalPages));
          sort($shown); $prevN = 0; ?>
        <nav class="lx-pager" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= e($leadsUrl(['page' => $page - 1])) ?>#all" aria-label="Previous">‹</a><?php endif; ?>
          <?php foreach ($shown as $p): if ($prevN && $p - $prevN > 1): ?><span>…</span><?php endif; $prevN = $p; ?>
            <a href="<?= e($leadsUrl(['page' => $p])) ?>#all" class="<?= $p === $page ? 'on' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
          <?php endforeach; ?>
          <?php if ($page < $totalPages): ?><a href="<?= e($leadsUrl(['page' => $page + 1])) ?>#all" aria-label="Next">›</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php if ($lead): ?>
  <a class="lx-scrim" href="<?= e($listUrl) ?>" aria-label="Close" data-close></a>
  <aside class="lx-drawer" aria-label="<?= e($lead['name']) ?>" data-drawer>
    <div class="lx-d-top">
      <a class="lx-d-close" href="<?= e($listUrl) ?>" aria-label="Close" data-close><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></a>
      <div class="lx-d-who lx-in" style="--j:0">
        <span class="lx-av big"><?= e($initials($lead['name'])) ?></span>
        <div><h2><?= e($lead['name']) ?></h2><p><?= e($lead['email']) ?><?= $lead['phone'] ? ' · ' . e($lead['phone']) : '' ?><?= $location ? ' · ' . e($location) : '' ?></p></div>
      </div>
      <div class="lx-d-facts">
        <div class="lx-in" style="--j:1"><span>Type</span><b><?= $lead['lead_type'] === 'creator' ? 'Teacher' : 'Learner' ?></b></div>
        <div class="lx-in" style="--j:2"><span>Source</span><b><?= e($sourceShort[$lead['source']] ?? $lead['source']) ?></b></div>
        <div class="lx-in" style="--j:3"><span>Visits</span><b><?= (int) $lead['visit_count'] ?></b></div>
        <div class="lx-in" style="--j:4"><span>Interest</span><b class="lx-heat-word <?= $leadHeat['level'] ?>"><?= ucfirst($leadHeat['level']) ?></b></div>
      </div>
    </div>
    <div class="lx-d-body">
      <section class="lx-in" style="--j:2">
        <h3>Status</h3>
        <form method="post" class="lx-status">
          <?= csrf_field() ?><input type="hidden" name="leadId" value="<?= (int) $lead['id'] ?>"><input type="hidden" name="_action" value="set_status">
          <?php foreach ($statusLabels as $val => $label): ?>
            <button type="submit" name="status" value="<?= $val ?>" class="<?= $lead['status'] === $val ? 'on' : '' ?>" style="--c:<?= $statusTint[$val] ?>" <?= $lead['status'] === $val ? 'aria-pressed="true"' : '' ?>><?= e($label) ?></button>
          <?php endforeach; ?>
        </form>
      </section>

      <section class="lx-in" style="--j:3" id="message">
        <h3>Message <?= e(lead_first_name($lead)) ?></h3>
        <form method="post" id="lxMsg">
          <?= csrf_field() ?><input type="hidden" name="leadId" value="<?= (int) $lead['id'] ?>">
          <div class="lx-tpls" role="radiogroup" aria-label="Template">
            <?php foreach ($msgTemplates as $key => $t): ?>
              <label><input type="radio" name="template" value="<?= e($key) ?>" data-text="<?= e(lead_message_render($t['text'], $lead, $key)) ?>" <?= $key === $msgDefault ? 'checked' : '' ?>><span><?= e($t['label']) ?></span></label>
            <?php endforeach; ?>
          </div>
          <textarea name="message" id="lxMessage" rows="7" maxlength="1500" aria-label="Message"><?= e(lead_message_render($msgTemplates[$msgDefault]['text'], $lead, $msgDefault)) ?></textarea>
          <div class="lx-send">
            <button type="submit" name="_action" value="message_whatsapp" formtarget="_blank" class="w" <?= $msgWa ? '' : 'disabled' ?>>Open WhatsApp</button>
            <button type="submit" name="_action" value="message_email" class="e" <?= $msgCanEmail ? 'data-confirm="Email this message to ' . e($lead['email']) . '?"' : 'disabled' ?>>Send email</button>
          </div>
          <p class="lx-hint">
            <?php if (!$msgWa): ?>No phone number on file, so WhatsApp is off. <?php endif; ?>
            <?php if (!$msgCanEmail): ?>This lead unsubscribed or did not agree to email, so email is off. <?php endif; ?>
            <?php if ($msgWa && $msgCanEmail): ?>WhatsApp opens in a new tab with the message ready. Email goes from Obin Academy with a copy to your inbox. Either way the lead moves to Contacted.<?php endif; ?>
          </p>
        </form>
        <?php if ($msgHistory): ?>
          <div class="lx-hist">
            <?php foreach ($msgHistory as $hh): ?>
              <div><span class="ch <?= $hh['channel'] === 'whatsapp' ? 'wa' : '' ?>"><?= $hh['channel'] === 'whatsapp' ? 'WhatsApp' : 'Email' ?></span><span><?= e($msgTemplates[$hh['template']]['label'] ?? 'Message') ?></span><small><?= e($hh['admin_name'] ?: 'Admin') ?> · <?= e(format_date($hh['created_at'])) ?></small></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="lx-in" style="--j:4">
        <h3>Campuses viewed</h3>
        <?php if ($campusesViewed): ?>
          <div class="lx-camps"><?php foreach ($campusesViewed as $c): ?><a href="<?= e(base_url('campus.php?slug=' . $c['slug'])) ?>" target="_blank" rel="noopener"><?= e($c['name']) ?></a><?php endforeach; ?></div>
        <?php else: ?><p class="lx-hint">No campus pages viewed yet.</p><?php endif; ?>
      </section>

      <section class="lx-in" style="--j:5">
        <h3>Notes</h3>
        <form method="post" class="lx-note">
          <?= csrf_field() ?><input type="hidden" name="leadId" value="<?= (int) $lead['id'] ?>"><input type="hidden" name="_action" value="add_note">
          <textarea name="note" rows="2" placeholder="Add a note about <?= e(lead_first_name($lead)) ?>…" required></textarea>
          <button type="submit">Add note</button>
        </form>
        <?php foreach ($notes as $n): ?>
          <div class="lx-noted"><p><?= nl2br(e($n['note'])) ?></p><small><?= e($n['admin_name']) ?> · <?= e(format_date($n['created_at'])) ?></small></div>
        <?php endforeach; ?>
      </section>

      <section class="lx-in" style="--j:6">
        <h3>Browsing history</h3>
        <?php if ($pageHistory): ?>
          <div class="lx-tl">
            <?php foreach ($pageHistory as $p): ?>
              <div class="<?= str_contains($p['path'], 'campus.php?') ? 'c' : '' ?>"><code><?= e($p['path']) ?></code><small><?= e(date('j M, g:i a', strtotime($p['entered_at']))) ?> · <?= $p['time_on_page_seconds'] !== null ? e(format_duration_short((int) $p['time_on_page_seconds'])) : '—' ?> · scrolled <?= (int) $p['scroll_depth_pct'] ?>%</small></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="lx-hint">No browsing history linked to this lead.</p><?php endif; ?>
      </section>
    </div>
  </aside>
<?php endif; ?>
<script type="application/json" id="lx-data"><?= json_encode($lxData, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(versioned_asset('assets/js/admin-leads.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
