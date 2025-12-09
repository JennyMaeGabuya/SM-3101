<?php
// repair_teacher_activation_codes.php
// Moderator-only utility: fix teachers.activation_code column type and regenerate hashed activation codes
// for any teacher rows where activation_code is NULL, empty or '0'.

header('Content-Type: application/json');
session_start();

// Permission check
$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['superadmin', 'admin', 'moderator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// Disable mysqli exceptions for safety
mysqli_report(MYSQLI_REPORT_OFF);

require_once(__DIR__ . '/../../../database.php');

$out = ['success' => true, 'fixed' => [], 'errors' => [], 'warnings' => []];

// Ensure we have a DB connection
if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}

// 1) Ensure column types are text-friendly. Record any errors but do not fail.
$alter1 = $conn->query("ALTER TABLE `teachers` MODIFY COLUMN `activation_code` VARCHAR(255) NULL;");
if ($alter1 === false) {
    $out['warnings'][] = 'Could not ALTER activation_code column: ' . $conn->error;
}
$alter2 = $conn->query("ALTER TABLE `teachers` MODIFY COLUMN `qr_token` VARCHAR(255) NULL;");
if ($alter2 === false) {
    $out['warnings'][] = 'Could not ALTER qr_token column: ' . $conn->error;
}

// Debug logger for repair actions (do not log plain activation codes)
$repairLog = __DIR__ . "/../logs/repair_actions.log";
if (!is_dir(dirname($repairLog))) {
    @mkdir(dirname($repairLog), 0777, true);
}
function dbg_repair($label, $data = [])
{
    global $repairLog;
    $entry = ['ts' => date('c'), 'label' => $label, 'payload' => $data];
    @file_put_contents($repairLog, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
dbg_repair('repair_started', ['warnings' => $out['warnings']]);

// Helper: generate code
function generateShortActivationCode($length = 10)
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = strlen($chars) - 1;
    $s = '';
    for ($i = 0; $i < $length; $i++) {
        try {
            $s .= $chars[random_int(0, $max)];
        } catch (Exception $e) {
            $s .= $chars[mt_rand(0, $max)];
        }
    }
    return $s;
}

// 2) Find affected teachers
$sql = "SELECT teacher_id, email, activation_code FROM teachers WHERE activation_code IS NULL OR activation_code = '' OR activation_code = '0'";
$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit;
}

while ($row = $res->fetch_assoc()) {
    $tid = $row['teacher_id'];
    $email = $row['email'];
    // generate plain code
    $plain = generateShortActivationCode(10);
    // Store the plain activation code for teachers (they will change it on first login)
    $upd = $conn->prepare("UPDATE teachers SET activation_code = ? WHERE teacher_id = ?");
    if (!$upd) {
        $out['errors'][] = ['teacher_id' => $tid, 'email' => $email, 'error' => 'prepare failed: ' . $conn->error];
        dbg_repair('repair_prepare_failed', ['teacher_id' => $tid, 'email' => $email, 'error' => $conn->error]);
        continue;
    }
    $upd->bind_param('si', $plain, $tid);
    if (!$upd->execute()) {
        $out['errors'][] = ['teacher_id' => $tid, 'email' => $email, 'error' => 'execute failed: ' . $upd->error];
        dbg_repair('repair_error_execute', ['teacher_id' => $tid, 'email' => $email, 'error' => $upd->error]);
        $upd->close();
        continue;
    }
    $upd->close();
    $out['fixed'][] = ['teacher_id' => $tid, 'email' => $email, 'activation_code_plain' => $plain];
    // Log the repaired teacher (do NOT include plain code in the log)
    dbg_repair('repaired_teacher', ['teacher_id' => $tid, 'email' => $email]);
}

dbg_repair('repair_finished', ['fixed_count' => count($out['fixed']), 'errors' => $out['errors']]);

echo json_encode($out, JSON_PRETTY_PRINT);
