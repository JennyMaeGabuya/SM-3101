<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['teacher_id'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$grade = trim($_POST['grade'] ?? '');
$status = trim($_POST['status'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($submission_id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid submission id']); exit; }

// Update only if teacher owns the linked task
$stmt = $connection->prepare('UPDATE task_submissions ts JOIN performance_tasks pt ON pt.id = ts.task_id SET ts.grade = ?, ts.status = ?, ts.notes = COALESCE(?, ts.notes), ts.graded_by = ?, ts.graded_at = NOW() WHERE ts.id = ? AND pt.teacher_id = ?');
if ($stmt) {
  $teacher_id = $_SESSION['teacher_id'];
  $stmt->bind_param('sssiii', $grade, $status, $notes, $teacher_id, $submission_id, $teacher_id);
  if ($stmt->execute()) {
    $stmt->close();
    // Fetch graded metadata
    $meta = ['graded_by'=>null,'graded_at'=>null,'graded_by_name'=>null];
    $mstmt = $connection->prepare('SELECT graded_by, graded_at FROM task_submissions WHERE id = ? LIMIT 1');
    if ($mstmt) {
      $mstmt->bind_param('i', $submission_id);
      if ($mstmt->execute()) {
        $mres = $mstmt->get_result();
        if ($mres && ($mr = $mres->fetch_assoc())) {
          $meta['graded_by'] = $mr['graded_by'];
          $meta['graded_at'] = $mr['graded_at'];
        }
        if ($mres) $mres->close();
      }
      $mstmt->close();
    }
    // look up teacher display name if graded_by present
    if (!empty($meta['graded_by'])) {
      $tb = intval($meta['graded_by']);
      $tn = null;
      // detect teachers.id column
      $hasId = false;
      try { $colRes = $connection->query("SHOW COLUMNS FROM teachers LIKE 'id'"); if ($colRes && $colRes->num_rows>0) { $hasId = true; $colRes->close(); } } catch (Exception $e) { }
      if ($hasId) {
        $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? OR id = ? LIMIT 1');
        if ($tq) { $tq->bind_param('ii', $tb, $tb); if ($tq->execute()) { $tres = $tq->get_result(); if ($tres && ($tr = $tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); if ($fn !== '' || $ln !== '') $tn = trim($fn . ' ' . $ln); if (!$tn) $tn = $tr['email'] ?? null; } if ($tres) $tres->close(); } $tq->close(); }
      } else {
        $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? LIMIT 1');
        if ($tq) { $tq->bind_param('i', $tb); if ($tq->execute()) { $tres = $tq->get_result(); if ($tres && ($tr = $tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); if ($fn !== '' || $ln !== '') $tn = trim($fn . ' ' . $ln); if (!$tn) $tn = $tr['email'] ?? null; } if ($tres) $tres->close(); } $tq->close(); }
      }
      if ($tn) $meta['graded_by_name'] = $tn;
    }

    echo json_encode(array_merge(['ok'=>true,'submission_id'=>$submission_id,'grade'=>$grade,'status'=>$status,'notes'=>$notes], $meta));
    exit;
  } else {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB execute failed','detail'=>$stmt->error]); $stmt->close(); exit;
  }
} else {
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB prepare failed','detail'=>$connection->error]); exit;
}

?>
