<?php
/*
 * DATABASE CONNECTION FILE
 * This one file will be included in all other PHP files.
 */

// 1. Define your database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Your XAMPP password (usually empty)
define('DB_NAME', 'colligo_db');

// 2. Create the connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// 3. Check the connection
if ($conn->connect_error) {
    // If connection fails, stop the script and show the error
    die("Connection failed: " . $conn->connect_error);
}

// 4. Set the character set (good practice)
$conn->set_charset("utf8mb4");

// Start a session
// We start the session here so it's available on every page
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>