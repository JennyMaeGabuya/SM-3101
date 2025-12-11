<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id<=0) { header('Location: ../activities.php'); exit; }

// fetch activity title for cleaning notifications
$actTitle = null;
$s = $connection->prepare('SELECT title FROM activities WHERE id = ? AND teacher_id = ? LIMIT 1');
if ($s) {
	$s->bind_param('ii', $id, $_SESSION['teacher_id']);
	$s->execute();
	$res = $s->get_result();
	if ($res) { $r = $res->fetch_assoc(); if ($r) $actTitle = $r['title']; $res->close(); }
	$s->close();
}

if (!empty($actTitle)) {
	try {
		$ntitle = 'New Activity: ' . $actTitle;
		$d = $connection->prepare('DELETE FROM notifications WHERE sender_id = ? AND title = ?');
		if ($d) { $d->bind_param('is', $_SESSION['teacher_id'], $ntitle); $d->execute(); $d->close(); }
	} catch (Exception $e) { error_log('delete_activity: failed to remove notifications: '.$e->getMessage()); }
}

$stmt = $connection->prepare('DELETE FROM activities WHERE id = ? AND teacher_id = ?');
if ($stmt) { $stmt->bind_param('ii',$id,$_SESSION['teacher_id']); $stmt->execute(); $stmt->close(); }

header('Location: ../activities.php'); exit;
