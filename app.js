// Prevent double-loading which causes `Identifier '...' has already been declared` errors
if (window.__APP_LOADED) {
  console.warn('app.js: already loaded, skipping duplicate initialization')
} else {
  window.__APP_LOADED = true

  // Option A: lazy-load the gx-animations script in a non-blocking way.
  // Adjust path if your server mounts `public/` differently (e.g., '/js/gx-animations.js').
  try {
    if (!window.GX_DISABLE_ANIM) {
      (function(){
  var s = document.createElement('script')
  s.src = 'js/gx-animations.js'
        s.async = true
        s.defer = true
        document.head.appendChild(s)
      })()
    }
  } catch (e) { /* do not block app startup */ }

  // Robustness: fallback loader for environments where the injected script
  // may have been blocked or failed to execute (e.g., CSP, race conditions,
  // extensions). If after a short delay `window.GX` is still undefined,
  // attempt to load the script again once.
  try {
    if (!window.__GX_LOADER_ATTEMPTED) {
      window.__GX_LOADER_ATTEMPTED = true
      setTimeout(function(){
        try {
          var reduced = (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
          if (window.GX || window.GX_DISABLE_ANIM || reduced) return
          // Add a cache-busting param to avoid stale caches during dev
          var s2 = document.createElement('script')
          s2.src = 'js/gx-animations.js?v=1'
          s2.async = true
          s2.defer = true
          document.head.appendChild(s2)
          console.warn('app.js: gx-animations fallback loader appended')
        } catch (err) {
          console.warn('app.js: gx-animations fallback failed', err)
        }
      }, 700)
    }
  } catch (e) { /* ignore */ }

  // State Management
  const state = {
  currentFilter: "all",
  searchQuery: "",
  currentPage: getCurrentPage(),
}

// Data Initialization
// If data arrays are provided by `portal-data.js` (or another loader), reuse them.
// Use a safe guard (try/catch) to detect existing global bindings without throwing
let __tmp
try { __tmp = coursesData } catch (e) { __tmp = undefined }
const coursesData = __tmp || []
try { __tmp = assignmentsData } catch (e) { __tmp = undefined }
const assignmentsData = __tmp || []
try { __tmp = announcementsData } catch (e) { __tmp = undefined }
const announcementsData = __tmp || []
try { __tmp = scheduleData } catch (e) { __tmp = undefined }
const scheduleData = __tmp || []
try { __tmp = gradesData } catch (e) { __tmp = undefined }
const gradesData = __tmp || []
try { __tmp = messagesData } catch (e) { __tmp = undefined }
const messagesData = __tmp || []
__tmp = undefined

// NOTE: logout() logic has been centralized in auth.js as window.authLogout().
// The app relies on that exported function; if it's not present, handlers fall back
// to a direct navigation to the login page.

// Show a small transient overlay while signing out to improve UX.
function showSigningOutOverlay() {
  try {
    if (document.getElementById('signout-overlay')) return
    const el = document.createElement('div')
    el.id = 'signout-overlay'
    el.className = 'signout-overlay'
    el.textContent = 'Signing you out…'
    Object.assign(el.style, {
      position: 'fixed',
      top: '50%',
      left: '50%',
      transform: 'translate(-50%, -50%)',
      padding: '1rem 1.5rem',
      background: 'rgba(0,0,0,0.85)',
      color: '#fff',
      borderRadius: '8px',
      fontWeight: '600',
      zIndex: 10000,
      boxShadow: '0 6px 18px rgba(0,0,0,0.4)'
    })
    document.body.appendChild(el)
    // remove after a short time in case redirect doesn't occur instantly
    setTimeout(() => {
      try { el.remove() } catch (e) {}
    }, 2500)
  } catch (e) {
    // ignore overlay failures
  }
}

function getCurrentUser() {
  try {
    const raw = localStorage.getItem('batstate_current_user')
    if (!raw) return null
    const parsed = JSON.parse(raw)
    // ensure we return a clean object with expected fields
    return {
      id: parsed.id || parsed.studentId || null,
      name: parsed.name || parsed.fullName || parsed.username || null,
      studentId: parsed.studentId || parsed.id || null,
      program: parsed.program || null,
      email: parsed.email || null,
      avatar: parsed.avatar || null,
      // include the original object for other uses
      _raw: parsed
    }
  } catch (e) {
    return null
  }
}

function calculateGPA() {
  // Implement calculateGPA functionality here
  return 3.5 // Placeholder GPA
}

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  loadTheme()
  setupEventListeners()
  renderPageContent()
  updateNavigation()
  displayUserInfo()
  renderProfileMenu()
  // Render the realtime clock in the navbar (skip on login/register pages)
  try { if (typeof renderClock === 'function') renderClock() } catch (e) { /* ignore */ }
  // render any .user-avatar placeholders across pages
  if (typeof renderUserAvatars === 'function') try { renderUserAvatars() } catch (e) {}
  // Prevent a brief disappearance: mark gx-reveal items as revealed immediately so
  // when the animation library later adds `html.js` they won't be hidden by CSS.
  try {
    document.querySelectorAll('.gx-reveal').forEach(el => el.classList.add('is-revealed'))
  } catch (e) { /* ignore */ }
  // Initialize page transitions (fade-in on load, fade-out on nav)
  try { setupPageTransitions() } catch (e) { /* ignore if setup missing */ }
})

// Render avatars into any elements with class 'user-avatar'
function renderUserAvatars() {
  try {
    const user = getCurrentUser()
  if (!user) return
  document.querySelectorAll('.user-avatar').forEach(el => {
      if (user.avatar) {
        // set image element
        el.innerHTML = `<img src="${escapeHtml(user.avatar)}" class="user-avatar-img" alt="${escapeHtml(user.name||'avatar')}">`
      } else {
        // fallback to initials
        const initials = (user.name || '').split(' ').map(n=>n[0]).slice(0,2).join('').toUpperCase()
        el.textContent = initials
      }
    })
  } catch (e) { console.warn('renderUserAvatars failed', e) }
}

// Render a realtime clock into the navbar (skips login/register pages)
function renderClock() {
  try {
    const p = (location && location.pathname) ? location.pathname.toLowerCase() : ''
    if (p.includes('login.php') || p.includes('register.php')) return
    const navbarContent = document.querySelector('.navbar-content')
    if (!navbarContent) return
    if (document.getElementById('siteClock')) return

    // Ensure navbarContent can be used as positioning context
    if (getComputedStyle(navbarContent).position === 'static') {
      navbarContent.style.position = 'relative'
    }

    const el = document.createElement('div')
    el.id = 'siteClock'
    el.className = 'site-clock'
    el.innerHTML = `
      <div class="clock-wrapper">
        <div class="time-boxes">
          <div class="box hour">--</div>
          <div class="sep">:</div>
          <div class="box minute">--</div>
          <div class="sep">:</div>
          <div class="box second">--</div>
        </div>
        <div class="am-pm">AM</div>
        <div class="site-clock-date">---</div>
      </div>`

    // Prefer to place the clock under the logout button (right side) as requested.
    const navbarMenu = navbarContent.querySelector('.navbar-menu')
    const logoutBtn = navbarMenu && (navbarMenu.querySelector('#logoutBtn') || navbarMenu.querySelector('.btn-logout'))

    // Prefer to place a small clock in the top-right outside the hero (append to .navbar)
    const navbarEl = document.querySelector('.navbar')
    if (navbarEl) {
      el.classList.add('top-clock')
      // Insert the clock immediately after the .navbar element so it becomes
      // part of the normal document flow (it will scroll away with the page)
      // instead of being visually anchored to the sticky navbar.
      try {
        if (navbarEl.parentNode) navbarEl.parentNode.insertBefore(el, navbarEl.nextSibling)
        else navbarEl.appendChild(el)
      } catch (e) {
        // fallback to append if insertion fails for any reason
        navbarEl.appendChild(el)
      }
    } else {
      // fallback: hero or main
      const hero = document.querySelector('.hero-section')
      const main = document.querySelector('.main-content')
      let target = hero || main || navbarContent
      if (target === hero) el.classList.add('hero-clock')
      else if (target === main) el.classList.add('main-clock')
      target.appendChild(el)
    }

    function pad(n){ return n.toString().padStart(2,'0') }
    function to12(h) { const m = h % 12; return m === 0 ? 12 : m }

    let lastMinute = null

    function update() {
      const d = new Date()
      const hours24 = d.getHours()
      const hours12 = to12(hours24)
      const mm = pad(d.getMinutes())
      const ss = pad(d.getSeconds())
      const ampm = hours24 >= 12 ? 'PM' : 'AM'
  const hStr = pad(hours12)
  const mStr = mm
  const sStr = ss
  const date = d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
  const hEl = el.querySelector('.box.hour')
  const mEl = el.querySelector('.box.minute')
  const sEl = el.querySelector('.box.second')
  const apEl = el.querySelector('.am-pm')
  const dEl = el.querySelector('.site-clock-date')
  if (hEl) hEl.textContent = hStr
  if (mEl) mEl.textContent = mStr
  if (sEl) sEl.textContent = sStr
  if (apEl) apEl.textContent = ampm
  if (dEl) dEl.textContent = date

      const curMinute = d.getMinutes()
      if (lastMinute === null) lastMinute = curMinute
      else if (curMinute !== lastMinute) {
        // Trigger a visual animation each minute change
        try {
          el.classList.add('minute-tick')
          setTimeout(() => { try { el.classList.remove('minute-tick') } catch (e) {} }, 1200)
        } catch (e) {}
        lastMinute = curMinute
      }
    }

    update()
    setInterval(update, 1000)
  } catch (e) { console.warn('renderClock failed', e) }
}

// Attach logout handlers to any logout controls (id, class, or data attribute).
function attachLogoutHandlers() {
  const selector = '#logoutBtn, .btn-logout, [data-logout]'
  const els = Array.from(document.querySelectorAll(selector))

  function handleLogoutEvent(e) {
    // Support activation via click or keyboard (Enter/Space) on focusable elements
    if (e.type === 'keydown') {
      if (!(e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar')) return
      e.preventDefault()
    }

    // confirm and logout
    try {
      if (confirm("Do you really want to log out?")) {
        // prevent default navigation if element is an anchor/button
        try { e.preventDefault() } catch (err) {}
        // show overlay, then call central logout
        try { showSigningOutOverlay() } catch (err) {}
        console.log('logout: user confirmed')
        try {
          if (typeof window.authLogout === 'function') {
            window.authLogout()
          } else {
            // fallback to simple navigation
            window.location.replace('login.php')
          }
        } catch (err) {
          console.error('logout: authLogout failed', err)
          try { window.location.replace('login.php') } catch (e) { window.location.href = 'login.php' }
        }
        // fallback: if authLogout didn't redirect, ensure we go to login after 200ms
        setTimeout(() => {
          try {
            if (location.pathname.indexOf('login.php') === -1) {
              console.warn('logout: fallback redirect to login')
              window.location.replace('login.php')
            }
          } catch (err) { /* ignore */ }
        }, 200)
      }
    } catch (err) {
      // In case confirm is blocked, still try to logout
      try {
        if (typeof window.authLogout === 'function') window.authLogout()
        else window.location.replace('login.php')
      } catch (e) { console.error('logout failed', e); try { window.location.replace('login.php') } catch (_) { window.location.href = 'login.php' } }
    }
  }

  // Attach to each element if not already attached
  els.forEach((el) => {
    // use a flag to avoid double-binding
    if (el.__logoutHandlerAttached) return
    el.addEventListener('click', handleLogoutEvent)
    el.addEventListener('keydown', handleLogoutEvent)
    el.__logoutHandlerAttached = true
    // ensure element is keyboard-focusable
    if (!el.hasAttribute('tabindex')) {
      el.setAttribute('tabindex', '0')
    }
    // set role if it's not a native button or link
    const tag = el.tagName.toLowerCase()
    if (tag !== 'button' && tag !== 'a' && !el.getAttribute('role')) {
      el.setAttribute('role', 'button')
    }
  })
}

document.addEventListener('DOMContentLoaded', () => {
  attachLogoutHandlers()
})

function displayUserInfo() {
  const user = getCurrentUser()
  const userInfoEl = document.getElementById("userInfo")
  if (userInfoEl) {
    if (user && user.name) {
      userInfoEl.textContent = `Welcome, ${user.name} (${user.studentId}) - ${user.program}`
    } else {
      userInfoEl.textContent = `Not signed in`
    }
  }
}

// Render a small profile menu in the navbar showing name, email, profile link and logout
function renderProfileMenu() {
  try {
    const navbar = document.querySelector('.navbar-content') || document.body
    const menu = navbar && navbar.querySelector('.navbar-menu')
    if (!menu) return

    // avoid duplicate insertion
    if (document.getElementById('profileContainer')) return

    const user = getCurrentUser()

    const container = document.createElement('div')
    container.id = 'profileContainer'
    container.className = 'profile-container'

    if (!user) {
      // show simple not-signed-in state with link to login
      container.innerHTML = `<div class="profile-unsigned">Not signed in • <a href="login.php">Sign in</a></div>`
      // insert before logout button if present, else append
      const ref = menu.querySelector('.btn-logout') || menu.querySelector('.theme-toggle')
      if (ref) menu.insertBefore(container, ref.nextSibling)
      else menu.appendChild(container)
      return
    }

    // build profile button + dropdown
    const initials = (user.name || '').split(' ').map(n=>n[0]).slice(0,2).join('').toUpperCase()
    const avatarHtml = user.avatar ? `<img src="${escapeHtml(user.avatar)}" alt="${escapeHtml(user.name||'avatar')}" />` : initials
    container.innerHTML = `
      <button id="profileButton" class="profile-button" role="button" aria-haspopup="true" aria-expanded="false" tabindex="0" aria-controls="profileDropdown">
        <span class="profile-avatar">${avatarHtml}</span>
        <span class="profile-name">${escapeHtml(user.name || 'Student')}</span>
      </button>
      <div class="profile-dropdown" id="profileDropdown" role="menu" aria-hidden="true" hidden>
  <div class="profile-info"><strong>${escapeHtml(user.name || '')}</strong><div class="profile-email">${escapeHtml(user.email || '')}</div></div>
        <div class="profile-actions" role="none">
          <a href="profile.php" class="profile-link" role="menuitem" tabindex="0">Profile</a>
          <button id="profileLogoutBtn" class="profile-logout" role="menuitem" tabindex="0">Logout</button>
        </div>
      </div>
    `

    // insert into menu near theme toggle/logout
    const ref = menu.querySelector('.theme-toggle') || menu.querySelector('.btn-logout')
    if (ref) menu.insertBefore(container, ref)
    else menu.appendChild(container)

    const btn = container.querySelector('#profileButton')
    const dd = container.querySelector('#profileDropdown')
    const logoutBtn = container.querySelector('#profileLogoutBtn')

    // toggle dropdown (click)
    function closeDropdown() {
      if (!dd) return
      dd.hidden = true
      dd.setAttribute('aria-hidden', 'true')
      if (btn) btn.setAttribute('aria-expanded', 'false')
    }

    function openDropdown() {
      if (!dd) return
      dd.hidden = false
      dd.setAttribute('aria-hidden', 'false')
      if (btn) btn.setAttribute('aria-expanded', 'true')
      // focus first focusable item
      const first = dd.querySelector('[role="menuitem"]')
      if (first) first.focus()
    }

    btn.addEventListener('click', function(e) {
      const expanded = btn.getAttribute('aria-expanded') === 'true'
      if (expanded) closeDropdown(); else openDropdown();
    })

    // keyboard: Enter/Space opens, ArrowDown opens and focuses first
    btn.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault()
        const expanded = btn.getAttribute('aria-expanded') === 'true'
        if (expanded) closeDropdown(); else openDropdown()
      } else if (e.key === 'ArrowDown') {
        e.preventDefault()
        openDropdown()
      }
    })

    // outside click closes
    document.addEventListener('click', function(e) {
      if (!container.contains(e.target)) {
        closeDropdown()
      }
    })

    // close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (dd && !dd.hidden) {
          closeDropdown()
          if (btn) btn.focus()
        }
      }
    })

    // keyboard navigation within dropdown (ArrowUp/ArrowDown)
    if (dd) {
      dd.addEventListener('keydown', function(e) {
        const items = Array.from(dd.querySelectorAll('[role="menuitem"]'))
        if (!items.length) return
        const idx = items.indexOf(document.activeElement)
        if (e.key === 'ArrowDown') {
          e.preventDefault()
          const next = items[(idx + 1) % items.length]
          next.focus()
        } else if (e.key === 'ArrowUp') {
          e.preventDefault()
          const prev = items[(idx - 1 + items.length) % items.length]
          prev.focus()
        } else if (e.key === 'Enter') {
          // activate focused item
          if (document.activeElement) document.activeElement.click()
        }
      })
    }

    // logout action
    if (logoutBtn) {
      logoutBtn.addEventListener('click', function() {
        try { showSigningOutOverlay() } catch (e) {}
        try {
          if (typeof window.authLogout === 'function') window.authLogout()
          else window.location.replace('login.php')
        } catch (e) { window.location.replace('login.php') }
      })
    }

  } catch (e) {
    // fail silently
    console.warn('renderProfileMenu failed', e)
  }
}

// Theme Management
function loadTheme() {
  const saved = localStorage.getItem("theme") || "dark"
  document.documentElement.setAttribute("data-theme", saved)
  updateThemeIcon(saved)
}

function toggleTheme() {
  const current = document.documentElement.getAttribute("data-theme")
  const newTheme = current === "dark" ? "light" : "dark"
  document.documentElement.setAttribute("data-theme", newTheme)
  localStorage.setItem("theme", newTheme)
  updateThemeIcon(newTheme)
}

function updateThemeIcon(theme) {
  const icons = document.querySelectorAll(".theme-icon")
  icons.forEach((icon) => {
    icon.textContent = theme === "dark" ? "☀️" : "🌙"
  })
}

// Navigation
function getCurrentPage() {
  const pathname = window.location.pathname
  if (pathname.includes("courses")) return "courses"
  if (pathname.includes("assignments")) return "assignments"
  if (pathname.includes("grades")) return "grades"
  if (pathname.includes("announcements")) return "announcements"
  if (pathname.includes("schedule")) return "schedule"
  if (pathname.includes("messages")) return "messages"
  return "dashboard"
}

function updateNavigation() {
  document.querySelectorAll(".nav-link").forEach((link) => {
    link.classList.remove("active")
  })
  document.querySelectorAll(".nav-link").forEach((link) => {
    if (state.currentPage === "dashboard" && link.href.includes("index.php")) {
      link.classList.add("active")
    } else if (state.currentPage !== "dashboard" && link.href.includes(state.currentPage)) {
      link.classList.add("active")
    }
  })
}

// Render Page Content
function renderPageContent() {
  switch (state.currentPage) {
    case "dashboard":
      renderDashboard()
      break
    case "courses":
      renderCourses()
      break
    case "assignments":
      renderAssignments()
      break
    case "grades":
      renderGrades()
      break
    case "announcements":
      renderAnnouncements()
      break
    case "schedule":
      renderSchedule()
      break
    case "messages":
      renderMessages()
      break
  }
}

// Dashboard Page
function renderDashboard() {
  updateGreeting()
  updateDashboardStats()
  renderDashboardAnnouncements()
  renderTodaySchedule()
  renderUpcomingAssignments()
}

function updateGreeting() {
  const hour = new Date().getHours()
  const greetings = ["Good night", "Good morning", "Good afternoon", "Good evening"]
  const index = hour < 6 ? 0 : hour < 12 ? 1 : hour < 18 ? 2 : 3
  const greeting = document.getElementById("greeting")
  const user = getCurrentUser()
  if (greeting && user) {
    const name = escapeHtml(user.name || 'Student')
    if (user.avatar) {
      greeting.innerHTML = `<span style="display:inline-flex;align-items:center;gap:0.6rem"><img class="greeting-avatar" src="${escapeHtml(user.avatar)}" alt="${name}"><span>${greetings[index]}, ${name}</span></span>`
    } else {
      greeting.textContent = `${greetings[index]}, ${user.name || "Student"}`
    }
  }
}

function updateDashboardStats() {
  const coursesCount = coursesData.length
  const dueSoon = assignmentsData.filter((a) => {
    const dueDate = new Date(a.dueDate)
    const today = new Date()
    const diff = dueDate - today
    return diff > 0 && diff < 7 * 24 * 60 * 60 * 1000
  }).length
  const currentGPA = calculateGPA()

  const coursesCountEl = document.getElementById("coursesCount")
  const dueSoonEl = document.getElementById("dueSoon")
  const gpaEl = document.getElementById("gpaDisplay")

  if (coursesCountEl) coursesCountEl.textContent = coursesCount
  if (dueSoonEl) dueSoonEl.textContent = dueSoon
  if (gpaEl) gpaEl.textContent = currentGPA
}

function renderDashboardAnnouncements() {
  const list = document.getElementById("announcementsList")
  if (!list) return

  const recent = announcementsData.slice(0, 3)
  if (recent.length === 0) {
    list.innerHTML = `<div class="empty-state"><div class="empty-icon">📢</div><h4>No announcements</h4></div>`
    return
  }

  list.innerHTML = recent
    .map(
      (ann) => `
    <div class="announcement-item">
      <div class="announcement-header">
        <div class="announcement-title">${escapeHtml(ann.title)}</div>
        <div class="announcement-date">${formatDate(ann.date)}</div>
      </div>
      <div class="announcement-content">${escapeHtml(ann.content)}</div>
    </div>
  `,
    )
    .join("")
}

function renderTodaySchedule() {
  const list = document.getElementById("todaySchedule")
  if (!list) return

  const today = new Date().toLocaleString("en-US", { weekday: "long" })
  const todayClasses = scheduleData.filter((s) => s.day === today)

  if (todayClasses.length === 0) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🕐</div>
        <h4>No classes today</h4>
      </div>
    `
    return
  }

  list.innerHTML = todayClasses
    .map(
      (s) => `
    <div class="schedule-item">
      <div class="time-slot">${s.time}</div>
      <div class="schedule-details">
        <div class="class-name">${escapeHtml(s.course)} - ${escapeHtml(s.room)}</div>
        <div class="class-room">${escapeHtml(s.instructor)}</div>
      </div>
    </div>
  `,
    )
    .join("")
}

function renderUpcomingAssignments() {
  const list = document.getElementById("recentAssignments")
  if (!list) return

  const upcoming = assignmentsData
    .filter((a) => a.status === "pending")
    .sort((a, b) => new Date(a.dueDate) - new Date(b.dueDate))
    .slice(0, 3)

  if (upcoming.length === 0) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">✍️</div>
        <h4>No pending assignments</h4>
      </div>
    `
    return
  }

  list.innerHTML = upcoming
    .map(
      (a) => `
    <div class="assignment-item">
      <div class="assignment-content">
        <div class="assignment-title">${escapeHtml(a.title)}</div>
        <div class="assignment-meta">
          <span class="course-code">${a.course}</span>
          <span class="assignment-due">📅 ${formatDate(a.dueDate)}</span>
        </div>
      </div>
      <div class="assignment-actions">
        <span style="font-size: 0.9rem; font-weight: 600;">${a.points} pts</span>
      </div>
    </div>
  `,
    )
    .join("")
}

// Courses Page
function renderCourses() {
  const list = document.getElementById("coursesList")
  if (!list) return

  if (coursesData.length === 0) {
    list.innerHTML = `<div class="empty-state"><div class="empty-icon">📚</div><h4>No courses</h4></div>`
    return
  }

  list.innerHTML = coursesData
    .map(
      (course) => `
    <div class="course-card-grid">
      <div class="course-header">
        <div class="course-code-large">${course.code}</div>
        <div class="course-name">${escapeHtml(course.name)}</div>
        <div class="course-professor">Prof: ${escapeHtml(course.professor)}</div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem;">${escapeHtml(course.description)}</div>
      </div>
      <div class="course-footer">
        <div class="course-progress">${course.credits} Credits • Sec. ${course.section}</div>
        <div class="course-status">${course.status}</div>
      </div>
    </div>
  `,
    )
    .join("")
}

// Assignments Page
function renderAssignments() {
  const list = document.getElementById("assignmentsList")
  if (!list) return

  let filtered = assignmentsData

  if (state.currentFilter !== "all") {
    filtered = filtered.filter((a) => a.status === state.currentFilter)
  }

  if (state.searchQuery) {
    const query = state.searchQuery.toLowerCase()
    filtered = filtered.filter((a) => a.title.toLowerCase().includes(query) || a.course.toLowerCase().includes(query))
  }

  filtered.sort((a, b) => new Date(a.dueDate) - new Date(b.dueDate))

  if (filtered.length === 0) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">✍️</div>
        <h4>No assignments found</h4>
      </div>
    `
    return
  }

  list.innerHTML = filtered
    .map(
      (a) => `
    <div class="assignment-item">
      <div class="assignment-content">
        <div class="assignment-title">${escapeHtml(a.title)}</div>
        <div class="assignment-meta">
          <span class="course-code">${a.course}</span>
          <span class="assignment-due">📅 ${formatDate(a.dueDate)}</span>
          <span class="assignment-status">${a.status}</span>
        </div>
        <div style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem;">
          ${escapeHtml(a.description)}
        </div>
        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.5rem; font-style: italic;">
          Rubric: ${escapeHtml(a.rubric)}
        </div>
      </div>
      <div class="assignment-actions">
        <span style="font-size: 0.9rem; font-weight: 600;">${a.points} pts</span>
      </div>
    </div>
  `,
    )
    .join("")
}

// Grades Page
function renderGrades() {
  const list = document.getElementById("gradesList")
  const gpaEl = document.getElementById("currentGPA")

  if (gpaEl) {
    gpaEl.textContent = calculateGPA()
  }

  if (!list) return

  if (gradesData.length === 0) {
    list.innerHTML = `<div class="empty-state"><div class="empty-icon">📊</div><h4>No grades</h4></div>`
    return
  }

  list.innerHTML = gradesData
    .map(
      (g) => `
    <div class="grade-item">
      <div class="grade-course">
        <div style="font-weight: 700;">${g.courseCode}</div>
        <div style="font-size: 0.9rem; color: var(--text-secondary);">${escapeHtml(g.courseName)}</div>
        <div style="font-size: 0.8rem; color: var(--text-secondary);">Prof: ${escapeHtml(g.professor)}</div>
      </div>
      <div style="display: flex; gap: 1rem; justify-content: center;">
        <div style="text-align: center;">
          <div style="font-size: 0.85rem; color: var(--text-secondary);">Midterm</div>
          <div style="font-weight: 600;">${g.midterm}%</div>
        </div>
        <div style="text-align: center;">
          <div style="font-size: 0.85rem; color: var(--text-secondary);">Finals</div>
          <div style="font-weight: 600;">${g.finals ? g.finals + "%" : "TBA"}</div>
        </div>
        <div style="text-align: center;">
          <div style="font-size: 0.85rem; color: var(--text-secondary);">Assignments</div>
          <div style="font-weight: 600;">${g.assignments}%</div>
        </div>
        <div style="text-align: center;">
          <div style="font-size: 0.85rem; color: var(--text-secondary);">Participation</div>
          <div style="font-weight: 600;">${g.participation}%</div>
        </div>
      </div>
      <div class="grade-score">${g.average}%</div>
    </div>
  `,
    )
    .join("")
}

// Announcements Page
function renderAnnouncements() {
  const list = document.getElementById("announcementsList")
  if (!list) return

  let filtered = announcementsData

  if (state.currentFilter !== "all") {
    filtered = filtered.filter((a) => a.type === state.currentFilter)
  }

  if (state.searchQuery) {
    const query = state.searchQuery.toLowerCase()
    filtered = filtered.filter((a) => a.title.toLowerCase().includes(query) || a.content.toLowerCase().includes(query))
  }

  filtered.sort((a, b) => new Date(b.date) - new Date(a.date))

  if (filtered.length === 0) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">📢</div>
        <h4>No announcements found</h4>
      </div>
    `
    return
  }

  list.innerHTML = filtered
    .map(
      (a) => `
    <div class="announcement-item">
      <div class="announcement-header">
        <div>
          <div class="announcement-title">${escapeHtml(a.title)}</div>
          <div style="font-size: 0.85rem; color: var(--primary-red); text-transform: uppercase; font-weight: 600; margin-top: 0.25rem;">
            ${a.type === "university" ? "🏫 University" : "📚 Course"} • ${a.department}
          </div>
        </div>
        <div class="announcement-date">${formatDate(a.date)}</div>
      </div>
      <div class="announcement-content">${escapeHtml(a.content)}</div>
    </div>
  `,
    )
    .join("")
}

// Schedule Page
function renderSchedule() {
  const list = document.getElementById("scheduleList")
  if (!list) return

  if (scheduleData.length === 0) {
    list.innerHTML = `<div class="empty-state"><div class="empty-icon">📅</div><h4>No classes scheduled</h4></div>`
    return
  }

  list.innerHTML = scheduleData
    .sort((a, b) => {
      const dayOrder = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]
      return dayOrder.indexOf(a.day) - dayOrder.indexOf(b.day)
    })
    .map(
      (s) => `
    <div class="schedule-item">
      <div class="time-slot">${s.time}</div>
      <div class="schedule-details">
        <div class="class-name">${escapeHtml(s.course)} - ${escapeHtml(s.room)}</div>
        <div class="class-room">${s.day} | ${escapeHtml(s.instructor)}</div>
      </div>
    </div>
  `,
    )
    .join("")
}

// Messages Page
function renderMessages() {
  const list = document.getElementById("messagesList")
  if (!list) return

  let filtered = messagesData

  if (state.searchQuery) {
    const query = state.searchQuery.toLowerCase()
    filtered = filtered.filter(
      (m) =>
        m.sender.toLowerCase().includes(query) ||
        m.subject.toLowerCase().includes(query) ||
        m.preview.toLowerCase().includes(query),
    )
  }

  filtered.sort((a, b) => new Date(b.date) - new Date(a.date))

  if (filtered.length === 0) {
    list.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">💬</div>
        <h4>No messages found</h4>
      </div>
    `
    return
  }

  list.innerHTML = filtered
    .map(
      (m) => `
    <div class="message-item">
      <div class="message-header">
        <div class="message-sender">${escapeHtml(m.sender)} ${m.course !== "General" ? `(${m.course})` : ""}</div>
        <div class="message-time">${formatDate(m.date)}</div>
      </div>
      <div style="font-weight: 600; margin-bottom: 0.3rem;">${escapeHtml(m.subject)}</div>
      <div class="message-preview">${escapeHtml(m.preview)}</div>
    </div>
  `,
    )
    .join("")
}

// Event Listeners
function setupEventListeners() {
  // Theme
  document.querySelectorAll(".theme-toggle").forEach((btn) => {
    btn.addEventListener("click", toggleTheme)
  })

  // Filters
  document.querySelectorAll(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      document.querySelectorAll(".filter-btn").forEach((b) => b.classList.remove("active"))
      e.target.classList.add("active")
      state.currentFilter = e.target.dataset.filter
      renderPageContent()
    })
  })

  // Search
  document.querySelectorAll(".search-input").forEach((input) => {
    input.addEventListener("input", (e) => {
      state.searchQuery = e.target.value
      renderPageContent()
    })
  })
}

// Utility Functions
function escapeHtml(text) {
  const div = document.createElement("div")
  div.textContent = text
  return div.innerHTML
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  })
}

/* Page transition handling
   - Creates an overlay element used to animate page load (fade-in) and navigation (fade-out).
   - Intercepts internal link clicks and plays an exit animation before navigating. */
function setupPageTransitions() {
  try {
    let overlay = document.getElementById('pageTransitionOverlay')
    // If overlay isn't in DOM yet (older pages), create it. If it's present (we injected
    // in HTML), just reuse it and attach listeners so the enter animation can be handled.
    if (!overlay) {
      overlay = document.createElement('div')
      overlay.id = 'pageTransitionOverlay'
      overlay.className = 'page-transition-overlay pt-enter'
      document.body.appendChild(overlay)
    } else {
      // Ensure it has the classes needed to animate in if page loaded with it present
      if (!overlay.classList.contains('pt-enter') && !overlay.classList.contains('hidden')) {
        overlay.classList.add('pt-enter')
      }
    }

    // After the enter animation ends, hide the overlay so content is interactive
    const handleEnterEnd = function onEnd(e) {
      try {
        if (e.animationName && e.animationName.toLowerCase().includes('page-fade-in')) {
          overlay.classList.remove('pt-enter')
          overlay.classList.add('hidden')
          overlay.removeEventListener('animationend', handleEnterEnd)
        }
      } catch (err) { /* ignore */ }
    }
    overlay.addEventListener('animationend', handleEnterEnd)

    // Intercept internal navigation clicks
    document.addEventListener('click', function (ev) {
      try {
        const a = ev.target.closest && ev.target.closest('a')
        if (!a) return
        // ignore if has target=_blank or external link with protocol
        if (a.target === '_blank') return
        const href = a.getAttribute('href')
        if (!href) return
        // ignore anchor-only links
        if (href.startsWith('#')) return
        // absolute full URLs: only handle same-origin
        const url = new URL(href, window.location.href)
        if (url.origin !== window.location.origin) return

        // only handle HTML pages in same origin
        // allow default for links that are the current page (let browser handle)
        const isSamePage = url.pathname === location.pathname && (!url.hash || url.hash === location.hash)
        if (isSamePage) return

        // prevent default navigation and run exit animation
        ev.preventDefault()
  overlay.classList.remove('hidden')
  overlay.classList.remove('pt-enter')
  overlay.classList.add('blocking')
  // force reflow so the animation class is applied cleanly
  // eslint-disable-next-line no-unused-expressions
  overlay.offsetHeight
  overlay.classList.add('pt-exit')

        // wait for animation to end then navigate
        const done = () => {
          try { window.location.href = url.href } catch (e) { window.location.assign(url.href) }
        }

        const listener = function (e) {
          try {
            if (e.animationName && e.animationName.toLowerCase().includes('page-fade-out')) {
              overlay.removeEventListener('animationend', listener)
              done()
            }
          } catch (err) { done() }
        }
        overlay.addEventListener('animationend', listener)

        // fallback: if animationend doesn't fire, navigate after 900ms (safe > 700ms)
        setTimeout(() => {
          try { done() } catch (e) {}
        }, 900)
      } catch (e) {
        // ignore and let default behavior happen
      }
    }, { capture: true })
  } catch (e) {
    console.warn('setupPageTransitions failed', e)
  }
}

// close the initialization guard from the top of the file
}
