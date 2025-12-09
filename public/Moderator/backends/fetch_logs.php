<?php
// fetch_logs.php
// Purpose: Fetches a list of all teacher accounts (core details) for the Super Admin dashboard.

// -------------------------------------------------------------------
// Output Buffering and Error Management
ob_start();
// Set to 0 in production; set to 1 for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
// -------------------------------------------------------------------

// --- 1. CONFIGURATION AND DEPENDENCIES ---
require_once(__DIR__ . "/../../../pdo_config.php");

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// --- Basic Security Check ---
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strpos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer super_admin_id_123') === false) {
    $response['message'] = 'Unauthorized access. Authentication token missing or invalid.';
    http_response_code(401);
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// --- 2. Database Fetching ---
$pdo = null;

try {
    // Connect using PDO
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // 3. Execute SIMPLE SELECT query from the teachers table
    // This query selects all necessary fields for the dashboard table view.
    $sql = "
        SELECT 
            teacher_id, 
            full_name, 
            email, 
            department, 
            activation_code, 
            is_active, 
            created_at,
            qr_token
        FROM 
            teachers 
        ORDER BY 
            created_at DESC 
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Success Response
    $response['status'] = 'success';
    $response['teachers'] = $teachers;
    $response['count'] = count($teachers);
    $response['message'] = 'Teacher accounts fetched successfully from the teachers table.';
    http_response_code(200);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error in fetch_logs.php: " . $e->getMessage());
    // The specific error about the logs table is now gone, but if the teachers table is missing, 
    // it will throw an error related to that table name.
    $response['message'] = 'Database error retrieving teacher data. Check that the **teachers** table exists: ' . $e->getMessage();
} catch (Exception $e) {
    http_response_code(500);
    error_log("Server Error in fetch_logs.php: " . $e->getMessage());
    $response['message'] = 'A general server error occurred.';
}

// 5. Final Cleanup and Output
$pdo = null;
ob_end_clean();
echo json_encode($response);
