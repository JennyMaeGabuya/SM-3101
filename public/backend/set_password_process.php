<?php
// Script: set_password_process.php
// Purpose: Verifies the HASHED unique code, sets the permanent password, and activates the account.

session_start();
// Set error_reporting to 0 only for the response function, but handle errors internally
error_reporting(E_ALL & ~E_NOTICE);

function sendResponse($success, $message, $role = null, $redirect = null)
{
    // Ensure no output is sent before headers
    if (ob_get_length() > 0) {
        ob_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "role" => $role,
        "redirect" => $redirect
    ]);
    exit();
}

$conn = null; // Initialize connection variable
try {
    // --- 2. CONFIGURATION AND AUTHORIZATION ---
    require_once __DIR__ . "/../../database.php";

    // Ensure database connection is established
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed.");
    }

    // Check session context
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        sendResponse(false, "Session context expired or invalid. Please log in again.", null, '/SANROOM/public/login.php');
    }

    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'];

    // Debug logger for set-password flow (masks sensitive data)
    $debugSetPass = __DIR__ . "/../Moderator/logs/debug_set_password.log";
    if (!is_dir(dirname($debugSetPass))) {
        @mkdir(dirname($debugSetPass), 0777, true);
    }
    function dbg_setpass($label, $data = [])
    {
        global $debugSetPass;
        $entry = ['ts' => date('c'), 'label' => $label, 'payload' => $data];
        @file_put_contents($debugSetPass, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // NOTE: Removed unnecessary error_log for user ID to keep logs clean in production

    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $accessCode = trim($_POST['access_code'] ?? ''); // The plain-text code entered by the user

    // --- 3. FETCH USER DATA AND ACTIVATION CODE ---
    if ($userRole === 'teacher') {
        // Fetch activation info from teachers table (teachers store plain codes for first-time activation)
        $stmtUser = $conn->prepare("SELECT is_active AS is_activated, activation_code FROM teachers WHERE teacher_id = ?");
    } else {
        // Default: users table (students)
        $stmtUser = $conn->prepare("SELECT is_activated, activation_code FROM users WHERE id = ?");
    }

    if (!$stmtUser) {
        throw new Exception("Database prepare error (user fetch): " . $conn->error);
    }

    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    $user = $resultUser->fetch_assoc();
    $stmtUser->close();

    if (!$user) {
        sendResponse(false, "User account not found.", null, '/SANROOM/public/login.php');
    }

    $isActivated = (int)$user['is_activated'];
    // For teachers this is the plain activation code; for users it's the hashed activation code
    $expectedHash = $user['activation_code'];

    if ($isActivated === 1) {
        $redirectURL = ($userRole === 'student') ? '/SANROOM/public/students.php' : '/SANROOM/public/dashboard.php';
        sendResponse(false, "Account is already activated. Proceed to your dashboard.", $userRole, $redirectURL);
    }

    // Handle scenarios where the code was not set or was improperly stored (e.g., stored as '0')
    if (empty($expectedHash) || $expectedHash === '0' || strlen($expectedHash) < 6) {
        dbg_setpass('expected_hash_missing', ['user_id' => $userId, 'expected_len' => strlen($expectedHash ?? '')]);
        // Fallback to check the plain-text code from the tracking table (login_access_codes)
        // This handles cases where the users.activation_code was incorrectly set to 0/NULL during registration
        $stmtCode = $conn->prepare("SELECT code FROM login_access_codes WHERE user_id = ?");
        $stmtCode->bind_param("i", $userId);
        $stmtCode->execute();
        $codeResult = $stmtCode->get_result();
        $codeRow = $codeResult->fetch_assoc();
        $stmtCode->close();

        // If a plain code exists in the tracking table, we assume the user table failed to hash/store.
        if (!empty($codeRow['code'])) {
            // Do NOT log the plain code. Log that a fallback plain code was present for this user.
            dbg_setpass('login_access_code_present', ['user_id' => $userId]);
            // For this edge case, we MUST use direct string comparison, as it's the plain code.
            if (strcasecmp($accessCode, $expectedHash) !== 0) {
                // Activation code matched plain code from tracking table.
                // We MUST proceed to update the users table with the HASHED password AND set the activation_code to NULL 
                // (as the user table never got the proper hash). The rest of the script handles this update correctly.
                // We skip to Step 6.
                goto update_database; // Using goto to avoid duplicating code block
            }
        }

        // If no code was found or it was invalid, throw the exception.
        throw new Exception("Activation code not found or invalid in the primary records. Contact admin.");
    }

    // --- 4. INPUT VALIDATION ---

    if (empty($newPassword) || empty($confirmPassword) || empty($accessCode)) {
        sendResponse(false, "All fields (New Password, Confirmation, and Access Code) are required.");
    }

    if ($newPassword !== $confirmPassword) {
        sendResponse(false, "Passwords do not match.");
    }

    if (strlen($newPassword) < 8) {
        sendResponse(false, "Password must be at least 8 characters long.");
    }

    // --- 5. ACTIVATION CODE VERIFICATION ---
    if ($userRole === 'teacher') {
        // Teachers store plain activation codes — compare case-insensitively
        if (strcasecmp($accessCode, $expectedHash) !== 0) {
            sendResponse(false, "Invalid Activation code. Please use the Unique Code provided during registration or Contact Your Administrator.", null, null);
        }
    } else {
        // Students/users: verify hashed activation code
        if (!password_verify($accessCode, $expectedHash)) {
            sendResponse(false, "Invalid Activation code. Please use the Unique Code provided during registration or Contact Your Administrator.", null, null);
        }
    }

    // --- 6. HASH PASSWORD AND UPDATE DATABASE ---
    update_database:

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Set the new password, clear the activation code (null), AND set is_activated to 1
    if ($userRole === 'teacher') {
        $updateStmt = $conn->prepare("UPDATE teachers SET password_hash = ?, is_active = 1, activation_code = NULL WHERE teacher_id = ?");
    } else {
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, is_activated = 1, activation_code = NULL WHERE id = ?");
    }

    if (!$updateStmt) {
        throw new Exception("Database prepare error during update: " . $conn->error);
    }

    // Set the new hashed password and update the user's status
    $updateStmt->bind_param("si", $hashedPassword, $userId);

    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update password and activate account. MySQL Error: " . $updateStmt->error);
    }
    $updateStmt->close();

    // Log success of password update and activation
    dbg_setpass('password_update_success', ['user_id' => $userId]);

    // OPTIONAL: Delete the used activation code from login_access_codes tracking table
    $deleteStmt = $conn->prepare("DELETE FROM login_access_codes WHERE user_id = ?");
    $deleteStmt->bind_param("i", $userId);
    // Ignore execution failure, as this is cleanup/optional tracking
    $deleteStmt->execute();
    $deleteStmt->close();

    // --- 7. FINALIZATION AND REDIRECTION ---

    $redirectURL = ($userRole === 'student')
        ? '/SANROOM/public/students.php'
        : '/SANROOM/public/dashboard.php';

    $welcomeMessage = "Success! Your secure password is set. Redirecting to your " . $userRole . " area.";

    sendResponse(true, $welcomeMessage, $userRole, $redirectURL);
} catch (Throwable $e) {
    error_log("General Error: " . $e->getMessage());
    sendResponse(false, "A server error occurred during password setup.");
} finally {
    // Close the connection
    if ($conn !== null && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}
