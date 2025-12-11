<?php
require_once __DIR__ . '/../includes/db.php';

session_start();
if (empty($_SESSION['teacher_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ../quizzes.php');
    exit;
}

// ensure quiz exists and belongs to teacher; also fetch title for cleaning notifications
$stmt = $connection->prepare('SELECT teacher_id, title FROM quizzes WHERE id = ? LIMIT 1');
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

$quizTitle = $row['title'] ?? null;

// Archive quiz attempts into quiz_attempts_archive so teachers keep history even after deleting the quiz
try {
        // ensure archive table exists
        $connection->query("CREATE TABLE IF NOT EXISTS `quiz_attempts_archive` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `orig_id` INT,
            `quiz_id` INT,
            `student_id` INT,
            `answers` TEXT,
            `score` DECIMAL(5,2) DEFAULT NULL,
            `submitted_at` DATETIME,
            `teacher_id` INT,
            `quiz_title` VARCHAR(500),
            INDEX(`quiz_id`), INDEX(`student_id`), INDEX(`teacher_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // move attempts into archive with teacher_id and quiz title
        $ins = $connection->prepare('INSERT INTO quiz_attempts_archive (orig_id, quiz_id, student_id, answers, score, submitted_at, teacher_id, quiz_title) SELECT id, quiz_id, student_id, answers, score, submitted_at, ?, ? FROM quiz_attempts WHERE quiz_id = ?');
        if ($ins) {
            $tid = intval($_SESSION['teacher_id']);
            $qtitle = $quizTitle ?: '';
            $ins->bind_param('isi', $tid, $qtitle, $id);
            $ins->execute();
            $ins->close();
        }

        // optionally remove original attempts to avoid duplicate active records
        $delAttempts = $connection->prepare('DELETE FROM quiz_attempts WHERE quiz_id = ?');
        if ($delAttempts) { $delAttempts->bind_param('i', $id); $delAttempts->execute(); $delAttempts->close(); }
} catch (Exception $e) { error_log('delete_quiz: failed to archive quiz_attempts: ' . $e->getMessage()); }

// best-effort: remove related notifications created for this quiz
if (!empty($quizTitle)) {
    try {
        $nTitle = 'New Quiz: ' . $quizTitle;
        $d = $connection->prepare('DELETE FROM notifications WHERE sender_id = ? AND title = ?');
        if ($d) { $d->bind_param('is', $_SESSION['teacher_id'], $nTitle); $d->execute(); $d->close(); }
    } catch (Exception $e) { error_log('delete_quiz: failed to remove notifications: ' . $e->getMessage()); }
}

// remove related submissions (legacy submissions rows pointing to this quiz)
try {
    $delSubs = $connection->prepare('DELETE FROM submissions WHERE resource_type = ? AND resource_id = ? AND teacher_id = ?');
    if ($delSubs) {
        $rtype = 'quiz';
        $tid = intval($_SESSION['teacher_id']);
        $delSubs->bind_param('sii', $rtype, $id, $tid);
        $delSubs->execute();
        $delSubs->close();
    }
} catch (Exception $e) { error_log('delete_quiz: failed to remove submissions: ' . $e->getMessage()); }

// finally remove the quiz row itself
try {
    $delQuiz = $connection->prepare('DELETE FROM quizzes WHERE id = ? AND teacher_id = ? LIMIT 1');
    if ($delQuiz) {
        $tid = intval($_SESSION['teacher_id']);
        $delQuiz->bind_param('ii', $id, $tid);
        $delQuiz->execute();
        $delQuiz->close();
    }
} catch (Exception $e) { error_log('delete_quiz: failed to delete quiz row: ' . $e->getMessage()); }

header('Location: ../quizzes.php?deleted=1');
exit;
