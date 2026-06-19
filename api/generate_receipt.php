<?php
require_once '../config/db.php';
require_once '../config/session.php';

if (!isLoggedIn()) {
    die("Unauthorized access.");
}

$role = $_SESSION['role'];
$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    die("Payment ID missing.");
}

$payment = null;

// Get payment details with role-based checks
if ($role === 'student') {
    // If student, they can only view their own payment
    $student_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT ph.*, s.name, s.student_id as sid, s.phone as phone_number, d.name AS department FROM payment_history ph JOIN students s ON ph.student_id = s.id LEFT JOIN departments d ON s.department_id = d.id WHERE ph.id = ? AND ph.student_id = ?");
    $stmt->bind_param("ii", $payment_id, $student_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
} else if ($role === 'admin') {
    // If department admin, they can view payments in their department
    $admin_dept = $_SESSION['department'];
    $stmt = $conn->prepare("SELECT ph.*, s.name, s.student_id as sid, s.phone as phone_number, d.name AS department FROM payment_history ph JOIN students s ON ph.student_id = s.id LEFT JOIN departments d ON s.department_id = d.id WHERE ph.id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if ($payment && strcasecmp($payment['department'], $admin_dept) !== 0) {
        die("Unauthorized: This student belongs to another department.");
    }
} else if ($role === 'super_admin') {
    // If super admin, they can view any payment
    $stmt = $conn->prepare("SELECT ph.*, s.name, s.student_id as sid, s.phone as phone_number, d.name AS department FROM payment_history ph JOIN students s ON ph.student_id = s.id LEFT JOIN departments d ON s.department_id = d.id WHERE ph.id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
} else {
    die("Unauthorized access.");
}

if (!$payment) {
    die("Payment record not found.");
}

// Calculate itemized payments for this transaction using history diff
$library_diff = 0;
$hostel_diff = 0;
$tuition_diff = 0;

if ($payment) {
    $payment_type = $payment['payment_type'] ?? 'all';
    $amount = floatval($payment['amount']);
    
    if ($payment_type === 'library') {
        $library_diff = $amount;
    } else if ($payment_type === 'hostel') {
        $hostel_diff = $amount;
    } else if ($payment_type === 'tuition') {
        $tuition_diff = $amount;
    } else {
        // For 'all' or other types, calculate differences using the previous history record
        $prev_stmt = $conn->prepare("SELECT library_paid, hostel_paid, tuition_paid FROM payment_history WHERE student_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
        $prev_stmt->bind_param("ii", $payment['student_id'], $payment['id']);
        $prev_stmt->execute();
        $prev_payment = $prev_stmt->get_result()->fetch_assoc();
        
        if ($prev_payment) {
            $library_diff = max(0, floatval($payment['library_paid']) - floatval($prev_payment['library_paid']));
            $hostel_diff = max(0, floatval($payment['hostel_paid']) - floatval($prev_payment['hostel_paid']));
            $tuition_diff = max(0, floatval($payment['tuition_paid']) - floatval($prev_payment['tuition_paid']));
        } else {
            $library_diff = floatval($payment['library_paid']);
            $hostel_diff = floatval($payment['hostel_paid']);
            $tuition_diff = floatval($payment['tuition_paid']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - <?php echo $payment['sid']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; background: #f0f2f5; }
        .receipt-card { background: white; max-width: 700px; margin: 0 auto; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .receipt-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 8px; background: linear-gradient(90deg, #4361ee, #4cc9f0); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #f0f2f5; padding-bottom: 20px; }
        .logo-area h1 { margin: 0; color: #1a1c23; font-size: 24px; }
        .logo-area p { margin: 5px 0 0; color: #64748b; font-size: 14px; }
        .receipt-info { text-align: right; }
        .receipt-info h2 { margin: 0; color: #4361ee; font-size: 20px; }
        .receipt-info p { margin: 5px 0 0; color: #64748b; font-size: 13px; }
        .student-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .detail-box h4 { margin: 0 0 5px; color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .detail-box p { margin: 0; color: #1e293b; font-weight: 700; font-size: 15px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .table th { text-align: left; padding: 12px; background: #f8fafc; color: #64748b; font-size: 13px; border-bottom: 1px solid #e2e8f0; }
        .table td { padding: 15px 12px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        .table .amount { text-align: right; font-weight: 700; }
        .total-section { margin-left: auto; width: 250px; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .total-row.grand-total { border-bottom: none; padding-top: 15px; margin-top: 5px; color: #4361ee; font-size: 18px; font-weight: 800; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #f0f2f5; color: #94a3b8; font-size: 12px; }
        .print-btn { background: #4361ee; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; display: block; margin: 30px auto 0; transition: 0.3s; }
        .print-btn:hover { background: #3f37c9; }
        @media print { .print-btn { display: none; } body { padding: 0; background: white; } .receipt-card { box-shadow: none; border: 1px solid #e2e8f0; } }
    </style>
</head>
<body>
    <div class="receipt-card">
        <div class="header">
            <div class="logo-area">
                <h1>Online Clearance Cloud</h1>
                <p>Digital Academic Clearance System</p>
            </div>
            <div class="receipt-info">
                <h2>PAYMENT RECEIPT</h2>
                <p>Receipt #: PAY-<?php echo str_pad($payment['id'], 5, '0', STR_PAD_LEFT); ?></p>
                <p>Date: <?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></p>
            </div>
        </div>

        <div class="student-details">
            <div class="detail-box">
                <h4>Student Name</h4>
                <p><?php echo $payment['name']; ?></p>
            </div>
            <div class="detail-box">
                <h4>Student ID</h4>
                <p><?php echo $payment['sid']; ?></p>
            </div>
            <div class="detail-box">
                <h4>Department</h4>
                <p><?php echo $payment['department']; ?></p>
            </div>
            <div class="detail-box">
                <h4>Payment Method</h4>
                <p>Digital Payment (via <?php echo $payment['phone_number']; ?>)</p>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if($library_diff > 0): ?>
                <tr>
                    <td>Library Dues Clearance</td>
                    <td class="amount">₹<?php echo number_format($library_diff, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if($hostel_diff > 0): ?>
                <tr>
                    <td>Hostel & Mess Dues</td>
                    <td class="amount">₹<?php echo number_format($hostel_diff, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if($tuition_diff > 0): ?>
                <tr>
                    <td>Tuition Fee Payment</td>
                    <td class="amount">₹<?php echo number_format($tuition_diff, 2); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-section">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Total Paid</span>
                <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
            </div>
        </div>

        <div class="footer">
            <p>This is a computer-generated receipt and does not require a physical signature.</p>
            <p>&copy; <?php echo date('Y'); ?> Online Clearance System. All rights reserved.</p>
        </div>

        <button class="print-btn" onclick="window.print()">Print Receipt / Save as PDF</button>
    </div>
</body>
</html>
