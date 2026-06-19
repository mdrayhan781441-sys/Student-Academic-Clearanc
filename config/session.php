<?php
session_start();

// Function to login as Student
function loginStudent($student_id) {
    $_SESSION['role'] = 'student';
    $_SESSION['user_id'] = $student_id;
    $_SESSION['logged_in'] = true;
    return true;
}

// Function to login as Admin
function loginAdmin($user_id, $department) {
    $_SESSION['role'] = 'admin';
    $_SESSION['user_id'] = $user_id;
    $_SESSION['department'] = $department;
    $_SESSION['logged_in'] = true;
    return true;
}

// Function to login as Super Admin
function loginSuperAdmin() {
    $_SESSION['role'] = 'super_admin';
    $_SESSION['user_id'] = 'super_admin';
    $_SESSION['logged_in'] = true;
    return true;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Function to get current role
function getCurrentRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Function to get current user ID (student_id or admin/super_admin)
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Function to logout
function logout() {
    session_destroy();
    return true;
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        // Dynamically determine project root folder path relative to server root
        $project_dir = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));
        $redirect_path = '/' . trim($project_dir, '/') . '/';
        header('Location: ' . $redirect_path);
        exit;
    }
}

// Function to require specific role
function requireRole($role) {
    requireLogin();
    if (getCurrentRole() !== $role) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
    }
}
?>
