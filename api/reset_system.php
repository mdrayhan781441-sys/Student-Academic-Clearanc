<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

// Only super admin can reset
if ($_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized. Only Super Admin can reset system.']));
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Delete all payment history
    $conn->query("TRUNCATE TABLE payment_history");

    // Reset all payments to zero
    $conn->query("UPDATE payments SET library_paid = 0, hostel_paid = 0, tuition_paid = 0, total_paid = 0, payment_date = CURRENT_TIMESTAMP");

    // Delete all clearance requests
    $conn->query("TRUNCATE TABLE clearance_requests");

    // Delete all certificates
    $conn->query("TRUNCATE TABLE certificates");

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'System reset successful! All payments, clearances, and certificates have been cleared.',
        'reset_items' => [
            'payments' => 'All student payments reset to zero',
            'payment_history' => 'All payment history cleared',
            'clearance_requests' => 'All clearance requests deleted',
            'certificates' => 'All generated certificates deleted'
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()]);
}

$conn->close();
?>
