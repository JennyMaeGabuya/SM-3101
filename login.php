<?php
include 'connection/dbsConnection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BatStateU - Student Portal Login</title>
    <link rel="stylesheet" href="styles/styles.css?v=2">
</head>
<body class="auth-page login-page">
   <section class="login-section" style="position:relative; overflow:visible;">

   
    <div class="gx-orb gx-orb--soft"  style="right:-80px; top:-100px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-40px; bottom:-70px; background:var(--gx-orb-2);"></div>
    <div class="gx-orb gx-orb--small" style="left:-50px; top:40%; background:var(--gx-orb-3);"></div>
   
    <div class="gx-orb gx-orb--soft"  style="right:-100px; bottom:-50px; background:var(--gx-orb-4);"></div>
    <div class="gx-orb gx-orb--small" style="right:35%; top:-40px; background:var(--gx-orb-5);"></div>
    </section>
    <div id="logoutBanner" class="logout-banner" style="display:none">You have been logged out.</div>
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-brand">
                <div class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU logo" class="brand-logo-img"></div>
                <h1>BatStateU - LearnHub</h1>
                <p>Student Learning Portal</p>
            </div>
            <div class="auth-welcome">
                <h2>Welcome Back</h2>
                <p>Access your courses, track your ongoing quizzes, assignments, activities, and more</p>
            </div>
        </div>
        
        <div class="auth-right">
            <form id="loginForm" class="auth-form">
                <h2>Login to Your Account</h2>
                <div class="form-group">
                    <label>Login As</label>
                    <div style="display:flex;gap:8px;align-items:center">
                        <label><input type="radio" name="role" value="student" checked> Student</label>
                        <label><input type="radio" name="role" value="teacher"> Teacher</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="loginEmail">Student ID or Email</label>
                    <input type="text" id="loginEmail" placeholder="Student ID or email" required>
                </div>
                
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <div class="password-field">
                        <input type="password" id="loginPassword" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('loginPassword')">👁️</button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn-primary btn-login">Sign In</button>
                
                <div class="divider">
                    <span>New to BatStateU?</span>
                </div>
                
                <a href="register.php" class="btn-secondary btn-register">Create an Account</a>
                
                <div class="auth-message" id="authMessage"></div>
            </form>
            
            <div class="demo-info">
                <p><strong>Demo Credentials:</strong></p>
                <p>Student — Email: student@batstateu.edu.ph | Password: Demo@2024</p>
                <p>Teacher — Email: teacher@batstateu.edu.ph | Password: Teach@2024</p>
            </div>
        </div>
    </div>
    
    <script src="settings.js"></script>
    <script src="auth.js"></script>
    <script>
        
        window.addEventListener('load', function() {
            try {
                const params = new URLSearchParams(window.location.search)
                const flagged = sessionStorage.getItem('batstate_just_logged_out') || params.has('logged_out')
                
                if (params.has('logged_out')) {
                    try { localStorage.removeItem('batstate_current_user') } catch (e) {}
                }
                if (flagged) {
                    showMessage('You have been logged out.', 'info')
                    const b = document.getElementById('logoutBanner')
                    if (b) {
                        b.style.display = 'block'
                        setTimeout(() => { b.style.display = 'none' }, 4000)
                    }
                    sessionStorage.removeItem('batstate_just_logged_out')
                   
                    if (params.has('logged_out')) {
                        params.delete('logged_out')
                        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '')
                        history.replaceState({}, document.title, newUrl)
                    }
                }

             
                if (params.has('account_deleted')) {
                    showMessage('Your account was deleted.', 'info')
                    const b2 = document.getElementById('logoutBanner')
                    if (b2) {
                        b2.textContent = 'Your account was deleted.'
                        b2.style.display = 'block'
                        setTimeout(() => { b2.style.display = 'none' }, 4000)
                    }
                  
                    params.delete('account_deleted')
                    const newUrl2 = window.location.pathname + (params.toString() ? '?' + params.toString() : '')
                    history.replaceState({}, document.title, newUrl2)
                }

                try {
                    const rawDeleted = sessionStorage.getItem('batstate_deleted_user')
                    if (rawDeleted) {
                        const existing = document.getElementById('undoBanner')
                        if (existing) existing.remove()
                        const b = document.createElement('div')
                        b.id = 'undoBanner'
                        b.className = 'undo-banner'
                        b.innerHTML = `<div class="undo-content">Account deleted. <button id="undoBtn" class="btn-secondary">Undo</button> <button id="dismissUndo" class="btn-secondary">Dismiss</button></div>`
                        document.body.appendChild(b)

                        const undoBtn = document.getElementById('undoBtn')
                        const dismissBtn = document.getElementById('dismissUndo')

                        const timeoutMs = (window && window.DELETE_UNDO_TIMEOUT_MS) ? window.DELETE_UNDO_TIMEOUT_MS : 8000
                        let to = setTimeout(() => {
                            try { sessionStorage.removeItem('batstate_deleted_user') } catch (e) {}
                            const b2 = document.getElementById('logoutBanner')
                            if (b2) {
                                b2.textContent = 'Your account was deleted.'
                                b2.style.display = 'block'
                                setTimeout(() => { b2.style.display = 'none' }, 4000)
                            }
                            const ex = document.getElementById('undoBanner')
                            if (ex) ex.remove()
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
                                const ex = document.getElementById('undoBanner')
                                if (ex) ex.remove()
                                window.location.replace('index.php')
                            } catch (err) { console.error('undo failed', err) }
                        })

                        dismissBtn.addEventListener('click', function() {
                            clearTimeout(to)
                            try { sessionStorage.removeItem('batstate_deleted_user') } catch (e) {}
                            const ex = document.getElementById('undoBanner')
                            if (ex) ex.remove()
                            try {
                                const b2 = document.getElementById('logoutBanner')
                                if (b2) {
                                    b2.textContent = 'Your account was deleted.'
                                    b2.style.display = 'block'
                                    setTimeout(() => { b2.style.display = 'none' }, 4000)
                                }
                            } catch (e) {}
                        })
                    }
                } catch (e) {
                }
            } catch (e) {
            }
        });

            document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const identifierRaw = (document.getElementById('loginEmail').value || '').toString();
            const identifier = identifierRaw.trim();
            const password = document.getElementById('loginPassword').value;
                const roleEl = document.querySelector('input[name="role"]:checked');
                const role = roleEl ? roleEl.value : 'student';

            if (!identifier) {
                showMessage('Please enter your Student ID or email', 'error');
                return;
            }

            // Send credentials to server for verification
            fetch('login_action.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ identifier: identifier, password: password, role: role })
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && data.user) {
                    // store current user locally for client-side features
                    try { localStorage.setItem('batstate_current_user', JSON.stringify(data.user)); } catch (e) {}
                    showMessage('Login successful. Redirecting...', 'success');
                    setTimeout(() => {
                        if (data.role && data.role === 'teacher') {
                            window.location.href = 'teacher/index.php';
                        } else {
                            window.location.href = 'index.php';
                        }
                    }, 800);
                } else {
                    showMessage((data && data.message) || 'Invalid credentials', 'error');
                }
            })
            .catch(err => {
                showMessage('Server error: ' + (err.message || err), 'error');
            });
        });
        
        function showMessage(msg, type) {
            const el = document.getElementById('authMessage');
            el.textContent = msg;
            el.className = 'auth-message ' + type;
        }
        
        function togglePassword(id) {
            const field = document.getElementById(id);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
        
        window.addEventListener('load', function() {
            let users = JSON.parse(localStorage.getItem('batstate_users') || '[]');
            if (!users.find(u => u.email === 'student@batstateu.edu.ph')) {
                users.push({
                    id: '2024001',
                    name: 'Juan Dela Cruz',
                    email: 'student@batstateu.edu.ph',
                    password: 'Demo@2024',
                    studentId: '2024001',
                    program: 'Bachelor of Science in Computer Science'
                });
                localStorage.setItem('batstate_users', JSON.stringify(users));
            }
        });
    </script>
</body>
</html>
