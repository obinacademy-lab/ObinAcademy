// Obin Academy addition — not part of the upstream PDF.js distribution.
// viewer.html is loaded as `viewer.html?file=...&download=0|1`. When
// download=0, hide the save/print UI (via obin-overrides.css) and swallow
// the Ctrl/Cmd+S and Ctrl/Cmd+P shortcuts PDF.js itself binds to those same
// actions, before PDF.js's own bubble-phase listener gets a chance to run.
(function () {
  const params = new URLSearchParams(window.location.search);
  const canDownload = params.get("download") === "1";
  if (canDownload) return;

  function markNoDownload() {
    document.body.classList.add("obin-no-download");
  }
  if (document.body) markNoDownload();
  else document.addEventListener("DOMContentLoaded", markNoDownload);

  window.addEventListener(
    "keydown",
    (event) => {
      const key = event.key ? event.key.toLowerCase() : "";
      if ((event.ctrlKey || event.metaKey) && (key === "s" || key === "p")) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    },
    { capture: true }
  );
})();
