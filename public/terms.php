<?php
require __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Terms of Service — Obin Academy';
require __DIR__ . '/../includes/header.php';
?>
<section class="course-hero">
  <div class="container" style="max-width:680px;">
    <span class="pill">Legal</span>
    <h1 style="margin-top:14px;">Terms of Service</h1>
    <p class="summary" style="margin-top:14px;">Last updated: <?= date('F Y') ?>. Please read these terms carefully before using Obin Academy.</p>
  </div>
</section>

<div class="section">
  <div class="container" style="max-width:760px;">
    <div class="stack gap-4">
      <div>
        <h2 class="h3">1. Acceptance of Terms</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">By creating an account or using Obin Academy, you agree to these Terms of Service. If you don't agree, please don't use the platform.</p>
      </div>
      <div>
        <h2 class="h3">2. Accounts</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">You're responsible for keeping your login credentials secure and for all activity under your account. You must provide accurate information when signing up.</p>
      </div>
      <div>
        <h2 class="h3">3. Course Purchases &amp; Payments</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">Courses are paid for instantly via MTN or Airtel Mobile Money. Once payment is confirmed, you're enrolled and gain access according to the course's stated access duration (or lifetime, where offered). Prices are set by individual creators and shown in Ugandan Shillings (UGX) before purchase.</p>
      </div>
      <div>
        <h2 class="h3">4. Creator Content &amp; Earnings</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">Creators retain ownership of the courses they upload and are responsible for the accuracy and quality of their content. Obin Academy takes a 10% platform fee on each sale; creators keep the remaining 90%, withdrawable to mobile money once minimum thresholds are met.</p>
      </div>
      <div>
        <h2 class="h3">5. Prohibited Conduct</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">You may not upload unlawful, infringing, or harmful content, attempt to circumvent payment or access controls, or share your enrolled course access with people who haven't paid for it.</p>
      </div>
      <div>
        <h2 class="h3">6. Refunds</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">Refund requests are reviewed on a case-by-case basis. Contact us within 48 hours of purchase if a course was materially different from its description or you were charged in error.</p>
      </div>
      <div>
        <h2 class="h3">7. Changes to These Terms</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">We may update these terms from time to time. Continued use of Obin Academy after changes means you accept the updated terms.</p>
      </div>
      <div>
        <h2 class="h3">8. Contact</h2>
        <p class="muted" style="margin-top:12px; line-height:1.75;">Questions about these terms? Reach us via the <a href="<?= e(base_url('contact.php')) ?>" style="color:var(--accent); font-weight:600;">Contact page</a> or WhatsApp.</p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
