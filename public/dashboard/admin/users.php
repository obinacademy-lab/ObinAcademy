<?php
require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/audit.php';
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
        db_run('DELETE FROM users WHERE id = ?', [$targetId]);
        log_admin_action((int) $user['id'], $user['name'], 'user.deleted', 'User', $target['name']);
    }
    redirect('/dashboard/admin/users.php');
}

$q = query_param('q');
$sql = 'SELECT * FROM users';
$params = [];
if ($q) { $sql .= ' WHERE name LIKE ? OR email LIKE ?'; $params = ["%$q%", "%$q%"]; }
$sql .= ' ORDER BY created_at DESC';
$users = db_all($sql, $params);

$totalUsers = (int) db_one('SELECT COUNT(*) AS n FROM users')['n'];
$totalLearners = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='LEARNER'")['n'];
$totalCreators = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='CREATOR'")['n'];
$totalAdmins = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='ADMIN'")['n'];

$roleTint = ['ADMIN' => '#dc2626', 'CREATOR' => '#b45309', 'LEARNER' => '#64748b'];
$roleIcon = ['ADMIN' => 'shield', 'CREATOR' => 'sparkle', 'LEARNER' => 'graduation-cap'];

$pageTitle = 'Users — Admin — Obin Academy';
require __DIR__ . '/../../../includes/dashboard_header.php';
?>
<div class="row between wrap gap-3" style="align-items:flex-end;">
  <div>
    <h1 class="h2">Users</h1>
    <p class="muted" style="margin-top:6px;">Everyone with an account on Obin Academy.</p>
  </div>
</div>

<div class="grid md:grid-4" style="margin-top:20px; gap:14px;">
  <div class="mini-stat"><span class="mini-stat-value"><?= $totalUsers ?></span><span class="mini-stat-label">Total Users</span></div>
  <div class="mini-stat" style="--tint:#64748b;"><span class="mini-stat-value"><?= $totalLearners ?></span><span class="mini-stat-label">Learners</span></div>
  <div class="mini-stat" style="--tint:#b45309;"><span class="mini-stat-value"><?= $totalCreators ?></span><span class="mini-stat-label">Creators</span></div>
  <div class="mini-stat" style="--tint:#dc2626;"><span class="mini-stat-value"><?= $totalAdmins ?></span><span class="mini-stat-label">Admins</span></div>
</div>

<div class="row between wrap gap-3" style="margin-top:26px; align-items:center;">
  <form method="get" class="search-pill" style="max-width:340px; margin:0;">
    <?php dash_icon('search'); ?>
    <input type="text" name="q" placeholder="Search by name or email" value="<?= e($q) ?>">
  </form>
  <p class="small muted"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?><?= $q ? ' matching "' . e($q) . '"' : '' ?></p>
</div>

<div class="table-wrap" style="margin-top:18px;">
  <table>
    <thead><tr><th>User</th><th>Role</th><th>Joined</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): $isSelf = (int) $u['id'] === (int) $user['id']; ?>
        <tr>
          <td class="cell-nowrap-reset">
            <div class="row gap-2" style="align-items:center;">
              <div class="row-avatar" style="--tint:<?= e($roleTint[$u['role']] ?? '#64748b') ?>; background:color-mix(in srgb, var(--tint) 15%, white); color:var(--tint);"><?= e(mb_substr($u['name'], 0, 1)) ?></div>
              <div style="min-width:0;">
                <div style="font-weight:700; display:flex; align-items:center; gap:6px;">
                  <?= e($u['name']) ?>
                  <?php if ($isSelf): ?><span class="you-badge">You</span><?php endif; ?>
                </div>
                <div class="small muted"><?= e($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <form method="post" class="role-select-wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="_action" value="set_role">
              <input type="hidden" name="userId" value="<?= (int) $u['id'] ?>">
              <select name="role" class="role-select" onchange="this.form.submit()" <?= $isSelf ? 'disabled' : '' ?>
                style="--tint:<?= e($roleTint[$u['role']] ?? '#64748b') ?>; background-color:color-mix(in srgb, var(--tint) 12%, white); color:var(--tint);">
                <?php foreach (['LEARNER', 'CREATOR', 'ADMIN'] as $r): ?>
                  <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
              </select>
              <?php dash_icon('chevron-down', 'role-select-chevron'); ?>
            </form>
          </td>
          <td class="small muted"><?= e(format_date($u['created_at'])) ?></td>
          <td>
            <?php if (!$isSelf): ?>
              <form method="post" data-confirm="Delete this user? This cannot be undone.">
                <?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="userId" value="<?= (int) $u['id'] ?>">
                <button class="icon-btn-danger" type="submit" aria-label="Delete user"><?php dash_icon('trash'); ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="4" class="muted" style="text-align:center; padding:32px 0;">No users match "<?= e($q) ?>".</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<script>document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault();}));</script>
<?php require __DIR__ . '/../../../includes/dashboard_footer.php'; ?>
