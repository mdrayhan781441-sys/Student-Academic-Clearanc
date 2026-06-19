<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$role = $_SESSION['role'];
$department_filter = null;

if ($role === 'admin') {
    // Department admins can only view their own department's students (except Library admin)
    if ($_SESSION['department'] === 'Library') {
        $department_filter = null;
    } else {
        $department_filter = $_SESSION['department'];
    }
} elseif ($role === 'super_admin') {
    // Super admins can optionally filter by department via GET parameter
    $department_filter = isset($_GET['department']) ? sanitize($_GET['department']) : null;
} else {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

// Fetch all students (with optional department filter) and their latest clearance status
$query = "SELECT s.id, s.student_id, s.name, s.email, dept.name AS department, s.cgpa,
                 cr.status as clearance_status, cr.id as clearance_id, cr.created_at as request_date,
                 (COALESCE(d.library_due, 0) + COALESCE(d.hostel_due, 0) + COALESCE(d.tuition_due, 0)) as total_due,
                 (CASE WHEN c.student_id IS NOT NULL THEN 1 ELSE 0 END) as has_certificate
          FROM students s
          LEFT JOIN departments dept ON s.department_id = dept.id
          LEFT JOIN dues d ON s.id = d.student_id
          LEFT JOIN (
              SELECT cr1.* FROM clearance_requests cr1
              INNER JOIN (
                  SELECT student_id, MAX(created_at) as max_created 
                  FROM clearance_requests 
                  GROUP BY student_id
              ) cr2 ON cr1.student_id = cr2.student_id AND cr1.created_at = cr2.max_created
          ) cr ON s.id = cr.student_id
          LEFT JOIN (SELECT DISTINCT student_id FROM certificates) c ON s.id = c.student_id";

if ($department_filter !== null && $department_filter !== 'All' && $department_filter !== 'Library' && $department_filter !== '') {
    $query .= " WHERE dept.name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $department_filter);
} else {
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $total_due = floatval($row['total_due']);
    $db_status = $row['clearance_status'];
    $has_cert = intval($row['has_certificate']) === 1;
    
    // Custom clearance status business rules
    if ($has_cert) {
        $clearance_status = "Issued Certificate";
    } elseif (!empty($db_status)) {
        $clearance_status = $db_status;
    } elseif ($total_due > 0) {
        $clearance_status = "Has Dues ($" . number_format($total_due, 2) . ")";
    } else {
        $clearance_status = "Not Started";
    }

    $students[] = [
        'id' => $row['id'],
        'student_id' => $row['student_id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'department' => $row['department'],
        'cgpa' => floatval($row['cgpa']),
        'clearance_status' => $clearance_status,
        'clearance_id' => $row['clearance_id'] ? intval($row['clearance_id']) : null,
        'request_date' => $row['request_date']
    ];
}

// If super_admin, also fetch aggregate stats per department to build a beautiful dashboard overview
$dept_stats = [];
if ($role === 'super_admin') {
    $stats_query = "SELECT 
                        dept.name AS department,
                        COUNT(s.id) as total_students,
                        SUM(CASE WHEN cr.status = 'Pending' THEN 1 ELSE 0 END) as pending_clearances,
                        SUM(CASE WHEN cr.status = 'Approved' THEN 1 ELSE 0 END) as approved_clearances
                    FROM students s
                    LEFT JOIN departments dept ON s.department_id = dept.id
                    LEFT JOIN (
                        SELECT cr1.* FROM clearance_requests cr1
                        INNER JOIN (
                            SELECT student_id, MAX(created_at) as max_created 
                            FROM clearance_requests 
                            GROUP BY student_id
                        ) cr2 ON cr1.student_id = cr2.student_id AND cr1.created_at = cr2.max_created
                    ) cr ON s.id = cr.student_id
                    GROUP BY dept.name";
    $stats_res = $conn->query($stats_query);
    $total_students_all = 0;
    $pending_clearances_all = 0;
    $approved_clearances_all = 0;
    while ($row = $stats_res->fetch_assoc()) {
        if ($row['department'] === 'Library') {
            continue;
        }
        $dept_stats[] = $row;
        $total_students_all += intval($row['total_students']);
        $pending_clearances_all += intval($row['pending_clearances']);
        $approved_clearances_all += intval($row['approved_clearances']);
    }
    $dept_stats[] = [
        'department' => 'Library',
        'total_students' => $total_students_all,
        'pending_clearances' => $pending_clearances_all,
        'approved_clearances' => $approved_clearances_all
    ];
}

echo json_encode([
    'success' => true,
    'students' => $students,
    'department_stats' => $dept_stats
]);

$stmt->close();
$conn->close();
?>
