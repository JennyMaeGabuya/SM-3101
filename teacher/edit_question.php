<?php
require_once __DIR__ . '/includes/header.php';
if (empty($_SESSION['teacher_id'])) { header('Location: ../login.php'); exit; }
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { header('Location: quizzes.php'); exit; }

$stmt = $connection->prepare('SELECT qq.*, q.teacher_id FROM quiz_questions qq JOIN quizzes q ON qq.quiz_id = q.id WHERE qq.id = ? LIMIT 1');
if (!$stmt) { echo '<div class="card">Could not load question.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $question = $res->fetch_assoc(); $stmt->close();

if (!$question) { echo '<div class="card">Question not found.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }
if ((int)$question['teacher_id'] !== (int)$_SESSION['teacher_id']) { echo '<div class="card">Permission denied.</div>'; require_once __DIR__ . '/includes/footer.php'; exit; }

?>
<div class="section-header">
  <div class="section-title">Edit Question</div>
  <div><a class="btn" href="edit_quiz.php?id=<?php echo htmlspecialchars($question['quiz_id']); ?>">Back</a></div>
</div>

<div class="card">
  <form method="post" action="actions/edit_question.php">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($question['id']); ?>">
    <div>
      <label>Question Text</label>
      <textarea name="question_text" rows="3"><?php echo htmlspecialchars($question['question_text']); ?></textarea>
    </div>
    <div>
      <label>Type</label>
      <select name="question_type">
        <option value="mcq" <?php echo $question['question_type']==='mcq'?'selected':''; ?>>Multiple choice</option>
        <option value="short" <?php echo $question['question_type']==='short'?'selected':''; ?>>Short answer</option>
      </select>
    </div>
    <div>
      <label>Options (one per line, for MCQ)</label>
      <textarea name="options" rows="4"><?php echo htmlspecialchars(is_string($question['options']) ? $question['options'] : ''); ?></textarea>
    </div>
    <div>
      <label>Correct Answer</label>
      <input type="text" name="correct_answer" value="<?php echo htmlspecialchars($question['correct_answer']); ?>">
    </div>
    <div style="margin-top:8px">
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
