<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('admin');

if (!isset($_GET['student_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing student ID']));
}

$student_db_id = intval($_GET['student_id']);
$admin_dept = $_SESSION['department'];

// Verify student exists and belongs to the admin's department
$stmt = $conn->prepare("SELECT s.id, s.student_id, s.name, dept.name AS department,
                        d.library_due, d.hostel_due, d.tuition_due,
                        p.library_paid, p.hostel_paid, p.tuition_paid, p.total_paid
                        FROM students s
                        LEFT JOIN departments dept ON s.department_id = dept.id
                        LEFT JOIN dues d ON s.id = d.student_id
                        LEFT JOIN payments p ON s.id = p.student_id
                        WHERE s.id = ?");
$stmt->bind_param("i", $student_db_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Student not found']));
}

$row = $result->fetch_assoc();
if ($admin_dept !== 'Library' && strcasecmp($row['department'], $admin_dept) !== 0) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized: Student belongs to another department']));
}

echo json_encode([
    'success' => true,
    'student' => [
        'id' => $row['id'],
        'student_id' => $row['student_id'],
        'name' => $row['name'],
        'department' => $row['department'],
    ],
    'dues' => [
        'library' => [
            'due' => floatval($row['library_due'] ?? 0),
            'paid' => floatval($row['library_paid'] ?? 0)
        ],
        'hostel' => [
            'due' => floatval($row['hostel_due'] ?? 0),
            'paid' => floatval($row['hostel_paid'] ?? 0)
        ],
        'tuition' => [
            'due' => floatval($row['tuition_due'] ?? 0),
            'paid' => floatval($row['tuition_paid'] ?? 0)
        ],
        'total' => [
            'due' => floatval(($row['library_due'] ?? 0) + ($row['hostel_due'] ?? 0) + ($row['tuition_due'] ?? 0)),
            'paid' => floatval($row['total_paid'] ?? 0)
        ]
    ]
]);

$conn->close();
?>
