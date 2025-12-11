<?php
// Secure download handler
// Usage: download.php?resource=task_submission&id=123
include_once 'connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Temporary debug logger (remove when done)
function download_log($line) {
    $file = __DIR__ . '/download_debug.log';
    $ts = date('Y-m-d H:i:s');
    $entry = "[{$ts}] " . $line . "\n";
    @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

$resource = $_GET['resource'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$resource || $id <= 0) {
    http_response_code(400);
    echo 'Invalid download request';
    exit;
}

$allowed = ['task_submission','submission','material','task_submission_archive'];
if (!in_array($resource, $allowed)) {
    http_response_code(400);
    echo 'Unsupported resource type';
    exit;
}

// helper to send file
function send_file($path) {
    if (!file_exists($path)) { http_response_code(404); echo 'File not found'; exit; }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
    finfo_close($finfo);
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

try {
    if ($resource === 'task_submission') {
        $stmt = $connection->prepare('SELECT file_path, student_id FROM task_submissions WHERE id = ? LIMIT 1');
        if (!$stmt) throw new Exception('DB prepare failed');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) { http_response_code(404); echo 'Submission not found'; exit; }
        $file = $row['file_path'];
        $owner = $row['student_id'];
        // authorize: students can download their own, teachers/admins can download
        $userId = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['user_role'] ?? null;
        if ($role !== 'teacher' && $role !== 'admin') {
            if (!$userId || intval($userId) !== intval($owner)) { http_response_code(403); echo 'Forbidden'; exit; }
        }
                // resolve path on disk — try multiple sensible locations to support XAMPP setups
                $candidates = [];
                $clean = ltrim(str_replace('\\', '/', $file), '/');
                // 1) DOCUMENT_ROOT + file (common when file paths are stored relative to webroot)
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $clean;
                // 2) DOCUMENT_ROOT + application folder (this app may live in a subfolder like /LearnHub)
                $appFolder = basename(__DIR__);
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $appFolder . '/' . $clean;
                // 3) script-dir relative path (useful when running from project root)
                $candidates[] = __DIR__ . '/' . $clean;
                // 4) also try raw file as-is
                $candidates[] = $file;

                download_log("task_submission id={$id} db_file_path=" . ($file ?? 'NULL'));
                $found = null;
                foreach ($candidates as $p) {
                    $origp = $p;
                    $p = str_replace('\\', DIRECTORY_SEPARATOR, $p);
                    download_log('trying candidate: ' . $origp . ' -> ' . $p);
                    if (file_exists($p)) { $found = $p; download_log('found: ' . $p); break; }
                }
                if (!$found) { download_log('not found for task_submission id={$id}'); http_response_code(404); echo 'File not found'; exit; }
                send_file($found);
    }

    if ($resource === 'submission') {
        $stmt = $connection->prepare('SELECT file_path, student_id, teacher_id FROM submissions WHERE id = ? LIMIT 1');
        if (!$stmt) throw new Exception('DB prepare failed');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) { http_response_code(404); echo 'Submission not found'; exit; }
        $file = $row['file_path'];
        $owner = $row['student_id'];
        $teacher = $row['teacher_id'];
        $userId = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['user_role'] ?? null;
        if ($role === 'teacher') {
            // allow
        } else {
            if (!$userId) { http_response_code(403); echo 'Forbidden'; exit; }
            // submissions.student_id may be stored as string (username/email) or numeric id
            if (is_numeric($owner)) {
                if (intval($owner) !== intval($userId)) { http_response_code(403); echo 'Forbidden'; exit; }
            } else {
                // allow if owner matches username or email of current user
                $u = $connection->prepare('SELECT username,email,accountId FROM accounts WHERE accountId = ? LIMIT 1');
                if ($u) { $u->bind_param('i',$userId); $u->execute(); $ur = $u->get_result(); $curr = $ur ? $ur->fetch_assoc() : null; if ($ur) $ur->close(); $u->close(); }
                $match = false;
                if ($curr) {
                    if ($owner === $curr['username'] || $owner === $curr['email'] || $owner === strval($curr['accountId'])) $match = true;
                }
                if (!$match) { http_response_code(403); echo 'Forbidden'; exit; }
            }
        }
                // resolve path similarly for submissions
                $candidates = [];
                $clean = ltrim(str_replace('\\', '/', $file), '/');
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $clean;
                $appFolder = basename(__DIR__);
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $appFolder . '/' . $clean;
                $candidates[] = __DIR__ . '/' . $clean;
                $candidates[] = $file;
                download_log("submission id={$id} db_file_path=" . ($file ?? 'NULL'));
                $found = null;
                foreach ($candidates as $p) {
                    $origp = $p;
                    $p = str_replace('\\', DIRECTORY_SEPARATOR, $p);
                    download_log('trying candidate: ' . $origp . ' -> ' . $p);
                    if (file_exists($p)) { $found = $p; download_log('found: ' . $p); break; }
                }
                if (!$found) { download_log('not found for submission id={$id}'); http_response_code(404); echo 'File not found'; exit; }
                send_file($found);
    }

            if ($resource === 'material') {
                $stmt = $connection->prepare('SELECT file_path, title FROM learning_materials WHERE id = ? LIMIT 1');
                if (!$stmt) throw new Exception('DB prepare failed');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if (!$row) { http_response_code(404); echo 'Material not found'; exit; }
                $file = $row['file_path'];
                // no strict authorization for materials; if you need, add checks here
                $candidates = [];
                $clean = ltrim(str_replace('\\', '/', $file), '/');
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $clean;
                $appFolder = basename(__DIR__);
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $appFolder . '/' . $clean;
                $candidates[] = __DIR__ . '/' . $clean;
                $candidates[] = $file;
                                download_log("material id={$id} db_file_path=" . ($file ?? 'NULL'));
                                $found = null;
                                foreach ($candidates as $p) {
                                    $origp = $p;
                                    $p = str_replace('\\', DIRECTORY_SEPARATOR, $p);
                                    download_log('trying candidate: ' . $origp . ' -> ' . $p);
                                    if (file_exists($p)) { $found = $p; download_log('found: ' . $p); break; }
                                }
                                if (!$found) { download_log('not found for material id={$id}'); http_response_code(404); echo 'File not found'; exit; }
                                send_file($found);
            }

            if ($resource === 'task_submission_archive') {
                $stmt = $connection->prepare('SELECT file_path FROM task_submissions_archive WHERE id = ? LIMIT 1');
                if (!$stmt) throw new Exception('DB prepare failed');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if (!$row) { http_response_code(404); echo 'Submission not found'; exit; }
                $file = $row['file_path'];
                $candidates = [];
                $clean = ltrim(str_replace('\\', '/', $file), '/');
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $clean;
                $appFolder = basename(__DIR__);
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $appFolder . '/' . $clean;
                $candidates[] = __DIR__ . '/' . $clean;
                $candidates[] = $file;
                                download_log("task_submission_archive id={$id} db_file_path=" . ($file ?? 'NULL'));
                                $found = null;
                                foreach ($candidates as $p) {
                                    $origp = $p;
                                    $p = str_replace('\\', DIRECTORY_SEPARATOR, $p);
                                    download_log('trying candidate: ' . $origp . ' -> ' . $p);
                                    if (file_exists($p)) { $found = $p; download_log('found: ' . $p); break; }
                                }
                                if (!$found) { download_log('not found for task_submission_archive id={$id}'); http_response_code(404); echo 'File not found'; exit; }
                                send_file($found);
            }
} catch (Exception $e) {
    http_response_code(500); echo 'Server error'; exit;
}

?>
