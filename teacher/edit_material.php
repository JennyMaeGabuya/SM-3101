<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$id = $_GET['id'] ?? null;
if (!$id) { header('Location: materials.php'); exit; }

$stmt = $connection->prepare('SELECT id, title, description, file_path, link, teacher_id FROM learning_materials WHERE id = ? LIMIT 1');
if (!$stmt) { echo '<div class="card">Could not load material.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $mat = $res->fetch_assoc(); $stmt->close();
if (!$mat) { echo '<div class="card">Material not found.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
if ((string)$mat['teacher_id'] !== (string)$_SESSION['teacher_id']) { echo '<div class="card">Permission denied.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

?>
<div class="section-header">
  <div class="section-title">Edit Material</div>
  <div><a class="btn" href="materials.php">Back</a></div>
</div>

<div class="card">
  <form method="post" action="actions/edit_material.php" class="row" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($mat['id']); ?>">
    <div>
      <label>Title</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($mat['title']); ?>" required>
    </div>
    <div>
      <label>Replace File (optional)</label>
      <input type="file" name="file">
    </div>
    <div style="grid-column:1/3">
      <label>Link (optional)</label>
      <input type="url" name="link" value="<?php echo htmlspecialchars($mat['link']); ?>">
    </div>
    <div style="grid-column:1/3">
      <label>Description</label>
      <textarea name="description" rows="3"><?php echo htmlspecialchars($mat['description']); ?></textarea>
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
