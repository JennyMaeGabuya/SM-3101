<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../quizzes.php'); exit; }

$id = intval($_POST['id'] ?? 0);
$text = trim($_POST['question_text'] ?? '');
$type = $_POST['question_type'] ?? 'mcq';
$options = $_POST['options'] ?? null;
$correct = $_POST['correct_answer'] ?? null;

if ($id <= 0) { header('Location: ../quizzes.php'); exit; }

try {
    // verify ownership
    $v = $connection->prepare('SELECT q.teacher_id, qq.quiz_id FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.id WHERE qq.id = ? LIMIT 1');
    if (!$v) { header('Location: ../quizzes.php'); exit; }
    $v->bind_param('i',$id); $v->execute(); $res = $v->get_result(); $row = $res->fetch_assoc(); $v->close();
    if (!$row || (int)$row['teacher_id'] !== (int)$_SESSION['teacher_id']) { header('Location: ../quizzes.php'); exit; }

    $optsJson = null;
    if ($type === 'mcq') {
        if (is_array($options)) $optArr = $options; else $optArr = preg_split('/\r?\n/', trim($options));
        $optsJson = json_encode(array_values(array_filter(array_map('trim', $optArr))));
    }

    $up = $connection->prepare('UPDATE quiz_questions SET question_text = ?, question_type = ?, options = ?, correct_answer = ? WHERE id = ?');
    if ($up) { $up->bind_param('ssssi', $text, $type, $optsJson, $correct, $id); $up->execute(); $up->close(); }
} catch (Exception $e) { error_log('Edit question failed: '.$e->getMessage()); }

header('Location: ../edit_quiz.php?id=' . ($row['quiz_id'] ?? ''));
exit;
