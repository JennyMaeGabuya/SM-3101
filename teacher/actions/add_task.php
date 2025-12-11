<?php
require_once __DIR__ . '/../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../tasks.php'); exit; }
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$due_date = $_POST['due_date'] ?? null;
$group_allowed = isset($_POST['group_allowed']) ? intval($_POST['group_allowed']) : 0;
$rubric = $_POST['rubric'] ?? '';
$audience = $_POST['audience'] ?? 'students';
$target_value = trim($_POST['target_value'] ?? '');
$teacher_id = $_SESSION['teacher_id'];

if (trim($title) === '') { $_SESSION['error']='Title required'; header('Location: ../tasks.php'); exit; }

// If specific audience is chosen, validate the target (email, username or id) and canonicalize to email
if ($audience === 'specific') {
	if ($target_value === '') {
		$_SESSION['error'] = 'Target student email or ID required for specific send.';
		header('Location: ../tasks.php'); exit;
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
				header('Location: ../tasks.php'); exit;
			}
			$u->close();
		}
	} catch (Exception $e) { error_log('Validate target student failed: '.$e->getMessage()); }
}
// support section audience (comma separated section ids)
if ($audience === 'section') {
	if ($target_value === '') { $_SESSION['error'] = 'Please select at least one section.'; header('Location: ../tasks.php'); exit; }
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
	if (empty($valid)) { $_SESSION['error'] = 'No valid sections selected.'; header('Location: ../tasks.php'); exit; }
	$target_value = implode(',', $valid);
}

// Determine if performance_tasks table has audience/target_value columns
try {
	$hasAudience = false;
	$colRes = $connection->query("SHOW COLUMNS FROM performance_tasks LIKE 'audience'");
	if ($colRes && $colRes->num_rows > 0) { $hasAudience = true; $colRes->close(); }
} catch (Exception $e) { $hasAudience = false; }

if ($hasAudience) {
	$stmt = $connection->prepare("INSERT INTO performance_tasks (teacher_id, title, description, due_date, group_allowed, rubric, audience, target_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
	if (!$stmt) { $_SESSION['error'] = 'Prepare error'; header('Location: ../tasks.php'); exit; }
	$stmt->bind_param('isssisss', $teacher_id, $title, $description, $due_date, $group_allowed, $rubric, $audience, $target_value);
	$stmt->execute();
	$newId = $stmt->insert_id;
	$stmt->close();
} else {
	$stmt = $connection->prepare("INSERT INTO performance_tasks (teacher_id, title, description, due_date, group_allowed, rubric, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
	if (!$stmt) { $_SESSION['error'] = 'Prepare error'; header('Location: ../tasks.php'); exit; }
	$stmt->bind_param('isssis', $teacher_id, $title, $description, $due_date, $group_allowed, $rubric);
	$stmt->execute();
	$newId = $stmt->insert_id;
	$stmt->close();
}

// notify students about new performance task
try {
	$n = $connection->prepare('INSERT INTO notifications (sender_id, title, message, audience, created_at) VALUES (?, ?, ?, ?, NOW())');
	if ($n) {
		$ntitle = 'New Performance Task: ' . $title;
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
			error_log('add_task: notification inserted id=' . $n->insert_id . ' title=' . $ntitle . ' audience=' . $notifAudience);
		} else {
			error_log('add_task: notification insert failed: ' . $connection->error . ' params:' . json_encode([$teacher_id,$ntitle,$notifAudience]));
		}
		$n->close();
	}
} catch (Exception $e) { error_log('Task notif failed: '.$e->getMessage()); }

// Set a visible flash message for the teacher
if (session_status() === PHP_SESSION_NONE) session_start();
if ($audience === 'specific') {
	$_SESSION['success'] = 'Task created and sent to ' . htmlspecialchars($target_value);
} else {
	$_SESSION['success'] = 'Task created and sent to all students';
}

// Diagnostic log to help debug targeted sends
error_log('add_task: created task id=' . ($newId ?? 'NULL') . ' title=' . $title . ' audience=' . $audience . ' target=' . $target_value);

header('Location: ../tasks.php'); exit;
