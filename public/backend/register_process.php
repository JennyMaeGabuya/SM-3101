<?php
// Script: register_process.php
// Purpose: Registers a new user, hashes the activation code for secure storage in 'users', 
//          and saves the plain-text code to 'login_access_codes' for tracking.

// Start output buffering and set headers
ob_start();
header("Content-Type: application/json");

// Error reporting setup - set to 0 for production to hide errors from users
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = [];
$conn = null; // Initialize connection variable

// --- HELPER FUNCTION: Generate Short Activation Code (6 chars, uppercase/numeric) ---
/**
 * Generates a cryptographically secure, random short code.
 * @param int $length The desired length of the code.
 * @return string The generated code.
 */
function generateShortActivationCode(int $length = 6): string
{
    // Use only uppercase letters and numbers
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($characters) - 1;

    if ($length < 1) {
        return "";
    }

    // Use random_int for cryptographically secure randomness
    for ($i = 0; $i < $length; $i++) {
        try {
            $code .= $characters[random_int(0, $max)];
        } catch (Exception $e) {
            // Fallback for extreme low-resource environments, though random_int should work
            return "";
        }
    }
    return $code;
}

// --- HELPER FUNCTION: Generate QR code using qrserver API ---
/**
 * Generates and saves a QR code image locally by calling an external API.
 * ⭐ UPDATED: Now accepts the activation code.
 * @param string $email The user's email.
 * @param string $token The unique QR token.
 * @param string $activationCode The user's plain-text activation code.
 * @return string The local path to the QR image file or the URL if saving fails.
 */
function generateQRCode($email, $token, $activationCode)
{
    $qrDir = __DIR__ . "/../qrcodes/";
    if (!is_dir($qrDir)) {
        // Fallback QR data for directory creation failure
        $qrDataFallback = "Email: " . trim($email) . "| Code: " . trim($activationCode) . "| Token:" . trim($token);
        return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrDataFallback);
    }

    // ⭐ CRITICAL CHANGE: Encode data with proper labels (Email:XX|Code:YY|Token:ZZ)
    $qrData = "Email:" . trim($email) . "|Code:" . trim($activationCode) . "|Token:" . trim($token);

    // Create a unique filename
    $filename = hash('sha256', $qrData . microtime(true)) . '.png';
    $qrPath = $qrDir . $filename;

    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);

    // Suppress warnings with @ for file_get_contents on external URL failure
    $qrImage = @file_get_contents($qrUrl);
    if ($qrImage !== false) {
        if (file_put_contents($qrPath, $qrImage)) {
            return "qrcodes/" . $filename; // Return local path
        }
    }
    // Return the external URL if saving locally fails
    return $qrUrl;
}

// --- SAFE EXECUTION BLOCK ---
try {
    // --- 1. LOAD DEPENDENCIES AND CHECK CONNECTION ---
    include __DIR__ . "/../../database.php";

    if (!isset($conn) || $conn->connect_error) {
        // Explicit check for a failed connection
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? "Connection object missing."));
    }

    // --- 2. INPUT DATA & VALIDATION ---
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role = "student"; // Hardcoded role for self-registration - SECURE

    if (!$email || !$password || !$confirm_password) {
        throw new Exception("All fields are required.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }
    if (strlen($password) < 8) {
        throw new Exception("Password must be at least 8 characters.");
    }

    // --- 3. CHECK IF EMAIL EXISTS ---
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$check) {
        throw new Exception("Database prepare error (check): " . $conn->error);
    }
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        throw new Exception("Email already registered.");
    }
    $check->close();

    // --- 4. GENERATE TOKENS ---
    $plainActivationCode = generateShortActivationCode(6);
    if (empty($plainActivationCode)) {
        throw new Exception("Activation code generation failed. Cannot proceed with registration.");
    }

    // ⭐ FIX: Hash the plain activation code for SECURE storage in the 'users' table.
    $hashedActivationCode = password_hash($plainActivationCode, PASSWORD_DEFAULT);

    // Generate QR Login Token
    $qrChars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $qrToken = "";
    for ($i = 0; $i < 12; $i++) {
        $qrToken .= $qrChars[random_int(0, strlen($qrChars) - 1)];
    }

    // --- 5. INSERT USER (Saves to 'users' table) ---
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $isActivated = 0;

    $stmt = $conn->prepare(
        "INSERT INTO users 
        (email, password, role, activation_code, is_activated, qr_token) 
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Database prepare error (insert user): " . $conn->error);
    }

    // NOTE: activation_code is bound to the HASHED code for security.
    // Correct types: email(s), password(s), role(s), activation_code(s), is_activated(i), qr_token(s)
    $stmt->bind_param("ssssis", $email, $hashedPassword, $role, $hashedActivationCode, $isActivated, $qrToken);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save user. MySQL Error: " . $stmt->error);
    }

    $userId = $stmt->insert_id;
    $stmt->close();

    // --- 6. SAVE UNIQUE ACTIVATION CODE (Saves PLAIN CODE to 'login_access_codes' tracking table) ---
    // This table holds the plain-text code which is given to the user.
    $insertCode = $conn->prepare(
        "INSERT INTO login_access_codes (user_id, role, code) VALUES (?, ?, ?)"
    );

    if (!$insertCode) {
        throw new Exception("Database prepare error (insert code): " . $conn->error);
    }

    // The plain code is used here for tracking/logging.
    $insertCode->bind_param("iss", $userId, $role, $plainActivationCode);

    if (!$insertCode->execute()) {
        throw new Exception("Failed to save activation code to tracking table. MySQL Error: " . $insertCode->error);
    }
    $insertCode->close();

    // --- 7. QR CODE GENERATION ---
    // ⭐ UPDATED CALL: Pass the plain-text activation code
    $qrUrl = generateQRCode($email, $qrToken, $plainActivationCode);
    if (empty($qrUrl)) {
        throw new Exception("Failed to generate QR code or URL");
    }

    // --- 8. SUCCESS RESPONSE ---
    $response = [
        "status" => "success",
        "message" => "Registration successful! Save this QR code for login and use the code below for activation.",
        "activation_code" => $plainActivationCode, // Send PLAIN TEXT code to user
        "qr_token" => $qrToken,
        "qr_url" => $qrUrl,
        "note" => "The activation code is now securely hashed in the primary users table. Remember to use password_verify() to check it."
    ];
} catch (Throwable $e) {
    // Catch any exceptions thrown during the process
    $response = [
        "status" => "error",
        "message" => $e->getMessage()
    ];
} finally {
    // Close the connection if it was opened
    if ($conn !== null && $conn instanceof mysqli) {
        $conn->close();
    }
}

// --- CLEAN ACCIDENTAL OUTPUT ---
$extraOutput = trim(ob_get_clean());
if (!empty($extraOutput)) {
    // If an error occurred AND there's extra output, append it to the error message
    if (isset($response['status']) && $response['status'] === 'error') {
        $response["message"] .= " [DEBUG: Extra output detected: " . $extraOutput . "]";
    } else {
        // If successful, log the unexpected output in a debug field
        $response["debug_output"] = $extraOutput;
    }
}

// Final output as JSON
echo json_encode($response);
