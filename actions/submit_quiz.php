<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Defensive: ensure DB connection is available
if (empty($connection) || ($connection instanceof mysqli && $connection->connect_error)) {
  error_log('submit_quiz: DB connection not available');
  http_response_code(500); echo json_encode(['error'=>'Server error','detail'=>'Database connection unavailable']); exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || empty($data['quiz_id'])) {
  http_response_code(400); echo json_encode(['error'=>'Invalid payload']); exit;
}
$quiz_id = intval($data['quiz_id']);
$answers = $data['answers'] ?? [];
$student_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
if (!$student_id) { http_response_code(401); echo json_encode(['error'=>'Not authenticated']); exit; }

$score = null; $correctCount = 0; $mcqCount = 0;
try {
  // load questions (handle 'question_type' vs 'type')
  $existingCols = [];
  try {
    $colRes = $connection->query("SHOW COLUMNS FROM quiz_questions");
    if ($colRes) { while ($crow = $colRes->fetch_assoc()) { $existingCols[] = $crow['Field']; } $colRes->close(); }
  } catch (Exception $e) { /* ignore */ }

  $selectCols = ['id'];
  if (in_array('question_type', $existingCols)) $selectCols[] = 'question_type';
  elseif (in_array('type', $existingCols)) $selectCols[] = 'type AS question_type';
  if (in_array('correct_answer', $existingCols)) $selectCols[] = 'correct_answer';

  $sql = 'SELECT '.implode(',', $selectCols).' FROM quiz_questions WHERE quiz_id = ?';
  $q = $connection->prepare($sql);
  if (!$q) { http_response_code(500); echo json_encode(['error'=>'Server error (prepare select): '.$connection->error]); exit; }
  $q->bind_param('i',$quiz_id);
  $q->execute(); $res = $q->get_result();
  while ($r = $res->fetch_assoc()) {
    $qid = $r['id'];
    $qtype = $r['question_type'] ?? 'mcq';
    if ($qtype === 'mcq') {
      $mcqCount++;
      $correct = isset($r['correct_answer']) ? (string)$r['correct_answer'] : '';
      $qidKey = (string)$qid;
      $given = isset($answers[$qidKey]) ? (string)$answers[$qidKey] : '';
      if ($given !== '' && $correct !== '' && $given === $correct) $correctCount++;
    }
  }
  $q->close();
  if ($mcqCount>0) $score = round(($correctCount / $mcqCount) * 100, 2);
  else $score = 0.0;

  // ensure quiz_attempts table exists (helpful for fresh installs)
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
        `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`quiz_id`), INDEX (`student_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
      $connection->query($create);
    }
  } catch (Exception $e) { /* ignore */ }
  // store attempt
  // Read per-quiz max_attempts (default 2 if not present).
  $maxAttempts = 2;
  try {
    $mstmt = $connection->prepare('SELECT max_attempts FROM quizzes WHERE id = ? LIMIT 1');
    if ($mstmt) {
      $mstmt->bind_param('i', $quiz_id);
      $mstmt->execute();
      $mres = $mstmt->get_result();
      if ($mres && ($mrow = $mres->fetch_assoc())) {
        $maxAttempts = intval($mrow['max_attempts']) > 0 ? intval($mrow['max_attempts']) : 2;
      }
      if ($mres) $mres->close();
      $mstmt->close();
    }
  } catch (Exception $e) { /* ignore */ }

  // enforce max attempts per quiz (count only truly completed attempts)
  // A completed attempt is one where answers were saved or a score is present.
  $checkStmt = $connection->prepare("SELECT COUNT(*) AS cnt FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? AND ((answers IS NOT NULL AND answers <> '') OR score IS NOT NULL)");
  if ($checkStmt) {
    $checkStmt->bind_param('ii', $quiz_id, $student_id);
    $checkStmt->execute();
    $cres = $checkStmt->get_result();
    if ($cres && ($crow = $cres->fetch_assoc())) {
      $attemptCount = intval($crow['cnt']);
    } else {
      $attemptCount = 0;
    }
    $checkStmt->close();
  } else {
    $attemptCount = 0;
  }

  if ($attemptCount >= $maxAttempts) {
    http_response_code(403);
    echo json_encode(['error' => 'Attempt limit reached', 'detail' => 'Maximum attempts reached for this quiz.']);
    exit;
  }

  // If a teacher has graded this resource already (legacy submissions table) then block further attempts.
  try {
    // resolve student email for submissions table fallback
    $studentEmail = null;
    $qEmail = $connection->prepare('SELECT email FROM accounts WHERE accountId = ? LIMIT 1');
    if ($qEmail) { $qEmail->bind_param('i', $student_id); $qEmail->execute(); $r = $qEmail->get_result(); if ($r && ($rr = $r->fetch_assoc())) { $studentEmail = $rr['email']; } if ($r) $r->close(); $qEmail->close(); }
    // Treat any non-pending/submitted state in legacy `submissions` as "graded" for blocking purposes.
    $subsSql = 'SELECT COUNT(*) AS cnt FROM submissions WHERE resource_type = ? AND resource_id = ? AND (student_id = ?';
    if ($studentEmail) $subsSql .= ' OR student_id = ?';
    $subsSql .= ') AND status NOT IN (?,?) LIMIT 1';
    $subsStmt = $connection->prepare($subsSql);
      if ($subsStmt) {
      $resourceType = 'quiz';
      $excludeA = 'submitted';
      $excludeB = 'pending';
      // bind types: resourceType (s), quiz_id (i), student_id (s|string), optional studentEmail (s), excludeA (s), excludeB (s)
      $sid_str = (string)$student_id;
      if ($studentEmail) {
        $subsStmt->bind_param('sissss', $resourceType, $quiz_id, $sid_str, $studentEmail, $excludeA, $excludeB);
      } else {
        $subsStmt->bind_param('sisss', $resourceType, $quiz_id, $sid_str, $excludeA, $excludeB);
      }
      $subsStmt->execute();
      $sr = $subsStmt->get_result();
      if ($sr && ($srow = $sr->fetch_assoc()) && intval($srow['cnt']) > 0) {
        http_response_code(403);
        echo json_encode(['error'=>'Already graded','detail'=>'This resource has already been graded; no further attempts allowed.']);
        $subsStmt->close();
        exit;
      }
      if ($sr) $sr->close();
      $subsStmt->close();
    }
  } catch (Exception $e) { /* ignore non-fatal */ }

  // If client provided an attempt_id (created by start_quiz.php), update that attempt row instead of inserting
  $ansJson = json_encode($answers);
  if (!is_numeric($score)) $score = 0.0;
  $attempt_id = null;
  if (!empty($data['attempt_id']) && is_numeric($data['attempt_id'])) {
    $provided_attempt = intval($data['attempt_id']);
    // verify that the attempt exists and belongs to this student and quiz
    $verify = $connection->prepare('SELECT started_at FROM quiz_attempts WHERE id = ? AND student_id = ? AND quiz_id = ? LIMIT 1');
    if ($verify) {
      $verify->bind_param('iii', $provided_attempt, $student_id, $quiz_id);
      $verify->execute();
      $vres = $verify->get_result();
      if ($vres && ($vrow = $vres->fetch_assoc())) {
        $started_at_db = $vrow['started_at'];
        // compute duration from server-side started_at when available
        $duration_seconds = null;
        if (!empty($started_at_db)) {
          $ts = strtotime($started_at_db);
          if ($ts !== false) $duration_seconds = max(0, time() - $ts);
        } elseif (!empty($data['started_at'])) {
          // fallback to client-provided started_at
          $cts = strtotime($data['started_at']); if ($cts !== false) $duration_seconds = max(0, time() - $cts);
        }
        // update that attempt row
        $up = $connection->prepare('UPDATE quiz_attempts SET answers = ?, score = ?, duration_seconds = ?, submitted_at = NOW() WHERE id = ? LIMIT 1');
        if ($up) {
          // bind duration as integer or null
          if ($duration_seconds === null) {
            $nullDuration = null;
            $up->bind_param('sdii', $ansJson, $score, $nullDuration, $provided_attempt);
          } else {
            $up->bind_param('sdii', $ansJson, $score, $duration_seconds, $provided_attempt);
          }
          if ($up->execute() === false) { http_response_code(500); echo json_encode(['error'=>'Update failed','detail'=>$up->error]); $up->close(); exit; }
          $up->close();
          $attempt_id = $provided_attempt;
        }
      }
      if ($vres) $vres->close();
      $verify->close();
    }
  }
  // If no attempt_id update happened, fall back to inserting a new attempt (legacy behavior)
  if (empty($attempt_id)) {
    // accept optional started_at from client and compute duration_seconds when provided
    $stmt = $connection->prepare('INSERT INTO quiz_attempts (quiz_id, student_id, answers, score, started_at, duration_seconds, submitted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    if (!$stmt) { http_response_code(500); echo json_encode(['error'=>'Server error (prepare insert): '.$connection->error]); exit; }
    $started_at = null; $duration_seconds = null;
    if (!empty($data['started_at'])) {
      $ts = strtotime($data['started_at']);
      if ($ts !== false) { $started_at = date('Y-m-d H:i:s', $ts); $duration_seconds = max(0, time() - $ts); }
    }
    if ($stmt->bind_param('iisdsi', $quiz_id, $student_id, $ansJson, $score, $started_at, $duration_seconds) === false) {
      http_response_code(500); echo json_encode(['error'=>'Server bind error: '.$connection->error]); exit;
    }
    if ($stmt->execute() === false) { http_response_code(500); echo json_encode(['error'=>'Insert failed: '.$stmt->error]); $stmt->close(); exit; }
    $attempt_id = $stmt->insert_id;
    $stmt->close();
  }

  // Additionally, attempt to add a submissions record so teachers see it in submissions.php
  try {
    // get teacher for this quiz
    $teacher_id = null;
    $tstmt = $connection->prepare('SELECT teacher_id FROM quizzes WHERE id = ? LIMIT 1');
    if ($tstmt) { $tstmt->bind_param('i', $quiz_id); $tstmt->execute(); $tres = $tstmt->get_result(); if ($tres) { $trow = $tres->fetch_assoc(); $teacher_id = $trow['teacher_id'] ?? null; $tres->close(); } $tstmt->close(); }

      if ($teacher_id) {
      // ensure submissions table exists (match structure used by teacher/submissions.php)
      $tbl2 = $connection->query("SHOW TABLES LIKE 'submissions'");
      if (!$tbl2 || $tbl2->num_rows === 0) {
        $create2 = "CREATE TABLE IF NOT EXISTS `submissions` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `teacher_id` INT NOT NULL,
          `student_id` VARCHAR(255) NOT NULL,
          `resource_type` VARCHAR(60) NOT NULL,
          `resource_id` INT NOT NULL,
          `file_path` VARCHAR(500),
          `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `status` VARCHAR(40) DEFAULT 'pending',
          `feedback` TEXT,
          `grade` VARCHAR(100) NULL,
          `graded_by` INT NULL,
          `graded_at` DATETIME NULL,
          INDEX (`teacher_id`), INDEX (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $connection->query($create2);
      }

      $ins = $connection->prepare('INSERT INTO submissions (teacher_id, student_id, resource_type, resource_id, submitted_at, status) VALUES (?, ?, ?, ?, NOW(), ?)');
      if ($ins) {
        $resType = 'quiz';
        $status = 'submitted';
        $sid = (string)$student_id;
        $ins->bind_param('issis', $teacher_id, $sid, $resType, $quiz_id, $status);
        $ins->execute();
        $ins->close();
      }
    }
  } catch (Exception $e) { /* non-fatal */ }

  echo json_encode(['ok'=>true,'score'=>$score,'correct'=>$correctCount,'mcq'=>$mcqCount,'attempt_id'=>$attempt_id]);
} catch (Exception $e) {
  error_log('Submit quiz failed: '.$e->getMessage());
  http_response_code(500); echo json_encode(['error'=>'Server error','detail'=>'Internal server error']);
}
?>
