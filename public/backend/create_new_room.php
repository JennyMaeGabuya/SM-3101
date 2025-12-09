<?php
// create_new_room.php
// Handles POST request to create a new entry in the 'rooms' table.

// Set headers for JSON response
header('Content-Type: application/json');

// Assuming your database connection file uses $conn for the MySQLi connection
require_once(__DIR__ . "/../../database.php");

// --- 1. Check Request Method and Input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$roomName = trim($data['roomName'] ?? '');
$capacity = $data['capacity'] ?? -1;
// <<<--- NEW CODE: Extract the special access code
$accessCode = trim($data['specialAccessCode'] ?? '');
// NEW CODE --->

// Validate input
if (empty($roomName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room Name is required.']);
    exit;
}
if (!is_numeric($capacity) || $capacity < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Capacity must be a non-negative number.']);
    exit;
}
// <<<--- NEW CODE: Validate the access code
if (empty($accessCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Special Access Code is required.']);
    exit;
}
// NEW CODE --->

// Ensure capacity is an integer
$capacity = (int)$capacity;

// --- 2. Database Operation: Insert New Room ---
try {
    // <<<--- UPDATED SQL: Include the special_access_code column
    $sql = "INSERT INTO rooms (room_name, capacity, special_access_code) 
             VALUES (?, ?, ?)";
    // UPDATED SQL --->

    // Use prepared statements for security
    $stmt = $conn->prepare($sql);

    // <<<--- UPDATED BIND: 'ssi' becomes 'sis' (string, integer, string) for roomName, capacity, accessCode
    $stmt->bind_param("sis", $roomName, $capacity, $accessCode);
    // UPDATED BIND --->

    if ($stmt->execute()) {
        $newRoomId = $conn->insert_id;

        echo json_encode([
            'success' => true,
            'message' => "Room '{$roomName}' created successfully with code '{$accessCode}'.",
            'roomId' => $newRoomId,
            'accessCode' => $accessCode // Return code in success response
        ]);
    } else {
        // Check for specific MySQL errors (e.g., duplicate unique key)
        if ($conn->errno === 1062) {
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'A room with this name already exists.']);
        } else {
            throw new Exception("Database insertion failed: " . $conn->error);
        }
    }
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    // Close the statement (optional, but good practice)
    if (isset($stmt)) {
        $stmt->close();
    }
    // Close the connection if your setup requires manual closing
    // if (isset($conn)) { $conn->close(); } 
}
