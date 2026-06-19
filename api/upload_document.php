<?php
require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$document_type = $_POST['document_type'] ?? 'Other';

if (!isset($_FILES['document'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['document'];
$filename = time() . '_' . basename($file['name']);
$target_dir = "../uploads/documents/";
$target_file = $target_dir . $filename;

// Check if image or pdf
$fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
$allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, & PDF files are allowed']);
    exit;
}

if (move_uploaded_file($file['tmp_name'], $target_file)) {
    $db_path = "uploads/documents/" . $filename;
    
    $stmt = $conn->prepare("INSERT INTO student_documents (student_id, document_type, file_path) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $student_id, $document_type, $db_path);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}
