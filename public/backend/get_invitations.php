<?php
// /SANROOM/public/backend/get_invitations.php

if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure this path correctly points to your database connection logic
require_once(__DIR__ . "/../../database.php");

header("Content-Type: application/json");

// Function to handle JSON response
function send_response($success, $data = null, $message = null, $http_code = 200)
{
    http_response_code($http_code);
    $response = ["success" => $success];
    if ($message !== null) $response["message"] = $message;
    if ($data !== null) $response["emails"] = $data;
    echo json_encode($response);
    exit();
}

// 1. Authorization Check (Ensure only logged-in users can access this)
if (!isset($_SESSION['user_id'])) {
    send_response(false, null, "Authentication Error: Please log in.", 401);
}

try {
    // Check for database connection error
    if ($conn->connect_error) {
        send_response(false, null, "Connection Error: " . $conn->connect_error, 500);
    }

    // Get input data from GET request
    $roomId = intval($_GET['room_id'] ?? 0);

    if (!$roomId) {
        send_response(false, null, "Missing Room ID.", 400);
    }

    // =========================================================
    // 2. Fetch Emails for the Room's Invitations
    // =========================================================
    $fetch_sql = "
        SELECT 
            u.email 
        FROM 
            room_invitations ri
        JOIN 
            users u ON ri.student_id = u.id
        WHERE 
            ri.room_id = ?";

    if (!$fetch_stmt = $conn->prepare($fetch_sql)) {
        throw new Exception("Fetch preparation failed: " . $conn->error);
    }

    $fetch_stmt->bind_param("i", $roomId);

    if (!$fetch_stmt->execute()) {
        throw new Exception("Failed to execute invitation fetch: " . $fetch_stmt->error);
    }

    $result = $fetch_stmt->get_result();
    $invitedEmails = [];

    while ($row = $result->fetch_assoc()) {
        $invitedEmails[] = $row['email'];
    }

    $fetch_stmt->close();

    // 3. Send successful response with the array of emails
    send_response(true, $invitedEmails);
} catch (Exception $e) {
    error_log("Failed to fetch room invitations (Room ID: $roomId): " . $e->getMessage());
    send_response(false, null, "Server error: Failed to retrieve invitations. " . $e->getMessage(), 500);
}

if (isset($conn)) {
    $conn->close();
}
