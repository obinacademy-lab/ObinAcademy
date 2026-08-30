document.addEventListener("DOMContentLoaded", () => {
  const lessons = window.OBIN_LESSONS || [];
  const streamBase = window.OBIN_STREAM_BASE || "/stream.php";
  const updateProgressUrl = window.OBIN_UPDATE_PROGRESS_URL || "/api/update-progress.php";
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
      const iframe = document.createElement("iframe");
      iframe.src = src + "#toolbar=0";
      iframe.title = lesson.title;
      videoWrap.appendChild(iframe);
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

  markBtn?.addEventListener("click", async () => {
    const newProgress = total > 0 ? Math.min(100, ((activeIndex + 1) / total) * 100) : 100;
    progress = newProgress;
    if (progressFill) progressFill.style.width = `${Math.round(progress)}%`;
    if (progressLabel) progressLabel.textContent = `${Math.round(progress)}% complete`;

    fetch(updateProgressUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ courseId, progress: newProgress, csrf_token: csrfToken }),
    }).catch(() => {});

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
