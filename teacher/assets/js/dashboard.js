// Dashboard JS: dropdown toggle, click-outside, and live clocks
(function(){
  // Profile/menu toggle (works for profileMenu and optional newMenu if present)
  function wireToggle(btnId, menuId){
    var btn = document.getElementById(btnId);
    var menu = document.getElementById(menuId);
    if (!btn || !menu) return;
    function close(){ menu.style.display = 'none'; btn.setAttribute('aria-expanded','false'); }
    function open(){ menu.style.display = 'block'; btn.setAttribute('aria-expanded','true'); }
    btn.addEventListener('click', function(e){ e.stopPropagation(); if (menu.style.display === 'block') close(); else open(); });
    document.addEventListener('click', function(e){ if (!menu.contains(e.target) && e.target !== btn) close(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
  }
  wireToggle('profileBtn','profileMenu');
  wireToggle('newBtn','newMenu');

  // Page-change fade animation: intercept navbar link clicks and fade page briefly
  (function(){
    var overlay = document.getElementById('pageFadeOverlay');
    var navLinks = document.querySelectorAll('.navbar .nav-link');
    if (!navLinks || navLinks.length===0 || !overlay) return;
    // if user prefers reduced motion, do not intercept
    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function fadeAndNavigate(href){
      overlay.classList.add('fade-active');
      setTimeout(function(){ window.location.href = href; }, prefersReduced ? 0 : 700);
    }
    navLinks.forEach(function(a){
      a.addEventListener('click', function(e){
        var href = a.getAttribute('href');
        if (!href || href.indexOf('#') === 0 || a.target === '_blank') return; // allow anchors and external
        e.preventDefault();
        try { fadeAndNavigate(href); } catch (err) { window.location.href = href; }
      });
    });
  })();

  // Live clocks updater
  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function updateClocks(){
    var now = new Date();
    var h = pad(now.getHours());
    var m = pad(now.getMinutes());
    var navH = document.getElementById('nav-hour');
    var navM = document.getElementById('nav-minute');
    if (navH) navH.textContent = h;
    if (navM) navM.textContent = m;
    var heroH = document.getElementById('hero-hour');
    var heroM = document.getElementById('hero-minute');
    if (heroH) heroH.textContent = h;
    if (heroM) heroM.textContent = m;
    // Add a subtle pulse animation class on minute change
    if (typeof window._lastMinute === 'undefined') window._lastMinute = now.getMinutes();
    if (window._lastMinute !== now.getMinutes()){
      window._lastMinute = now.getMinutes();
      var pulses = document.querySelectorAll('.minute-pulse');
      pulses.forEach(function(el){
        el.classList.remove('pulse-on');
        // trigger reflow
        void el.offsetWidth;
        el.classList.add('pulse-on');
      });
    }
  }
  updateClocks();
  setInterval(updateClocks, 1000 * 15);

  // Mark page ready so CSS fade-in can run
  (function(){
    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReduced) {
      document.documentElement.classList.add('page-ready');
      return;
    }
    // Defer to next frame so transitions run
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){ document.documentElement.classList.add('page-ready'); });
    });
  })();
})();
