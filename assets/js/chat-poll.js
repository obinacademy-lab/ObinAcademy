// Generic polling chat widget — reused by study-group chat and 1:1 direct
// messages. No WebSocket server on this host, so polling is the deliberate
// v1 choice, same interval-polling pattern as payment.js's status check.
//
// Attach to a container: [data-chat-log] with data-chat-endpoint (the API
// URL), data-chat-id-param (the field name the API expects, e.g. "groupId"
// or "conversationId"), data-chat-id (its value), and data-my-user-id.
// A sibling [data-chat-form] must contain input[name="body"].
(function () {
  const POLL_INTERVAL_MS = 4000;

  const log = document.querySelector("[data-chat-log]");
  const form = document.querySelector("[data-chat-form]");
  if (!log || !form) return;

  const endpoint = log.dataset.chatEndpoint;
  const idParam = log.dataset.chatIdParam;
  const chatId = log.dataset.chatId;
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
    const mine = Number(m.author_id ?? m.sender_id) === myUserId;
    const authorName = m.author_name;
    const wrap = document.createElement("div");
    wrap.className = "chat-message" + (mine ? " mine" : "");
    wrap.dataset.messageId = m.id;

    const avatarHtml = mine || !authorName
      ? ""
      : `<div class="avatar-circle" style="width:28px; height:28px; font-size:11px; flex-shrink:0;">${
          m.author_avatar_url ? `<img src="${escapeHtml(m.author_avatar_url)}" alt="">` : escapeHtml(initials(authorName))
        }</div>`;
    const authorHtml = mine || !authorName ? "" : `<div class="chat-author">${escapeHtml(authorName)}</div>`;

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
      const res = await fetch(`${endpoint}?${idParam}=${chatId}&after=${lastId}`);
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
      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ [idParam]: chatId, body: text, csrf_token: csrfToken }),
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
