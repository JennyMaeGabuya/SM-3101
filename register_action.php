<?php
// Ensure the script always returns JSON and doesn't leak HTML error output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json');

// Convert PHP errors to exceptions so they can be handled uniformly
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($ex) {
    http_response_code(500);
    error_log('Uncaught exception: ' . $ex->getMessage() . "\n" . $ex->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
    exit;
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        error_log('Shutdown error: ' . print_r($err, true));
        echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
    }
});

require_once __DIR__ . '/connection/dbsConnection.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

$name = isset($data['name']) ? trim($data['name']) : '';
$studentId = isset($data['studentId']) ? trim($data['studentId']) : '';
$srCode = isset($data['sr_code']) ? trim($data['sr_code']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$program = isset($data['program']) ? trim($data['program']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$year = isset($data['year']) ? trim($data['year']) : '';

// Require name, email, password and sr_code
if (empty($name) || empty($email) || empty($password) || empty($srCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name, email and password are required.']);
    exit;
}

// Require program, year and at least one section selection
if (empty($program) || empty($year) || !isset($data['sections']) || !is_array($data['sections']) || count($data['sections']) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Program, year and at least one section selection are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

if (!isset($connection) || !$connection) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available.']);
    exit;
}

// Check existing email
$stmt = $connection->prepare('SELECT accountId FROM accounts WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered.']);
    exit;
}
$stmt->close();

// Validate SR-CODE format: two digits, dash, five digits (00-00000)
if (!preg_match('/^\d{2}-\d{5}$/', $srCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'SR-CODE must be in format 00-00000']);
    exit;
}

// Check existing SR-CODE
$stmt = $connection->prepare('SELECT accountId FROM accounts WHERE sr_code = ? LIMIT 1');
$stmt->bind_param('s', $srCode);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'SR-CODE already registered.']);
    exit;
}
$stmt->close();

// Check existing username to avoid duplicate-key DB exception
$stmt = $connection->prepare('SELECT accountId FROM accounts WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $name);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Username already registered.']);
    exit;
}
$stmt->close();

$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert into accounts. Current table has (username, email, password)
// Persist program and year into accounts if columns exist
// Include sr_code in accounts insert (column must exist in DB)
$insertSql = "INSERT INTO accounts (username, email, password, program, `year`, sr_code) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $connection->prepare($insertSql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $connection->error]);
    exit;
}
$stmt->bind_param('ssssss', $name, $email, $hash, $program, $year, $srCode);
$ok = $stmt->execute();
if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$insertedId = $stmt->insert_id;
$stmt->close();

// Start a session and log the user in on the server so subsequent page loads
// and API calls (notifications, activities) reflect the newly created account.
if (session_status() === PHP_SESSION_NONE) session_start();
try {
    $_SESSION['user_id'] = $insertedId;
    $_SESSION['user_role'] = 'student';
} catch (Exception $e) {
    // non-fatal; continue — client will still have localStorage set
    error_log('register_action: failed to set session for new user: ' . $e->getMessage());
}

// Process submitted sections (normalize names/ids), persist mappings, and prepare section names for response
$sections = [];
$sectionsOut = [];
if (!empty($data['sections']) && is_array($data['sections'])) {
    foreach ($data['sections'] as $s) {
        // numeric section id
        if (is_numeric($s) && intval($s) > 0) {
            $sections[] = intval($s);
            continue;
        }
        // treat as section name
        $sectionName = trim((string)$s);
        if ($sectionName === '') continue;
        try {
            $secId = null;
            $q = $connection->prepare('SELECT id FROM sections WHERE name = ? LIMIT 1');
            if ($q) {
                $q->bind_param('s', $sectionName);
                $q->execute();
                $res = $q->get_result();
                if ($res && $res->num_rows > 0) { $row = $res->fetch_assoc(); $secId = intval($row['id']); }
                $res && $res->close();
                $q->close();
            }
            if (!$secId) {
                $ins = $connection->prepare('INSERT INTO sections (name, created_at) VALUES (?, NOW())');
                if ($ins) {
                    $ins->bind_param('s', $sectionName);
                    $ins->execute();
                    $secId = intval($ins->insert_id);
                    $ins->close();
                }
            }
            if ($secId) $sections[] = $secId;
        } catch (Exception $e) {
            error_log('register_action: failed to find/insert section "' . $sectionName . '": ' . $e->getMessage());
        }
    }
}

// Remove duplicates and ensure ints
$sections = array_values(array_unique(array_filter(array_map('intval', $sections))));

if (!empty($sections)) {
    try {
        $ins = $connection->prepare('INSERT IGNORE INTO user_sections (user_id, section_id, created_at) VALUES (?, ?, NOW())');
        if ($ins) {
            foreach ($sections as $sec) {
                $ins->bind_param('ii', $insertedId, $sec);
                $ins->execute();
            }
            $ins->close();
        }
    } catch (Exception $e) {
        error_log('register_action: failed to insert user_sections: ' . $e->getMessage());
    }
    // retrieve section names for response
    try {
        $q = $connection->prepare('SELECT name FROM sections WHERE id = ? LIMIT 1');
        if ($q) {
            foreach ($sections as $sid) {
                $q->bind_param('i', $sid);
                $q->execute();
                $r = $q->get_result();
                if ($r && ($row = $r->fetch_assoc())) { $sectionsOut[] = $row['name']; }
                if ($r) $r->close();
            }
            $q->close();
        }
    } catch (Exception $e) { /* ignore */ }
}

// Return a richer user payload so the client can immediately render correct UI
$userPayload = [
    'id' => $insertedId,
    'name' => $name,
    'email' => $email,
    'program' => $program,
    'year' => $year,
    'sr_code' => $srCode,
    'sections' => $sectionsOut
];

$response = ['success' => true, 'message' => 'Account created', 'id' => $insertedId, 'user' => $userPayload];
echo json_encode($response);
exit;
// If sections were provided, insert mappings into user_sections
// Sections may be sent as IDs or as section names. Normalize to section IDs.
$sections = [];
if (!empty($data['sections']) && is_array($data['sections'])) {
    foreach ($data['sections'] as $s) {
        if (is_numeric($s) && intval($s) > 0) {
            $sections[] = intval($s);
            continue;
        }
        // treat as name string
        $sectionName = trim((string)$s);
        if ($sectionName === '') continue;
        try {
            $secId = null;
            $q = $connection->prepare('SELECT id FROM sections WHERE name = ? LIMIT 1');
            if ($q) {
                $q->bind_param('s', $sectionName);
                $q->execute();
                $res = $q->get_result();
                if ($res && $res->num_rows > 0) { $row = $res->fetch_assoc(); $secId = intval($row['id']); }
                $res && $res->close();
                $q->close();
            }
            if (!$secId) {
                $ins = $connection->prepare('INSERT INTO sections (name, created_at) VALUES (?, NOW())');
                if ($ins) {
                    $ins->bind_param('s', $sectionName);
                    $ins->execute();
                    $secId = intval($ins->insert_id);
                    $ins->close();
                }
            }
            if ($secId) $sections[] = $secId;
        } catch (Exception $e) {
            error_log('register_action: failed to find/insert section "' . $sectionName . '": ' . $e->getMessage());
        }
    }
}

// Remove duplicates and ensure ints
$sections = array_values(array_unique(array_filter(array_map('intval', $sections))));

if (!empty($sections)) {
    try {
        $ins = $connection->prepare('INSERT IGNORE INTO user_sections (user_id, section_id, created_at) VALUES (?, ?, NOW())');
        if ($ins) {
            foreach ($sections as $sec) {
                $ins->bind_param('ii', $insertedId, $sec);
                $ins->execute();
            }
            $ins->close();
        }
    } catch (Exception $e) {
        error_log('register_action: failed to insert user_sections: ' . $e->getMessage());
    }
}

// Mark existing notifications as read for this newly created account so the bell is empty
try {
    $res = $connection->query('SELECT id FROM notifications');
    if ($res) {
        $ins = $connection->prepare('INSERT IGNORE INTO notification_reads (notification_id, account_id, read_at) VALUES (?, ?, NOW())');
        if ($ins) {
            while ($r = $res->fetch_assoc()) {
                $nid = intval($r['id']);
                $ins->bind_param('ii', $nid, $insertedId);
                $ins->execute();
            }
            $ins->close();
        }
        $res->close();
    }
} catch (Exception $e) {
    error_log('register_action: failed to mark notifications read for new user: ' . $e->getMessage());
}

// Remove any notifications explicitly targeted at this email (user:email)
try {
    $target = 'user:' . $connection->real_escape_string($email);
    $del = $connection->prepare('DELETE FROM notifications WHERE audience = ?');
    if ($del) {
        $del->bind_param('s', $target);
        $del->execute();
        $del->close();
    }
} catch (Exception $e) {
    error_log('register_action: failed to delete user-targeted notifications for ' . $email . ': ' . $e->getMessage());
}

// Additionally attempt to remove notifications that were created by the same email
try {
    // 1) If the email corresponds to a teacher, delete notifications sent by that teacher id
    $tid = null;
    $q = $connection->prepare('SELECT teacher_id FROM teachers WHERE email = ? LIMIT 1');
    if ($q) {
        $q->bind_param('s', $email);
        $q->execute();
        $r = $q->get_result();
        if ($r && ($row = $r->fetch_assoc())) { $tid = intval($row['teacher_id']); }
        if ($r) $r->close();
        $q->close();
    }
    if ($tid) {
        $d = $connection->prepare('DELETE FROM notifications WHERE sender_id = ?');
        if ($d) { $d->bind_param('i', $tid); $d->execute(); $d->close(); }
    }

    // 2) Delete notifications whose title or message include the email (best-effort cleanup)
    $like = '%' . $connection->real_escape_string($email) . '%';
    $delText = $connection->prepare('DELETE FROM notifications WHERE title LIKE ? OR message LIKE ?');
    if ($delText) {
        $delText->bind_param('ss', $like, $like);
        $delText->execute();
        $delText->close();
    }

    // 3) Also try to delete notifications where sender_id matches an account with this email (if present)
    $aid = null;
    $q2 = $connection->prepare('SELECT accountId FROM accounts WHERE email = ? LIMIT 1');
    if ($q2) {
        $q2->bind_param('s', $email);
        $q2->execute();
        $r2 = $q2->get_result();
        if ($r2 && ($row2 = $r2->fetch_assoc())) { $aid = intval($row2['accountId']); }
        if ($r2) $r2->close();
        $q2->close();
    }
    if ($aid) {
        $d2 = $connection->prepare('DELETE FROM notifications WHERE sender_id = ?');
        if ($d2) { $d2->bind_param('i', $aid); $d2->execute(); $d2->close(); }
    }
} catch (Exception $e) {
    error_log('register_action: failed to cleanup notifications for ' . $email . ': ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => 'Account created', 'id' => $insertedId]);
exit;
// Note: response already sent above; but ensure server session is set for this new user so
// subsequent page loads (server-rendered content, API endpoints) see the correct logged-in user.

?>
