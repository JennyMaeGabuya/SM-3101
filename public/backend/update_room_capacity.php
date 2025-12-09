<?php
// Set headers for JSON response
header('Content-Type: application/json');

// --- 1. Database Connection (Replace with your actual file) ---
require_once 'db_connect.php';

// --- 2. Check Request Method and Input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$roomId = $data['roomId'] ?? 0;
$capacity = $data['capacity'] ?? -1; // Use -1 to check for valid input

// Validate input
if (!is_numeric($roomId) || $roomId <= 0 || !is_numeric($capacity) || $capacity < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Room ID or Capacity (Capacity must be non-negative).']);
    exit;
}

// Ensure capacity is an integer
$capacity = (int)$capacity;

// --- 3. Database Operation: Update Capacity ---
try {
    $pdo = connectDB(); // Assuming connectDB() returns a PDO object

    $sql = "UPDATE rooms 
            SET capacity = :capacity 
            WHERE room_id = :roomId";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':capacity', $capacity, PDO::PARAM_INT);
    $stmt->bindParam(':roomId', $roomId, PDO::PARAM_INT);

    // Execute the statement
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Room capacity updated successfully.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Room not found.']);
        }
    } else {
        throw new Exception("Database update failed.");
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
