<?php
require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$request_id = $data['request_id'] ?? null;
$message = $data['message'] ?? null;
$recipient_role = $data['recipient_role'] ?? null;

if (!$request_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

// Security Check
if ($_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("SELECT id FROM clearance_requests WHERE id = ? AND student_id = ?");
    $stmt->bind_param("ii", $request_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} else if ($_SESSION['role'] === 'admin') {
    $admin_dept = $_SESSION['department'];
    $stmt = $conn->prepare("SELECT cr.status, cr.super_admin_action, d.name AS department FROM clearance_requests cr JOIN students s ON cr.student_id = s.id LEFT JOIN departments d ON s.department_id = d.id WHERE cr.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req_info = $stmt->get_result()->fetch_assoc();
    
    if (!$req_info) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    // Bypass department restriction only for Library role
    if (strcasecmp($admin_dept, 'Library') !== 0 && strcasecmp($req_info['department'], $admin_dept) !== 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied: another department']);
        exit;
    }
} else if ($_SESSION['role'] === 'super_admin') {
    $stmt = $conn->prepare("SELECT status, super_admin_action FROM clearance_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req_info = $stmt->get_result()->fetch_assoc();
    
    if (!$req_info) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
}

$sender_role = $_SESSION['role'];
$sender_id = $_SESSION['user_id'];

if ($sender_role === 'super_admin') {
    $stmt_sa = $conn->prepare("SELECT id FROM admins WHERE username = 'super_admin' LIMIT 1");
    $stmt_sa->execute();
    $res_sa = $stmt_sa->get_result();
    if ($res_sa->num_rows > 0) {
        $sender_id = $res_sa->fetch_assoc()['id'];
    }
    $stmt_sa->close();
}

// Auto-resolve recipient_role if not sent (e.g. from admin dashboards where it is always student)
if ($sender_role === 'admin' || $sender_role === 'super_admin') {
    $recipient_role = 'student';
}

$stmt = $conn->prepare("INSERT INTO support_messages (request_id, sender_role, sender_id, message, recipient_role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isiss", $request_id, $sender_role, $sender_id, $message, $recipient_role);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>
