<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($connection) || ($connection instanceof mysqli && $connection->connect_error)) {
  http_response_code(500); echo json_encode(['error'=>'Server error','detail'=>'Database connection unavailable']); exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || empty($data['quiz_id'])) { http_response_code(400); echo json_encode(['error'=>'Invalid payload']); exit; }
$quiz_id = intval($data['quiz_id']);
$student_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
if (!$student_id) { http_response_code(401); echo json_encode(['error'=>'Not authenticated']); exit; }

try {
  // Check quiz exists and determine max attempts
  $maxAttempts = 2;
  $q = $connection->prepare('SELECT COALESCE(max_attempts,2) AS max_attempts FROM quizzes WHERE id = ? LIMIT 1');
  if ($q) { $q->bind_param('i',$quiz_id); $q->execute(); $r = $q->get_result(); if ($r && ($row = $r->fetch_assoc())) { $maxAttempts = intval($row['max_attempts']); } if ($r) $r->close(); $q->close(); }

  // count existing completed attempts only (do not count in-progress attempts)
  // Completed attempts are those with answers saved or a score present.
  $countStmt = $connection->prepare("SELECT COUNT(*) AS c FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? AND ((answers IS NOT NULL AND answers <> '') OR score IS NOT NULL)");
  $attemptCount = 0;
  if ($countStmt) { $countStmt->bind_param('ii',$quiz_id,$student_id); $countStmt->execute(); $cres = $countStmt->get_result(); if ($cres && ($crow = $cres->fetch_assoc())) { $attemptCount = intval($crow['c']); } if ($cres) $cres->close(); $countStmt->close(); }

  if ($attemptCount >= $maxAttempts) {
    http_response_code(403); echo json_encode(['error'=>'Attempt limit reached','detail'=>'Maximum attempts reached for this quiz.']); exit;
  }

  // Ensure table exists (best-effort)
  try {
    $tbl = $connection->query("SHOW TABLES LIKE 'quiz_attempts'");
    if (!$tbl || $tbl->num_rows === 0) {
      $create = "CREATE TABLE IF NOT EXISTS `quiz_attempts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `quiz_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `answers` TEXT,
        `score` DECIMAL(5,2) DEFAULT NULL,
        `started_at` DATETIME NULL,
        `duration_seconds` INT NULL,
        `submitted_at` TIMESTAMP NULL,
        INDEX (`quiz_id`), INDEX (`student_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
      $connection->query($create);
    }
  } catch (Exception $e) { /* ignore */ }

  $ins = $connection->prepare('INSERT INTO quiz_attempts (quiz_id, student_id, started_at, submitted_at) VALUES (?, ?, NOW(), NULL)');
  if (!$ins) { http_response_code(500); echo json_encode(['error'=>'Server error (prepare insert)','detail'=>$connection->error]); exit; }
  $ins->bind_param('ii', $quiz_id, $student_id);
  if ($ins->execute() === false) { http_response_code(500); echo json_encode(['error'=>'Insert failed','detail'=>$ins->error]); $ins->close(); exit; }
  $attempt_id = $ins->insert_id;
  $ins->close();

  // fetch the server-side started_at to return to client
  $sstmt = $connection->prepare('SELECT started_at FROM quiz_attempts WHERE id = ? LIMIT 1');
  $started_at = null;
  if ($sstmt) { $sstmt->bind_param('i', $attempt_id); $sstmt->execute(); $sr = $sstmt->get_result(); if ($sr && ($srow = $sr->fetch_assoc())) { $started_at = $srow['started_at']; } if ($sr) $sr->close(); $sstmt->close(); }

  // return attempt id and started_at
  echo json_encode(['ok'=>true,'attempt_id'=>$attempt_id,'started_at'=>$started_at]);
} catch (Exception $e) {
  error_log('start_quiz failed: '.$e->getMessage());
  http_response_code(500); echo json_encode(['error'=>'Server error']);
}

?>
