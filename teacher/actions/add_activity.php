<?php
require_once __DIR__ . '/../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../activities.php'); exit; }
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$scheduled_at = $_POST['scheduled_at'] ?? null;
$deadline = $_POST['deadline'] ?? null;
$teacher_id = $_SESSION['teacher_id'];
$audience = $_POST['audience'] ?? 'students';
$target_value = trim($_POST['target_value'] ?? '');

if (trim($title) === '') { $_SESSION['error']='Title required'; header('Location: ../activities.php'); exit; }

// If specific audience is chosen, validate the target (email, username or id) and canonicalize to email
if ($audience === 'specific') {
	if ($target_value === '') {
		$_SESSION['error'] = 'Target student email or ID required for specific send.';
		header('Location: ../activities.php'); exit;
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
				header('Location: ../activities.php'); exit;
			}
			$u->close();
		}
	} catch (Exception $e) { error_log('Validate target student failed: '.$e->getMessage()); }
}
// support section audience (comma separated)
if ($audience === 'section') {
	if ($target_value === '') { $_SESSION['error'] = 'Please select at least one section.'; header('Location: ../activities.php'); exit; }
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
	if (empty($valid)) { $_SESSION['error'] = 'No valid sections selected.'; header('Location: ../activities.php'); exit; }
	$target_value = implode(',', $valid);
}

// detect if activities table has audience/target_value
try {
	$hasAudience = false;
	$colRes = $connection->query("SHOW COLUMNS FROM activities LIKE 'audience'");
	if ($colRes && $colRes->num_rows > 0) { $hasAudience = true; $colRes->close(); }
} catch (Exception $e) { $hasAudience = false; }

if ($hasAudience) {
	$stmt = $connection->prepare("INSERT INTO activities (teacher_id, title, description, scheduled_at, deadline, audience, target_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
	if (!$stmt) { $_SESSION['error']='Prepare error'; header('Location: ../activities.php'); exit; }
	$stmt->bind_param('issssss', $teacher_id, $title, $description, $scheduled_at, $deadline, $audience, $target_value);
	$stmt->execute();
	$newId = $stmt->insert_id;
	$stmt->close();
} else {
	$stmt = $connection->prepare("INSERT INTO activities (teacher_id, title, description, scheduled_at, deadline, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
	if (!$stmt) { $_SESSION['error']='Prepare error'; header('Location: ../activities.php'); exit; }
	$stmt->bind_param('issss', $teacher_id, $title, $description, $scheduled_at, $deadline);
	$stmt->execute();
	$newId = $stmt->insert_id;
	$stmt->close();
}
// notify students
try {
	$n = $connection->prepare('INSERT INTO notifications (sender_id, title, message, audience, created_at) VALUES (?, ?, ?, ?, NOW())');
	if ($n) {
		$ntitle = 'New Activity: ' . $title;
		$nmsg = substr(trim($description),0,500);
		$notifAudience = 'students';
		if ($audience === 'specific' && $target_value !== '') {
			$notifAudience = 'user:' . $target_value;
		} elseif ($audience === 'section' && $target_value !== '') {
			$notifAudience = 'section:' . $target_value;
		}
		$n->bind_param('isss', $teacher_id, $ntitle, $nmsg, $notifAudience);
		$n->execute();
		// diagnostic logging
		if ($n->affected_rows > 0) {
			error_log('add_activity: notification inserted id=' . $n->insert_id . ' title=' . $ntitle . ' audience=' . $notifAudience);
		} else {
			error_log('add_activity: notification insert failed: ' . $connection->error . ' params:' . json_encode([$teacher_id,$ntitle,$notifAudience]));
		}
		$n->close();
	}
} catch (Exception $e) { error_log('Notif failed: '.$e->getMessage()); }

// Set a visible flash message for the teacher
if (session_status() === PHP_SESSION_NONE) session_start();
if ($audience === 'specific') {
	$_SESSION['success'] = 'Activity created and sent to ' . htmlspecialchars($target_value);
} else {
	$_SESSION['success'] = 'Activity created and sent to all students';
}

// Diagnostic log to help debug targeted sends
error_log('add_activity: created activity id=' . ($newId ?? 'NULL') . ' title=' . $title . ' audience=' . $audience . ' target=' . $target_value);

header('Location: ../activities.php'); exit;
