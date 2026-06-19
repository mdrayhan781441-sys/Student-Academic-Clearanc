<?php
ob_start();
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['role'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Role not specified']));
}

$role = sanitize($data['role']);

if ($role === 'student') {
    if (!isset($data['student_id']) || !isset($data['password'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Student ID and password are required']));
    }
    
    $student_id_input = sanitize($data['student_id']);
    $password = $data['password'];
    
    // Verify student exists by alphanumeric student_id column OR numeric primary key id
    $stmt = $conn->prepare("SELECT id, student_id, password FROM students WHERE student_id = ? OR id = ?");
    $stmt->bind_param("si", $student_id_input, $student_id_input);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Student not found']));
    }
    
    $student = $result->fetch_assoc();
    
    // Verify password
    if ($password !== $student['password']) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Invalid password']));
    }
    
    loginStudent($student['id']);
    echo json_encode(['success' => true, 'message' => 'Logged in as student', 'role' => 'student']);
} elseif ($role === 'admin' || $role === 'super_admin') {
    if (!isset($data['username']) || !isset($data['password'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Username or password not specified']));
    }
    
    $username = sanitize($data['username']);
    $password = $data['password'];
    
    $stmt = $conn->prepare("SELECT a.id, a.username, a.password, d.name AS department, a.role FROM admins a LEFT JOIN departments d ON a.department_id = d.id WHERE a.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Admin not found']));
    }
    
    $admin = $result->fetch_assoc();
    
    // In a real app, use password_verify($password, $admin['password'])
    // For now, we use plain comparison as requested/existing
    if ($password !== $admin['password']) {
        http_response_code(401);
        die(json_encode(['success' => false, 'message' => 'Invalid password']));
    }
    
    if ($admin['role'] === 'SuperAdmin') {
        $_SESSION['role'] = 'super_admin';
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['logged_in'] = true;
    } else {
        loginAdmin($admin['id'], $admin['department']);
        $_SESSION['username'] = $admin['username'];
    }
    
    echo json_encode(['success' => true, 'message' => 'Logged in successfully', 'role' => strtolower($admin['role'])]);
} else {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Invalid role']));
}

$conn->close();
?>
