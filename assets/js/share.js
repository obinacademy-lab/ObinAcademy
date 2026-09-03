// "Share Course" button popover: toggle open/close, copy-to-clipboard with a
// toast, and a fallback copy path for browsers without Clipboard API access
// (e.g. non-HTTPS contexts). Works for any number of [data-share-wrap]
// widgets on a page.
//
// The popover is position:fixed, placed here via JS rather than pinned with
// plain CSS (top/left relative to the button) — the button lives inside
// .course-hero, which needs overflow:hidden for its glow effect, and that
// clips anything position:absolute the moment it grows past the hero's
// bottom edge. Fixed positioning escapes that clipping entirely.
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

  // Places the (already-unhidden) menu below the button, clamped so it
  // never runs off any edge of the viewport — flips above the button if
  // there isn't enough room underneath.
  function positionMenu(toggle, menu) {
    const margin = 12;
    const gap = 10;
    const toggleRect = toggle.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const menuWidth = menuRect.width || 264;
    const menuHeight = menuRect.height;

    let left = toggleRect.left;
    if (left + menuWidth > window.innerWidth - margin) left = window.innerWidth - menuWidth - margin;
    if (left < margin) left = margin;

    let top = toggleRect.bottom + gap;
    if (top + menuHeight > window.innerHeight - margin) {
      const above = toggleRect.top - menuHeight - gap;
      top = above > margin ? above : Math.max(margin, window.innerHeight - menuHeight - margin);
    }

    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
  }

  document.addEventListener("click", async (e) => {
    const toggle = e.target.closest("[data-share-toggle]");
    if (toggle) {
      const menu = toggle.nextElementSibling;
      const willOpen = menu.hidden;
      closeAllMenus();
      if (willOpen) {
        menu.hidden = false;
        positionMenu(toggle, menu);
      }
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

  // A fixed-position menu doesn't move with the page, so once the button
  // that opened it scrolls away the popover would look detached — closing
  // it on scroll/resize is simpler and more predictable than re-tracking
  // the button's position continuously. Scrolling inside the menu itself
  // (its own overflow-y: auto, for very short viewports) must not trigger
  // this — capture:true sees that inner scroll too, so it's excluded explicitly.
  window.addEventListener("scroll", (e) => {
    if (e.target.closest && e.target.closest("[data-share-menu]")) return;
    closeAllMenus();
  }, { passive: true, capture: true });
  window.addEventListener("resize", closeAllMenus);
})();
