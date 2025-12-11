<?php
require_once __DIR__ . '/../includes/db.php';
session_start();
if (empty($_SESSION['teacher_id'])) { header('Location: ../../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../activities.php'); exit; }

$id = intval($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$scheduled_at = $_POST['scheduled_at'] ?? null;
$deadline = $_POST['deadline'] ?? null;

if (!$id || !$title) { header('Location: ../activities.php'); exit; }

// verify ownership
$stmt = $connection->prepare('SELECT teacher_id FROM activities WHERE id = ? LIMIT 1');
if (!$stmt) { header('Location: ../activities.php'); exit; }
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); $stmt->close();
if (!$row || (string)$row['teacher_id'] !== (string)$_SESSION['teacher_id']) { header('Location: ../activities.php'); exit; }

$up = $connection->prepare('UPDATE activities SET title = ?, description = ?, scheduled_at = ?, deadline = ? WHERE id = ?');
if ($up) { $up->bind_param('ssssi', $title, $description, $scheduled_at, $deadline, $id); $up->execute(); $up->close(); }

header('Location: ../activities.php?edited=1'); exit;
