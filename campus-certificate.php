<?php
// A campus certificate, public by its code so anyone can verify it.
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/campuses.php';

$cert = get_campus_certificate(query_param('code'));
$pageTitle = $cert ? $cert['learner_name'] . ' — ' . $cert['campus_name'] . ' Campus certificate' : 'Certificate not found — Obin Academy';
$noindex = true;
if (!$cert) http_response_code(404);
$extraGoogleFont = 'Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(versioned_asset('assets/css/campuses.css')) ?>">
<main class="cx" style="padding-block:8px 40px;">
  <?php if (!$cert): ?>
    <div class="cx-lock"><span class="cx-label">Certificate</span><h1>We couldn't find that certificate.</h1><p>Check the code and try again.</p></div>
  <?php else: ?>
    <div class="cx-cert">
      <div class="seal">O</div>
      <span class="cx-label" style="display:block; margin-top:18px;">Obin Academy · Certificate of completion</span>
      <h1><?= e($cert['campus_name']) ?> Campus</h1>
      <p>This certifies that</p>
      <div class="to"><?= e($cert['learner_name']) ?></div>
      <p>completed the twelve-month programme of the <?= e($cert['campus_name']) ?> Campus at Obin Academy.</p>
      <div class="foot">
        <div><b><?= e(date('j F Y', strtotime($cert['certificate_issued_at']))) ?></b>Date issued</div>
        <div><b><?= e($cert['professor_name'] ?: 'Obin Academy') ?></b>Professor</div>
        <div><b class="cx-mono" style="font-size:14px;"><?= e($cert['certificate_code']) ?></b>Certificate code</div>
      </div>
    </div>
    <p class="cx-noprint" style="text-align:center;"><button class="cx-btn" type="button" onclick="window.print()">Print or save as PDF</button></p>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
