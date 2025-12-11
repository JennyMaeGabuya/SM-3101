<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BatStateU - Student Registration</title>
    <link rel="stylesheet" href="styles.css?v=2">
</head>
<style>
    .btn-create-teacher {
        background-color: #fff;
        color: #c41e3a;
        border: none;
        padding: 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s ease;
    }

    .btn-create-teacher:hover {
        background-color: #c41e3a;
        color: #fff;
    }
</style>

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
                <div class="form-header" style="display: flex; justify-content: space-between;">
                    <h2>Create Student Account</h2>
                    <button type="button" class="btn-close btn-create-teacher" onclick="window.location.href='register_teacher.php';">Create Teacher Account ➥</button>
                </div>

                <div class="form-group">
                    <label for="regFullName">Full Name</label>
                    <input type="text" id="regFullName" placeholder="Juan Dela Cruz" required>
                </div>

                <div class="form-group">
                    <label for="regSrCode">SR-CODE</label>
                    <input type="text" id="regSrCode" placeholder="00-00000" pattern="\d{2}-\d{5}" title="Format: 00-00000 (two digits, dash, five digits)" maxlength="8" required>
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
                    <label for="regYear">Year</label>
                    <select id="regYear" required>
                        <option value="">Select year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="regSections">Section (select one or more)</label>
                    <select id="regSections" multiple size="6" style="min-height:120px;" title="Hold Ctrl (Cmd) to select multiple">
                        <!-- populated dynamically -->
                    </select>
                    <small class="form-help">Tip: choose the sections assigned to you. You can select multiple using Ctrl (Cmd) key.</small>
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
            const srCode = document.getElementById('regSrCode').value;
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

            // validate program, year and sections using DOM values (avoid referencing newUser before initialization)
            const selectedSections = (function() {
                const sel = document.getElementById('regSections');
                if (!sel) return [];
                const out = [];
                for (let i = 0; i < sel.options.length; i++) {
                    if (sel.options[i].selected) out.push(sel.options[i].value);
                }
                return out;
            })();

            if (!program) {
                showMessage('Please select your program', 'error');
                return;
            }
            const yearVal = (document.getElementById('regYear') && document.getElementById('regYear').value) || '';
            if (!yearVal) {
                showMessage('Please select your year', 'error');
                return;
            }
            if (!selectedSections || selectedSections.length === 0) {
                showMessage('Please select at least one section', 'error');
                return;
            }

            let users = JSON.parse(localStorage.getItem('batstate_users') || '[]');

            const normalizedSrCode = (srCode || '').toString().trim();

            // enforce SR-CODE format: two digits, dash, five digits (e.g. 12-34567)
            const srPattern = /^\d{2}-\d{5}$/;
            if (!srPattern.test(normalizedSrCode)) {
                showMessage('SR-CODE must be in format 00-00000', 'error');
                return;
            }
            const normalizedEmail = (email || '').toString().trim().toLowerCase();


            let deletedSid = null
            try {
                const rawDeleted = sessionStorage.getItem('batstate_deleted_user')
                if (rawDeleted) {
                    const payload = JSON.parse(rawDeleted)
                    deletedSid = (payload && payload.user && (payload.user.studentId || payload.user.id)) || null
                }
            } catch (e) {
                deletedSid = null
            }

            if (normalizedSrCode) {
                const found = users.find(u => ((u.sr_code || u.studentId) || '').toString().trim() === normalizedSrCode)
                if (found && String((found.sr_code || found.studentId || found.id || '')).trim() !== String(deletedSid || '').trim()) {
                    showMessage('SR-CODE already registered', 'error');
                    return;
                }
            }

            if (normalizedEmail) {
                const foundEmail = users.find(u => (u.email || '').toString().toLowerCase() === normalizedEmail)
                if (foundEmail && String((foundEmail.id || foundEmail.studentId || '')).trim() !== String(deletedSid || '').trim()) {
                    showMessage('Email already registered', 'error');
                    return;
                }
            }

            const newUser = {
                name: fullName,
                sr_code: normalizedSrCode,
                email: normalizedEmail,
                program: program,
                password: password,
                year: yearVal,
                sections: selectedSections
            };

            // Send to server for persistent storage
            fetch('register_action.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(newUser)
                })
                .then(r => r.json())
                .then(res => {
                    if (res && res.success) {
                        // prefer server-returned user object when available
                        try {
                            // if server returned a user but omitted sections, merge the locally-chosen sections
                            let u;
                            if (res.user && typeof res.user === 'object') {
                                u = Object.assign({}, res.user);
                                try {
                                    if ((!u.sections || (Array.isArray(u.sections) && u.sections.length === 0)) && Array.isArray(newUser.sections) && newUser.sections.length) {
                                        u.sections = newUser.sections.slice();
                                    }
                                } catch (e) {}
                            } else {
                                u = {
                                    id: res.id,
                                    name: fullName,
                                    sr_code: normalizedSrCode,
                                    email: normalizedEmail,
                                    program: newUser.program,
                                    year: newUser.year,
                                    sections: newUser.sections
                                };
                            }
                            localStorage.setItem('batstate_current_user', JSON.stringify(u));
                        } catch (e) {}
                        showMessage('Account created successfully! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 1200);
                    } else {
                        showMessage((res && res.message) || 'Registration failed', 'error');
                    }
                })
                .catch(err => {
                    showMessage('Server error: ' + (err.message || err), 'error');
                });
        });

        (function() {
            // populate initial sections from API (names) and wire program/year change to generate program-specific sections
            // Initially, keep sections empty and disabled until both program and year are selected.
            try {
                const selInit = document.getElementById('regSections');
                if (selInit) {
                    selInit.innerHTML = '';
                    const ph = document.createElement('option');
                    ph.value = '';
                    ph.text = 'Choose program and year to show sections';
                    ph.disabled = true;
                    ph.selected = true;
                    selInit.appendChild(ph);
                    selInit.disabled = true;
                }
            } catch (e) {}

            // mapping of program full name to abbreviation used for section codes
            const programAbbrev = {
                'Bachelor of Science in Computer Science': 'BSCS',
                'Bachelor of Science in Information Technology': 'BSIT',
                'Bachelor of Science in Engineering': 'BSE',
                'Bachelor of Science in Business Administration': 'BSBA',
                'Bachelor of Arts in Education': 'BAED'
            };

            function generateSectionsFor(programFull, year) {
                const sel = document.getElementById('regSections');
                if (!sel) return;
                // clear existing options
                sel.innerHTML = '';
                // if program or year not selected, keep select disabled and show placeholder
                if (!programFull || !year) {
                    const ph = document.createElement('option');
                    ph.value = '';
                    ph.text = 'Choose program and year to show sections';
                    ph.disabled = true;
                    ph.selected = true;
                    sel.appendChild(ph);
                    sel.disabled = true;
                    return;
                }
                sel.disabled = false;
                const abbrev = programAbbrev[programFull] || programFull.split(' ').map(w => w[0]).join('').toUpperCase();
                const prefix = String(year) + String(year); // e.g., '11' for year=1
                for (let i = 1; i <= 9; i++) {
                    const suffix = i < 10 ? '0' + i : String(i);
                    const code = prefix + suffix; // e.g., 1101
                    const label = abbrev + ' ' + code;
                    const opt = document.createElement('option');
                    opt.value = label;
                    opt.text = label;
                    sel.appendChild(opt);
                }
            }

            // attach program/year listeners
            try {
                const progEl = document.getElementById('regProgram');
                const yearEl = document.getElementById('regYear');
                if (progEl && yearEl) {
                    progEl.addEventListener('change', function() {
                        generateSectionsFor(progEl.value, yearEl.value);
                    });
                    yearEl.addEventListener('change', function() {
                        generateSectionsFor(progEl.value, yearEl.value);
                    });
                }
            } catch (e) {}

            const sidEl = document.getElementById('regSrCode');
            if (!sidEl) return;
            sidEl.addEventListener('input', function() {
                try {
                    const val = (this.value || '').toString().trim();
                    const users = JSON.parse(localStorage.getItem('batstate_users') || '[]');
                    if (val) {
                        let deletedSidLocal = null
                        try {
                            const rawDeletedLocal = sessionStorage.getItem('batstate_deleted_user')
                            if (rawDeletedLocal) {
                                const p = JSON.parse(rawDeletedLocal)
                                deletedSidLocal = (p && p.user && (p.user.sr_code || p.user.studentId || p.user.id)) || null
                            }
                        } catch (e) {
                            deletedSidLocal = null
                        }
                        const foundLocal = users.find(u => ((u.sr_code || u.studentId) || '').toString().trim() === val)
                        if (foundLocal && String((foundLocal.sr_code || foundLocal.studentId || foundLocal.id || '')).trim() !== String(deletedSidLocal || '').trim()) {
                            this.setCustomValidity('SR-CODE already registered');
                            showMessage('SR-CODE already registered', 'error');
                        } else {
                            this.setCustomValidity('');
                            const el = document.getElementById('authMessage');
                            if (el) {
                                el.textContent = '';
                                el.className = 'auth-message';
                            }
                        }
                    } else {
                        this.setCustomValidity('');
                        const el = document.getElementById('authMessage');
                        if (el) {
                            el.textContent = '';
                            el.className = 'auth-message';
                        }
                    }
                } catch (e) {}
            });
            sidEl.addEventListener('blur', function() {
                this.reportValidity();
            });
        })();

        function showMessage(msg, type) {
            const el = document.getElementById('authMessage');
            el.textContent = msg;
            el.className = 'auth-message ' + type;
        }
    </script>
</body>

</html>