// Star-rating input + AJAX submit for the course review form.
document.addEventListener("DOMContentLoaded", () => {
  const wrap = document.querySelector("[data-review-form]");
  if (!wrap) return;

  const courseId = wrap.dataset.courseId;
  const submitUrl = wrap.dataset.submitUrl || "/api/submit-review.php";
  const form = wrap.querySelector("[data-review-submit]");
  const starWrap = wrap.querySelector("[data-star-input]");
  const stars = starWrap ? [...starWrap.querySelectorAll("[data-star]")] : [];
  const ratingInput = form.querySelector('input[name="rating"]');
  const errorBox = wrap.querySelector("[data-review-error]");
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";

  function paintStars(value) {
    stars.forEach((s) => {
      s.textContent = Number(s.dataset.star) <= value ? "★" : "☆";
    });
  }

  stars.forEach((s) => {
    s.addEventListener("click", () => {
      const value = Number(s.dataset.star);
      ratingInput.value = String(value);
      paintStars(value);
    });
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    errorBox.classList.add("hidden");
    const rating = Number(ratingInput.value);
    const comment = form.querySelector('textarea[name="comment"]').value.trim();

    try {
      const res = await fetch(submitUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ courseId, rating, comment, csrf_token: csrfToken }),
      });
      const data = await res.json();
      if (data.error) {
        errorBox.textContent = data.error;
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
