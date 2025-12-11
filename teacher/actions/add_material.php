<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../materials.php'); exit; }

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$link = $_POST['link'] ?? '';
$audience = $_POST['audience'] ?? 'students';
$target_value = trim($_POST['target_value'] ?? '');
$teacher_id = $_SESSION['teacher_id'];

$file_path = null;
if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $u = $_FILES['file'];
    $allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','image/png','image/jpeg','video/mp4','application/zip'];
    if (!in_array($u['type'],$allowed)) {
        $_SESSION['error']='File type not allowed'; header('Location: ../materials.php'); exit;
    }
    $uploadsRel = 'public/uploads/teacher';
    $uploadsDir = __DIR__ . '/../../' . $uploadsRel;
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
    $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/','_', basename($u['name']));
    $destAbs = $uploadsDir . '/' . $safeName;
    if (!move_uploaded_file($u['tmp_name'],$destAbs)) { $_SESSION['error']='Upload failed'; header('Location: ../materials.php'); exit; }
    $file_path = $uploadsRel . '/' . $safeName;
}

// determine notification audience value and normalize target_value if needed
$notifAudience = 'students';
if ($audience === 'specific') {
    if ($target_value === '') { $_SESSION['error']='Please provide target student email or ID for specific delivery.'; header('Location: ../materials.php'); exit; }
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
                $notifAudience = 'user:' . $row['email'];
            } else {
                $_SESSION['error'] = 'Target student not found.'; header('Location: ../materials.php'); exit;
            }
            if ($resu) $resu->close();
            $u->close();
        }
    } catch (Exception $e) { error_log('Validate target student failed: '.$e->getMessage()); }
} elseif ($audience === 'section') {
    if ($target_value === '') { $_SESSION['error']='Please select at least one section.'; header('Location: ../materials.php'); exit; }
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
    if (empty($valid)) { $_SESSION['error']='No valid sections selected.'; header('Location: ../materials.php'); exit; }
    $target_value = implode(',', $valid);
    $notifAudience = 'section:' . $target_value;
}

// insert learning material
$stmt = $connection->prepare("INSERT INTO learning_materials (teacher_id, title, description, file_path, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmt) { $_SESSION['error']='Prepare error'; error_log('add_material: learning_materials prepare failed: ' . $connection->error); header('Location: ../materials.php'); exit; }
$stmt->bind_param('issss', $teacher_id, $title, $description, $file_path, $link);
if ($stmt->execute() === false) { error_log('add_material: learning_materials execute failed: ' . $stmt->error); $_SESSION['error']='Insert failed'; $stmt->close(); header('Location: ../materials.php'); exit; }
$lm_insert_id = $stmt->insert_id; $stmt->close();
error_log('add_material: learning_material inserted id=' . intval($lm_insert_id) . ' teacher=' . intval($teacher_id) . ' title=' . $title);

// notify students about new material
try {
    $n = $connection->prepare('INSERT INTO notifications (sender_id, title, message, audience, created_at) VALUES (?, ?, ?, ?, NOW())');
    if ($n) {
        $ntitle = 'New Material: ' . $title;
        $nmsg = substr(trim($description),0,500);
        $n->bind_param('isss', $teacher_id, $ntitle, $nmsg, $notifAudience);
        $execOk = $n->execute();
        $notif_insert_id = $connection->insert_id;
        if ($execOk && $n->affected_rows > 0) {
            try {
                $hasResType = false; $hasResId = false;
                $rc = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_type'"); if ($rc && $rc->num_rows > 0) $hasResType = true; if ($rc) $rc->close();
                $rc2 = $connection->query("SHOW COLUMNS FROM notifications LIKE 'resource_id'"); if ($rc2 && $rc2->num_rows > 0) $hasResId = true; if ($rc2) $rc2->close();
                if ($hasResType && $hasResId && intval($notif_insert_id) > 0) {
                    $u2 = $connection->prepare('UPDATE notifications SET resource_type = ?, resource_id = ? WHERE id = ?');
                    if ($u2) { $rtype = 'learning_material'; $mid = intval($lm_insert_id); $nid = intval($notif_insert_id); $u2->bind_param('sii', $rtype, $mid, $nid); $u2->execute(); $u2->close(); }
                }
            } catch (Exception $e) { error_log('add_material: map resource failed: '.$e->getMessage()); }
        } else {
            error_log('add_material: notification insert failed: ' . $connection->error . ' params:' . json_encode([$teacher_id,$ntitle,$notifAudience]) . ' material_id:' . intval($lm_insert_id));
        }
        $n->close();
    }
} catch (Exception $e) { error_log('Material notif failed: '.$e->getMessage()); }

// Set a success message for the teacher indicating canonical target
if (session_status() === PHP_SESSION_NONE) session_start();
if ($notifAudience === 'students' || $notifAudience === 'all') {
    $_SESSION['success'] = 'Material uploaded and notification sent to all students';
} elseif (strpos($notifAudience, 'user:') === 0) {
    $_SESSION['success'] = 'Material uploaded and notification sent to ' . htmlspecialchars(substr($notifAudience, 5));
} elseif (strpos($notifAudience, 'section:') === 0) {
    $_SESSION['success'] = 'Material uploaded and notification sent to selected section(s)';
}

header('Location: ../materials.php'); exit;

