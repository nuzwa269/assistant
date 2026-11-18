/* Public landing interactions only. No application API calls or persistence. */
(function () {
  'use strict';
  function boot() {
    document.querySelectorAll('.cpl').forEach(function (root) {
      if (root.dataset.enhanced) return;
      root.dataset.enhanced = 'true';
      var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
      var desktop = window.matchMedia('(min-width: 901px)');
      var menu = root.querySelector('.cpl-menu');
      var summary = menu.querySelector('summary');
      var header = root.querySelector('.cpl-nav');
      var workspace = root.querySelector('.cpl-workspace');
      var journey = root.querySelector('.cpl-journey');
      var scheduled = false;
      function menuState() {
        // Open details on desktop so links remain in the accessibility tree.
        menu.open = desktop.matches;
      }
      menuState();
      desktop.addEventListener('change', menuState);
      menu.addEventListener('click', function (event) {
        if (event.target.closest('a') && !desktop.matches) menu.open = false;
      });
      root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menu.open && !desktop.matches) {
          menu.open = false;
          summary.focus();
        }
      });
      document.addEventListener('click', function (event) {
        if (!desktop.matches && menu.open && !header.contains(event.target)) menu.open = false;
      });
      var samples = {
        prompt: ['SAMPLE PROMPT', 'Help me turn a broad idea into a clear brief. Ask about my audience, my goal, and the format I need.'],
        project: ['SAMPLE PROJECT OUTLINE', 'Workshop planning: keep the audience brief, session ideas, and follow-up conversations together in one project.'],
        saved: ['SAMPLE SAVED RESPONSE', 'A useful starting point: define the audience, choose one clear goal, and decide what the finished work should look like.']
      };
      root.querySelectorAll('[data-demo]').forEach(function (button) {
        button.addEventListener('click', function () {
          root.querySelectorAll('[data-demo]').forEach(function (item) { item.setAttribute('aria-pressed', String(item === button)); });
          var sample = samples[button.dataset.demo];
          root.querySelector('[data-demo-label]').textContent = sample[0];
          root.querySelector('[data-demo-copy]').textContent = sample[1];
        });
      });
      // Native details retain keyboard and no-JS support for the full catalog.
      root.querySelectorAll('.cpl-assistant').forEach(function (item) {
        item.addEventListener('toggle', function () {
          if (!item.open) return;
          root.querySelectorAll('.cpl-assistant').forEach(function (other) { if (other !== item) other.open = false; });
        });
      });
      if ('IntersectionObserver' in window && !motion.matches) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.08 });
        root.querySelectorAll('[data-reveal]').forEach(function (item) {
          // Never hide content already in view when scripts arrive late.
          if (item.getBoundingClientRect().top > window.innerHeight) item.classList.add('will-reveal');
          observer.observe(item);
        });
      }
      function frame() {
        scheduled = false;
        var height = window.innerHeight;
        // Batch geometry reads before changing styles.
        var headerRect = header.getBoundingClientRect();
        var rootRect = root.getBoundingClientRect();
        var previewRect = workspace.getBoundingClientRect();
        var journeyRect = journey.getBoundingClientRect();
        header.classList.toggle('is-scrolled', headerRect.top <= 1 && rootRect.top < -10);
        var progress = Math.max(0, Math.min(1, (height * 0.8 - journeyRect.top) / (journeyRect.height + height * 0.25)));
        journey.style.setProperty('--progress', motion.matches ? '1' : String(progress));
        if (!motion.matches && desktop.matches && previewRect.bottom > 0 && previewRect.top < height) {
          workspace.style.setProperty('--depth', Math.max(-20, Math.min(48, (height * 0.5 - previewRect.top) * 0.12)) + 'px');
        } else workspace.style.setProperty('--depth', '0px');
      }
      function schedule() { if (!scheduled) { scheduled = true; window.requestAnimationFrame(frame); } }
      window.addEventListener('scroll', schedule, { passive: true });
      window.addEventListener('resize', schedule, { passive: true });
      motion.addEventListener('change', function () {
        root.querySelectorAll('.will-reveal').forEach(function (item) { item.classList.add('is-visible'); });
        schedule();
      });
      root.classList.add('motion-ready');
      schedule();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
