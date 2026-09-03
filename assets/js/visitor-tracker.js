// First-party visitor/session tracking — no external service. Records one
// pageview per load, tracks scroll depth + time on page, and reports the
// final numbers via sendBeacon so they land even on tab close (not just a
// clean navigation). See includes/analytics.php for the server side.
(() => {
  const startedAt = Date.now();
  let pageviewId = null;
  let maxScrollPct = 0;
  let reported = false;

  function currentScrollPct() {
    const doc = document.documentElement;
    const scrollable = doc.scrollHeight - doc.clientHeight;
    if (scrollable <= 0) return 100;
    return Math.min(100, Math.round(((window.scrollY || doc.scrollTop) / scrollable) * 100));
  }

  let scrollTicking = false;
  window.addEventListener("scroll", () => {
    if (scrollTicking) return;
    scrollTicking = true;
    requestAnimationFrame(() => {
      maxScrollPct = Math.max(maxScrollPct, currentScrollPct());
      scrollTicking = false;
    });
  }, { passive: true });

  function reportEnd() {
    if (reported || !pageviewId) return;
    reported = true;
    const payload = JSON.stringify({
      pageviewId,
      timeOnPageSeconds: Math.round((Date.now() - startedAt) / 1000),
      scrollDepthPct: maxScrollPct,
    });
    const url = (window.OBIN_BASE_URL || "") + "/api/track-pageview-end.php";
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, new Blob([payload], { type: "application/json" }));
    } else {
      fetch(url, { method: "POST", headers: { "Content-Type": "application/json" }, body: payload, keepalive: true });
    }
  }

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") reportEnd();
  });
  window.addEventListener("pagehide", reportEnd);

  fetch((window.OBIN_BASE_URL || "") + "/api/track-pageview.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ path: location.pathname + location.search, referrer: document.referrer || null }),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data && data.pageviewId) pageviewId = data.pageviewId;
      document.dispatchEvent(new CustomEvent("obin:pageview-tracked", { detail: data || {} }));
    })
    .catch(() => {});
})();
