<?php
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../quizzes.php');
    exit;
}

$id = $_POST['id'] ?? null;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$scheduled_at = $_POST['scheduled_at'] ?? null;
$deadline = $_POST['deadline'] ?? null;
$max_attempts = isset($_POST['max_attempts']) ? intval($_POST['max_attempts']) : null;
$duration_minutes = isset($_POST['duration_minutes']) && $_POST['duration_minutes']!=='' ? intval($_POST['duration_minutes']) : null;

if (!$id || !$title) {
    header('Location: ../quizzes.php');
    exit;
}

// ensure quiz exists and belongs to teacher
$stmt = $connection->prepare('SELECT teacher_id FROM quizzes WHERE id = ? LIMIT 1');
if (!$stmt) { header('Location: ../quizzes.php'); exit; }
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row || (string)$row['teacher_id'] !== (string)$_SESSION['teacher_id']) {
    header('Location: ../quizzes.php');
    exit;
}

$hasMax = false;
try { $c = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'max_attempts'"); if ($c && $c->num_rows>0) { $hasMax = true; $c->close(); } } catch (Exception $e) { }
// detect duration column presence
$hasDuration = false;
try { $c2 = $connection->query("SHOW COLUMNS FROM quizzes LIKE 'duration_minutes'"); if ($c2 && $c2->num_rows>0) { $hasDuration = true; $c2->close(); } } catch (Exception $e) { }

// Build update query dynamically depending on available columns
if ($hasMax && $hasDuration) {
    $up = $connection->prepare('UPDATE quizzes SET title = ?, description = ?, scheduled_at = ?, deadline = ?, max_attempts = ?, duration_minutes = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('ssssiii', $title, $description, $scheduled_at, $deadline, $max_attempts, $duration_minutes, $id);
        $up->execute();
        $up->close();
    }
} else if ($hasMax) {
    $up = $connection->prepare('UPDATE quizzes SET title = ?, description = ?, scheduled_at = ?, deadline = ?, max_attempts = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('ssssii', $title, $description, $scheduled_at, $deadline, $max_attempts, $id);
        $up->execute();
        $up->close();
    }
} else if ($hasDuration) {
    $up = $connection->prepare('UPDATE quizzes SET title = ?, description = ?, scheduled_at = ?, deadline = ?, duration_minutes = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('ssssii', $title, $description, $scheduled_at, $deadline, $duration_minutes, $id);
        $up->execute();
        $up->close();
    }
} else {
    $up = $connection->prepare('UPDATE quizzes SET title = ?, description = ?, scheduled_at = ?, deadline = ? WHERE id = ?');
    if ($up) {
        $up->bind_param('ssssi', $title, $description, $scheduled_at, $deadline, $id);
        $up->execute();
        $up->close();
    }
}

header('Location: ../quizzes.php?edited=1');
exit;
