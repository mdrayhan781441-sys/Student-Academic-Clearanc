<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Submit clearance request
    requireRole('student');
    
    $user_id = getCurrentUserId();
    // Check if it's a FormData (multipart/form-data) or JSON
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
        $message = isset($data['message']) ? sanitize($data['message']) : '';
    } else {
        $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    }
    
    // Process document upload if present
    $document_path = null;
    if (isset($_FILES['appealDocument']) && $_FILES['appealDocument']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        // Basic security check for file extension
        $fileExt = strtolower(pathinfo($_FILES['appealDocument']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'png', 'jpg', 'jpeg'];
        
        if (in_array($fileExt, $allowedExts)) {
            $fileName = time() . '_' . $user_id . '.' . $fileExt;
            $targetFilePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['appealDocument']['tmp_name'], $targetFilePath)) {
                $document_path = 'uploads/documents/' . $fileName;
            }
        }
    }
    
    // 3. Due Check & Paid Check Constants
    // Verify student has no outstanding balances before allowing request
    $due_check = $conn->prepare("SELECT 
        (COALESCE(d.library_due, 0) + COALESCE(d.hostel_due, 0) + COALESCE(d.tuition_due, 0)) as total_due,
        COALESCE(p.total_paid, 0) as total_paid
        FROM students s
        LEFT JOIN dues d ON s.id = d.student_id
        LEFT JOIN payments p ON s.id = p.student_id
        WHERE s.id = ?");
    $due_check->bind_param("i", $user_id);
    $due_check->execute();
    $fin_result = $due_check->get_result()->fetch_assoc();
    
    $remaining = floatval($fin_result['total_due']) - floatval($fin_result['total_paid']);
    
    if ($remaining > 0) {
        die(json_encode([
            'success' => false,
            'message' => 'Due Check Failed: You have an outstanding balance of $' . number_format($remaining, 2) . '. Please clear all dues before requesting clearance.'
        ]));
    }

    // Check if already has a pending request
    $stmt = $conn->prepare("SELECT id FROM clearance_requests WHERE student_id = ? AND status = 'Pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        $req_id = $existing['id'];
        
        $stmt_update = $conn->prepare("UPDATE clearance_requests SET message = ?, document_path = COALESCE(?, document_path) WHERE id = ?");
        $stmt_update->bind_param("ssi", $message, $document_path, $req_id);
        if ($stmt_update->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Clearance request updated successfully!',
                'request_id' => $req_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update clearance request'
            ]);
        }
        $stmt_update->close();
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Create new clearance request
    $stmt = $conn->prepare("INSERT INTO clearance_requests (student_id, message, status, document_path) VALUES (?, ?, 'Pending', ?)");
    $stmt->bind_param("iss", $user_id, $message, $document_path);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Clearance request submitted successfully!',
            'request_id' => $stmt->insert_id
        ]);
    } else {
        http_response_code(500);
        die(json_encode([
            'success' => false,
            'message' => 'Failed to submit clearance request'
        ]));
    }
    
} elseif ($method === 'GET') {
    // Get clearance request status
    requireRole('student');
    
    $user_id = getCurrentUserId();
    
    $stmt = $conn->prepare("SELECT * FROM clearance_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Auto-create a pending clearance request placeholder so chats are unlocked by default
        $stmt_insert = $conn->prepare("INSERT INTO clearance_requests (student_id, message, status, document_path) VALUES (?, '', 'Pending', NULL)");
        $stmt_insert->bind_param("i", $user_id);
        $stmt_insert->execute();
        $stmt_insert->close();
        
        // Re-execute SELECT query
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        $req_id = $request['id'];
        
        // Find Library department ID
        $lib_stmt = $conn->prepare("SELECT id FROM departments WHERE name = 'Library' LIMIT 1");
        $lib_stmt->execute();
        $lib_res = $lib_stmt->get_result();
        $lib_dept_id = $lib_res->num_rows > 0 ? $lib_res->fetch_assoc()['id'] : 0;
        $lib_stmt->close();
        
        // Dept admin unread
        $dept_stmt = $conn->prepare("SELECT COUNT(*) FROM support_messages WHERE request_id = ? AND sender_role = 'admin' AND is_read = 0 AND sender_id IN (SELECT id FROM admins WHERE department_id IS NULL OR department_id != ?)");
        $dept_stmt->bind_param("ii", $req_id, $lib_dept_id);
        $dept_stmt->execute();
        $dept_stmt->bind_result($dept_unread);
        $dept_stmt->fetch();
        $dept_stmt->close();
        
        // Library admin unread
        $lib_unread_stmt = $conn->prepare("SELECT COUNT(*) FROM support_messages WHERE request_id = ? AND sender_role = 'admin' AND is_read = 0 AND sender_id IN (SELECT id FROM admins WHERE department_id = ?)");
        $lib_unread_stmt->bind_param("ii", $req_id, $lib_dept_id);
        $lib_unread_stmt->execute();
        $lib_unread_stmt->bind_result($lib_unread);
        $lib_unread_stmt->fetch();
        $lib_unread_stmt->close();
        
        // Super admin unread
        $super_unread_stmt = $conn->prepare("SELECT COUNT(*) FROM support_messages WHERE request_id = ? AND sender_role = 'super_admin' AND is_read = 0");
        $super_unread_stmt->bind_param("i", $req_id);
        $super_unread_stmt->execute();
        $super_unread_stmt->bind_result($super_unread);
        $super_unread_stmt->fetch();
        $super_unread_stmt->close();
        
        $request['unread_counts'] = [
            'admin' => intval($dept_unread),
            'library' => intval($lib_unread),
            'super_admin' => intval($super_unread)
        ];
        
        echo json_encode([
            'success' => true,
            'request' => $request
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'request' => null
        ]);
    }
}

$conn->close();
?>
