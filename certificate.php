<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/certificates.php';

$code = query_param('code');
$cert = $code ? get_certificate_by_code($code) : null;
if (!$cert) {
    http_response_code(404);
    $pageTitle = 'Certificate Not Found — Obin Academy';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:80px 0; text-align:center;"><h1 class="h2">Certificate not found</h1><p class="muted" style="margin-top:10px;">Check the link and try again.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$learnerName = $cert['learner_name'] ?? $cert['guest_name'] ?? 'Obin Academy Learner';
$issueDate = date('F j, Y', strtotime($cert['issued_at']));
$verifyUrl = base_url('certificate.php?code=' . $cert['code']);
$courseUrl = base_url('courses/view.php?slug=' . $cert['course_slug']);

$linkedInUrl = 'https://www.linkedin.com/profile/add?' . http_build_query([
    'startTask' => 'CERTIFICATION_NAME',
    'name' => $cert['course_title'],
    'organizationName' => 'Obin Academy',
    'issueYear' => date('Y', strtotime($cert['issued_at'])),
    'issueMonth' => date('n', strtotime($cert['issued_at'])),
    'certUrl' => $verifyUrl,
    'certId' => $cert['code'],
]);

$pageTitle = $cert['course_title'] . ' — Certificate — Obin Academy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Great+Vibes&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <style>
    body { background: var(--surface); min-height: 100vh; }
    .cert-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 1000px; margin: 0 auto; padding: 20px 20px 0; }
    .cert-wrap { max-width: 1000px; margin: 24px auto 60px; padding: 0 20px; }
    .cert-doc {
      position: relative; background: #fff; border-radius: 14px; overflow: hidden;
      box-shadow: 0 30px 70px -30px rgba(20,24,27,0.35);
      aspect-ratio: 1.414/1; padding: 5%;
      display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
    }
    .cert-doc::before {
      content: ""; position: absolute; inset: 14px; border: 2px solid var(--gold); border-radius: 6px; pointer-events: none;
    }
    .cert-doc::after {
      content: ""; position: absolute; inset: 20px; border: 1px solid color-mix(in srgb, var(--accent) 40%, transparent); border-radius: 4px; pointer-events: none;
    }
    .cert-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 18px; color: var(--brand-900); }
    .cert-brand .mark { width: 34px; height: 34px; border-radius: 9px; background: var(--brand-900); display: flex; align-items: center; justify-content: center; }
    .cert-brand .mark svg { width: 19px; height: 19px; stroke: #fff; }
    .cert-eyebrow { margin-top: 26px; font-size: 12px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; color: var(--gold); }
    .cert-title { margin-top: 8px; font-size: clamp(24px, 3.4vw, 34px); font-weight: 800; color: var(--brand-900); }
    .cert-presented { margin-top: 22px; font-size: 13px; color: var(--muted); }
    .cert-name { margin-top: 10px; font-family: 'Great Vibes', cursive; font-size: clamp(36px, 5.5vw, 54px); color: var(--ink); line-height: 1; }
    .cert-body-text { margin-top: 18px; font-size: 14px; color: var(--muted); max-width: 560px; line-height: 1.7; }
    .cert-course { margin-top: 4px; font-size: 17px; font-weight: 700; color: var(--brand-900); }
    .cert-footer { margin-top: auto; padding-top: 30px; display: flex; align-items: flex-end; justify-content: space-between; width: 100%; max-width: 620px; }
    .cert-sig { text-align: center; }
    .cert-sig .sig-name { font-family: 'Great Vibes', cursive; font-size: 24px; color: var(--ink); }
    .cert-sig .sig-line { margin-top: 4px; border-top: 1px solid var(--border); padding-top: 4px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--muted); }
    .cert-meta { margin-top: 22px; font-size: 10.5px; color: var(--muted); }
    @media (max-width: 640px) {
      .cert-doc { aspect-ratio: auto; padding: 8%; }
      .cert-footer { flex-direction: column; gap: 18px; align-items: center; }
    }
    @media print {
      @page { size: landscape; margin: 0; }
      body { background: #fff; }
      .cert-toolbar, .no-print { display: none !important; }
      .cert-wrap { margin: 0; padding: 0; max-width: none; }
      .cert-doc { box-shadow: none; border-radius: 0; aspect-ratio: auto; width: 100vw; height: 100vh; }
    }
  </style>
</head>
<body>
  <div class="cert-toolbar no-print">
    <?php render_logo(); ?>
    <div class="row gap-2">
      <a href="<?= e($courseUrl) ?>" class="btn btn-outline btn-sm">← Back to Course</a>
      <a href="<?= e($linkedInUrl) ?>" target="_blank" rel="noopener" class="btn btn-dark btn-sm">Add to LinkedIn</a>
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">🖨 Download / Print</button>
    </div>
  </div>

  <div class="cert-wrap">
    <div class="cert-doc">
      <div class="cert-brand">
        <span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg></span>
        Obin Academy
      </div>

      <div class="cert-eyebrow">Certificate of Completion</div>
      <h1 class="cert-title">This certifies that</h1>

      <div class="cert-name"><?= e($learnerName) ?></div>

      <p class="cert-body-text">has successfully completed the course</p>
      <div class="cert-course"><?= e($cert['course_title']) ?></div>
      <p class="cert-body-text" style="margin-top:14px;">issued on <?= e($issueDate) ?></p>

      <div class="cert-footer">
        <div class="cert-sig">
          <div class="sig-name">Obin Academy</div>
          <div class="sig-line">Platform</div>
        </div>
        <div class="cert-sig">
          <div class="sig-name"><?= e($cert['creator_name']) ?></div>
          <div class="sig-line">Course Instructor</div>
        </div>
      </div>

      <div class="cert-meta">Certificate ID: <?= e($cert['code']) ?> &middot; Verify at <?= e($verifyUrl) ?></div>
    </div>
  </div>
</body>
</html>
