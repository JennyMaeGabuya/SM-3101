<?php
require_once __DIR__ . '/../connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../announcements.php'); exit; }
$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$notes = trim($_POST['notes'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || $task_id<=0) { $_SESSION['error'] = 'Invalid submission'; header('Location: ../view_task.php?id=' . $task_id); exit; }

// Upload validation
$allowedMime = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/png',
    'image/jpeg',
    'video/mp4',
    'application/zip'
];
$maxSize = 10 * 1024 * 1024; // 10 MB

$uploadDir = __DIR__ . '/../public/uploads/task_submissions';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$storedPath = null;
if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'File upload error (' . $f['error'] . ')'; header('Location: ../view_task.php?id=' . $task_id); exit;
    }
    if ($f['size'] > $maxSize) { $_SESSION['error'] = 'File too large (max 10MB)'; header('Location: ../view_task.php?id=' . $task_id); exit; }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMime, true)) { $_SESSION['error'] = 'File type not allowed'; header('Location: ../view_task.php?id=' . $task_id); exit; }

    $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/','_', basename($f['name']));
    $dest = $uploadDir . '/' . $safe;
    if (!move_uploaded_file($f['tmp_name'], $dest)) { $_SESSION['error'] = 'Upload failed'; header('Location: ../view_task.php?id=' . $task_id); exit; }
    $storedPath = 'public/uploads/task_submissions/' . $safe;
}

// Save to DB: insert or update existing submission for this task + student
try {
    $existing = null;
    $q = $connection->prepare('SELECT id, file_path, status, grade, graded_by, graded_at FROM task_submissions WHERE task_id = ? AND student_id = ? LIMIT 1');
    if ($q) {
        $q->bind_param('ii', $task_id, $user_id);
        $q->execute();
        $res = $q->get_result();
        if ($res) { $existing = $res->fetch_assoc(); $res->close(); }
        $q->close();
    }

    // If existing submission is already graded, block further submissions/updates
    if ($existing) {
        $alreadyGraded = false;
        if (!empty($existing['graded_by']) || !empty($existing['graded_at'])) $alreadyGraded = true;
        if (!empty($existing['status']) && !in_array($existing['status'], ['submitted','pending'])) $alreadyGraded = true;
        if ($alreadyGraded) {
            $_SESSION['error'] = 'This submission has already been graded and cannot be resubmitted.';
            header('Location: ../view_task.php?id=' . $task_id);
            exit;
        }
    }

    if ($existing) {
        // update
        $stmt = $connection->prepare('UPDATE task_submissions SET notes = ?, file_path = COALESCE(?, file_path), status = ?, updated_at = NOW() WHERE id = ?');
        $status = 'submitted';
        $fp = $storedPath ?: null;
        $stmt->bind_param('sssi', $notes, $fp, $status, $existing['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $connection->prepare('INSERT INTO task_submissions (task_id, student_id, file_path, notes, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $status = 'submitted';
        $fp = $storedPath ?: null;
        $stmt->bind_param('iisss', $task_id, $user_id, $fp, $notes, $status);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION['success'] = 'Submission saved';
} catch (Exception $e) {
    error_log('submit_task: ' . $e->getMessage());
    $_SESSION['error'] = 'Could not save submission';
}

header('Location: ../view_task.php?id=' . $task_id);
exit;
?>