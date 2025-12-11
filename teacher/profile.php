<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$tid = $_SESSION['teacher_id'];
// check if `avatar` column exists to avoid fatal errors on older schemas
$hasAvatar = false;
$col = $connection->prepare("SHOW COLUMNS FROM teachers LIKE 'avatar'");
if ($col) {
  $col->execute();
  $cres = $col->get_result();
  if ($cres && $cres->num_rows > 0) $hasAvatar = true;
  $col->close();
}

// Select first_name/last_name when available (some schemas use first/last, others use full_name)
// Avoid referencing a non-existent `id` column. Detect presence first and query accordingly.
$hasId = false;
$col = $connection->prepare("SHOW COLUMNS FROM teachers LIKE 'id'");
if ($col) {
  $col->execute();
  $cres = $col->get_result();
  if ($cres && $cres->num_rows > 0) $hasId = true;
  $col->close();
}

if ($hasId) {
  $select = 'SELECT id, teacher_id, email, first_name, last_name' . ($hasAvatar ? ', avatar' : '') . ' FROM teachers WHERE id = ? OR teacher_id = ? LIMIT 1';
  $stmt = $connection->prepare($select);
  if ($stmt) {
    $stmt->bind_param('ss', $tid, $tid);
    $stmt->execute();
    $res = $stmt->get_result();
    $teacher = $res->fetch_assoc();
    $stmt->close();
  } else {
    $teacher = null;
  }
} else {
  $select = 'SELECT teacher_id, email, first_name, last_name' . ($hasAvatar ? ', avatar' : '') . ' FROM teachers WHERE teacher_id = ? LIMIT 1';
  $stmt = $connection->prepare($select);
  if ($stmt) {
    $stmt->bind_param('s', $tid);
    $stmt->execute();
    $res = $stmt->get_result();
    $teacher = $res->fetch_assoc();
    $stmt->close();
  } else {
    $teacher = null;
  }
}

// Derive a display name and initials for fallback avatar
$teacherFullName = '';
$teacherInitials = 'T';
if ($teacher) {
  $nameParts = array_filter([($teacher['first_name'] ?? ''), ($teacher['last_name'] ?? '')]);
  $teacherFullName = $nameParts ? implode(' ', $nameParts) : ($teacher['email'] ?? 'Teacher');
  $parts = preg_split('/\s+/', trim($teacherFullName));
  $first = strtoupper($parts[0][0] ?? 'T');
  $second = strtoupper($parts[1][0] ?? '');
  $teacherInitials = substr($first . $second, 0, 2);
}


?>
<div class="section-header">
  <div class="section-title">Profile</div>
  <div><a class="btn" href="index.php">Back</a></div>
</div>

<div class="card">
  <h3>Teacher Profile</h3>
  <?php if ($teacher): ?>
    <div style="display:flex;gap:18px;align-items:center">
      <div>
        <?php if (!empty($teacher['avatar']) && file_exists(__DIR__ . '/../public/uploads/teacher/' . $teacher['avatar'])): ?>
          <img src="/LearnHub/public/uploads/teacher/<?php echo rawurlencode($teacher['avatar']); ?>" alt="avatar" style="width:120px;height:120px;border-radius:10px;object-fit:cover;" />
        <?php else: ?>
          <div style="width:120px;height:120px;border-radius:10px;background:var(--primary-gold);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;color:#111"><?php echo htmlspecialchars($teacherInitials); ?></div>
        <?php endif; ?>
      </div>
      <div style="flex:1">
        <form method="post" action="actions/upload_avatar.php" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start">
          <div>
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($teacherFullName ?? ($teacher['full_name'] ?? '')); ?>">
          </div>
          <div>
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>" disabled>
          </div>
          <div style="grid-column:1/3">
            <label>Replace Avatar</label>
            <input type="file" name="avatar">
          </div>
          <div>
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">Save Profile</button>
          </div>
        </form>
      </div>
    </div>
  <?php else: ?>
    <div>Could not load profile.</div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
