// Comment/reply forms (AJAX submit) + delete buttons for the course/event
// discussion section. Works for any number of [data-comments-root] widgets
// on a page, each of which can contain multiple [data-comment-submit] forms
// — one top-level "Add a Comment" form plus one per-comment reply form.
document.addEventListener("DOMContentLoaded", () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";

  document.querySelectorAll("[data-comments-root]").forEach((root) => {
    const courseId = root.dataset.courseId;
    const submitUrl = root.dataset.submitUrl;
    const deleteUrl = root.dataset.deleteUrl;
    const list = root.querySelector("[data-comment-list]");

    root.querySelectorAll("[data-comment-submit]").forEach((form) => {
      const errorBox = form.querySelector("[data-comment-error]");
      const textarea = form.querySelector('textarea[name="body"]');
      const parentId = form.dataset.parentId || null;

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        errorBox.classList.add("hidden");
        const body = textarea.value.trim();
        if (!body) return;

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

    // Reply toggle: one button per top-level comment, revealing/hiding the
    // reply form that immediately follows it (and focusing its textarea).
    root.querySelectorAll("[data-reply-toggle]").forEach((btn) => {
      const form = btn.nextElementSibling;
      if (!form || !form.matches("[data-comment-submit]")) return;
      btn.addEventListener("click", () => {
        const willOpen = form.classList.contains("hidden");
        form.classList.toggle("hidden", !willOpen);
        if (willOpen) form.querySelector("textarea")?.focus();
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
