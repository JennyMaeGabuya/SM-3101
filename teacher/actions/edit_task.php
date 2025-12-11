<?php
require_once __DIR__ . '/../includes/db.php';
session_start();
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../tasks.php'); exit; }

$id = intval($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$due_date = $_POST['due_date'] ?? null;
$group_allowed = isset($_POST['group_allowed']) ? intval($_POST['group_allowed']) : 0;
$rubric = trim($_POST['rubric'] ?? '');

if (!$id || !$title) { header('Location: ../tasks.php'); exit; }

// verify ownership
$stmt = $connection->prepare('SELECT teacher_id FROM performance_tasks WHERE id = ? LIMIT 1');
if (!$stmt) { header('Location: ../tasks.php'); exit; }
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close();
if (!$row || (string)$row['teacher_id'] !== (string)$_SESSION['teacher_id']) { header('Location: ../tasks.php'); exit; }

$up = $connection->prepare('UPDATE performance_tasks SET title = ?, description = ?, due_date = ?, group_allowed = ?, rubric = ? WHERE id = ?');
if ($up) { $up->bind_param('sssisi', $title, $description, $due_date, $group_allowed, $rubric, $id); $up->execute(); $up->close(); }

header('Location: ../tasks.php?edited=1'); exit;
