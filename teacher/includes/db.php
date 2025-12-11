<?php
// Reuse existing connection settings
require_once __DIR__ . '/../../connection/dbsConnection.php';

// $connection should be available from dbsConnection.php
if (!isset($connection) || !$connection) {
    die('Database connection not available.');
}

// Start session for teacher auth (simple placeholder)
if (session_status() === PHP_SESSION_NONE) session_start();

?>
