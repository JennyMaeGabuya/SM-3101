<?php
header('Content-Type: application/json');
require_once(__DIR__ . "/../../database.php");

$response = ["success" => false, "message" => ""];

// 1. Get the JSON input
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 2. Validate input
$roomId = $data['roomId'] ?? null;
$roomName = $data['roomName'] ?? null;
$capacity = $data['capacity'] ?? null;

if (empty($roomId) || empty($roomName) || !is_numeric($capacity)) {
    $response["message"] = "Invalid or missing room details (ID, Name, or Capacity).";
    echo json_encode($response);
    exit;
}

// 3. Update database
try {
    $sql = "UPDATE rooms SET room_name = ?, capacity = ? WHERE room_id = ?";
    // Use your actual database connection variable, e.g., $conn
    $stmt = $conn->prepare($sql);

    // Bind parameters: 's' for string (name), 'i' for integer (capacity and id)
    // Note: Adjust the binding types based on your actual schema/db_connect implementation.
    $stmt->bind_param("sii", $roomName, $capacity, $roomId);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Room details updated successfully.";
    } else {
        $response["message"] = "Database update failed.";
    }

    $stmt->close();
} catch (Exception $e) {
    $response["message"] = "Server error: " . $e->getMessage();
}

// 4. Send the JSON response
echo json_encode($response);

// NOTE: You must include your database connection logic (e.g., db_connect.php).
