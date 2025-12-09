<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page or just exit (since JS will handle redirect)
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
exit;
