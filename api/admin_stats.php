<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

// Allow both admin and superadmin to see stats
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Determine if we need to filter by department
$admin_dept = isset($_SESSION['department']) ? $_SESSION['department'] : '';
$is_superadmin = ($_SESSION['role'] === 'superadmin' || $_SESSION['role'] === 'super_admin' || $admin_dept === 'Library');

// 1. SELECT Operation (Filtered by department if not superadmin)
$total_students_query = $is_superadmin 
    ? "SELECT COUNT(*) as count FROM students" 
    : "SELECT COUNT(*) as count FROM students s JOIN departments dept ON s.department_id = dept.id WHERE dept.name = '$admin_dept'";
$total_students = $conn->query($total_students_query)->fetch_assoc()['count'];

// 2. AGGREGATE Function (SUM) - Filtered by department if not superadmin
$total_collection_query = $is_superadmin 
    ? "SELECT SUM(total_paid) as total FROM payments" 
    : "SELECT SUM(p.total_paid) as total FROM payments p JOIN students s ON p.student_id = s.id JOIN departments dept ON s.department_id = dept.id WHERE dept.name = '$admin_dept'";
$total_collection = $conn->query($total_collection_query)->fetch_assoc()['total'] ?? 0;

// 3. JOIN Query - Filtered by department if not superadmin
$recent_activities_query = $is_superadmin 
    ? "SELECT s.name, c.status, c.created_at FROM clearance_requests c JOIN students s ON c.student_id = s.id ORDER BY c.created_at DESC LIMIT 5"
    : "SELECT s.name, c.status, c.created_at FROM clearance_requests c JOIN students s ON c.student_id = s.id JOIN departments dept ON s.department_id = dept.id WHERE dept.name = '$admin_dept' ORDER BY c.created_at DESC LIMIT 5";
    
$recent_activities = $conn->query($recent_activities_query);
$activities = [];
while($row = $recent_activities->fetch_assoc()) {
    $activities[] = $row;
}

// 4. SUBQUERY - Filtered by department if not superadmin
$subquery = $is_superadmin 
    ? "SELECT COUNT(*) as count FROM students WHERE id IN (SELECT student_id FROM clearance_requests WHERE status = 'Pending')"
    : "SELECT COUNT(*) as count FROM students s JOIN departments dept ON s.department_id = dept.id WHERE dept.name = '$admin_dept' AND s.id IN (SELECT student_id FROM clearance_requests WHERE status = 'Pending')";
$pending_count = $conn->query($subquery)->fetch_assoc()['count'];

// Extra stats for approved requests
$approved_query = $is_superadmin 
    ? "SELECT COUNT(*) as count FROM clearance_requests WHERE status = 'Approved'"
    : "SELECT COUNT(*) as count FROM clearance_requests cr JOIN students s ON cr.student_id = s.id JOIN departments dept ON s.department_id = dept.id WHERE dept.name = '$admin_dept' AND cr.status = 'Approved'";
$approved_count = $conn->query($approved_query)->fetch_assoc()['count'];

// Total requests (any status)
$total_requests_query = $is_superadmin 
    ? "SELECT COUNT(*) as count FROM clearance_requests"
    : "SELECT COUNT(*) as count FROM clearance_requests cr JOIN students s ON cr.student_id = s.id JOIN departments dept ON s.department_id = dept.id WHERE dept.name = '$admin_dept'";
$total_requests_count = $conn->query($total_requests_query)->fetch_assoc()['count'];

echo json_encode([
    'success' => true,
    'stats' => [
        'total_students' => $total_students,
        'total_collection' => floatval($total_collection),
        'pending_requests_count' => $pending_count,
        'approved_requests_count' => $approved_count,
        'total_requests_count' => $total_requests_count
    ],
    'recent_activities' => $activities
]);

$conn->close();
