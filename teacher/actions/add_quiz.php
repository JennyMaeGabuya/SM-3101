<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../quizzes.php');
    exit;
}

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$scheduled_at = $_POST['scheduled_at'] ?? null;
$deadline = $_POST['deadline'] ?? null;
$audience = $_POST['audience'] ?? 'all';
$target_value = trim($_POST['target_value'] ?? '');
$max_attempts = isset($_POST['max_attempts']) ? intval($_POST['max_attempts']) : 2;

// Basic validation
if (trim($title) === '') {
    $_SESSION['error'] = 'Title is required.';
    header('Location: ../quizzes.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'] ?? 1;

// If specific audience is chosen, validate the target (email, username or id) and canonicalize to email
if ($audience === 'specific') {
    if ($target_value === '') {
        $_SESSION['error'] = 'Target student email or ID required for specific send.';
        header('Location: ../quizzes.php'); exit;
    }
    try {
        $search = $target_value;
        $searchId = intval($search);
        $u = $connection->prepare('SELECT email FROM accounts WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) OR accountId = ? LIMIT 1');
        if ($u) {
            $u->bind_param('ssi', $search, $search, $searchId);
            $u->execute();
            $resu = $u->get_result();
            if ($resu && $resu->num_rows > 0) {
                $row = $resu->fetch_assoc();
                $target_value = $row['email'];
                $resu->close();
            } else {
                $_SESSION['error'] = 'Target student not found.';
                header('Location: ../quizzes.php'); exit;
            }
            $u->close();
        }
    } catch (Exception $e) { error_log('Validate target student failed: '.$e->getMessage()); }
}
// If audience is 'section', validate provided section ids (comma separated) and normalize
if ($audience === 'section') {
    if ($target_value === '') {
        $_SESSION['error'] = 'Please select at least one section for section delivery.';
        header('Location: ../quizzes.php'); exit;
    }
    // normalize: accept comma-separated IDs
    $parts = array_filter(array_map('trim', explode(',', $target_value)));
    $valid = [];
    try {
        $pstmt = $connection->prepare('SELECT id FROM sections WHERE id = ? LIMIT 1');
        if ($pstmt) {
            foreach ($parts as $p) {
                $pid = intval($p);
                if ($pid <= 0) continue;
                $pstmt->bind_param('i', $pid);
                $pstmt->execute();
                $resu = $pstmt->get_result();
                if ($resu && $resu->num_rows > 0) { $valid[] = $pid; }
                if ($resu) $resu->close();
            }
            $pstmt->close();
        }
    } catch (Exception $e) { error_log('validate section ids failed: '.$e->getMessage()); }
    if (empty($valid)) { $_SESSION['error'] = 'No valid sections selected.'; header('Location: ../quizzes.php'); exit; }
    $target_value = implode(',', $valid);
}

// Check whether the quizzes table has 'audience' column. If not, use fallback insert.
try {
    $hasAudience = false;
    $colRes = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'audience'");
    if ($colRes && $colRes->num_rows > 0) { $hasAudience = true; $colRes->close(); }
} catch (Exception $e) {
    // If SHOW COLUMNS fails, assume older schema
    $hasAudience = false;
}

if ($hasAudience) {
    // Safe prepared insert using audience/target_value
    try {
        // include max_attempts if column exists
        $hasMax = false;
        try { $c = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'max_attempts'"); if ($c && $c->num_rows>0) { $hasMax = true; $c->close(); } } catch (Exception $e) { }
        if ($hasMax) {
            $stmt = $connection->prepare("INSERT INTO quizzes (teacher_id, title, description, scheduled_at, deadline, audience, target_value, max_attempts, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        } else {
            $stmt = $connection->prepare("INSERT INTO quizzes (teacher_id, title, description, scheduled_at, deadline, audience, target_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        }
        if ($stmt) {
            if ($hasMax) $stmt->bind_param('issssssi', $teacher_id, $title, $description, $scheduled_at, $deadline, $audience, $target_value, $max_attempts);
            else $stmt->bind_param('issssss', $teacher_id, $title, $description, $scheduled_at, $deadline, $audience, $target_value);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
        } else {
            error_log('Prepare failed for add_quiz (with audience): ' . $connection->error);
            // fallback
            $hasAudience = false;
        }
    } catch (mysqli_sql_exception $ex) {
        error_log('Add quiz (with audience) exception: ' . $ex->getMessage());
        $hasAudience = false;
    }
}

if (!$hasAudience) {
    // Fallback: older schema without audience/target_value
    // also include max_attempts if available
    $fb = null;
    try { $c = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'max_attempts'"); $hasMax = ($c && $c->num_rows>0); if ($c) $c->close(); } catch (Exception $e) { $hasMax = false; }
    if ($hasMax) {
    $fb = $connection->prepare("INSERT INTO quizzes (teacher_id, title, description, scheduled_at, deadline, max_attempts, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    if ($fb) { $fb->bind_param('issssi', $teacher_id, $title, $description, $scheduled_at, $deadline, $max_attempts); $fb->execute(); $newId = $fb->insert_id; $fb->close(); }
    } else {
        $fb = $connection->prepare("INSERT INTO quizzes (teacher_id, title, description, scheduled_at, deadline, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if ($fb) { $fb->bind_param('issss', $teacher_id, $title, $description, $scheduled_at, $deadline); $fb->execute(); $newId = $fb->insert_id; $fb->close(); }
    }
    if (!$fb) { $_SESSION['error'] = 'Prepare failed: ' . $connection->error; header('Location: ../quizzes.php'); exit; }
}

// notify students about new quiz
try {
    $nstmt = $connection->prepare('INSERT INTO notifications (sender_id, title, message, audience, created_at) VALUES (?, ?, ?, ?, NOW())');
        if ($nstmt) {
        $notifTitle = 'New Quiz: ' . $title;
        $notifMsg = substr(trim($description), 0, 500);
        // Map quiz audience to notification audience
            $notifAudience = 'students';
            if ($audience === 'specific' && $target_value !== '') {
                $notifAudience = 'user:' . $target_value;
            } elseif ($audience === 'section' && $target_value !== '') {
                $notifAudience = 'section:' . $target_value; // comma separated section ids
            }
        $nstmt->bind_param('isss', $teacher_id, $notifTitle, $notifMsg, $notifAudience);
        $nstmt->execute();
        // diagnostic logging
        if ($nstmt->affected_rows > 0) {
            error_log('add_quiz: notification inserted id=' . $nstmt->insert_id . ' title=' . $notifTitle . ' audience=' . $notifAudience);
        } else {
            error_log('add_quiz: notification insert failed: ' . $connection->error . ' params:' . json_encode([$teacher_id,$notifTitle,$notifAudience]));
        }
        $nstmt->close();
    }
} catch (Exception $e) {
    error_log('Notification insert failed: ' . $e->getMessage());
}

// Redirect back to quizzes list and focus on the newly created quiz for adding questions when possible
if (session_status() === PHP_SESSION_NONE) session_start();
// Prepare a teacher-visible success message showing canonical target
if ($notifAudience === 'students' || $notifAudience === 'all') {
    $_SESSION['success'] = 'Quiz created and notification sent to all students';
} elseif (strpos($notifAudience, 'user:') === 0) {
    $_SESSION['success'] = 'Quiz created and notification sent to ' . htmlspecialchars(substr($notifAudience, 5));
} elseif (strpos($notifAudience, 'section:') === 0) {
    $_SESSION['success'] = 'Quiz created and notification sent to selected section(s)';
}

if (!empty($newId)) {
    header('Location: ../quizzes.php?created_id=' . intval($newId));
} else {
    header('Location: ../quizzes.php');
}
exit;

// Diagnostic log for quiz creation
// diagnostic logged earlier where needed; removed unreachable trailing log
