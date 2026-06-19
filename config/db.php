<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_clearance');

// Create Connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check Connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set charset
$conn->set_charset("utf8");

// Function to sanitize input
function sanitize($data) {
    global $conn;
    return htmlspecialchars($conn->real_escape_string($data));
}

// Align PHP timezone with MySQL timezone dynamically
$tz_query = $conn->query("SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP()) as offset");
if ($tz_query) {
    $tz_row = $tz_query->fetch_assoc();
    $offset = $tz_row['offset'];
    if ($offset) {
        $parts = explode(':', $offset);
        $hours = intval($parts[0]);
        $sign = ($hours >= 0) ? '-' : '+';
        $abs_hours = abs($hours);
        $tz_name = "Etc/GMT" . $sign . $abs_hours;
        date_default_timezone_set($tz_name);
    }
}
?>
