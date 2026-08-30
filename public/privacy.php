<?php
require __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Privacy Policy — Obin Academy';
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:680px;">
    <span class="pill">Legal</span>
    <h1 style="margin-top:14px;">Privacy Policy</h1>
    <p class="summary" style="margin-top:14px;">Last updated: <?= date('F Y') ?>. Here's what we collect, why, and how it's protected.</p>
  </div>
</section>

<div class="section">
  <div class="container" style="max-width:760px;">
    <div class="stack gap-4">
      <div>
        <h2 class="h3">1. Information We Collect</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">When you create an account, we collect your name, email, and phone number. When you make a purchase, our payment partner processes your mobile money details — we never see or store your mobile money PIN.</p>
      </div>
      <div>
        <h2 class="h3">2. How We Use Your Information</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">We use your information to run your account, process payments, deliver course access, send important updates (like payment confirmations), and improve the platform.</p>
      </div>
      <div>
        <h2 class="h3">3. Payments</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">Mobile money payments are processed securely through our payment partner (iotec Pay). We store the transaction reference and status, not your mobile money credentials.</p>
      </div>
      <div>
        <h2 class="h3">4. Cookies &amp; Sessions</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">We use a session cookie to keep you logged in. It's essential to the site working and isn't used for advertising or cross-site tracking.</p>
      </div>
      <div>
        <h2 class="h3">5. Sharing Your Information</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">We don't sell your data. We share only what's necessary with our payment processor to complete transactions, and with course creators for enrollment records (name and progress) for courses you've bought.</p>
      </div>
      <div>
        <h2 class="h3">6. Data Retention</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">We keep your account and purchase history for as long as your account is active, so you retain access to courses you've paid for. You can request account deletion at any time.</p>
      </div>
      <div>
        <h2 class="h3">7. Your Rights</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">You can update your profile information anytime from your account settings, or contact us to request a copy of your data or ask us to delete your account.</p>
      </div>
      <div>
        <h2 class="h3">8. Contact</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">Questions about your privacy? Reach us via the <a href="<?= e(base_url('contact.php')) ?>" style="color:var(--accent); font-weight:600;">Contact page</a> or WhatsApp.</p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
