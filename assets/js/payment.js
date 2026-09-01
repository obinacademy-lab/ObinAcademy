// Mobile-money payment widget. Attach to any element with [data-payment-widget]
// carrying data-course-id, data-initiate-url and (optionally) data-success-redirect.
// Reused for both course purchase and premium-upgrade flows.
(function () {
  const POLL_INTERVAL_MS = 2000;
  const MAX_POLLS = 90; // ~3 minutes

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  }

  function initWidget(root) {
    const courseId = root.dataset.courseId;
    const initiateUrl = root.dataset.initiateUrl;
    // Poll lives in the same api/ folder as initiate — derive it from that
    // URL rather than hardcoding a root-relative path, since the app isn't
    // necessarily hosted at the domain root (e.g. /OA/public/ locally).
    const pollUrl = initiateUrl.replace(/initiate-[^/]+\.php(?:\?.*)?$/, "poll-payment.php");
    const validateCouponUrl = initiateUrl.replace(/initiate-[^/]+\.php(?:\?.*)?$/, "validate-coupon.php");
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
    const couponInput = root.querySelector('[data-coupon-input]');
    const applyCouponBtn = root.querySelector('[data-action="apply-coupon"]');
    const couponMessage = root.querySelector('[data-coupon-message]');
    const priceDisplay = root.querySelector('[data-price-display]');
    const originalPriceHtml = priceDisplay ? priceDisplay.innerHTML : "";

    let pollCount = 0;
    let pollTimer = null;
    let pollToken = null;
    let appliedCouponCode = null;

    function setCouponMessage(msg, isError) {
      if (!couponMessage) return;
      couponMessage.textContent = msg || "";
      couponMessage.classList.toggle("hidden", !msg);
      couponMessage.classList.toggle("error", !!isError);
      couponMessage.classList.toggle("success", !isError && !!msg);
    }

    applyCouponBtn?.addEventListener("click", async () => {
      const code = couponInput?.value.trim() || "";
      if (!code) { setCouponMessage("Enter a coupon code first.", true); return; }
      applyCouponBtn.disabled = true;
      try {
        const res = await fetch(validateCouponUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ courseId, code, csrf_token: csrfToken() }),
        });
        const data = await res.json();
        if (!data.valid) {
          appliedCouponCode = null;
          if (priceDisplay) priceDisplay.innerHTML = originalPriceHtml;
          setCouponMessage(data.error || "Invalid coupon.", true);
        } else {
          appliedCouponCode = code;
          if (priceDisplay) {
            priceDisplay.innerHTML = `<span class="price-strike">${originalPriceHtml}</span>${data.discountedPriceFormatted}`;
          }
          setCouponMessage(`Coupon applied — you saved ${data.savingsFormatted}!`, false);
        }
      } catch {
        setCouponMessage("Couldn't check that coupon. Please try again.", true);
      } finally {
        applyCouponBtn.disabled = false;
      }
    });

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
        setError("");
        show("waiting");
        if (statusText) statusText.textContent = "Starting payment...";

        try {
          const body = { courseId, phone, csrf_token: csrfToken() };
          if (isGuest) { body.name = name; body.email = email; }
          if (appliedCouponCode) body.couponCode = appliedCouponCode;
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
