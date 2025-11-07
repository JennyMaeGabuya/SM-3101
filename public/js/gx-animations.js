/* gx-animations.js
   Tiny opt-in motion utilities. No external libs.

   Global flags (read-only):
     window.GX_DISABLE_ANIM  -> if true, no enhancements
     window.GX_AUTO_REVEAL   -> boolean, default false (whitelist mode)
     window.GX_EXCLUDE_SELECTORS -> CSS selector string of elements to exclude

   API:
     GX.enable() -> bind all behaviors
     GX.observe(el) -> bind behaviors to a newly inserted element

   Design goals: namespaced classes, progressive enhancement, respects prefers-reduced-motion, small fast handlers.
*/
(function(){
  'use strict'

  if (typeof window === 'undefined') return

  // Guard: allow explicit opt-out
  if (window.GX_DISABLE_ANIM) return

  // Respect user motion preferences
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (prefersReduced) {
    // still add JS marker, but do not enable animations
    try{ document.documentElement.classList.add('js') }catch(e){}
    return
  }

  // Namespace
  const GX = window.GX = window.GX || {}

  // Configs
  GX.autoReveal = Boolean(window.GX_AUTO_REVEAL) || false
  GX.excludeSelectors = window.GX_EXCLUDE_SELECTORS || 'form, table, [data-no-reveal], [role="dialog"]'

  // Add enhanced class to html
  try { document.documentElement.classList.add('js') } catch(e) {}

  // Small helpers
  const raf = window.requestAnimationFrame.bind(window)
  function safeQueryAll(selector, root=document){ try { return Array.from(root.querySelectorAll(selector)) } catch(e){ return [] } }

  /* Reveal system (IntersectionObserver) */
  const revealObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry => {
      const el = entry.target
      if (entry.isIntersecting) {
        // apply reveal with optional stagger via data-gx-delay or data-stagger
        const delayAttr = el.getAttribute('data-gx-delay') || el.getAttribute('data-stagger') || el.dataset.gxDelay || 0
        const delay = Number(delayAttr) || 0
        if (delay) {
          el.__gx_reveal_timeout = setTimeout(()=> el.classList.add('is-revealed'), delay)
        } else {
          el.classList.add('is-revealed')
        }
        revealObserver.unobserve(el)
      }
    })
  }, { threshold: 0.12 })

  function bindReveal(root=document) {
    // If auto mode, reveal many elements unless excluded; otherwise only .gx-reveal or .gx-revealable
    const selector = GX.autoReveal ? `:not(${GX.excludeSelectors})` : '.gx-reveal, .gx-revealable'
    const nodes = safeQueryAll(selector, root)
    nodes.forEach(el => {
      // skip excluded nodes
      try { if (el.matches && el.matches(GX.excludeSelectors)) return } catch(e) {}
      // don't double-bind
      if (el.__gx_reveal_bound) return
      el.__gx_reveal_bound = true
      // initial hidden state left to CSS; observe
      revealObserver.observe(el)
    })
  }

  /* Marquee duplication: duplicate children to ensure seamless loop.
     Simple approach: duplicate track content once and enable animation class. */
  function initMarquees(root=document) {
    safeQueryAll('.gx-marquee', root).forEach(container => {
      if (container.__gx_marquee_bound) return
      container.__gx_marquee_bound = true
      const track = container.querySelector('.gx-marquee__track')
      if (!track) return
      // clone content once for seamless effect
      try {
        const clone = track.cloneNode(true)
        clone.classList.add('gx-marquee__track-clone')
        track.parentNode.appendChild(clone)
        // mark animate unless disabled
        container.classList.add('gx-marquee--animate')
      } catch (e) { console.warn('gx-marquee init failed', e) }
    })
  }

  /* Parallax tilt - subtle. Binds mousemove on element to apply rotateX/Y.
     Uses requestAnimationFrame to keep it cheap. */
  function bindParallax(root=document) {
    safeQueryAll('.gx-parallax', root).forEach(el => {
      if (el.__gx_parallax) return
      el.__gx_parallax = true
      let width, height, left, top
      function updateBounds() { const r = el.getBoundingClientRect(); width=r.width; height=r.height; left=r.left; top=r.top }
      updateBounds()
      window.addEventListener('resize', updateBounds)
      let rafId = null
      let tx = 0, ty = 0
      function onMove(e){
        const x = (e.clientX - left) / width - 0.5
        const y = (e.clientY - top) / height - 0.5
        const tiltX = (-y * (parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--gx-parallax-tilt'))||8)).toFixed(2)
        const tiltY = (x * (parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--gx-parallax-tilt'))||8)).toFixed(2)
        tx = tiltX; ty = tiltY
        if (!rafId) rafId = raf(()=>{ el.style.transform = `rotateX(${tx}deg) rotateY(${ty}deg)`; rafId=null })
      }
      el.addEventListener('pointermove', onMove)
      el.addEventListener('pointerleave', ()=>{ el.style.transform=''; })
    })
  }

  /* Magnetic hover: small translation toward cursor.
     Move element by up to strength px and snap back on leave. */
  function bindMagnetic(root=document) {
    safeQueryAll('.gx-magnetic', root).forEach(el => {
      if (el.__gx_magnetic) return
      el.__gx_magnetic = true
      const strength = Number(getComputedStyle(document.documentElement).getPropertyValue('--gx-magnetic-strength')) || 14
      let rafId = null
      function onMove(e){
        const r = el.getBoundingClientRect()
        const px = (e.clientX - (r.left + r.width/2)) / (r.width/2)
        const py = (e.clientY - (r.top + r.height/2)) / (r.height/2)
        const tx = Math.max(-1, Math.min(1, px)) * strength
        const ty = Math.max(-1, Math.min(1, py)) * (strength/2)
        if (!rafId) rafId = raf(()=>{ el.style.transform = `translate3d(${tx}px, ${ty}px, 0) scale(1.01)`; rafId=null })
      }
      function onLeave(){ el.style.transform=''; }
      el.addEventListener('pointermove', onMove)
      el.addEventListener('pointerleave', onLeave)
      el.addEventListener('pointerup', onLeave)
    })
  }

  /* Observe function for dynamic content */
  GX.observe = function(node){
    try {
      bindReveal(node)
      bindParallax(node)
      bindMagnetic(node)
      initMarquees(node)
    } catch(e){ console.warn('GX.observe failed', e) }
  }

  GX.enable = function(){
    try {
      bindReveal(document)
      bindParallax(document)
      bindMagnetic(document)
      initMarquees(document)
    } catch (e) { console.warn('GX.enable failed', e) }
  }

  // Auto-run when script loads
  try { GX.enable() } catch(e){ console.warn('gx init failed', e) }

  // Expose as global
  window.GX = GX

  // Provide simple cleanup on pagehide to avoid leaks
  window.addEventListener('pagehide', function(){ try{ revealObserver.disconnect() }catch(e){} })

})();
