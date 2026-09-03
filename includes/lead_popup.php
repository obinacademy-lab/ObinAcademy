<?php
/**
 * Lead-capture popup markup — one overlay, two form variants (learner/
 * creator) plus a shared success state, all toggled by
 * assets/js/lead-capture.js. Rendered once per page (from footer.php) and
 * hidden until a trigger condition fires.
 */
?>
<div class="lead-overlay" data-lead-overlay>
  <div class="lead-modal" data-lead-modal role="dialog" aria-modal="true" aria-labelledby="lead-modal-title">
    <button type="button" class="lead-modal-close" data-lead-close aria-label="Close">&times;</button>

    <div data-lead-panel="learner">
      <div class="lead-modal-icon">🎓</div>
      <h2 id="lead-modal-title">Start Learning Today</h2>
      <p class="lead-sub">Join thousands of learners building valuable skills. Enter your details below and receive exclusive offers plus access to our newest courses.</p>
      <form class="guest-form" data-lead-form data-lead-type="learner">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="name" placeholder="Full Name" required>
        </div>
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
          <input type="email" name="email" placeholder="Email Address" required>
        </div>
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <input type="tel" name="phone" placeholder="Phone Number (Optional)">
        </div>
        <label class="lead-consent">
          <input type="checkbox" name="consent" required>
          <span>I agree to receive emails from Obin Academy and accept the <a href="<?= e(base_url('privacy.php')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</span>
        </label>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Start Learning</button>
        <p class="lead-form-error hidden" data-lead-error></p>
      </form>
    </div>

    <div data-lead-panel="creator" class="hidden">
      <div class="lead-modal-icon">🚀</div>
      <h2>Become a Course Creator</h2>
      <p class="lead-sub">Share your knowledge. Earn income. Build your personal brand. Create your instructor account today.</p>
      <form class="guest-form" data-lead-form data-lead-type="creator">
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="name" placeholder="Full Name" required>
        </div>
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
          <input type="email" name="email" placeholder="Email" required>
        </div>
        <div class="field-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <input type="tel" name="phone" placeholder="Phone" required>
        </div>
        <label class="lead-consent">
          <input type="checkbox" name="consent" required>
          <span>I agree to receive emails from Obin Academy and accept the <a href="<?= e(base_url('privacy.php')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</span>
        </label>
        <button type="submit" class="btn btn-gold btn-block btn-lg">Become a Creator</button>
        <p class="lead-form-error hidden" data-lead-error></p>
      </form>
    </div>

    <div data-lead-panel="success" class="hidden">
      <div class="lead-success-icon">✓</div>
      <h2 data-lead-success-title>You're in!</h2>
      <p class="lead-sub" data-lead-success-text>Check your email for next steps.</p>
      <a href="#" class="btn btn-block btn-lg lead-whatsapp-btn" data-lead-whatsapp target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:-3px; margin-right:6px;" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.39 1.26 4.81L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.25 1.29-1.98 1.44-.53.11-1.22.2-3.55-.76-2.98-1.24-4.89-4.24-5.04-4.44-.15-.2-1.21-1.6-1.21-3.06 0-1.46.76-2.17 1.03-2.47.27-.3.6-.37.8-.37h.57c.18 0 .43-.07.67.51.25.6.85 2.06.92 2.21.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.31.31-.13.62.18.3.8 1.32 1.72 2.14 1.18 1.05 2.18 1.38 2.5 1.53.32.15.5.13.68-.08.18-.2.78-.9.99-1.21.2-.3.4-.25.68-.15.27.1 1.73.82 2.03.97.3.15.5.22.57.35.07.13.07.75-.18 1.45z"/></svg>
        Continue on WhatsApp
      </a>
      <button type="button" class="btn btn-outline btn-block" data-lead-close-success style="margin-top:10px;">Maybe Later</button>
    </div>
  </div>
</div>
