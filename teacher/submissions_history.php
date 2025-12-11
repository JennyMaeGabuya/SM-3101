<?php
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';
if (!$embed) {
  require_once __DIR__ . '/includes/header.php';
  if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }
}

// ensure session and DB connection are available in embed mode as well
if ($embed) {
  // start session if not already
  if (session_status() === PHP_SESSION_NONE) session_start();
  // include DB connection used by teacher pages
  require_once __DIR__ . '/includes/db.php';
}

$teacher_id = $_SESSION['teacher_id'] ?? null;
if (!$teacher_id) { if (!$embed) { header('Location: ../login.php'); exit; } else { echo '<div>No teacher session</div>'; exit; } }
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;

$rows = [];
try {
  // 1) legacy submissions table for this teacher
  $stmt = $connection->prepare('SELECT id, student_id, resource_type, resource_id, file_path, submitted_at, status, feedback FROM submissions WHERE teacher_id = ? ORDER BY submitted_at DESC');
  if ($stmt) { $stmt->bind_param('i', $teacher_id); $stmt->execute(); $res = $stmt->get_result(); if ($res) { while ($r = $res->fetch_assoc()) { $r['source_table'] = 'submissions'; $rows[] = $r; } $res->close(); } $stmt->close(); }
} catch (Exception $e) { error_log('submissions_history: submissions load failed: '.$e->getMessage()); }

try {
  // 2) task_submissions belonging to this teacher (joined to performance_tasks)
  $sql = "SELECT ts.id, ts.student_id, 'task' AS resource_type, ts.task_id AS resource_id, ts.file_path, ts.created_at AS submitted_at, ts.status, ts.notes AS feedback, ts.grade, ts.graded_by, ts.graded_at, pt.title AS task_title FROM task_submissions ts JOIN performance_tasks pt ON pt.id = ts.task_id WHERE pt.teacher_id = ? ORDER BY ts.created_at DESC";
  $stmt2 = $connection->prepare($sql);
  if ($stmt2) { $stmt2->bind_param('i', $teacher_id); $stmt2->execute(); $res2 = $stmt2->get_result(); if ($res2) { while ($r = $res2->fetch_assoc()) { $r['source_table'] = 'task_submissions'; $rows[] = $r; } $res2->close(); } $stmt2->close(); }
} catch (Exception $e) { error_log('submissions_history: task_submissions load failed: '.$e->getMessage()); }

// 2b) include archived task submissions (moved when a performance task was deleted)
try {
  $sqlTaskArchive = "SELECT tsa.id, tsa.student_id, 'task' AS resource_type, tsa.task_id AS resource_id, tsa.file_path, tsa.created_at AS submitted_at, tsa.status, tsa.notes AS feedback, tsa.grade, tsa.graded_by, tsa.graded_at, tsa.task_title FROM task_submissions_archive tsa WHERE tsa.teacher_id = ? ORDER BY tsa.created_at DESC";
  $stmtTA = $connection->prepare($sqlTaskArchive);
  if ($stmtTA) { $stmtTA->bind_param('i', $teacher_id); $stmtTA->execute(); $resTA = $stmtTA->get_result(); if ($resTA) { while ($r = $resTA->fetch_assoc()) { $r['source_table'] = 'task_submissions_archive'; $rows[] = $r; } $resTA->close(); } $stmtTA->close(); }
} catch (Exception $e) { error_log('submissions_history: archived task_submissions load failed: '.$e->getMessage()); }

try {
  // 3) quiz attempts joined to quizzes owned by this teacher
  $sqlq = "SELECT qa.id, qa.student_id, 'quiz' AS resource_type, qa.quiz_id AS resource_id, NULL AS file_path, qa.submitted_at, NULL AS status, qa.score AS grade, NULL AS feedback, q.title AS quiz_title FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id WHERE q.teacher_id = ? ORDER BY qa.submitted_at DESC";
  $stmt3 = $connection->prepare($sqlq);
  if ($stmt3) { $stmt3->bind_param('i', $teacher_id); $stmt3->execute(); $res3 = $stmt3->get_result(); if ($res3) { while ($r = $res3->fetch_assoc()) { $r['source_table'] = 'quiz_attempts'; $rows[] = $r; } $res3->close(); } $stmt3->close(); }
} catch (Exception $e) { error_log('submissions_history: quiz_attempts load failed: '.$e->getMessage()); }

// 3b) include archived quiz attempts (moved when quiz was deleted)
try {
  $sqlArchive = "SELECT qa.id, qa.student_id, 'quiz' AS resource_type, qa.quiz_id AS resource_id, NULL AS file_path, qa.submitted_at, NULL AS status, qa.score AS grade, NULL AS feedback, qa.quiz_title FROM quiz_attempts_archive qa WHERE qa.teacher_id = ? ORDER BY qa.submitted_at DESC";
  $stmtA = $connection->prepare($sqlArchive);
  if ($stmtA) { $stmtA->bind_param('i', $teacher_id); $stmtA->execute(); $resA = $stmtA->get_result(); if ($resA) { while ($r = $resA->fetch_assoc()) { $r['source_table'] = 'quiz_attempts_archive'; $rows[] = $r; } $resA->close(); } $stmtA->close(); }
} catch (Exception $e) { error_log('submissions_history: archived quiz_attempts load failed: '.$e->getMessage()); }

// map student ids to names/emails like submissions.php
$studentNames = [];
try {
  $ids = [];
  foreach ($rows as $rr) {
    $sid = $rr['student_id'] ?? null;
    if ($sid === null) continue;
    if (is_numeric($sid)) $ids[] = intval($sid);
  }
  $ids = array_values(array_unique($ids));
  if (count($ids) > 0) {
    $hasNameCols = false;
    try { $colRes = $connection->query("SHOW COLUMNS FROM accounts LIKE 'first_name'"); if ($colRes && $colRes->num_rows>0) { $hasNameCols = true; $colRes->close(); } } catch (Exception $e) { $hasNameCols = false; }
    $in = implode(',', array_map('intval', $ids));
    if ($hasNameCols) {
      $sqls = "SELECT accountId, first_name, last_name, username, email FROM accounts WHERE accountId IN ($in)";
    } else {
      $sqls = "SELECT accountId, username, email FROM accounts WHERE accountId IN ($in)";
    }
    $resS = $connection->query($sqls);
    if ($resS) { while ($r = $resS->fetch_assoc()) { $aid = $r['accountId']; $name = ''; if ($hasNameCols) { $fn = trim($r['first_name'] ?? ''); $ln = trim($r['last_name'] ?? ''); if ($fn !== '' || $ln !== '') $name = trim($fn . ' ' . $ln); } if ($name === '') $name = $r['username'] ?? ($r['email'] ?? $aid); $studentNames[intval($aid)] = $name . ' — ' . ($r['email'] ?? ''); } $resS->close(); }
  }
} catch (Exception $e) { error_log('submissions_history: student map failed: '.$e->getMessage()); }

// resolve titles helper
function sh_getTitle($conn, $type, $id) {
  $id = intval($id);
  if ($type === 'quiz') {
    $q = $conn->prepare('SELECT title FROM quizzes WHERE id = ? LIMIT 1'); if ($q) { $q->bind_param('i',$id); $q->execute(); $r = $q->get_result(); if ($r && ($row=$r->fetch_assoc())) { $t = $row['title']; $r->close(); $q->close(); return $t; } if ($r) $r->close(); $q->close(); }
  }
  if ($type === 'task') {
    $q = $conn->prepare('SELECT title FROM performance_tasks WHERE id = ? LIMIT 1'); if ($q) { $q->bind_param('i',$id); $q->execute(); $r = $q->get_result(); if ($r && ($row=$r->fetch_assoc())) { $t = $row['title']; $r->close(); $q->close(); return $t; } if ($r) $r->close(); $q->close(); }
  }
  return ucfirst(htmlspecialchars($type)) . ' #' . intval($id);
}

// sort and paginate
usort($rows, function($a,$b){ $ta = strtotime($a['submitted_at'] ?? '1970-01-01'); $tb = strtotime($b['submitted_at'] ?? '1970-01-01'); return $tb <=> $ta; });
$total = count($rows); $totalPages = max(1, ceil($total / $perPage)); if ($page > $totalPages) $page = $totalPages; $offset = ($page - 1) * $perPage; $paged = array_slice($rows, $offset, $perPage);

?>
<?php
// Prepare the HTML for the table/paged output so it can be returned as either full page or embed
ob_start();
?>
<div class="section-header"><div class="section-title">Submissions History</div></div>
<div class="card">
  <h3>Submissions History</h3>
  <table>
    <thead><tr><th>Student</th><th>Resource</th><th>Title</th><th>Submitted</th><th>Status</th><th>Grade / Feedback</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (count($paged)>0): foreach($paged as $r): ?>
      <tr>
        <td>
          <?php $sidRaw = $r['student_id'] ?? ''; $display = htmlspecialchars($sidRaw); if (is_numeric($sidRaw) && isset($studentNames[intval($sidRaw)])) { $display = htmlspecialchars($studentNames[intval($sidRaw)]); $display .= ' <div style="font-size:0.85em;color:var(--text-secondary);">ID: '.htmlspecialchars($sidRaw).'</div>'; } elseif (filter_var($sidRaw, FILTER_VALIDATE_EMAIL)) { $display = htmlspecialchars($sidRaw); } echo $display; ?>
        </td>
        <td><?php echo htmlspecialchars($r['resource_type']); ?></td>
        <td>
          <?php
            $title = '';
            if (!empty($r['task_title'])) $title = $r['task_title'];
            elseif (!empty($r['quiz_title'])) $title = $r['quiz_title'];
            else $title = sh_getTitle($connection, $r['resource_type'], $r['resource_id']);
            echo htmlspecialchars($title);
          ?>
        </td>
        <td><?php echo htmlspecialchars($r['submitted_at'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($r['status'] ?? ''); ?></td>
        <td>
          <?php if (!empty($r['grade'])) echo '<strong>'.htmlspecialchars($r['grade']).'</strong><br>'; if (!empty($r['feedback'])) echo nl2br(htmlspecialchars($r['feedback'])); if (!empty($r['notes'])) echo nl2br(htmlspecialchars($r['notes'])); ?>
        </td>
        <td>
          <?php if (!empty($r['file_path'])):
              $dl = null;
              $st = $r['source_table'] ?? '';
              if ($st === 'task_submissions' || $st === 'task_submissions_archive') {
                // archive uses a different resource that download.php understands
                if ($st === 'task_submissions_archive') $dl = '../download.php?resource=task_submission_archive&id=' . intval($r['id']);
                else $dl = '../download.php?resource=task_submission&id=' . intval($r['id']);
              } else {
                // generic submissions table
                $dl = '../download.php?resource=submission&id=' . intval($r['id']);
              }
          ?>
            <a href="<?php echo htmlspecialchars($dl); ?>" target="_blank">View</a>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7">No history found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<div style="margin-top:12px; display:flex; gap:8px; align-items:center">
  <div>Page <?php echo $page; ?> / <?php echo $totalPages; ?> (<?php echo $total; ?> results)</div>
  <div style="margin-left:auto">
    <?php if ($page>1): ?><a class="btn btn-secondary" href="?page=<?php echo $page-1; ?><?php echo $embed ? '&embed=1' : ''; ?>">Prev</a><?php endif; ?>
    <?php if ($page<$totalPages): ?><a class="btn btn-secondary" href="?page=<?php echo $page+1; ?><?php echo $embed ? '&embed=1' : ''; ?>">Next</a><?php endif; ?>
  </div>
</div>

<?php
$html = ob_get_clean();
if ($embed) {
  echo $html;
  exit;
} else {
  echo $html;
  require_once __DIR__ . '/includes/footer.php';
}

?>
