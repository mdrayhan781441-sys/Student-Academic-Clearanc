<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('student');

$user_id = getCurrentUserId();

// Get student details
$stmt = $conn->prepare("SELECT s.*, dept.name AS department, d.library_due, d.hostel_due, d.tuition_due, 
                        p.library_paid, p.hostel_paid, p.tuition_paid, p.total_paid
                        FROM students s
                        LEFT JOIN departments dept ON s.department_id = dept.id
                        LEFT JOIN dues d ON s.id = d.student_id
                        LEFT JOIN payments p ON s.id = p.student_id
                        WHERE s.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Student not found']));
}

$student = $result->fetch_assoc();

// Calculate dynamic accruing library fine for outstanding overdue loans
$accruing_library_fine = 0.00;
$now = new DateTime();
$loans_stmt = $conn->prepare("SELECT due_date FROM book_loans WHERE student_id = ? AND status != 'Returned'");
$loans_stmt->bind_param("i", $user_id);
$loans_stmt->execute();
$loans_res = $loans_stmt->get_result();
while ($loan_row = $loans_res->fetch_assoc()) {
    $due = new DateTime($loan_row['due_date']);
    if ($now > $due) {
        $diff_seconds = $now->getTimestamp() - $due->getTimestamp();
        if ($diff_seconds > 0) {
            $days = ceil($diff_seconds / 86400);
            $accruing_library_fine += $days * 10.00;
        }
    }
}
$loans_stmt->close();

// Add dynamic fine to library due
$student['library_due'] = floatval($student['library_due'] ?? 0) + $accruing_library_fine;

// Calculate totals
$total_due = floatval($student['library_due']) + floatval($student['hostel_due']) + floatval($student['tuition_due']);
$total_paid = floatval($student['total_paid']) ?? 0;
$remaining_balance = $total_due - $total_paid;
$progress_percentage = $total_due > 0 ? round(($total_paid / $total_due) * 100, 2) : 0;

// Get latest clearance request
$stmt = $conn->prepare("SELECT * FROM clearance_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$clearance_request = $result->num_rows > 0 ? $result->fetch_assoc() : null;

// Check if certificate exists
$stmt = $conn->prepare("SELECT * FROM certificates WHERE student_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$certificate = $result->num_rows > 0 ? $result->fetch_assoc() : null;

// Get latest notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND user_role = 'student' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_result = $stmt->get_result();
$notifications = [];
while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get latest payments
$stmt = $conn->prepare("SELECT * FROM payment_history WHERE student_id = ? ORDER BY payment_date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pay_result = $stmt->get_result();
$payments = [];
while ($row = $pay_result->fetch_assoc()) {
    $payments[] = $row;
}

$response = [
    'success' => true,
    'student' => [
        'id' => $student['student_id'] ?? 'N/A',
        'name' => $student['name'] ?? 'N/A',
        'email' => $student['email'] ?? '',
        'phone' => $student['phone'] ?? '',
        'department' => $student['department'] ?? 'N/A',
        'cgpa' => $student['cgpa'] ?? '0.00',
        'qr_token' => $student['qr_token'] ?? ''
    ],
    'dues' => [
        'library' => [
            'amount' => floatval($student['library_due'] ?? 0),
            'paid' => floatval($student['library_paid'] ?? 0),
            'status' => (floatval($student['library_due'] ?? 0) > 0 && floatval($student['library_paid'] ?? 0) >= floatval($student['library_due'] ?? 0)) ? 'Paid' : 'Unpaid'
        ],
        'hostel' => [
            'amount' => floatval($student['hostel_due'] ?? 0),
            'paid' => floatval($student['hostel_paid'] ?? 0),
            'status' => (floatval($student['hostel_due'] ?? 0) > 0 && floatval($student['hostel_paid'] ?? 0) >= floatval($student['hostel_due'] ?? 0)) ? 'Paid' : 'Unpaid'
        ],
        'tuition' => [
            'amount' => floatval($student['tuition_due'] ?? 0),
            'paid' => floatval($student['tuition_paid'] ?? 0),
            'status' => (floatval($student['tuition_due'] ?? 0) > 0 && floatval($student['tuition_paid'] ?? 0) >= floatval($student['tuition_due'] ?? 0)) ? 'Paid' : 'Unpaid'
        ]
    ],
    'summary' => [
        'total_due' => $total_due,
        'total_paid' => $total_paid,
        'remaining_balance' => $remaining_balance,
        'progress_percentage' => $progress_percentage
    ],
    'clearance' => $clearance_request,
    'certificate' => $certificate ? [
        'issued_date' => $certificate['issued_date'],
        'exists' => true
    ] : ['exists' => false],
    'notifications' => $notifications,
    'payments' => $payments
];

echo json_encode($response);
$conn->close();
?>
