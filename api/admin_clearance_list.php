<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('admin');

$admin_dept = $_SESSION['department'];
if ($admin_dept === 'Library') {
    $stmt = $conn->prepare("SELECT cr.*, s.student_id, s.name, s.email, dept.name AS department, s.cgpa,
                            d.library_due, d.hostel_due, d.tuition_due,
                            p.library_paid, p.hostel_paid, p.tuition_paid, p.total_paid,
                            (SELECT COUNT(*) FROM support_messages WHERE request_id = cr.id AND sender_role = 'student' AND recipient_role = 'library' AND is_read = 0) AS unread_count
                            FROM clearance_requests cr
                            JOIN students s ON cr.student_id = s.id
                            LEFT JOIN departments dept ON s.department_id = dept.id
                            LEFT JOIN dues d ON s.id = d.student_id
                            LEFT JOIN payments p ON s.id = p.student_id
                            WHERE cr.status = 'Pending'
                            ORDER BY cr.created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT cr.*, s.student_id, s.name, s.email, dept.name AS department, s.cgpa,
                            d.library_due, d.hostel_due, d.tuition_due,
                            p.library_paid, p.hostel_paid, p.tuition_paid, p.total_paid,
                            (SELECT COUNT(*) FROM support_messages WHERE request_id = cr.id AND sender_role = 'student' AND (recipient_role = 'admin' OR recipient_role IS NULL) AND is_read = 0) AS unread_count
                            FROM clearance_requests cr
                            JOIN students s ON cr.student_id = s.id
                            LEFT JOIN departments dept ON s.department_id = dept.id
                            LEFT JOIN dues d ON s.id = d.student_id
                            LEFT JOIN payments p ON s.id = p.student_id
                            WHERE cr.status = 'Pending' AND dept.name = ?
                            ORDER BY cr.created_at DESC");
    $stmt->bind_param("s", $admin_dept);
}
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = [
        'id' => $row['id'],
        'student' => [
            'id' => $row['student_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'department' => $row['department'],
            'cgpa' => $row['cgpa']
        ],
        'payment' => [
            'library' => [
                'due' => floatval($row['library_due']),
                'paid' => floatval($row['library_paid']) ?? 0
            ],
            'hostel' => [
                'due' => floatval($row['hostel_due']),
                'paid' => floatval($row['hostel_paid']) ?? 0
            ],
            'tuition' => [
                'due' => floatval($row['tuition_due']),
                'paid' => floatval($row['tuition_paid']) ?? 0
            ],
            'total_due' => floatval($row['library_due']) + floatval($row['hostel_due']) + floatval($row['tuition_due']),
            'total_paid' => floatval($row['total_paid']) ?? 0
        ],
        'message' => $row['message'],
        'document_path' => $row['document_path'],
        'status' => $row['status'],
        'unread_count' => intval($row['unread_count'] ?? 0),
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'success' => true,
    'requests' => $requests
]);

$conn->close();
?>
