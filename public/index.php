<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SanRoom | Login</title>
    <!-- Now links to login.css which contains ALL styles including the centered toast -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <div id="toast-container"></div>

    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <img src="assets/img/San.png" alt="SanRoom Logo">
                <p>Find the Right Room, Right on Time</p>
            </div>

            <div class="login-box">
                <h2>Login</h2>
                <p>Sign in to your account.</p>

                <form id="loginForm" method="POST">
                    <label for="username">Email</label>
                    <input type="email" id="username" name="username" placeholder="example@gmail.com" required>

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="****************" required>

                    <label for="access_code">Enter Your Qr Token</label>
                    <input
                        type="text"
                        id="access_code"
                        name="access_code"
                        placeholder="Input Appropriate Qr Token"
                        required>

                    <button type="button" id="qrLoginBtn" class="btn">Login with QR</button>
                    <input type="file" id="qrFileInput" accept="image/*" style="display:none;" />


                    <button type="submit" class="btn">Sign In</button>
                    <p class="signup-link">
                        Don't Have an Account? <a href="register.php">Sign Up</a>
                    </p>
                </form>
            </div>
        </div>

        <div class="login-right">
            <h1>Hello,<br>Welcome!</h1>
        </div>
    </div>

    <script>
        /**
         * Function to display a toast notification
         * @param {string} message The text to display in the toast.
         * @param {number} duration The time in milliseconds the toast should be visible.
         */
        function showToast(message, duration = 3000) {
            const container = document.getElementById('toast-container');
            if (!container) return; // Exit if container doesn't exist

            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = message;

            // Since the toast itself handles its position (fixed top/left/translate)
            // we can just append it to the container. The container is now mostly a wrapper.
            container.appendChild(toast);

            // Force reflow to ensure CSS transition runs
            void toast.offsetWidth;

            toast.classList.add('show');

            // Hide and remove the toast after duration
            setTimeout(() => {
                toast.classList.remove('show');
                // Wait for the transition to finish before removing from DOM
                setTimeout(() => {
                    if (container.contains(toast)) {
                        container.removeChild(toast);
                    }
                }, 500); // Must match CSS transition duration
            }, duration);
        }

        // --- Key Sequence Easter Egg (M O D E -> Redirects to Moderator/) ---
        const keyEasterEggCode = ['M', 'O', 'D', 'E'];
        let keyPosition = 0;

        document.addEventListener('keydown', (e) => {
            const key = e.key.toUpperCase();

            if (key === keyEasterEggCode[keyPosition]) {
                keyPosition++;

                if (keyPosition === keyEasterEggCode.length) {
                    // 1. Show the cool "Super Admin Activated" toast!
                    showToast('🎉 Super Admin Activated! Redirecting...', 2500);

                    // 2. Wait a moment (for the user to see the toast) before redirecting
                    setTimeout(() => {
                        window.location.href = 'Moderator/';
                    }, 2800);

                    keyPosition = 0;
                }
            } else {
                keyPosition = 0;
            }
        });

        // --- Click Easter Egg (Redirects to login.php) ---
        document.addEventListener("DOMContentLoaded", () => {
            const loginTitle = document.querySelector(".login-box h2");

            let clickCount = 0;
            let clickTimer = null;
            const requiredClicks = 3;
            const timeoutDuration = 1200; // 1.2 seconds

            loginTitle.style.cursor = "default";

            loginTitle.addEventListener("click", () => {
                clickCount++;

                if (clickTimer) clearTimeout(clickTimer);

                clickTimer = setTimeout(() => {
                    clickCount = 0;
                }, timeoutDuration);

                if (clickCount === requiredClicks) {
                    // 1. Show a different toast for the click trigger
                    showToast('Secret Login Access Granted!', 2000);

                    // 2. Wait a moment (for the user to see the toast) before redirecting
                    setTimeout(() => {
                        window.location.href = "Moderator/login.php";
                    }, 2300);

                    clickCount = 0; // Reset
                }
            });
        });
    </script>
</body>
<script src="assets/js/login.js?v=2"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr/dist/jsQR.js"></script>

</html>