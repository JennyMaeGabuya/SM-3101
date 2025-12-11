<?php
include "connection/dbsConnection.php";

// Start session at the beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if connection exists
if (!$connection) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Handle username check request (for real-time validation)
if (isset($_GET['check_username'])) {
    $username = trim($_GET['check_username']);
    $stmt = $connection->prepare("SELECT teacher_id FROM teachers WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode(['exists' => $result->num_rows > 0]);
    $stmt->close();
    $connection->close();
    exit;
}

// Handle email check request (for real-time validation)
if (isset($_GET['check_email'])) {
    $email = trim($_GET['check_email']);
    $stmt = $connection->prepare("SELECT teacher_id FROM teachers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    echo json_encode(['exists' => $result->num_rows > 0]);
    $stmt->close();
    $connection->close();
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || !isset($data['department'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Sanitize and validate inputs
$fullName = trim($data['name']);
$email = trim($data['email']);
$password = $data['password'];
$department = trim($data['department']);
$username = isset($data['username']) ? trim($data['username']) : explode('@', $email)[0];

// Split full name into first and last name
$nameParts = explode(' ', $fullName, 2);
$firstName = $nameParts[0];
$lastName = isset($nameParts[1]) ? $nameParts[1] : '';

// Validate inputs
if (empty($firstName)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid name'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters long'
    ]);
    exit;
}

if (empty($username)) {
    echo json_encode([
        'success' => false,
        'message' => 'Username is required'
    ]);
    exit;
}

// Validate SR-CODE format (XX-XXXXX)
if (!preg_match('/^\d{2}-\d{5}$/', $username)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid SR-CODE format. Use format: 00-00000'
    ]);
    exit;
}

// Check if email already exists
$stmt = $connection->prepare("SELECT teacher_id FROM teachers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Email already registered'
    ]);
    $stmt->close();
    exit;
}
$stmt->close();

// Check if username already exists
$stmt = $connection->prepare("SELECT teacher_id FROM teachers WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'SR-CODE already registered'
    ]);
    $stmt->close();
    exit;
}
$stmt->close();

// Hash password securely
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert teacher into database
$stmt = $connection->prepare("INSERT INTO teachers (username, email, password, first_name, last_name, department, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->bind_param("ssssss", $username, $email, $hashedPassword, $firstName, $lastName, $department);

if ($stmt->execute()) {
    $teacherId = $connection->insert_id;

    echo json_encode([
        'success' => true,
        'message' => 'Teacher account created successfully',
        'user' => [
            'id' => $teacherId,
            'teacher_id' => $teacherId,
            'username' => $username,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $fullName,
            'department' => $department,
            'role' => 'teacher'
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create account. Please try again.'
    ]);
    error_log('Teacher registration error: ' . $stmt->error);
}

$stmt->close();
$connection->close();
