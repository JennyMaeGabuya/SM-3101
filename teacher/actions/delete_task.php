<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id<=0) { header('Location: ../tasks.php'); exit; }

// fetch the task title so we can remove related notifications
$taskTitle = null;
$s = $connection->prepare('SELECT title FROM performance_tasks WHERE id = ? AND teacher_id = ? LIMIT 1');
if ($s) {
	$s->bind_param('ii', $id, $_SESSION['teacher_id']);
	$s->execute();
	$res = $s->get_result();
	if ($res) {
		$r = $res->fetch_assoc();
		if ($r) $taskTitle = $r['title'];
		$res->close();
	}
	$s->close();
}

// delete related notifications (best-effort). Notifications created by add_task use title prefix 'New Performance Task: '
if (!empty($taskTitle)) {
	try {
		$ntitle = 'New Performance Task: ' . $taskTitle;
		$d = $connection->prepare('DELETE FROM notifications WHERE sender_id = ? AND title = ?');
		if ($d) { $d->bind_param('is', $_SESSION['teacher_id'], $ntitle); $d->execute(); $d->close(); }
	} catch (Exception $e) { error_log('delete_task: failed to remove notifications: '.$e->getMessage()); }
}

// Archive task_submissions so teacher history preserves submissions even after deleting the task
try {
	$connection->query("CREATE TABLE IF NOT EXISTS `task_submissions_archive` (
		`id` INT AUTO_INCREMENT PRIMARY KEY,
		`orig_id` INT,
		`task_id` INT,
		`student_id` INT,
		`file_path` VARCHAR(500),
		`notes` TEXT,
		`grade` VARCHAR(100),
		`status` VARCHAR(60),
		`graded_by` INT,
		`graded_at` DATETIME,
		`created_at` DATETIME,
		`teacher_id` INT,
		`task_title` VARCHAR(500),
		INDEX(`task_id`), INDEX(`student_id`), INDEX(`teacher_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

	// Copy submissions into archive and remove originals
	$escapedTitle = $connection->real_escape_string($taskTitle ?: '');
	$connection->query("INSERT INTO task_submissions_archive (orig_id, task_id, student_id, file_path, notes, grade, status, graded_by, graded_at, created_at, teacher_id, task_title) SELECT id, task_id, student_id, file_path, notes, grade, status, graded_by, graded_at, created_at, ".intval(
		$_SESSION['teacher_id']).", '".$escapedTitle."' FROM task_submissions WHERE task_id = ".intval($id));
	// remove original submissions
	$delSubs = $connection->prepare('DELETE FROM task_submissions WHERE task_id = ?'); if ($delSubs) { $delSubs->bind_param('i', $id); $delSubs->execute(); $delSubs->close(); }
} catch (Exception $e) { error_log('delete_task: archive task_submissions failed: '.$e->getMessage()); }

// finally remove the task row itself
$stmt = $connection->prepare('DELETE FROM performance_tasks WHERE id = ? AND teacher_id = ?');
if ($stmt) { $stmt->bind_param('ii',$id,$_SESSION['teacher_id']); $stmt->execute(); $stmt->close(); }

header('Location: ../tasks.php'); exit;
