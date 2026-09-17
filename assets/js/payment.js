// Mobile-money payment widget. Attach to any element with [data-payment-widget]
// carrying data-course-id, data-creator-id (for a school subscription), or
// data-tier, plus data-initiate-url and (optionally) data-success-redirect.
// Reused for course purchase, premium-upgrade, and school-subscription flows.
(function () {
  const POLL_INTERVAL_MS = 2000;
  const MAX_POLLS = 90; // ~3 minutes

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  }

  function initWidget(root) {
    const courseId = root.dataset.courseId;
    const tier = root.dataset.tier;
    const creatorId = root.dataset.creatorId;
    const bundleId = root.dataset.bundleId;
    const initiateUrl = root.dataset.initiateUrl;
    // Poll lives in the same api/ folder as initiate — derive it from that
    // URL rather than hardcoding a root-relative path, since the app isn't
    // necessarily hosted at the domain root (e.g. /OA/public/ locally).
    const pollUrl = initiateUrl.replace(/initiate-[^/]+\.php(?:\?.*)?$/, "poll-payment.php");
    const successRedirect = root.dataset.successRedirect || "";
    const isGuest = root.dataset.guest === "1";
    const states = {
      idle: root.querySelector('[data-state="idle"]'),
      phone: root.querySelector('[data-state="phone"]'),
      waiting: root.querySelector('[data-state="waiting"]'),
      success: root.querySelector('[data-state="success"]'),
      failed: root.querySelector('[data-state="failed"]'),
    };
    const errorBox = root.querySelector('[data-error]');
    const statusText = root.querySelector('[data-status-text]');
    const failText = root.querySelector('[data-fail-text]');
    const phoneInput = root.querySelector('[data-phone-input]');
    const nameInput = root.querySelector('[data-name-input]');
    const emailInput = root.querySelector('[data-email-input]');
    // Gift-a-course widget only — who the course is FOR, not the (already
    // logged-in) buyer paying for it. Distinct from nameInput/emailInput
    // above, which is the guest-checkout buyer's own info.
    const recipientNameInput = root.querySelector('[data-recipient-name-input]');
    const recipientEmailInput = root.querySelector('[data-recipient-email-input]');

    // "Have a coupon code?" box — a sibling of this widget, not inside it,
    // since it needs to update the top-of-panel price display too, not just
    // the pay button's own price span. Applying a coupon here doesn't touch
    // the server beyond a read-only preview (api/preview-coupon.php); the
    // code itself is only actually redeemed once the payment succeeds.
    let appliedCoupon = null;
    const couponBox = root.parentElement?.querySelector("[data-coupon-box]");
    if (couponBox) {
      const couponToggle = couponBox.querySelector("[data-coupon-toggle]");
      const couponRow = couponBox.querySelector("[data-coupon-row]");
      const couponInput = couponBox.querySelector("[data-coupon-input]");
      const couponApplyBtn = couponBox.querySelector("[data-coupon-apply]");
      const couponMsg = couponBox.querySelector("[data-coupon-msg]");
      const csrfToken2 = document.querySelector('meta[name="csrf-token"]')?.content ?? "";

      couponToggle?.addEventListener("click", () => {
        couponRow.classList.toggle("hidden");
        if (!couponRow.classList.contains("hidden")) couponInput?.focus();
      });

      couponApplyBtn?.addEventListener("click", async () => {
        const code = couponInput?.value.trim() || "";
        if (!code) return;
        couponApplyBtn.disabled = true;
        try {
          const res = await fetch(couponBox.dataset.previewUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ courseId: couponBox.dataset.courseId, code, csrf_token: csrfToken2 }),
          });
          const data = await res.json();
          couponMsg.hidden = false;
          if (data.error) {
            couponMsg.textContent = data.error;
            couponMsg.classList.remove("is-success");
            couponMsg.classList.add("is-error");
            appliedCoupon = null;
            return;
          }
          appliedCoupon = code;
          couponMsg.textContent = `Coupon applied — new price ${data.finalPriceFormatted}`;
          couponMsg.classList.remove("is-error");
          couponMsg.classList.add("is-success");
          const payAmountEl = root.querySelector("[data-pay-amount]");
          const topPriceEl = document.querySelector("[data-top-price-amount]");
          if (payAmountEl) payAmountEl.textContent = data.finalPriceFormatted;
          if (topPriceEl) topPriceEl.textContent = data.finalPriceFormatted;
          couponInput.disabled = true;
          couponApplyBtn.textContent = "Applied";
        } catch {
          couponMsg.hidden = false;
          couponMsg.textContent = "Something went wrong. Please try again.";
        } finally {
          couponApplyBtn.disabled = appliedCoupon !== null;
        }
      });
    }

    let pollCount = 0;
    let pollTimer = null;
    let pollToken = null;

    function show(state) {
      Object.values(states).forEach((el) => el && el.classList.add("hidden"));
      if (states[state]) states[state].classList.remove("hidden");
    }

    function setError(msg) {
      if (errorBox) {
        errorBox.textContent = msg || "";
        errorBox.classList.toggle("hidden", !msg);
      }
    }

    root.querySelectorAll('[data-action="start"]').forEach((btn) =>
      btn.addEventListener("click", () => { setError(""); show("phone"); })
    );

    root.querySelectorAll('[data-action="pay"]').forEach((btn) =>
      btn.addEventListener("click", async () => {
        const phone = phoneInput?.value.trim() || "";
        if (phone.length < 9) return;
        const name = nameInput?.value.trim() || "";
        const email = emailInput?.value.trim() || "";
        if (isGuest && (!name || !email)) {
          setError("Enter your name and email address.");
          return;
        }
        const recipientName = recipientNameInput?.value.trim() || "";
        const recipientEmail = recipientEmailInput?.value.trim() || "";
        if (recipientNameInput && (!recipientName || !recipientEmail)) {
          setError("Enter the recipient's name and email address.");
          return;
        }
        setError("");
        show("waiting");
        if (statusText) statusText.textContent = "Starting payment...";

        try {
          const body = { courseId, tier, creatorId, bundleId, phone, csrf_token: csrfToken() };
          if (isGuest) { body.name = name; body.email = email; }
          if (recipientNameInput) { body.recipientName = recipientName; body.recipientEmail = recipientEmail; }
          if (appliedCoupon) body.couponCode = appliedCoupon;
          const res = await fetch(initiateUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
          });
          const data = await res.json();
          if (data.error) {
            setError(data.error);
            show("phone");
            return;
          }
          if (data.pollToken) pollToken = data.pollToken;
          if (statusText) statusText.textContent = "Check your phone and approve the mobile money prompt.";
          pollCount = 0;
          pollStatus(data.paymentId);
          pollTimer = setInterval(() => pollStatus(data.paymentId), POLL_INTERVAL_MS);
        } catch {
          setError("Something went wrong. Please try again.");
          show("phone");
        }
      })
    );

    async function pollStatus(paymentId) {
      pollCount += 1;
      try {
        const body = { paymentId, csrf_token: csrfToken() };
        if (isGuest) body.pollToken = pollToken;
        const res = await fetch(pollUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.status === "SUCCESS") {
          clearInterval(pollTimer);
          show("success");
          const goTo = data.accessUrl || successRedirect;
          if (goTo) {
            setTimeout(() => { window.location.href = goTo; }, 300);
          } else {
            setTimeout(() => window.location.reload(), 300);
          }
        } else if (data.status === "FAILED") {
          clearInterval(pollTimer);
          if (failText) failText.textContent = data.statusMessage || "The payment was not completed.";
          show("failed");
        } else if (pollCount >= MAX_POLLS) {
          clearInterval(pollTimer);
          if (failText) failText.textContent = "This is taking longer than expected. Please try again.";
          show("failed");
        }
      } catch {
        // transient network hiccup — keep polling until MAX_POLLS
      }
    }

    root.querySelectorAll('[data-action="retry"]').forEach((btn) =>
      btn.addEventListener("click", () => { setError(""); show("phone"); })
    );
  }

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-payment-widget]").forEach(initWidget);
  });
})();
