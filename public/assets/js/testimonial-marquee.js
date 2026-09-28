/**
 * Infinite testimonial marquee helpers.
 * Animation is CSS-driven; this only tunes duration + pause/reduced-motion.
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function initMarquee(root) {
    var track = root.querySelector('.tm-marquee-track');
    var group = root.querySelector('.tm-marquee-group');
    if (!track || !group) return;

    if (prefersReducedMotion()) {
      root.classList.add('is-static');
      track.style.animation = 'none';
      track.style.transform = 'none';
      return;
    }

    function applyDuration() {
      var groupWidth = group.scrollWidth || group.getBoundingClientRect().width;
      if (!groupWidth) return;
      var seconds = Math.max(32, Math.round(groupWidth / 30));
      root.style.setProperty('--tm-duration', seconds + 's');
    }

    applyDuration();
    // Restart after measure so loop stays seamless at new duration
    track.style.animation = 'none';
    void track.offsetWidth;
    track.style.animation = '';

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(applyDuration, 150);
    });

    root.addEventListener('touchstart', function () {
      root.classList.add('is-paused');
    }, { passive: true });
    root.addEventListener('touchend', function () {
      root.classList.remove('is-paused');
    }, { passive: true });
    root.addEventListener('touchcancel', function () {
      root.classList.remove('is-paused');
    }, { passive: true });
  }

  function boot() {
    document.querySelectorAll('[data-testimonial-marquee]').forEach(initMarquee);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
