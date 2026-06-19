<?php
require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
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

$stmt = $conn->prepare("SELECT m.id, m.message, m.sender_role, m.created_at, m.recipient_role, m.sender_id,
                        a.department_id, d.name AS sender_dept,
                        CASE 
                            WHEN m.sender_role = 'student' THEN s.name
                            WHEN m.sender_role IN ('admin', 'super_admin') THEN a.username
                        END as sender_name
                        FROM support_messages m
                        LEFT JOIN students s ON m.sender_role = 'student' AND m.sender_id = s.id
                        LEFT JOIN admins a ON m.sender_role != 'student' AND m.sender_id = a.id
                        LEFT JOIN departments d ON a.department_id = d.id
                        WHERE m.request_id = ? 
                        ORDER BY m.created_at ASC");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

// Mark messages as read depending on who is calling
$user_role = $_SESSION['role'];

if ($user_role === 'student') {
    $recipient_role = $_GET['recipient_role'] ?? null;
    
    // Find Library department ID
    $lib_stmt = $conn->prepare("SELECT id FROM departments WHERE name = 'Library' LIMIT 1");
    $lib_stmt->execute();
    $lib_res = $lib_stmt->get_result();
    $lib_dept_id = $lib_res->num_rows > 0 ? $lib_res->fetch_assoc()['id'] : 0;
    $lib_stmt->close();
    
    if ($recipient_role === 'admin') {
        $up_stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 
                                   WHERE request_id = ? AND sender_role = 'admin' AND is_read = 0 
                                   AND sender_id IN (SELECT id FROM admins WHERE department_id IS NULL OR department_id != ?)");
        $up_stmt->bind_param("ii", $request_id, $lib_dept_id);
        $up_stmt->execute();
        $up_stmt->close();
    } elseif ($recipient_role === 'library') {
        $up_stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 
                                   WHERE request_id = ? AND sender_role = 'admin' AND is_read = 0 
                                   AND sender_id IN (SELECT id FROM admins WHERE department_id = ?)");
        $up_stmt->bind_param("ii", $request_id, $lib_dept_id);
        $up_stmt->execute();
        $up_stmt->close();
    } elseif ($recipient_role === 'super_admin') {
        $up_stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 
                                   WHERE request_id = ? AND sender_role = 'super_admin' AND is_read = 0");
        $up_stmt->bind_param("i", $request_id);
        $up_stmt->execute();
        $up_stmt->close();
    }
} elseif ($user_role === 'admin') {
    $admin_dept = $_SESSION['department'];
    if (strcasecmp($admin_dept, 'Library') === 0) {
        $up_stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 
                                   WHERE request_id = ? AND sender_role = 'student' 
                                   AND recipient_role = 'library' AND is_read = 0");
        $up_stmt->bind_param("i", $request_id);
        $up_stmt->execute();
        $up_stmt->close();
    } else {
        $up_stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 
                                   WHERE request_id = ? AND sender_role = 'student' 
                                   AND (recipient_role = 'admin' OR recipient_role IS NULL) AND is_read = 0");
        $up_stmt->bind_param("i", $request_id);
        $up_stmt->execute();
        $up_stmt->close();
    }
} elseif ($user_role === 'super_admin') {
    $up_stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 
                               WHERE request_id = ? AND sender_role = 'student' 
                               AND recipient_role = 'super_admin' AND is_read = 0");
    $up_stmt->bind_param("i", $request_id);
    $up_stmt->execute();
    $up_stmt->close();
}

echo json_encode(['success' => true, 'messages' => $messages]);

