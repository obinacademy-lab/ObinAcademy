document.addEventListener("DOMContentLoaded", () => {
  const lessons = window.OBIN_LESSONS || [];
  const streamBase = window.OBIN_STREAM_BASE || "/stream.php";
  const updateProgressUrl = window.OBIN_UPDATE_PROGRESS_URL || "/api/update-progress.php";
  const trackWatchTimeUrl = window.OBIN_TRACK_WATCH_TIME_URL || "/api/track-watch-time.php";
  const markLessonCompleteUrl = window.OBIN_MARK_LESSON_COMPLETE_URL || "/api/mark-lesson-complete.php";
  const certificateUrlBase = window.OBIN_CERTIFICATE_URL_BASE || "/certificate.php";
  const courseId = window.OBIN_COURSE_ID;
  const canDownload = !!window.OBIN_CAN_DOWNLOAD;
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

  // Video watch-time, reported in ~20s batches rather than on every
  // timeupdate tick (which fires several times a second) — feeds the
  // creator subscription payout pool (see includes/subscriptions.php).
  // Each per-tick delta is clamped below so a seek/jump can't be reported
  // as real watched time.
  let watchState = { lessonId: null, lastTime: null, unreportedSeconds: 0 };

  function reportWatchTime(lessonId, seconds, useBeacon) {
    if (seconds <= 0) return;
    const payload = JSON.stringify({ lessonId, seconds, csrf_token: csrfToken });
    if (useBeacon && navigator.sendBeacon) {
      navigator.sendBeacon(trackWatchTimeUrl, new Blob([payload], { type: "application/json" }));
    } else {
      fetch(trackWatchTimeUrl, { method: "POST", headers: { "Content-Type": "application/json" }, body: payload, keepalive: true }).catch(() => {});
    }
  }

  // Called before switching lessons, and on tab-hide/unload, so up to ~19s
  // of real watch-time isn't silently lost every time a learner moves on.
  function flushWatchTime(useBeacon) {
    if (watchState.lessonId !== null && watchState.unreportedSeconds > 0) {
      reportWatchTime(watchState.lessonId, Math.round(watchState.unreportedSeconds), useBeacon);
    }
    watchState = { lessonId: null, lastTime: null, unreportedSeconds: 0 };
  }

  function renderLesson(index) {
    const lesson = lessons[index];
    if (!lesson) return;
    flushWatchTime();
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

      watchState = { lessonId: lesson.id, lastTime: null, unreportedSeconds: 0 };
      video.addEventListener("timeupdate", () => {
        if (watchState.lessonId !== lesson.id) return; // a stale listener from a lesson already switched away from
        if (watchState.lastTime !== null) {
          const delta = video.currentTime - watchState.lastTime;
          if (delta > 0 && delta < 2) watchState.unreportedSeconds += delta;
        }
        watchState.lastTime = video.currentTime;
        if (watchState.unreportedSeconds >= 20) {
          reportWatchTime(lesson.id, Math.round(watchState.unreportedSeconds));
          watchState.unreportedSeconds = 0;
        }
      });
    } else {
      // The browser's own native PDF plugin doesn't reliably support
      // touch-scroll when embedded in an iframe on mobile (it just shows
      // page 1 with no way to swipe through the rest). Our own bundled
      // PDF.js viewer (assets/pdfjs) renders to <canvas> with its own touch
      // handling, so it scrolls/pinches correctly on every device while
      // staying embedded — which also means Ctrl+S targets our page, not a
      // standalone PDF tab, and we control which toolbar buttons it shows
      // (obin-overrides.css/js) so a non-premium learner on a paid course
      // doesn't get a save/print button at all.
      const viewerBase = window.OBIN_PDFJS_VIEWER_URL || "/assets/pdfjs/web/viewer.html";
      const viewerSrc = `${viewerBase}?file=${encodeURIComponent(src)}&download=${canDownload ? "1" : "0"}`;
      const iframe = document.createElement("iframe");
      iframe.src = viewerSrc;
      iframe.title = lesson.title;
      iframe.className = "pdf-viewer-frame";
      videoWrap.appendChild(iframe);
    }

    heading.textContent = lesson.title;
    counter.textContent = `Lesson ${index + 1} of ${total}`;
    if (downloadLink) {
      if (canDownload) {
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
    const currentLesson = lessons[activeIndex];
    const newProgress = total > 0 ? Math.min(100, ((activeIndex + 1) / total) * 100) : 100;
    progress = newProgress;
    if (progressFill) progressFill.style.width = `${Math.round(progress)}%`;
    if (progressLabel) progressLabel.textContent = `${Math.round(progress)}% complete`;

    // A PDF/text lesson has no playback clock to earn watch-time from, so
    // its own "mark complete" click is what credits the creator payout pool
    // instead — a video lesson's watch-time already comes from timeupdate
    // heartbeats above, so it doesn't also fire this (that would double-count).
    if (currentLesson && currentLesson.type !== "VIDEO") {
      fetch(markLessonCompleteUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ lessonId: currentLesson.id, csrf_token: csrfToken }),
      }).catch(() => {});
    }

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

  // beforeunload is unreliable on mobile browsers — visibilitychange (tab
  // backgrounded, app switched away from) is the one that actually fires
  // reliably there, so flush on both.
  document.addEventListener("visibilitychange", () => { if (document.hidden) flushWatchTime(true); });
  window.addEventListener("beforeunload", () => flushWatchTime(true));

  if (lessons.length > 0) renderLesson(0);
});
