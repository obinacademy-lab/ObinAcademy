<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/audit.php';
$user = require_role(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $targetId = (int) post('userId');
    $action = post('_action');
    $target = db_one('SELECT * FROM users WHERE id = ?', [$targetId]);

    if ($target && $action === 'set_role') {
        $role = post('role');
        if (in_array($role, ['LEARNER', 'CREATOR', 'ADMIN'], true)) {
            db_run('UPDATE users SET role = ? WHERE id = ?', [$role, $targetId]);
            log_admin_action((int) $user['id'], $user['name'], 'user.role_changed', 'User', $target['name'], "role -> $role");
        }
    } elseif ($target && $action === 'delete' && (int) $target['id'] !== (int) $user['id']) {
        // The database cascades a user delete into their payments, earnings, courses and plans, so an
        // account with any money or content attached must never be deleted from here: that would erase
        // financial records. Only accounts with no history can go.
        $history = (int) db_one(
            "SELECT (SELECT COUNT(*) FROM payments WHERE user_id = ?) + (SELECT COUNT(*) FROM earnings WHERE creator_id = ?)
                  + (SELECT COUNT(*) FROM courses WHERE creator_id = ?) + (SELECT COUNT(*) FROM enrollments WHERE user_id = ?)
                  + (SELECT COUNT(*) FROM installment_plans WHERE learner_id = ? OR creator_id = ?)
                  + (SELECT COUNT(*) FROM withdrawal_requests WHERE creator_id = ?) AS n",
            [$targetId, $targetId, $targetId, $targetId, $targetId, $targetId, $targetId]
        )['n'];
        if ($history > 0) {
            flash_set('error', $target['name'] . ' has payments, courses or enrolments attached (' . $history . ' records). Deleting them would erase that history, so it was not done. If this account should not sign in, change its role to Learner or contact support.');
        } else {
            db_run('DELETE FROM users WHERE id = ?', [$targetId]);
            log_admin_action((int) $user['id'], $user['name'], 'user.deleted', 'User', $target['name']);
            flash_set('success', 'Deleted ' . $target['name'] . '. They had no payments or courses.');
        }
    }
    redirect('/dashboard/admin/users.php' . (query_param('role') ? '?role=' . urlencode(query_param('role')) : ''));
}

$q = query_param('q');
$roleFilter = strtoupper(query_param('role'));
if (!in_array($roleFilter, ['LEARNER', 'CREATOR', 'ADMIN'], true)) $roleFilter = '';
$perPage = 40;
$page = max(1, (int) query_param('page', '1'));
$where = [];
$params = [];
if ($q) { $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if ($roleFilter) { $where[] = 'role = ?'; $params[] = $roleFilter; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$matching = (int) db_one('SELECT COUNT(*) AS n FROM users' . $whereSql, $params)['n'];
$pages = max(1, (int) ceil($matching / $perPage));
$page = min($page, $pages);
$users = db_all('SELECT * FROM users' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
$usersUrl = function (array $over = []) use ($q, $roleFilter): string {
    $p = array_filter(array_merge(['q' => $q, 'role' => $roleFilter, 'page' => ''], $over), fn($v) => $v !== '' && $v !== null);
    return base_url('dashboard/admin/users.php' . ($p ? '?' . http_build_query($p) : ''));
};

$totalUsers = (int) db_one('SELECT COUNT(*) AS n FROM users')['n'];
$totalLearners = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='LEARNER'")['n'];
$totalCreators = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='CREATOR'")['n'];
$totalAdmins = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='ADMIN'")['n'];

$roleTint = ['ADMIN' => '#f87171', 'CREATOR' => '#fbbf24', 'LEARNER' => '#94a3b8'];
$roleIcon = ['ADMIN' => 'shield', 'CREATOR' => 'sparkle', 'LEARNER' => 'graduation-cap'];

$pageTitle = 'Users — Admin — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3" style="align-items:flex-end;">
  <div>
    <h1 class="h2">Users</h1>
    <p class="muted" style="margin-top:6px;">Everyone with an account on Obin Academy.</p>
  </div>
</div>

<div class="grid sm:grid-2 lg:grid-4" style="margin-top:20px; gap:14px;">
  <div class="mini-stat"><span class="mini-stat-value"><?= $totalUsers ?></span><span class="mini-stat-label">Total Users</span></div>
  <div class="mini-stat" style="--tint:#94a3b8;"><span class="mini-stat-value"><?= $totalLearners ?></span><span class="mini-stat-label">Learners</span></div>
  <div class="mini-stat" style="--tint:#fbbf24;"><span class="mini-stat-value"><?= $totalCreators ?></span><span class="mini-stat-label">Creators</span></div>
  <div class="mini-stat" style="--tint:#f87171;"><span class="mini-stat-value"><?= $totalAdmins ?></span><span class="mini-stat-label">Admins</span></div>
</div>

<div class="row between wrap gap-3" style="margin-top:26px; align-items:center;">
  <form method="get" class="search-pill" style="max-width:340px; margin:0;">
    <?php dash_icon('search'); ?>
    <input type="text" name="q" placeholder="Search by name, email, or phone" value="<?= e($q) ?>">
    <?php if ($roleFilter): ?><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><?php endif; ?>
  </form>
  <p class="small muted"><?= number_format($matching) ?> user<?= $matching === 1 ? '' : 's' ?><?= $q ? ' matching "' . e($q) . '"' : '' ?></p>
</div>
<div class="row gap-2 wrap" style="margin-top:12px;">
  <?php foreach (['' => 'Everyone', 'LEARNER' => 'Learners', 'CREATOR' => 'Creators', 'ADMIN' => 'Admins'] as $val => $label): ?>
    <a href="<?= e($usersUrl(['role' => $val])) ?>" class="btn btn-sm <?= $roleFilter === $val ? 'btn-primary' : 'btn-outline' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$users): ?>
  <div class="card card-pad" style="margin-top:18px; border-style:dashed; text-align:center;">
    <p class="muted">No users match "<?= e($q) ?>".</p>
  </div>
<?php else: ?>
  <div class="activity-feed" style="margin-top:18px;">
    <?php foreach ($users as $u): $isSelf = (int) $u['id'] === (int) $user['id']; ?>
      <div class="list-row">
        <div class="list-row-main">
          <div class="row-avatar" style="--tint:<?= e($roleTint[$u['role']] ?? '#94a3b8') ?>; background:color-mix(in srgb, var(--tint) 20%, transparent); color:var(--tint); flex-shrink:0;"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
          <div style="min-width:0;">
            <div style="font-weight:700; display:flex; align-items:center; gap:6px;">
              <a href="<?= e(base_url('dashboard/admin/user.php?id=' . (int) $u['id'])) ?>" style="color:inherit;" title="Open <?= e($u['name']) ?>"><?= e($u['name']) ?></a>
              <?php if ($isSelf): ?><span class="you-badge">You</span><?php endif; ?>
            </div>
            <div class="small muted" style="margin-top:2px;">
              <a href="mailto:<?= e($u['email']) ?>" style="color:inherit;"><?= e($u['email']) ?></a>
              <?php if (!empty($u['phone'])): $waPhone = preg_replace('/\D/', '', $u['phone']); $waPhone = str_starts_with($waPhone, '256') ? $waPhone : ('256' . ltrim($waPhone, '0')); ?>
                &middot; <?= e($u['phone']) ?> &middot;
                <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--dash-good);">WhatsApp</a>
              <?php endif; ?>
              &middot; joined <?= e(format_date($u['created_at'])) ?>
            </div>
          </div>
        </div>
        <div class="list-row-meta">
          <form method="post" class="role-select-wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="set_role">
            <input type="hidden" name="userId" value="<?= (int) $u['id'] ?>">
            <select name="role" class="role-select" onchange="this.form.submit()" <?= $isSelf ? 'disabled' : '' ?>
              style="--tint:<?= e($roleTint[$u['role']] ?? '#94a3b8') ?>; background-color:color-mix(in srgb, var(--tint) 18%, transparent); color:var(--tint);">
              <?php foreach (['LEARNER', 'CREATOR', 'ADMIN'] as $r): ?>
                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
            <?php dash_icon('chevron-down', 'role-select-chevron'); ?>
          </form>
          <?php if (!$isSelf): ?>
            <form method="post" data-confirm="Delete this user? This cannot be undone.">
              <?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="userId" value="<?= (int) $u['id'] ?>">
              <button class="icon-btn-danger" type="submit" aria-label="Delete user"><?php dash_icon('trash'); ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="ad-pager" aria-label="Pages">
      <?php if ($page > 1): ?><a href="<?= e($usersUrl(['page' => $page - 1])) ?>">Previous</a><?php else: ?><span class="dis">Previous</span><?php endif; ?>
      <span class="on">Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a href="<?= e($usersUrl(['page' => $page + 1])) ?>">Next</a><?php else: ?><span class="dis">Next</span><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<script>document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault();}));</script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
