<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user) {
        redirect('/login.php?redirect=/become-affiliate.php');
    }
    $existingAffiliate = get_affiliate_by_user_id((int) $user['id']);
    if ($existingAffiliate && $existingAffiliate['status'] === 'ACTIVE') {
        $errors[] = "You're already an affiliate partner.";
    } else {
        $motivation = post('motivation');
        if (strlen($motivation) < 20) $errors[] = 'Tell us a bit more about how you plan to share Obin Academy (at least 20 characters).';

        if (!$errors) {
            $existing = db_one('SELECT id FROM affiliate_applications WHERE user_id = ?', [$user['id']]);
            if ($existing) {
                db_run("UPDATE affiliate_applications SET status='PENDING', motivation=?, rejection_reason=NULL, reviewed_at=NULL WHERE id=?", [$motivation, $existing['id']]);
            } else {
                db_insert('INSERT INTO affiliate_applications (user_id, motivation) VALUES (?, ?)', [$user['id'], $motivation]);
            }
            $submitted = true;
        }
    }
}

$myAffiliate = $user ? get_affiliate_by_user_id((int) $user['id']) : null;
$myApplication = $user ? db_one('SELECT * FROM affiliate_applications WHERE user_id = ?', [$user['id']]) : null;

// Real social proof, not a marketing round number — same "actually paid
// out" definition used on the admin financial pages (approved withdrawals,
// not gross accrued earnings).
$paidToAffiliates = (float) (db_one("SELECT COALESCE(SUM(amount),0) AS n FROM withdrawal_requests WHERE status='APPROVED' AND payee_type='AFFILIATE'")['n'] ?? 0);

$pageTitle = 'Become an Affiliate Partner — Obin Academy';
$pageDescription = 'Earn 2% commission on every course purchase you refer through your own affiliate link, on any course from any creator on Obin Academy.';
require __DIR__ . '/includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:640px; text-align:center;">
    <span class="pill">Earn by Sharing Obin Academy</span>
    <h1 style="margin-top:14px;">Turn Your Network Into Income</h1>
    <p class="summary" style="margin:14px auto 0;">Apply to become an affiliate partner. Once approved, you get your own link to share — earn 2% commission whenever someone buys any course, from any creator, through your link.</p>

    <?php if ($paidToAffiliates > 0): ?>
      <div class="hero-trust-stat">
        <?php dash_icon('banknote'); ?>
        <span data-count-up data-count-value="<?= (int) round($paidToAffiliates) ?>" data-count-prefix="UGX " data-count-grouped data-count-suffix="">UGX 0</span> already paid out to affiliates
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="container" style="max-width:560px; padding:56px 20px;">
  <?php if ($myAffiliate && $myAffiliate['status'] === 'ACTIVE'): ?>
    <div class="card card-pad" style="text-align:center;">
      <p>You're already an affiliate partner.</p>
      <a href="<?= e(base_url('dashboard/affiliate.php')) ?>" class="btn btn-primary" style="margin-top:14px;">Go to Affiliate Dashboard</a>
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
          <a href="<?= e(base_url('login.php?redirect=/become-affiliate.php')) ?>" class="btn btn-outline">Log In</a>
          <a href="<?= e(base_url('signup.php?redirect=/become-affiliate.php')) ?>" class="btn btn-primary">Sign Up</a>
        </div>
      </div>
    <?php else: ?>
      <form method="post" class="card card-pad">
        <?= csrf_field() ?>
        <div class="field"><label for="motivation">How do you plan to share Obin Academy?</label><textarea id="motivation" name="motivation" rows="4" required placeholder="e.g. My WhatsApp community, social media following, church group, students I mentor..."><?= e($myApplication['motivation'] ?? '') ?></textarea></div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Submit Application</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
