<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('super_admin');

// Get all approved clearance requests with final decisions (history)
$stmt = $conn->prepare("SELECT cr.*, s.id as student_db_id, s.student_id, s.name, s.email, dept.name AS department, s.cgpa,
                        d.library_due, d.hostel_due, d.tuition_due,
                        p.library_paid, p.hostel_paid, p.tuition_paid, p.total_paid,
                        c.id as cert_id, c.issued_date,
                        (SELECT COUNT(*) FROM support_messages WHERE request_id = cr.id AND sender_role = 'student' AND recipient_role = 'super_admin' AND is_read = 0) AS unread_count
                        FROM clearance_requests cr
                        JOIN students s ON cr.student_id = s.id
                        LEFT JOIN departments dept ON s.department_id = dept.id
                        LEFT JOIN dues d ON s.id = d.student_id
                        LEFT JOIN payments p ON s.id = p.student_id
                        LEFT JOIN certificates c ON s.id = c.student_id
                        WHERE cr.super_admin_action IS NOT NULL
                        ORDER BY cr.super_admin_timestamp DESC");
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'id' => $row['id'],
        'student_db_id' => $row['student_db_id'],
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
        'status' => $row['status'],
        'super_admin_action' => $row['super_admin_action'],
        'super_admin_timestamp' => $row['super_admin_timestamp'],
        'admin_rejection_reason' => $row['admin_rejection_reason'],
        'unread_count' => intval($row['unread_count'] ?? 0),
        'certificate_issued' => !is_null($row['cert_id']),
        'issued_date' => $row['issued_date'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'success' => true,
    'history' => $history
]);

$conn->close();
?>
