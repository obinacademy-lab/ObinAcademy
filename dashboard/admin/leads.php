<?php
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
            send_lead_message_email($lead, $templates[$tplKey]['subject'], $text);
            log_lead_contact($leadId, (int) $user['id'], 'email', $tplKey);
            log_admin_action((int) $user['id'], $user['name'], 'lead.contacted', 'Lead', $lead['name'], 'email');
            flash_set('success', 'Email sent to ' . $lead['email'] . '. The lead is now marked Contacted.');
        }
        redirect('/dashboard/admin/leads.php?id=' . $leadId);
    } elseif ($lead && $action === 'add_note') {
        add_lead_note($leadId, (int) $user['id'], (string) post('note'));
        log_admin_action((int) $user['id'], $user['name'], 'lead.note_added', 'Lead', $lead['name']);
    }
    redirect('/dashboard/admin/leads.php' . ($leadId ? "?id=$leadId" : ''));
}

$detailId = (int) query_param('id');
$statusLabels = ['NEW' => 'New', 'CONTACTED' => 'Contacted', 'INTERESTED' => 'Interested', 'ENROLLED' => 'Enrolled', 'CREATOR' => 'Creator', 'LOST' => 'Lost'];
$statusTint = ['NEW' => '#0b00ff', 'CONTACTED' => '#8b5cf6', 'INTERESTED' => '#f5b301', 'ENROLLED' => '#10b981', 'CREATOR' => '#ec4899', 'LOST' => '#94a3b8'];
$sourceLabels = ['google' => 'Google / Search', 'social' => 'Social Media', 'direct' => 'Direct / Shared Link', 'other' => 'Other'];

if ($detailId) {
    // -------------------- Detail view --------------------
    $lead = get_lead_by_id($detailId);
    if (!$lead) { flash_set('error', 'Lead not found.'); redirect('/dashboard/admin/leads.php'); }
    $notes = get_lead_notes($detailId);
    $pageHistory = get_lead_page_history($lead['visitor_id']);
    $coursesViewed = get_lead_courses_viewed($lead['visitor_id']);

    $pageTitle = e($lead['name']) . ' — Leads — Admin — Obin Academy';
    require __DIR__ . '/../../includes/dashboard_header.php';
    ?>
    <a href="<?= e(base_url('dashboard/admin/leads.php')) ?>" class="muted small" style="display:inline-flex; align-items:center; gap:6px;">&larr; Back to Leads</a>

    <?php
      $location = trim(($lead['city'] ?? '') . ($lead['city'] && $lead['country'] ? ', ' : '') . ($lead['country'] ? country_name($lead['country']) : ''), ' ,');
    ?>
    <div class="dash-page-head" style="margin-top:14px;">
      <div>
        <h1 class="h2"><?= e($lead['name']) ?></h1>
        <p class="muted" style="margin-top:6px;">
          <?= e($lead['email']) ?><?= $lead['phone'] ? ' &middot; ' . e($lead['phone']) : '' ?><?= $location ? ' &middot; 📍 ' . e($location) : '' ?>
        </p>
      </div>
      <span class="role-pill" style="--tint:<?= e($statusTint[$lead['status']]) ?>; font-size:12px; padding:6px 14px;"><?= e($statusLabels[$lead['status']]) ?></span>
    </div>

    <div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px;">
      <div class="mini-stat"><span class="mini-stat-value"><?= $lead['lead_type'] === 'creator' ? '🚀' : '🎓' ?></span><span class="mini-stat-label"><?= $lead['lead_type'] === 'creator' ? 'Creator Lead' : 'Learner Lead' ?></span></div>
      <div class="mini-stat"><span class="mini-stat-value"><?= e($sourceLabels[$lead['source']] ?? $lead['source']) ?></span><span class="mini-stat-label">Source</span></div>
      <div class="mini-stat"><span class="mini-stat-value"><?= (int) $lead['visit_count'] ?></span><span class="mini-stat-label">Visits</span></div>
      <div class="mini-stat"><span class="mini-stat-value"><?= e(format_date($lead['first_visit_at'])) ?></span><span class="mini-stat-label">First Seen</span></div>
    </div>

    <?php
      $msgTemplates = lead_message_templates();
      $msgDefault = lead_message_default_for($lead);
      $msgWa = whatsapp_number($lead['phone']);
      $msgCanEmail = $lead['consent_marketing'] && !$lead['unsubscribed'];
      $msgHistory = get_lead_contacts($detailId);
    ?>
    <div class="ld-panel ot-card" id="message" style="margin-top:24px;">
      <div class="ot-head">
        <div><h2>Message <?= e(lead_first_name($lead)) ?></h2><p class="sub">Pick a template, change the words if you like, then send it. The lead moves to Contacted for you.</p></div>
      </div>
      <form method="post" class="ot-form" id="otForm">
        <?= csrf_field() ?>
        <input type="hidden" name="leadId" value="<?= $detailId ?>">
        <div class="ot-tpls" role="radiogroup" aria-label="Template">
          <?php foreach ($msgTemplates as $key => $t): ?>
            <label class="ot-tpl"><input type="radio" name="template" value="<?= e($key) ?>" data-text="<?= e(lead_message_render($t['text'], $lead, $key)) ?>" <?= $key === $msgDefault ? 'checked' : '' ?>><span><?= e($t['label']) ?></span></label>
          <?php endforeach; ?>
        </div>
        <textarea name="message" id="otMessage" rows="7" maxlength="1500" aria-label="Message"><?= e(lead_message_render($msgTemplates[$msgDefault]['text'], $lead, $msgDefault)) ?></textarea>
        <div class="ot-row">
          <button type="submit" name="_action" value="message_whatsapp" formtarget="_blank" class="ot-btn wa big" <?= $msgWa ? '' : 'disabled' ?>>Open WhatsApp</button>
          <button type="submit" name="_action" value="message_email" class="ot-btn big" <?= $msgCanEmail ? 'data-confirm="Email this message to ' . e($lead['email']) . '?"' : 'disabled' ?>>Send email</button>
          <span class="small muted">
            <?php if (!$msgWa): ?>No phone number on file, so WhatsApp is off.<?php endif; ?>
            <?php if (!$msgCanEmail): ?><?= $msgWa ? '' : ' ' ?>This lead unsubscribed, so email is off.<?php endif; ?>
            <?php if ($msgWa && $msgCanEmail): ?>WhatsApp opens in a new tab with the message ready. Email goes from Obin Academy.<?php endif; ?>
          </span>
        </div>
      </form>
      <?php if ($msgHistory): ?>
        <div class="ot-history">
          <h3>Already contacted</h3>
          <?php foreach ($msgHistory as $h): ?>
            <div class="ot-hrow"><span class="ot-chan <?= $h['channel'] === 'whatsapp' ? 'wa' : '' ?>"><?= $h['channel'] === 'whatsapp' ? 'WhatsApp' : 'Email' ?></span><span><?= e($msgTemplates[$h['template']]['label'] ?? 'Message') ?></span><span class="muted"><?= e($h['admin_name'] ?: 'Admin') ?> &middot; <?= e(format_date($h['created_at'])) ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <script>
      (function () {
        var box = document.getElementById('otMessage');
        document.querySelectorAll('#otForm input[name=template]').forEach(function (r) {
          r.addEventListener('change', function () { if (r.checked) box.value = r.dataset.text; });
        });
        document.querySelectorAll('#otForm [data-confirm]').forEach(function (b) {
          b.addEventListener('click', function (e) { if (!confirm(b.dataset.confirm)) e.preventDefault(); });
        });
      })();
    </script>

    <div class="growth-layout" style="margin-top:24px;">
      <div class="chart-card">
        <h2 class="h3">Status &amp; Actions</h2>
        <form method="post" class="row gap-2" style="margin-top:14px; align-items:center;">
          <?= csrf_field() ?>
          <input type="hidden" name="leadId" value="<?= $detailId ?>">
          <input type="hidden" name="_action" value="set_status">
          <select name="status" onchange="this.form.submit()">
            <?php foreach ($statusLabels as $val => $label): ?>
              <option value="<?= $val ?>" <?= $lead['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <div class="row gap-2" style="margin-top:12px;">
          <a href="mailto:<?= e($lead['email']) ?>" class="btn btn-outline btn-sm">✉ Email</a>
          <?php if ($lead['phone']): ?>
            <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $lead['phone'])) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">📱 WhatsApp</a>
          <?php endif; ?>
        </div>

        <h2 class="h3" style="margin-top:28px;">Notes</h2>
        <form method="post" style="margin-top:12px;">
          <?= csrf_field() ?>
          <input type="hidden" name="leadId" value="<?= $detailId ?>">
          <input type="hidden" name="_action" value="add_note">
          <textarea name="note" rows="2" placeholder="Add a note about this lead…" required style="width:100%; resize:vertical;"></textarea>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px;">Add Note</button>
        </form>
        <div style="margin-top:16px; display:flex; flex-direction:column; gap:12px;">
          <?php foreach ($notes as $n): ?>
            <div style="border-left:2px solid var(--border); padding-left:12px;">
              <p class="small" style="line-height:1.5;"><?= nl2br(e($n['note'])) ?></p>
              <p class="muted" style="font-size:11px; margin-top:4px;"><?= e($n['admin_name']) ?> &middot; <?= e(format_date($n['created_at'])) ?></p>
            </div>
          <?php endforeach; ?>
          <?php if (!$notes): ?><p class="muted small">No notes yet.</p><?php endif; ?>
        </div>
      </div>

      <div class="growth-side">
        <div class="chart-card">
          <h2 class="h3">Courses Viewed</h2>
          <div style="margin-top:12px; display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($coursesViewed as $c): ?>
              <a href="<?= e(base_url('courses/view.php?slug=' . $c['slug'])) ?>" target="_blank" class="small" style="display:block;"><?= e($c['title']) ?></a>
            <?php endforeach; ?>
            <?php if (!$coursesViewed): ?><p class="muted small">No course views recorded for this visitor.</p><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <h3 class="dash-section-label" style="margin-top:28px;">Browsing History</h3>
    <?php if ($pageHistory): ?>
      <div class="activity-feed" style="margin-top:14px;">
        <?php foreach ($pageHistory as $p): ?>
          <div class="list-row">
            <div class="list-row-main"><span class="small" style="font-weight:600; word-break:break-all;"><?= e($p['path']) ?></span></div>
            <div class="list-row-meta small muted"><?= e(format_date($p['entered_at'])) ?> &middot; <?= $p['time_on_page_seconds'] !== null ? (int) $p['time_on_page_seconds'] . 's' : '—' ?> &middot; <?= (int) $p['scroll_depth_pct'] ?>%</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="card" style="margin-top:14px; padding:28px; text-align:center; border-style:dashed; color:var(--muted);">No browsing history linked to this lead.</div>
    <?php endif; ?>
    <?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
    <?php
    return;
}

// -------------------- List view --------------------
$filters = ['q' => query_param('q'), 'status' => query_param('status'), 'type' => query_param('type'), 'source' => query_param('source')];
$page = max(1, (int) (query_param('page') ?: 1));
$perPage = 25;
$result = get_leads($filters, $page, $perPage);
$leads = $result['rows'];
$totalLeads = $result['total'];
$totalPages = max(1, (int) ceil($totalLeads / $perPage));

$statCounts = db_one(
    "SELECT COUNT(*) AS total,
            SUM(status = 'NEW') AS new_count,
            SUM(lead_type = 'creator') AS creator_count,
            SUM(status = 'ENROLLED') AS enrolled_count
     FROM leads"
);
$allLeads = (int) ($statCounts['total'] ?? 0);
$newCount = (int) ($statCounts['new_count'] ?? 0);
$creatorCount = (int) ($statCounts['creator_count'] ?? 0);
$enrolledCount = (int) ($statCounts['enrolled_count'] ?? 0);
$pctOf = fn(int $n): int => $allLeads ? (int) round($n / $allLeads * 100) : 0;

$leadsSeries = get_leads_daily_series(30);
$dailyCounts = array_column($leadsSeries, 'count');
$bestDay = $dailyCounts ? max($dailyCounts) : 0;
$last7 = array_sum(array_slice($dailyCounts, -7));
$prev7 = array_sum(array_slice($dailyCounts, -14, 7));
$trendPct = $prev7 > 0 ? round((($last7 - $prev7) / $prev7) * 100) : null;

// The chart: a y-axis that rounds up to a multiple of 4 so the four gridlines carry whole numbers.
$chartW = 640; $chartH = 190; $padL = 0; $padTop = 12; $padBottom = 6;
$yTop = max(4, (int) (ceil($bestDay / 4) * 4));
$n = count($leadsSeries);
$xStep = $n > 1 ? ($chartW - 8) / ($n - 1) : 0;
$points = [];
foreach ($leadsSeries as $i => $row) {
    $points[] = [$i * $xStep, $padTop + ($chartH - $padTop - $padBottom) * (1 - $row['count'] / $yTop)];
}
$linePath = smooth_svg_path($points);
$areaPath = $points ? $linePath . sprintf(' L%.2f,%d L0,%d Z', end($points)[0], $chartH - $padBottom, $chartH - $padBottom) : '';
$labelIdxs = $n > 1 ? [0, (int) round(($n - 1) * 0.2), (int) round(($n - 1) * 0.4), (int) round(($n - 1) * 0.6), (int) round(($n - 1) * 0.8), $n - 1] : [0];

$statusBreakdown = get_lead_status_breakdown();
$sourceBreakdown = get_lead_source_breakdown();

$exportQuery = http_build_query(array_filter($filters));
$leadsUrl = fn(array $over = []): string => base_url('dashboard/admin/leads.php' . (($p = array_filter(array_merge($filters, ['page' => ''], $over), fn($v) => $v !== '' && $v !== null)) ? '?' . http_build_query($p) : ''));

$pageTitle = 'Leads — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Leads</h1>
    <p class="muted" style="margin-top:6px;">Everyone who has shared their details. Follow up, track, and convert.</p>
  </div>
  <a href="<?= e(base_url('api/export-leads.php?' . $exportQuery)) ?>" class="ld-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>Export CSV</a>
</div>

<div class="ld-panel ld-strip" style="margin-top:20px;">
  <div><b><?= number_format($allLeads) ?></b><span>Total leads</span></div>
  <div class="<?= $newCount > 0 ? 'warn' : '' ?>"><b><?= number_format($newCount) ?></b><span>Not yet contacted</span><small><?= $pctOf($newCount) ?>% of all leads</small></div>
  <div><b><?= number_format($creatorCount) ?></b><span>Creator leads</span><small><?= $pctOf($creatorCount) ?>% of all leads</small></div>
  <div><b><?= number_format($enrolledCount) ?></b><span>Became students</span><small><?= $pctOf($enrolledCount) ?>% conversion</small></div>
</div>

<?php if ($newCount > 0): ?>
  <div class="ld-alert" role="note" style="margin-top:16px;">
    <?php dash_icon('clock'); ?>
    <span><strong><?= $newCount === $allLeads ? 'None of your ' . number_format($allLeads) . ' leads have been contacted yet.' : number_format($newCount) . ' lead' . ($newCount === 1 ? ' has' : 's have') . ' not been contacted yet.' ?></strong> Leads that hear back within a day are far more likely to enrol.</span>
    <a href="<?= e(base_url('dashboard/admin/leads.php?status=NEW')) ?>">Show new leads</a>
  </div>
<?php endif; ?>

<div class="ld-sec" style="margin-top:28px;"><h2>Pipeline</h2><p>Where every lead stands today</p></div>
<div class="ld-panel ld-pipe">
  <?php foreach ($statusLabels as $key => $label): $c = (int) ($statusBreakdown[$key] ?? 0); ?>
    <a href="<?= e(base_url('dashboard/admin/leads.php?status=' . $key)) ?>" style="--c:<?= e($statusTint[$key]) ?>;">
      <div class="lb"><i></i><?= e($label) ?></div>
      <b><?= number_format($c) ?></b>
      <div class="bar"><u style="width:<?= $pctOf($c) ?>%"></u></div>
      <small><?= $pctOf($c) ?>% of leads</small>
    </a>
  <?php endforeach; ?>
</div>

<div class="ld-two" style="margin-top:20px;">
  <div class="ld-panel ld-card">
    <div class="ld-chead">
      <div>
        <h2>Leads captured</h2>
        <p class="sub">New leads per day, last 30 days</p>
      </div>
      <?php if ($trendPct !== null): ?>
        <span class="ld-trend <?= $trendPct >= 0 ? 'up' : 'down' ?>"><?= $trendPct >= 0 ? '+' : '' ?><?= $trendPct ?>% vs previous week</span>
      <?php endif; ?>
    </div>
    <div class="ld-chart chart-wrap">
      <div class="ld-yaxis" aria-hidden="true">
        <?php for ($g = 4; $g >= 0; $g--): ?><span><?= (int) ($yTop / 4 * $g) ?></span><?php endfor; ?>
      </div>
      <svg viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none" role="img" aria-label="Leads captured per day">
        <defs>
          <linearGradient id="ldAreaFill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.22"/>
            <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <?php for ($g = 0; $g <= 4; $g++): $gy = $padTop + ($chartH - $padTop - $padBottom) * ($g / 4); ?>
          <line x1="0" y1="<?= round($gy, 1) ?>" x2="<?= $chartW ?>" y2="<?= round($gy, 1) ?>" class="ld-gl"></line>
        <?php endfor; ?>
        <?php if ($points): ?>
          <path d="<?= e($areaPath) ?>" fill="url(#ldAreaFill)"></path>
          <path d="<?= e($linePath) ?>" class="ld-line" vector-effect="non-scaling-stroke"></path>
        <?php endif; ?>
        <?php foreach ($points as $i => [$px, $py]): ?>
          <circle cx="<?= round($px, 1) ?>" cy="<?= round($py, 1) ?>" class="chart-point" tabindex="0"
            data-chart-label="<?= e(format_date($leadsSeries[$i]['date'] . ' 00:00:00')) ?>"
            data-chart-value="<?= (int) $leadsSeries[$i]['count'] ?> lead<?= $leadsSeries[$i]['count'] === 1 ? '' : 's' ?>"></circle>
        <?php endforeach; ?>
        <?php if ($points): [$lx, $ly] = end($points); ?>
          <circle cx="<?= round($lx, 1) ?>" cy="<?= round($ly, 1) ?>" r="5" class="chart-end-dot chart-end-dot-blue" style="pointer-events:none;"></circle>
        <?php endif; ?>
      </svg>
    </div>
    <div class="ld-xl">
      <?php foreach ($labelIdxs as $idx): if (!isset($leadsSeries[$idx])) continue; ?>
        <span><?= e(date('M j', strtotime($leadsSeries[$idx]['date']))) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="ld-panel ld-card">
    <h2>Where leads come from</h2>
    <p class="sub">Share of all <?= number_format($allLeads) ?> lead<?= $allLeads === 1 ? '' : 's' ?></p>
    <div class="ld-src">
      <?php foreach ($sourceLabels as $key => $label): $c = (int) ($sourceBreakdown[$key] ?? 0); ?>
        <div class="r"><span><?= e($label) ?></span><em><?= number_format($c) ?> &middot; <?= $pctOf($c) ?>%</em><div class="bar"><u style="width:<?= $pctOf($c) ?>%"></u></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="ld-sec" style="margin-top:28px;"><h2>All leads</h2></div>
<div class="ld-panel" style="overflow:hidden;">
  <form method="get" class="ld-tools">
    <label class="ld-field ld-grow"><?php dash_icon('search'); ?><input type="search" name="q" placeholder="Search by name or email" aria-label="Search leads" value="<?= e($filters['q']) ?>"></label>
    <label class="ld-field"><select name="status" aria-label="Status" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach ($statusLabels as $val => $label): ?><option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select></label>
    <label class="ld-field"><select name="type" aria-label="Type" onchange="this.form.submit()">
      <option value="">All types</option>
      <option value="learner" <?= $filters['type'] === 'learner' ? 'selected' : '' ?>>Learner</option>
      <option value="creator" <?= $filters['type'] === 'creator' ? 'selected' : '' ?>>Creator</option>
    </select></label>
    <label class="ld-field"><select name="source" aria-label="Source" onchange="this.form.submit()">
      <option value="">All sources</option>
      <?php foreach ($sourceLabels as $val => $label): ?><option value="<?= $val ?>" <?= $filters['source'] === $val ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select></label>
    <?php if (array_filter($filters)): ?><a href="<?= e(base_url('dashboard/admin/leads.php')) ?>" class="small muted">Clear</a><?php endif; ?>
    <span class="ld-count"><?= number_format($totalLeads) ?> lead<?= $totalLeads === 1 ? '' : 's' ?></span>
  </form>

  <?php if ($leads): ?>
    <div class="ld-grid ld-th"><span>Lead</span><span>Type</span><span>Source</span><span>Activity</span><span>Status</span><span></span></div>
    <?php foreach ($leads as $l): $isCreator = $l['lead_type'] === 'creator'; $viewUrl = base_url('dashboard/admin/leads.php?id=' . (int) $l['id']); ?>
      <div class="ld-grid ld-row" style="--c:<?= e($statusTint[$l['status']] ?? '#94a3b8') ?>;">
        <div class="ld-who">
          <div class="ld-av"><?= e(mb_strtoupper(mb_substr($l['name'], 0, 1))) ?></div>
          <div style="min-width:0;"><a href="<?= e($viewUrl) ?>" class="nm"><?= e($l['name']) ?></a><small><?= e($l['email']) ?></small></div>
        </div>
        <span class="ld-type <?= $isCreator ? 'creator' : '' ?>"><?= $isCreator ? 'Creator' : 'Learner' ?></span>
        <span class="ld-srcc"><?= e($sourceLabels[$l['source']] ?? $l['source']) ?></span>
        <div class="ld-seen"><b><?= (int) $l['visit_count'] ?> visit<?= (int) $l['visit_count'] === 1 ? '' : 's' ?></b><?= e(format_date($l['last_visit_at'])) ?></div>
        <span class="ld-pill"><i></i><?= e($statusLabels[$l['status']] ?? $l['status']) ?></span>
        <a href="<?= e($viewUrl) ?>#message" class="ld-view" title="Message <?= e(lead_first_name($l)) ?>">Message</a>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="ld-none">No leads match these filters yet.</div>
  <?php endif; ?>

  <div class="ld-foot">
    <span><?= $totalLeads ? 'Showing ' . number_format(($page - 1) * $perPage + 1) . ' to ' . number_format(min($totalLeads, $page * $perPage)) . ' of ' . number_format($totalLeads) : 'No leads to show' ?></span>
    <?php if ($totalPages > 1):
        $shown = array_unique(array_filter([1, $page - 1, $page, $page + 1, $totalPages], fn($p) => $p >= 1 && $p <= $totalPages));
        sort($shown); $prevN = 0; ?>
      <nav class="ld-pager" aria-label="Pages">
        <?php if ($page > 1): ?><a href="<?= e($leadsUrl(['page' => $page - 1])) ?>">Previous</a><?php else: ?><a class="dis" aria-disabled="true">Previous</a><?php endif; ?>
        <?php foreach ($shown as $p): if ($prevN && $p - $prevN > 1): ?><span class="gap">&hellip;</span><?php endif; $prevN = $p; ?>
          <a href="<?= e($leadsUrl(['page' => $p])) ?>" class="<?= $p === $page ? 'on' : '' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
        <?php endforeach; ?>
        <?php if ($page < $totalPages): ?><a href="<?= e($leadsUrl(['page' => $page + 1])) ?>">Next</a><?php else: ?><a class="dis" aria-disabled="true">Next</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
