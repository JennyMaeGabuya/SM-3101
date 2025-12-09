<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$display_name = '';
$role_label = '';

// Prefer an explicit display name set during login, otherwise derive from email
if (!empty($_SESSION['display_name'])) {
  $display_name = $_SESSION['display_name'];
} elseif (!empty($_SESSION['email'])) {
  $namePart = strstr($_SESSION['email'], '@', true);
  $display_name = $namePart ? ucfirst(str_replace(['.', '_', '-'], ' ', $namePart)) : $_SESSION['email'];
} else {
  $display_name = 'Guest';
}

$role = $_SESSION['role'] ?? 'guest';
if ($role === 'teacher') {
  $role_label = 'Teacher';
} elseif ($role === 'student') {
  $role_label = 'Student';
} else {
  $role_label = ucfirst($role);
}
?>

<head>
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<header class="navbar">
  <div class="logo">SanRoom</div>
  <div class="user-info">
    <p>Current Time: <span id="currentTime"></span></p>
    <div class="user-right">
      <span class="username"><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></span>
      <small class="role"><?php echo htmlspecialchars($role_label, ENT_QUOTES, 'UTF-8'); ?></small>
      <button class="btn-logout">Logout</button>
    </div>
  </div>
</header>

<script src="assets/js/logout.js"></script>

<style>
  /* Base style for the logout button */
  .btn-logout {
    background-color: #e74c3c;
    /* Initial background color */
    color: #fff;
    /* Text color */
    border: none;
    padding: 10px 20px;
    font-size: 16px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.3s ease;
    /* Smooth transitions */
  }

  /* Hover effect for the logout button */
  .btn-logout:hover {
    background-color: #c0392b !important;
    /* Red on hover */
    transform: scale(1.05) !important;
    /* Slight scaling effect */
  }

  /* Active (clicked) effect for the logout button */
  .btn-logout:active {
    background-color: #e74c3c !important;
    /* Bright red when clicked */
    transform: scale(1.05) !important;
    /* Maintain scaling effect */
  }
</style>