<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('admin');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['request_id']) || !isset($data['action'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$request_id = intval($data['request_id']);
$action = sanitize($data['action']); // 'approve' or 'reject'

// Get request details and check department
$admin_dept = $_SESSION['department'];
$stmt = $conn->prepare("SELECT cr.student_id, d.name AS department FROM clearance_requests cr JOIN students s ON cr.student_id = s.id LEFT JOIN departments d ON s.department_id = d.id WHERE cr.id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Request not found']));
}

$request = $result->fetch_assoc();
if ($admin_dept !== 'Library' && strcasecmp($request['department'], $admin_dept) !== 0) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized: This student belongs to another department']));
}
$student_id = $request['student_id'];

if ($action === 'approve') {
    // Update status to Approved
    $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Approved', admin_action = 'Approved', admin_timestamp = NOW() WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    
    if ($stmt->execute()) {
        // 1. Create Notification for Student
        $title = "Clearance Approved by Admin";
        $message = "Your clearance request has been approved by the " . $admin_dept . " department admin and forwarded to the Super Admin for final verification.";
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'student', ?, ?)");
        $stmt_notif->bind_param("iss", $student_id, $title, $message);
        $stmt_notif->execute();

        // 2. Create Notification for Super Admin
        // Find super admin users (there might be one or more)
        $super_admins = $conn->query("SELECT id FROM admins WHERE role = 'SuperAdmin'");
        while ($sa = $super_admins->fetch_assoc()) {
            $sa_title = "New Approval: " . $admin_dept;
            $sa_message = "A clearance request from " . $admin_dept . " department has been approved by the admin and requires your final signature.";
            $stmt_sa_notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'super_admin', ?, ?)");
            $stmt_sa_notif->bind_param("iss", $sa['id'], $sa_title, $sa_message);
            $stmt_sa_notif->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Clearance request approved! Request forwarded to Super Admin.'
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
    
    // Update status to Rejected
    $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Rejected', admin_action = 'Rejected', admin_rejection_reason = ?, admin_timestamp = NOW() WHERE id = ?");
    $stmt->bind_param("si", $reason, $request_id);
    
    if ($stmt->execute()) {
        // Create Notification
        $title = "Clearance Request Rejected";
        $message = "Your clearance request for the " . $admin_dept . " department was rejected. Reason: " . $reason;
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'student', ?, ?)");
        $stmt_notif->bind_param("iss", $student_id, $title, $message);
        $stmt_notif->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Clearance request rejected. Student has been notified.'
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
