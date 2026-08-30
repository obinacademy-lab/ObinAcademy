// Shared site behavior: mobile nav toggle, flash-message auto-dismiss.
document.addEventListener("DOMContentLoaded", () => {
  // Header "solidifies" (deeper bg + shadow) once the page scrolls.
  const siteHeader = document.querySelector("[data-site-header]");
  if (siteHeader) {
    const updateHeaderScrolled = () => {
      siteHeader.classList.toggle("scrolled", window.scrollY > 8);
    };
    updateHeaderScrolled();
    window.addEventListener("scroll", updateHeaderScrolled, { passive: true });
  }

  const toggle = document.querySelector("[data-nav-toggle]");
  const menu = document.querySelector("[data-mobile-menu]");
  if (toggle && menu) {
    toggle.addEventListener("click", () => {
      menu.classList.toggle("open");
      const expanded = menu.classList.contains("open");
      toggle.setAttribute("aria-expanded", String(expanded));
    });
    menu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => menu.classList.remove("open"));
    });
  }

  document.querySelectorAll("[data-flash]").forEach((el) => {
    setTimeout(() => el.remove(), 6000);
  });

  // Hero background slideshow — crossfades to the next image on an interval.
  const heroSlides = document.querySelector("[data-hero-slides]");
  if (heroSlides) {
    const slides = heroSlides.querySelectorAll("img");
    const interval = parseInt(heroSlides.getAttribute("data-interval"), 10) || 5000;
    if (slides.length > 1) {
      let current = 0;
      setInterval(() => {
        slides[current].classList.remove("active");
        current = (current + 1) % slides.length;
        slides[current].classList.add("active");
      }, interval);
    }
  }

  // Animated count-up stats (hero numbers, etc.)
  function easeOutCubic(t) { return 1 - Math.pow(1 - t, 3); }
  const countEls = document.querySelectorAll("[data-count-up]");
  const prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function runCountUp(el) {
    const target = parseInt(el.getAttribute("data-count-value"), 10) || 0;
    const suffix = el.textContent.replace(/^[0-9]+/, "") || "+";
    if (prefersReducedMotion) { el.textContent = target + suffix; return; }
    const duration = 2800;
    const start = performance.now();
    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      el.textContent = Math.round(easeOutCubic(progress) * target) + suffix;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  if (countEls.length) {
    const started = new WeakSet();
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting && !started.has(entry.target)) {
          started.add(entry.target);
          runCountUp(entry.target);
        }
      });
    }, { threshold: 0.3 });
    countEls.forEach((el) => {
      const rect = el.getBoundingClientRect();
      const alreadyVisible = rect.top < window.innerHeight && rect.bottom > 0;
      if (alreadyVisible) {
        started.add(el);
        runCountUp(el);
      } else {
        observer.observe(el);
      }
    });
  }

  // Scroll-reveal: fade/slide elements in as they enter the viewport. Reused
  // across any page — just add class="reveal" (optionally with a
  // "reveal-delay-N" class for staggered groups, N = 1..6).
  const revealEls = document.querySelectorAll(".reveal");
  if (revealEls.length) {
    if (prefersReducedMotion) {
      revealEls.forEach((el) => el.classList.add("in-view"));
    } else {
      const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("in-view");
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
      revealEls.forEach((el) => revealObserver.observe(el));
    }
  }

  // FAQ accordion: click a .faq-question to open/close its .faq-item. Only
  // one item stays open at a time per .faq-list. The CSS transition is on
  // max-height, so we measure each answer's real content height (scrollHeight
  // ignores its own overflow:hidden/max-height clipping) and set it inline —
  // that's what makes the collapse/expand animate smoothly to an exact stop
  // instead of guessing a fixed max-height.
  document.querySelectorAll(".faq-list").forEach((list) => {
    const items = list.querySelectorAll(".faq-item");
    items.forEach((item) => {
      const question = item.querySelector(".faq-question");
      const answer = item.querySelector(".faq-answer");
      if (!question || !answer) return;
      question.addEventListener("click", () => {
        const isOpen = item.classList.contains("open");
        items.forEach((other) => {
          other.classList.remove("open");
          other.querySelector(".faq-question")?.setAttribute("aria-expanded", "false");
          const otherAnswer = other.querySelector(".faq-answer");
          if (otherAnswer) otherAnswer.style.maxHeight = "";
        });
        if (!isOpen) {
          item.classList.add("open");
          question.setAttribute("aria-expanded", "true");
          answer.style.maxHeight = answer.scrollHeight + "px";
        }
      });
    });
  });

  // Adds a spinner + disables the submit button the instant a form with
  // [data-loading-submit] is submitted, so slower connections get instant
  // feedback while the normal full-page POST completes.
  document.querySelectorAll("[data-loading-submit]").forEach((form) => {
    form.addEventListener("submit", () => {
      const btn = form.querySelector('button[type="submit"]');
      if (!btn || btn.classList.contains("is-loading")) return;
      btn.classList.add("is-loading");
      const label = btn.querySelector("[data-btn-label]");
      if (label) label.style.opacity = "0";
      const spinner = document.createElement("span");
      spinner.className = "btn-spinner";
      spinner.style.position = "absolute";
      btn.style.position = "relative";
      btn.appendChild(spinner);
    });
  });

  const dashSidebar = document.querySelector("[data-dash-sidebar]");
  const dashOverlay = document.querySelector("[data-dash-overlay]");
  const dashOpenBtns = document.querySelectorAll("[data-dash-open]");
  const dashCloseBtns = document.querySelectorAll("[data-dash-close]");
  function setSidebar(open) {
    dashSidebar?.classList.toggle("open", open);
    dashOverlay?.classList.toggle("open", open);
  }
  dashOpenBtns.forEach((btn) => btn.addEventListener("click", () => setSidebar(true)));
  dashCloseBtns.forEach((btn) => btn.addEventListener("click", () => setSidebar(false)));
});
