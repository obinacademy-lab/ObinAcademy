<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/leads.php';
require __DIR__ . '/../../includes/audit.php';
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
    } elseif ($lead && $action === 'add_note') {
        add_lead_note($leadId, (int) $user['id'], (string) post('note'));
        log_admin_action((int) $user['id'], $user['name'], 'lead.note_added', 'Lead', $lead['name']);
    }
    redirect('/dashboard/admin/leads.php' . ($leadId ? "?id=$leadId" : ''));
}

$detailId = (int) query_param('id');
$statusLabels = ['NEW' => 'New', 'CONTACTED' => 'Contacted', 'INTERESTED' => 'Interested', 'ENROLLED' => 'Enrolled', 'CREATOR' => 'Creator', 'LOST' => 'Lost'];
$statusTint = ['NEW' => '#2563eb', 'CONTACTED' => '#8b5cf6', 'INTERESTED' => '#f5b301', 'ENROLLED' => '#10b981', 'CREATOR' => '#ec4899', 'LOST' => '#64748b'];
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

    <div class="dash-page-head" style="margin-top:14px;">
      <div>
        <h1 class="h2"><?= e($lead['name']) ?></h1>
        <p class="muted" style="margin-top:6px;"><?= e($lead['email']) ?><?= $lead['phone'] ? ' &middot; ' . e($lead['phone']) : '' ?></p>
      </div>
      <span class="role-pill" style="--tint:<?= e($statusTint[$lead['status']]) ?>; font-size:12px; padding:6px 14px;"><?= e($statusLabels[$lead['status']]) ?></span>
    </div>

    <div class="grid md:grid-4" style="margin-top:20px;">
      <div class="mini-stat"><span class="mini-stat-value"><?= $lead['lead_type'] === 'creator' ? '🚀' : '🎓' ?></span><span class="mini-stat-label"><?= $lead['lead_type'] === 'creator' ? 'Creator Lead' : 'Learner Lead' ?></span></div>
      <div class="mini-stat"><span class="mini-stat-value"><?= e($sourceLabels[$lead['source']] ?? $lead['source']) ?></span><span class="mini-stat-label">Source</span></div>
      <div class="mini-stat"><span class="mini-stat-value"><?= (int) $lead['visit_count'] ?></span><span class="mini-stat-label">Visits</span></div>
      <div class="mini-stat"><span class="mini-stat-value"><?= e(format_date($lead['first_visit_at'])) ?></span><span class="mini-stat-label">First Seen</span></div>
    </div>

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
    <div class="table-wrap" style="margin-top:14px;">
      <?php if ($pageHistory): ?>
        <table>
          <thead><tr><th>Page</th><th>When</th><th>Time on Page</th><th>Scroll Depth</th></tr></thead>
          <tbody>
            <?php foreach ($pageHistory as $p): ?>
              <tr>
                <td><?= e($p['path']) ?></td>
                <td><?= e(format_date($p['entered_at'])) ?></td>
                <td><?= $p['time_on_page_seconds'] !== null ? (int) $p['time_on_page_seconds'] . 's' : '—' ?></td>
                <td><?= (int) $p['scroll_depth_pct'] ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="card" style="padding:28px; text-align:center; border-style:dashed; color:var(--muted);">No browsing history linked to this lead.</div>
      <?php endif; ?>
    </div>
    <?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
    <?php
    return;
}

// -------------------- List view --------------------
$filters = ['q' => query_param('q'), 'status' => query_param('status'), 'type' => query_param('type'), 'source' => query_param('source')];
$page = max(1, (int) (query_param('page') ?: 1));
$result = get_leads($filters, $page, 25);
$leads = $result['rows'];
$totalLeads = $result['total'];
$totalPages = max(1, (int) ceil($totalLeads / 25));

$statCounts = db_one(
    "SELECT COUNT(*) AS total,
            SUM(status = 'NEW') AS new_count,
            SUM(lead_type = 'creator') AS creator_count,
            SUM(status = 'ENROLLED') AS enrolled_count
     FROM leads"
);

$exportQuery = http_build_query(array_filter($filters));
$pageTitle = 'Leads — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="dash-page-head">
  <div>
    <h1 class="h2">Leads</h1>
    <p class="muted" style="margin-top:6px;">Everyone who's voluntarily shared their details — track, follow up, and convert.</p>
  </div>
  <a href="<?= e(base_url('api/export-leads.php?' . $exportQuery)) ?>" class="btn btn-outline">⬇ Export CSV</a>
</div>

<div class="grid md:grid-4" style="margin-top:20px;">
  <div class="mini-stat"><span class="mini-stat-value"><?= (int) ($statCounts['total'] ?? 0) ?></span><span class="mini-stat-label">Total Leads</span></div>
  <div class="mini-stat" style="--tint:#2563eb;"><span class="mini-stat-value"><?= (int) ($statCounts['new_count'] ?? 0) ?></span><span class="mini-stat-label">New</span></div>
  <div class="mini-stat" style="--tint:#ec4899;"><span class="mini-stat-value"><?= (int) ($statCounts['creator_count'] ?? 0) ?></span><span class="mini-stat-label">Creator Leads</span></div>
  <div class="mini-stat" style="--tint:#10b981;"><span class="mini-stat-value"><?= (int) ($statCounts['enrolled_count'] ?? 0) ?></span><span class="mini-stat-label">Enrolled</span></div>
</div>

<form method="get" class="row gap-2 wrap" style="margin-top:24px; align-items:center;">
  <input type="text" name="q" placeholder="Search by name or email" value="<?= e($filters['q']) ?>" style="max-width:260px;">
  <select name="status">
    <option value="">All Statuses</option>
    <?php foreach ($statusLabels as $val => $label): ?>
      <option value="<?= $val ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type">
    <option value="">All Types</option>
    <option value="learner" <?= $filters['type'] === 'learner' ? 'selected' : '' ?>>Learner</option>
    <option value="creator" <?= $filters['type'] === 'creator' ? 'selected' : '' ?>>Creator</option>
  </select>
  <select name="source">
    <option value="">All Sources</option>
    <?php foreach ($sourceLabels as $val => $label): ?>
      <option value="<?= $val ?>" <?= $filters['source'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Filter</button>
  <?php if (array_filter($filters)): ?><a href="<?= e(base_url('dashboard/admin/leads.php')) ?>" class="small muted">Clear</a><?php endif; ?>
</form>

<div class="table-wrap" style="margin-top:16px;">
  <?php if ($leads): ?>
    <table>
      <thead><tr><th>Name</th><th>Type</th><th>Source</th><th>Status</th><th>Visits</th><th>Last Visit</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($leads as $l): ?>
          <tr>
            <td>
              <div style="font-weight:700;"><?= e($l['name']) ?></div>
              <div class="small muted"><?= e($l['email']) ?></div>
            </td>
            <td><?= $l['lead_type'] === 'creator' ? '🚀 Creator' : '🎓 Learner' ?></td>
            <td class="small"><?= e($sourceLabels[$l['source']] ?? $l['source']) ?></td>
            <td><span class="role-pill" style="--tint:<?= e($statusTint[$l['status']]) ?>;"><?= e($statusLabels[$l['status']]) ?></span></td>
            <td><?= (int) $l['visit_count'] ?></td>
            <td class="small"><?= e(format_date($l['last_visit_at'])) ?></td>
            <td><a href="<?= e(base_url('dashboard/admin/leads.php?id=' . $l['id'])) ?>" class="btn btn-outline btn-sm">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="card" style="padding:36px; text-align:center; border-style:dashed; color:var(--muted);">No leads match these filters yet.</div>
  <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
  <div class="row gap-2" style="margin-top:16px; justify-content:center;">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="<?= e(base_url('dashboard/admin/leads.php?' . http_build_query(array_filter($filters) + ['page' => $p]))) ?>"
         class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
