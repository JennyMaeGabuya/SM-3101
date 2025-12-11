<?php
require_once __DIR__ . '/../includes/db.php';
session_start();
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../materials.php'); exit; }

$id = intval($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$link = trim($_POST['link'] ?? '');

if (!$id || !$title) { header('Location: ../materials.php'); exit; }

// verify ownership and existing
$stmt = $connection->prepare('SELECT file_path FROM learning_materials WHERE id = ? AND teacher_id = ? LIMIT 1');
if (!$stmt) { header('Location: ../materials.php'); exit; }
$stmt->bind_param('ii', $id, $_SESSION['teacher_id']); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close();
if (!$row) { header('Location: ../materials.php'); exit; }

$filePath = $row['file_path'];
// handle file replacement
if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $up = $_FILES['file'];
    $allowed = ['application/pdf','image/png','image/jpeg','application/zip','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (in_array($up['type'],$allowed)) {
        $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
        $fn = 'material_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $destRel = 'public/uploads/teacher/' . $fn;
        $dest = __DIR__ . '/../../' . $destRel;
        if (move_uploaded_file($up['tmp_name'], $dest)) {
            // remove old file
            if (!empty($filePath) && file_exists(__DIR__ . '/../../' . $filePath)) { @unlink(__DIR__ . '/../../' . $filePath); }
            $filePath = $destRel;
        }
    }
}

$up = $connection->prepare('UPDATE learning_materials SET title = ?, description = ?, link = ?, file_path = ? WHERE id = ? AND teacher_id = ?');
if ($up) { $up->bind_param('ssssii', $title, $description, $link, $filePath, $id, $_SESSION['teacher_id']); $up->execute(); $up->close(); }

header('Location: ../materials.php?edited=1'); exit;
