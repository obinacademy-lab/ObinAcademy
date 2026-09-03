// Smart lead-capture popup — triggers on real engagement signals (time,
// scroll, pageviews, exit intent, page context, return visits), never
// immediately on load. Shows once per visitor per cookie window; the
// visitor decides whether to hand over any details at all.
(() => {
  if (window.OBIN_LOGGED_IN) return;
  if (document.cookie.split("; ").some((c) => c.startsWith("oa_lead_status="))) return;

  const overlay = document.querySelector("[data-lead-overlay]");
  if (!overlay) return;
  const panels = {
    learner: overlay.querySelector('[data-lead-panel="learner"]'),
    creator: overlay.querySelector('[data-lead-panel="creator"]'),
    success: overlay.querySelector('[data-lead-panel="success"]'),
  };

  let shown = false;
  const cleanups = [];

  function setCookie(name, value, days) {
    document.cookie = `${name}=${value}; max-age=${days * 86400}; path=/; SameSite=Lax`;
  }

  function variantForPage() {
    return location.pathname.indexOf("/become-creator.php") !== -1 ? "creator" : "learner";
  }

  function showPopup() {
    if (shown) return;
    shown = true;
    cleanups.forEach((fn) => fn());
    const variant = variantForPage();
    Object.values(panels).forEach((p) => p && p.classList.add("hidden"));
    if (panels[variant]) panels[variant].classList.remove("hidden");
    overlay.classList.add("open");
    requestAnimationFrame(() => overlay.classList.add("visible"));
    setCookie("oa_lead_status", "shown", 7);
  }

  function closePopup() {
    overlay.classList.remove("visible");
    setTimeout(() => overlay.classList.remove("open"), 250);
  }

  overlay.addEventListener("click", (e) => { if (e.target === overlay) closePopup(); });
  overlay.querySelectorAll("[data-lead-close], [data-lead-close-success]").forEach((btn) => btn.addEventListener("click", closePopup));

  // --- triggers ---------------------------------------------------------
  const t25s = setTimeout(showPopup, 25000);
  cleanups.push(() => clearTimeout(t25s));

  const onCreatorOrCoursePage = /\/become-creator\.php/.test(location.pathname) || /\/courses\/view\.php/.test(location.pathname);
  if (onCreatorOrCoursePage) {
    const tPage = setTimeout(showPopup, 8000);
    cleanups.push(() => clearTimeout(tPage));
  }

  const pageviewCount = (parseInt(sessionStorage.getItem("oa_pv_count") || "0", 10)) + 1;
  sessionStorage.setItem("oa_pv_count", String(pageviewCount));
  if (pageviewCount >= 3) {
    const t3rd = setTimeout(showPopup, 2000);
    cleanups.push(() => clearTimeout(t3rd));
  }

  let scrollTicking = false;
  function onScroll() {
    if (scrollTicking) return;
    scrollTicking = true;
    requestAnimationFrame(() => {
      const doc = document.documentElement;
      const scrollable = doc.scrollHeight - doc.clientHeight;
      const pct = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 100;
      if (pct >= 70) showPopup();
      scrollTicking = false;
    });
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  cleanups.push(() => window.removeEventListener("scroll", onScroll));

  function onMouseOut(e) {
    if (e.clientY <= 0) showPopup();
  }
  document.addEventListener("mouseout", onMouseOut);
  cleanups.push(() => document.removeEventListener("mouseout", onMouseOut));

  document.addEventListener("obin:pageview-tracked", (e) => {
    if (e.detail && e.detail.isReturning) {
      const tReturn = setTimeout(showPopup, 4000);
      cleanups.push(() => clearTimeout(tReturn));
    }
  });

  // --- form submission ----------------------------------------------------
  overlay.querySelectorAll("[data-lead-form]").forEach((form) => {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const errorEl = form.querySelector("[data-lead-error]");
      errorEl.classList.add("hidden");
      const fd = new FormData(form);
      const payload = {
        name: fd.get("name"),
        email: fd.get("email"),
        phone: fd.get("phone") || "",
        consent: fd.get("consent") === "on",
        leadType: form.dataset.leadType,
        referrer: document.referrer || null,
      };
      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      try {
        const res = await fetch((window.OBIN_BASE_URL || "") + "/api/capture-lead.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.error) {
          errorEl.textContent = data.error;
          errorEl.classList.remove("hidden");
          submitBtn.disabled = false;
          return;
        }

        setCookie("oa_lead_status", "submitted", 180);
        const successTitle = overlay.querySelector("[data-lead-success-title]");
        const successText = overlay.querySelector("[data-lead-success-text]");
        const waLink = overlay.querySelector("[data-lead-whatsapp]");
        if (data.leadType === "creator") {
          successTitle.textContent = "You're on your way!";
          successText.textContent = "Check your email for how to finish setting up as a creator.";
        } else {
          successTitle.textContent = "You're in!";
          successText.textContent = "Check your email for course recommendations and exclusive offers.";
        }
        if (waLink) waLink.href = data.whatsappUrl || "#";
        Object.values(panels).forEach((p) => p && p.classList.add("hidden"));
        if (panels.success) panels.success.classList.remove("hidden");
      } catch {
        errorEl.textContent = "Something went wrong. Please try again.";
        errorEl.classList.remove("hidden");
        submitBtn.disabled = false;
      }
    });
  });
})();
