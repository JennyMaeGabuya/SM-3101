<?php
require_once __DIR__ . '/includes/header.php';

if (empty($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: quizzes.php');
    exit;
}

$stmt = $connection->prepare('SELECT id, title, description, scheduled_at, deadline, teacher_id, COALESCE(max_attempts, 2) AS max_attempts, duration_minutes FROM quizzes WHERE id = ? LIMIT 1');
if (!$stmt) { echo '<div class="card">Could not load quiz.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$quiz = $res->fetch_assoc();
$stmt->close();

if (!$quiz) {
    echo '<div class="card">Quiz not found.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ensure teacher owns this quiz
if ((string)$quiz['teacher_id'] !== (string)$_SESSION['teacher_id']) {
    echo '<div class="card">Permission denied.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

?>
<div class="section-header">
  <div class="section-title">Edit Quiz</div>
  <div><a class="btn" href="quizzes.php">Back</a></div>
</div>

<div class="card">
  <form method="post" action="actions/edit_quiz.php">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($quiz['id']); ?>">
    <div>
      <label>Title</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
    </div>
    <div>
      <label>Scheduled At</label>
      <input type="datetime-local" name="scheduled_at" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($quiz['scheduled_at']))); ?>">
    </div>
    <div style="grid-column:1/3">
      <label>Description / Instructions</label>
      <textarea name="description" rows="4"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
    </div>
    <div>
      <label>Deadline</label>
      <input type="datetime-local" name="deadline" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($quiz['deadline']))); ?>">
    </div>
    <div>
      <label>Max Attempts</label>
      <input type="number" name="max_attempts" min="1" value="<?php echo htmlspecialchars($quiz['max_attempts'] ?? 2); ?>">
    </div>
    <div>
      <label>Duration (minutes)</label>
      <input type="number" name="duration_minutes" min="0" step="1" value="<?php echo htmlspecialchars($quiz['duration_minutes'] ?? ''); ?>" placeholder="Leave blank for no timer">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</div>

<?php
// fetch questions for this quiz
$questions = [];
try {
  $qst = $connection->prepare('SELECT id, question_text, question_type, options, correct_answer FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC');
  if ($qst) { $qst->bind_param('i', $quiz['id']); $qst->execute(); $resq = $qst->get_result(); if ($resq) { while ($row = $resq->fetch_assoc()) $questions[] = $row; $resq->close(); } $qst->close(); }
} catch (Exception $e) { error_log('Fetch quiz questions failed: '.$e->getMessage()); }
?>

<div class="card">
  <h3>Questions</h3>
  <?php if (empty($questions)): ?>
    <div style="color:var(--text-secondary)">No questions yet. Add below.</div>
  <?php else: ?>
    <ol>
      <?php foreach ($questions as $qq): ?>
          <li style="margin:8px 0; display:flex; justify-content:space-between; align-items:flex-start">
            <div>
              <strong><?php echo htmlspecialchars($qq['question_text']); ?></strong>
              <div style="font-size:0.9rem;color:var(--text-secondary)">
                Type: <?php echo htmlspecialchars($qq['question_type']); ?>
                <?php if (!empty($qq['options'])): ?>
                  <div>Options:
                    <ul>
                      <?php $opts = json_decode($qq['options'], true) ?: []; foreach ($opts as $opt): ?>
                        <li><?php echo htmlspecialchars($opt); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:8px">
              <a class="btn" href="edit_question.php?id=<?php echo $qq['id']; ?>">Edit</a>
              <a class="btn btn-danger" href="actions/delete_question.php?id=<?php echo $qq['id']; ?>" onclick="return confirm('Delete question?')">Delete</a>
            </div>
          </li>
        <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Add Question</h3>
  <form method="post" action="actions/add_question.php">
    <input type="hidden" name="quiz_id" value="<?php echo htmlspecialchars($quiz['id']); ?>">
    <div>
      <label>Question Text</label>
      <textarea name="question_text" rows="3" required></textarea>
    </div>
    <div>
      <label>Type</label>
      <select name="question_type" id="qType">
        <option value="mcq">Multiple choice</option>
        <option value="short">Short answer</option>
      </select>
    </div>
    <div id="mcqArea">
      <label>Options (one per line)</label>
      <textarea name="options" rows="4" placeholder="Option A\nOption B\nOption C"></textarea>
      <label>Correct Answer (exact match or option text)</label>
      <input type="text" name="correct_answer">
    </div>
    <div id="shortArea" style="display:none">
      <label>Expected Answer (optional)</label>
      <input type="text" name="correct_answer_short">
    </div>
    <div style="margin-top:8px">
      <button class="btn btn-primary" type="submit">Add Question</button>
    </div>
  </form>
</div>

<script>
  (function(){
    var sel = document.getElementById('qType');
    var mcq = document.getElementById('mcqArea');
    var short = document.getElementById('shortArea');
    sel.addEventListener('change', function(){
      if (sel.value === 'mcq') { mcq.style.display=''; short.style.display='none'; }
      else { mcq.style.display='none'; short.style.display=''; }
    });
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

