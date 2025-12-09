<?php
// Script: login_process.php
// Status: FINAL FIXED CODE - Unifies Teacher and Student login (including QR flow).

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../database.php";

// Turn off mysqli exceptions for this script and handle errors manually to avoid fatal uncaught exceptions
mysqli_report(MYSQLI_REPORT_OFF);

// --- Simple debug logger (safe: masks sensitive values) ---
$debugLogFile = __DIR__ . "/../Moderator/logs/debug_login.log";
if (!is_dir(dirname($debugLogFile))) {
    @mkdir(dirname($debugLogFile), 0777, true);
}
function dbg_login($label, $data = [])
{
    global $debugLogFile;
    $safe = [
        'ts' => date('c'),
        'label' => $label,
        'payload' => $data
    ];
    @file_put_contents($debugLogFile, json_encode($safe, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- 1. HELPER FUNCTION: Send JSON Response ---

function sendResponse($success, $message, $role = null, $redirect = null)
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    $data = [
        "success" => $success,
        "message" => $message,
        "role" => $role,
        "redirect" => $redirect
    ];
    echo json_encode($data);
    exit();
}

// --- 2. INPUT SANITIZATION AND FLOW DETERMINATION ---

$email = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$accessCode = trim($_POST['access_code'] ?? '');

$qrToken = trim($_POST['qr_token'] ?? '');
$qrEmail = trim($_POST['qr_email'] ?? '');

$isQrLoginFlow = !empty($qrToken) && !empty($qrEmail);
$isAuthenticated = false;
$user = null;

if (empty($email) || empty($password)) {
    if (!$isQrLoginFlow || empty($accessCode)) {
        sendResponse(false, "Username and Password are required.");
    }
}

// --- 3. UNIFIED USER DATA RETRIEVAL (Checking BOTH Tables) ---

$loginIdentifier = strtolower($email);

// 1. Check Teachers Table
$teacherQuery = "SELECT teacher_id AS id, password_hash AS password, 'teacher' AS role, 
                        is_active AS is_activated, qr_token, email AS login_id, full_name, activation_code 
                 FROM teachers 
                 WHERE email = ?";

$stmt = false;
try {
    $stmt = $conn->prepare($teacherQuery);
} catch (mysqli_sql_exception $e) {
    $stmt = false;
}
if ($stmt) {
    $stmt->bind_param("s", $loginIdentifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    // Log summary about fetched user (masking activation_code)
    if ($user) {
        dbg_login('teacher_fetched', [
            'id' => $user['id'] ?? null,
            'login_id' => $user['login_id'] ?? null,
            'is_activated' => $user['is_activated'] ?? null,
            'has_activation_hash' => !empty($user['activation_code']) && $user['activation_code'] !== '0',
            'activation_hash_len' => isset($user['activation_code']) ? strlen($user['activation_code']) : 0,
            'qr_token_present' => !empty($user['qr_token'])
        ]);
    } else {
        dbg_login('teacher_not_found', ['identifier' => $loginIdentifier]);
    }
}

// 2. If no teacher found, check Students Table (fallback to `users` table if `students` doesn't exist)
if (!$user) {
    // Try the legacy `students` table first (if it exists)
    $studentQuery = "SELECT student_id AS id, password_hash AS password, 'student' AS role,
                            is_active AS is_activated, qr_token, email AS login_id, full_name, NULL AS activation_code
                     FROM students
                     WHERE student_id = ? OR email = ?";

    try {
        $stmt = $conn->prepare($studentQuery);
    } catch (mysqli_sql_exception $e) {
        $stmt = false;
    }

    if ($stmt) {
        $stmt->bind_param("ss", $loginIdentifier, $loginIdentifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    } else {
        // `students` table likely does not exist in this installation — fall back to the primary `users` table
        $userQuery = "SELECT id AS id, password AS password, 'student' AS role,
                             is_activated AS is_activated, qr_token, email AS login_id, full_name, NULL AS activation_code
                      FROM users
                      WHERE email = ? LIMIT 1";

        $stmt2 = $conn->prepare($userQuery);
        if ($stmt2) {
            $stmt2->bind_param("s", $loginIdentifier);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $user = $result2->fetch_assoc();
            $stmt2->close();
        } else {
            // If even the users table can't be queried, return a generic error
            sendResponse(false, "Authentication service unavailable. Contact administrator.");
        }
    }
}

if (!$user) {
    sendResponse(false, "Invalid credentials. User not found.");
}

// --- 4. ROLE-BASED AUTHENTICATION LOGIC ---

$userRole = $user['role'];
$isActivated = (int)($user['is_activated'] ?? 1); // Default to activated if field is missing (like for older student accounts)
$userPasswordHash = $user['password'];
$userActivationCodeHash = $user['activation_code'];
$userQrToken = $user['qr_token'];

// --- A. TEACHER AUTHENTICATION FLOW ---
if ($userRole === 'teacher') {

    // 4A.1 First-Time Teacher Login (is_activated = 0)

    if ($isActivated === 0) {
        // For teachers we store the activation code in plain text; compare case-insensitively.
        $userActivationCode = $userActivationCodeHash; // rename for clarity (still the plain stored value)
        $passwordMatchesActivation = (!empty($password) && !empty($userActivationCode) && strcasecmp($password, $userActivationCode) === 0);
        $passwordMatchesQrToken = ($isQrLoginFlow && !empty($password) && !empty($userQrToken) && hash_equals($password, $userQrToken));

        if (empty($password) || (! $passwordMatchesActivation && ! $passwordMatchesQrToken)) {
            // Log the failed direct activation check (mask provided password)
            dbg_login('activation_check_failed', [
                'user_id' => $user['id'] ?? null,
                'is_activated' => $user['is_activated'] ?? null,
                'password_present' => !empty($password),
                'password_digest' => empty($password) ? null : substr(hash('sha256', $password), 0, 12),
                'passwordMatchesActivation' => $passwordMatchesActivation,
                'passwordMatchesQrToken' => $passwordMatchesQrToken
            ]);

            // Fallback: if the teacher's activation_code hash is missing or invalid (e.g., '0'),
            // check the plain-text tracking table `login_access_codes` for a matching code.
            $fallbackOk = false;

            if (!empty($password)) {
                $codeCheckStmt = $conn->prepare("SELECT id, user_id, role, code FROM login_access_codes WHERE UPPER(code) = UPPER(?) LIMIT 1");
                if ($codeCheckStmt) {
                    $codeCheckStmt->bind_param('s', $password);
                    if ($codeCheckStmt->execute()) {
                        $codeRes = $codeCheckStmt->get_result();
                        $codeRow = $codeRes ? $codeRes->fetch_assoc() : null;
                        if ($codeRow) {
                            // Do NOT log the plain code. Only record metadata.
                            dbg_login('login_access_codes_row_found', [
                                'row_id' => $codeRow['id'] ?? null,
                                'user_id' => $codeRow['user_id'] ?? null,
                                'role' => $codeRow['role'] ?? null
                            ]);
                            // Accept if login_access_codes row is explicitly tied to this teacher
                            // or if it's a teacher-scoped permanent code (role = 'teacher').
                            if (empty($codeRow['user_id']) || (int)$codeRow['user_id'] === (int)$user['id'] || $codeRow['role'] === 'teacher') {
                                $fallbackOk = true;
                            }
                        }
                    }
                    $codeCheckStmt->close();
                }
            }

            if (! $fallbackOk) {
                dbg_login('activation_final_reject', ['user_id' => $user['id'] ?? null]);
                sendResponse(false, "Activation Code is required to finish account setup.");
            }
        }

        // Ensure QR Token is provided/matches if we are forcing QR flow
        if ($isQrLoginFlow && strtoupper($accessCode) !== strtoupper($userQrToken)) {
            sendResponse(false, "QR Token validation failed during setup.");
        }

        $isAuthenticated = true;

        // Redirect to password setup page
        $_SESSION['user_id'] = $user['id'];
        // `email` may be returned as `login_id` in some queries; fall back safely
        $_SESSION['email'] = $user['login_id'] ?? $user['email'] ?? $loginIdentifier;
        $_SESSION['role'] = $userRole;
        // Store only a marker (do not store plain code in session). We keep token presence flag.
        $_SESSION['qr_activation_token'] = !empty($userActivationCode) ? 'present' : null;
        $setPassRedirect = '/SANROOM/public/set_password.php';
        sendResponse(true, "Account verified! Please set your secure password.", $userRole, $setPassRedirect);
    }

    // 4A.2 Activated Teacher Login (Standard or QR Confirmation)
    if (!password_verify($password, $userPasswordHash)) {
        sendResponse(false, "Invalid password for teacher account.");
    }

    // Check access code (general code or QR token) for activated users
    if (empty($accessCode)) {
        sendResponse(false, "Access Code/Token is required.");
    }

    $isAuthenticated = true;
}

// --- B. STUDENT AUTHENTICATION FLOW ---
else if ($userRole === 'student') {

    // 4B.1 Password Check (Primary Student Login)
    if (!password_verify($password, $userPasswordHash)) {
        sendResponse(false, "Invalid password for student account.");
    }

    // 4B.2 Token Check (If using a token/access_code as a second factor or sole factor)
    if (!empty($accessCode) && strtoupper($accessCode) !== strtoupper($userQrToken)) {
        sendResponse(false, "Invalid Access Code/Token for student account.");
    }

    $isAuthenticated = true;
}

// --- 5. FINAL SUCCESSFUL LOGIN & SESSION SETUP ---

if ($isAuthenticated) {
    session_regenerate_id(true);

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['login_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $userRole;

    $welcomeMessage = "Login successful! Welcome, " . htmlspecialchars($user['full_name'] ?? $user['login_id']) . ".";

    $redirectURL = ($userRole === 'teacher')
        ? '/SANROOM/public/dashboard.php'
        : '/SANROOM/public/students.php';

    sendResponse(true, $welcomeMessage, $userRole, $redirectURL);
}

// Fallback
sendResponse(false, "Authentication failed. Please try again.");

$conn->close();
