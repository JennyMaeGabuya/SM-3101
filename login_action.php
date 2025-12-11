<?php
// Ensure no accidental HTML or PHP notices are sent before JSON
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Start output buffering and provide a helper to send JSON responses
if (!ob_get_level()) ob_start();
function send_json($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json');
    }
    // Clean any stray output before sending JSON
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode($data);
    exit;
}

// Log fatal errors on shutdown (do not override normal JSON responses)
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err) {
        error_log('login_action fatal: ' . print_r($err, true));
        if (!headers_sent()) {
            header('Content-Type: application/json', true, 500);
            // Attempt minimal JSON output
            echo json_encode(['success' => false, 'message' => 'Server fatal error']);
        }
    }
});

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/connection/dbsConnection.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

$identifier = isset($data['identifier']) ? trim($data['identifier']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$role = isset($data['role']) ? $data['role'] : 'student';

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Identifier and password required.']);
    exit;
}

if (!isset($connection) || !$connection) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    exit;
}
$row = null;

if ($role === 'teacher') {
    // Try teachers table by email or teacher id
    $stmt = $connection->prepare('SELECT * FROM teachers WHERE email = ? OR teacher_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
    }
}

if (!$row) {
    // Fallback to accounts table (students / general accounts)
    $stmt = $connection->prepare('SELECT accountId, username, email, password, program, `year`, sr_code FROM accounts WHERE email = ? OR sr_code = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        // If a row was found here and role was teacher, override role to student
        if ($row && $role === 'teacher') $role = 'student';
    }
}

if (!$row) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$stored = isset($row['password']) ? $row['password'] : '';
$verified = false;

// If password is hashed, use password_verify. Otherwise, allow plaintext match and optionally re-hash.
if (password_verify($password, $stored)) {
    $verified = true;
} elseif ($password === $stored) {
    $verified = true;
    // re-hash and update stored password to hashed version
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    if ($role === 'teacher') {
        // Update teachers table; prefer numeric id if available
        if (!empty($row['id'])) {
            $upd = $connection->prepare('UPDATE teachers SET password = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('si', $newHash, $row['id']);
                $upd->execute();
                $upd->close();
            }
        } elseif (!empty($row['teacher_id'])) {
            $upd = $connection->prepare('UPDATE teachers SET password = ? WHERE teacher_id = ?');
            if ($upd) {
                $upd->bind_param('ss', $newHash, $row['teacher_id']);
                $upd->execute();
                $upd->close();
            }
        }
    } else {
        $upd = $connection->prepare('UPDATE accounts SET password = ? WHERE accountId = ?');
        if ($upd) {
            $upd->bind_param('si', $newHash, $row['accountId']);
            $upd->execute();
            $upd->close();
        }
    }
}

if (!$verified) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}
// Start session and set session vars
if (session_status() === PHP_SESSION_NONE) session_start();

$user = [];
if ($role === 'teacher') {
    // Try to infer id and name columns from teachers table
    $idKeys = ['id', 'teacher_id', 'teacherId', 'accountId', 'uid'];
    $nameKeys = ['name', 'full_name', 'fullname', 'first_name', 'username'];
    $teacherId = null;
    foreach ($idKeys as $k) {
        if (isset($row[$k]) && $row[$k]) {
            $teacherId = $row[$k];
            break;
        }
    }
    $teacherName = null;
    foreach ($nameKeys as $k) {
        if (isset($row[$k]) && $row[$k]) {
            $teacherName = $row[$k];
            break;
        }
    }
    $teacherEmail = isset($row['email']) ? $row['email'] : null;
    $user = ['id' => $teacherId, 'name' => $teacherName, 'email' => $teacherEmail];
    if ($teacherId) $_SESSION['teacher_id'] = $teacherId;
    $_SESSION['user_role'] = 'teacher';
} else {
    // Build student user object and include program/year and sections
    $user = ['id' => $row['accountId'], 'name' => $row['username'], 'email' => $row['email']];
    if (isset($row['program'])) $user['program'] = $row['program'];
    if (isset($row['year'])) $user['year'] = $row['year'];
    if (isset($row['sr_code'])) $user['sr_code'] = $row['sr_code'];
    // Load sections for this user
    $userSections = [];
    try {
        $s = $connection->prepare('SELECT s.name FROM user_sections us JOIN sections s ON us.section_id = s.id WHERE us.user_id = ?');
        if ($s) {
            $s->bind_param('i', $row['accountId']);
            $s->execute();
            $r = $s->get_result();
            while ($rr = $r->fetch_assoc()) {
                $userSections[] = $rr['name'];
            }
            $r && $r->close();
            $s->close();
        }
    } catch (Exception $e) {
        error_log('login_action: failed to load user sections: ' . $e->getMessage());
    }
    if (!empty($userSections)) $user['sections'] = $userSections;

    $_SESSION['user_id'] = $row['accountId'];
    $_SESSION['user_role'] = 'student';
}

echo json_encode(['success' => true, 'user' => $user, 'role' => $role]);
exit;
