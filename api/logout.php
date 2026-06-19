<?php
require_once '../config/session.php';

logout();

// If it's a GET request (direct browser navigation), redirect to the homepage
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: ../index.php');
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
exit;
?>
