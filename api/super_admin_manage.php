<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

// Force Super Admin authorization
if (!isLoggedIn() || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied: Super Admin authorization required']));
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
$action = isset($_GET['action']) ? sanitize($_GET['action']) : '';

if ($method === 'POST') {
    if ($action === 'add_student') {
        $student_id = isset($data['student_id']) ? sanitize($data['student_id']) : '';
        $name = isset($data['name']) ? sanitize($data['name']) : '';
        $email = isset($data['email']) ? sanitize($data['email']) : '';
        $phone = isset($data['phone']) ? sanitize($data['phone']) : '';
        $department = isset($data['department']) ? sanitize($data['department']) : '';
        $cgpa = isset($data['cgpa']) ? floatval($data['cgpa']) : 0.00;

        if (empty($student_id) || empty($name) || empty($department)) {
            die(json_encode(['success' => false, 'message' => 'Missing required fields: Student ID, Name, and Department are mandatory']));
        }

        // Check if student_id already exists
        $chk = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $chk->bind_param("s", $student_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            die(json_encode(['success' => false, 'message' => 'Registration failed: Student ID already exists']));
        }
        $chk->close();

        // Lookup department_id
        $dept_lookup = $conn->prepare("SELECT id FROM departments WHERE name = ?");
        $dept_lookup->bind_param("s", $department);
        $dept_lookup->execute();
        $dept_res = $dept_lookup->get_result();
        if ($dept_res->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Registration failed: Invalid department name']));
        }
        $department_id = $dept_res->fetch_assoc()['id'];
        $dept_lookup->close();

        // Start transaction
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO students (student_id, name, email, phone, department_id, cgpa) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssid", $student_id, $name, $email, $phone, $department_id, $cgpa);
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
            echo json_encode(['success' => true, 'message' => 'Student successfully added and initialized!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }

    } elseif ($action === 'remove_student') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        if ($id <= 0) {
            die(json_encode(['success' => false, 'message' => 'Invalid student ID']));
        }

        // Start transaction for cascading deletes
        $conn->begin_transaction();
        try {
            // 1. Delete dependent messages
            $msg = $conn->prepare("DELETE FROM support_messages WHERE request_id IN (SELECT id FROM clearance_requests WHERE student_id = ?)");
            $msg->bind_param("i", $id);
            $msg->execute();
            $msg->close();

            // 2. Delete certificates
            $cert = $conn->prepare("DELETE FROM certificates WHERE student_id = ?");
            $cert->bind_param("i", $id);
            $cert->execute();
            $cert->close();

            // 3. Delete clearance requests
            $req = $conn->prepare("DELETE FROM clearance_requests WHERE student_id = ?");
            $req->bind_param("i", $id);
            $req->execute();
            $req->close();

            // 4. Delete payments
            $pay = $conn->prepare("DELETE FROM payments WHERE student_id = ?");
            $pay->bind_param("i", $id);
            $pay->execute();
            $pay->close();

            // 5. Delete dues
            $due = $conn->prepare("DELETE FROM dues WHERE student_id = ?");
            $due->bind_param("i", $id);
            $due->execute();
            $due->close();

            // 6. Delete student
            $stu = $conn->prepare("DELETE FROM students WHERE id = ?");
            $stu->bind_param("i", $id);
            $stu->execute();
            $stu->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Student and all related data successfully deleted!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }

    } elseif ($action === 'add_admin') {
        $username = isset($data['username']) ? sanitize($data['username']) : '';
        $password = isset($data['password']) ? $data['password'] : '';
        $department = isset($data['department']) ? sanitize($data['department']) : '';
        $role = isset($data['role']) && $data['role'] === 'SuperAdmin' ? 'SuperAdmin' : 'Admin';

        if (empty($username) || empty($password) || empty($department)) {
            die(json_encode(['success' => false, 'message' => 'Missing required fields: Username, Password, and Department are mandatory']));
        }

        // Check if username already exists
        $chk = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $chk->bind_param("s", $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            die(json_encode(['success' => false, 'message' => 'Failed to create admin: Username is already registered']));
        }
        $chk->close();

        $department_id = null;
        if ($role !== 'SuperAdmin') {
            $dept_lookup = $conn->prepare("SELECT id FROM departments WHERE name = ?");
            $dept_lookup->bind_param("s", $department);
            $dept_lookup->execute();
            $dept_res = $dept_lookup->get_result();
            if ($dept_res->num_rows === 0) {
                die(json_encode(['success' => false, 'message' => 'Failed to create admin: Invalid department name']));
            }
            $department_id = $dept_res->fetch_assoc()['id'];
            $dept_lookup->close();
        }

        $stmt = $conn->prepare("INSERT INTO admins (username, password, department_id, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $username, $password, $department_id, $role);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Department administrator successfully created!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save admin record']);
        }
        $stmt->close();

    } elseif ($action === 'remove_admin') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        if ($id <= 0) {
            die(json_encode(['success' => false, 'message' => 'Invalid administrator ID']));
        }

        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ? AND role != 'SuperAdmin'");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Administrator successfully removed!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Administrator not found or protected.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove admin record']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported administrative action']);
    }
} elseif ($method === 'GET') {
    if ($action === 'get_admins') {
        $dept = isset($_GET['department']) ? sanitize($_GET['department']) : '';
        
        $query = "SELECT a.id, a.username, d.name AS department, a.role, a.created_at FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.role != 'SuperAdmin'";
        if (!empty($dept) && $dept !== 'All') {
            $query .= " AND d.name = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $dept);
        } else {
            $stmt = $conn->prepare($query);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        
        $admins = [];
        while ($row = $res->fetch_assoc()) {
            $admins[] = $row;
        }
        
        echo json_encode(['success' => true, 'admins' => $admins]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported query action']);
    }
}

$conn->close();
?>
