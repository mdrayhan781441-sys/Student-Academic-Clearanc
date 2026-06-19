<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

// Only logged in students can update their profile
requireRole('student');

$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');

    // 1. Email Check Constant (Format validation)
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['success' => false, 'message' => 'Invalid email format. Must contain @ and a domain.']));
    }

    // 2. Phone Check Constant (Bangladesh format: 11 digits)
    if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
        die(json_encode(['success' => false, 'message' => 'Invalid phone number. Must be 11 digits starting with 01.']));
    }

    // Update students table
    $stmt = $conn->prepare("UPDATE students SET email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $email, $phone, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $conn->error]);
    }
    
    $stmt->close();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
