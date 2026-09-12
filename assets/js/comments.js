// Comment/reply forms (AJAX submit) + delete buttons for the course/event
// discussion section. Works for any number of [data-comments-root] widgets
// on a page. Each top-level comment ("thread") has exactly one reply form,
// shared by every "Reply" button inside that thread (on the top-level
// comment itself and on each of its replies) — clicking any of them just
// changes which comment the shared form is currently addressing, via
// form.dataset.replyToId, so anyone can reply to anyone in the thread
// without a separate form per comment.
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
    };
    textarea.addEventListener("input", update);
    update();
  }

  document.querySelectorAll("[data-comments-root]").forEach((root) => {
    const courseId = root.dataset.courseId;
    const submitUrl = root.dataset.submitUrl;
    const deleteUrl = root.dataset.deleteUrl;
    const list = root.querySelector("[data-comment-list]");

    // Wire each thread's reply-toggle buttons (on the comment and on every
    // reply within it) to the one shared reply form for that thread.
    root.querySelectorAll(".ccard[data-comment-id]").forEach((card) => {
      const threadId = card.dataset.commentId;
      const form = card.querySelector("form.comment-reply-form");
      if (!form) return;
      const chip = form.querySelector("[data-reply-chip]");
      const chipName = form.querySelector("[data-reply-chip-name]");
      const textarea = form.querySelector("textarea");

      card.querySelectorAll("[data-reply-toggle]").forEach((btn) => {
        btn.addEventListener("click", () => {
          const targetId = btn.dataset.replyToId;
          form.dataset.replyToId = targetId;
          if (targetId === threadId) {
            chip?.classList.add("hidden");
          } else {
            if (chipName) chipName.textContent = btn.dataset.replyToName || "";
            chip?.classList.remove("hidden");
          }
          form.classList.add("open");
          textarea?.focus();
        });
      });

      form.querySelector("[data-reply-cancel]")?.addEventListener("click", () => {
        delete form.dataset.replyToId;
        chip?.classList.add("hidden");
      });
    });

    root.querySelectorAll("[data-comment-submit]").forEach((form) => {
      wireCharCount(form);
      const errorBox = form.querySelector("[data-comment-error]");
      const textarea = form.querySelector('textarea[name="body"]');

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        errorBox.classList.add("hidden");
        const body = textarea.value.trim();
        if (!body) return;
        const parentId = form.dataset.replyToId || form.dataset.threadId || null;

        try {
          const res = await fetch(submitUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ courseId, body, parentId, csrf_token: csrfToken }),
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
    });

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
