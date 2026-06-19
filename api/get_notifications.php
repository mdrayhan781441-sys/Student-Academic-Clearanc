<?php
require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$stmt = $conn->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? AND user_role = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode(['success' => true, 'notifications' => $notifications]);
