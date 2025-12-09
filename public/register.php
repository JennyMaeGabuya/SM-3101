<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SanRoom | Register</title>
    <link rel="stylesheet" href="assets/css/register.css">
    <style>
        /* QR Modal Styles */
        .qr-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .qr-modal.show {
            display: flex;
        }

        .qr-modal-content {
            background: white;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .qr-modal-content h2 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
        }

        .qr-modal-content p {
            color: #666;
            margin: 10px 0 20px;
        }

        .qr-code-image {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
            margin: 20px 0;
        }

        .qr-code-image img {
            max-width: 300px;
            width: 100%;
            height: auto;
        }

        .qr-modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        .btn-download {
            background-color: #2a9d8f;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .btn-download:hover {
            background-color: #1f7f6f;
        }

        .btn-continue {
            background-color: #457b9d;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .btn-continue:hover {
            background-color: #2d5a7b;
        }
    </style>
</head>

<body>
    <div id="toast-container"></div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="qr-modal">
        <div class="qr-modal-content">
            <h2>Registration Successful! 🎉</h2>
            <p>Scan this QR code to login with your access code</p>
            <div id="qrCodeContainer" class="qr-code-image">
                <!-- QR image will be inserted here -->
            </div>
            <p style="font-size: 12px; color: #999;">Access code never expires</p>
            <div class="qr-modal-buttons">
                <button class="btn-download" id="downloadQRBtn">📥 Download QR Code</button>
                <button class="btn-continue" id="continueLoginBtn">Continue to Login</button>
            </div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <img src="assets/img/San.png" alt="SanRoom Logo">
                <p>Create Your Account</p>
            </div>
            <div class="login-box">
                <h2>Register</h2>
                <p>Fill in your details to create an account.</p>
                <form id="registerForm" method="POST">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="example@gmail.com" required>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="****************" required>
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="****************" required>
                    <button type="submit" class="btn">Register</button>
                    <p class="login-link">Already Have an Account? <a href="index.php">Sign In</a></p>
                </form>
            </div>
        </div>
        <div class="login-right">
            <h1>Join Us<br>Today!</h1>
        </div>
    </div>

    <script>
        document.getElementById("registerForm").addEventListener("submit", function(e) {
            e.preventDefault();

            let email = document.getElementById("email").value.trim();
            let password = document.getElementById("password").value.trim();
            let confirm = document.getElementById("confirm_password").value.trim();

            if (password !== confirm) {
                showToast("Passwords do not match!", "error");
                return;
            }

            let formData = new FormData();
            formData.append("email", email);
            formData.append("password", password);
            formData.append("confirm_password", confirm);

            fetch("backend/register_process.php", {
                    method: "POST",
                    body: formData,
                })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(text => {
                            console.error("Non-JSON Response Received:", text);
                            throw new Error("Server returned an error response (not valid JSON). Check console for details.");
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    showToast(data.message, data.status);
                    if (data.status === "success") {
                        // Show QR Modal
                        const qrModal = document.getElementById("qrModal");
                        const qrCodeContainer = document.getElementById("qrCodeContainer");
                        qrCodeContainer.innerHTML = `<img src="${data.qr_url}" alt="QR Code" id="qrImage" style="width: 280px; height: 280px;">`;
                        qrModal.classList.add("show");

                        // Store QR URL for download
                        window.currentQRUrl = data.qr_url;
                        window.userEmail = email;
                    }
                })
                .catch(err => {
                    console.error("Fetch error:", err);
                    showToast("Server communication error. See console for details.", "error");
                });
        });

        // Download QR Code
        document.getElementById("downloadQRBtn").addEventListener("click", function() {
            const qrUrl = window.currentQRUrl;
            const email = window.userEmail;

            if (!qrUrl) {
                showToast("QR code not available.", "error");
                return;
            }

            // Create a link element and trigger download
            const link = document.createElement("a");
            link.href = qrUrl;
            link.download = `QR_Code_${email.split("@")[0]}_${new Date().getTime()}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showToast("QR code downloaded!", "success");
        });

        // Continue to Login
        document.getElementById("continueLoginBtn").addEventListener("click", function() {
            window.location.href = "index.php";
        });

        function showToast(message, type = "info") {
            let toast = document.createElement("div");
            toast.className = "toast";
            if (type === "error") toast.style.background = "#e63946";
            if (type === "success") toast.style.background = "#2a9d8f";
            toast.textContent = message;

            const toastContainer = document.getElementById("toast-container");
            if (toastContainer) {
                toastContainer.appendChild(toast);
            } else {
                document.body.appendChild(toast);
            }

            setTimeout(() => toast.classList.add("show"), 100);
            setTimeout(() => {
                toast.classList.remove("show");
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }
    </script>
</body>

</html>