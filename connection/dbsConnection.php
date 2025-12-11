<?php

$serverName = "localhost";
$username = "root";
$password = "";
$databaseName = "learnHub";

// -----Connection-----
try {
    $connection = new mysqli($serverName, $username, $password, $databaseName);
    // -----Check connection-----
    if ($connection->connect_error) {
        error_log('DB Connection Failed: ' . $connection->connect_error);
        $connection = null;
    }
} catch (mysqli_sql_exception $e) {
    // Log the exception instead of letting it crash the app
    error_log('DB Connection Exception: ' . $e->getMessage());
    $connection = null;
}
