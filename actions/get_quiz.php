<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../connection/dbsConnection.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { echo json_encode(['error'=>'missing id']); exit; }

$out = ['quiz'=>null,'questions'=>[]];
try {
  $qs = $connection->prepare('SELECT id,title,description,scheduled_at,deadline,teacher_id FROM quizzes WHERE id = ? LIMIT 1');
  if ($qs) { $qs->bind_param('i',$id); $qs->execute(); $r = $qs->get_result(); if ($r) { $out['quiz'] = $r->fetch_assoc(); $r->close(); } $qs->close(); }
  if ($out['quiz']) {
    $qst = $connection->prepare('SELECT id,question_text,question_type,options FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC');
    if ($qst) { $qst->bind_param('i',$id); $qst->execute(); $resq = $qst->get_result(); if ($resq) { while ($row = $resq->fetch_assoc()) { if (!empty($row['options'])) $row['options_arr'] = json_decode($row['options'], true); $out['questions'][] = $row; } $resq->close(); } $qst->close(); }
  }
} catch (Exception $e) { error_log('Get quiz failed: '.$e->getMessage()); }

echo json_encode($out);
exit;
