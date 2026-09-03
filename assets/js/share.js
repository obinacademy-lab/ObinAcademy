// "Share Course" button popover: toggle open/close, copy-to-clipboard with a
// toast, and a fallback copy path for browsers without Clipboard API access
// (e.g. non-HTTPS contexts). Works for any number of [data-share-wrap]
// widgets on a page.
(function () {
  let toastEl = null;

  function showToast(text) {
    if (!toastEl) {
      toastEl = document.createElement("div");
      toastEl.className = "share-toast";
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = text;
    requestAnimationFrame(() => toastEl.classList.add("show"));
    clearTimeout(toastEl._hideTimer);
    toastEl._hideTimer = setTimeout(() => toastEl.classList.remove("show"), 2600);
  }

  async function copyText(text) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      let ok = false;
      try { ok = document.execCommand("copy"); } catch {}
      document.body.removeChild(ta);
      return ok;
    }
  }

  function closeAllMenus() {
    document.querySelectorAll("[data-share-menu]").forEach((menu) => {
      menu.hidden = true;
      menu.previousElementSibling?.setAttribute("aria-expanded", "false");
    });
  }

  document.addEventListener("click", async (e) => {
    const toggle = e.target.closest("[data-share-toggle]");
    if (toggle) {
      const menu = toggle.nextElementSibling;
      const willOpen = menu.hidden;
      closeAllMenus();
      menu.hidden = !willOpen;
      toggle.setAttribute("aria-expanded", String(willOpen));
      return;
    }

    const copyBtn = e.target.closest("[data-share-copy]");
    if (copyBtn) {
      const url = copyBtn.dataset.shareCopy;
      const hint = copyBtn.dataset.shareHint || "Link copied.";
      const ok = await copyText(url);
      showToast(ok ? hint : "Couldn't copy — copy the URL from your address bar instead.");
      closeAllMenus();
      return;
    }

    if (!e.target.closest("[data-share-wrap]")) closeAllMenus();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeAllMenus();
  });
})();
