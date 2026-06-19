<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('admin');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['student_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing student ID']));
}

$student_db_id = intval($data['student_id']);
$admin_dept = $_SESSION['department'];

// 1. Verify student exists and belongs to the admin's department
$stmt = $conn->prepare("SELECT s.id, s.name, dept.name AS department FROM students s LEFT JOIN departments dept ON s.department_id = dept.id WHERE s.id = ?");
$stmt->bind_param("i", $student_db_id);
$stmt->execute();
$student_result = $stmt->get_result();

if ($student_result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Student not found']));
}

$student = $student_result->fetch_assoc();
if ($admin_dept !== 'Library' && strcasecmp($student['department'], $admin_dept) !== 0) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized: This student belongs to another department']));
}

// 2. Check if a clearance request already exists for this student
$stmt = $conn->prepare("SELECT id, status FROM clearance_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $student_db_id);
$stmt->execute();
$req_result = $stmt->get_result();

$success = false;
$msg = '';

if ($req_result->num_rows > 0) {
    // If request exists, update the status of the latest one to Approved
    $req = $req_result->fetch_assoc();
    if ($req['status'] === 'Approved') {
        die(json_encode(['success' => true, 'message' => 'Student is already approved']));
    }
    
    $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Approved', admin_action = 'Approved', admin_timestamp = NOW() WHERE id = ?");
    $stmt->bind_param("i", $req['id']);
    $success = $stmt->execute();
    $msg = 'Student clearance has been approved successfully!';
} else {
    // If no request exists, insert a brand new approved request
    $stmt = $conn->prepare("INSERT INTO clearance_requests (student_id, message, status, admin_action, admin_timestamp) VALUES (?, 'Directly approved by department administrator.', 'Approved', 'Approved', NOW())");
    $stmt->bind_param("i", $student_db_id);
    $success = $stmt->execute();
    $msg = 'Clearance request created and approved successfully!';
}

if ($success) {
    // 3. Create Notification for Student
    $title = "Clearance Approved directly by Admin";
    $notif_msg = "Your clearance has been approved directly by the " . $admin_dept . " department admin and forwarded to the Super Admin for final verification.";
    $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'student', ?, ?)");
    $stmt_notif->bind_param("iss", $student_db_id, $title, $notif_msg);
    $stmt_notif->execute();

    // 4. Create Notification for Super Admin
    $super_admins = $conn->query("SELECT id FROM admins WHERE role = 'SuperAdmin'");
    while ($sa = $super_admins->fetch_assoc()) {
        $sa_title = "Direct Approval: " . $admin_dept;
        $sa_message = "A clearance request has been directly approved by the " . $admin_dept . " admin for student " . $student['name'] . " and requires your final signature.";
        $stmt_sa_notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'super_admin', ?, ?)");
        $stmt_sa_notif->bind_param("iss", $sa['id'], $sa_title, $sa_message);
        $stmt_sa_notif->execute();
    }

    echo json_encode(['success' => true, 'message' => $msg]);
} else {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Failed to process direct approval']));
}

$conn->close();
?>
