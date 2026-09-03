// Polling group chat for study groups — same interval-polling pattern as
// payment.js's payment-status check, just recurring instead of one-shot.
// No WebSocket server on this host, so polling is the deliberate v1 choice.
(function () {
  const POLL_INTERVAL_MS = 4000;

  const log = document.querySelector("[data-group-chat-log]");
  const form = document.querySelector("[data-group-chat-form]");
  if (!log || !form) return;

  const baseUrl = window.OBIN_BASE_URL || "";
  const groupId = log.dataset.groupId;
  const myUserId = Number(log.dataset.myUserId);
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  const input = form.querySelector('input[name="body"]');

  let lastId = 0;
  log.querySelectorAll("[data-message-id]").forEach((el) => {
    lastId = Math.max(lastId, Number(el.dataset.messageId));
  });

  function scrollToBottom() {
    log.scrollTop = log.scrollHeight;
  }
  scrollToBottom();

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function initials(name) {
    return (name || "?").trim().slice(0, 1).toUpperCase();
  }

  function renderMessage(m) {
    const mine = Number(m.author_id) === myUserId;
    const wrap = document.createElement("div");
    wrap.className = "chat-message" + (mine ? " mine" : "");
    wrap.dataset.messageId = m.id;

    const avatarHtml = mine
      ? ""
      : `<div class="avatar-circle" style="width:28px; height:28px; font-size:11px; flex-shrink:0;">${
          m.author_avatar_url ? `<img src="${escapeHtml(m.author_avatar_url)}" alt="">` : escapeHtml(initials(m.author_name))
        }</div>`;
    const authorHtml = mine ? "" : `<div class="chat-author">${escapeHtml(m.author_name)}</div>`;

    wrap.innerHTML = `${avatarHtml}<div class="chat-bubble">${authorHtml}<div class="chat-text">${escapeHtml(m.body)}</div><div class="chat-time">just now</div></div>`;
    return wrap;
  }

  function appendMessages(messages) {
    if (!messages.length) return;
    const emptyNotice = log.querySelector("[data-chat-empty]");
    if (emptyNotice) emptyNotice.remove();

    const wasNearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 80;
    messages.forEach((m) => {
      if (m.id <= lastId) return;
      log.appendChild(renderMessage(m));
      lastId = Math.max(lastId, Number(m.id));
    });
    if (wasNearBottom) scrollToBottom();
  }

  async function poll() {
    if (document.hidden) return;
    try {
      const res = await fetch(`${baseUrl}/api/study-group-chat.php?groupId=${groupId}&after=${lastId}`);
      const data = await res.json();
      if (data.ok) appendMessages(data.messages);
    } catch {
      // Silent — the next tick just retries.
    }
  }

  setInterval(poll, POLL_INTERVAL_MS);

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.disabled = true;

    try {
      const res = await fetch(`${baseUrl}/api/study-group-chat.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ groupId, body: text, csrf_token: csrfToken }),
      });
      const data = await res.json();
      if (data.error) {
        alert(data.error);
      } else {
        appendMessages(data.messages);
        scrollToBottom();
        input.value = "";
      }
    } catch {
      alert("Couldn't send that message — check your connection and try again.");
    } finally {
      input.disabled = false;
      input.focus();
    }
  });
})();
