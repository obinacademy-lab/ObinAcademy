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

  // Testimonials slider: a horizontal scroll-snap track (swipeable natively
  // on touch) with arrow buttons, dot indicators, and autoplay that pauses
  // on hover/touch. Dots stay in sync even when the visitor swipes manually
  // by watching for the slide whose center lands closest to the track center.
  const storiesSlider = document.querySelector("[data-stories-slider]");
  if (storiesSlider) {
    const track = storiesSlider.querySelector("[data-stories-track]");
    const slides = Array.from(track.children);
    const dotsWrap = storiesSlider.querySelector("[data-stories-dots]");
    let current = 0;
    let autoplayTimer = null;

    slides.forEach((_, i) => {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.setAttribute("aria-label", `Go to story ${i + 1}`);
      dot.addEventListener("click", () => { goTo(i); resetAutoplay(); });
      dotsWrap?.appendChild(dot);
    });
    const dots = dotsWrap ? Array.from(dotsWrap.children) : [];

    function setActive(i) {
      current = i;
      dots.forEach((d, idx) => d.classList.toggle("active", idx === i));
    }

    function goTo(i) {
      const clamped = (i + slides.length) % slides.length;
      const slide = slides[clamped];
      // scrollIntoView's inline centering is unreliable for a nested horizontal
      // scroller in some browsers, so compute the delta to the slide's center
      // directly off live geometry and scroll the track itself by that amount.
      const trackRect = track.getBoundingClientRect();
      const slideRect = slide.getBoundingClientRect();
      const delta = (slideRect.left + slideRect.width / 2) - (trackRect.left + trackRect.width / 2);
      track.scrollBy({ left: delta, behavior: prefersReducedMotion ? "auto" : "smooth" });
      setActive(clamped);
    }

    storiesSlider.querySelector("[data-stories-prev]")?.addEventListener("click", () => { goTo(current - 1); resetAutoplay(); });
    storiesSlider.querySelector("[data-stories-next]")?.addEventListener("click", () => { goTo(current + 1); resetAutoplay(); });

    function syncActiveFromGeometry() {
      const centerX = track.getBoundingClientRect().left + track.clientWidth / 2;
      let closest = 0, closestDist = Infinity;
      slides.forEach((slide, i) => {
        const r = slide.getBoundingClientRect();
        const dist = Math.abs((r.left + r.width / 2) - centerX);
        if (dist < closestDist) { closestDist = dist; closest = i; }
      });
      setActive(closest);
    }
    // "scrollend" fires exactly once, once scrolling has truly stopped — the
    // reliable signal here. A "scroll"+debounce fallback covers older
    // browsers, but firing it on every scroll tick risks recomputing mid-
    // animation (a brief pause between dispatched scroll events can look
    // like "settled" before a smooth-scroll actually finishes), so it only
    // runs where "scrollend" isn't supported.
    if ("onscrollend" in window) {
      track.addEventListener("scrollend", syncActiveFromGeometry, { passive: true });
    } else {
      let scrollSettleTimer;
      track.addEventListener("scroll", () => {
        clearTimeout(scrollSettleTimer);
        scrollSettleTimer = setTimeout(syncActiveFromGeometry, 200);
      }, { passive: true });
    }

    function startAutoplay() {
      clearInterval(autoplayTimer); // guards against stacked timers if start fires more than once in a row (e.g. repeated mouseleave)
      if (prefersReducedMotion || slides.length < 2) return;
      autoplayTimer = setInterval(() => goTo(current + 1), 6000);
    }
    function stopAutoplay() { clearInterval(autoplayTimer); }
    function resetAutoplay() { stopAutoplay(); startAutoplay(); }

    storiesSlider.addEventListener("mouseenter", stopAutoplay);
    storiesSlider.addEventListener("mouseleave", startAutoplay);
    storiesSlider.addEventListener("touchstart", stopAutoplay, { passive: true });

    setActive(0);
    startAutoplay();
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
