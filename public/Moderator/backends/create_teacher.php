<?php
// Script: create_teacher.php
// Status: FINAL FIX - Generates labeled QR data and handles DB insertion security.

ob_start();
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);

$response = [];
$conn = null;

// --- HELPER FUNCTION: Generate Short Activation Code (10 chars, uppercase/numeric) ---
function generateShortActivationCode(int $length = 10): string
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($characters) - 1;

    if ($length < 1) {
        return "";
    }

    for ($i = 0; $i < $length; $i++) {
        try {
            $code .= $characters[random_int(0, $max)];
        } catch (Exception $e) {
            return "";
        }
    }
    return $code;
}

// --- HELPER FUNCTION: Generate QR code locally ---
function generateQRCode($email, $token, $activationCode)
{
    $qrDir = __DIR__ . "/../../qrcodes/";

    // FIX: Attempt to create the directory if it doesn't exist.
    if (!is_dir($qrDir)) {
        if (!mkdir($qrDir, 0777, true)) {
            throw new Exception("QR code storage directory ('" . $qrDir . "') is missing and creation failed. Check permissions.");
        }
    }

    // CRITICAL: Encode data with proper labels (Email:XX|Code:YY|Token:ZZ)
    $qrData = "Email:" . trim($email) . "|Code:" . trim($activationCode) . "|Token:" . trim($token);
    error_log("QR_GEN_DEBUG: Final QR Content String: " . $qrData);

    $filename = hash('sha256', $qrData . microtime(true)) . '.png';
    $qrPath = $qrDir . $filename;

    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);

    $qrImage = @file_get_contents($qrUrl);

    if ($qrImage === false) {
        throw new Exception("Failed to fetch QR code image data from the external service: " . $qrUrl);
    }

    if (file_put_contents($qrPath, $qrImage)) {
        return "../../qrcodes/" . $filename;
    } else {
        throw new Exception("Failed to save the QR code image locally to: " . $qrPath);
    }
}

// --- SAFE EXECUTION BLOCK ---
try {
    require_once(__DIR__ . "/../../../database.php");

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? "Connection object missing."));
    }

    // Do NOT delete existing teacher rows here. Instead validate uniqueness and abort on conflict.


    // --- 2. INPUT DATA & VALIDATION ---
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['initial_password'] ?? 'changeme');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = "teacher";

    if (!$email) {
        throw new Exception("Teacher email is required.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    // --- 3. CHECK IF EMAIL EXISTS ---
    $check = $conn->prepare("SELECT teacher_id FROM teachers WHERE email = ? LIMIT 1");
    if ($check === false) throw new Exception('Prepare failed: ' . $conn->error);
    $check->bind_param('s', $email);
    $check->execute();
    $resCheck = $check->get_result();
    if ($resCheck && $resCheck->fetch_assoc()) {
        echo json_encode(["status" => "error", "message" => "A teacher with that email already exists."]);
        exit;
    }
    $check->close();

    // --- 4. GENERATE TOKENS ---
    $plainActivationCode = generateShortActivationCode(10);
    if (empty($plainActivationCode)) {
        throw new Exception("Activation code generation failed: empty code.");
    }

    // NOTE: activation code for teachers is stored in PLAIN TEXT by design for first-time activation
    // (it will be cleared when the teacher sets their permanent password).
    $plainActivationCode = $plainActivationCode; // already generated above

    $qrChars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $qrToken = "";
    for ($i = 0; $i < 12; $i++) {
        try {
            $qrToken .= $qrChars[random_int(0, strlen($qrChars) - 1)];
        } catch (Exception $e) {
            throw new Exception("QR token generation failed.");
        }
    }

    // CRITICAL: Generate the raw labeled data string for direct parsing
    $rawQrData = "Email:" . trim($email) . "|Code:" . trim($plainActivationCode) . "|Token:" . trim($qrToken);

    // --- 5. INSERT TEACHER ACCOUNT ---
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $isActivated = 0;

    $stmt = $conn->prepare(
        "INSERT INTO teachers 
        (email, password_hash, full_name, activation_code, is_active, qr_token) 
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Database prepare error (insert teacher): " . $conn->error);
    }
    // Correct types: email(s), password(s), full_name(s), activation_code(s), is_active(i), qr_token(s)
    // Bind the plain activation code (string) into the teachers table for first-time activation
    $stmt->bind_param("ssssis", $email, $hashedPassword, $fullName, $plainActivationCode, $isActivated, $qrToken);
    if (!$stmt->execute()) {
        throw new Exception("Failed to save teacher account. MySQL Error: " . $stmt->error);
    }

    $teacherId = $stmt->insert_id;
    $stmt->close();

    // --- 6. QR CODE GENERATION ---
    $qrUrl = generateQRCode($email, $qrToken, $plainActivationCode);

    // --- 7. SUCCESS RESPONSE ---
    $response = [
        "status" => "success",
        "message" => "Teacher account created! The user must scan the QR code and use the temporary activation code/password to finish setup.",
        "teacher_id" => $teacherId,
        "email" => $email,
        "activation_code" => $plainActivationCode,
        "qr_token" => $qrToken,
        "qr_url" => $qrUrl,
        "raw_qr_data" => $rawQrData // This enables direct JS parsing
    ];
} catch (Throwable $e) {
    $response = [
        "status" => "error",
        "message" => $e->getMessage() . " (Line: " . $e->getLine() . ")"
    ];
} finally {
    if ($conn !== null && $conn instanceof mysqli) {
        $conn->close();
    }
}
// Final output logic (omitted for brevity)
$extraOutput = trim(ob_get_clean());
if (!empty($extraOutput)) { /* handle output */
}
echo json_encode($response);
