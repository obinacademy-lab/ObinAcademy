// Course Viewers (dashboard/creator/viewers.php): the "how details are shared" note, the editable
// opening message used by the WhatsApp and email buttons (kept in this browser), copy buttons,
// and auto-submitting the course / sort pickers.
(() => {
  const cfgEl = document.getElementById("vw-config");
  if (!cfgEl) return;
  const cfg = JSON.parse(cfgEl.textContent);
  const $ = (id) => document.getElementById(id);
  const KEY = "oa-viewers-template-" + cfg.creatorId;

  const load = () => { try { return localStorage.getItem(KEY) || ""; } catch { return ""; } };
  const store = (v) => { try { v ? localStorage.setItem(KEY, v) : localStorage.removeItem(KEY); } catch {} };
  let template = load() || cfg.defaultTemplate;

  const fill = (tpl, name, course) => tpl.replace(/\{name\}/g, name || "there").replace(/\{course\}/g, course);

  // Rewrite every WhatsApp / email link so it carries the creator's own message.
  function applyTemplate() {
    document.querySelectorAll("[data-wa]").forEach((a) => {
      a.href = "https://wa.me/" + a.dataset.wa + "?text=" + encodeURIComponent(fill(template, a.dataset.name, a.dataset.course));
    });
    document.querySelectorAll("[data-mail]").forEach((a) => {
      a.href = "mailto:" + a.dataset.mail + "?subject=" + encodeURIComponent("About " + a.dataset.course) +
        "&body=" + encodeURIComponent(fill(template, a.dataset.name, a.dataset.course));
    });
  }

  // Toggle buttons (how details are shared / your message).
  function toggle(btnId, boxId) {
    const btn = $(btnId), box = $(boxId);
    if (!btn || !box) return;
    btn.addEventListener("click", () => {
      box.hidden = !box.hidden;
      btn.setAttribute("aria-expanded", String(!box.hidden));
    });
  }
  toggle("vw-how-btn", "vw-how");
  toggle("vw-tpl-btn", "vw-tpl");

  // Message editor with a live preview on the first person in the list.
  const ta = $("vw-tpl-text");
  if (ta) {
    ta.value = template;
    const sample = document.querySelector("[data-wa], [data-mail]");
    const preview = () => {
      const pv = $("vw-tpl-pv");
      const name = sample ? sample.dataset.name : "Grace";
      const course = sample ? sample.dataset.course : "Your course";
      pv.innerHTML = "";
      const b = document.createElement("strong");
      b.textContent = "Preview ";
      pv.append(b, document.createTextNode("for " + (name || "someone") + ": " + fill(ta.value, name, course)));
    };
    ta.addEventListener("input", preview);
    preview();
    const saved = $("vw-tpl-saved");
    const flash = () => { saved.hidden = false; setTimeout(() => { saved.hidden = true; }, 1600); };
    $("vw-tpl-save").addEventListener("click", () => {
      template = ta.value.trim() || cfg.defaultTemplate;
      ta.value = template;
      store(template === cfg.defaultTemplate ? "" : template);
      applyTemplate(); preview(); flash();
    });
    $("vw-tpl-reset").addEventListener("click", () => {
      template = cfg.defaultTemplate;
      ta.value = template;
      store("");
      applyTemplate(); preview(); flash();
    });
  }
  applyTemplate();

  // Copy buttons.
  const toast = $("vw-toast");
  let toastTimer;
  const say = (msg) => {
    toast.textContent = msg;
    toast.classList.add("is-on");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("is-on"), 1800);
  };
  document.querySelectorAll("[data-copy]").forEach((b) => b.addEventListener("click", () => {
    const text = b.dataset.copy;
    const fallback = () => say("Select and copy: " + text);
    try { navigator.clipboard.writeText(text).then(() => say("Copied " + text), fallback); } catch { fallback(); }
  }));

  // Course and sort pickers apply immediately.
  document.querySelectorAll("[data-autosubmit]").forEach((s) => s.addEventListener("change", () => s.form.submit()));
})();
