<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/email.php';

/** Inline stroke-icon set (Lucide-style: 24x24, stroke-width 2, round caps) — matches the icons already used in header.php/footer.php. */
function ci(string $name, string $class = ''): string {
    $icons = [
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m3 7 9 6 9-6"></path>',
        'message-circle' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"></path>',
        'graduation-cap' => '<path d="M21.42 10.92a1 1 0 0 0-.02-1.84L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.83l8.57 3.91a2 2 0 0 0 1.66 0z"></path><path d="M22 10v6"></path><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"></path>',
        'award' => '<circle cx="12" cy="8" r="6"></circle><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"></path>',
        'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path>',
        'newspaper' => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"></path><path d="M18 14h-8"></path><path d="M15 18h-5"></path><path d="M10 6h8v4h-8V6Z"></path>',
        'map-pin' => '<path d="M20 10c0 4.99-5.54 10.19-7.4 11.8a1 1 0 0 1-1.2 0C9.54 20.19 4 14.99 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.79 17 5 19 5a1 1 0 0 1 1 1z"></path><path d="m9 12 2 2 4-4"></path>',
        'smartphone' => '<rect x="5" y="2" width="14" height="20" rx="2"></rect><path d="M12 18h.01"></path>',
        'headphones' => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-4a9 9 0 1 1 18 0v4a1 1 0 0 1-1 1h-2a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"></path>',
        'book-open' => '<path d="M12 7v14"></path><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"></path>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
        'send' => '<path d="M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.02.94l7.93 3.18a2 2 0 0 1 1.11 1.11z"></path><path d="m21.85 2.15-10.94 10.94"></path>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'user' => '<circle cx="12" cy="8" r="4"></circle><path d="M6 21v-2a6 6 0 0 1 12 0v2"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path>',
        'navigation' => '<polygon points="3 11 22 2 13 21 11 13 3 11"></polygon>',
        'check' => '<path d="M20 6 9 17l-5-5"></path>',
    ];
    $inner = $icons[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . e($class) . '" aria-hidden="true">' . $inner . '</svg>';
}

$inquiryTypes = [
    'general' => 'General Question',
    'student_support' => 'Student Support',
    'creator_support' => 'Creator Support',
    'technical_support' => 'Technical Support',
    'billing' => 'Billing',
    'partnership' => 'Partnership',
    'business_inquiry' => 'Business Inquiry',
    'report_problem' => 'Report a Problem',
];

$ADMIN_EMAIL = 'obinacademy@gmail.com';
$SUPPORT_EMAIL = 'support@obinacademy.com';
$WHATSAPP_NUMBER = '256775361998';
$PHONE_DISPLAY = '+256 775 361 998';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_type') === 'newsletter') {
    csrf_verify();
    $newsletterEmail = post('newsletter_email');
    if (!filter_var($newsletterEmail, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Enter a valid email address to subscribe.');
    } else {
        resend_send($ADMIN_EMAIL, 'New newsletter subscriber', '<p>' . e($newsletterEmail) . '</p>');
        flash_set('success', "You're subscribed! Watch your inbox for updates from Obin Academy.");
    }
    redirect('/contact.php#newsletter');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_type') === 'contact') {
    csrf_verify();
    $name = post('name');
    $email = post('email');
    $phone = post('phone');
    $subject = post('subject');
    $inquiryType = post('inquiry_type');
    $message = post('message');

    $errors = [];
    if (strlen($name) < 2) $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($subject) < 3) $errors[] = 'Enter a subject.';
    if (!array_key_exists($inquiryType, $inquiryTypes)) $errors[] = 'Choose an inquiry type.';
    if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

    if ($errors) {
        flash_set('error', implode(' ', $errors));
        redirect('/contact.php#contact-form');
    }

    $body = '<p><strong>From:</strong> ' . e($name) . ' (' . e($email) . ')</p>'
          . ($phone !== '' ? '<p><strong>Phone:</strong> ' . e($phone) . '</p>' : '')
          . '<p><strong>Inquiry type:</strong> ' . e($inquiryTypes[$inquiryType]) . '</p>'
          . '<p>' . nl2br(e($message)) . '</p>';
    resend_send($ADMIN_EMAIL, 'Contact form: ' . $subject, $body);
    flash_set('success', "Thanks — your message is in. Our team replies within 24 hours.");
    redirect('/contact.php#contact-form');
}

$presetInquiry = $_GET['type'] ?? '';
if (!array_key_exists($presetInquiry, $inquiryTypes)) $presetInquiry = '';

$whatsappHref = 'https://wa.me/' . $WHATSAPP_NUMBER . '?text=' . urlencode('Hi, I have a question about Obin Academy');

$faqs = [
    ['How do I enroll in a course?', 'Browse our course catalog, open the course you want, and tap "Enroll." Pay instantly with MTN or Airtel Mobile Money and you\'ll get access the moment payment is confirmed — no waiting, no card required.'],
    ['Can I learn from my phone?', "Yes. Obin Academy works on any device with a browser — phone, tablet, or laptop. Video and PDF lessons load right in the browser, so there's nothing extra to install."],
    ['How do creators earn money?', 'Creators set their own course price and keep 90% of every sale. Obin Academy takes a simple, transparent 10% platform fee — nothing hidden. Earnings can be withdrawn straight to mobile money.'],
    ['How do certificates work?', "Once you complete all lessons in a course, a Certificate of Completion is generated automatically and appears in your learner dashboard, ready to download or share."],
    ['What payment methods are accepted?', 'We currently accept MTN Mobile Money and Airtel Money. You\'ll get a payment prompt on your phone to approve — no bank account or credit card needed.'],
    ['Can I request a refund?', "Refund requests are reviewed case-by-case. If a course was materially different from its description, or you were charged in error, contact us within 48 hours of purchase and we'll sort it out."],
    ['How do I publish my own course?', 'Apply to become a creator, then use the Creator Dashboard to add modules, upload video or PDF lessons, set your price, and submit for review. Once approved, your course goes live to every learner on the platform.'],
    ['How long does support take to respond?', "Email and contact-form requests are answered within 24 hours. WhatsApp and Live Chat during business hours (Mon–Fri, 8:00 AM–6:00 PM EAT) are usually much faster."],
];

$pageTitle = 'Contact & Support — Obin Academy';
require __DIR__ . '/includes/header.php';
?>
<!-- 1. Hero -->
<section class="course-hero">
  <div class="course-hero-glow" aria-hidden="true"></div>
  <div class="container" style="max-width:720px; text-align:center;">
    <span class="pill"><?= ci('headphones') ?> Support &amp; Contact Center</span>
    <h1 style="text-align:center;">We're Here to Help</h1>
    <p class="summary" style="margin-left:auto; margin-right:auto; text-align:center;">Whether you're a learner, creator, business, or partner, our team is ready to answer your questions and help you get the most out of Obin Academy.</p>
    <div class="row gap-2" style="justify-content:center; margin-top:26px;">
      <a href="#contact-form" class="btn btn-gold">Contact Support <span class="btn-arrow">→</span></a>
      <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-outline-light">▶ Become a Creator</a>
    </div>
    <div class="hero-trust-row">
      <span class="item"><?= ci('shield-check') ?> Secure &amp; Trusted</span>
      <span class="item"><?= ci('clock') ?> Replies Within 24 Hours</span>
      <span class="item"><?= ci('headphones') ?> Real Human Support</span>
    </div>
  </div>
</section>

<!-- 2. Multiple Ways to Reach Us -->
<div class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:600px; margin:0 auto 40px;">
      <span class="eyebrow">Get In Touch</span>
      <h2 class="h2" style="margin-top:10px;">Multiple Ways to Reach Us</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Pick whatever channel is fastest for you — every path leads to a real person on our team.</p>
    </div>
    <div class="grid sm:grid-2 lg:grid-4">
      <div class="contact-card reveal reveal-delay-1">
        <span class="icon-badge" style="--tint:#2563eb;"><?= ci('mail') ?></span>
        <h3>Email Support</h3>
        <p class="desc">General inquiries and platform support.</p>
        <span class="meta-label">Email</span>
        <span class="meta"><a href="mailto:<?= e($SUPPORT_EMAIL) ?>"><?= e($SUPPORT_EMAIL) ?></a></span>
        <span class="meta-label">Response Time</span>
        <span class="meta">Within 24 Hours</span>
        <a href="mailto:<?= e($SUPPORT_EMAIL) ?>" class="btn btn-outline btn-sm">Send Email</a>
      </div>
      <div class="contact-card reveal reveal-delay-2">
        <span class="icon-badge" style="--tint:#25D366;"><?= ci('message-circle') ?></span>
        <h3>WhatsApp Support</h3>
        <p class="desc">Need quick assistance? Chat directly with our support team.</p>
        <a href="<?= e($whatsappHref) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-btn" style="margin-top:18px; align-self:flex-start;">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"></path></svg>
          WhatsApp Us
        </a>
      </div>
      <div class="contact-card reveal reveal-delay-3">
        <span class="icon-badge" style="--tint:#8b5cf6;"><?= ci('message-circle') ?></span>
        <h3>Live Chat</h3>
        <p class="desc">Chat instantly with our support team during business hours.</p>
        <span class="live-pill" style="margin-top:16px; align-self:flex-start;"><span class="live-dot"></span>Online</span>
        <a href="<?= e($whatsappHref) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">Start Chat</a>
      </div>
      <div class="contact-card reveal reveal-delay-4">
        <span class="icon-badge" style="--tint:#f5b301;"><?= ci('phone') ?></span>
        <h3>Phone Support</h3>
        <p class="desc">Prefer to talk it through? Give us a call.</p>
        <span class="meta-label">Phone</span>
        <span class="meta"><a href="tel:+<?= e($WHATSAPP_NUMBER) ?>"><?= e($PHONE_DISPLAY) ?></a></span>
        <span class="meta-label">Business Hours</span>
        <span class="meta">Mon – Fri, 8:00 AM – 6:00 PM (EAT)</span>
        <a href="tel:+<?= e($WHATSAPP_NUMBER) ?>" class="btn btn-outline btn-sm">Call Now</a>
      </div>
    </div>
  </div>
</div>

<!-- 3. Contact Form -->
<div id="contact-form" class="section" style="background:var(--surface); scroll-margin-top:90px;">
  <div class="container" style="max-width:720px;">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 32px;">
      <span class="eyebrow">Send a Message</span>
      <h2 class="h2" style="margin-top:10px;">Tell Us How We Can Help</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Fill out the form below and our team will get back to you within 24 hours.</p>
    </div>
    <form method="post" class="card card-pad reveal" style="padding:36px;" data-loading-submit>
      <?= csrf_field() ?>
      <input type="hidden" name="form_type" value="contact">
      <div class="grid sm:grid-2" style="gap:0 16px;">
        <div class="field"><label for="cf-name">Full Name</label><input id="cf-name" name="name" type="text" autocomplete="name" required></div>
        <div class="field"><label for="cf-email">Email Address</label><input id="cf-email" name="email" type="email" autocomplete="email" required></div>
      </div>
      <div class="grid sm:grid-2" style="gap:0 16px;">
        <div class="field"><label for="cf-phone">Phone Number <span class="muted small">(optional)</span></label><input id="cf-phone" name="phone" type="tel" autocomplete="tel" placeholder="e.g. +256 7XX XXX XXX"></div>
        <div class="field"><label for="cf-inquiry">Inquiry Type</label>
          <select id="cf-inquiry" name="inquiry_type" required>
            <option value="" disabled <?= $presetInquiry === '' ? 'selected' : '' ?>>Choose one…</option>
            <?php foreach ($inquiryTypes as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $presetInquiry === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field"><label for="cf-subject">Subject</label><input id="cf-subject" name="subject" type="text" required></div>
      <div class="field"><label for="cf-message">Message</label><textarea id="cf-message" name="message" rows="6" required placeholder="How can we help?"></textarea></div>
      <button type="submit" class="btn btn-primary btn-block btn-lg"><span data-btn-label class="row gap-2 center"><?= ci('send') ?> Send Message</span></button>
    </form>
  </div>
</div>

<!-- 4. Who Are You? -->
<div class="section">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 40px;">
      <span class="eyebrow">Who Are You?</span>
      <h2 class="h2" style="margin-top:10px;">Get Support Built for Your Role</h2>
    </div>
    <div class="grid sm:grid-2 lg:grid-4">
      <div class="persona-card reveal reveal-delay-1">
        <span class="icon-badge" style="--tint:#2563eb;"><?= ci('graduation-cap') ?></span>
        <h3>I'm a Learner</h3>
        <ul class="check-list">
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Course access</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Certificates</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Learning assistance</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Payments</span></li>
        </ul>
        <a href="?type=student_support#contact-form" class="btn btn-outline btn-sm">Get Learner Support</a>
      </div>
      <div class="persona-card reveal reveal-delay-2">
        <span class="icon-badge" style="--tint:#f5b301;"><?= ci('award') ?></span>
        <h3>I'm a Creator</h3>
        <ul class="check-list">
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Publish courses</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Earnings</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Course approval</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Creator Dashboard</span></li>
        </ul>
        <a href="?type=creator_support#contact-form" class="btn btn-outline btn-sm">Creator Support</a>
      </div>
      <div class="persona-card reveal reveal-delay-3">
        <span class="icon-badge" style="--tint:#8b5cf6;"><?= ci('building') ?></span>
        <h3>Business Partnership</h3>
        <ul class="check-list">
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Schools</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Organizations</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Corporate Training</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Strategic Partnerships</span></li>
        </ul>
        <a href="?type=partnership#contact-form" class="btn btn-outline btn-sm">Partner With Us</a>
      </div>
      <div class="persona-card reveal reveal-delay-4">
        <span class="icon-badge" style="--tint:#ec4899;"><?= ci('newspaper') ?></span>
        <h3>Media &amp; Press</h3>
        <ul class="check-list">
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Interviews</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Press</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Speaking invitations</span></li>
          <li><span class="check-icon"><?= ci('check') ?></span><span class="label-text">Brand collaborations</span></li>
        </ul>
        <a href="?type=business_inquiry#contact-form" class="btn btn-outline btn-sm">Media Contact</a>
      </div>
    </div>
  </div>
</div>

<!-- 5. Help Center -->
<div id="faq" class="section" style="background:var(--surface); scroll-margin-top:90px;">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 32px;">
      <span class="eyebrow">Help Center</span>
      <h2 class="h2" style="margin-top:10px;">Find Answers Instantly</h2>
      <p class="lede" style="margin-top:10px; max-width:none;">Popular articles our learners and creators search for most.</p>
    </div>
    <div class="help-articles reveal">
      <a href="#faq" class="help-article-tile"><?= ci('user') ?> How to Create an Account</a>
      <a href="#faq" class="help-article-tile"><?= ci('users') ?> How to Become a Creator</a>
      <a href="#faq" class="help-article-tile"><?= ci('book-open') ?> Upload Your First Course</a>
      <a href="#faq" class="help-article-tile"><?= ci('credit-card') ?> Payment Methods</a>
      <a href="#faq" class="help-article-tile"><?= ci('award') ?> Certificates</a>
      <a href="#faq" class="help-article-tile"><?= ci('key') ?> Reset Your Password</a>
    </div>
    <div class="text-center" style="margin-top:28px;">
      <a href="#faq" class="btn btn-primary">Visit Help Center <span class="btn-arrow">→</span></a>
    </div>
  </div>
</div>

<!-- 6. Frequently Asked Questions -->
<div class="section">
  <div class="container" style="max-width:760px;">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">FAQ</span>
      <h2 class="h2" style="margin-top:10px;">Frequently Asked Questions</h2>
    </div>
    <div class="faq-list reveal">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
        <div class="faq-item">
          <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-panel-<?= $i ?>" id="faq-q-<?= $i ?>">
            <span><?= e($q) ?></span>
            <?= ci('chevron-down', 'faq-chevron') ?>
          </button>
          <div class="faq-answer" id="faq-panel-<?= $i ?>" role="region" aria-labelledby="faq-q-<?= $i ?>">
            <div class="faq-answer-inner"><p><?= e($a) ?></p></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- 7. Office Information -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="grid lg:grid-2" style="gap:40px; align-items:center;">
      <div class="reveal">
        <span class="eyebrow">Visit Us</span>
        <h2 class="h2" style="margin-top:10px;">Head Office</h2>
        <div class="stack gap-3" style="margin-top:22px;">
          <div class="row gap-3" style="align-items:flex-start;">
            <span class="icon-badge" style="--tint:#2563eb; width:42px; height:42px; font-size:18px;"><?= ci('map-pin') ?></span>
            <div>
              <div style="font-weight:700; color:var(--ink);">Address</div>
              <p class="muted" style="margin-top:2px;">Plot 14, Kampala Road<br>Kampala, Uganda</p>
            </div>
          </div>
          <div class="row gap-3" style="align-items:flex-start;">
            <span class="icon-badge" style="--tint:#f5b301; width:42px; height:42px; font-size:18px;"><?= ci('clock') ?></span>
            <div>
              <div style="font-weight:700; color:var(--ink);">Business Hours</div>
              <p class="muted" style="margin-top:2px;">Monday – Friday<br>8:00 AM – 6:00 PM (EAT)</p>
            </div>
          </div>
        </div>
        <a href="https://www.google.com/maps/search/?api=1&query=Kampala%2C+Uganda" target="_blank" rel="noopener noreferrer" class="btn btn-dark" style="margin-top:26px;"><?= ci('navigation') ?> Get Directions</a>
      </div>
      <div class="map-placeholder reveal reveal-delay-2">
        <span class="pin"><?= ci('map-pin') ?></span>
      </div>
    </div>
  </div>
</div>

<!-- 8. Follow Us -->
<div class="section" style="padding:48px 0;">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto;">
      <span class="eyebrow">Stay Connected</span>
      <h2 class="h2" style="margin-top:10px;">Follow Us</h2>
      <p class="lede" style="margin:10px auto 0; max-width:none;">Get updates on new courses, creator stories, and behind-the-scenes moments from Obin Academy.</p>
      <div class="social-icons-light" style="justify-content:center; gap:14px; margin-top:28px;">
        <a href="https://www.facebook.com/profile.php?id=61591414895842" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/></svg></a>
        <a href="https://www.instagram.com/obinacademyofficial/?hl=en" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><svg viewBox="0 0 24 24"><path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/></svg></a>
        <a href="#" aria-label="TikTok"><svg viewBox="0 0 24 24"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
        <a href="#" aria-label="LinkedIn"><svg viewBox="0 0 24 24"><path d="M6.9 8.4H3.6V20h3.3V8.4zM5.3 3.4a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM20.4 20h-3.3v-6.1c0-1.5-.5-2.5-1.9-2.5-1 0-1.6.7-1.9 1.4-.1.2-.1.6-.1.9V20H10s0-10.6 0-11.6h3.3v1.6c.4-.7 1.2-1.7 3-1.7 2.2 0 3.8 1.4 3.8 4.5V20z"/></svg></a>
        <a href="https://www.youtube.com/@obinacademy" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M23.5 6.19a2.99 2.99 0 0 0-2.11-2.12C19.51 3.55 12 3.55 12 3.55s-7.51 0-9.39.52A2.99 2.99 0 0 0 .5 6.19 31.26 31.26 0 0 0 0 12a31.26 31.26 0 0 0 .5 5.81 2.99 2.99 0 0 0 2.11 2.12c1.88.52 9.39.52 9.39.52s7.51 0 9.39-.52a2.99 2.99 0 0 0 2.11-2.12A31.26 31.26 0 0 0 24 12a31.26 31.26 0 0 0-.5-5.81zM9.75 15.57V8.43L15.82 12z"/></svg></a>
        <a href="https://x.com/obinacademy" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)"><svg viewBox="0 0 24 24"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24H16.17l-5.21-6.82-5.96 6.82H1.68l7.73-8.84L1.25 2.25h6.82l4.71 6.23zm-1.16 17.52h1.83L7.08 4.13H5.12z"/></svg></a>
      </div>
    </div>
  </div>
</div>

<!-- 9. Why People Trust Obin Academy -->
<div class="section" style="background:var(--surface);">
  <div class="container">
    <div class="text-center reveal" style="max-width:560px; margin:0 auto 36px;">
      <span class="eyebrow">Why People Trust Us</span>
      <h2 class="h2" style="margin-top:10px;">Why People Trust Obin Academy</h2>
    </div>
    <div class="grid sm:grid-2 lg:grid-4">
      <div class="value-card reveal reveal-delay-1">
        <span class="icon-badge" style="--tint:#2563eb;"><?= ci('credit-card') ?></span>
        <h3>Secure Payments</h3>
        <p>Your payments are protected, every time.</p>
      </div>
      <div class="value-card reveal reveal-delay-2">
        <span class="icon-badge" style="--tint:#10b981;"><?= ci('book-open') ?></span>
        <h3>Practical Learning</h3>
        <p>Learn real-world skills from experienced creators.</p>
      </div>
      <div class="value-card reveal reveal-delay-3">
        <span class="icon-badge" style="--tint:#8b5cf6;"><?= ci('headphones') ?></span>
        <h3>Dedicated Support</h3>
        <p>Our support team is ready to assist you.</p>
      </div>
      <div class="value-card reveal reveal-delay-4">
        <span class="icon-badge" style="--tint:#f5b301;"><?= ci('smartphone') ?></span>
        <h3>Learn Anywhere</h3>
        <p>Access your courses anytime, on any device.</p>
      </div>
    </div>
  </div>
</div>

<!-- 10. Become a Creator CTA -->
<section class="feature-cta-section cta-photo-visible" style="background-image: url('<?= e(versioned_asset('assets/img/abt-video-call.jpg')) ?>');">
  <div class="container">
    <div class="cta-panel-premium reveal">
      <span class="eyebrow" style="background:rgba(255,255,255,0.1); color:var(--gold);">For Creators &amp; Experts</span>
      <h2 class="h2" style="margin-top:14px; color:#fff;">Ready to Share Your Knowledge?</h2>
      <p style="margin-top:12px; color:rgba(255,255,255,0.7); max-width:480px; margin-left:auto; margin-right:auto;">Create your first course today, inspire thousands of learners, and earn income from your expertise.</p>
      <div class="row gap-2" style="justify-content:center; margin-top:26px;">
        <a href="<?= e(base_url('become-creator.php')) ?>" class="btn btn-gold btn-lg">Become a Creator <span class="btn-arrow">→</span></a>
        <a href="<?= e(base_url('courses/index.php')) ?>" class="btn btn-outline-light btn-lg">Explore Courses</a>
      </div>
    </div>
  </div>
</section>

<!-- 11. Newsletter -->
<div id="newsletter" class="section" style="scroll-margin-top:90px;">
  <div class="container" style="max-width:640px;">
    <div class="newsletter-panel reveal">
      <span class="icon-badge" style="--tint:#2563eb; margin:0 auto;"><?= ci('mail') ?></span>
      <h2 class="h2" style="margin-top:18px;">Stay Updated</h2>
      <p class="lede" style="margin:10px auto 0; max-width:440px;">Receive updates on new courses, creator opportunities, platform improvements, and learning resources.</p>
      <form method="post" class="newsletter-form" data-loading-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="form_type" value="newsletter">
        <div class="field">
          <label for="newsletter-email" class="hidden">Email address</label>
          <input id="newsletter-email" name="newsletter_email" type="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg"><span data-btn-label>Subscribe</span></button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
