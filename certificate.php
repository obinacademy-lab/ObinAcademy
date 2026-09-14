<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/certificates.php';

$code = query_param('code');
$cert = $code ? get_certificate_by_code($code) : null;
if (!$cert) {
    http_response_code(404);
    $pageTitle = 'Certificate Not Found — Obin Academy';
    $noindex = true;
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
  <meta name="robots" content="noindex, follow">
  <title><?= e($pageTitle) ?></title>
  <link rel="icon" href="<?= e(versioned_asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="icon" href="<?= e(versioned_asset('favicon-32x32.png')) ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= e(versioned_asset('apple-touch-icon.png')) ?>" sizes="180x180">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Great+Vibes&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(versioned_asset('assets/css/style.css')) ?>">
  <style>
    body { background: var(--surface); min-height: 100vh; }
    .cert-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 1040px; margin: 0 auto; padding: 20px 20px 0; }
    .cert-wrap { max-width: 1040px; margin: 24px auto 60px; padding: 0 20px; }

    .cert-doc {
      position: relative; background: #fdfcf8; overflow: hidden;
      box-shadow: 0 30px 70px -30px rgba(20,24,27,0.35);
      aspect-ratio: 1.414/1;
      display: flex; flex-direction: column; align-items: center;
      print-color-adjust: exact; -webkit-print-color-adjust: exact;
    }

    /* Security-paper texture + faint logo watermark */
    .cert-bg {
      position: absolute; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse at 50% 42%, rgba(184,134,11,.05), transparent 60%),
        repeating-linear-gradient(115deg, rgba(30,58,138,.025) 0px, rgba(30,58,138,.025) 1px, transparent 1px, transparent 14px),
        repeating-linear-gradient(25deg, rgba(184,134,11,.02) 0px, rgba(184,134,11,.02) 1px, transparent 1px, transparent 14px);
    }
    .cert-watermark { position: absolute; z-index: 0; top: 50%; left: 50%; transform: translate(-50%,-46%); width: 40%; opacity: .05; color: var(--brand-950); pointer-events: none; }

    /* Ornate gold double border with corner flourishes */
    .cert-border-outer { position: absolute; inset: 16px; border: 2.5px solid var(--gold); z-index: 1; pointer-events: none; }
    .cert-border-inner { position: absolute; inset: 23px; border: 1px solid var(--gold); opacity: .55; z-index: 1; pointer-events: none; }
    .cert-corner { position: absolute; width: 46px; height: 46px; z-index: 2; pointer-events: none; color: var(--gold); }
    .cert-corner svg { width: 100%; height: 100%; }
    .cert-corner.tl { top: 8px; left: 8px; }
    .cert-corner.tr { top: 8px; right: 8px; transform: scaleX(-1); }
    .cert-corner.bl { bottom: 8px; left: 8px; transform: scaleY(-1); }
    .cert-corner.br { bottom: 8px; right: 8px; transform: scale(-1,-1); }

    .cert-content { position: relative; z-index: 3; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; padding: 4.6% 7% 3.6%; text-align: center; }

    .cert-seal { width: 72px; height: 72px; flex-shrink: 0; }
    .cert-brand { margin-top: 8px; display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 15px; color: var(--brand-950); }
    .cert-brand .accent { color: var(--gold); }

    .cert-eyebrow { margin-top: 20px; font-size: 11.5px; font-weight: 700; letter-spacing: .28em; text-transform: uppercase; color: var(--gold); }
    .cert-title { margin: 9px 0 0; font-family: 'Cormorant Garamond', serif; font-size: clamp(24px, 2.8vw, 33px); font-weight: 600; font-style: italic; color: var(--ink); }

    .cert-name {
      margin-top: 18px; font-family: 'Great Vibes', cursive; font-size: clamp(38px, 5vw, 54px);
      color: var(--brand-950); line-height: 1; position: relative; padding-bottom: 12px;
    }
    .cert-name::after { content: ""; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 200px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold), transparent); }

    .cert-body-text { margin-top: 16px; font-family: 'Cormorant Garamond', serif; font-size: 17px; color: var(--muted); }
    .cert-course { margin-top: 5px; font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 700; color: var(--brand-950); max-width: 620px; }
    .cert-date { margin-top: 8px; font-size: 11.5px; color: var(--muted); }

    .cert-footer { margin-top: auto; padding-top: 18px; display: flex; align-items: flex-end; justify-content: center; gap: 56px; width: 100%; max-width: 700px; }
    .cert-sig { text-align: center; min-width: 140px; }
    .cert-sig .sig-name { font-family: 'Great Vibes', cursive; font-size: 23px; color: var(--ink); }
    .cert-sig .sig-line { margin-top: 3px; border-top: 1px solid var(--gold); padding-top: 4px; font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }

    .cert-qr { display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .cert-qr canvas { width: 54px; height: 54px; border-radius: 4px; }
    .cert-qr span { font-size: 8px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }

    .cert-meta { margin-top: 14px; font-size: 9px; color: var(--muted); }

    @media (max-width: 640px) {
      .cert-doc { aspect-ratio: auto; }
      .cert-content { padding: 36px 22px 26px; }
      .cert-footer { flex-direction: column; gap: 18px; }
    }
    @media print {
      @page { size: landscape; margin: 0; }
      body { background: #fff; }
      .cert-toolbar, .no-print { display: none !important; }
      .cert-wrap { margin: 0; padding: 0; max-width: none; }
      .cert-doc { box-shadow: none; aspect-ratio: auto; width: 100vw; height: 100vh; }
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
      <div class="cert-bg"></div>
      <svg class="cert-watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path></svg>

      <div class="cert-border-outer"></div>
      <div class="cert-border-inner"></div>
      <div class="cert-corner tl"><svg viewBox="0 0 46 46" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 30V10a8 8 0 0 1 8-8h20"></path><path d="M2 20V10a8 8 0 0 1 8-8h10"></path></svg></div>
      <div class="cert-corner tr"><svg viewBox="0 0 46 46" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 30V10a8 8 0 0 1 8-8h20"></path><path d="M2 20V10a8 8 0 0 1 8-8h10"></path></svg></div>
      <div class="cert-corner bl"><svg viewBox="0 0 46 46" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 30V10a8 8 0 0 1 8-8h20"></path><path d="M2 20V10a8 8 0 0 1 8-8h10"></path></svg></div>
      <div class="cert-corner br"><svg viewBox="0 0 46 46" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 30V10a8 8 0 0 1 8-8h20"></path><path d="M2 20V10a8 8 0 0 1 8-8h10"></path></svg></div>

      <div class="cert-content">
        <div class="cert-seal">
          <svg viewBox="0 0 100 100">
            <defs>
              <linearGradient id="sealGrad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#e6c25c"></stop><stop offset="0.5" stop-color="#b8860b"></stop><stop offset="1" stop-color="#8a6508"></stop>
              </linearGradient>
            </defs>
            <circle cx="50" cy="46" r="38" fill="url(#sealGrad)"></circle>
            <circle cx="50" cy="46" r="38" fill="none" stroke="#7a5a06" stroke-width="1"></circle>
            <circle cx="50" cy="46" r="31" fill="none" stroke="#fff" stroke-width="1" stroke-dasharray="2 3" opacity="0.65"></circle>
            <circle cx="50" cy="46" r="26" fill="#1e3a8a"></circle>
            <g transform="translate(50,46) scale(1.5) translate(-12,-12)" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 10 12 5 2 10l10 5 10-5Z"></path>
              <path d="M6 12v5c0 1.1 2.7 2 6 2s6-.9 6-2v-5"></path>
            </g>
            <path d="M30 62 L38 96 L50 88 L62 96 L70 62 Z" fill="#8a6508"></path>
            <path d="M30 62 L38 90 L50 83 L62 90 L70 62" fill="none" stroke="#e6c25c" stroke-width="1"></path>
          </svg>
        </div>

        <div class="cert-brand">Obin <span class="accent">Academy</span></div>

        <div class="cert-eyebrow">Certificate of Completion</div>
        <h1 class="cert-title">This certifies that</h1>

        <div class="cert-name"><?= e($learnerName) ?></div>

        <p class="cert-body-text">has successfully completed the course</p>
        <div class="cert-course"><?= e($cert['course_title']) ?></div>
        <p class="cert-date">Issued on <?= e($issueDate) ?></p>

        <div class="cert-footer">
          <div class="cert-sig">
            <div class="sig-name">Obin Academy</div>
            <div class="sig-line">Platform</div>
          </div>
          <div class="cert-qr">
            <div id="certQr" class="no-print-hide"></div>
            <span>Scan to Verify</span>
          </div>
          <div class="cert-sig">
            <div class="sig-name"><?= e($cert['creator_name']) ?></div>
            <div class="sig-line">Course Instructor</div>
          </div>
        </div>

        <div class="cert-meta">Certificate ID: <?= e($cert['code']) ?> &middot; Verify at <?= e($verifyUrl) ?></div>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    var qrHolder = document.getElementById('certQr');
    if (qrHolder && window.QRCode) {
      new QRCode(qrHolder, {
        text: <?= json_encode($verifyUrl) ?>,
        width: 54,
        height: 54,
        colorDark: '#1e3a8a',
        colorLight: '#fdfcf8',
        correctLevel: QRCode.CorrectLevel.M
      });
    }
  </script>
</body>
</html>
