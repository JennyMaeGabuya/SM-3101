// Check authentication on page load
function checkAuthentication() {
  const currentPage = window.location.pathname.split("/").pop() || "index.php"
  const params = new URLSearchParams(window.location.search)
  const skipRedirect = params.has('logged_out') || params.has('skip_auth')

  // Read user after handling any logged_out flag so we don't pick up a stale value
  // that was present before we cleared it.
  let user = null
  try {
    if (params.has('logged_out')) {
      // Clear any stored user immediately to prevent redirect loops
      if (window.localStorage) localStorage.removeItem('batstate_current_user')
      if (window.sessionStorage) sessionStorage.removeItem('batstate_current_user')
      user = null
    } else {
      user = localStorage.getItem('batstate_current_user')
    }
  } catch (e) {
    // ignore and treat as not authenticated
    user = null
  }

  if (!user && !["login.php", "register.php"].includes(currentPage)) {
    window.location.href = "login.php"
  }

  if (user && ["login.php", "register.php"].includes(currentPage) && !skipRedirect) {
    window.location.href = "index.php"
  }
}

// Logout function
function logout() {
  // Clear stored user information (best-effort)
  try {
    if (window.localStorage) localStorage.removeItem('batstate_current_user')
  } catch (e) {
    console.warn('auth.logout: could not remove localStorage key', e)
  }
  try {
    if (window.sessionStorage) sessionStorage.removeItem('batstate_current_user')
  } catch (e) {
    console.warn('auth.logout: could not remove sessionStorage key', e)
  }

  // Attempt to clear cookies (best-effort)
  try {
    const cookies = document.cookie.split(';').map(c => c.trim()).filter(Boolean)
    cookies.forEach((cookie) => {
      const eqPos = cookie.indexOf('=')
      const name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${window.location.pathname}`
    })
  } catch (e) {
    console.warn('auth.logout: cookie clearing failed', e)
  }

  // Dispatch an event so other scripts can respond if needed
  try {
    window.dispatchEvent(new CustomEvent('auth:logout'))
  } catch (e) {
    // ignore
  }

  // Use replace to avoid keeping protected page in history
  try {
    if (window.sessionStorage) sessionStorage.setItem('batstate_just_logged_out', '1')
  } catch (e) {
    // ignore
  }
  const target = 'login.php?logged_out=1'
  try {
    window.location.replace(target)
  } catch (e) {
    // fallback to href
    window.location.href = target
  }
  // Extra assurance: force a navigation after a short delay in case replace() is ignored
    setTimeout(() => {
      try {
        if (window.location.pathname.indexOf('login.php') === -1 || window.location.search.indexOf('logged_out') === -1) {
          window.location.href = target
        }
      } catch (e) {
        // ignore
      }
    }, 250)
}

// Expose a stable auth logout function that other scripts can call without
// worrying about function name collisions.
try {
  window.authLogout = logout
} catch (e) {
  // ignore in non-browser environments
}

// Get current user
function getCurrentUser() {
  return JSON.parse(localStorage.getItem("batstate_current_user") || "{}")
}

// Check on page load
window.addEventListener("load", checkAuthentication)
