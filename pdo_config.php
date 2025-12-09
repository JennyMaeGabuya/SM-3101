<?php

/**
 * Database Configuration Constants for PDO
 * * This file defines the constants required by PDO connection scripts.
 * * It ensures DB_PASS is set to an empty string for the local 'root' user.
 */

// Hostname
define('DB_HOST', '127.0.0.1');

// Port Number
define('DB_PORT', '3307');

// Database User
define('DB_USER', 'root');

// Database Password: Set to EMPTY STRING to fix the 'Access denied' error
define('DB_PASS', '');

// Database Name
define('DB_NAME', 'sanroom');

// Character Set
define('DB_CHARSET', 'utf8mb4');
