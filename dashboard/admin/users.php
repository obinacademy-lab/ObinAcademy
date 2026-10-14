<?php
/**
 * Admin: users, as a member directory. A dark console with everyone who has an account, who is in a
 * campus now, learners, admins and sign-ups (with a 14-day pulse); below, every account with its campus
 * membership, last sign-in, role and delete (refused when payments or memberships are attached).
 *
 * Styles: assets/css/admin-campuses.css (console) + assets/css/admin-users.css. Motion: assets/js/admin-pay.js.
 */
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
        if (in_array($role, ['LEARNER', 'ADMIN'], true)) {
            db_run('UPDATE users SET role = ? WHERE id = ?', [$role, $targetId]);
            log_admin_action((int) $user['id'], $user['name'], 'user.role_changed', 'User', $target['name'], "role -> $role");
        }
    } elseif ($target && $action === 'delete' && (int) $target['id'] !== (int) $user['id']) {
        // The database cascades a user delete into their payments and campus memberships, so an account with
        // any money or membership attached must never be deleted from here: that would erase financial records.
        $history = (int) db_one(
            'SELECT (SELECT COUNT(*) FROM payments WHERE user_id = ?) + (SELECT COUNT(*) FROM campus_memberships WHERE user_id = ?) AS n',
            [$targetId, $targetId]
        )['n'];
        if ($history > 0) {
            flash_set('error', $target['name'] . ' has payments or campus memberships attached (' . $history . ' records). Deleting them would erase that history, so it was not done.');
        } else {
            db_run('DELETE FROM users WHERE id = ?', [$targetId]);
            log_admin_action((int) $user['id'], $user['name'], 'user.deleted', 'User', $target['name']);
            flash_set('success', 'Deleted ' . $target['name'] . '. They had no payments or memberships.');
        }
    }
    redirect('/dashboard/admin/users.php' . (query_param('role') ? '?role=' . urlencode(query_param('role')) : ''));
}

$q = query_param('q');
$roleFilter = strtoupper(query_param('role'));
if (!in_array($roleFilter, ['LEARNER', 'ADMIN'], true)) $roleFilter = '';
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
$totalAdmins = (int) db_one("SELECT COUNT(*) AS n FROM users WHERE role='ADMIN'")['n'];
$totalMembers = 0;
try { $totalMembers = (int) db_one('SELECT COUNT(DISTINCT user_id) AS n FROM campus_memberships WHERE access_until > NOW()')['n']; } catch (Throwable $e) {}
$joined30 = (int) db_one('SELECT COUNT(*) AS n FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['n'];

// Sign-ups per day, last 14 days.
$joins = [];
for ($i = 13; $i >= 0; $i--) $joins[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach (db_all('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)') as $r) {
    if (isset($joins[$r['d']])) $joins[$r['d']] = (int) $r['n'];
}
$joinMax = max(1, max($joins));
$joined14 = array_sum($joins);

// For the people on this page: campuses they are in now, and their last sign-in.
$ids = array_map(fn($u) => (int) $u['id'], $users);
$inCampus = $lastSeen = [];
if ($ids) {
    $in = implode(',', $ids);
    try { $inCampus = array_map('intval', array_column(db_all("SELECT user_id, COUNT(*) AS n FROM campus_memberships WHERE user_id IN ($in) AND access_until > NOW() GROUP BY user_id"), 'n', 'user_id')); } catch (Throwable $e) {}
    $lastSeen = array_column(db_all("SELECT user_id, MAX(logged_in_at) AS t FROM login_log WHERE user_id IN ($in) GROUP BY user_id"), 't', 'user_id');
}
$initials = fn(string $name) => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($name)) ?: [''], 0, 2))));

$pageTitle = 'Users — Admin — Obin Academy';
$dashClass = 'dash-ov';
$pageCss = ['assets/css/admin-overview.css', 'assets/css/admin-campuses.css', 'assets/css/admin-users.css'];
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<div class="ov acx ux" data-pp>
  <div class="acx-head acx-slam" style="--i:0">
    <div><h1>Users</h1><p>Everyone with an account on Obin Academy.</p></div>
  </div>

  <section class="lc-console acx-slam" style="--i:1">
    <span class="lc-glow" aria-hidden="true"></span>
    <div>
      <span class="lc-eyebrow"><i></i>Accounts</span>
      <div class="lc-big"><b><span data-scramble="<?= $totalUsers ?>"><?= number_format($totalUsers) ?></span></b><span><?= $totalUsers === 1 ? 'person has' : 'people have' ?><br>an account</span></div>
      <p class="lc-copy"><?= $totalUsers ? round($totalMembers / max(1, $totalLearners) * 100) . '% of learners are in a campus right now.' : 'Accounts appear here as people sign up.' ?></p>
      <div class="lc-stats">
        <div><span>In a campus now</span><b class="lime" data-scramble="<?= $totalMembers ?>"><?= number_format($totalMembers) ?></b></div>
        <div><span>Learners</span><b data-scramble="<?= $totalLearners ?>"><?= number_format($totalLearners) ?></b></div>
        <div><span>Joined · 30 days</span><b data-scramble="<?= $joined30 ?>"><?= number_format($joined30) ?></b></div>
        <div><span>Admins</span><b class="red" data-scramble="<?= $totalAdmins ?>"><?= number_format($totalAdmins) ?></b></div>
      </div>
    </div>
    <div class="lc-rx">
      <div class="lc-rx-h"><b>Sign-ups · last 14 days</b><span><?= $joined14 ?> new account<?= $joined14 === 1 ? '' : 's' ?></span></div>
      <div class="ux-pulse" role="img" aria-label="New accounts per day, last 14 days">
        <?php $k = 0; foreach ($joins as $d => $n): $today = $d === date('Y-m-d'); ?>
          <i class="<?= $n ? '' : 'zero' ?> <?= $today ? 'now' : '' ?>" style="height:<?= $n ? max(6, round($n / $joinMax * 100)) : 3 ?>%;--h:<?= $k++ ?>" data-t="<?= e(date('D j M', strtotime($d))) ?> · <?= $n ?> new"></i>
        <?php endforeach; ?>
      </div>
      <?php if (!$joined14): ?><p class="ux-quiet">No new accounts in the last 14 days</p><?php endif; ?>
      <div class="ux-days" aria-hidden="true"><?php $k = 0; foreach ($joins as $d => $n): ?><span><?= $k++ % 2 === 0 ? e(date('j', strtotime($d))) : '' ?></span><?php endforeach; ?></div>
    </div>
  </section>

  <section class="acx-sec acx-slam" style="--i:2" id="all">
    <div class="lc-sec-h">
      <div><h2>Directory</h2><p><?= number_format($matching) ?> <?= $matching === 1 ? 'account' : 'accounts' ?><?= $q ? ' matching "' . e($q) . '"' : '' ?>, newest first</p></div>
      <div class="ux-tools">
        <form method="get" class="ux-search" action="<?= e(base_url('dashboard/admin/users.php')) ?>">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
          <input type="search" name="q" placeholder="Search by name, email or phone" aria-label="Search users" value="<?= e($q) ?>">
          <?php if ($roleFilter): ?><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><?php endif; ?>
        </form>
        <nav class="lc-chips ux-chips" aria-label="Filter by role">
          <?php foreach (['' => 'Everyone', 'LEARNER' => 'Learners', 'ADMIN' => 'Admins'] as $val => $label): ?>
            <a href="<?= e($usersUrl(['role' => $val])) ?>" class="<?= $roleFilter === $val ? 'on' : '' ?> <?= $val === 'ADMIN' ? 'red' : '' ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
    </div>

    <?php if (!$users): ?>
      <p class="ux-none">No users match<?= $q ? ' "' . e($q) . '"' : '' ?>.</p>
    <?php else: ?>
      <div class="ux-grid ux-th"><span>Person</span><span>Phone</span><span>Campus</span><span>Last sign-in</span><span>Joined</span><span>Role</span><span></span></div>
      <?php foreach ($users as $j => $u): $uid = (int) $u['id']; $isSelf = $uid === (int) $user['id']; $isAdmin = $u['role'] === 'ADMIN'; $wa = whatsapp_number($u['phone'] ?? ''); $nIn = $inCampus[$uid] ?? 0; $seen = $lastSeen[$uid] ?? null; ?>
        <div class="ux-grid lc-row acx-whip <?= $isAdmin ? 'admin' : '' ?>" style="--j:<?= min($j, 14) ?>">
          <a class="ux-who" href="<?= e(base_url('dashboard/admin/user.php?id=' . $uid)) ?>" title="Open <?= e($u['name']) ?>">
            <span class="ux-av"><?= e($initials($u['name'])) ?></span>
            <span><b><?= e($u['name']) ?><?php if ($isSelf): ?><em class="ux-you">You</em><?php endif; ?></b><small><?= e($u['email']) ?></small></span>
          </a>
          <span class="ux-phone"><?php if (!empty($u['phone'])): ?><?= e($u['phone']) ?><?php if ($wa): ?><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp <?= e($u['name']) ?>"><svg class="wa-glyph" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.91-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.08-.13-.27-.2-.57-.35zM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88zm8.41-18.3A11.82 11.82 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89a11.82 11.82 0 0 0-3.48-8.42z"/></svg></a><?php endif; ?><?php else: ?><span class="acx-muted">—</span><?php endif; ?></span>
          <span><?= $nIn ? '<span class="ux-in"><i></i>In ' . $nIn . ' campus' . ($nIn === 1 ? '' : 'es') . '</span>' : '<span class="acx-muted">No campus</span>' ?></span>
          <span class="ux-t"><?= $seen ? e(time_ago($seen)) : '<span class="acx-muted">Never</span>' ?></span>
          <span class="ux-t"><?= e(date('j M Y', strtotime($u['created_at']))) ?></span>
          <form method="post" class="ux-role">
            <?= csrf_field() ?><input type="hidden" name="_action" value="set_role"><input type="hidden" name="userId" value="<?= $uid ?>">
            <select name="role" onchange="this.form.submit()" <?= $isSelf ? 'disabled' : '' ?> class="<?= $isAdmin ? 'admin' : '' ?>" aria-label="Role for <?= e($u['name']) ?>">
              <?php foreach (['LEARNER' => 'Learner', 'ADMIN' => 'Admin'] as $r => $label): ?><option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
          </form>
          <span class="ux-act">
            <?php if (!$isSelf): ?>
              <form method="post" data-confirm="Delete <?= e($u['name']) ?>? This cannot be undone. Accounts with payments or memberships are never deleted.">
                <?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="userId" value="<?= $uid ?>">
                <button class="ux-del" type="submit" aria-label="Delete <?= e($u['name']) ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg></button>
              </form>
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
      <div class="ux-foot">
        <span>Showing <?= number_format(($page - 1) * $perPage + 1) ?> to <?= number_format(min($matching, $page * $perPage)) ?> of <?= number_format($matching) ?></span>
        <?php if ($pages > 1): ?>
          <nav class="ux-pager" aria-label="Pages">
            <?php if ($page > 1): ?><a href="<?= e($usersUrl(['page' => $page - 1])) ?>#all" aria-label="Previous">‹</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= e($usersUrl(['page' => $page + 1])) ?>#all" aria-label="Next">›</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<script src="<?= e(versioned_asset('assets/js/admin-pay.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
