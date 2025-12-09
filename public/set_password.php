<?php
session_start();
// Basic security check: ensure a user ID and the unique activation token are present
// This ensures the user came through the initial successful login/activation flow.
if (empty($_SESSION['user_id']) || empty($_SESSION['qr_activation_token'])) {
    // If they bypass the activation flow, redirect them to login
    header('Location: login.php');
    exit();
}

// Prepare the necessary session data for display
$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'Account');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white p-8 md:p-10 shadow-2xl rounded-xl border border-gray-100">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Set Your Password</h1>
        <p class="text-gray-500 mb-8">
            Welcome, <?php echo $displayName; ?>! Please set a secure password and confirm your Unique Access Code to activate your account.
        </p>

        <form id="setPasswordForm" class="space-y-6">

            <div id="messageBox" class="hidden p-3 rounded-lg text-sm font-medium" role="alert"></div>

            <div>
                <label for="access_code" class="block text-sm font-medium text-gray-700 mb-1">Unique Activation Code</label>
                <input type="text" id="access_code" name="access_code" required
                    placeholder="Enter Your Unique Activation Code"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
            </div>

            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                    class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
            </div>

            <button type="submit" id="submitBtn"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out disabled:opacity-50">
                Set Password & Activate
            </button>
        </form>
    </div>

    <script>
        /**
         * Displays a message in the designated message box.
         * @param {string} message The message text.
         * @param {boolean} isSuccess Whether the message is for success or failure.
         */
        function showMessage(message, isSuccess) {
            const messageBox = document.getElementById('messageBox');
            messageBox.textContent = message;
            messageBox.classList.remove('hidden');
            // Remove and add appropriate color classes
            messageBox.classList.remove('bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
            if (isSuccess) {
                messageBox.classList.add('bg-green-100', 'text-green-700');
            } else {
                messageBox.classList.add('bg-red-100', 'text-red-700');
            }
        }

        document.getElementById('setPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const form = e.target;
            const newPassword = form.new_password.value;
            const confirmPassword = form.confirm_password.value;
            const submitBtn = document.getElementById('submitBtn');
            const messageBox = document.getElementById('messageBox');

            // Reset message box
            messageBox.textContent = '';
            messageBox.className = 'hidden p-3 rounded-lg text-sm font-medium';

            // Client-side validation
            if (newPassword !== confirmPassword) {
                showMessage("Passwords do not match.", false);
                return;
            }

            if (newPassword.length < 8) {
                showMessage("Password must be at least 8 characters long.", false);
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Setting Password...';

            const formData = new FormData(form);

            try {
                // Submit to the handler file
                const response = await fetch('backend/set_password_process.php', { // NOTE: Changed to _process.php as per your backend file
                    method: 'POST',
                    body: formData
                });

                // Check for HTTP errors before processing JSON
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, true);
                    // Clear the password fields upon successful submission for security/UX
                    form.reset();

                    // Redirect to the dashboard after a short delay
                    setTimeout(() => {
                        // Use the redirect URL provided by the backend, or a default
                        window.location.href = result.redirect || '/SANROOM/public/dashboard.php';
                    }, 1500);
                } else {
                    showMessage(result.message, false);
                }

            } catch (error) {
                console.error('Error:', error);
                showMessage('An unexpected error occurred. Please try again.', false);
            } finally {
                // Only re-enable the button if the process was NOT successful
                if (!document.getElementById('messageBox').classList.contains('bg-green-100')) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Set Password & Activate';
                }
            }
        });
    </script>
</body>

</html>