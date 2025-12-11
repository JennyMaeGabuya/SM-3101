<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: activities.php'); exit; }

$stmt = $connection->prepare('SELECT id, title, description, scheduled_at, deadline, teacher_id FROM activities WHERE id = ? LIMIT 1');
if (!$stmt) { echo '<div class="card">Could not load activity.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $act = $res->fetch_assoc(); $stmt->close();
if (!$act) { echo '<div class="card">Activity not found.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
if ((string)$act['teacher_id'] !== (string)$_SESSION['teacher_id']) { echo '<div class="card">Permission denied.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

?>
<div class="section-header">
  <div class="section-title">Edit Activity</div>
  <div><a class="btn" href="activities.php">Back</a></div>
</div>

<div class="card">
  <form method="post" action="actions/edit_activity.php" class="row">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($act['id']); ?>">
    <div>
      <label>Title</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($act['title']); ?>" required>
    </div>
    <div>
      <label>Scheduled At</label>
      <input type="datetime-local" name="scheduled_at" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($act['scheduled_at']))); ?>">
    </div>
    <div style="grid-column:1/3">
      <label>Description</label>
      <textarea name="description" rows="4"><?php echo htmlspecialchars($act['description']); ?></textarea>
    </div>
    <div>
      <label>Deadline</label>
      <input type="datetime-local" name="deadline" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($act['deadline']))); ?>">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
