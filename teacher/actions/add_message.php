<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../messages.php'); exit; }

$title = $_POST['title'] ?? '';
$message = $_POST['message'] ?? '';
$send_to = $_POST['send_to'] ?? 'all';
$target_value = trim($_POST['target_value'] ?? '');
$teacher_id = $_SESSION['teacher_id'];

if (trim($title)==='') { $_SESSION['error']='Title required'; header('Location: ../messages.php'); exit; }

// Normalize audience and support section/specific targets
$aud = $send_to;
if ($send_to === 'specific') {
	if ($target_value === '') { $_SESSION['error']='Please provide target student email'; header('Location: ../messages.php'); exit; }
	// validate email exists
	try {
		$search = $target_value;
		$searchId = intval($search);
		$u = $connection->prepare('SELECT email FROM accounts WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) OR accountId = ? LIMIT 1');
		if ($u) { $u->bind_param('ssi', $search, $search, $searchId); $u->execute(); $resu = $u->get_result(); if ($resu && $resu->num_rows>0) { $row = $resu->fetch_assoc(); $aud = 'user:' . $row['email']; } else { $_SESSION['error']='Target student not found'; header('Location: ../messages.php'); exit; } $resu && $resu->close(); $u->close(); }
	} catch (Exception $e) { error_log('add_message validate student failed: '.$e->getMessage()); }
} elseif ($send_to === 'section') {
	if ($target_value === '') { $_SESSION['error']='Please select one or more sections'; header('Location: ../messages.php'); exit; }
	// expect comma separated ids (already prepared by client)
	$parts = array_filter(array_map('trim', explode(',', $target_value)));
	$valid = [];
	try {
		$pstmt = $connection->prepare('SELECT id FROM sections WHERE id = ? LIMIT 1');
		if ($pstmt) {
			foreach ($parts as $p) { $pid = intval($p); if ($pid<=0) continue; $pstmt->bind_param('i',$pid); $pstmt->execute(); $resu = $pstmt->get_result(); if ($resu && $resu->num_rows>0) $valid[] = $pid; if ($resu) $resu->close(); }
			$pstmt->close();
		}
	} catch (Exception $e) { error_log('add_message validate sections failed: '.$e->getMessage()); }
	if (empty($valid)) { $_SESSION['error']='No valid sections selected'; header('Location: ../messages.php'); exit; }
	$aud = 'section:' . implode(',', $valid);
}

$stmt = $connection->prepare('INSERT INTO notifications (sender_id, title, message, audience, created_at) VALUES (?, ?, ?, ?, NOW())');
if (!$stmt) { $_SESSION['error']='Prepare error'; header('Location: ../messages.php'); exit; }
$stmt->bind_param('isss', $teacher_id, $title, $message, $aud);
$stmt->execute(); $stmt->close();

// Set a success message for the teacher indicating canonical target
if (session_status() === PHP_SESSION_NONE) session_start();
if ($aud === 'students' || $aud === 'all') {
	$_SESSION['success'] = 'Message sent to all students';
} elseif (strpos($aud, 'user:') === 0) {
	$_SESSION['success'] = 'Message sent to ' . htmlspecialchars(substr($aud, 5));
} elseif (strpos($aud, 'section:') === 0) {
	$_SESSION['success'] = 'Message sent to selected section(s)';
}

header('Location: ../messages.php'); exit;
