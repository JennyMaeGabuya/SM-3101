<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header('Location: ../quizzes.php'); exit; }

try {
    // ensure the teacher owns the quiz that contains this question
    $q = $connection->prepare('SELECT qq.quiz_id, q.teacher_id FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.id WHERE qq.id = ? LIMIT 1');
    if (!$q) { header('Location: ../quizzes.php'); exit; }
    $q->bind_param('i', $id); $q->execute(); $res = $q->get_result(); $row = $res->fetch_assoc(); $q->close();
    if (!$row || (int)$row['teacher_id'] !== (int)$_SESSION['teacher_id']) { header('Location: ../quizzes.php'); exit; }

    $del = $connection->prepare('DELETE FROM quiz_questions WHERE id = ?');
    if ($del) { $del->bind_param('i',$id); $del->execute(); $del->close(); }
} catch (Exception $e) { error_log('Delete question failed: '.$e->getMessage()); }

header('Location: ../edit_quiz.php?id=' . ($row['quiz_id'] ?? ''));
exit;
