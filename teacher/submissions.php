<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }

$teacher_id = $_SESSION['teacher_id'];

// optional student filter (by id or email)
$studentFilter = trim($_GET['student'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

$rows = [];

// Defensive: ensure `submissions` table has grading columns to avoid SQL errors on older DBs
try {
  $existingCols = [];
  $rc = $connection->query("SHOW COLUMNS FROM submissions");
  if ($rc) { while ($c = $rc->fetch_assoc()) $existingCols[] = $c['Field']; $rc->close(); }
  $alts = [];
  if (!in_array('grade', $existingCols)) $alts[] = "ADD COLUMN `grade` VARCHAR(100) NULL AFTER `status`";
  if (!in_array('graded_by', $existingCols)) $alts[] = "ADD COLUMN `graded_by` INT NULL AFTER `grade`";
  if (!in_array('graded_at', $existingCols)) $alts[] = "ADD COLUMN `graded_at` DATETIME NULL AFTER `graded_by`";
  if (!empty($alts)) { $sqlAlt = "ALTER TABLE submissions " . implode(', ', $alts); try { $connection->query($sqlAlt); } catch (Exception $e) { /* ignore */ } }
} catch (Exception $e) { /* non-fatal */ }

// If a student filter is provided, resolve matching accountIds/emails by name/email/username
$candidateIds = [];
$candidateEmails = [];
if ($studentFilter !== '') {
  try {
    $like = '%'.$connection->real_escape_string($studentFilter).'%';
    // prefer first_name/last_name when available
    $hasNameCols = false;
    try { $colRes = $connection->query("SHOW COLUMNS FROM accounts LIKE 'first_name'"); if ($colRes && $colRes->num_rows>0) { $hasNameCols = true; $colRes->close(); } } catch (Exception $e) { $hasNameCols = false; }
    if ($hasNameCols) {
      $q = $connection->prepare("SELECT accountId, email FROM accounts WHERE CONCAT_WS(' ', first_name, last_name, username, email) LIKE ? OR email LIKE ? OR username LIKE ? LIMIT 200");
      if ($q) { $q->bind_param('sss', $like, $like, $like); $q->execute(); $res = $q->get_result(); while ($r = $res->fetch_assoc()) { $candidateIds[] = intval($r['accountId']); $candidateEmails[] = $r['email']; } $q->close(); }
    } else {
      $q = $connection->prepare("SELECT accountId, email FROM accounts WHERE username LIKE ? OR email LIKE ? LIMIT 200");
      if ($q) { $q->bind_param('ss', $like, $like); $q->execute(); $res = $q->get_result(); while ($r = $res->fetch_assoc()) { $candidateIds[] = intval($r['accountId']); $candidateEmails[] = $r['email']; } $q->close(); }
    }
  } catch (Exception $e) { error_log('grade_center: candidate lookup failed: '.$e->getMessage()); }
}

// 1) Load generic submissions (quizzes / whatever writes to `submissions`)
try {
  // Build submissions query. If we resolved candidate IDs/emails use IN filters; otherwise fall back to simple equals/LIKE
  if (!empty($candidateIds) || !empty($candidateEmails)) {
    $parts = [];
    $parts[] = "teacher_id = " . intval($teacher_id);
    $conds = [];
    if (!empty($candidateIds)) {
      $idsSql = implode(',', array_map('intval', $candidateIds));
      $conds[] = "student_id IN ($idsSql)";
    }
    if (!empty($candidateEmails)) {
      $emailsEsc = array_map(function($e){ return "'".addslashes($e)."'"; }, $candidateEmails);
      $conds[] = "student_id IN (".implode(',', $emailsEsc).")";
    }
    // also allow matching raw filter as fallback
    $conds[] = "student_id LIKE '%".addslashes($studentFilter)."%'";
    $where = implode(' OR ', $conds);
    $sqlStr = "SELECT id, student_id, resource_type, resource_id, file_path, submitted_at, status, feedback, grade, graded_by, graded_at FROM submissions WHERE (".$where.") AND teacher_id = " . intval($teacher_id) . " ORDER BY submitted_at DESC";
    $res = $connection->query($sqlStr);
    if ($res) {
      while ($r = $res->fetch_assoc()) { $r['source_table'] = 'submissions'; $rows[] = $r; }
      $res->close();
    }
  } else {
    $sql = "SELECT id, student_id, resource_type, resource_id, file_path, submitted_at, status, feedback, grade, graded_by, graded_at FROM submissions WHERE teacher_id = ?";
    if ($studentFilter !== '') $sql .= " AND (student_id = ? OR student_id LIKE ?)";
    $stmt = $connection->prepare($sql);
    if ($stmt) {
      if ($studentFilter !== '') {
        $like = '%'.$studentFilter.'%';
        $stmt->bind_param('iss', $teacher_id, $studentFilter, $like);
      } else {
        $stmt->bind_param('i', $teacher_id);
      }
      $stmt->execute();
      $res = $stmt->get_result();
      while ($r = $res->fetch_assoc()) {
        $r['source_table'] = 'submissions';
        $r['submitted_at'] = $r['submitted_at'] ?: null;
        $rows[] = $r;
      }
      $stmt->close();
    }
  }
} catch (Exception $e) { error_log('grade_center: submissions load: '.$e->getMessage()); }

// 2) Load task_submissions joined to performance_tasks so we only show tasks owned by this teacher
try {
  if (!empty($candidateIds) || !empty($candidateEmails)) {
    $conds = [];
    if (!empty($candidateIds)) {
      $conds[] = 'ts.student_id IN (' . implode(',', array_map('intval',$candidateIds)) . ')';
    }
    if (!empty($candidateEmails)) {
      $emailsEsc = array_map(function($e){ return "'".addslashes($e)."'"; }, $candidateEmails);
      $conds[] = 'ts.student_id IN (' . implode(',', $emailsEsc) . ')';
    }
    $conds[] = "ts.student_id LIKE '%".addslashes($studentFilter)."%'";
    $where = implode(' OR ', $conds);
  $sql2 = "SELECT ts.id, ts.student_id, 'task' AS resource_type, ts.task_id AS resource_id, ts.file_path, ts.created_at AS submitted_at, ts.status, ts.notes AS feedback, ts.grade, ts.graded_by, ts.graded_at FROM task_submissions ts JOIN performance_tasks pt ON pt.id = ts.task_id WHERE pt.teacher_id = " . intval($teacher_id) . " AND (".$where.") ORDER BY ts.created_at DESC";
    $res2 = $connection->query($sql2);
    if ($res2) { while ($r = $res2->fetch_assoc()) { $r['source_table'] = 'task_submissions'; $rows[] = $r; } $res2->close(); }
  } else {
  $sql2 = "SELECT ts.id, ts.student_id, 'task' AS resource_type, ts.task_id AS resource_id, ts.file_path, ts.created_at AS submitted_at, ts.status, ts.notes AS feedback, ts.grade, ts.graded_by, ts.graded_at FROM task_submissions ts JOIN performance_tasks pt ON pt.id = ts.task_id WHERE pt.teacher_id = ?";
    if ($studentFilter !== '') $sql2 .= " AND (ts.student_id = ? OR ts.student_id LIKE ? )";
    $stmt2 = $connection->prepare($sql2);
    if ($stmt2) {
      if ($studentFilter !== '') {
        $like2 = '%'.$studentFilter.'%';
        $stmt2->bind_param('iss', $teacher_id, $studentFilter, $like2);
      } else {
        $stmt2->bind_param('i', $teacher_id);
      }
      $stmt2->execute();
      $res2 = $stmt2->get_result();
      while ($r = $res2->fetch_assoc()) {
        $r['source_table'] = 'task_submissions';
        $rows[] = $r;
      }
      $stmt2->close();
    }
  }
} catch (Exception $e) { error_log('grade_center: task_submissions load: '.$e->getMessage()); }

// Helper to get a resource title by type/id
function getResourceTitle($conn, $type, $id) {
  $id = intval($id);
  if ($type === 'quiz') {
    $q = $conn->prepare('SELECT title FROM quizzes WHERE id = ? LIMIT 1');
    if ($q) { $q->bind_param('i',$id); $q->execute(); $res=$q->get_result(); if ($res && ($r=$res->fetch_assoc())) { $q->close(); return $r['title']; } $q->close(); }
  }
  if ($type === 'task') {
    $q = $conn->prepare('SELECT title FROM performance_tasks WHERE id = ? LIMIT 1');
    if ($q) { $q->bind_param('i',$id); $q->execute(); $res=$q->get_result(); if ($res && ($r=$res->fetch_assoc())) { $q->close(); return $r['title']; } $q->close(); }
  }
  // generic fallback
  return ucfirst(htmlspecialchars($type)) . ' #' . intval($id);
}

// helper: check whether a resource still exists
function resourceExists($conn, $type, $id) {
  $id = intval($id);
  if ($type === 'quiz') {
    $q = $conn->prepare('SELECT 1 FROM quizzes WHERE id = ? LIMIT 1');
    if ($q) { $q->bind_param('i',$id); $q->execute(); $res = $q->get_result(); if ($res && $res->num_rows>0) { $res->close(); $q->close(); return true; } if ($res) $res->close(); $q->close(); }
    return false;
  }
  if ($type === 'task') {
    $q = $conn->prepare('SELECT 1 FROM performance_tasks WHERE id = ? LIMIT 1');
    if ($q) { $q->bind_param('i',$id); $q->execute(); $res = $q->get_result(); if ($res && $res->num_rows>0) { $res->close(); $q->close(); return true; } if ($res) $res->close(); $q->close(); }
    return false;
  }
  return true;
}

// Helper: resolve student accountId from student_id field (may be numeric id or email)
function resolveAccountId($conn, $studentRaw) {
  if (is_numeric($studentRaw)) return intval($studentRaw);
  $email = trim($studentRaw);
  if ($email === '') return null;
  $q = $conn->prepare('SELECT accountId FROM accounts WHERE email = ? LIMIT 1');
  if ($q) { $q->bind_param('s', $email); $q->execute(); $r = $q->get_result(); if ($r && ($row = $r->fetch_assoc())) { $aid = intval($row['accountId']); $r->close(); $q->close(); return $aid; } if ($r) $r->close(); $q->close(); }
  return null;
}

// Helper: get latest quiz attempt score for a student and quiz
function getLatestQuizScore($conn, $quiz_id, $studentRaw) {
  $sid = resolveAccountId($conn, $studentRaw);
  if (!$sid) return null;
  // include timing columns if available
  $q = $conn->prepare('SELECT score, submitted_at, started_at, duration_seconds FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY submitted_at DESC LIMIT 1');
  if ($q) {
    $q->bind_param('ii', $quiz_id, $sid);
    $q->execute();
    $res = $q->get_result();
    if ($res && ($r = $res->fetch_assoc())) {
      $out = ['score'=>$r['score'], 'submitted_at'=>$r['submitted_at']];
      if (isset($r['started_at'])) $out['started_at'] = $r['started_at'];
      if (isset($r['duration_seconds'])) $out['duration_seconds'] = intval($r['duration_seconds']);
      $res->close(); $q->close(); return $out;
    }
    if ($res) $res->close(); $q->close();
  }
  return null;
}

// sort rows by submitted_at desc
// Build student name map (replace student_id display with full name when possible)
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
    // detect name columns
    $hasNameCols = false;
    try {
      $colRes = $connection->query("SHOW COLUMNS FROM accounts LIKE 'first_name'");
      if ($colRes && $colRes->num_rows>0) { $hasNameCols = true; $colRes->close(); }
    } catch (Exception $e) { $hasNameCols = false; }

    $in = implode(',', array_map('intval', $ids));
    if ($hasNameCols) {
      $sqls = "SELECT accountId, first_name, last_name, username, email FROM accounts WHERE accountId IN ($in)";
    } else {
      $sqls = "SELECT accountId, username, email FROM accounts WHERE accountId IN ($in)";
    }
    $resS = $connection->query($sqls);
    if ($resS) {
      while ($r = $resS->fetch_assoc()) {
        $aid = $r['accountId'];
        $name = '';
        if ($hasNameCols) {
          $fn = trim($r['first_name'] ?? ''); $ln = trim($r['last_name'] ?? '');
          if ($fn !== '' || $ln !== '') $name = trim($fn . ' ' . $ln);
        }
  if ($name === '') $name = $r['username'] ?? ($r['email'] ?? $aid);
  $studentNames[intval($aid)] = $name . ' — ' . ($r['email'] ?? '');
      }
      $resS->close();
    }
  }
} catch (Exception $e) { error_log('grade_center: student map error: '.$e->getMessage()); }

usort($rows, function($a,$b){ $ta = strtotime($a['submitted_at'] ?? '1970-01-01'); $tb = strtotime($b['submitted_at'] ?? '1970-01-01'); return $tb <=> $ta; });

// simple paging after merge
$total = count($rows);
$totalPages = max(1, ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$pagedRows = array_slice($rows, $offset, $perPage);

// use $pagedRows for rendering
$rowsToRender = $pagedRows;

?>

<div class="section-header">
  <div class="section-title">Grade Center</div>
  <div>
    <form method="get" style="display:inline-block">
      <input type="text" name="student" placeholder="Filter by student id or email" value="<?php echo htmlspecialchars($studentFilter); ?>">
      <button class="btn btn-secondary" type="submit">Filter</button>
      <a class="btn btn-link" href="grade_center.php">Clear</a>
    </form>
  </div>
</div>

<div class="card">
  <h3>Submissions</h3>
  <style>
    /* Submission status badges and grade card */
    .status-badge { display:inline-block; padding:6px 10px; border-radius:999px; font-weight:700; font-size:0.85rem; color:#fff; }
    .status-badge.pending { background:#6b7280; }
    .status-badge.submitted { background:#2563eb; }
    .status-badge.graded { background:#059669; }
    .status-badge.late { background:#dc2626; }
    .status-badge.on-time { background:#10b981; }
    .grade-box { display:flex; gap:12px; align-items:center; background: rgba(255,255,255,0.02); padding:8px; border-radius:8px; border:1px solid rgba(255,255,255,0.03); }
    .grade-value { font-size:1.1rem; font-weight:800; background:linear-gradient(180deg,#fff,#f3f4f6); color:#111; padding:8px 12px; border-radius:8px; }
    .grade-meta { font-size:0.85rem; color:var(--text-secondary); }
    .feedback-text { font-size:0.95rem; color:var(--text-primary); }
  </style>

  <table>
    <thead><tr><th>Student</th><th>Resource</th><th>Title</th><th>Submitted</th><th>Status</th><th>Grade / Feedback</th><th>Actions</th></tr></thead>
    <tbody>
  <?php if (count($rowsToRender)>0): foreach($rowsToRender as $r): ?>
      <tr>
        <td>
          <?php
            $sidRaw = $r['student_id'] ?? '';
            $display = htmlspecialchars($sidRaw);
            if (is_numeric($sidRaw) && isset($studentNames[intval($sidRaw)])) {
              $display = htmlspecialchars($studentNames[intval($sidRaw)]);
              // show id in small muted text
              $display .= ' <div style="font-size:0.85em;color:var(--text-secondary);">ID: '.htmlspecialchars($sidRaw).'</div>';
            } else {
              // if it looks like an email, show it as-is; otherwise fall back to raw
              if (filter_var($sidRaw, FILTER_VALIDATE_EMAIL)) {
                $display = htmlspecialchars($sidRaw);
              } else {
                $display = htmlspecialchars($sidRaw);
              }
            }
            echo $display;
          ?>
        </td>
        <td><?php echo htmlspecialchars($r['resource_type']); ?></td>
        <td>
          <?php
            $type = $r['resource_type']; $resId = $r['resource_id'];
            $exists = resourceExists($connection, $type, $resId);
            if ($exists) {
              echo htmlspecialchars(getResourceTitle($connection, $type, $resId));
            } else {
              // show deleted indicator and provide a button to remove the orphan submission
              echo htmlspecialchars(ucfirst($type) . ' #' . intval($resId) . ' (deleted)');
              echo ' <button class="btn btn-link delete-submission" data-submission-id="'.htmlspecialchars($r['id']).'">Remove record</button>';
            }
          ?>
        </td>
        <td><?php echo htmlspecialchars($r['submitted_at'] ?? ''); ?></td>
        <td class="status-cell">
          <?php
            $rawStatus = strtolower(trim($r['status'] ?? 'pending'));
            $isGraded = (!empty($r['grade']) || stripos($rawStatus,'graded') !== false);
            $badgeClass = 'pending'; $label = ucfirst($rawStatus ?: 'Pending');
            if ($isGraded) { $badgeClass = 'graded'; $label = 'Graded'; }
            else if (stripos($rawStatus,'late') !== false) { $badgeClass = 'late'; $label = 'Late'; }
            else if (stripos($rawStatus,'on-time') !== false || stripos($rawStatus,'ontime') !== false) { $badgeClass = 'on-time'; $label = 'On-time'; }
            else if (stripos($rawStatus,'submitted') !== false) { $badgeClass = 'submitted'; $label = 'Submitted'; }
          ?>
          <span class="status-badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($label); ?></span>
        </td>
        <td class="grade-feedback-cell">
          <?php if (!empty($r['grade']) || !empty($r['feedback']) || !empty($r['notes'])): ?>
            <div class="grade-box">
              <?php if (!empty($r['grade'])): ?>
                <div class="grade-value"><?php echo htmlspecialchars($r['grade']); ?></div>
              <?php endif; ?>
              <div>
                <?php if (!empty($r['feedback'])): ?><div class="feedback-text"><?php echo nl2br(htmlspecialchars($r['feedback'])); ?></div><?php endif; ?>
                <?php if (!empty($r['notes'])): ?><div class="feedback-text"><?php echo nl2br(htmlspecialchars($r['notes'])); ?></div><?php endif; ?>
                <?php if (!empty($r['graded_by']) || !empty($r['graded_at'])): ?>
                  <div class="grade-meta" style="margin-top:6px">
                    <?php
                      $gb = $r['graded_by'] ?? null; $ga = $r['graded_at'] ?? null; $gbn = '';
                      if (!empty($gb)) {
                        $tq = $connection->prepare('SELECT first_name, last_name, email FROM teachers WHERE teacher_id = ? LIMIT 1');
                        if ($tq) { $tq->bind_param('i', $gb); $tq->execute(); $tres = $tq->get_result(); if ($tres && ($tr = $tres->fetch_assoc())) { $fn = trim($tr['first_name'] ?? ''); $ln = trim($tr['last_name'] ?? ''); if ($fn !== '' || $ln !== '') $gbn = trim($fn . ' ' . $ln); if (!$gbn) $gbn = $tr['email'] ?? ''; } if ($tres) $tres->close(); $tq->close(); }
                      }
                      if ($gbn !== '') echo 'Graded by '.htmlspecialchars($gbn).($ga ? ' on '.htmlspecialchars($ga) : '');
                      elseif ($ga) echo 'Graded on '.htmlspecialchars($ga);
                    ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <div style="color:var(--text-secondary)">No grade yet</div>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!empty($r['file_path'])):
              // Prefer secure download endpoint — submissions vs task_submissions
              $dlHref = null;
              if (!empty($r['source_table']) && $r['source_table'] === 'task_submissions') {
                $dlHref = '../download.php?resource=task_submission&id=' . intval($r['id']);
              } else {
                // generic submissions table
                $dlHref = '../download.php?resource=submission&id=' . intval($r['id']);
              }
          ?>
            <a href="<?php echo htmlspecialchars($dlHref); ?>" target="_blank">View / Download</a>
          <?php endif; ?>

          <div style="margin-top:8px">
            <a href="#" onclick="document.getElementById('gform-<?php echo $r['source_table'].'-'.$r['id']; ?>').style.display='block';return false;">Grade</a>
            <div id="gform-<?php echo $r['source_table'].'-'.$r['id']; ?>" style="display:none;margin-top:6px">
              <?php if ($r['source_table'] === 'submissions'): ?>
                <?php if ($type === 'quiz'): $attempt = getLatestQuizScore($connection, $resId, $r['student_id']); ?>
                  <?php if ($attempt): ?>
                    <?php
                      $scoreTxt = htmlspecialchars($attempt['score']);
                      $submittedAt = htmlspecialchars($attempt['submitted_at'] ?? '');
                      $timeTaken = '';
                      // prefer stored duration_seconds, but fall back to started_at/submitted_at difference when available
                      if (!empty($attempt['duration_seconds'])) {
                        $ds = intval($attempt['duration_seconds']);
                      } elseif (!empty($attempt['started_at']) && !empty($attempt['submitted_at'])) {
                        $st = strtotime($attempt['started_at']); $et = strtotime($attempt['submitted_at']);
                        if ($st !== false && $et !== false && $et >= $st) {
                          $ds = max(0, intval($et - $st));
                        } else {
                          $ds = null;
                        }
                      } else {
                        $ds = null;
                      }
                      if (is_int($ds) && $ds !== null) {
                        $m = floor($ds/60); $s = $ds % 60;
                        $timeTaken = ' — Time taken: ' . $m . 'm ' . $s . 's';
                      }
                    ?>
                    <div style="margin-bottom:6px;color:var(--text-secondary)">Latest attempt: <strong><?php echo $scoreTxt; ?></strong> (<?php echo $submittedAt; ?>)<?php echo htmlspecialchars($timeTaken); ?></div>
                  <?php endif; ?>
                <?php endif; ?>
                <form method="post" action="actions/grade_submission.php" class="ajax-grade-form">
                  <input type="hidden" name="submission_id" value="<?php echo intval($r['id']); ?>">
                  <?php if ($type === 'quiz'): ?>
                    <div style="margin-bottom:6px">
                      <label style="display:block;font-size:0.9rem;margin-bottom:4px">Grade (e.g. 85/100 or 100)</label>
                      <input type="text" name="grade" placeholder="Enter grade" style="width:160px;padding:6px;border-radius:6px;border:1px solid rgba(0,0,0,0.08)">
                    </div>
                  <?php endif; ?>
                  <textarea name="feedback" rows="2" placeholder="Feedback (optional)"></textarea>
                  <select name="status"><option value="on-time">On-time</option><option value="late">Late</option></select>
                  <button class="btn btn-primary" type="submit">Save</button>
                </form>
              <?php else: /* task_submissions */ ?>
                <form method="post" action="actions/grade_task_submission.php" class="ajax-grade-form">
                  <input type="hidden" name="submission_id" value="<?php echo intval($r['id']); ?>">
                  <input type="text" name="grade" placeholder="Grade (e.g. 85/100 or A)">
                  <select name="status"><option value="graded">Graded</option><option value="on-time">On-time</option><option value="late">Late</option></select>
                  <textarea name="notes" rows="2" placeholder="Feedback"></textarea>
                  <button class="btn btn-primary" type="submit">Save</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7">No submissions found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
  <div style="margin-top:16px"> 
    <button class="btn btn-primary" id="openHistoryBtn">Submissions History</button>
  </div>

<!-- Modal for Submissions History -->
<style>
  /* lightweight modal styles and transitions for submissions history */
  #subHistoryModal.lh-modal { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:99998; visibility:hidden; opacity:0; pointer-events:none; transition: opacity 220ms ease, visibility 220ms ease; }
  #subHistoryModal.lh-modal.open { visibility:visible; opacity:1; pointer-events:auto; }
  #subHistoryModal .lh-modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.6); opacity:0; transition: opacity 220ms ease; }
  #subHistoryModal.lh-modal.open .lh-modal-backdrop { opacity:1; }
  #subHistoryModal .lh-modal-content { position:relative; background:var(--bg-primary); width:90%; max-width:1100px; border-radius:10px; padding:16px; max-height:80vh; overflow:auto; transform: translateY(12px) scale(.99); opacity:0; transition: transform 260ms cubic-bezier(.2,.9,.3,1), opacity 260ms ease; box-shadow: 0 12px 40px rgba(0,0,0,0.45); }
  #subHistoryModal.lh-modal.open .lh-modal-content { transform: translateY(0) scale(1); opacity:1; }
  #subHistoryModal .lh-modal-close { position:absolute; right:12px; top:12px; background:transparent;border:0;color:var(--text-primary); cursor:pointer; }
</style>
<div id="subHistoryModal" class="lh-modal" aria-hidden="true">
  <div class="lh-modal-backdrop" aria-hidden="true"></div>
  <div class="lh-modal-content">
    <button id="closeHistoryBtn" class="lh-modal-close">Close</button>
    <div id="subHistoryContent">Loading...</div>
  </div>
</div>

<div style="margin-top:12px; display:flex; gap:8px; align-items:center">
  <div>Page <?php echo $page; ?> / <?php echo $totalPages; ?> (<?php echo $total; ?> results)</div>
  <div style="margin-left:auto">
    <?php if ($page>1): ?>
      <a class="btn btn-secondary" href="?student=<?php echo urlencode($studentFilter); ?>&page=<?php echo $page-1; ?>">Prev</a>
    <?php endif; ?>
    <?php if ($page<$totalPages): ?>
      <a class="btn btn-secondary" href="?student=<?php echo urlencode($studentFilter); ?>&page=<?php echo $page+1; ?>">Next</a>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


<script>
(function(){
  function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function bindForm(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var action = form.getAttribute('action') || '';
      // map to ajax endpoint
      var ajaxUrl = action.replace('grade_submission.php','ajax_grade_submission.php').replace('grade_task_submission.php','ajax_grade_task_submission.php');
      // ensure correct relative path
      if (ajaxUrl.indexOf('actions/') !== 0) ajaxUrl = 'actions/' + ajaxUrl;
      var fd = new FormData(form);
      fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(resp){ return resp.json(); })
        .then(function(data){
          if (data && data.ok) {
            var row = form.closest('tr');
            if (row) {
              var statusCell = row.querySelector('.status-cell');
              if (statusCell && data.status) statusCell.textContent = data.status;
              var gf = row.querySelector('.grade-feedback-cell');
              if (gf) {
                var html = '';
                if (data.grade || data.feedback || data.notes) {
                  html += '<div class="grade-box">';
                  if (data.grade) html += '<div class="grade-value">'+escapeHtml(data.grade)+'</div>';
                  html += '<div>';
                  if (data.feedback) html += '<div class="feedback-text">'+escapeHtml(data.feedback).replace(/\n/g,'<br>')+'</div>';
                  if (data.notes) html += '<div class="feedback-text">'+escapeHtml(data.notes).replace(/\n/g,'<br>')+'</div>';
                  if (data.graded_by_name || data.graded_at) {
                    var meta = 'Graded';
                    if (data.graded_by_name) meta += ' by ' + escapeHtml(data.graded_by_name);
                    if (data.graded_at) meta += ' on ' + escapeHtml(data.graded_at);
                    html += '<div class="grade-meta" style="margin-top:6px">' + meta + '</div>';
                  }
                  html += '</div></div>';
                } else {
                  html = '<div style="color:var(--text-secondary)">No grade yet</div>';
                }
                gf.innerHTML = html;
              }
            }
            // Toast helper
            function showToast(msg, timeout){
              timeout = timeout || 3000;
              var container = document.getElementById('lh-toast-container');
              if (!container) {
                container = document.createElement('div');
                container.id = 'lh-toast-container';
                container.style.position = 'fixed';
                container.style.right = '18px';
                container.style.bottom = '18px';
                container.style.zIndex = 99999;
                document.body.appendChild(container);
              }
              var t = document.createElement('div');
              t.textContent = msg;
              t.style.background = 'rgba(34,197,94,0.95)';
              t.style.color = '#fff';
              t.style.padding = '8px 12px';
              t.style.marginTop = '8px';
              t.style.borderRadius = '6px';
              t.style.boxShadow = '0 4px 10px rgba(0,0,0,0.12)';
              t.style.fontSize = '0.95em';
              container.appendChild(t);
              setTimeout(function(){ t.style.transition = 'opacity 300ms'; t.style.opacity = '0'; setTimeout(function(){ if (t && t.parentNode) t.parentNode.removeChild(t); }, 350); }, timeout);
            }
            var toastMsg = 'Saved';
            if (data.graded_by_name) toastMsg = 'Saved — graded by ' + data.graded_by_name;
            else if (data.graded_at) toastMsg = 'Saved — graded on ' + data.graded_at;
            showToast(toastMsg, 3500);
            form.style.display = 'none';
          } else {
            alert('Could not save: ' + (data && data.error ? data.error : 'Unknown error'));
          }
        }).catch(function(err){ console.error(err); alert('Network error'); });
    });
  }
  document.querySelectorAll('form.ajax-grade-form').forEach(bindForm);
  // bind delete buttons for orphan submissions
  document.querySelectorAll('button.delete-submission').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var id = this.getAttribute('data-submission-id');
      if (!id) return;
      if (!confirm('Remove this submission record? This cannot be undone.')) return;
      var fd = new FormData(); fd.append('submission_id', id);
      fetch('../actions/ajax_delete_submission.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data && data.ok) {
            // remove the row
            var row = btn.closest('tr'); if (row && row.parentNode) row.parentNode.removeChild(row);
            // show toast
            var container = document.getElementById('lh-toast-container');
            if (!container) {
              container = document.createElement('div'); container.id = 'lh-toast-container'; container.style.position='fixed'; container.style.right='18px'; container.style.bottom='18px'; container.style.zIndex=99999; document.body.appendChild(container);
            }
            var t = document.createElement('div'); t.textContent = 'Record removed'; t.style.background='rgba(34,197,94,0.95)'; t.style.color='#fff'; t.style.padding='8px 12px'; t.style.marginTop='8px'; t.style.borderRadius='6px'; t.style.boxShadow='0 4px 10px rgba(0,0,0,0.12)'; t.style.fontSize='0.95em'; container.appendChild(t);
            setTimeout(function(){ if (t && t.parentNode) t.parentNode.removeChild(t); }, 3500);
          } else {
            alert('Could not delete: ' + (data && data.error ? data.error : 'Unknown error'));
          }
        }).catch(function(err){ console.error(err); alert('Network error'); });
    });
  });
  // history modal behavior
  var openBtn = document.getElementById('openHistoryBtn');
  var modal = document.getElementById('subHistoryModal');
  var closeBtn = document.getElementById('closeHistoryBtn');
  var content = document.getElementById('subHistoryContent');
  function openHistory(page){
    if (!modal) return;
    // save last focused element for accessibility
    lastFocus = document.activeElement;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    content.innerHTML = 'Loading...';
    var url = 'submissions_history.php?embed=1' + (page ? '&page=' + parseInt(page) : '');
    fetch(url, { credentials: 'same-origin' }).then(function(r){ return r.text(); }).then(function(html){ content.innerHTML = html; 
      // after loading content, set focus into modal and enable focus trap
      try { var closeBtnEl = document.getElementById('closeHistoryBtn'); if (closeBtnEl) closeBtnEl.focus(); } catch(e){}
      // enable trap
      if (typeof trapFocus === 'function') {
        try { window.__lh_focus_cleanup = trapFocus(modal); } catch(e) { window.__lh_focus_cleanup = null; }
      }
    }).catch(function(e){ console.error(e); content.innerHTML = '<div style="padding:12px;color:#f88">Failed to load history</div>'; });
    // after setting content, wire modal pagination links to use AJAX
    function wireModalPagination(){
      try {
        var links = content.querySelectorAll('a');
        links.forEach(function(a){
          var href = a.getAttribute('href') || '';
          if (/([?&]page=\d+)/.test(href)) {
            a.addEventListener('click', function(ev){ ev.preventDefault(); var m = href.match(/([?&]page=(\d+))/); var p = m ? parseInt(m[2]) : null; openHistory(p); });
          }
        });
      } catch (err) { /* ignore wiring errors */ }
    }
    // call wiring after a short delay to ensure DOM nodes are ready
    setTimeout(wireModalPagination, 20);
  }
  if (openBtn) openBtn.addEventListener('click', function(){ openHistory(); });
  var lastFocus = null;
  if (closeBtn) closeBtn.addEventListener('click', function(){ if (modal) { modal.classList.remove('open'); document.body.style.overflow = ''; if (window.__lh_focus_cleanup) { try{ window.__lh_focus_cleanup(); } catch(e){} window.__lh_focus_cleanup = null; } if (lastFocus) try{ lastFocus.focus(); }catch(e){} } });
  if (modal) modal.addEventListener('click', function(e){ if (e.target === modal || e.target.classList.contains('lh-modal-backdrop')) { modal.classList.remove('open'); document.body.style.overflow = ''; if (window.__lh_focus_cleanup) { try{ window.__lh_focus_cleanup(); } catch(e){} window.__lh_focus_cleanup = null; } if (lastFocus) try{ lastFocus.focus(); }catch(e){} } });

  // enhance modal with basic focus trap and Esc-to-close
  function trapFocus(modalEl){
    var focusable = modalEl.querySelectorAll('a[href], button, textarea, input, select, [tabindex]:not([tabindex="-1"])');
    focusable = Array.prototype.slice.call(focusable).filter(function(el){ return !el.hasAttribute('disabled'); });
    if (focusable.length === 0) return null;
    var first = focusable[0], last = focusable[focusable.length-1];
    function keyHandler(e){
      if (e.key === 'Escape' || e.key === 'Esc') {
        modalEl.classList.remove('open'); document.body.style.overflow = ''; if (lastFocus) try{ lastFocus.focus(); }catch(err){}
        document.removeEventListener('keydown', keyHandler);
        document.removeEventListener('focus', focusHandler, true);
        return;
      }
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          if (document.activeElement === first) { e.preventDefault(); last.focus(); }
        } else {
          if (document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
      }
    }
    function focusHandler(e){ if (modalEl.contains(e.target)) return; e.stopPropagation(); first.focus(); }
    document.addEventListener('keydown', keyHandler);
    document.addEventListener('focus', focusHandler, true);
    return function cleanup(){ document.removeEventListener('keydown', keyHandler); document.removeEventListener('focus', focusHandler, true); };
  }
})();
</script>
