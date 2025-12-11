<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../submissions.php'); exit; }

$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$feedback = $_POST['feedback'] ?? '';
$status = $_POST['status'] ?? '';
$grade = trim($_POST['grade'] ?? '');

if ($submission_id<=0) { header('Location: ../submissions.php'); exit; }

$cols = [];
$rc = $connection->query("SHOW COLUMNS FROM submissions");
if ($rc) { while ($c = $rc->fetch_assoc()) $cols[] = $c['Field']; $rc->close(); }
if (!in_array('grade', $cols) || !in_array('graded_by', $cols) || !in_array('graded_at', $cols)) {
	$alts = [];
	if (!in_array('grade', $cols)) $alts[] = "ADD COLUMN `grade` VARCHAR(100) NULL AFTER `status"."`";
	if (!in_array('graded_by', $cols)) $alts[] = "ADD COLUMN `graded_by` INT NULL AFTER `grade`";
	if (!in_array('graded_at', $cols)) $alts[] = "ADD COLUMN `graded_at` DATETIME NULL AFTER `graded_by`";
	if (!empty($alts)) { $sqlAlt = "ALTER TABLE submissions " . implode(', ', $alts); try { $connection->query($sqlAlt); } catch (Exception $e) { } }
}

$tid = $_SESSION['teacher_id'];
$graded_by = null; $graded_at = null;
if ($grade !== '') { $graded_by = $tid; $graded_at = date('Y-m-d H:i:s'); }

$stmt = $connection->prepare('UPDATE submissions SET feedback = ?, status = ?, grade = ?, graded_by = ?, graded_at = ? WHERE id = ? AND teacher_id = ?');
if ($stmt) {
	$gb = $graded_by === null ? null : intval($graded_by);
	$ga = $graded_at === null ? null : $graded_at;
	$gradeParam = ($grade === '' ? null : $grade);
	$stmt->bind_param('sssisii', $feedback, $status, $gradeParam, $gb, $ga, $submission_id, $tid);
	$stmt->execute(); $stmt->close();
}

header('Location: ../submissions.php'); exit;
