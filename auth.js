
function checkAuthentication() {
  const currentPage = window.location.pathname.split("/").pop() || "index.php"
  const params = new URLSearchParams(window.location.search)
  const skipRedirect = params.has('logged_out') || params.has('skip_auth')

  let user = null
  try {
    if (params.has('logged_out')) {
      if (window.localStorage) localStorage.removeItem('batstate_current_user')
      if (window.sessionStorage) sessionStorage.removeItem('batstate_current_user')
      user = null
    } else {
      user = localStorage.getItem('batstate_current_user')
    }
  } catch (e) {
    user = null
  }

  if (!user && !["login.php", "register.php"].includes(currentPage)) {
    window.location.href = "login.php"
  }

  if (user && ["login.php", "register.php"].includes(currentPage) && !skipRedirect) {
    window.location.href = "index.php"
  }
}

function logout() {
  // Clear storage robustly. We attempt removal, then write an empty value then remove
  try {
    if (window.localStorage) {
      localStorage.removeItem('batstate_current_user')
      try { localStorage.setItem('batstate_current_user', '') } catch (e) {}
      localStorage.removeItem('batstate_current_user')
    }
  } catch (e) {
    console.warn('auth.logout: could not remove localStorage key', e)
  }
  try {
    if (window.sessionStorage) {
      sessionStorage.removeItem('batstate_current_user')
      try { sessionStorage.setItem('batstate_current_user', '') } catch (e) {}
      sessionStorage.removeItem('batstate_current_user')
    }
  } catch (e) {
    console.warn('auth.logout: could not remove sessionStorage key', e)
  }

  try {
    const cookies = document.cookie.split(';').map(c => c.trim()).filter(Boolean)
    cookies.forEach((cookie) => {
      const eqPos = cookie.indexOf('=')
      const name = eqPos > -1 ? cookie.slice(0, eqPos) : cookie
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
      document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${window.location.pathname}`
    })
  } catch (e) {
    console.warn('auth.logout: cookie clearing failed', e)
  }

  try {
    window.dispatchEvent(new CustomEvent('auth:logout'))
  } catch (e) {
    // ignore
  }

  try {
    if (window.sessionStorage) sessionStorage.setItem('batstate_just_logged_out', '1')
  } catch (e) {
    // ignore
  }

  const target = 'login.php?logged_out=1'
  // small delay to ensure storage operations complete before navigation
  try {
    setTimeout(() => {
      try {
        // final attempt to clear storage before redirect
        try { if (window.localStorage) localStorage.removeItem('batstate_current_user') } catch (e) {}
        try { if (window.sessionStorage) sessionStorage.removeItem('batstate_current_user') } catch (e) {}
        window.location.replace(target)
      } catch (err) {
        try { window.location.href = target } catch (e) {}
      }
    }, 60)
  } catch (e) {
    try { window.location.replace(target) } catch (err) { try { window.location.href = target } catch (e) {} }
  }

  // Fallback: if not on login after a short while, force navigation
  setTimeout(() => {
    try {
      if (window.location.pathname.indexOf('login.php') === -1 || window.location.search.indexOf('logged_out') === -1) {
        window.location.replace(target)
      }
    } catch (e) {
      // ignore
    }
  }, 400)
}

// Expose the logout function early so other scripts (app.js) can call it
try {
  window.authLogout = logout
} catch (e) {
  // ignore
}

function getCurrentUser() {
  return JSON.parse(localStorage.getItem("batstate_current_user") || "{}")
}

try {
  window.getCurrentUser = getCurrentUser
} catch (e) {
  // ignore
}

window.addEventListener("load", checkAuthentication)
