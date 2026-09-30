// Header notification bell — polls for new comment replies/likes/course
// comments every ~20s. No WebSocket server on this host, so polling is the
// deliberate choice, same pattern as chat-poll.js (which polls every 4s for
// an active chat; this is lower-frequency since it's a background badge,
// not a live conversation someone is staring at).
(() => {
  if (!window.OBIN_LOGGED_IN) return;

  const trigger = document.querySelector("[data-notif-trigger]");
  const dot = document.querySelector("[data-notif-dot]");
  const list = document.querySelector("[data-notif-list]");
  const markReadBtn = document.querySelector("[data-notif-mark-read]");
  if (!trigger || !list) return;

  const POLL_INTERVAL_MS = 20000;
  const base = window.OBIN_BASE_URL || "";
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  let lastRenderedIds = "";

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function render(notifications) {
    // Skip re-rendering when nothing actually changed — avoids losing
    // hover/focus state on the dropdown mid-poll for no reason.
    const idsKey = notifications.map((n) => n.id + ":" + n.isRead).join(",");
    if (idsKey === lastRenderedIds) return;
    lastRenderedIds = idsKey;

    if (!notifications.length) {
      list.innerHTML = '<p class="muted small" style="padding:14px;">No notifications yet.</p>';
      return;
    }
    list.innerHTML = notifications
      .map((n) => {
        const tag = n.linkUrl ? "a" : "div";
        const href = n.linkUrl ? ` href="${escapeHtml(n.linkUrl)}"` : "";
        return `<${tag} class="site-notif-row${n.isRead ? "" : " unread"}"${href}><p>${escapeHtml(n.message)}</p><span>${escapeHtml(n.timeAgo)}</span></${tag}>`;
      })
      .join("");
  }

  async function poll() {
    if (document.hidden) return;
    try {
      const res = await fetch(base + "/api/get-notifications.php");
      if (!res.ok) return;
      const data = await res.json();
      dot?.classList.toggle("hidden", !(data.count > 0));
      markReadBtn?.classList.toggle("hidden", !(data.count > 0));
      render(data.notifications || []);
    } catch {
      // Silent — the next tick just retries.
    }
  }

  markReadBtn?.addEventListener("click", async () => {
    try {
      await fetch(base + "/api/mark-user-notifications-read.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ csrf_token: csrfToken }),
      });
      dot?.classList.add("hidden");
      markReadBtn.classList.add("hidden");
      list.querySelectorAll(".site-notif-row.unread").forEach((row) => row.classList.remove("unread"));
      lastRenderedIds = ""; // force the next poll's render through, even if the id list is unchanged
    } catch {
      // Leave it as-is — the user can try again.
    }
  });

  // Refresh right when someone actually opens the dropdown, not just on
  // the regular tick, so it never looks stale after they've been idle.
  trigger.addEventListener("click", poll);
  trigger.addEventListener("focus", poll);

  poll();
  setInterval(poll, POLL_INTERVAL_MS);
})();
