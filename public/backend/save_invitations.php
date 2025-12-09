<?php
// /SANROOM/public/backend/save_invitations.php - FINAL VERSION (Email/ID-Based)

if (session_status() === PHP_SESSION_NONE) session_start();

// Ensure this path correctly points to your database connection logic
require_once(__DIR__ . "/../../database.php");

header("Content-Type: application/json");

// Function to handle JSON response with HTTP status code
function send_response($success, $message, $http_code = 200)
{
    http_response_code($http_code);
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

// 1. Authorization Check
if (!isset($_SESSION['user_id'])) {
    error_log("[DEBUG] save_invitations: Authentication failed. No user_id in session.");
    send_response(false, "Authentication Error: Please log in.", 401);
}

try {
    // Check for database connection error
    if ($conn->connect_error) {
        error_log("[DEBUG] save_invitations: DB Connection Error: " . $conn->connect_error);
        send_response(false, "Connection Error: " . $conn->connect_error, 500);
    }

    $instructor_id = intval($_SESSION['user_id']);

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    $roomId = intval($input['room_id'] ?? 0);
    $emails = $input['emails'] ?? [];

    error_log("[DEBUG] save_invitations: Instructor {$instructor_id} processing Room {$roomId}. Emails provided: " . count($emails));

    if (!$roomId) {
        send_response(false, "Missing Room ID.", 400);
    }

    if (!is_array($emails)) {
        send_response(false, "Invalid data format for emails.", 400);
    }

    $validEmails = array_map('strtolower', $emails);
    $validEmails = array_unique($validEmails);

    $conn->begin_transaction();

    // =========================================================
    // 2. Clear Existing Invitations
    // =========================================================
    $clear_sql = "DELETE FROM room_invitations WHERE room_id = ?";
    if (!$clear_stmt = $conn->prepare($clear_sql)) {
        throw new Exception("Clear preparation failed: " . $conn->error);
    }
    $clear_stmt->bind_param("i", $roomId);
    if (!$clear_stmt->execute()) {
        throw new Exception("Failed to clear existing invitations: " . $clear_stmt->error);
    }
    error_log("[DEBUG] save_invitations: Cleared existing invitations for Room {$roomId}.");
    $clear_stmt->close();

    $total_emails_provided = count($validEmails);

    // =========================================================
    // 3. Find Student IDs for provided emails
    // =========================================================
    $foundStudents = [];
    $foundEmails = [];

    if ($total_emails_provided > 0) {
        $in_clause = str_repeat('?,', $total_emails_provided - 1) . '?';
        $lookup_sql = "SELECT id, email FROM users WHERE role = 'student' AND LOWER(email) IN ($in_clause)";

        if (!$lookup_stmt = $conn->prepare($lookup_sql)) {
            throw new Exception("Lookup preparation failed: " . $conn->error);
        }

        $types = str_repeat('s', $total_emails_provided);
        $bind_params = array_merge([$types], $validEmails);
        $lookup_stmt->bind_param(...$bind_params);

        $lookup_stmt->execute();
        $lookup_result = $lookup_stmt->get_result();

        while ($row = $lookup_result->fetch_assoc()) {
            $foundStudents[] = $row['id'];
            $foundEmails[] = strtolower($row['email']);
        }
        error_log("[DEBUG] save_invitations: Found " . count($foundStudents) . " student IDs to invite.");
        $lookup_stmt->close();
    }

    // =========================================================
    // 4. Insert New Invitations
    // =========================================================
    $inserted_count = 0;
    if (!empty($foundStudents)) {
        $insert_sql = "INSERT IGNORE INTO room_invitations (room_id, student_id) VALUES (?, ?)";
        if (!$insert_stmt = $conn->prepare($insert_sql)) {
            throw new Exception("Insert preparation failed: " . $conn->error);
        }

        foreach ($foundStudents as $studentId) {
            $insert_stmt->bind_param("ii", $roomId, $studentId);
            if ($insert_stmt->execute()) {
                $inserted_count++;
            }
        }
        error_log("[DEBUG] save_invitations: Inserted {$inserted_count} new invitations for Room {$roomId}.");
        $insert_stmt->close();
    }

    // =========================================================
    // 4.5. Cleanup room_participants (CRITICAL ENFORCEMENT)
    // =========================================================
    // This removes students from the 'room_participants' table 
    // if they are NOT in the 'room_invitations' list AND the room is 'reserved for event'.
    $cleanup_sql = "
        DELETE FROM room_participants 
        WHERE room_id = ? 
        AND EXISTS (
            SELECT 1 FROM rooms 
            WHERE room_id = room_participants.room_id 
            AND manual_status = 'reserved for event'
        )
        AND NOT EXISTS (
            SELECT 1 FROM room_invitations 
            WHERE room_id = room_participants.room_id 
            AND student_id = room_participants.student_id
        )
    ";

    if (!$cleanup_stmt = $conn->prepare($cleanup_sql)) {
        throw new Exception("Cleanup preparation failed: " . $conn->error);
    }
    $cleanup_stmt->bind_param("i", $roomId);
    if (!$cleanup_stmt->execute()) {
        throw new Exception("Failed to clean up room participants: " . $cleanup_stmt->error);
    }

    $deleted_rows = $cleanup_stmt->affected_rows;
    error_log("[DEBUG] save_invitations: Cleanup executed for Room {$roomId}. Deleted {$deleted_rows} uninvited participants.");
    $cleanup_stmt->close();


    // =========================================================
    // 5. Finalize Transaction and Send Response
    // =========================================================
    $conn->commit();

    $not_found_emails = array_diff($validEmails, $foundEmails);
    $message = "Successfully saved $inserted_count invitations.";

    if (!empty($not_found_emails)) {
        $message .= " Note: The following emails did not match an active student account and were skipped: " . implode(", ", $not_found_emails) . ".";
    }

    send_response(true, $message);
} catch (Exception $e) {
    $conn->rollback();
    error_log("[ERROR] save_invitations: Rollback due to failure (Room ID: {$roomId}): " . $e->getMessage());
    send_response(false, "Server error: Failed to save invitations. " . $e->getMessage(), 500);
}

if (isset($conn)) {
    $conn->close();
}
