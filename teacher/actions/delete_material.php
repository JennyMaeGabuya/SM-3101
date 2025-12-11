<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id<=0) { header('Location: ../materials.php'); exit; }

// Optionally fetch file path to delete file from disk
// Fetch material info (file path and title) so we can remove file and related notification
$stmt = $connection->prepare('SELECT file_path, title FROM learning_materials WHERE id = ? AND teacher_id = ? LIMIT 1');
if ($stmt) { $stmt->bind_param('ii',$id,$_SESSION['teacher_id']); $stmt->execute(); $res = $stmt->get_result(); $r = $res->fetch_assoc(); $stmt->close(); }
if (!empty($r['file_path']) && file_exists(__DIR__ . '/../../' . $r['file_path'])) { @unlink(__DIR__ . '/../../' . $r['file_path']); }

// Remove the material row
$del = $connection->prepare('DELETE FROM learning_materials WHERE id = ? AND teacher_id = ?');
if ($del) { $del->bind_param('ii',$id,$_SESSION['teacher_id']); $del->execute(); $del->close(); }

// Also delete any notification created for this material (best-effort)
if (!empty($r['title'])) {
	try {
		$ntitle = 'New Material: ' . $r['title'];
		$d = $connection->prepare('DELETE FROM notifications WHERE sender_id = ? AND title = ?');
		if ($d) { $d->bind_param('is', $_SESSION['teacher_id'], $ntitle); $d->execute(); $d->close(); }
	} catch (Exception $e) { error_log('delete_material: failed to remove notifications: '.$e->getMessage()); }
}
header('Location: ../materials.php'); exit;
