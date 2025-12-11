<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id) { http_response_code(401); echo json_encode(['error'=>'Not authenticated']); exit; }

$rawId = $_POST['submission_id'] ?? null;
if (!$rawId) { http_response_code(400); echo json_encode(['error'=>'Missing submission_id']); exit; }
$sid = intval($rawId);

try {
  // verify submission exists and belongs to this teacher
  $q = $connection->prepare('SELECT id FROM submissions WHERE id = ? AND teacher_id = ? LIMIT 1');
  if (!$q) { http_response_code(500); echo json_encode(['error'=>'Server prepare failed']); exit; }
  $q->bind_param('ii', $sid, $teacher_id);
  $q->execute();
  $res = $q->get_result();
  if (!($res && $res->num_rows>0)) { http_response_code(404); echo json_encode(['error'=>'Not found or permission denied']); exit; }
  $res->close(); $q->close();

  $d = $connection->prepare('DELETE FROM submissions WHERE id = ? AND teacher_id = ?');
  if (!$d) { http_response_code(500); echo json_encode(['error'=>'Server prepare failed (delete)']); exit; }
  $d->bind_param('ii', $sid, $teacher_id);
  if ($d->execute() === false) { http_response_code(500); echo json_encode(['error'=>'Delete failed','detail'=>$d->error]); exit; }
  $d->close();

  echo json_encode(['ok'=>true]);
} catch (Exception $e) {
  error_log('ajax_delete_submission error: '.$e->getMessage());
  http_response_code(500); echo json_encode(['error'=>'Server error']);
}

?>
