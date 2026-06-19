<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

// Allow both admin and super_admin
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$role = $_SESSION['role'];
$user_id = getCurrentUserId();

// Determine department filter
$dept_filter = 'All';
if ($role === 'admin') {
    // Get Admin's Department
    $stmt = $conn->prepare("SELECT d.name AS department FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin_dept = $stmt->get_result()->fetch_assoc()['department'];
    $dept_filter = $admin_dept;
} else {
    // Super admin can select via GET parameter
    $dept_filter = isset($_GET['department']) ? sanitize($_GET['department']) : 'All';
}

$use_filter = ($dept_filter !== 'All' && $dept_filter !== 'Library' && !empty($dept_filter));

// 1. Financial Report Data
if ($use_filter) {
    $stmt = $conn->prepare("SELECT 
                            SUM(d.library_due + d.hostel_due + d.tuition_due) as total_dues,
                            SUM(p.total_paid) as total_collected
                            FROM students s
                            JOIN departments dept ON s.department_id = dept.id
                            JOIN dues d ON s.id = d.student_id
                            JOIN payments p ON s.id = p.student_id
                            WHERE dept.name = ?");
    $stmt->bind_param("s", $dept_filter);
} else {
    $stmt = $conn->prepare("SELECT 
                            SUM(d.library_due + d.hostel_due + d.tuition_due) as total_dues,
                            SUM(p.total_paid) as total_collected
                            FROM students s
                            JOIN dues d ON s.id = d.student_id
                            JOIN payments p ON s.id = p.student_id");
}
$stmt->execute();
$financials = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Clearance Stats Data
if ($use_filter) {
    $stmt = $conn->prepare("SELECT 
                            SUM(CASE WHEN cr.status = 'Approved' THEN 1 ELSE 0 END) as cleared,
                            SUM(CASE WHEN cr.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN cr.status IS NULL THEN 1 ELSE 0 END) as not_started
                            FROM students s
                            LEFT JOIN clearance_requests cr ON s.id = cr.student_id
                            JOIN departments dept ON s.department_id = dept.id
                            WHERE dept.name = ?");
    $stmt->bind_param("s", $dept_filter);
} else {
    $stmt = $conn->prepare("SELECT 
                            SUM(CASE WHEN cr.status = 'Approved' THEN 1 ELSE 0 END) as cleared,
                            SUM(CASE WHEN cr.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN cr.status IS NULL THEN 1 ELSE 0 END) as not_started
                            FROM students s
                            LEFT JOIN clearance_requests cr ON s.id = cr.student_id");
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Recent Students Activity
if ($use_filter) {
    $stmt = $conn->prepare("SELECT s.student_id, s.name, dept.name AS department, cr.status, cr.updated_at 
                            FROM students s
                            INNER JOIN clearance_requests cr ON s.id = cr.student_id
                            JOIN departments dept ON s.department_id = dept.id
                            WHERE dept.name = ?
                            ORDER BY cr.updated_at DESC
                            LIMIT 10");
    $stmt->bind_param("s", $dept_filter);
} else {
    $stmt = $conn->prepare("SELECT s.student_id, s.name, dept.name AS department, cr.status, cr.updated_at 
                            FROM students s
                            INNER JOIN clearance_requests cr ON s.id = cr.student_id
                            LEFT JOIN departments dept ON s.department_id = dept.id
                            ORDER BY cr.updated_at DESC
                            LIMIT 10");
}
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success' => true,
    'department' => $dept_filter,
    'financials' => [
        'total_dues' => floatval($financials['total_dues'] ?? 0),
        'total_collected' => floatval($financials['total_collected'] ?? 0),
        'outstanding' => floatval($financials['total_dues'] ?? 0) - floatval($financials['total_collected'] ?? 0)
    ],
    'clearance_stats' => [
        'cleared' => intval($stats['cleared'] ?? 0),
        'pending' => intval($stats['pending'] ?? 0),
        'not_started' => intval($stats['not_started'] ?? 0)
    ],
    'recent_activity' => $recent_activity
]);

$conn->close();
?>
