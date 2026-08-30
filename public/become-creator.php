<?php
require __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user) {
        redirect('/login.php?redirect=/become-creator.php');
    }
    if (in_array($user['role'], ['CREATOR', 'ADMIN'], true)) {
        $errors[] = 'You already have creator access.';
    } else {
        $expertise = post('expertise');
        $motivation = post('motivation');
        if (strlen($expertise) < 10) $errors[] = "Tell us what you'd like to teach (at least 10 characters).";
        if (strlen($motivation) < 20) $errors[] = 'Tell us a bit more about why you want to teach (at least 20 characters).';

        if (!$errors) {
            $existing = db_one('SELECT id FROM creator_applications WHERE user_id = ?', [$user['id']]);
            if ($existing) {
                db_run("UPDATE creator_applications SET status='PENDING', expertise=?, motivation=?, rejection_reason=NULL, reviewed_at=NULL WHERE id=?", [$expertise, $motivation, $existing['id']]);
            } else {
                db_insert('INSERT INTO creator_applications (user_id, expertise, motivation) VALUES (?, ?, ?)', [$user['id'], $expertise, $motivation]);
            }
            $submitted = true;
        }
    }
}

$myApplication = $user ? db_one('SELECT * FROM creator_applications WHERE user_id = ?', [$user['id']]) : null;

$pageTitle = 'Become a Creator — Obin Academy';
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:640px; text-align:center;">
    <span class="pill">Teach on Obin Academy</span>
    <h1 style="margin-top:14px;">Turn What You Know Into Income</h1>
    <p class="summary" style="margin:14px auto 0;">Share your expertise with thousands of learners across East Africa. Upload video or PDF courses, get paid instantly via mobile money, and keep 90% of every sale.</p>
  </div>
</section>

<div class="container" style="max-width:560px; padding:56px 20px;">
  <?php if ($user && in_array($user['role'], ['CREATOR', 'ADMIN'], true)): ?>
    <div class="card card-pad" style="text-align:center;">
      <p>You already have creator access.</p>
      <a href="<?= e(base_url('dashboard/creator/index.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Go to Creator Dashboard</a>
    </div>
  <?php elseif ($submitted || ($myApplication && $myApplication['status'] === 'PENDING')): ?>
    <div class="alert alert-success">Your application has been submitted and is pending review. We'll email you once it's decided.</div>
  <?php else: ?>
    <?php if ($myApplication && $myApplication['status'] === 'REJECTED'): ?>
      <div class="alert alert-error">Your last application was not approved<?= $myApplication['rejection_reason'] ? ': ' . e($myApplication['rejection_reason']) : '.' ?> You're welcome to apply again below.</div>
    <?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

    <?php if (!$user): ?>
      <div class="card card-pad" style="text-align:center;">
        <p class="muted">Log in or create an account first to apply.</p>
        <div class="row gap-2 center" style="margin-top:14px;">
          <a href="<?= e(base_url('login.php?redirect=/become-creator.php')) ?>" class="btn btn-outline">Log In</a>
          <a href="<?= e(base_url('signup.php')) ?>" class="btn btn-primary">Sign Up</a>
        </div>
      </div>
    <?php else: ?>
      <form method="post" class="card card-pad">
        <?= csrf_field() ?>
        <div class="field"><label for="expertise">What would you like to teach?</label><textarea id="expertise" name="expertise" rows="3" required placeholder="e.g. Personal finance, digital marketing, farming techniques..."><?= e($myApplication['expertise'] ?? '') ?></textarea></div>
        <div class="field"><label for="motivation">Why do you want to teach on Obin Academy?</label><textarea id="motivation" name="motivation" rows="4" required placeholder="Tell us about your experience and what makes you a great teacher."><?= e($myApplication['motivation'] ?? '') ?></textarea></div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Submit Application</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
