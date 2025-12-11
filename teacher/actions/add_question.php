<?php
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../quizzes.php'); exit; }

$quiz_id = intval($_POST['quiz_id'] ?? 0);
$question_text = trim($_POST['question_text'] ?? '');
$question_type = $_POST['question_type'] ?? 'mcq';
$options = $_POST['options'] ?? null; // expected as JSON or newline-separated
$correct_answer = isset($_POST['correct_answer']) ? trim($_POST['correct_answer']) : null;
// If short-answer field used (different name to avoid collision with hidden inputs), prefer it when relevant
if (($correct_answer === null || $correct_answer === '') && isset($_POST['correct_answer_short'])) {
    $correct_answer = trim($_POST['correct_answer_short']);
}

$return_to = trim($_POST['return_to'] ?? '');

if ($quiz_id <= 0 || $question_text === '') {
    $_SESSION['error'] = 'Invalid input';
    if ($return_to === 'quizzes') header('Location: ../quizzes.php'); else header('Location: ../edit_quiz.php?id=' . $quiz_id);
    exit;
}

// normalize options to JSON for MCQ
if ($question_type === 'mcq') {
    if (is_array($options)) $optArr = $options; else $optArr = preg_split('/\r?\n/', trim($options));
    $optionsJson = json_encode(array_values(array_filter(array_map('trim', $optArr))));
} else {
    $optionsJson = null;
}

// Validation: MCQ questions must have at least one option and a correct answer
if ($question_type === 'mcq') {
    $optsCount = 0;
    if (!empty($optionsJson)) {
        $decoded = json_decode($optionsJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $optsCount = count($decoded);
    }
    if ($optsCount === 0 || $correct_answer === null || $correct_answer === '') {
        $_SESSION['error'] = 'For MCQ questions, please provide at least one option and mark the correct answer.';
        if ($return_to === 'quizzes') header('Location: ../quizzes.php?error=missing_correct'); else header('Location: ../edit_quiz.php?id=' . $quiz_id . '&error=missing_correct');
        exit;
    }
}

// Build an INSERT that's compatible with different schema variants (question_type vs type)
$cols = ['quiz_id', 'question_text'];
$placeholders = ['?', '?'];
$types = 'is';
$values = [$quiz_id, $question_text];

// detect available columns in quiz_questions
$existingCols = [];
try {
    $res = $connection->query("SHOW COLUMNS FROM quiz_questions");
    if ($res) {
        while ($row = $res->fetch_assoc()) $existingCols[] = $row['Field'];
        $res->close();
    }
} catch (Exception $e) { /* ignore - we'll attempt best-effort insert */ }

// question type column name may be 'question_type' or 'type'
$qtypeCol = in_array('question_type', $existingCols) ? 'question_type' : (in_array('type', $existingCols) ? 'type' : null);
if ($qtypeCol) {
    $cols[] = $qtypeCol; $placeholders[]='?'; $types .= 's'; $values[] = $question_type;
}

if (in_array('options', $existingCols)) {
    $cols[] = 'options'; $placeholders[]='?'; $types .= 's'; $values[] = $optionsJson;
}

if (in_array('correct_answer', $existingCols)) {
    $cols[] = 'correct_answer'; $placeholders[]='?'; $types .= 's'; $values[] = $correct_answer;
}

// If created_at exists but has no default, we avoid inserting it and rely on DB default when possible.

$sql = 'INSERT INTO quiz_questions ('.implode(',', $cols).') VALUES ('.implode(',', $placeholders).')';
$stmt = $connection->prepare($sql);
if (!$stmt) {
    // log prepare error for debugging
    @file_put_contents(__DIR__ . '/../../debug_add_question.log', date('[Y-m-d H:i:s]') . " PREPARE FAILED for quiz_id={$quiz_id} - error: {$connection->error}\nPOST:" . print_r($_POST, true) . "\n\n", FILE_APPEND);
    $_SESSION['error'] = 'DB prepare failed: '.htmlspecialchars($connection->error);
    header('Location: ../edit_quiz.php?id=' . $quiz_id);
    exit;
}

// bind params dynamically
$bindParams = array_merge([$types], $values);
// make references
$tmp = [];
foreach ($bindParams as $k => $v) {
    $tmp[$k] = &$bindParams[$k];
}
call_user_func_array([$stmt, 'bind_param'], $tmp);
$stmt->execute();
// log execution result
$execError = $stmt->error;
$insert_id = $stmt->insert_id;
$log = date('[Y-m-d H:i:s]') . " ADD_QUESTION: quiz_id={$quiz_id} insert_id={$insert_id} execError=" . ($execError ?: '(none)') . "\nPOST:" . print_r($_POST, true) . "\n\n";
@file_put_contents(__DIR__ . '/../../debug_add_question.log', $log, FILE_APPEND);
$stmt->close();

// Set a success message and redirect back; include quiz id so the list page can auto-open/highlight
$_SESSION['success'] = 'Question added.';
if ($return_to === 'quizzes') {
    header('Location: ../quizzes.php?added_question=1&quiz_id=' . intval($quiz_id) . '&qid=' . intval($insert_id));
} else {
    header('Location: ../edit_quiz.php?id=' . $quiz_id . '&added_question=1&qid=' . intval($insert_id));
}
exit;
