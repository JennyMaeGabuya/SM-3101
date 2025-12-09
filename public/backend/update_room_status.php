<?php
// update_room_status.php
// Handles POST request to update the manual_status and archive state of a room.

// Set headers for JSON response
header('Content-Type: application/json');

// --- 1. Database Connection ---
// Assumes this file provides the MySQLi connection object $conn
require_once(__DIR__ . "/../../database.php");

// Ensure $conn exists (if it was an issue in previous steps)
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// --- 2. Check Request Method and Input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$roomId = $data['roomId'] ?? 0;
$status = strtolower($data['status'] ?? '');

// Validate input
if (!is_numeric($roomId) || $roomId <= 0 || !in_array($status, ['available', 'occupied', 'reserved', 'archived'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Room ID or Status provided.']);
    exit;
}

// Prepare update values
$manual_status = $status;
$is_archived = 0;

// Handle special status cases
if ($status === 'archived') {
    // If archiving, set is_archived to 1 and manual_status to 'available'
    $is_archived = 1;
    $manual_status = 'available';
} else {
    // If setting to available/occupied/reserved, ensure it is NOT archived
    $is_archived = 0;
}

// Ensure the room ID is an integer
$roomId = (int)$roomId;


// --- 3. Database Operation: Update Room Status ---
try {
    // SQL to update room status and archive flag simultaneously
    $sql = "UPDATE rooms 
            SET manual_status = ?, is_archived = ? 
            WHERE room_id = ?";

    // Use prepared statements (MySQLi)
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("SQL prepare failed: " . $conn->error);
    }

    // 'sii' means 'string, integer, integer' for manual_status, is_archived, roomId
    $stmt->bind_param("sii", $manual_status, $is_archived, $roomId);

    // Execute the statement
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows; // Use affected_rows for MySQLi

        if ($affectedRows > 0) {
            echo json_encode(['success' => true, 'message' => "Room status updated to '{$status}' successfully."]);
        } else {
            // No rows affected, check if the room ID existed
            if ($conn->errno === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Room not found or status was already set.']);
            } else {
                throw new Exception("Database update failed: " . $stmt->error);
            }
        }
    } else {
        throw new Exception("Statement execution failed: " . $stmt->error);
    }
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    // Close the statement
    if (isset($stmt)) {
        $stmt->close();
    }
    // Note: Do not close the $conn here if it is needed for other requests
}
