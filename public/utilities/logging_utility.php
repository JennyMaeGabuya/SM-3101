<?php
// /SANROOM/includes/logging_utility.php

/**
 * Logs an action to the user_logs table.
 * * @param mysqli $conn The database connection object.
 * @param string $user_type 'student' or 'teacher'.
 * @param int $user_id The ID of the user.
 * @param string $action_type e.g., 'login_success', 'login_fail', 'account_created'.
 * @return bool True on successful log insertion.
 */
function log_account_action($conn, $user_type, $user_id, $action_type)
{
    // Basic IP address retrieval (be aware of proxies/load balancers)
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    $log_sql = "INSERT INTO user_logs (user_type, user_id, action_type, ip_address) 
                VALUES (?, ?, ?, ?)";

    if (!$log_stmt = $conn->prepare($log_sql)) {
        error_log("Logging preparation failed: " . $conn->error);
        return false;
    }

    $log_stmt->bind_param("siss", $user_type, $user_id, $action_type, $ip_address);
    $result = $log_stmt->execute();

    if (!$result) {
        error_log("Logging execution failed for $user_type $user_id: " . $log_stmt->error);
    }

    $log_stmt->close();
    return $result;
}
