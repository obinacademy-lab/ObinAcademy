// In-school course search on a creator's school page (profile.php). Filters the
// course cards already on the page — by name, category or summary text, plus
// category chips — instantly and without a reload. Several words must all
// match (so "money young" finds "Money Mastery for Young People").
document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-school-search]");
  const list = document.querySelector("[data-school-courses]");
  if (!root || !list) return;

  const input = root.querySelector("[data-school-search-input]");
  const clearBtn = root.querySelector("[data-school-search-clear]");
  const status = root.querySelector("[data-school-search-status]");
  const chips = Array.from(root.querySelectorAll("[data-school-chip]"));
  const section = root.closest(".profile-section");
  const countEl = section?.querySelector(".profile-section-head .count");
  const empty = section?.querySelector("[data-school-search-empty]");
  const emptyTerm = empty?.querySelector("[data-school-search-term]");
  const resetBtn = empty?.querySelector("[data-school-search-reset]");
  const total = parseInt(root.dataset.total, 10) || 0;
  const originalCount = countEl ? countEl.textContent : "";

  // Lowercased and accent-stripped on both sides so "cafe" finds "Café".
  const norm = (s) => s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
  const items = Array.from(list.querySelectorAll(".school-course")).map((el) => ({
    el,
    cat: el.dataset.courseCat,
    hay: norm(el.dataset.courseText || ""),
  }));

  let activeCat = "";

  function apply() {
    const raw = input.value.trim();
    const terms = norm(raw).split(/\s+/).filter(Boolean);
    let shown = 0;

    items.forEach((item) => {
      const catOk = !activeCat || item.cat === activeCat;
      const textOk = terms.every((t) => item.hay.includes(t));
      const visible = catOk && textOk;
      item.el.hidden = !visible;
      if (visible) shown += 1;
    });

    const filtering = raw !== "" || activeCat !== "";
    clearBtn.hidden = raw === "";
    if (countEl) {
      countEl.textContent = filtering ? `${shown} of ${total} course${total === 1 ? "" : "s"}` : originalCount;
    }
    if (status) {
      status.hidden = !filtering;
      status.textContent = filtering ? `${shown} course${shown === 1 ? "" : "s"} found` : "";
    }
    list.hidden = shown === 0;
    if (empty) {
      empty.hidden = shown !== 0;
      if (emptyTerm) {
        const activeChip = chips.find((c) => c.dataset.schoolChip === activeCat && activeCat !== "");
        emptyTerm.textContent = raw ? `“${raw}”` : activeChip ? activeChip.firstChild.textContent.trim() : "";
      }
    }
  }

  function setCategory(cat) {
    activeCat = cat;
    chips.forEach((chip) => {
      const on = chip.dataset.schoolChip === cat;
      chip.classList.toggle("is-active", on);
      chip.setAttribute("aria-pressed", String(on));
    });
    apply();
  }

  function reset() {
    input.value = "";
    setCategory("");
    input.focus();
  }

  input.addEventListener("input", apply);
  input.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && input.value) {
      e.preventDefault();
      input.value = "";
      apply();
    }
  });
  clearBtn.addEventListener("click", () => {
    input.value = "";
    apply();
    input.focus();
  });
  chips.forEach((chip) => chip.addEventListener("click", () => setCategory(chip.dataset.schoolChip)));
  resetBtn?.addEventListener("click", reset);
});
