// Comment/reply widget for the course/event discussion section — a single
// flat, WhatsApp-group-style chat thread (newest first) with ONE composer
// at the bottom shared by every row. Replying to any message (top-level or
// a reply alike, since there's no visual thread-nesting anymore) sets that
// message as the composer's reply target — either by tapping its reply
// icon or by swiping the row, like swiping a WhatsApp message — and posts
// as a single request carrying that target's id plus whatever was typed.
// Works for any number of [data-comments-root] widgets on a page.
document.addEventListener("DOMContentLoaded", () => {
  const MAX_LEN = 2000;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";

  function wireCharCount(form) {
    const textarea = form.querySelector("textarea");
    const counter = form.querySelector("[data-char-count]");
    if (!textarea || !counter) return;
    const update = () => {
      const remaining = MAX_LEN - textarea.value.length;
      counter.textContent = remaining;
      counter.classList.toggle("comment-char-count-low", remaining < 100);
      // Auto-grow the single-line-at-rest pill so a longer comment still
      // wraps to multiple visible lines instead of scrolling internally.
      textarea.style.height = "auto";
      textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";
    };
    textarea.addEventListener("input", update);
    update();
  }

  // Swipe-to-reply — a horizontal drag on a message row (touch or mouse,
  // unified via Pointer Events) nudges it toward the trailing edge and
  // reveals a reply icon; releasing past the threshold fires onReply.
  // touch-action:pan-y (see CSS) lets vertical page scroll keep working
  // on touch while this still gets first crack at horizontal movement.
  function wireSwipeToReply(row, onReply) {
    const THRESHOLD = 46;
    const MAX = 64;
    let startX = 0;
    let startY = 0;
    let dragging = false;
    let decided = false;
    let horizontal = false;

    function reset() {
      row.style.transform = "";
      row.classList.remove("swiping");
    }

    row.addEventListener("pointerdown", (e) => {
      if (e.target.closest("button, a")) return;
      if (e.pointerType === "mouse" && e.button !== 0) return;
      startX = e.clientX;
      startY = e.clientY;
      dragging = true;
      decided = false;
      horizontal = false;
    });

    row.addEventListener("pointermove", (e) => {
      if (!dragging) return;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      if (!decided) {
        if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return;
        decided = true;
        horizontal = Math.abs(dx) > Math.abs(dy);
        if (!horizontal) {
          dragging = false;
          return;
        }
        row.setPointerCapture?.(e.pointerId);
      }
      const mine = row.classList.contains("mine");
      const clamped = mine ? Math.min(0, Math.max(dx, -MAX)) : Math.max(0, Math.min(dx, MAX));
      row.style.transform = `translateX(${clamped}px)`;
      row.classList.toggle("swiping", Math.abs(clamped) > 14);
    });

    function end(e) {
      if (!dragging) return;
      dragging = false;
      const dx = e.clientX - startX;
      const triggered = decided && horizontal && Math.abs(dx) >= THRESHOLD;
      reset();
      if (triggered) onReply();
    }
    row.addEventListener("pointerup", end);
    row.addEventListener("pointercancel", end);
  }

  // Emoji picker — one shared list, a panel built lazily on the composer's
  // toggle button's first click and reused after that. Only one panel
  // stays open at a time; picking an emoji inserts it at the textarea's
  // actual cursor position and keeps the panel open, so tapping several in
  // a row (the TikTok-style flurry-of-emoji habit this is built for) works
  // without re-opening it each time.
  const EMOJI = [
    "😀", "😂", "🤣", "😊", "😍", "🥰", "😎", "🤔",
    "😅", "😉", "😇", "🥳", "😮", "😢", "😭", "😡",
    "👍", "👎", "👏", "🙌", "🙏", "💪", "👀", "🤝",
    "❤️", "🧡", "💛", "💚", "💙", "💜", "🖤", "🤍",
    "🔥", "✨", "⭐", "🎉", "🎊", "💯", "🚀", "🎯",
    "📚", "✅", "❌", "💡", "🤩", "😴", "🙃", "😱",
  ];
  let openPicker = null;

  function closeOpenPicker() {
    if (!openPicker) return;
    openPicker.panel.classList.add("hidden");
    openPicker.toggle.classList.remove("open");
    openPicker = null;
  }

  document.addEventListener("click", (e) => {
    if (
      openPicker &&
      !e.target.closest(".comment-emoji-picker") &&
      !e.target.closest("[data-emoji-toggle]") &&
      !e.target.closest(".comment-gif-picker") &&
      !e.target.closest("[data-gif-toggle]")
    ) {
      closeOpenPicker();
    }
  });

  document.querySelectorAll("[data-emoji-toggle]").forEach((toggle) => {
    const footer = toggle.closest(".comment-form-bar");
    const textarea = footer?.querySelector('textarea[name="body"]');
    if (!footer || !textarea) return;

    let panel = null;
    toggle.addEventListener("click", () => {
      if (openPicker && openPicker.toggle === toggle) {
        closeOpenPicker();
        return;
      }
      closeOpenPicker();

      if (!panel) {
        panel = document.createElement("div");
        panel.className = "comment-emoji-picker hidden";
        EMOJI.forEach((emoji) => {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.textContent = emoji;
          btn.addEventListener("click", () => {
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            textarea.value = textarea.value.slice(0, start) + emoji + textarea.value.slice(end);
            const caret = start + emoji.length;
            textarea.focus();
            textarea.setSelectionRange(caret, caret);
            textarea.dispatchEvent(new Event("input", { bubbles: true }));
          });
          panel.appendChild(btn);
        });
        footer.appendChild(panel);
      }

      panel.classList.remove("hidden");
      toggle.classList.add("open");
      openPicker = { toggle, panel };
    });
  });

  // GIF picker — a panel for the composer's toggle button, built lazily
  // and reused, sharing the same openPicker/closeOpenPicker
  // single-panel-at-a-time tracking as the emoji picker
  // above. Unlike emoji (multi-select, stays open), picking a GIF is a
  // single choice: it fills the form's hidden gifUrl input, shows a
  // preview, and closes the panel — a comment carries at most one GIF.
  function debounce(fn, ms) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  }

  document.querySelectorAll("[data-gif-toggle]").forEach((toggle) => {
    const form = toggle.closest("form");
    const footer = toggle.closest(".comment-form-bar");
    const root = toggle.closest("[data-comments-root]");
    const searchUrl = root?.dataset.gifSearchUrl;
    const preview = form?.querySelector("[data-gif-preview]");
    const previewImg = form?.querySelector("[data-gif-preview-img]");
    const gifUrlInput = form?.querySelector("[data-gif-url-input]");
    if (!footer || !form || !searchUrl || !gifUrlInput) return;

    form.querySelector("[data-gif-remove]")?.addEventListener("click", () => {
      gifUrlInput.value = "";
      preview.classList.add("hidden");
    });

    let panel = null;
    let grid = null;
    let searchInput = null;

    function renderGifs(gifs) {
      grid.innerHTML = "";
      if (!gifs.length) {
        grid.innerHTML = '<p class="comment-gif-empty">No GIFs found.</p>';
        return;
      }
      gifs.forEach((gif) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "comment-gif-tile";
        const img = document.createElement("img");
        img.src = gif.previewUrl;
        img.alt = "";
        img.loading = "lazy";
        btn.appendChild(img);
        btn.addEventListener("click", () => {
          gifUrlInput.value = gif.url;
          previewImg.src = gif.previewUrl;
          preview.classList.remove("hidden");
          closeOpenPicker();
        });
        grid.appendChild(btn);
      });
    }

    async function fetchGifs(q) {
      grid.innerHTML = '<p class="comment-gif-empty">Loading…</p>';
      try {
        const res = await fetch(searchUrl + "?q=" + encodeURIComponent(q));
        const data = await res.json();
        if (!Array.isArray(data.gifs)) throw new Error("bad response");
        renderGifs(data.gifs);
      } catch {
        grid.innerHTML = '<p class="comment-gif-empty">GIFs aren\'t available right now.</p>';
      }
    }

    const debouncedSearch = debounce((q) => fetchGifs(q), 350);

    toggle.addEventListener("click", () => {
      if (openPicker && openPicker.toggle === toggle) {
        closeOpenPicker();
        return;
      }
      closeOpenPicker();

      if (!panel) {
        panel = document.createElement("div");
        panel.className = "comment-gif-picker hidden";
        searchInput = document.createElement("input");
        searchInput.type = "text";
        searchInput.placeholder = "Search GIFs…";
        searchInput.className = "comment-gif-search";
        searchInput.addEventListener("input", () => debouncedSearch(searchInput.value.trim()));
        grid = document.createElement("div");
        grid.className = "comment-gif-grid";
        const attrib = document.createElement("div");
        attrib.className = "comment-gif-attrib";
        attrib.textContent = "Powered by GIPHY";
        panel.append(searchInput, grid, attrib);
        footer.appendChild(panel);
        fetchGifs("");
      }

      panel.classList.remove("hidden");
      toggle.classList.add("open");
      openPicker = { toggle, panel };
      searchInput?.focus();
    });
  });

  document.querySelectorAll("[data-comments-root]").forEach((root) => {
    const courseId = root.dataset.courseId;
    const submitUrl = root.dataset.submitUrl;
    const deleteUrl = root.dataset.deleteUrl;
    const likeUrl = root.dataset.likeUrl;
    const list = root.querySelector("[data-comment-list]");

    // Optimistic like/unlike — flips the heart and count immediately (the
    // TikTok-style snappy feel the button is going for), then reconciles
    // with whatever the server actually recorded. A concurrent double-tap
    // or a network hiccup self-corrects from that reconciliation rather
    // than needing its own lock: the server's (liked, count) response is
    // always the source of truth the UI settles back to.
    list?.querySelectorAll("[data-like-toggle]").forEach((btn) => {
      const countEl = btn.querySelector("[data-like-count]");
      let busy = false;
      btn.addEventListener("click", async () => {
        if (busy) return;
        busy = true;
        const commentId = btn.dataset.commentId;
        const wasLiked = btn.classList.contains("is-liked");
        const prevCount = parseInt(countEl.textContent, 10) || 0;
        btn.classList.toggle("is-liked", !wasLiked);
        countEl.textContent = String(prevCount + (wasLiked ? -1 : 1));

        try {
          const res = await fetch(likeUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ commentId, csrf_token: csrfToken }),
          });
          const data = await res.json();
          if (typeof data.count === "number") {
            btn.classList.toggle("is-liked", !!data.liked);
            countEl.textContent = String(data.count);
          } else {
            throw new Error("bad response");
          }
        } catch {
          btn.classList.toggle("is-liked", wasLiked);
          countEl.textContent = String(prevCount);
        } finally {
          busy = false;
        }
      });
    });

    // The single composer at the bottom doubles as the reply box — tapping
    // a row's reply icon or swiping it (wired below, per row) sets that
    // row as the target via setReplyTarget, shown as a quoted chip above
    // the input until posted or cancelled.
    const form = root.querySelector("[data-comment-submit]");
    const chip = form?.querySelector("[data-reply-chip]");
    const chipName = form?.querySelector("[data-reply-chip-name]");
    const chipSnippet = form?.querySelector("[data-reply-chip-snippet]");
    const textarea = form?.querySelector('textarea[name="body"]');

    function setReplyTarget(id, name, snippet) {
      if (!form) return;
      form.dataset.replyToId = id;
      if (chipName) chipName.textContent = name || "";
      if (chipSnippet) chipSnippet.textContent = snippet || "";
      chip?.classList.remove("hidden");
      textarea?.focus();
    }
    function clearReplyTarget() {
      if (!form) return;
      delete form.dataset.replyToId;
      chip?.classList.add("hidden");
    }
    form?.querySelector("[data-reply-cancel]")?.addEventListener("click", clearReplyTarget);

    list?.querySelectorAll(".crow[data-comment-id]").forEach((row) => {
      const doReply = () => setReplyTarget(row.dataset.commentId, row.dataset.authorName, row.dataset.snippet);
      row.querySelector("[data-reply-toggle]")?.addEventListener("click", doReply);
      wireSwipeToReply(row, doReply);
    });

    if (form) {
      wireCharCount(form);
      const errorBox = form.querySelector("[data-comment-error]");
      const gifUrlInput = form.querySelector("[data-gif-url-input]");

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        errorBox.classList.add("hidden");
        const body = textarea.value.trim();
        const gifUrl = gifUrlInput?.value || null;
        if (!body) return;
        const parentId = form.dataset.replyToId || null;

        try {
          const res = await fetch(submitUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ courseId, body, parentId, gifUrl, csrf_token: csrfToken }),
          });
          const data = await res.json();
          if (data.error) {
            errorBox.textContent = data.error;
            errorBox.classList.remove("hidden");
            return;
          }
          if (data.hidden) {
            errorBox.textContent = (parentId ? "Your reply" : "Your comment") + " couldn't be posted — it contains language that isn't allowed here.";
            errorBox.classList.remove("hidden");
            return;
          }
          window.location.reload();
        } catch {
          errorBox.textContent = "Something went wrong. Please try again.";
          errorBox.classList.remove("hidden");
        }
      });
    }

    list?.querySelectorAll("[data-comment-delete]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        if (!window.confirm("Delete this comment?")) return;
        const card = btn.closest("[data-comment-id]");
        const commentId = card?.dataset.commentId;
        if (!commentId) return;

        try {
          const res = await fetch(deleteUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ commentId, csrf_token: csrfToken }),
          });
          const data = await res.json();
          if (data.ok) card.remove();
        } catch {
          // leave the comment in place — the user can retry
        }
      });
    });
  });
});
