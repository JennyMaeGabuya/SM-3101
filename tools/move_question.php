<?php
// Local dev helper to move or duplicate a quiz question between quizzes.
// Usage (in browser on the machine running XAMPP):
// 1) Duplicate question id 6 into quiz 6:
//    http://localhost/LearnHub/tools/move_question.php?action=duplicate&question_id=6&target_quiz=6
// 2) Move question id 6 into quiz 6:
//    http://localhost/LearnHub/tools/move_question.php?action=move&question_id=6&target_quiz=6
// Security: only accessible from localhost (127.0.0.1 or ::1).

if (php_sapi_name() !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo "Forbidden: this script can only be run from the local machine."; exit;
    }
}

require_once __DIR__ . '/../connection/dbsConnection.php';
if (!isset($connection) || !$connection) { echo "DB connection not available.\n"; exit; }

$action = $_GET['action'] ?? 'duplicate';
$question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;
$target_quiz = isset($_GET['target_quiz']) ? intval($_GET['target_quiz']) : 0;

if (!$question_id || !$target_quiz) {
    echo "Usage: ?action=move|duplicate&question_id=<id>&target_quiz=<quiz id>\n"; exit;
}

// Fetch the question
$stmt = $connection->prepare('SELECT * FROM quiz_questions WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $question_id);
$stmt->execute();
$res = $stmt->get_result();
$question = $res->fetch_assoc();
$stmt->close();

if (!$question) { echo "Question id {$question_id} not found."; exit; }

if ($action === 'move') {
    $u = $connection->prepare('UPDATE quiz_questions SET quiz_id = ? WHERE id = ?');
    $u->bind_param('ii', $target_quiz, $question_id);
    if ($u->execute()) {
        echo "Moved question {$question_id} to quiz {$target_quiz}.\n";
    } else {
        echo "Failed to move: " . $connection->error;
    }
    $u->close();
    exit;
}

// duplicate
$cols = [];
$vals = [];
$placeholders = [];
$types = '';

// Build insert from available columns on the row, but override quiz_id
foreach ($question as $col => $val) {
    if ($col === 'id') continue; // skip PK
    if ($col === 'quiz_id') continue; // we'll set target explicitly
    $cols[] = "`$col`";
    $placeholders[] = '?';
    $vals[] = $val;
    // determine param type
    if (is_null($val)) $types .= 's';
    elseif (is_int($val)) $types .= 'i';
    else $types .= 's';
}
// add quiz_id at start
array_unshift($cols, '`quiz_id`');
array_unshift($placeholders, '?');
array_unshift($vals, $target_quiz);
array_unshift($types, 'i');

$sql = 'INSERT INTO quiz_questions ('.implode(',', $cols).') VALUES ('.implode(',', $placeholders).')';
$ins = $connection->prepare($sql);
if (!$ins) { echo "Prepare failed: " . $connection->error; exit; }

// bind dynamically
$params = array_merge([$types], $vals);
$tmp = [];
foreach ($params as $k => $v) { $tmp[$k] = &$params[$k]; }
call_user_func_array([$ins, 'bind_param'], $tmp);

if ($ins->execute()) {
    echo "Duplicated question {$question_id} into quiz {$target_quiz} as id {$ins->insert_id}.\n";
} else {
    echo "Insert failed: " . $ins->error;
}
$ins->close();

?>