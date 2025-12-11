<?php
require_once __DIR__ . '/../includes/db.php';
// db.php already ensures a session is started if needed. Only start if none.
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }

$tid = $_SESSION['teacher_id'];
$full_name = trim($_POST['full_name'] ?? '');

// determine whether `avatar` column exists to avoid SQL errors on older schemas
$hasAvatar = false;
$col = $connection->prepare("SHOW COLUMNS FROM teachers LIKE 'avatar'");
if ($col) {
    $col->execute(); $cres = $col->get_result(); if ($cres && $cres->num_rows>0) $hasAvatar = true; $col->close();
}

// fetch existing to delete old file if replaced (only if avatar column exists)
$oldAvatar = '';
if ($hasAvatar) {
    // Avoid referencing `id` when it doesn't exist
    $hasId = false;
    $col = $connection->prepare("SHOW COLUMNS FROM teachers LIKE 'id'");
    if ($col) { $col->execute(); $cres = $col->get_result(); if ($cres && $cres->num_rows>0) $hasId = true; $col->close(); }

    if ($hasId) {
        $stmt = $connection->prepare('SELECT avatar FROM teachers WHERE id = ? OR teacher_id = ? LIMIT 1');
        if ($stmt) { $stmt->bind_param('ss', $tid, $tid); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); } else { $row = null; }
    } else {
        $stmt = $connection->prepare('SELECT avatar FROM teachers WHERE teacher_id = ? LIMIT 1');
        if ($stmt) { $stmt->bind_param('s', $tid); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close(); } else { $row = null; }
    }
    $oldAvatar = $row['avatar'] ?? '';
}

$newAvatar = $oldAvatar;
if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $up = $_FILES['avatar'];
    $allowed = ['image/png','image/jpeg','image/jpg','image/gif'];
    if (in_array($up['type'], $allowed)) {
        $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
        $fn = 'avatar_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $destRel = 'public/uploads/teacher/' . $fn;
        $dest = __DIR__ . '/../../' . $destRel;
        if (move_uploaded_file($up['tmp_name'], $dest)) {
            // delete old avatar
            if (!empty($oldAvatar) && file_exists(__DIR__ . '/../../' . $oldAvatar)) { @unlink(__DIR__ . '/../../' . $oldAvatar); }
            $newAvatar = $destRel;
        }
    }
}

// update teacher record
$up = null;
// Detect whether teachers table uses full_name or first_name/last_name
$hasFullName = false;
$col2 = $connection->prepare("SHOW COLUMNS FROM teachers LIKE 'full_name'");
if ($col2) { $col2->execute(); $cres2 = $col2->get_result(); if ($cres2 && $cres2->num_rows>0) $hasFullName = true; $col2->close(); }

// Prepare name parts if needed
$first_name = '';
$last_name = '';
if (!$hasFullName) {
    // split full name into first and last (best-effort)
    $parts = preg_split('/\s+/', trim($full_name));
    $first_name = $parts[0] ?? '';
    $last_name = isset($parts[1]) ? implode(' ', array_slice($parts,1)) : '';
}

if ($hasAvatar) {
    if ($hasFullName) {
        if ($hasId) {
            $up = $connection->prepare('UPDATE teachers SET full_name = ?, avatar = ? WHERE id = ? OR teacher_id = ?');
            if ($up) { $up->bind_param('ssss', $full_name, $newAvatar, $tid, $tid); $up->execute(); $up->close(); }
        } else {
            $up = $connection->prepare('UPDATE teachers SET full_name = ?, avatar = ? WHERE teacher_id = ?');
            if ($up) { $up->bind_param('sss', $full_name, $newAvatar, $tid); $up->execute(); $up->close(); }
        }
    } else {
        if ($hasId) {
            $up = $connection->prepare('UPDATE teachers SET first_name = ?, last_name = ?, avatar = ? WHERE id = ? OR teacher_id = ?');
            if ($up) { $up->bind_param('sssss', $first_name, $last_name, $newAvatar, $tid, $tid); $up->execute(); $up->close(); }
        } else {
            $up = $connection->prepare('UPDATE teachers SET first_name = ?, last_name = ?, avatar = ? WHERE teacher_id = ?');
            if ($up) { $up->bind_param('ssss', $first_name, $last_name, $newAvatar, $tid); $up->execute(); $up->close(); }
        }
    }
} else {
    if ($hasFullName) {
        if ($hasId) {
            $up = $connection->prepare('UPDATE teachers SET full_name = ? WHERE id = ? OR teacher_id = ?');
            if ($up) { $up->bind_param('sss', $full_name, $tid, $tid); $up->execute(); $up->close(); }
        } else {
            $up = $connection->prepare('UPDATE teachers SET full_name = ? WHERE teacher_id = ?');
            if ($up) { $up->bind_param('ss', $full_name, $tid); $up->execute(); $up->close(); }
        }
    } else {
        if ($hasId) {
            $up = $connection->prepare('UPDATE teachers SET first_name = ?, last_name = ? WHERE id = ? OR teacher_id = ?');
            if ($up) { $up->bind_param('ssss', $first_name, $last_name, $tid, $tid); $up->execute(); $up->close(); }
        } else {
            $up = $connection->prepare('UPDATE teachers SET first_name = ?, last_name = ? WHERE teacher_id = ?');
            if ($up) { $up->bind_param('sss', $first_name, $last_name, $tid); $up->execute(); $up->close(); }
        }
    }
}

header('Location: ../profile.php?updated=1'); exit;
