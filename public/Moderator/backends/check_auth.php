<?php
session_start();
header('Content-Type: application/json');

$response = [
    'authenticated' => false,
    'user_role' => null,
    'user_id' => null
];

// Check if the user is logged in and has the correct role
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'super_admin') {
    $response['authenticated'] = true;
    $response['user_role'] = $_SESSION['user_role'];
    $response['user_id'] = $_SESSION['user_id'];
}

echo json_encode($response);
