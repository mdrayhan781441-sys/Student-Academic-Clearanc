<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('super_admin');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['request_id']) || !isset($data['action'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$request_id = intval($data['request_id']);
$action = sanitize($data['action']); // 'approve' or 'reject'

// Get request details
$stmt = $conn->prepare("SELECT student_id FROM clearance_requests WHERE id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Request not found']));
}

$request = $result->fetch_assoc();
$student_id = $request['student_id'];

if ($action === 'approve') {
    // Update status to Approved
    $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Approved', super_admin_action = 'Approved', super_admin_timestamp = NOW() WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Clearance request approved by Super Admin!'
        ]);
    } else {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to approve request']));
    }
    
} elseif ($action === 'reject') {
    // Rejection requires a reason
    if (!isset($data['reason']) || empty($data['reason'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Rejection reason is required']));
    }
    
    $reason = sanitize($data['reason']);
    
    // Update status to Rejected with super admin rejection reason
    $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Rejected', super_admin_action = 'Rejected', admin_rejection_reason = ?, super_admin_timestamp = NOW() WHERE id = ?");
    $stmt->bind_param("si", $reason, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Clearance request rejected by Super Admin. Student has been notified.'
        ]);
    } else {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to reject request']));
    }
} else {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid action']));
}

$conn->close();
?>
