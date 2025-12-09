<?php
// send_message.php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

// Check if it's an AJAX POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit;
}

// Include database connection
include __DIR__ . "/../database.php"; // Adjust path as needed

// Decode JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$sender_id = $data['sender_id'] ?? null;
$recipient_id = $data['recipient_id'] ?? null;
$sender_role = $data['sender_role'] ?? null; // 'student' or 'teacher'
$subject = $data['subject'] ?? null;
$body = $data['body'] ?? null;
$schedule_id = $data['schedule_id'] ?? null; // Optional, but highly recommended for context

if (!$sender_id || !$recipient_id || !$sender_role || !$subject || !$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: sender_id, recipient_id, sender_role, subject, or body.']);
    exit;
}

// Basic sanitization
$sender_id = intval($sender_id);
$recipient_id = intval($recipient_id);
$schedule_id = $schedule_id ? intval($schedule_id) : NULL;
$subject = htmlspecialchars(trim($subject));
$body = htmlspecialchars(trim($body));
$sender_role = strtolower(trim($sender_role));

// Ensure sender_role is valid
if (!in_array($sender_role, ['student', 'teacher'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid sender role.']);
    exit;
}

try {
    // Determine the user's ID from session for security (Optional, but recommended)
    // For simplicity here, we trust the JS payload, but in production, verify $sender_id against $_SESSION['user_id']

    $is_read = ($sender_role === 'student') ? FALSE : TRUE; // Teacher's first message to student should be unread for student.

    $sql = "INSERT INTO messages 
            (schedule_id, sender_id, recipient_id, sender_role, subject, body, is_read) 
            VALUES 
            (:schedule_id, :sender_id, :recipient_id, :sender_role, :subject, :body, :is_read)";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':schedule_id', $schedule_id, $schedule_id === NULL ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
    $stmt->bindParam(':recipient_id', $recipient_id, PDO::PARAM_INT);
    $stmt->bindParam(':sender_role', $sender_role, PDO::PARAM_STR);
    $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindParam(':body', $body, PDO::PARAM_STR);
    $stmt->bindParam(':is_read', $is_read, PDO::PARAM_BOOL);

    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
} catch (PDOException $e) {
    error_log("Message insertion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Could not send message.']);
}
