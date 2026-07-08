<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change this for Bluehost
define('DB_PASS', '');     // Change this for Bluehost
define('DB_NAME', 'fcpams');

// Auto-detect the base URL (works on localhost/fcpamsweb and Bluehost root)
$_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
if (strpos($_script, '/fcpamsweb/') !== false) {
    define('BASE_URL', '/fcpamsweb/');
} else {
    define('BASE_URL', '/');
}

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Timezone
date_default_timezone_set('Asia/Manila'); // Adjust as per USER'S region

// Sync database timezone with PHP timezone
$conn->query("SET time_zone = '" . date('P') . "';");
?>