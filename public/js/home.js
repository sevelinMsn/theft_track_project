/**
 * Theft Track & Reporting — Home page (stats counter animation)
 */
(function () {
  "use strict";

  function animateCounter(el, target, duration) {
    const start = 0;
    const startTime = performance.now();

    function update(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(start + (target - start) * eased);
      el.textContent = current.toLocaleString();
      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        el.textContent = target.toLocaleString();
      }
    }

    requestAnimationFrame(update);
  }

  function initStatsCounters() {
    const statValues = document.querySelectorAll(".home-stat-value[data-count]");
    if (!statValues.length) return;

    const observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          const el = entry.target;
          if (el.dataset.animated === "true") return;
          el.dataset.animated = "true";
          const target = parseInt(el.getAttribute("data-count"), 10) || 0;
          animateCounter(el, target, 1800);
          observer.unobserve(el);
        });
      },
      { threshold: 0.35 }
    );

    statValues.forEach(function (el) {
      observer.observe(el);
    });
  }

  document.addEventListener("DOMContentLoaded", initStatsCounters);
})();
