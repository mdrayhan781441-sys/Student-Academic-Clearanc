<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isLoggedIn()) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$role = $_SESSION['role'];
if ($role !== 'admin' && $role !== 'super_admin') {
    die(json_encode(['success' => false, 'message' => 'Forbidden']));
}

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['request_ids']) || !is_array($data['request_ids'])) {
    die(json_encode(['success' => false, 'message' => 'Missing request IDs']));
}

$request_ids = $data['request_ids'];
$action = $data['action'] ?? 'approve';
$success_count = 0;

foreach ($request_ids as $id) {
    $request_id = intval($id);
    
    if ($role === 'admin') {
        // Admin approval logic
        $admin_dept = $_SESSION['department'];
        
        // Verify department first
        $check_stmt = $conn->prepare("SELECT s.id as student_id, d.name AS department FROM clearance_requests cr JOIN students s ON cr.student_id = s.id LEFT JOIN departments d ON s.department_id = d.id WHERE cr.id = ?");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result()->fetch_assoc();
        
        if ($res && ($admin_dept === 'Library' || strcasecmp($res['department'], $admin_dept) === 0)) {
            $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Approved', admin_action = 'Approved', admin_timestamp = NOW() WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                $success_count++;
                // Notify
                $title = "Clearance Approved (Bulk)";
                $msg = "Your clearance request has been approved by the " . $admin_dept . " department admin.";
                $notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'student', ?, ?)");
                $notif->bind_param("iss", $res['student_id'], $title, $msg);
                $notif->execute();
            }
        }
    } else if ($role === 'super_admin') {
        // Super Admin approval logic
        $check_stmt = $conn->prepare("SELECT student_id FROM clearance_requests WHERE id = ? AND status = 'Approved'");
        $check_stmt->bind_param("i", $request_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result()->fetch_assoc();
        
        if ($res) {
            $stmt = $conn->prepare("UPDATE clearance_requests SET super_admin_action = 'Approved', super_admin_timestamp = NOW() WHERE id = ?");
            $stmt->bind_param("i", $request_id);
            if ($stmt->execute()) {
                // Auto generate certificate data (simulated)
                $cert_data = "OFFICIAL CLEARANCE CERTIFICATE FOR STUDENT #" . $res['student_id'];
                $cert_stmt = $conn->prepare("INSERT INTO certificates (student_id, certificate_data) VALUES (?, ?)");
                $cert_stmt->bind_param("is", $res['student_id'], $cert_data);
                $cert_stmt->execute();
                
                $success_count++;
                
                // Notify
                $title = "Final Clearance Approved!";
                $msg = "Your academic clearance has been fully approved by the Super Admin. You can now download your certificate.";
                $notif = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'student', ?, ?)");
                $notif->bind_param("iss", $res['student_id'], $title, $msg);
                $notif->execute();
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => "Successfully processed $success_count requests."
]);
