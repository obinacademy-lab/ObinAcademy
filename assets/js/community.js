// Community feed interactions: like/vote via fetch (no reload), composer
// type tabs + poll builder + image preview, @mention typeahead, inline
// reply forms. Mirrors the fetch+CSRF pattern already used by review.js.
document.addEventListener("DOMContentLoaded", () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
  const baseUrl = window.OBIN_BASE_URL || "";

  async function postJson(url, payload) {
    const res = await fetch(baseUrl + url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ ...payload, csrf_token: csrfToken }),
    });
    return res.json();
  }

  // ---- Like a post ----
  document.querySelectorAll("[data-like-post]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const postId = btn.dataset.postId;
      const data = await postJson("/api/community-like-post.php", { postId });
      if (data.error) { alert(data.error); return; }
      btn.classList.toggle("liked", data.liked);
      btn.querySelector("[data-like-count]").textContent = data.likeCount;
    });
  });

  // ---- Like a comment ----
  document.querySelectorAll("[data-like-comment]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const commentId = btn.dataset.commentId;
      const data = await postJson("/api/community-like-comment.php", { commentId });
      if (data.error) { alert(data.error); return; }
      btn.classList.toggle("liked", data.liked);
      btn.querySelector("[data-like-count]").textContent = data.likeCount;
    });
  });

  // ---- Vote on a poll ----
  document.querySelectorAll("[data-poll]").forEach((pollEl) => {
    pollEl.addEventListener("click", async (e) => {
      const optionEl = e.target.closest("[data-poll-option]");
      if (!optionEl || pollEl.dataset.voted === "1") return;

      const pollId = pollEl.dataset.pollId;
      const optionId = optionEl.dataset.optionId;
      const data = await postJson("/api/community-vote-poll.php", { pollId, optionId });
      if (data.error) { alert(data.error); return; }

      pollEl.dataset.voted = "1";
      const total = data.totalVotes || 1;
      data.options.forEach((opt) => {
        const el = pollEl.querySelector(`[data-option-id="${opt.id}"]`);
        if (!el) return;
        const pct = Math.round((opt.vote_count / total) * 100);
        el.classList.add("voted");
        el.classList.toggle("mine", Number(opt.id) === Number(data.myOptionId));
        el.querySelector(".fill").style.width = pct + "%";
        el.querySelector("[data-vote-pct]").textContent = pct + "%";
      });
      pollEl.querySelector("[data-poll-total]").textContent = data.totalVotes + " vote" + (data.totalVotes === 1 ? "" : "s");
    });
  });

  // ---- Composer: type tabs (Post / Question / Poll) ----
  document.querySelectorAll("[data-composer]").forEach((composer) => {
    const typeInput = composer.querySelector('input[name="type"]');
    const pollBuilder = composer.querySelector("[data-poll-builder]");
    composer.querySelectorAll("[data-composer-type-tab]").forEach((tab) => {
      tab.addEventListener("click", () => {
        composer.querySelectorAll("[data-composer-type-tab]").forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");
        const type = tab.dataset.composerTypeTab;
        typeInput.value = type;
        if (pollBuilder) pollBuilder.classList.toggle("hidden", type !== "poll");
      });
    });

    // ---- Poll builder: add option (max 6) ----
    const addOptionBtn = composer.querySelector("[data-add-poll-option]");
    if (addOptionBtn) {
      addOptionBtn.addEventListener("click", () => {
        const list = composer.querySelector("[data-poll-option-list]");
        const count = list.querySelectorAll("input").length;
        if (count >= 6) return;
        const input = document.createElement("input");
        input.type = "text";
        input.name = "poll_options[]";
        input.placeholder = `Option ${count + 1}`;
        input.maxLength = 120;
        list.appendChild(input);
      });
    }

    // ---- Image preview ----
    const imageInput = composer.querySelector('input[type="file"]');
    const preview = composer.querySelector("[data-image-preview]");
    if (imageInput && preview) {
      imageInput.addEventListener("change", () => {
        const file = imageInput.files[0];
        if (!file) { preview.classList.add("hidden"); preview.innerHTML = ""; return; }
        const reader = new FileReader();
        reader.onload = (e) => {
          preview.innerHTML = `<img src="${e.target.result}" alt=""><button type="button" data-clear-image class="feed-icon-btn" style="position:absolute; top:6px; right:6px;">Remove</button>`;
          preview.classList.remove("hidden");
          preview.querySelector("[data-clear-image]").addEventListener("click", () => {
            imageInput.value = "";
            preview.classList.add("hidden");
            preview.innerHTML = "";
          });
        };
        reader.readAsDataURL(file);
      });
    }
  });

  // ---- Inline reply form toggle ----
  document.querySelectorAll("[data-reply-toggle]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const form = document.getElementById(btn.dataset.replyToggle);
      if (form) form.classList.toggle("hidden");
    });
  });

  // ---- @mention typeahead ----
  document.querySelectorAll("[data-mentionable]").forEach((textarea) => {
    let members = [];
    try { members = JSON.parse(textarea.dataset.members || "[]"); } catch { members = []; }
    if (!members.length) return;

    const form = textarea.closest("form");
    let menu = null;

    function closeMenu() {
      if (menu) { menu.remove(); menu = null; }
    }

    function addMentionInput(id) {
      if (!form) return;
      if (form.querySelector(`input[name="mention_ids[]"][value="${id}"]`)) return;
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "mention_ids[]";
      hidden.value = id;
      form.appendChild(hidden);
    }

    textarea.addEventListener("input", () => {
      closeMenu();
      const caret = textarea.selectionStart;
      const before = textarea.value.slice(0, caret);
      const match = before.match(/(?:^|\s)@([a-zA-Z0-9 ]{0,30})$/);
      if (!match) return;

      const query = match[1].toLowerCase();
      const matches = members.filter((m) => m.name.toLowerCase().includes(query)).slice(0, 6);
      if (!matches.length) return;

      menu = document.createElement("div");
      menu.className = "mention-menu";
      matches.forEach((m) => {
        const item = document.createElement("button");
        item.type = "button";
        item.textContent = m.name;
        item.addEventListener("click", () => {
          const start = caret - match[1].length - 1;
          textarea.value = textarea.value.slice(0, start) + "@" + m.name + " " + textarea.value.slice(caret);
          addMentionInput(m.id);
          textarea.focus();
          closeMenu();
        });
        menu.appendChild(item);
      });

      const rect = textarea.getBoundingClientRect();
      menu.style.position = "absolute";
      menu.style.left = window.scrollX + rect.left + "px";
      menu.style.top = window.scrollY + rect.bottom + 4 + "px";
      document.body.appendChild(menu);
    });

    textarea.addEventListener("blur", () => setTimeout(closeMenu, 150));
  });
});
