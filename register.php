<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BatStateU - Student Registration</title>
    <link rel="stylesheet" href="styles.css?v=2">
</head>
<body class="auth-page">
        </div>
        <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
        <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>
    </section>
    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-brand">
                <div class="brand-logo"><img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU logo" class="brand-logo-img"></div>
                <h1>BatStateU - LearnHub</h1>
                <p>Student Learning Portal</p>
            </div>
            <div class="auth-welcome">
                <h2>Join Our Community</h2>
                <p>Create your account to access learning resources</p>
            </div>
        </div>
        
        <div class="auth-right">
            <form id="registerForm" class="auth-form">
                <h2>Create Your Account</h2>
                
                <div class="form-group">
                    <label for="regFullName">Full Name</label>
                    <input type="text" id="regFullName" placeholder="Juan Dela Cruz" required>
                </div>
                
                <div class="form-group">
                    <label for="regStudentId">Student ID</label>
                    <input type="text" id="regStudentId" placeholder="2024001" required>
                </div>
                
                <div class="form-group">
                    <label for="regEmail">Email Address</label>
                    <input type="email" id="regEmail" placeholder="student@batstateu.edu.ph" required>
                </div>
                
                <div class="form-group">
                    <label for="regProgram">Program</label>
                    <select id="regProgram" required>
                        <option value="">Select your program</option>
                        <option value="Bachelor of Science in Computer Science">BS Computer Science</option>
                        <option value="Bachelor of Science in Information Technology">BS Information Technology</option>
                        <option value="Bachelor of Science in Engineering">BS Engineering</option>
                        <option value="Bachelor of Science in Business Administration">BS Business Administration</option>
                        <option value="Bachelor of Arts in Education">BA Education</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="regPassword">Password</label>
                    <div class="password-field">
                        <input type="password" id="regPassword" placeholder="Create a strong password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('regPassword')">👁️</button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                        <small id="strengthText">Password strength: Weak</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="regConfirmPassword">Confirm Password</label>
                    <div class="password-field">
                        <input type="password" id="regConfirmPassword" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('regConfirmPassword')">👁️</button>
                    </div>
                </div>
                
                <label class="checkbox">
                    <input type="checkbox" id="agreeTerms" required>
                    <span>I agree to the Terms of Service and Privacy Policy</span>
                </label>
                
                <button type="submit" class="btn-primary btn-register">Create Account</button>
                
                <div class="divider">
                    <span>Already have an account?</span>
                </div>
                
                <a href="login.php" class="btn-secondary btn-login">Sign In</a>
                
                <div class="auth-message" id="authMessage"></div>
            </form>
        </div>
    </div>
    
    <script>
        function togglePassword(id) {
            const field = document.getElementById(id);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
        
        function getPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            return strength;
        }
        
        document.getElementById('regPassword').addEventListener('input', function() {
            const strength = getPasswordStrength(this.value);
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            
            const strengthLevels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong', 'Excellent'];
            const colors = ['#c41e3a', '#ff9800', '#ffeb3b', '#8bc34a', '#4caf50', '#009688'];
            
            bar.style.width = (strength * 16.67) + '%';
            bar.style.backgroundColor = colors[strength - 1] || '#ccc';
            text.textContent = 'Password strength: ' + strengthLevels[strength - 1];
        });
        
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fullName = document.getElementById('regFullName').value;
            const studentId = document.getElementById('regStudentId').value;
            const email = document.getElementById('regEmail').value;
            const program = document.getElementById('regProgram').value;
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;
            
            if (password !== confirmPassword) {
                showMessage('Passwords do not match', 'error');
                return;
            }
            
            if (password.length < 8) {
                showMessage('Password must be at least 8 characters', 'error');
                return;
            }
            
            let users = JSON.parse(localStorage.getItem('batstate_users') || '[]');

            // Normalize inputs
            const normalizedStudentId = (studentId || '').toString().trim();
            const normalizedEmail = (email || '').toString().trim().toLowerCase();

            // Check for duplicate studentId
            // If there's a recently deleted user in sessionStorage (undo window), ignore that user when checking duplicates
            let deletedSid = null
            try {
                const rawDeleted = sessionStorage.getItem('batstate_deleted_user')
                if (rawDeleted) {
                    const payload = JSON.parse(rawDeleted)
                    deletedSid = (payload && payload.user && (payload.user.studentId || payload.user.id)) || null
                }
            } catch (e) { deletedSid = null }

            if (normalizedStudentId) {
                const found = users.find(u => (u.studentId || '').toString().trim() === normalizedStudentId)
                if (found && String((found.studentId||found.id||'')).trim() !== String(deletedSid || '').trim()) {
                    showMessage('Student ID already registered', 'error');
                    return;
                }
            }

            // Check for duplicate email
            if (normalizedEmail) {
                const foundEmail = users.find(u => (u.email || '').toString().toLowerCase() === normalizedEmail)
                if (foundEmail && String((foundEmail.id||foundEmail.studentId||'')).trim() !== String(deletedSid || '').trim()) {
                    showMessage('Email already registered', 'error');
                    return;
                }
            }
            
            const newUser = {
                id: Date.now().toString(),
                name: fullName,
                studentId: normalizedStudentId,
                email: normalizedEmail,
                program: program,
                password: password
            };
            
            users.push(newUser);
            localStorage.setItem('batstate_users', JSON.stringify(users));
            localStorage.setItem('batstate_current_user', JSON.stringify(newUser));
            
            showMessage('Account created successfully! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        });

        // Inline Student ID validation (instant feedback)
        (function() {
            const sidEl = document.getElementById('regStudentId');
            if (!sidEl) return;
            sidEl.addEventListener('input', function() {
                try {
                    const val = (this.value || '').toString().trim();
                    const users = JSON.parse(localStorage.getItem('batstate_users') || '[]');
                    if (val) {
                        // Respect recently deleted user in sessionStorage (undo window)
                        let deletedSidLocal = null
                        try {
                            const rawDeletedLocal = sessionStorage.getItem('batstate_deleted_user')
                            if (rawDeletedLocal) {
                                const p = JSON.parse(rawDeletedLocal)
                                deletedSidLocal = (p && p.user && (p.user.studentId || p.user.id)) || null
                            }
                        } catch (e) { deletedSidLocal = null }

                        const foundLocal = users.find(u => (u.studentId || '').toString().trim() === val)
                        if (foundLocal && String((foundLocal.studentId||foundLocal.id||'')).trim() !== String(deletedSidLocal || '').trim()) {
                            this.setCustomValidity('Student ID already registered');
                            showMessage('Student ID already registered', 'error');
                        } else {
                            this.setCustomValidity('');
                            const el = document.getElementById('authMessage');
                            if (el) { el.textContent = ''; el.className = 'auth-message'; }
                        }
                    } else {
                        this.setCustomValidity('');
                        const el = document.getElementById('authMessage');
                        if (el) { el.textContent = ''; el.className = 'auth-message'; }
                    }
                } catch (e) {
                    // ignore validation errors
                }
            });
            sidEl.addEventListener('blur', function() { this.reportValidity(); });
        })();
        
        function showMessage(msg, type) {
            const el = document.getElementById('authMessage');
            el.textContent = msg;
            el.className = 'auth-message ' + type;
        }
    </script>
</body>
</html>
