<?php
// delete_teacher.php
// Purpose: Performs a soft-delete (sets is_active = -1) on a teacher account
// and logs the action into the 'account_creation_logs' table.

// -------------------------------------------------------------------
// Set headers for security and JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- Configuration and Setup ---

// CRITICAL: Include the shared database configuration file
require_once(__DIR__ . "/../../../pdo_config.php");

// Must match the AUTH_TOKEN in super_admin.js
$auth_token_secret = "super_admin_id_123";

// Function to establish database connection
function getDbConnection()
{
    // Access the global constants from pdo_config.php
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    try {
        $conn = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $conn;
    } catch (PDOException $exception) {
        http_response_code(500);
        error_log("Database connection failed in soft delete script: " . $exception->getMessage());
        echo json_encode(["status" => "error", "message" => "Database connection failed. Check server logs."]);
        exit();
    }
}

// Get the HTTP Authorization header
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';

// Basic authentication check against the hardcoded token
if ($auth_header !== "Bearer " . $auth_token_secret) {
    http_response_code(401); // Unauthorized
    echo json_encode(["status" => "error", "message" => "Authentication failed. Invalid or missing Authorization token."]);
    exit();
}

// Get POST data from request body
$data = json_decode(file_get_contents("php://input"));

// Check for required data
if (empty($data->teacher_id) || empty($data->super_admin_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Missing required teacher_id or super_admin_id."]);
    exit();
}

$teacher_id = (int)$data->teacher_id;
$super_admin_id = (int)$data->super_admin_id;

// --- Database Operation ---

$conn = getDbConnection(); // Connect to the database

try {
    // 1. Begin Transaction
    $conn->beginTransaction();

    // 2. Fetch the teacher's name BEFORE soft-deleting them (needed for the log table)
    $name_query = "SELECT full_name FROM teachers WHERE teacher_id = :teacher_id";
    $name_stmt = $conn->prepare($name_query);
    $name_stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
    $name_stmt->execute();
    $teacher_name = $name_stmt->fetchColumn();

    if (!$teacher_name) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Teacher with ID $teacher_id not found."]);
        exit();
    }

    // 3. Perform the Soft Delete (Update is_active status to -1)
    // NOTE: This assumes 'updated_at' column exists in your 'teachers' table.
    $query = "UPDATE teachers SET is_active = -1, updated_at = NOW() WHERE teacher_id = :teacher_id";
    $stmt = $conn->prepare($query);

    // Bind parameters
    $stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Check if a row was actually affected
        if ($stmt->rowCount() === 0) {
            $conn->rollBack();
            http_response_code(404);
            // This case handles a soft-delete on an existing teacher, but the fetch above already handles not found
            // This condition usually catches if the teacher was already soft-deleted (-1)
            echo json_encode(["status" => "error", "message" => "Teacher with ID $teacher_id was not updated (possibly already soft-deleted)."]);
            exit();
        }

        // 4. Log the Delete Action into the 'account_creation_logs' table
        $log_action = "SOFT_DELETE";
        $admin_user_details = "Super Admin ID: " . $super_admin_id; // Using ID for the admin_user VARCHAR field
        $action_status = "Completed"; // Must match one of the ENUM values: 'Success', 'Completed', 'Reversed', 'Failed'

        // CRITICAL FIX: The query and bindings now match the 'account_creation_logs' table structure.
        $log_query = "INSERT INTO account_creation_logs 
                      (teacher_id, teacher_name, action_type, admin_user, action_status, log_time) 
                      VALUES (:teacher_id, :teacher_name, :action_type, :admin_user, :action_status, NOW())";
        $log_stmt = $conn->prepare($log_query);

        $log_stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
        $log_stmt->bindParam(':teacher_name', $teacher_name); // Bind the fetched name
        $log_stmt->bindParam(':action_type', $log_action);
        $log_stmt->bindParam(':admin_user', $admin_user_details);
        $log_stmt->bindParam(':action_status', $action_status);

        $log_stmt->execute();

        // 5. Commit Transaction
        $conn->commit();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Teacher ID $teacher_id has been successfully soft-deleted and logged."
        ]);
    } else {
        $conn->rollBack();
        throw new Exception("Database update failed.");
    }
} catch (Exception $e) {
    // If anything fails above, roll back the transaction
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    error_log("Soft Deletion Error: " . $e->getMessage());
    // Provide a slightly more informative error message to the client
    $error_detail = (strpos($e->getMessage(), 'SQLSTATE') !== false) ? 'Database query error.' : 'Server processing error.';
    echo json_encode(["status" => "error", "message" => "A server error occurred during soft deletion: " . $error_detail . " Check server logs."]);
}
