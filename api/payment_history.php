<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('student');

$user_id = getCurrentUserId();

// First, ensure payment_history table exists
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

// Get payment history
$stmt = $conn->prepare("SELECT * FROM payment_history WHERE student_id = ? ORDER BY payment_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    // Determine criteria based on payment type and amounts
    $criteria = [];
    if ($row['payment_type'] === 'all') {
        $criteria = ['Library Clearance', 'Hostel Clearance', 'Tuition Fee'];
    } elseif ($row['payment_type'] === 'library') {
        $criteria = ['Library Clearance'];
    } elseif ($row['payment_type'] === 'hostel') {
        $criteria = ['Hostel Clearance'];
    } elseif ($row['payment_type'] === 'tuition') {
        $criteria = ['Tuition Fee'];
    } else {
        // Check individual amounts to determine criteria
        if ($row['library_paid'] > 0) $criteria[] = 'Library Clearance';
        if ($row['hostel_paid'] > 0) $criteria[] = 'Hostel Clearance';
        if ($row['tuition_paid'] > 0) $criteria[] = 'Tuition Fee';
    }

    $payments[] = [
        'id' => $row['id'],
        'payment_type' => $row['payment_type'],
        'criteria' => implode(', ', $criteria),
        'amount' => floatval($row['amount']),
        'library_paid' => floatval($row['library_paid']),
        'hostel_paid' => floatval($row['hostel_paid']),
        'tuition_paid' => floatval($row['tuition_paid']),
        'total_paid' => floatval($row['total_paid']),
        'payment_date' => $row['payment_date']
    ];
}

// Get current payment status
$stmt = $conn->prepare("SELECT library_paid, hostel_paid, tuition_paid, total_paid, payment_date 
                        FROM payments WHERE student_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_payment = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'payments' => $payments,
    'current_payment' => $current_payment
]);
?>