<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

requireRole('admin');

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

$admin_dept = $_SESSION['department'];

if ($method === 'POST') {
    if ($action === 'add_student') {
        $student_id = isset($data['student_id']) ? sanitize($data['student_id']) : '';
        $name = isset($data['name']) ? sanitize($data['name']) : '';
        $email = isset($data['email']) ? sanitize($data['email']) : '';
        $phone = isset($data['phone']) ? sanitize($data['phone']) : '';
        $cgpa = isset($data['cgpa']) ? floatval($data['cgpa']) : 0.00;

        if (empty($student_id) || empty($name)) {
            die(json_encode(['success' => false, 'message' => 'Missing required fields: Student ID and Name are mandatory']));
        }

        // Check if student_id already exists
        $chk = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $chk->bind_param("s", $student_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            die(json_encode(['success' => false, 'message' => 'Registration failed: Student ID already exists']));
        }
        $chk->close();

        // Lookup department_id for $admin_dept
        $dept_lookup = $conn->prepare("SELECT id FROM departments WHERE name = ?");
        $dept_lookup->bind_param("s", $admin_dept);
        $dept_lookup->execute();
        $dept_res = $dept_lookup->get_result();
        if ($dept_res->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Registration failed: Invalid department']));
        }
        $admin_dept_id = $dept_res->fetch_assoc()['id'];
        $dept_lookup->close();

        // Start transaction
        $conn->begin_transaction();
        try {
            // Force locked to $admin_dept
            $stmt = $conn->prepare("INSERT INTO students (student_id, name, email, phone, department_id, cgpa) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssid", $student_id, $name, $email, $phone, $admin_dept_id, $cgpa);
            $stmt->execute();
            $new_student_id = $stmt->insert_id;
            $stmt->close();

            // Initialize dues table row
            $dues = $conn->prepare("INSERT INTO dues (student_id, library_due, hostel_due, tuition_due) VALUES (?, 0, 0, 0)");
            $dues->bind_param("i", $new_student_id);
            $dues->execute();
            $dues->close();

            // Initialize payments table row
            $pay = $conn->prepare("INSERT INTO payments (student_id, library_paid, hostel_paid, tuition_paid, total_paid) VALUES (?, 0, 0, 0, 0)");
            $pay->bind_param("i", $new_student_id);
            $pay->execute();
            $pay->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Student successfully added to ' . $admin_dept . '!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }

    } elseif ($action === 'remove_student') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        if ($id <= 0) {
            die(json_encode(['success' => false, 'message' => 'Invalid student ID']));
        }

        // 1. Verify student department
        $chk = $conn->prepare("SELECT d.name AS department FROM students s LEFT JOIN departments d ON s.department_id = d.id WHERE s.id = ?");
        $chk->bind_param("i", $id);
        $chk->execute();
        $res = $chk->get_result();
        
        if ($res->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Student not found']));
        }
        
        $student = $res->fetch_assoc();
        if (strcasecmp($student['department'], $admin_dept) !== 0) {
            die(json_encode(['success' => false, 'message' => 'Access denied: You can only remove students from your assigned department (' . $admin_dept . ')']));
        }
        $chk->close();

        // Start transaction for cascading deletes
        $conn->begin_transaction();
        try {
            // Delete dependent messages
            $msg = $conn->prepare("DELETE FROM support_messages WHERE request_id IN (SELECT id FROM clearance_requests WHERE student_id = ?)");
            $msg->bind_param("i", $id);
            $msg->execute();
            $msg->close();

            // Delete certificates
            $cert = $conn->prepare("DELETE FROM certificates WHERE student_id = ?");
            $cert->bind_param("i", $id);
            $cert->execute();
            $cert->close();

            // Delete clearance requests
            $req = $conn->prepare("DELETE FROM clearance_requests WHERE student_id = ?");
            $req->bind_param("i", $id);
            $req->execute();
            $req->close();

            // Delete payments
            $pay = $conn->prepare("DELETE FROM payments WHERE student_id = ?");
            $pay->bind_param("i", $id);
            $pay->execute();
            $pay->close();

            // Delete dues
            $due = $conn->prepare("DELETE FROM dues WHERE student_id = ?");
            $due->bind_param("i", $id);
            $due->execute();
            $due->close();

            // Delete student
            $stu = $conn->prepare("DELETE FROM students WHERE id = ?");
            $stu->bind_param("i", $id);
            $stu->execute();
            $stu->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Student and all related records successfully deleted!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported administrative action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unsupported request method']);
}

$conn->close();
?>
