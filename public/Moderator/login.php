<?php
// Start the session to manage login state.
session_start();

// Include the database connection file
require_once(__DIR__ . "/../../database.php");

$error_message = "";
$username = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = null;
    $user_type = 'super_admin';

    // --- Attempt login against super_admins table ---
    $sql_admin = "SELECT password_hash FROM super_admins WHERE username = ?";

    if ($stmt_admin = $conn->prepare($sql_admin)) {
        $stmt_admin->bind_param("s", $username);

        if ($stmt_admin->execute()) {
            $result_admin = $stmt_admin->get_result();

            if ($result_admin && $result_admin->num_rows === 1) {
                $user = $result_admin->fetch_assoc();
            }
        } else {
            // SQL execution error: log only
            error_log("SQL Execution Error: " . $stmt_admin->error);
        }

        $stmt_admin->close();
    } else {
        // SQL prepare error: log only
        error_log("SQL Prepare Error: " . $conn->error);
    }

    // --- Verify password if user exists ---
    if ($user !== null && password_verify($password, $user['password_hash'])) {

        $_SESSION['loggedin'] = true;
        $_SESSION['user_type'] = $user_type;
        $_SESSION['username'] = $username;

        $_SESSION['toast_message'] = "Login successful! Welcome, " . htmlspecialchars($username) . ".";
        $_SESSION['toast_type'] = "success";

        header("Location: /SANROOM/public/Moderator/Super_admin_dashboard.php");
        exit;
    } elseif (!empty($username) && !empty($password)) {
        // Only show invalid credentials if the user submitted both fields
        $error_message = "Invalid credentials.";
    }
}

// Close connection
if (isset($conn)) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login</title>
    <link rel="stylesheet" href="../assets/css/Moderator.css">
    <style>
        /* Simple styling for the exit link to match the context */
        .exit-link {
            display: block;
            margin-top: 15px;
            text-align: center;
            color: #1e40af;
            /* Assuming a primary blue color */
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.2s;
        }

        .exit-link:hover {
            color: #374151;
            /* Darker hover color */
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-center">
            <div class="login-box">
                <h2>Super Admin Access Point</h2>
                <p>Please enter your credentials.</p>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form id="loginForm" method="POST">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter Username" required value="<?php echo htmlspecialchars($username); ?>">

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter Password" required>

                    <button type="submit" class="btn">Sign In</button>
                </form>

                <a href="../index.php" class="exit-link">← Return to Main Page</a>

            </div>
        </div>
    </div>

</body>
<script src="../Moderator/Moderator.js?v=2"></script>

</html>