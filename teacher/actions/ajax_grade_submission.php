<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['teacher_id'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$feedback = trim($_POST['feedback'] ?? '');
$status = trim($_POST['status'] ?? '');
$grade = trim($_POST['grade'] ?? '');

if ($submission_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid submission id']); exit; }

// Ensure submissions table has grade/graded_by/graded_at columns
$cols = [];
$rc = $connection->query("SHOW COLUMNS FROM submissions");
if ($rc) { while ($c = $rc->fetch_assoc()) $cols[] = $c['Field']; $rc->close(); }
if (!in_array('grade', $cols) || !in_array('graded_by', $cols) || !in_array('graded_at', $cols)) {
  $alts = [];
  if (!in_array('grade', $cols)) $alts[] = "ADD COLUMN `grade` VARCHAR(100) NULL AFTER `status"."`";
  if (!in_array('graded_by', $cols)) $alts[] = "ADD COLUMN `graded_by` INT NULL AFTER `grade`";
  if (!in_array('graded_at', $cols)) $alts[] = "ADD COLUMN `graded_at` DATETIME NULL AFTER `graded_by`";
  if (!empty($alts)) {
    $sqlAlt = "ALTER TABLE submissions " . implode(', ', $alts);
    try { $connection->query($sqlAlt); } catch (Exception $e) { /* ignore */ }
  }
}

$tid = $_SESSION['teacher_id'];
$graded_by = null; $graded_at = null;
if ($grade !== '') { $graded_by = $tid; $graded_at = date('Y-m-d H:i:s'); }

$stmt = $connection->prepare('UPDATE submissions SET feedback = ?, status = ?, grade = ?, graded_by = ?, graded_at = ? WHERE id = ? AND teacher_id = ?');
if ($stmt) {
  // bind grade and grader info; allow NULLs
  if ($graded_by === null) {
    $gb = null; $ga = null;
  } else {
    $gb = intval($graded_by); $ga = $graded_at;
  }
  $gradeParam = ($grade === '' ? null : $grade);
  $gaParam = ($ga === null ? null : $ga);
  // types: feedback(s), status(s), grade(s), graded_by(i), graded_at(s), submission_id(i), teacher_id(i)
  $stmt->bind_param('sssisii', $feedback, $status, $gradeParam, $gb, $gaParam, $submission_id, $tid);
  if ($stmt->execute()) {
    // include grader display name and timestamp for UI convenience
    $graded_by_name = null;
    // use graded_at set above if provided
    $graded_at = $graded_at ?: null;
    $tid = $_SESSION['teacher_id'];
    // detect whether teachers.id column exists
    $hasId = false;
    try { $colRes = $connection->query("SHOW COLUMNS FROM teachers LIKE 'id'"); if ($colRes && $colRes->num_rows>0) { $hasId = true; $colRes->close(); } } catch (Exception $e) { }
    if ($hasId) {
      $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? OR id = ? LIMIT 1');
      if ($tq) { $tq->bind_param('ii', $tid, $tid); $tq->execute(); $tres = $tq->get_result(); if ($tres && ($tr = $tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); if ($fn !== '' || $ln !== '') $graded_by_name = trim($fn . ' ' . $ln); if (!$graded_by_name) $graded_by_name = $tr['email'] ?? null; } if ($tres) $tres->close(); $tq->close(); }
    } else {
      $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? LIMIT 1');
      if ($tq) { $tq->bind_param('i', $tid); $tq->execute(); $tres = $tq->get_result(); if ($tres && ($tr = $tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); if ($fn !== '' || $ln !== '') $graded_by_name = trim($fn . ' ' . $ln); if (!$graded_by_name) $graded_by_name = $tr['email'] ?? null; } if ($tres) $tres->close(); $tq->close(); }
    }

    echo json_encode(['ok'=>true,'submission_id'=>$submission_id,'status'=>$status,'feedback'=>$feedback,'grade'=>$gradeParam,'graded_by_name'=>$graded_by_name,'graded_at'=>$graded_at]);
    $stmt->close();
    exit;
  } else {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'DB execute failed','detail'=>$stmt->error]);
    $stmt->close();
    exit;
  }
} else {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB prepare failed','detail'=>$connection->error]); exit;
}

?>
