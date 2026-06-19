<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('student');

$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['id'] ?? null;
$student_id = $_SESSION['user_id'];

if (!$notification_id) {
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit;
}

// DELETE Operation - Required by Guideline
// We ensure the student can only delete their own notifications
$stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND student_id = ?");
$stmt->bind_param("ii", $notification_id, $student_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notification deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
}

$stmt->close();
$conn->close();
