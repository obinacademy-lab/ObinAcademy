// Upload-first course builder (dashboard/creator/course-build.php).
// The first file dropped creates the draft course through api/course-builder.php;
// after that the page is a lesson timeline (upload, rename, reorder, split into
// modules) next to a details panel that autosaves. Nothing reloads.
(() => {
  const root = document.querySelector("[data-cb]");
  if (!root) return;
  const cfg = JSON.parse(document.getElementById("cb-config").textContent);
  const $ = (sel, scope = root) => scope.querySelector(sel);
  const $$ = (sel, scope = root) => Array.from(scope.querySelectorAll(sel));
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  const startEl = $("[data-cb-start]");
  const buildEl = $("[data-cb-build]");
  const tl = $("[data-cb-tl]");
  const fields = $$("[data-f]");
  const field = (name) => fields.filter((f) => f.dataset.f === name);
  const val = (name) => (field(name)[0] ? field(name)[0].value : "");

  let S = cfg.state; // {course, modules} from the server, or null before the draft exists
  let courseId = cfg.courseId || 0;
  let creating = null;
  let pending = []; // files uploading or waiting: {pid, file, pct, status: queued|uploading|error, msg}
  let nextPid = 1;
  let running = false;
  let submitted = false;
  let dragId = null;

  const ICON_VIDEO = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
  const ICON_PDF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';
  const TICK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>';

  // ---- server calls -------------------------------------------------------
  async function api(action, data = {}) {
    const res = await fetch(cfg.api, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action, courseId, csrf_token: cfg.csrf, ...data }),
    });
    let json;
    try { json = await res.json(); } catch { throw new Error("The server sent an unexpected reply. Please try again."); }
    if (!res.ok || json.error) {
      const err = new Error(json.error || "Something went wrong.");
      err.problems = json.problems;
      throw err;
    }
    return json;
  }

  function upload(action, file, onProgress) {
    return new Promise((resolve, reject) => {
      const fd = new FormData();
      fd.append("action", action);
      fd.append("courseId", String(courseId));
      fd.append("csrf_token", cfg.csrf);
      fd.append("file", file);
      const xhr = new XMLHttpRequest();
      xhr.open("POST", cfg.api);
      xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100)); };
      xhr.onload = () => {
        let json;
        try { json = JSON.parse(xhr.responseText); } catch { return reject(new Error("The server sent an unexpected reply. The file may be too large for it.")); }
        if (xhr.status >= 200 && xhr.status < 300 && !json.error) resolve(json);
        else reject(new Error(json.error || "Upload failed."));
      };
      xhr.onerror = () => reject(new Error("Connection lost. Check your internet and retry."));
      xhr.send(fd);
    });
  }

  // ---- draft creation -----------------------------------------------------
  function ensureCourse() {
    if (courseId) return Promise.resolve(courseId);
    if (!creating) {
      creating = api("create")
        .then((j) => {
          courseId = j.state.course.id;
          S = j.state;
          history.replaceState(null, "", cfg.buildUrl + courseId);
          return courseId;
        })
        .finally(() => { creating = null; });
    }
    return creating;
  }

  function showBuilder() {
    startEl.hidden = true;
    buildEl.hidden = false;
    renderAll();
  }

  function startError(msg) {
    const el = $("[data-cb-start-error]");
    el.textContent = msg;
    el.hidden = !msg;
  }

  // ---- adding files -------------------------------------------------------
  const OK_EXT = /\.(mp4|mov|webm|ogv|ogg|m4v|pdf)$/i;
  function addFiles(list) {
    const files = Array.from(list).filter((f) => f.type === "application/pdf" || f.type.startsWith("video/") || OK_EXT.test(f.name));
    const skipped = list.length - files.length;
    if (!files.length) { startError("Those files aren't videos or PDFs. Use MP4, MOV, WebM or PDF."); return; }
    startError("");
    files.forEach((file) => pending.push({ pid: nextPid++, file, pct: 0, status: "queued", msg: "" }));
    showBuilder();
    if (skipped) setSaved(skipped + " file" + (skipped === 1 ? " was" : "s were") + " skipped (not a video or PDF).", true);
    ensureCourse().then(runQueue).catch((e) => {
      pending = [];
      if (!courseId) { buildEl.hidden = true; startEl.hidden = false; }
      startError(e.message);
      renderAll();
    });
  }

  async function runQueue() {
    if (running) return;
    running = true;
    try {
      let p;
      while ((p = pending.find((x) => x.status === "queued"))) {
        p.status = "uploading";
        p.pct = 0;
        renderTimeline();
        try {
          const j = await upload("upload_lesson", p.file, (pct) => { p.pct = pct; paintPending(p); });
          S = j.state;
          pending = pending.filter((x) => x !== p);
        } catch (e) {
          p.status = "error";
          p.msg = e.message;
        }
        renderTimeline();
        updateChecklist();
      }
    } finally {
      running = false;
    }
  }

  function paintPending(p) {
    const row = tl.querySelector('[data-pid="' + p.pid + '"]');
    if (!row) return;
    const bar = row.querySelector(".cb-pb i");
    const meta = row.querySelector(".cb-meta");
    if (bar) bar.style.width = p.pct + "%";
    if (meta) meta.textContent = p.pct >= 100 ? "Processing…" : "Uploading · " + p.pct + "%";
  }

  // ---- timeline -----------------------------------------------------------
  const lessonCount = () => (S ? S.modules.reduce((n, m) => n + m.lessons.length, 0) : 0);

  function lessonRow(l) {
    return '<div class="cb-les" draggable="true" data-lid="' + l.id + '">' +
      '<span class="cb-grip" aria-hidden="true" title="Drag to reorder">⋮⋮</span>' +
      '<span class="cb-ico">' + (l.type === "PDF" ? ICON_PDF : ICON_VIDEO) + "</span>" +
      '<div class="cb-mid"><input type="text" class="cb-lt" data-lt="' + l.id + '" value="' + esc(l.title) + '" maxlength="190" aria-label="Lesson title">' +
      '<div class="cb-meta">' + (l.type === "PDF" ? "PDF" : "Video") + (l.size ? " · " + esc(l.size) : "") + (l.fileName ? " · " + esc(l.fileName) : "") + "</div></div>" +
      '<div class="cb-mv"><button type="button" data-up="' + l.id + '" aria-label="Move up">▲</button><button type="button" data-dn="' + l.id + '" aria-label="Move down">▼</button></div>' +
      '<button type="button" class="cb-x" data-rm="' + l.id + '" aria-label="Remove lesson">Remove</button></div>';
  }

  function pendingRow(p) {
    const err = p.status === "error";
    return '<div class="cb-les is-pending' + (err ? " is-error" : "") + '" data-pid="' + p.pid + '">' +
      '<span class="cb-ico">' + (/pdf$/i.test(p.file.name) ? ICON_PDF : ICON_VIDEO) + "</span>" +
      '<div class="cb-mid"><div class="cb-pt">' + esc(p.file.name) + "</div>" +
      (err ? '<div class="cb-meta cb-bad">' + esc(p.msg) + "</div>"
           : '<div class="cb-pb"><i style="width:' + p.pct + '%"></i></div><div class="cb-meta">' + (p.status === "queued" ? "Waiting…" : "Uploading · " + p.pct + "%") + "</div>") +
      "</div>" +
      (err ? '<button type="button" class="cb-x" data-retry="' + p.pid + '">Retry</button>' : "") +
      '<button type="button" class="cb-x" data-cancel="' + p.pid + '">' + (err ? "Remove" : "Cancel") + "</button></div>";
  }

  function renderTimeline() {
    const mods = S ? S.modules : [];
    let h = "";
    if (!mods.length && pending.length) {
      h += '<div class="cb-modh"><span class="cb-k">Module 1</span><span class="cb-mn">Course content</span></div>';
    }
    mods.forEach((m, mi) => {
      h += '<div class="cb-modh" data-mh="' + m.id + '"><span class="cb-k">Module ' + (mi + 1) + '</span>' +
        '<input type="text" class="cb-mt" data-mt="' + m.id + '" value="' + esc(m.title) + '" maxlength="190" aria-label="Module name">' +
        (mi > 0 ? '<button type="button" class="cb-x" data-merge="' + m.id + '">Merge up</button>' : "") + "</div>";
      if (!m.lessons.length) h += '<div class="cb-empty" data-mh="' + m.id + '">No lessons here. Drag one in, or merge this module up.</div>';
      m.lessons.forEach((l, li) => {
        h += lessonRow(l);
        if (li < m.lessons.length - 1) h += '<div class="cb-split"><button type="button" data-split="' + l.id + '">Split here · start a new module</button></div>';
      });
    });
    pending.forEach((p) => { h += pendingRow(p); });
    if (!mods.length && !pending.length) h = '<div class="cb-empty">No lessons yet. Add files below.</div>';
    tl.innerHTML = h;
    renderSub();
  }

  function renderSub() {
    const n = lessonCount();
    const m = S ? S.modules.filter((x) => x.lessons.length).length : 0;
    const up = pending.filter((p) => p.status !== "error").length;
    $("[data-cb-sub]").textContent =
      n + " lesson" + (n === 1 ? "" : "s") + " in " + m + " module" + (m === 1 ? "" : "s") +
      (up ? " · " + up + " uploading" : "") + ". Drag by the handle, or use the arrows, to reorder.";
  }

  // ---- ordering -----------------------------------------------------------
  const cloneMods = () => S.modules.map((m) => ({ ...m, lessons: m.lessons.slice() }));
  const structure = (mods) => mods.map((m) => ({ id: m.id, lessons: m.lessons.map((l) => l.id) }));

  function locate(mods, lid) {
    for (let mi = 0; mi < mods.length; mi++) {
      const li = mods[mi].lessons.findIndex((l) => l.id === lid);
      if (li > -1) return { mi, li };
    }
    return null;
  }

  async function commitOrder(mods) {
    const before = S;
    S = { ...S, modules: mods };
    renderTimeline();
    try {
      const j = await api("save_order", { modules: structure(mods) });
      S = j.state;
    } catch (e) {
      S = before;
      setSaved(e.message, true);
    }
    renderTimeline();
    updateChecklist();
  }

  function moveBy(lid, dir) {
    const mods = cloneMods();
    const at = locate(mods, lid);
    if (!at) return;
    const { mi, li } = at;
    const [lesson] = mods[mi].lessons.splice(li, 1);
    if (dir < 0) {
      if (li > 0) mods[mi].lessons.splice(li - 1, 0, lesson);
      else if (mi > 0) mods[mi - 1].lessons.push(lesson);
      else mods[mi].lessons.splice(0, 0, lesson);
    } else {
      if (li < mods[mi].lessons.length) mods[mi].lessons.splice(li + 1, 0, lesson);
      else if (mi < mods.length - 1) mods[mi + 1].lessons.unshift(lesson);
      else mods[mi].lessons.push(lesson);
    }
    commitOrder(mods);
  }

  function dropOn(targetLid, targetModuleId) {
    if (dragId === null) return;
    const mods = cloneMods();
    const from = locate(mods, dragId);
    if (!from) return;
    const [lesson] = mods[from.mi].lessons.splice(from.li, 1);
    if (targetLid) {
      const to = locate(mods, targetLid);
      if (!to) return;
      mods[to.mi].lessons.splice(to.li, 0, lesson);
    } else {
      const mod = mods.find((m) => m.id === targetModuleId);
      if (!mod) return;
      mod.lessons.unshift(lesson);
    }
    commitOrder(mods);
  }

  // ---- timeline events (delegated) ----------------------------------------
  tl.addEventListener("click", async (e) => {
    const b = e.target.closest("button");
    if (!b) return;
    try {
      if (b.dataset.up) moveBy(+b.dataset.up, -1);
      else if (b.dataset.dn) moveBy(+b.dataset.dn, 1);
      else if (b.dataset.rm) {
        if (!confirm("Remove this lesson from the course?")) return;
        S = (await api("remove_lesson", { id: +b.dataset.rm })).state;
        renderTimeline(); updateChecklist();
      } else if (b.dataset.split) {
        const j = await api("split", { id: +b.dataset.split });
        S = j.state; renderTimeline();
        const inp = tl.querySelector('[data-mt="' + j.newModuleId + '"]');
        if (inp) { inp.focus(); inp.select(); }
      } else if (b.dataset.merge) {
        S = (await api("merge", { id: +b.dataset.merge })).state;
        renderTimeline();
      } else if (b.dataset.cancel) {
        pending = pending.filter((p) => p.pid !== +b.dataset.cancel);
        renderTimeline(); updateChecklist();
      } else if (b.dataset.retry) {
        const p = pending.find((x) => x.pid === +b.dataset.retry);
        if (p) { p.status = "queued"; p.msg = ""; renderTimeline(); runQueue(); }
      }
    } catch (err) { setSaved(err.message, true); }
  });

  tl.addEventListener("change", async (e) => {
    const t = e.target;
    if (!(t instanceof HTMLInputElement)) return;
    const kind = t.dataset.lt ? "lesson" : t.dataset.mt ? "module" : null;
    if (!kind) return;
    const id = +(t.dataset.lt || t.dataset.mt);
    const title = t.value.trim();
    if (!title) { renderTimeline(); return; }
    try { S = (await api("rename", { kind, id, title })).state; setSaved("Saved"); }
    catch (err) { setSaved(err.message, true); }
  });

  tl.addEventListener("dragstart", (e) => {
    const row = e.target.closest("[data-lid]");
    if (!row) return;
    dragId = +row.dataset.lid;
    row.classList.add("is-drag");
    e.dataTransfer.effectAllowed = "move";
    try { e.dataTransfer.setData("text/plain", String(dragId)); } catch {}
  });
  tl.addEventListener("dragend", () => {
    dragId = null;
    tl.querySelectorAll(".is-drag, .is-target").forEach((el) => el.classList.remove("is-drag", "is-target"));
  });
  tl.addEventListener("dragover", (e) => {
    if (dragId === null) return;
    const t = e.target.closest("[data-lid], [data-mh]");
    if (!t) return;
    e.preventDefault();
    tl.querySelectorAll(".is-target").forEach((el) => el.classList.remove("is-target"));
    t.classList.add("is-target");
  });
  tl.addEventListener("drop", (e) => {
    const t = e.target.closest("[data-lid], [data-mh]");
    if (!t || dragId === null) return;
    e.preventDefault();
    if (t.dataset.lid) { if (+t.dataset.lid !== dragId) dropOn(+t.dataset.lid, null); }
    else dropOn(null, +t.dataset.mh);
  });

  // ---- file inputs and drop zones -----------------------------------------
  $$("[data-cb-pick]").forEach((inp) => inp.addEventListener("change", () => { addFiles(inp.files); inp.value = ""; }));
  [$("[data-cb-drop]"), $("[data-cb-more]")].forEach((zone) => {
    zone.addEventListener("dragover", (e) => { if (dragId === null) { e.preventDefault(); zone.classList.add("is-over"); } });
    zone.addEventListener("dragleave", () => zone.classList.remove("is-over"));
    zone.addEventListener("drop", (e) => {
      if (dragId !== null) return;
      e.preventDefault();
      zone.classList.remove("is-over");
      addFiles(e.dataTransfer.files);
    });
  });
  $("[data-cb-blank]").addEventListener("click", async () => {
    startError("");
    try { await ensureCourse(); showBuilder(); $("[data-f=title]").focus(); }
    catch (e) { startError(e.message); }
  });

  // ---- details panel ------------------------------------------------------
  const savedEl = $("[data-cb-saved]");
  function setSaved(text, bad = false) {
    savedEl.textContent = text;
    savedEl.classList.toggle("is-bad", bad);
  }

  function priceKind() {
    if (cfg.subscription) return "paid";
    return val("kind") === "free" ? "free" : "paid";
  }

  function collect() {
    const out = {
      title: val("title"),
      summary: val("summary"),
      description: val("description"),
      accessDurationDays: val("accessDurationDays"),
      premiumPrice: val("premiumPrice"),
    };
    if (val("categoryId")) out.categoryId = +val("categoryId");
    if (cfg.subscription) {
      const checked = field("subscriptionIncluded").find((r) => r.checked);
      out.subscriptionIncluded = checked ? +checked.value : 1;
      out.price = val("price");
    } else {
      out.kind = priceKind();
      out.price = out.kind === "free" ? 0 : val("price");
      out.subscriptionIncluded = 1;
    }
    return out;
  }

  function fillForm() {
    if (!S) return;
    const c = S.course;
    field("title")[0].value = c.title;
    field("categoryId")[0].value = c.categoryId ? String(c.categoryId) : "";
    field("summary")[0].value = c.summary;
    field("description")[0].value = c.description;
    field("accessDurationDays")[0].value = c.accessDurationDays;
    field("premiumPrice")[0].value = c.premiumPrice;
    field("price")[0].value = c.price > 0 ? String(c.price) : "";
    if (cfg.subscription) {
      field("subscriptionIncluded").forEach((r) => { r.checked = +r.value === c.subscriptionIncluded; });
    } else {
      // A brand-new draft has price 0 because nothing is set yet, not because it's free.
      const touched = c.summary !== "" || c.description !== "" || c.title !== "";
      field("kind")[0].value = c.price > 0 || !touched ? "paid" : "free";
    }
    if (c.thumbnailUrl) { const img = $("[data-cb-thumb-img]"); img.src = c.thumbnailUrl; img.hidden = false; }
    syncPriceUi();
    $("[data-cb-pill]").textContent = c.status === "REJECTED" ? "Needs changes" : "Draft";
  }

  function syncPriceUi() {
    const price = field("price")[0];
    if (cfg.subscription) {
      const sep = (field("subscriptionIncluded").find((r) => r.checked) || {}).value === "0";
      $("[data-cb-pricebox]").hidden = !sep;
    } else {
      const free = priceKind() === "free";
      price.disabled = free;
      if (free) price.value = "";
      const earn = $("[data-cb-earn]");
      const p = Number(price.value) || 0;
      earn.textContent = free ? "Free courses are open to everyone who enrols." :
        p > 0 ? "You keep UGX " + Math.round(p * (1 - cfg.feeRate)).toLocaleString("en-US") + " of each sale after the " + Math.round(cfg.feeRate * 100) + "% platform fee." : "";
    }
  }

  let saveTimer = null;
  let saving = null;
  let again = false;
  function scheduleSave() {
    clearTimeout(saveTimer);
    setSaved("Saving…");
    saveTimer = setTimeout(save, 700);
  }
  function save() {
    clearTimeout(saveTimer);
    if (saving) { again = true; return saving; }
    saving = (async () => {
      try {
        await ensureCourse();
        await api("details", collect());
        setSaved("Draft saved");
      } catch (e) { setSaved("Not saved: " + e.message, true); }
      finally {
        saving = null;
        if (again) { again = false; return save(); }
      }
    })();
    return saving;
  }

  fields.forEach((f) => {
    const evt = f.tagName === "SELECT" || f.type === "radio" ? "change" : "input";
    f.addEventListener(evt, () => { syncPriceUi(); updateChecklist(); scheduleSave(); });
    if (evt === "input") f.addEventListener("change", () => { syncPriceUi(); updateChecklist(); });
  });

  const thumbInput = $("[data-cb-thumb]");
  thumbInput.addEventListener("change", async () => {
    const f = thumbInput.files[0];
    thumbInput.value = "";
    if (!f) return;
    const err = $("[data-cb-thumb-err]");
    err.hidden = true;
    try {
      await ensureCourse();
      const j = await upload("upload_thumbnail", f, () => {});
      S = j.state;
      const img = $("[data-cb-thumb-img]");
      img.src = S.course.thumbnailUrl;
      img.hidden = false;
      setSaved("Thumbnail saved");
    } catch (e) { err.textContent = e.message; err.hidden = false; }
  });

  // ---- checklist and submit -----------------------------------------------
  function readiness() {
    const f = collect();
    const left = pending.length;
    const price = Number(f.price) || 0;
    let priceOk;
    if (cfg.subscription) priceOk = f.subscriptionIncluded === 1 || price > 0;
    else priceOk = f.kind === "free" || price > 0;
    return [
      ["Name your course", f.title.trim().length >= 4],
      ["Pick a category", !!f.categoryId],
      [cfg.subscription ? "Set a price for this course" : "Set a price, or choose Free", priceOk],
      ["Write a one-line summary", f.summary.trim().length >= 10],
      ["Describe what learners get", f.description.trim().length >= 20],
      [left ? "Finish uploading (" + left + " left)" : "Upload at least one lesson", lessonCount() > 0 && left === 0],
    ];
  }

  function updateChecklist() {
    if (buildEl.hidden) return;
    const r = readiness();
    const done = r.filter((x) => x[1]).length;
    $("[data-cb-left]").innerHTML = r.map((x) => '<li class="' + (x[1] ? "is-ok" : "") + '"><span class="cb-d">' + TICK + "</span>" + esc(x[0]) + "</li>").join("");
    $("[data-cb-meter]").style.width = (done / r.length) * 100 + "%";
    const all = done === r.length && !submitted;
    $("[data-cb-submit]").disabled = !all;
    const hint = $("[data-cb-hint]");
    if (submitted) hint.textContent = "Waiting for admin review.";
    else if (all) hint.textContent = S && S.course.thumbnailUrl ? "" : "Tip: a thumbnail helps the course sell (under More options).";
    else hint.textContent = r.length - done + " thing" + (r.length - done === 1 ? "" : "s") + " left";
    renderSub();
  }

  $("[data-cb-submit]").addEventListener("click", async () => {
    const btn = $("[data-cb-submit]");
    const errEl = $("[data-cb-submit-error]");
    errEl.hidden = true;
    btn.disabled = true;
    btn.textContent = "Submitting…";
    try {
      await save();
      await api("submit", { kind: priceKind() });
      submitted = true;
      $("[data-cb-pill]").textContent = "Pending review";
      $("[data-cb-pill]").classList.add("is-pending");
      btn.textContent = "Submitted";
      $("[data-cb-hint]").innerHTML = 'Sent for review. <a href="' + esc(cfg.manageUrl + courseId) + '">Open the course page</a> or <a href="' + esc(cfg.listUrl) + '">go to My Courses</a>.';
      $$("input, select, textarea, button", root).forEach((el) => { if (el !== btn && !el.closest("a")) el.disabled = true; });
    } catch (e) {
      errEl.innerHTML = esc(e.message) + (e.problems ? "<ul>" + e.problems.map((p) => "<li>" + esc(p) + "</li>").join("") + "</ul>" : "");
      errEl.hidden = false;
      btn.textContent = "Submit for review";
      updateChecklist();
    }
  });

  window.addEventListener("beforeunload", (e) => {
    if (pending.some((p) => p.status !== "error")) { e.preventDefault(); e.returnValue = ""; }
  });

  function renderAll() {
    renderTimeline();
    updateChecklist();
  }

  // ---- boot ---------------------------------------------------------------
  if (S) {
    fillForm();
    showBuilder();
    setSaved("Draft saved");
  }
})();
