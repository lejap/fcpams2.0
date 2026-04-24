<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change this for Bluehost
define('DB_PASS', '');     // Change this for Bluehost
define('DB_NAME', 'fcpams');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Timezone
date_default_timezone_set('Asia/Manila'); // Adjust as per USER'S region
?>