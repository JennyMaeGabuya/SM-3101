<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profile - BatStateU</title>
  <link rel="stylesheet" href="styles.css?v=2">

</head>
<body>
  
    </div>
     <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
        <div class="gx-orb gx-orb--small" style="right:20px; bottom:10px; background:rgba(196,30,58,0.06);"></div>
        <div class="gx-orb gx-orb--soft" style="left:10px; top:20px; background:rgba(184,134,11,0.05);"></div>
  </section>
  <div class="app-container">
    <nav class="navbar">
      <div class="navbar-content">
        <div class="navbar-brand">
          <h1 class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU" class="brand-logo-img">  Student Portal</h1>
        </div>
        <div class="navbar-menu">
         <a href="index.php" class="nav-link active">Dashboard</a>
          <a href="courses.php" class="nav-link">Quizzes</a>
          <a href="assignments.php" class="nav-link">Assignments</a>
          <a href="grades.php" class="nav-link">Grades</a>
          <a href="announcements.php" class="nav-link">Performance Tasks</a>
          <a href="schedule.php" class="nav-link">Activities</a>
          <a href="messages.php" class="nav-link">Learning Materials</a>
          <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme"> <span class="theme-icon">🌙</span></button>
          <button type="button" class="btn-logout" id="logoutBtn" data-logout>🚪 Logout</button>
        </div>
      </div>
    </nav>

    <main class="main-content">
      <section style="max-width:820px;margin:2rem auto;padding:1.5rem;background:var(--bg-secondary);border-radius:10px;">
          <h2>Profile</h2>
          <p id="profileMessage" class="auth-message"></p>

          <div id="profileHeader" style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
            <div class="avatar-preview" id="headerAvatarWrap" style="width:56px;height:56px;flex-shrink:0;min-width:56px;">
              <img id="headerAvatar" src="" alt="avatar" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;" />
            </div>
            <div style="min-width:0;">
              <div id="headerName" style="font-weight:700;font-size:1.1rem;color:var(--text-primary);overflow:hidden;white-space:nowrap;text-overflow:ellipsis"></div>
              <div id="headerStudentId" style="color:var(--text-secondary);font-size:0.9rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"></div>
            </div>
          </div>

          <div style="margin-bottom:1rem;">
            <div id="headerProgram" style="color:var(--text-secondary);font-size:0.9rem;"></div>
            <div id="headerSections" style="color:var(--text-secondary);font-size:0.9rem;"></div>
          </div>

          <form id="profileForm">
          <div style="display:flex;gap:1rem;align-items:center;margin-bottom:0.6rem;">
            <div>
              <div class="avatar-preview" id="avatarPreview">
                <img id="avatarImg" src="" alt="avatar" style="display:none">
                <div id="avatarSpinner" class="avatar-spinner" aria-hidden="true" style="display:none"></div>
              </div>
            </div>
            <div style="flex:1">
              <div class="form-group">
                <label for="avatarInput">Avatar / Photo</label>
                <input id="avatarInput" type="file" accept="image/*">
                <button type="button" id="removeAvatar" class="btn-secondary" style="margin-top:0.5rem;">Remove avatar</button>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="profileName">Full Name</label>
            <input id="profileName" type="text" required>
          </div>
          <div class="form-group">
            <label for="currentPassword">Current Password (required to change password)</label>
            <input id="currentPassword" type="password" placeholder="Current password">
          </div>
          <div class="form-group">
            <label for="newPassword">New Password</label>
            <input id="newPassword" type="password" placeholder="New password">
            <div class="password-strength-inline" id="pwStrength">
              <div class="strength-bar-inline"><div class="strength-fill" id="strengthFill"></div></div>
              <div class="strength-text" id="strengthText">&nbsp;</div>
            </div>
          </div>
          <div class="form-group">
            <label for="confirmNewPassword">Confirm New Password</label>
            <input id="confirmNewPassword" type="password" placeholder="Confirm new password">
          </div>
          <div class="form-group">
            <label for="profileEmail">Email</label>
            <input id="profileEmail" type="email" required>
          </div>
          <div class="form-group">
            <label for="profileProgram">Program</label>
            <select id="profileProgram" required>
              <option value="">Select program</option>
              <option>Bachelor of Science in Computer Science</option>
              <option>Bachelor of Science in Information Technology</option>
              <option>Bachelor of Science in Engineering</option>
              <option>Bachelor of Science in Business Administration</option>
              <option>Bachelor of Arts in Education</option>
            </select>
          </div>
          <div class="form-group">
            <label for="profileYear">Year</label>
            <select id="profileYear" required>
              <option value="">Select year</option>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
            </select>
          </div>
            <div style="display:flex;gap:0.5rem;margin-top:1rem;align-items:center;">
            <button type="submit" class="btn-primary">Save Profile</button>
            <a href="index.php" class="btn-secondary">Cancel</a>
            <button type="button" id="deleteAccount" class="btn-delete btn-danger" style="margin-left:auto;">🗑️ Delete account</button>
          </div>
        </form>
      </section>

  <script src="settings.js"></script>
  <script src="auth.js"></script>
  <script src="app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('profileForm')
      const msg = document.getElementById('profileMessage')
  const avatarInput = document.getElementById('avatarInput')
  const avatarPreview = document.getElementById('avatarPreview')
  const avatarImg = document.getElementById('avatarImg')
  const removeAvatarBtn = document.getElementById('removeAvatar')
  const headerAvatar = document.getElementById('headerAvatar')
  const headerName = document.getElementById('headerName')
  const headerStudentId = document.getElementById('headerStudentId')
      const strengthFill = document.getElementById('strengthFill')
      const strengthText = document.getElementById('strengthText')

      function showMessage(t, type) {
        msg.textContent = t
        msg.className = 'auth-message ' + (type || '')
      }

      function load() {
        try {
          const raw = localStorage.getItem('batstate_current_user')
          if (!raw) {
           
            showMessage('You are not signed in. Please login or register to edit your profile', 'error')
            try {
              const controls = form.querySelectorAll('input, select, textarea, button')
              controls.forEach(c => {
                if (!c.classList.contains('btn-secondary')) c.disabled = true
              })
            } catch (e) {}
            return
          }
          const u = JSON.parse(raw)
          // normalize sections to an array so display works whether stored as array or CSV string
          try {
            if (u && u.sections && !Array.isArray(u.sections)) {
              if (typeof u.sections === 'string') {
                // split by comma and trim
                u.sections = u.sections.split(',').map(s => s.trim()).filter(Boolean)
              } else if (typeof u.sections === 'object' && u.sections !== null) {
                // if stored as object with numeric keys, try to convert to array
                try { u.sections = Object.values(u.sections).map(String).map(s=>s.trim()).filter(Boolean) } catch(e) { u.sections = [] }
              } else {
                u.sections = []
              }
            }
            if (!u.sections || (Array.isArray(u.sections) && u.sections.length === 0)) {
              // fallback: try to find the full user entry from the users list stored in localStorage
              try {
                const usersRaw = localStorage.getItem('batstate_users') || '[]'
                const users = JSON.parse(usersRaw)
                if (Array.isArray(users) && users.length) {
                  const match = users.find(x => (x.email && x.email.toLowerCase() === (u.email||'').toLowerCase()) || (x.sr_code && String(x.sr_code) === String(u.sr_code)) || (x.id && String(x.id) === String(u.id)))
                  if (match && match.sections) {
                    if (Array.isArray(match.sections)) u.sections = match.sections
                    else if (typeof match.sections === 'string') u.sections = match.sections.split(',').map(s=>s.trim()).filter(Boolean)
                  }
                }
              } catch (er) { /* ignore */ }
            }
            if (!u.sections) u.sections = []
          } catch(e) { u.sections = u.sections || [] }
          document.getElementById('profileName').value = u.name || ''
          document.getElementById('profileYear').value = u.year || ''
          const sidEl = document.getElementById('profileStudentId')
          if (sidEl) sidEl.textContent = u.sr_code || u.studentId || u.id || ''
          document.getElementById('profileEmail').value = u.email || ''
          // Render Program/Year/Sections as plain non-editable text for clarity
          try {
            const progEl = document.getElementById('profileProgram')
            if (progEl) {
              const txt = document.createElement('div')
              txt.className = 'profile-readonly-text'
              txt.style.padding = '10px 12px'
              txt.style.background = 'var(--bg-input)'
              txt.style.borderRadius = '6px'
              txt.style.color = 'var(--text-primary)'
              txt.textContent = u.program || ''
              progEl.parentNode.replaceChild(txt, progEl)
            }
          } catch (e) {}

          // Render Year as text
          try {
            const yearEl = document.getElementById('profileYear')
            if (yearEl) {
              const txty = document.createElement('div')
              txty.className = 'profile-readonly-text'
              txty.style.padding = '10px 12px'
              txty.style.background = 'var(--bg-input)'
              txty.style.borderRadius = '6px'
              txty.style.color = 'var(--text-primary)'
              txty.textContent = (u.year ? (u.year + (u.year === '1' ? 'st Year' : ' Year')) : '')
              yearEl.parentNode.replaceChild(txty, yearEl)
            }
          } catch (e) {}

          // populate sections display: ensure we show the user's selected sections (from registration)
          try {
            // headerSections shown above will use u.sections; but also show a readonly block for the profile form area if needed
            const profileSectionsContainer = document.getElementById('profileSections')
            if (profileSectionsContainer) {
              // replace with a readonly list showing selected sections
              const div = document.createElement('div')
              div.className = 'profile-readonly-text'
              div.style.padding = '10px 12px'
              div.style.background = 'var(--bg-input)'
              div.style.borderRadius = '6px'
              div.style.color = 'var(--text-primary)'
              div.textContent = (Array.isArray(u.sections) && u.sections.length) ? u.sections.join(', ') : ''
              profileSectionsContainer.parentNode.replaceChild(div, profileSectionsContainer)
            }
          } catch (e) {}
          if (u.avatar) {
            avatarImg.src = u.avatar
            avatarImg.style.display = 'block'
            if (headerAvatar) { headerAvatar.src = u.avatar; headerAvatar.style.display = 'block' }
          } else {
            avatarImg.style.display = 'none'
            if (headerAvatar) { headerAvatar.src = ''; headerAvatar.style.display = 'none' }
          }
          if (headerName) headerName.textContent = u.name || ''
          if (headerStudentId) headerStudentId.textContent = u.sr_code || u.studentId || ''
          const headerProgramEl = document.getElementById('headerProgram')
          const headerSectionsEl = document.getElementById('headerSections')
          if (headerProgramEl) headerProgramEl.textContent = (u.program ? (u.program + (u.year ? ' • Year ' + u.year : '')) : '')
          if (headerSectionsEl) {
            const secList = (Array.isArray(u.sections) && u.sections.length) ? u.sections.join(', ') : 'None'
            const label = (Array.isArray(u.sections) && u.sections.length === 1) ? 'Section: ' : 'Sections: '
            headerSectionsEl.textContent = label + secList
          }
          // enable form controls (except program/year/sections which should remain read-only)
          try {
            const controls = form.querySelectorAll('input, textarea, button')
            controls.forEach(c => { if (!c.classList.contains('btn-secondary')) c.disabled = false })
          } catch (e) {}
        } catch (e) {}
      }

      load()

      function calcPasswordStrength(pw) {
        if (!pw) return { pct: 0, text: '' }
        let score = 0
        if (pw.length >= 8) score += 1
        if (/[A-Z]/.test(pw)) score += 1
        if (/[a-z]/.test(pw)) score += 1
        if (/[0-9]/.test(pw)) score += 1
        if (/[^A-Za-z0-9]/.test(pw)) score += 1
        const pct = Math.min(100, Math.round((score / 5) * 100))
        let text = 'Very weak'
        if (pct >= 80) text = 'Strong'
        else if (pct >= 60) text = 'Good'
        else if (pct >= 40) text = 'Fair'
        else text = 'Weak'
        return { pct, text }
      }

      const newPwInput = document.getElementById('newPassword')
      const confirmPwInput = document.getElementById('confirmNewPassword')
      function updateStrength() {
        const val = newPwInput.value || ''
        const s = calcPasswordStrength(val)
        if (strengthFill) strengthFill.style.width = s.pct + '%'
        if (strengthText) strengthText.textContent = s.text
      }
      if (newPwInput) newPwInput.addEventListener('input', updateStrength)

      if (avatarInput) {
        avatarInput.addEventListener('change', function() {
          const f = this.files && this.files[0]
          if (!f) return
          const spinner = document.getElementById('avatarSpinner')
          if (spinner) spinner.style.display = 'block'
          const reader = new FileReader()
          reader.onload = function(ev) {
            const tmp = new Image()
            tmp.onload = function() {
              try {
                const side = Math.min(tmp.width, tmp.height)
                const sx = Math.round((tmp.width - side) / 2)
                const sy = Math.round((tmp.height - side) / 2)
                const outSize = 128 
                const canvas = document.createElement('canvas')
                canvas.width = outSize
                canvas.height = outSize
                const ctx = canvas.getContext('2d')
                ctx.drawImage(tmp, sx, sy, side, side, 0, 0, outSize, outSize)
                const dataUrl = canvas.toDataURL('image/jpeg', 0.72)
                avatarImg.src = dataUrl
                avatarImg.style.display = 'block'
                avatarInput.dataset.preview = dataUrl
              } catch (e) {
                avatarImg.src = ev.target.result
                avatarImg.style.display = 'block'
                avatarInput.dataset.preview = ev.target.result
              } finally {
                if (spinner) spinner.style.display = 'none'
              }
            }
            tmp.onerror = function() {
              avatarImg.src = ev.target.result
              avatarImg.style.display = 'block'
              avatarInput.dataset.preview = ev.target.result
              if (spinner) spinner.style.display = 'none'
            }
            tmp.src = ev.target.result
          }
          reader.readAsDataURL(f)
        })
      }

      if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', function() {
          avatarImg.src = ''
          avatarImg.style.display = 'none'
          avatarInput.value = ''
          if (avatarInput) delete avatarInput.dataset.preview
        })
      }

      form.addEventListener('submit', (e) => {
        e.preventDefault()
  const name = document.getElementById('profileName').value.trim()
  const studentIdEl = document.getElementById('profileStudentId')
  let studentId = ''
  if (studentIdEl) {
    const tag = (studentIdEl.tagName || '').toLowerCase()
    if (tag === 'input' || tag === 'textarea' || studentIdEl.isContentEditable) {
      studentId = (studentIdEl.value || studentIdEl.textContent || '').trim()
    } else {
      studentId = (studentIdEl.textContent || '').trim()
    }
  }
        const email = document.getElementById('profileEmail').value.trim().toLowerCase()
        const program = document.getElementById('profileProgram').value

  const currentPasswordInput = document.getElementById('currentPassword').value
  const newPassword = document.getElementById('newPassword').value
  const confirmNewPassword = document.getElementById('confirmNewPassword').value
        let users = JSON.parse(localStorage.getItem('batstate_users') || '[]')
        const current = JSON.parse(localStorage.getItem('batstate_current_user') || 'null')
        const currentId = current && (current.id || current.studentId)

        const effectiveName = name || (current && current.name) || ''
        const effectiveEmail = email || (current && current.email) || ''
        // prefer sr_code where available
        const effectiveStudentId = studentId || (current && (current.sr_code || current.studentId || current.id)) || ''
        // program/year/sections are read-only; preserve current values from localStorage instead of reading DOM
        const profileYearVal = (current && current.year) || ''
        const profileSections = (current && Array.isArray(current.sections) ? current.sections : [])

        if (!effectiveName || !effectiveEmail || !effectiveStudentId) {
          showMessage('Please complete required fields', 'error')
          return
        }

        const studentIdFieldIsEditable = studentIdEl && ((studentIdEl.tagName || '').toLowerCase() === 'input' || (studentIdEl.tagName || '').toLowerCase() === 'textarea' || studentIdEl.isContentEditable)
        if (studentIdFieldIsEditable) {
          const dupSid = users.find(u => ((u.sr_code||u.studentId)||'').toString().trim() === effectiveStudentId && ((u.id||u.studentId) !== currentId))
          if (dupSid) { showMessage('SR-CODE already in use by another account', 'error'); return }
        }

        const dupEmail = users.find(u => (u.email||'').toString().toLowerCase() === effectiveEmail && ((u.id||u.studentId) !== currentId))
        if (dupEmail) { showMessage('Email already in use by another account', 'error'); return }

        let updated = null
        if (currentId) {
          users = users.map(u => {
            if ((u.id || u.studentId) == currentId) {
              // Do not allow changing program/year/sections here; keep existing stored values
              updated = Object.assign({}, u, { name: effectiveName, studentId: effectiveStudentId, sr_code: effectiveStudentId, email: effectiveEmail, program: u.program || program, year: profileYearVal, sections: profileSections })
              if (newPassword || confirmNewPassword) {
                if (!currentPasswordInput) {
                  showMessage('Enter your current password to change it', 'error')
                  updated = null
                  return u
                }
                if ((u.password || '') !== currentPasswordInput) {
                  showMessage('Current password is incorrect', 'error')
                  updated = null
                  return u
                }
                if (newPassword !== confirmNewPassword) {
                  showMessage('New passwords do not match', 'error')
                  updated = null
                  return u
                }
                if (newPassword && newPassword.length < 8) {
                  showMessage('New password must be at least 8 characters', 'error')
                  updated = null
                  return u
                }
              
                updated.password = newPassword
              }
              return updated
            }
            return u
          })
        }
        if (!updated) {
          updated = { id: Date.now().toString(), name: effectiveName, studentId: effectiveStudentId, sr_code: effectiveStudentId, email: effectiveEmail, program: (current && current.program) || program, year: profileYearVal, sections: profileSections, password: current && current.password ? current.password : '' }
          users.push(updated)
        }
        if (avatarInput && avatarInput.dataset && avatarInput.dataset.preview) {
          updated.avatar = avatarInput.dataset.preview
        } else if (avatarImg && avatarImg.src) {
          updated.avatar = avatarImg.src || ''
        }

        localStorage.setItem('batstate_users', JSON.stringify(users))
        localStorage.setItem('batstate_current_user', JSON.stringify(updated))

        try {
          if (headerAvatar) {
            if (updated.avatar) { headerAvatar.src = updated.avatar; headerAvatar.style.display = 'block' }
            else { headerAvatar.src = ''; headerAvatar.style.display = 'none' }
          }
          if (headerName) headerName.textContent = updated.name || ''
          if (headerStudentId) headerStudentId.textContent = updated.sr_code || updated.studentId || ''
          if (avatarImg) { avatarImg.src = updated.avatar || ''; avatarImg.style.display = updated.avatar ? 'block' : 'none' }

          const profileAvatarEl = document.querySelector('.profile-avatar')
          if (profileAvatarEl) {
            const existingImg = profileAvatarEl.querySelector('img')
            if (updated.avatar) {
              if (existingImg) existingImg.src = updated.avatar
              else profileAvatarEl.innerHTML = `<img src="${updated.avatar}" alt="${(updated.name||'avatar')}">`
            } else {
              if (existingImg) existingImg.remove()
              const initials = (updated.name || '').split(' ').map(n=>n[0]).slice(0,2).join('').toUpperCase()
              if (!profileAvatarEl.textContent.trim()) profileAvatarEl.textContent = initials
            }
          }

          if (typeof updateGreeting === 'function') try { updateGreeting() } catch (e) {}

          if (avatarInput && avatarInput.dataset) delete avatarInput.dataset.preview
        } catch (e) {
        }

        showMessage('Profile saved successfully', 'success')
      })

      const deleteBtn = document.getElementById('deleteAccount')
      function showUndoBanner(timeoutMs, deletedUser) {
        const existing = document.getElementById('undoBanner')
        if (existing) existing.remove()
        const b = document.createElement('div')
        b.id = 'undoBanner'
        b.className = 'undo-banner'
        b.innerHTML = `<div class="undo-content">Account deleted. <button id="undoBtn" class="btn-secondary">Undo</button> <button id="dismissUndo" class="btn-secondary">Dismiss</button></div>`
        document.body.appendChild(b)

        const undoBtn = document.getElementById('undoBtn')
        const dismissBtn = document.getElementById('dismissUndo')

        let to = setTimeout(() => {
          try { sessionStorage.removeItem('batstate_deleted_user') } catch (e) {}
          window.location.replace('login.php?account_deleted=1')
        }, timeoutMs)

        undoBtn.addEventListener('click', function() {
          clearTimeout(to)
          try {
            const raw = sessionStorage.getItem('batstate_deleted_user')
            if (!raw) return
            const payload = JSON.parse(raw)
            const restored = payload.user
            let users = JSON.parse(localStorage.getItem('batstate_users') || '[]')
            if (!users.find(u => (u.id||u.studentId) == (restored.id||restored.studentId))) {
              users.push(restored)
              localStorage.setItem('batstate_users', JSON.stringify(users))
            }
            localStorage.setItem('batstate_current_user', JSON.stringify(restored))
            sessionStorage.removeItem('batstate_deleted_user')
            if (headerAvatar) { headerAvatar.src = restored.avatar || ''; headerAvatar.style.display = restored.avatar ? 'block' : 'none' }
            if (avatarImg) { avatarImg.src = restored.avatar || ''; avatarImg.style.display = restored.avatar ? 'block' : 'none' }
            if (headerName) headerName.textContent = restored.name || ''
            if (headerStudentId) headerStudentId.textContent = restored.sr_code || restored.studentId || ''
            const profileAvatarEl = document.querySelector('.profile-avatar')
            if (profileAvatarEl) {
              profileAvatarEl.innerHTML = restored.avatar ? `<img src="${restored.avatar}" alt="${(restored.name||'avatar')}">` : (restored.name||'').split(' ').map(n=>n[0]).slice(0,2).join('').toUpperCase()
            }
            const ex = document.getElementById('undoBanner')
            if (ex) ex.remove()
            showMessage('Account restored', 'success')
          } catch (err) { console.error('undo failed', err) }
        })

        dismissBtn.addEventListener('click', function() {
          clearTimeout(to)
          try { sessionStorage.removeItem('batstate_deleted_user') } catch (e) {}
          const ex = document.getElementById('undoBanner')
          if (ex) ex.remove()
          window.location.replace('login.php?account_deleted=1')
        })
      }

     
      window.performPermanentDelete = function() {
        try {
          const cur = JSON.parse(localStorage.getItem('batstate_current_user') || 'null')
          if (!cur) { showMessage('No signed-in user to delete', 'error'); return }

          const timeoutMs = (window && window.DELETE_UNDO_TIMEOUT_MS) ? window.DELETE_UNDO_TIMEOUT_MS : 8000

          // show pending deletion banner and wait for user's choice (Undo or Dismiss)
          const existing = document.getElementById('pendingDeleteBanner')
          if (existing) existing.remove()
          const b = document.createElement('div')
          b.id = 'pendingDeleteBanner'
          b.className = 'undo-banner'
          b.innerHTML = `<div class="undo-content">Account deletion pending. <button id="undoBtn" class="btn-secondary">Undo</button> <button id="dismissDelete" class="btn-secondary">Dismiss</button></div>`
          document.body.appendChild(b)

          // store pending deletion so it can be inspected if needed
          try { sessionStorage.setItem('batstate_pending_deleted_user', JSON.stringify({ user: cur, scheduledAt: Date.now() })) } catch (e) {}

          // function to execute the actual delete (server + local)
            const executeDelete = function() {
            try { sessionStorage.removeItem('batstate_pending_deleted_user') } catch (e) {}
            // choose an identifier for server delete: prefer numeric account id, otherwise email, otherwise studentId
            const maybeId = cur.id || null
            const maybeEmail = cur.email || null
            const maybeStudentId = cur.studentId || null
            const deleteIdentifier = (maybeId && String(maybeId).match(/^\d+$/)) ? maybeId : (maybeEmail ? maybeEmail : maybeStudentId)
            const tryServerDelete = deleteIdentifier && String(deleteIdentifier).match(/^\d+$/)
            if (tryServerDelete) {
              fetch('delete_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: deleteIdentifier })
              })
              .then(r => r.json())
              .then(data => {
                // save deleted user info for undo restore
                try { sessionStorage.setItem('batstate_deleted_user', JSON.stringify({ user: cur, deletedAt: Date.now() })) } catch (e) {}
                // perform local cleanup
                finalizeLocalDelete(cur)
                window.location.replace('login.php?account_deleted=1')
              })
              .catch(err => {
                console.error('Server delete error', err)
                try { sessionStorage.setItem('batstate_deleted_user', JSON.stringify({ user: cur, deletedAt: Date.now() })) } catch (e) {}
                finalizeLocalDelete(cur)
                window.location.replace('login.php?account_deleted=1')
              })
            } else {
              // try server delete by non-numeric identifier (email or studentId)
              fetch('delete_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: deleteIdentifier })
              })
              .then(r => r.json())
              .then(data => {
                try { sessionStorage.setItem('batstate_deleted_user', JSON.stringify({ user: cur, deletedAt: Date.now() })) } catch (e) {}
                finalizeLocalDelete(cur)
                window.location.replace('login.php?account_deleted=1')
              })
              .catch(err => {
                // if server delete fails, fall back to local delete
                try { sessionStorage.setItem('batstate_deleted_user', JSON.stringify({ user: cur, deletedAt: Date.now() })) } catch (e) {}
                finalizeLocalDelete(cur)
                window.location.replace('login.php?account_deleted=1')
              })
            }
          }

          // schedule automatic delete after timeout
          let to = setTimeout(executeDelete, timeoutMs)

          // bind undo and dismiss
          const undoBtn = document.getElementById('undoBtn')
          const dismissBtn = document.getElementById('dismissDelete')
          undoBtn && undoBtn.addEventListener('click', function() {
            clearTimeout(to)
            try { sessionStorage.removeItem('batstate_pending_deleted_user') } catch (e) {}
            const ex = document.getElementById('pendingDeleteBanner')
            if (ex) ex.remove()
            showMessage('Account deletion cancelled', 'info')
          })
          dismissBtn && dismissBtn.addEventListener('click', function() {
            clearTimeout(to)
            const ex = document.getElementById('pendingDeleteBanner')
            if (ex) ex.remove()
            executeDelete()
          })

        } catch (err) { console.error(err); showMessage('Delete failed', 'error') }
      }

      function finalizeLocalDelete(cur) {
        try {
          let users = JSON.parse(localStorage.getItem('batstate_users') || '[]')
          const curKey = String(cur.id || cur.studentId || '')
          const remaining = users.filter(u => String(u.id || u.studentId || '') !== curKey)
          try { localStorage.setItem('batstate_users', JSON.stringify(remaining)) } catch (e) { console.error('failed to update users', e) }
          try { sessionStorage.setItem('batstate_deleted_user', JSON.stringify({ user: cur, deletedAt: Date.now() })) } catch (e) { console.error('failed to save deleted user', e) }
          localStorage.removeItem('batstate_current_user')
        } catch (e) { console.error('finalizeLocalDelete failed', e) }
      }

      if (deleteBtn) {
        try {
          deleteBtn.addEventListener('click', window.performPermanentDelete)
        } catch (e) { console.error('failed to bind deleteBtn', e) }
      }
    })
  </script>
</body>
</html>
