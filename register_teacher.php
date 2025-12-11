
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BatStateU - Teacher Registration</title>
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

    .form-help {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: #666;
    }
</style>

<body class="auth-page">
    <div class="gx-orb gx-orb--soft" style="right:-80px; top:-60px; background:var(--gx-orb-1);"></div>
    <div class="gx-orb gx-orb--small" style="left:-60px; bottom:-40px; background:var(--gx-orb-2);"></div>

    <div class="auth-container">
        <div class="auth-left">
            <div class="auth-brand">
                <div class="brand-logo">
                    <img src="assets/BatStateU-NEU-Logo-1-300x282.png" alt="BatStateU logo" class="brand-logo-img">
                </div>
                <h1>BatStateU - LearnHub</h1>
                <p>Teacher Portal</p>
            </div>
            <div class="auth-welcome">
                <h2>Join Our Faculty</h2>
                <p>Create your teacher account to manage courses and students</p>
            </div>
        </div>

        <div class="auth-right">
            <form id="registerForm" class="auth-form">
                <div class="form-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Create Teacher Account</h2>
                    <button type="button" class="btn-close btn-create-teacher" onclick="window.location.href='register.php';">
                        Create Student Account ➥
                    </button>
                </div>

                <div class="form-group">
                    <label for="regFullName">Full Name</label>
                    <input type="text" id="regFullName" placeholder="Juan Dela Cruz" required>
                </div>

                <div class="form-group">
                    <label for="regSrCode">SR-CODE (Employee ID)</label>
                    <input type="text" id="regSrCode" placeholder="00-00000" pattern="\d{2}-\d{5}"
                        title="Format: 00-00000 (two digits, dash, five digits)" maxlength="8" required>
                    <small class="form-help">Format: XX-XXXXX (e.g., 24-12345)</small>
                </div>

                <div class="form-group">
                    <label for="regEmail">Email Address</label>
                    <input type="email" id="regEmail" placeholder="teacher@batstateu.edu.ph" required>
                </div>

                <div class="form-group">
                    <label for="regDepartment">Department</label>
                    <select id="regDepartment" required>
                        <option value="">Select your department</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Business Administration">Business Administration</option>
                        <option value="Education">Education</option>
                        <option value="Arts and Sciences">Arts and Sciences</option>
                        <option value="Accountancy">Accountancy</option>
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
            const button = field.nextElementSibling;
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
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

        // Password strength indicator
        document.getElementById('regPassword').addEventListener('input', function() {
            const strength = getPasswordStrength(this.value);
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');

            const strengthLevels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong', 'Excellent'];
            const colors = ['#c41e3a', '#ff9800', '#ffeb3b', '#8bc34a', '#4caf50', '#009688'];

            bar.style.width = (strength * 16.67) + '%';
            bar.style.backgroundColor = colors[strength - 1] || '#ccc';
            text.textContent = 'Password strength: ' + (strengthLevels[strength - 1] || 'Too weak');
            text.style.color = colors[strength - 1] || '#ccc';
        });

        // Real-time SR-CODE validation
        let srCodeTimeout;
        document.getElementById('regSrCode').addEventListener('input', function() {
            clearTimeout(srCodeTimeout);
            const val = this.value.trim();

            if (!val) {
                this.setCustomValidity('');
                return;
            }

            // Check format first
            if (!/^\d{2}-\d{5}$/.test(val)) {
                this.setCustomValidity('Invalid format. Use: 00-00000');
                return;
            }

            // Check if already exists
            srCodeTimeout = setTimeout(() => {
                fetch('register_teacher_action.php?check_username=' + encodeURIComponent(val))
                    .then(r => r.json())
                    .then(data => {
                        if (data.exists) {
                            this.setCustomValidity('SR-CODE already registered');
                            showMessage('SR-CODE already registered', 'error');
                        } else {
                            this.setCustomValidity('');
                            const el = document.getElementById('authMessage');
                            if (el && el.classList.contains('error')) {
                                el.textContent = '';
                                el.className = 'auth-message';
                            }
                        }
                    })
                    .catch(() => {
                        this.setCustomValidity('');
                    });
            }, 500);
        });

        // Real-time email validation
        let emailTimeout;
        document.getElementById('regEmail').addEventListener('input', function() {
            clearTimeout(emailTimeout);
            const val = this.value.trim();

            if (!val || !this.validity.valid) {
                return;
            }

            emailTimeout = setTimeout(() => {
                fetch('register_teacher_action.php?check_email=' + encodeURIComponent(val))
                    .then(r => r.json())
                    .then(data => {
                        if (data.exists) {
                            this.setCustomValidity('Email already registered');
                            showMessage('Email already registered', 'error');
                        } else {
                            this.setCustomValidity('');
                            const el = document.getElementById('authMessage');
                            if (el && el.classList.contains('error')) {
                                el.textContent = '';
                                el.className = 'auth-message';
                            }
                        }
                    })
                    .catch(() => {
                        this.setCustomValidity('');
                    });
            }, 500);
        });

        // Form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const fullName = document.getElementById('regFullName').value.trim();
            const srCode = document.getElementById('regSrCode').value.trim();
            const email = document.getElementById('regEmail').value.trim();
            const department = document.getElementById('regDepartment').value;
            const password = document.getElementById('regPassword').value;
            const confirmPassword = document.getElementById('regConfirmPassword').value;
            const agreeTerms = document.getElementById('agreeTerms').checked;

            // Validation
            if (!fullName) {
                showMessage('Please enter your full name', 'error');
                return;
            }

            if (!srCode || !/^\d{2}-\d{5}$/.test(srCode)) {
                showMessage('Invalid SR-CODE format. Use: 00-00000', 'error');
                return;
            }

            if (!email || !email.includes('@')) {
                showMessage('Please enter a valid email address', 'error');
                return;
            }

            if (!department) {
                showMessage('Please select your department', 'error');
                return;
            }

            if (password.length < 8) {
                showMessage('Password must be at least 8 characters', 'error');
                return;
            }

            if (password !== confirmPassword) {
                showMessage('Passwords do not match', 'error');
                return;
            }

            if (!agreeTerms) {
                showMessage('Please agree to the Terms of Service', 'error');
                return;
            }

            // Prepare data
            const newTeacher = {
                name: fullName,
                username: srCode,
                email: email,
                password: password,
                department: department
            };

            // Disable submit button
            const submitBtn = this.querySelector('.btn-register');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            // Submit to backend
            fetch('register_teacher_action.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(newTeacher)
                })
                .then(r => r.json())
                .then(res => {
                    if (res && res.success) {
                        showMessage('Account created successfully! Logging you in...', 'success');

                        // Redirect to login.php after 1.5 seconds
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 1500);
                    } else {
                        showMessage(res.message || 'Registration failed. Please try again.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                })
                .catch(err => {
                    console.error('Registration error:', err);
                    showMessage('Server error. Please try again later.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
        });

        function showMessage(msg, type) {
            const el = document.getElementById('authMessage');
            el.textContent = msg;
            el.className = 'auth-message ' + type;

            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    el.textContent = '';
                    el.className = 'auth-message';
                }, 5000);
            }
        }
    </script>
</body>

</html>