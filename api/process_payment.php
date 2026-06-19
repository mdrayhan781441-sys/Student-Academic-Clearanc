<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('student');

$user_id = getCurrentUserId();
$data = json_decode(file_get_contents("php://input"), true);

// Validation
if (!isset($data['phone_number']) || !isset($data['payment_type']) || !isset($data['amount'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$phone_number = trim(sanitize($data['phone_number']));
$payment_type = sanitize($data['payment_type']); // 'library', 'hostel', 'tuition', 'all'
$amount = floatval($data['amount']);

// Get current student dues, payments, and department info
$stmt = $conn->prepare("SELECT s.id, s.phone, s.name, dept.name AS department, d.library_due, d.hostel_due, d.tuition_due, 
                        p.library_paid, p.hostel_paid, p.tuition_paid, p.total_paid
                        FROM students s
                        LEFT JOIN departments dept ON s.department_id = dept.id
                        LEFT JOIN dues d ON s.id = d.student_id
                        LEFT JOIN payments p ON s.id = p.student_id
                        WHERE s.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Student not found']));
}

$student_name = $student['name'];
$student_dept = $student['department'];

// Verify phone number matches the student's phone number in the database
if ($phone_number !== trim($student['phone'])) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'message' => 'Invalid phone number. Payment failed.',
        'status' => 'Due'
    ]));
}

// Calculate new payment amounts
$library_paid = floatval($student['library_paid']) ?? 0;
$hostel_paid = floatval($student['hostel_paid']) ?? 0;
$tuition_paid = floatval($student['tuition_paid']) ?? 0;

$library_due = floatval($student['library_due']) ?? 0;
$hostel_due = floatval($student['hostel_due']) ?? 0;
$tuition_due = floatval($student['tuition_due']) ?? 0;

// Process payment based on type
if ($payment_type === 'library') {
    if ($library_due <= 0) {
        die(json_encode(['success' => false, 'message' => 'No library dues to pay', 'status' => 'Paid']));
    }
    $library_paid = min($library_paid + $amount, $library_due);
} elseif ($payment_type === 'hostel') {
    if ($hostel_due <= 0) {
        die(json_encode(['success' => false, 'message' => 'No hostel dues to pay', 'status' => 'Paid']));
    }
    $hostel_paid = min($hostel_paid + $amount, $hostel_due);
} elseif ($payment_type === 'tuition') {
    if ($tuition_due <= 0) {
        die(json_encode(['success' => false, 'message' => 'No tuition dues to pay', 'status' => 'Paid']));
    }
    $tuition_paid = min($tuition_paid + $amount, $tuition_due);
} elseif ($payment_type === 'all') {
    $total_due = $library_due + $hostel_due + $tuition_due;
    if ($total_due <= 0) {
        die(json_encode(['success' => false, 'message' => 'No dues to pay', 'status' => 'Paid']));
    }
    
    $remaining = $amount;
    if ($library_due > 0 && $remaining > 0) {
        $pay = min($remaining, $library_due - $library_paid);
        $library_paid += $pay;
        $remaining -= $pay;
    }
    if ($hostel_due > 0 && $remaining > 0) {
        $pay = min($remaining, $hostel_due - $hostel_paid);
        $hostel_paid += $pay;
        $remaining -= $pay;
    }
    if ($tuition_due > 0 && $remaining > 0) {
        $pay = min($remaining, $tuition_due - $tuition_paid);
        $tuition_paid += $pay;
        $remaining -= $pay;
    }
}

$total_paid = $library_paid + $hostel_paid + $tuition_paid;

// Create payment_history table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS payment_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    payment_type VARCHAR(20),
    amount DECIMAL(10,2),
    library_paid DECIMAL(10,2),
    hostel_paid DECIMAL(10,2),
    tuition_paid DECIMAL(10,2),
    total_paid DECIMAL(10,2),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB";
$conn->query($createTableSQL);

// Use Transactions - Highly encouraged by project guidelines
$conn->begin_transaction();

try {
    // 1. Update main payment summary record
    $stmt = $conn->prepare("UPDATE payments SET library_paid = ?, hostel_paid = ?, tuition_paid = ?, total_paid = ? WHERE student_id = ?");
    $stmt->bind_param("ddddi", $library_paid, $hostel_paid, $tuition_paid, $total_paid, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update payment summary");
    }

    // 2. Insert into detailed payment history (Demonstrates multiple writes in one transaction)
    $stmt = $conn->prepare("INSERT INTO payment_history (student_id, payment_type, amount, library_paid, hostel_paid, tuition_paid, total_paid) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdddd", $user_id, $payment_type, $amount, $library_paid, $hostel_paid, $tuition_paid, $total_paid);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert payment history");
    }

    // If everything is fine, commit the transaction
    $conn->commit();

    $response = [
        'success' => true,
        'message' => 'Payment successful!',
        'status' => 'Paid',
        'dues' => [
            'library' => [
                'amount' => $library_due,
                'paid' => $library_paid,
                'status' => $library_paid >= $library_due ? 'Paid' : 'Due'
            ],
            'hostel' => [
                'amount' => $hostel_due,
                'paid' => $hostel_paid,
                'status' => $hostel_paid >= $hostel_due ? 'Paid' : 'Due'
            ],
            'tuition' => [
                'amount' => $tuition_due,
                'paid' => $tuition_paid,
                'status' => $tuition_paid >= $tuition_due ? 'Paid' : 'Due'
            ]
        ],
        'summary' => [
            'total_due' => $library_due + $hostel_due + $tuition_due,
            'total_paid' => $total_paid,
            'remaining_balance' => ($library_due + $hostel_due + $tuition_due) - $total_paid
        ]
    ];

    // AUTOMATIC CLEARANCE REQUEST: If all dues are cleared, submit request automatically
    $remaining_total = ($library_due + $hostel_due + $tuition_due) - $total_paid;
    if ($remaining_total <= 0) {
        // Check if a request already exists
        $checkReq = $conn->prepare("SELECT id FROM clearance_requests WHERE student_id = ? AND status = 'Pending'");
        $checkReq->bind_param("i", $user_id);
        $checkReq->execute();
        if ($checkReq->get_result()->num_rows === 0) {
            $autoMsg = "Automatic request submitted after full payment.";
            $insReq = $conn->prepare("INSERT INTO clearance_requests (student_id, message, status) VALUES (?, ?, 'Pending')");
            $insReq->bind_param("is", $user_id, $autoMsg);
            $insReq->execute();
            
            // NOTIFY DEPARTMENT ADMIN
            $admin_stmt = $conn->prepare("SELECT a.id FROM admins a JOIN departments d ON a.department_id = d.id WHERE d.name = ?");
            $admin_stmt->bind_param("s", $student_dept);
            $admin_stmt->execute();
            $admin_res = $admin_stmt->get_result();
            while ($admin = $admin_res->fetch_assoc()) {
                $notif_title = "New Clearance Request: " . $student_name;
                $notif_msg = "Student " . $student_name . " (ID: " . $user_id . ") from your department has cleared all dues and submitted a clearance request.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message) VALUES (?, 'admin', ?, ?)");
                $notif_stmt->bind_param("iss", $admin['id'], $notif_title, $notif_msg);
                $notif_stmt->execute();
            }

            $response['message'] .= " Clearance request submitted automatically!";
        }
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    // If any error occurs, rollback all changes
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment failed: ' . $e->getMessage()]);
}

$conn->close();
?>
