<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role(['CREATOR', 'ADMIN']);

// One row per enrollment (matches privacy.php's "enrollment records"
// framing) — a learner in two of this creator's courses appears twice,
// which is correct, not a duplicate to collapse. Guest checkouts have no
// user_id/phone, only a name+email captured at checkout time.
$learners = db_all(
    "SELECT COALESCE(u.name, e.guest_name) AS learner_name,
            COALESCE(u.email, e.guest_email) AS learner_email,
            u.phone AS learner_phone, u.avatar_url,
            c.title AS course_title, e.enrolled_at
     FROM enrollments e
     JOIN courses c ON c.id = e.course_id
     LEFT JOIN users u ON u.id = e.user_id
     WHERE c.creator_id = ?
     ORDER BY e.enrolled_at DESC",
    [$user['id']]
);

$pageTitle = 'Learners — Obin Academy';
require __DIR__ . '/../../includes/dashboard_header.php';
?>
<h1 class="h2">Learners</h1>
<p class="muted" style="margin-top:6px;">Everyone enrolled in your courses — reach out by email or WhatsApp about the courses they've bought from you.</p>

<?php if (!$learners): ?>
  <div class="card card-pad" style="margin-top:24px; text-align:center; border-style:dashed;">
    <p class="muted">No one has enrolled in your courses yet.</p>
  </div>
<?php else: ?>
  <div class="table-wrap" style="margin-top:24px;">
    <table>
      <thead><tr><th>Learner</th><th>Email</th><th>WhatsApp</th><th>Course</th><th>Enrolled</th></tr></thead>
      <tbody>
        <?php foreach ($learners as $l): ?>
          <tr>
            <td>
              <div class="row gap-2" style="align-items:center;">
                <div class="avatar-circle" style="width:28px; height:28px; font-size:11px;">
                  <?php if (!empty($l['avatar_url'])): ?><img src="<?= e(asset_src($l['avatar_url'])) ?>" alt="">
                  <?php else: ?><?= e(mb_substr($l['learner_name'], 0, 1)) ?><?php endif; ?>
                </div>
                <span style="font-weight:600;"><?= e($l['learner_name']) ?></span>
              </div>
            </td>
            <td><a href="mailto:<?= e($l['learner_email']) ?>"><?= e($l['learner_email']) ?></a></td>
            <td>
              <?php if (!empty($l['learner_phone'])): $digits = preg_replace('/[^0-9]/', '', $l['learner_phone']); ?>
                <a href="https://wa.me/<?= e($digits) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">💬 <?= e($l['learner_phone']) ?></a>
              <?php else: ?>
                <span class="muted small">—</span>
              <?php endif; ?>
            </td>
            <td><?= e($l['course_title']) ?></td>
            <td class="small muted"><?= e(format_date($l['enrolled_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/dashboard_footer.php'; ?>
