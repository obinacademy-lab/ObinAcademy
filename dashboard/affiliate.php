<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_affiliate();
$affiliate = $user['affiliate'];

$summary = get_affiliate_summary((int) $affiliate['id']);
$recentEarnings = get_affiliate_recent_earnings((int) $affiliate['id'], 20);

$shareUrl = base_url('') . '?aff=' . $affiliate['ref_code'];

$pageTitle = 'Affiliate Dashboard — Obin Academy';
require __DIR__ . '/../includes/dashboard_header.php';
?>
<h1 class="h2">Affiliate Dashboard</h1>
<p class="muted" style="margin-top:6px;">Share your link — earn 2% commission on any course, from any creator, that anyone buys through it.</p>

<div class="card card-pad row between wrap gap-3" style="margin-top:24px; background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 14%, var(--dash-panel)), var(--dash-panel)); border-color: var(--dash-border);">
  <div style="min-width:240px; flex:1;">
    <h3 class="small" style="font-weight:700;">Your Affiliate Link</h3>
    <p class="small" style="margin-top:6px; word-break:break-all; font-weight:600;"><?= e($shareUrl) ?></p>
  </div>
  <?php render_share_button($shareUrl, 'Obin Academy', 'Share Your Link', 'light'); ?>
</div>

<div class="grid md:grid-3" style="margin-top:24px;">
  <div class="stat-card" data-hoverable="true" style="--hover-color:#f5b301;">
    <div class="icon"><?php dash_icon('banknote'); ?></div>
    <div class="value"><?= e(format_money($summary['earned'])) ?></div><div class="label">Total Earned</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#34d399;">
    <div class="icon"><?php dash_icon('users'); ?></div>
    <div class="value"><?= $summary['sales_count'] ?></div><div class="label">Referred Sales</div>
  </div>
  <div class="stat-card" data-hoverable="true" style="--hover-color:#60a5fa;">
    <div class="icon"><?php dash_icon('tag'); ?></div>
    <div class="value">2%</div><div class="label">Commission Rate</div>
  </div>
</div>

<h2 class="h3" style="margin-top:36px;">Recent Commissions</h2>
<div class="table-wrap" style="margin-top:14px;">
  <table>
    <thead><tr><th>Course</th><th>Commission</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($recentEarnings as $e): ?>
        <tr>
          <td><?= e($e['course_title']) ?></td>
          <td style="font-weight:700;"><?= e(format_money((float) $e['amount'])) ?></td>
          <td><?= e(format_date($e['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentEarnings): ?><tr><td colspan="3" class="muted">No commissions yet — share your link to get started.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
