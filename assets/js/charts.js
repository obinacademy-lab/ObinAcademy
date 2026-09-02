// Floating hover tooltips for the admin dashboard's line charts — replaces
// the browser's plain native <title> tooltip with a styled one that follows
// each point, reading its value straight off data-chart-label/-value.
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".chart-wrap").forEach((wrap) => {
    const points = wrap.querySelectorAll("[data-chart-value]");
    if (!points.length) return;

    const tooltip = document.createElement("div");
    tooltip.className = "chart-tooltip";
    const labelEl = document.createElement("span");
    labelEl.className = "ct-label";
    const valueEl = document.createElement("span");
    valueEl.className = "ct-value";
    tooltip.append(labelEl, valueEl);
    wrap.appendChild(tooltip);

    const show = (point) => {
      const wrapRect = wrap.getBoundingClientRect();
      const pointRect = point.getBoundingClientRect();
      labelEl.textContent = point.getAttribute("data-chart-label") || "";
      valueEl.textContent = point.getAttribute("data-chart-value") || "";
      tooltip.style.left = `${pointRect.left - wrapRect.left + pointRect.width / 2}px`;
      tooltip.style.top = `${pointRect.top - wrapRect.top}px`;
      tooltip.classList.add("visible");
    };
    const hide = () => tooltip.classList.remove("visible");

    points.forEach((point) => {
      point.addEventListener("mouseenter", () => show(point));
      point.addEventListener("focus", () => show(point));
      point.addEventListener("mouseleave", hide);
      point.addEventListener("blur", hide);
    });
  });
});
