// Mobile-money payment widget for joining a campus. Attach to an element with [data-payment-widget]
// carrying data-campus-id, data-plan (MONTHLY or YEARLY, updated by the plan picker), data-initiate-url
// and optionally data-success-redirect. States: phone, waiting, success, failed.
(function () {
  const POLL_INTERVAL_MS = 2000;
  const MAX_POLLS = 90; // ~3 minutes

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  }

  function initWidget(root) {
    const initiateUrl = root.dataset.initiateUrl;
    // Poll lives in the same api/ folder as initiate, so the app works under a subpath too (/OA/public/ locally).
    const pollUrl = initiateUrl.replace(/initiate-[^/]+\.php(?:\?.*)?$/, "poll-payment.php");
    const successRedirect = root.dataset.successRedirect || "";
    const states = {
      phone: root.querySelector('[data-state="phone"]'),
      waiting: root.querySelector('[data-state="waiting"]'),
      success: root.querySelector('[data-state="success"]'),
      failed: root.querySelector('[data-state="failed"]'),
    };
    const errorBox = root.querySelector("[data-error]");
    const statusText = root.querySelector("[data-status-text]");
    const failText = root.querySelector("[data-fail-text]");
    const phoneInput = root.querySelector("[data-phone-input]");
    let pollCount = 0;
    let pollTimer = null;

    function show(state) {
      Object.values(states).forEach((el) => el && el.classList.add("hidden"));
      if (states[state]) states[state].classList.remove("hidden");
    }

    function setError(msg) {
      if (!errorBox) return;
      errorBox.textContent = msg || "";
      errorBox.classList.toggle("hidden", !msg);
    }

    root.querySelectorAll('[data-action="pay"]').forEach((btn) =>
      btn.addEventListener("click", async () => {
        const phone = phoneInput?.value.trim() || "";
        if (phone.length < 9) {
          setError("Enter the mobile money number to pay with.");
          return;
        }
        setError("");
        show("waiting");
        if (statusText) statusText.textContent = "Starting payment...";
        try {
          const res = await fetch(initiateUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ campusId: root.dataset.campusId, plan: root.dataset.plan || "MONTHLY", phone, csrf_token: csrfToken() }),
          });
          const data = await res.json();
          if (data.error) {
            setError(data.error);
            show("phone");
            return;
          }
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
        const res = await fetch(pollUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ paymentId, csrf_token: csrfToken() }),
        });
        const data = await res.json();
        if (data.status === "SUCCESS") {
          clearInterval(pollTimer);
          show("success");
          setTimeout(() => { successRedirect ? (window.location.href = successRedirect) : window.location.reload(); }, 300);
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
        // a passing network hiccup: keep polling until MAX_POLLS
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
