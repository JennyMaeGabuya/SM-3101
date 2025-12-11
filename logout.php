<?php
// Global logout endpoint — destroys session and redirects to login page with a flag
if (session_status() === PHP_SESSION_NONE) session_start();

// Clear all session data
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect back to login with logged_out flag so UI shows a message
header('Location: /LearnHub/login.php?logged_out=1');
exit;
?>