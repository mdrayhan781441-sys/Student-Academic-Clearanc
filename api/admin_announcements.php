<?php
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['role'])) {
    ob_clean();
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$role = $_SESSION['role'];
$user_id = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

// Get User's Department (if they are not super admin)
$user_dept = '';
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT TRIM(d.name) as dept FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $user_dept = $user_data ? $user_data['dept'] : '';
} elseif ($role === 'student') {
    $stmt = $conn->prepare("SELECT TRIM(d.name) as dept FROM students s LEFT JOIN departments d ON s.department_id = d.id WHERE s.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $user_dept = $user_data ? $user_data['dept'] : '';
}

if ($method === 'GET') {
    if ($role === 'super_admin') {
        // Super admin sees all announcements
        $stmt = $conn->prepare("SELECT a.*, COALESCE(adm.username, 'Super Admin') as author 
                                FROM announcements a 
                                LEFT JOIN admins adm ON a.admin_id = adm.id 
                                ORDER BY a.created_at DESC");
    } else {
        if (empty($user_dept)) {
            ob_clean();
            die(json_encode(['success' => true, 'announcements' => []]));
        }
        if ($role === 'student') {
            $stmt = $conn->prepare("SELECT a.*, COALESCE(adm.username, 'Admin') as author 
                                    FROM announcements a 
                                    LEFT JOIN admins adm ON a.admin_id = adm.id 
                                    WHERE (TRIM(LOWER(a.department)) = TRIM(LOWER(?)) OR TRIM(LOWER(a.department)) = 'all' OR TRIM(LOWER(a.department)) = 'library')
                                      AND (a.start_date IS NULL OR a.start_date <= NOW())
                                      AND (a.end_date IS NULL OR a.end_date >= NOW())
                                    ORDER BY a.created_at DESC");
            $stmt->bind_param("s", $user_dept);
        } elseif ($user_dept === 'Library') {
            // Library admin sees all announcements
            $stmt = $conn->prepare("SELECT a.*, COALESCE(adm.username, 'Admin') as author 
                                    FROM announcements a 
                                    LEFT JOIN admins adm ON a.admin_id = adm.id 
                                    ORDER BY a.created_at DESC");
        } else {
            // Fetch announcements for this department, "all" (both active and inactive for admins)
            $stmt = $conn->prepare("SELECT a.*, COALESCE(adm.username, 'Admin') as author 
                                    FROM announcements a 
                                    LEFT JOIN admins adm ON a.admin_id = adm.id 
                                    WHERE TRIM(LOWER(a.department)) = TRIM(LOWER(?)) OR TRIM(LOWER(a.department)) = 'all'
                                    ORDER BY a.created_at DESC");
            $stmt->bind_param("s", $user_dept);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'announcements' => $announcements]);

} elseif ($method === 'POST') {
    if ($role !== 'admin' && $role !== 'super_admin') {
        ob_clean();
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Only administrators can broadcast announcements']));
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $title = isset($data['title']) ? sanitize($data['title']) : '';
    $message = isset($data['message']) ? sanitize($data['message']) : '';
    
    // Determine target department
    if ($role === 'super_admin') {
        $target_dept = isset($data['department']) ? sanitize($data['department']) : 'All';
    } else {
        // Locked to admin's own department
        $target_dept = $user_dept;
    }
    
    if (empty($title) || empty($message)) {
        ob_clean();
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Title and message are required']));
    }
    
    $start_date = (isset($data['start_date']) && !empty($data['start_date'])) ? sanitize($data['start_date']) : null;
    $end_date = (isset($data['end_date']) && !empty($data['end_date'])) ? sanitize($data['end_date']) : null;

    $stmt = $conn->prepare("INSERT INTO announcements (admin_id, department, title, message, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $target_dept, $title, $message, $start_date, $end_date);
    
    if ($stmt->execute()) {
        // Create notifications for targeted students
        if (strcasecmp($target_dept, 'all') === 0 || strcasecmp($target_dept, 'library') === 0) {
            $stmt_students = $conn->prepare("SELECT id FROM students");
        } else {
            $stmt_students = $conn->prepare("SELECT s.id FROM students s JOIN departments d ON s.department_id = d.id WHERE d.name = ?");
            $stmt_students->bind_param("s", $target_dept);
        }
        
        $stmt_students->execute();
        $student_ids = $stmt_students->get_result();
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'student', ?, ?)");
        while ($student = $student_ids->fetch_assoc()) {
            $notif_stmt->bind_param("iss", $student['id'], $title, $message);
            $notif_stmt->execute();
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Announcement broadcasted successfully']);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to broadcast announcement']);
    }
}

$conn->close();
exit();
?>
