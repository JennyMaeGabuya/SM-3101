<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../grade_center.php'); exit; }

$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$grade = trim($_POST['grade'] ?? '');
$status = trim($_POST['status'] ?? '');
$notes = trim($_POST['notes'] ?? null);

if ($submission_id <= 0) { header('Location: ../grade_center.php'); exit; }

// Update only if the teacher owns the related task (join)
$stmt = $connection->prepare(
  'UPDATE task_submissions ts JOIN performance_tasks pt ON pt.id = ts.task_id SET ts.grade = ?, ts.status = ?, ts.notes = COALESCE(?, ts.notes), ts.graded_by = ?, ts.graded_at = NOW() WHERE ts.id = ? AND pt.teacher_id = ?'
);
if ($stmt) {
  $teacher_id = $_SESSION['teacher_id'];
  // grade, status, notes (strings) then graded_by, submission_id, teacher_id (ints)
  $stmt->bind_param('sssiii', $grade, $status, $notes, $teacher_id, $submission_id, $teacher_id);
  $stmt->execute();
  $stmt->close();
  $_SESSION['success'] = 'Submission graded';
} else {
  error_log('grade_task_submission prepare failed: '.$connection->error);
  $_SESSION['error'] = 'Could not grade submission';
}

header('Location: ../grade_center.php'); exit;
?>
