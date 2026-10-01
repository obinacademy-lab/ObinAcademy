// Homepage category chips: "More…" reveals the categories beyond the first
// seven (and flips to "Less"), the same way skool.com's chip row does.
document.addEventListener("DOMContentLoaded", () => {
  const nav = document.querySelector("[data-home-chips]");
  const more = nav?.querySelector("[data-home-chips-more]");
  if (!nav || !more) return;

  const extras = Array.from(nav.querySelectorAll(".is-extra"));

  more.addEventListener("click", () => {
    const open = more.getAttribute("aria-expanded") !== "true";
    extras.forEach((chip) => { chip.hidden = !open; });
    nav.classList.toggle("is-expanded", open);
    more.setAttribute("aria-expanded", String(open));
    more.textContent = open ? "Less" : "More…";
  });
});
