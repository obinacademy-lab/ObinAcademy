<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/platform.php';
require __DIR__ . '/includes/bright.php';

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
    'creator_support' => 'Teaching at Obin Academy',
    'technical_support' => 'Technical Support',
    'billing' => 'Billing',
    'partnership' => 'Partnership',
    'business_inquiry' => 'Business Inquiry',
    'report_problem' => 'Report a Problem',
];

$ADMIN_EMAIL = 'obinacademy@gmail.com';
$SUPPORT_EMAIL = 'info@obinacademy.site';
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
    ['How do I join a campus?', 'Open the campus you want, choose monthly or yearly, and pay with MTN or Airtel Mobile Money. You approve a prompt on your phone and your campus opens straight away.'],
    ['Can I learn from my phone?', "Yes. Lessons, PDF guides, live classes and the community all work in the browser on any phone, tablet or laptop. There's nothing extra to install."],
    ['What is the difference between monthly and yearly?', 'Monthly gives you 30 days of access at a time. Yearly gives you twelve months at once and costs less overall. Nothing renews by itself: we remind you by email before your access ends.'],
    ['How do certificates work?', 'Complete every lesson of your campus\'s twelve stages and your certificate is issued automatically, ready to view and share.'],
    ['What payment methods are accepted?', 'MTN Mobile Money and Airtel Money. You approve a payment prompt on your phone, so no bank account or card is needed.'],
    ['Can I request a refund?', "Refund requests are reviewed case by case. If you were charged in error, contact us within 48 hours of paying and we'll sort it out."],
    ['How do I become a professor?', 'Apply on the Teach page. We read every application and call the people who are a fit to talk through the role.'],
    ['How long does support take to respond?', "Email and contact-form requests are answered within 24 hours. WhatsApp during business hours (Mon–Fri, 8:00 AM–6:00 PM EAT) is usually much faster."],
];

$stats = get_platform_stats();

$pageTitle = 'Contact & Support — Obin Academy';
$pageDescription = "Get in touch with Obin Academy — support for learners, future professors and businesses. Real human support, replies within 24 hours.";
require __DIR__ . '/includes/header.php';
br_sprite();
?>
<div class="br">
  <section class="b-ph">
    <div class="b-w">
      <div>
        <span class="b-eb">Support &amp; contact</span>
        <h1>We're here <i>to help.</i></h1>
        <p class="b-lead">Whether you are a learner, a future professor, a business or a partner, our team is ready to answer your questions.</p>
        <div class="b-acts"><a class="b-btn p lg" href="#contact-form">Contact support <?= br_ic('arrow') ?></a><a class="b-btn o lg" href="<?= e(base_url('campuses.php')) ?>">See the campuses</a></div>
      </div>
    </div>
  </section>

  <section class="b-sec alt" id="channels"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Get in touch</span><h2>Multiple ways <i>to reach us.</i></h2><p class="b-lead">Pick whatever channel is fastest for you. Every path leads to a real person.</p></div>
    <div class="b-contacts">
      <div><i><?= br_ic('mail') ?></i><b>Email support</b><span>General inquiries and platform support. Replies within 24 hours.</span><span class="b-mono"><a href="mailto:<?= e($SUPPORT_EMAIL) ?>"><?= e($SUPPORT_EMAIL) ?></a></span><a class="b-btn o" href="mailto:<?= e($SUPPORT_EMAIL) ?>">Send email</a></div>
      <div><i><?= br_ic('wa') ?></i><b>WhatsApp support</b><span>Need quick assistance? Chat directly with our support team.</span><span class="b-mono"><?= e($PHONE_DISPLAY) ?></span><a class="b-btn o" href="<?= e($whatsappHref) ?>" target="_blank" rel="noopener noreferrer">WhatsApp us</a></div>
      <div><i><?= br_ic('chat') ?></i><b>Live chat</b><span>Chat instantly with our support team during business hours.</span><span class="b-mono">Mon – Fri, 8:00 – 18:00 EAT</span><a class="b-btn o" href="<?= e($whatsappHref) ?>" target="_blank" rel="noopener noreferrer">Start chat</a></div>
      <div><i><?= br_ic('call') ?></i><b>Phone support</b><span>Prefer to talk it through? Mon to Fri, 8:00 AM to 6:00 PM (EAT).</span><span class="b-mono"><a href="tel:+<?= e($WHATSAPP_NUMBER) ?>"><?= e($PHONE_DISPLAY) ?></a></span><a class="b-btn o" href="tel:+<?= e($WHATSAPP_NUMBER) ?>">Call now</a></div>
    </div>
  </div></section>

  <section class="b-sec" id="contact-form" style="scroll-margin-top:90px"><div class="b-w">
    <div class="b-split2" style="align-items:start">
      <div>
        <span class="b-eb">Send a message</span>
        <h2 style="font-size:clamp(30px,3.6vw,48px);line-height:1.06;margin:12px 0 18px">Tell us how <i>we can help.</i></h2>
        <p class="b-lead">Fill out the form and our team will get back to you within 24 hours.</p>
        <div class="b-promise" style="margin-top:28px">
          <div><b>I am a learner</b><span>Campus access, certificates, learning help and payments.</span><a class="b-link" href="?type=student_support#contact-form">Get learner support →</a></div>
          <div><b>I want to teach</b><span>Leading a campus, your application, live classes and professor pay.</span><a class="b-link" href="<?= e(base_url('teach.php')) ?>">Apply to teach →</a></div>
          <div><b>Partnerships and press</b><span>Schools, organisations, interviews and brand collaborations.</span><a class="b-link" href="?type=partnership#contact-form">Partner with us →</a></div>
        </div>
      </div>
      <form method="post" class="b-formcard" data-loading-submit>
        <?= csrf_field() ?>
        <input type="hidden" name="form_type" value="contact">
        <div class="b-row2">
          <label class="b-fld" for="cf-name">Full name<input id="cf-name" name="name" type="text" autocomplete="name" required></label>
          <label class="b-fld" for="cf-email">Email address<input id="cf-email" name="email" type="email" autocomplete="email" required></label>
        </div>
        <div class="b-row2">
          <label class="b-fld" for="cf-phone">Phone (optional)<input id="cf-phone" name="phone" type="tel" autocomplete="tel" placeholder="+256 7XX XXX XXX"></label>
          <label class="b-fld" for="cf-inquiry">Inquiry type
            <select id="cf-inquiry" name="inquiry_type" required>
              <option value="" disabled <?= $presetInquiry === '' ? 'selected' : '' ?>>Choose one…</option>
              <?php foreach ($inquiryTypes as $value => $label): ?><option value="<?= e($value) ?>" <?= $presetInquiry === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </label>
        </div>
        <label class="b-fld" for="cf-subject">Subject<input id="cf-subject" name="subject" type="text" required></label>
        <label class="b-fld" for="cf-message">Message<textarea id="cf-message" name="message" rows="6" required placeholder="How can we help?"></textarea></label>
        <button type="submit" class="b-btn p full lg"><span data-btn-label style="display:inline-flex;align-items:center;gap:8px">Send message <?= br_ic('arrow') ?></span></button>
      </form>
    </div>
  </div></section>

  <section class="b-sec alt" id="help"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Help center</span><h2>What learners <i>ask most.</i></h2></div>
    <div class="b-helpgrid">
      <?php foreach (['How to create an account', 'How to join a campus', 'Monthly or yearly', 'Payment methods', 'Certificates', 'Reset your password'] as $h): ?><a href="#faq"><?= e($h) ?><?= br_ic('arrow') ?></a><?php endforeach; ?>
    </div>
  </div></section>

  <section class="b-sec" id="faq" style="scroll-margin-top:90px"><div class="b-w">
    <div class="b-sh"><span class="b-eb">Questions</span><h2>Frequently <i>asked.</i></h2></div>
    <div class="b-faq" style="max-width:820px;margin:0 auto">
      <?php foreach ($faqs as $i => [$q, $a]): ?><details<?= $i === 0 ? ' open' : '' ?>><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?>
    </div>
  </div></section>

  <section class="b-sec alt"><div class="b-w">
    <div class="b-split2">
      <div>
        <span class="b-eb">Visit us</span>
        <h2 style="font-size:clamp(30px,3.6vw,48px);line-height:1.06;margin:12px 0 18px">Head <i>office.</i></h2>
        <ul style="display:grid;gap:18px;margin-top:8px">
          <li class="b-tick" style="align-items:flex-start"><?= br_ic('globe') ?><span><b>Location</b><br>Kampala, Uganda</span></li>
          <li class="b-tick" style="align-items:flex-start"><?= br_ic('check') ?><span><b>Business hours</b><br>Monday – Friday, 8:00 AM – 6:00 PM (EAT)</span></li>
        </ul>
        <a class="b-btn dk" style="margin-top:26px" href="https://www.google.com/maps/search/?api=1&query=Kampala%2C+Uganda" target="_blank" rel="noopener noreferrer">Get directions <?= br_ic('arrow') ?></a>
      </div>
      <div class="b-map"><iframe src="https://www.google.com/maps?q=Kampala,+Uganda&output=embed" title="Map showing Kampala, Uganda" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
    </div>
  </div></section>

  <section class="b-sec" style="padding:56px 0"><div class="b-w">
    <div class="b-sh" style="margin-bottom:28px"><span class="b-eb">Stay connected</span><h2>Follow <i>us.</i></h2><p class="b-lead">Updates on new campuses, live classes and behind-the-scenes moments.</p></div>
<div class="social-icons-light" style="justify-content:center; gap:14px; margin-top:28px;">
        <a href="https://www.facebook.com/profile.php?id=61591414895842" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M13.5 21v-7.5h2.5l.5-3H13.5V8.5c0-.9.25-1.5 1.53-1.5H16.5V4.34C16.19 4.3 15.13 4.2 14 4.2c-2.34 0-3.94 1.43-3.94 4.05V10.5H7.5v3H10V21h3.5z"/></svg></a>
        <a href="https://www.instagram.com/obinacademyofficial/?hl=en" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><svg viewBox="0 0 24 24"><path d="M12 8.4a3.6 3.6 0 1 0 0 7.2 3.6 3.6 0 0 0 0-7.2zM12 2c-2.7 0-3.1 0-4.1.1-1.1 0-1.8.2-2.4.5A4.8 4.8 0 0 0 2.6 5.5c-.3.6-.5 1.3-.5 2.4C2 8.9 2 9.3 2 12s0 3.1.1 4.1c.1 1.1.2 1.8.5 2.4a4.8 4.8 0 0 0 2.9 2.9c.6.3 1.3.5 2.4.5C8.9 22 9.3 22 12 22s3.1 0 4.1-.1c1.1-.1 1.8-.2 2.4-.5a4.8 4.8 0 0 0 2.9-2.9c.3-.6.5-1.3.5-2.4.1-1 .1-1.4.1-4.1s0-3.1-.1-4.1c-.1-1.1-.2-1.8-.5-2.4a4.8 4.8 0 0 0-2.9-2.9c-.6-.3-1.3-.5-2.4-.5C15.1 2 14.7 2 12 2zm0 1.8c2.6 0 3 0 4 .1.9 0 1.5.2 1.8.3.5.2.8.4 1.1.7.3.3.5.6.7 1.1.1.3.3.9.3 1.8.1 1 .1 1.4.1 4s0 3-.1 4c0 .9-.2 1.5-.3 1.8-.2.5-.4.8-.7 1.1-.3.3-.6.5-1.1.7-.3.1-.9.3-1.8.3-1 .1-1.4.1-4 .1s-3 0-4-.1c-.9 0-1.5-.2-1.8-.3a3 3 0 0 1-1.1-.7 3 3 0 0 1-.7-1.1c-.1-.3-.3-.9-.3-1.8-.1-1-.1-1.4-.1-4s0-3 .1-4c0-.9.2-1.5.3-1.8.2-.5.4-.8.7-1.1.3-.3.6-.5 1.1-.7.3-.1.9-.3 1.8-.3 1-.1 1.4-.1 4-.1z"/></svg></a>
        <a href="https://www.tiktok.com/@obinacademy.site" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><svg viewBox="0 0 24 24"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
        <a href="https://www.youtube.com/@obinacademy" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><svg viewBox="0 0 24 24"><path d="M23.5 6.19a2.99 2.99 0 0 0-2.11-2.12C19.51 3.55 12 3.55 12 3.55s-7.51 0-9.39.52A2.99 2.99 0 0 0 .5 6.19 31.26 31.26 0 0 0 0 12a31.26 31.26 0 0 0 .5 5.81 2.99 2.99 0 0 0 2.11 2.12c1.88.52 9.39.52 9.39.52s7.51 0 9.39-.52a2.99 2.99 0 0 0 2.11-2.12A31.26 31.26 0 0 0 24 12a31.26 31.26 0 0 0-.5-5.81zM9.75 15.57V8.43L15.82 12z"/></svg></a>
        <a href="https://x.com/obinacademy" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)"><svg viewBox="0 0 24 24"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24H16.17l-5.21-6.82-5.96 6.82H1.68l7.73-8.84L1.25 2.25h6.82l4.71 6.23zm-1.16 17.52h1.83L7.08 4.13H5.12z"/></svg></a>
      </div>
  </div></section>

  <section class="b-sec alt" id="newsletter" style="scroll-margin-top:90px"><div class="b-w">
    <div class="b-sh" style="margin-bottom:28px"><span class="b-eb">Stay updated</span><h2>News of new <i>campuses.</i></h2><p class="b-lead">Intake dates, new campuses and learning resources, in your inbox.</p></div>
    <form method="post" class="b-news" data-loading-submit>
      <?= csrf_field() ?>
      <input type="hidden" name="form_type" value="newsletter">
      <label class="b-fld" for="newsletter-email" style="flex:1;margin:0"><span class="b-sr">Email address</span><input id="newsletter-email" name="newsletter_email" type="email" placeholder="you@example.com" required></label>
      <button type="submit" class="b-btn p lg"><span data-btn-label>Subscribe</span></button>
    </form>
  </div></section>

  <section class="b-sec" style="padding-top:20px"><div class="b-w">
    <div class="b-cta"><div><h2>Teach what you know. Lead a campus.</h2><p>We are building a faculty of people who have done the work. Apply, and we will call you to talk it through.</p></div><a class="b-btn a lg" href="<?= e(base_url('teach.php')) ?>">Apply to teach <?= br_ic('arrow') ?></a></div>
  </div></section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>