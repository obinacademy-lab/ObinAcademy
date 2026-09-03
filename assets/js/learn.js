document.addEventListener("DOMContentLoaded", () => {
  const lessons = window.OBIN_LESSONS || [];
  const streamBase = window.OBIN_STREAM_BASE || "/stream.php";
  const updateProgressUrl = window.OBIN_UPDATE_PROGRESS_URL || "/api/update-progress.php";
  const certificateUrlBase = window.OBIN_CERTIFICATE_URL_BASE || "/certificate.php";
  const courseId = window.OBIN_COURSE_ID;
  const isPremium = !!window.OBIN_IS_PREMIUM;
  let progress = Number(window.OBIN_INITIAL_PROGRESS) || 0;
  const total = lessons.length;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";

  const videoWrap = document.querySelector("[data-video-wrap]");
  const heading = document.querySelector("[data-lesson-heading]");
  const counter = document.querySelector("[data-lesson-counter]");
  const downloadLink = document.querySelector("[data-download-link]");
  const markBtn = document.querySelector("[data-mark-complete]");
  const progressFill = document.querySelector("[data-progress-fill]");
  const progressLabel = document.querySelector("[data-progress-label]");
  const lessonButtons = [...document.querySelectorAll("[data-lesson-btn]")];

  let activeIndex = 0;

  function renderLesson(index) {
    const lesson = lessons[index];
    if (!lesson) return;
    activeIndex = index;

    const src = `${streamBase}?lesson=${lesson.id}`;
    videoWrap.innerHTML = "";
    if (lesson.type === "VIDEO") {
      const video = document.createElement("video");
      video.controls = true;
      video.setAttribute("controlslist", "nodownload noremoteplayback");
      video.setAttribute("disablepictureinpicture", "");
      video.src = src;
      videoWrap.appendChild(video);
    } else {
      // #toolbar=0 hides the native PDF viewer's own toolbar — which has its
      // own Download button — so a non-premium learner can't get a download
      // out of the "view" path; stream.php's own premium check is the only
      // thing that ever hands out download=1.
      const fullscreenUrl = src + "#toolbar=0";
      const expandIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>';

      // A PDF's own scroll living inside an iframe, inside a page that also
      // scrolls, is a "nested scrollable region" that mobile touch-gesture
      // arbitration handles unreliably across browsers — desktop's mouse
      // wheel has no equivalent ambiguity, which is why the embedded iframe
      // works fine there but not on phones (reported as stuck scrolling,
      // and separately as the iframe rendering as a plain black box —
      // both symptoms of the same underlying nested-iframe-PDF unreliability
      // on mobile). Rather than keep chasing that, mobile skips the iframe
      // entirely and shows a plain card whose only job is the fullscreen
      // link below — nothing left in that space that can render broken.
      if (window.matchMedia("(max-width: 640px)").matches) {
        const card = document.createElement("div");
        card.className = "pdf-mobile-card";

        const icon = document.createElement("div");
        icon.className = "pdf-mobile-icon";
        icon.textContent = "📄";
        card.appendChild(icon);

        const title = document.createElement("p");
        title.className = "pdf-mobile-title";
        title.textContent = lesson.title;
        card.appendChild(title);

        const hint = document.createElement("p");
        hint.className = "pdf-mobile-hint";
        hint.textContent = "PDFs open best in fullscreen on mobile";
        card.appendChild(hint);

        const link = document.createElement("a");
        link.href = fullscreenUrl;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.className = "pdf-fullscreen-link pdf-fullscreen-link-lg";
        link.innerHTML = expandIcon + "<span>Open Fullscreen</span>";
        card.appendChild(link);

        videoWrap.appendChild(card);
      } else {
        const iframe = document.createElement("iframe");
        iframe.src = fullscreenUrl;
        iframe.title = lesson.title;
        videoWrap.appendChild(iframe);

        const fullscreenLink = document.createElement("a");
        fullscreenLink.href = fullscreenUrl;
        fullscreenLink.target = "_blank";
        fullscreenLink.rel = "noopener noreferrer";
        fullscreenLink.className = "pdf-fullscreen-link";
        fullscreenLink.innerHTML = expandIcon + "<span>Open Fullscreen</span>";
        videoWrap.appendChild(fullscreenLink);
      }
    }

    heading.textContent = lesson.title;
    counter.textContent = `Lesson ${index + 1} of ${total}`;
    if (downloadLink) {
      if (isPremium) {
        downloadLink.href = `${src}&download=1`;
        downloadLink.classList.remove("hidden");
      } else {
        downloadLink.classList.add("hidden");
      }
    }

    lessonButtons.forEach((btn) => btn.classList.toggle("active", Number(btn.dataset.lessonIndex) === index));
    document.querySelector("[data-learn-sidebar]")?.classList.remove("open");
    document.querySelector("[data-learn-overlay]")?.classList.remove("open");
  }

  lessonButtons.forEach((btn) => {
    btn.addEventListener("click", () => renderLesson(Number(btn.dataset.lessonIndex)));
  });

  const certificateBanner = document.querySelector("[data-certificate-banner]");
  const certificateLink = document.querySelector("[data-certificate-link]");

  markBtn?.addEventListener("click", async () => {
    const newProgress = total > 0 ? Math.min(100, ((activeIndex + 1) / total) * 100) : 100;
    progress = newProgress;
    if (progressFill) progressFill.style.width = `${Math.round(progress)}%`;
    if (progressLabel) progressLabel.textContent = `${Math.round(progress)}% complete`;

    try {
      const res = await fetch(updateProgressUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ courseId, progress: newProgress, csrf_token: csrfToken }),
      });
      const data = await res.json();
      if (data.certificateCode && certificateBanner && certificateLink) {
        certificateLink.href = `${certificateUrlBase}?code=${encodeURIComponent(data.certificateCode)}`;
        certificateBanner.classList.remove("hidden");
        certificateBanner.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }
    } catch {}

    if (activeIndex + 1 < total) renderLesson(activeIndex + 1);
  });

  const sidebar = document.querySelector("[data-learn-sidebar]");
  const overlay = document.querySelector("[data-learn-overlay]");
  document.querySelectorAll("[data-learn-open]").forEach((btn) =>
    btn.addEventListener("click", () => { sidebar?.classList.add("open"); overlay?.classList.add("open"); })
  );
  document.querySelectorAll("[data-learn-close]").forEach((btn) =>
    btn.addEventListener("click", () => { sidebar?.classList.remove("open"); overlay?.classList.remove("open"); })
  );

  if (lessons.length > 0) renderLesson(0);
});
