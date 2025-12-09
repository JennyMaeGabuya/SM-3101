<?php

session_start();
error_reporting(0); // Suppress warnings/notices

// --- 1. FUNCTION DEFINITIONS (Copied from login.php for consistency) ---

/**
 * Sends a JSON response and terminates the script.
 * @param bool $success Status.
 * @param string $message Message for the toast/UI.
 * @param string|null $role User role.
 * @param string|null $redirect Target page URL.
 */
function sendResponse($success, $message, $role = null, $redirect = null)
{
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

// --- 2. CONFIGURATION AND AUTHORIZATION ---

require_once __DIR__ . "/../../database.php";

// Check if the user is authorized to be here (logged in from the activation step)
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    sendResponse(false, "Authorization failed. Please log in again.", null, '/SANROOM/public/set_password.php');
}

$userId = $_SESSION['user_id'];
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

// --- 3. INPUT VALIDATION ---

if (empty($newPassword) || empty($confirmPassword)) {
    sendResponse(false, "New password and confirmation are required.");
}

if ($newPassword !== $confirmPassword) {
    sendResponse(false, "Passwords do not match.");
}

if (strlen($newPassword) < 8) {
    sendResponse(false, "Password must be at least 8 characters long.");
}

// --- 4. HASH PASSWORD AND UPDATE DATABASE ---

$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the user's password and set is_activated to 1 (activated). Keep activation_code intact.
$updateStmt = $conn->prepare(
    "UPDATE users SET password = ?, is_activated = 1 WHERE id = ? AND role = 'teacher' AND is_activated = 0"
);

if (!$updateStmt) {
    sendResponse(false, "Database preparation error during update.");
}

$updateStmt->bind_param("si", $hashedPassword, $userId);

if (!$updateStmt->execute()) {
    // Check if the update actually affected any rows
    if ($updateStmt->affected_rows === 0) {
        // This likely means the user was already activated or the ID/Role didn't match.
        // We can treat this as an already completed step or an authentication issue.
        sendResponse(false, "Account is already activated or user not found. Please proceed to login.", null, '/SANROOM/public/login.php');
    }
    sendResponse(false, "Failed to update password. Please contact support.");
}

// --- 5. FINALIZATION AND REDIRECTION ---

// Update the session state now that the account is fully activated
// No need to change the session variables as they were set by login.php, 
// but we confirm success and redirect to the final destination.

$redirectURL = '/SANROOM/public/dashboard.php';
$welcomeMessage = "Success! Your password is set. Welcome, " . ($_SESSION['display_name'] ?? 'Teacher') . ".";

sendResponse(true, $welcomeMessage, $_SESSION['role'], $redirectURL);
