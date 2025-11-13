<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SanRoom | Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="logo">
                <img src="assets/img/San.png" alt="SanRoom Logo">
                <p>Find the Right Room, Right on Time</p>
            </div>

            <div class="login-box">
                <h2>Login</h2>
                <p>Sign in to your account.</p>

                <form id="loginForm" method="POST" action="../api/auth.php">
                    <label for="username">Username</label>
                    <input type="email" id="username" name="username" placeholder="example@gmail.com" required>

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="****************" required>

                    <button type="submit" class="btn">Sign In</button>
                </form>
            </div>
        </div>

        <div class="login-right">
            <h1>Hello,<br>Welcome!</h1>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>
