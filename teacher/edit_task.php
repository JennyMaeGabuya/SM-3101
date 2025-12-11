<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: tasks.php'); exit; }

$stmt = $connection->prepare('SELECT id, title, description, due_date, group_allowed, rubric, teacher_id FROM performance_tasks WHERE id = ? LIMIT 1');
if (!$stmt) { echo '<div class="card">Could not load task.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $task = $res->fetch_assoc(); $stmt->close();
if (!$task) { echo '<div class="card">Task not found.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
if ((string)$task['teacher_id'] !== (string)$_SESSION['teacher_id']) { echo '<div class="card">Permission denied.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

?>
<div class="section-header">
  <div class="section-title">Edit Task</div>
  <div><a class="btn" href="tasks.php">Back</a></div>
</div>

<div class="card">
  <form method="post" action="actions/edit_task.php" class="row">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($task['id']); ?>">
    <div>
      <label>Title</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
    </div>
    <div>
      <label>Due Date</label>
      <input type="datetime-local" name="due_date" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($task['due_date']))); ?>">
    </div>
    <div style="grid-column:1/3">
      <label>Description</label>
      <textarea name="description" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
    </div>
    <div>
      <label>Group Allowed?</label>
      <select name="group_allowed"><option value="0" <?php echo $task['group_allowed'] ? '' : 'selected'; ?>>No</option><option value="1" <?php echo $task['group_allowed'] ? 'selected' : ''; ?>>Yes</option></select>
    </div>
    <div style="grid-column:1/3">
      <label>Rubric</label>
      <textarea name="rubric" rows="3"><?php echo htmlspecialchars($task['rubric']); ?></textarea>
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
