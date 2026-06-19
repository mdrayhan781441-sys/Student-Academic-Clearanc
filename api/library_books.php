<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$role = getCurrentRole();
$user_id = getCurrentUserId();

if ($method === 'GET') {
    // 1. SELECT query (Optionally filtered by search term)
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    
    if (!empty($search)) {
        $query = "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY title ASC";
        $searchTerm = "%$search%";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    } else {
        $query = "SELECT * FROM books ORDER BY title ASC";
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books
    ]);
    $stmt->close();

} elseif ($method === 'POST') {
    // 2. INSERT query - Admin (Library only) or SuperAdmin only
    if ($role !== 'super_admin' && ($role !== 'admin' || $_SESSION['department'] !== 'Library')) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Permission denied. Only Library admins or Super Admin can manage books.']));
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    $title = isset($data['title']) ? sanitize($data['title']) : '';
    $author = isset($data['author']) ? sanitize($data['author']) : '';
    $isbn = isset($data['isbn']) ? sanitize($data['isbn']) : '';
    $copies = isset($data['copies']) ? intval($data['copies']) : 1;
    
    if (empty($title) || empty($author) || empty($isbn) || $copies <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Invalid parameters. Title, Author, ISBN and Copies are required.']));
    }
    
    // Check if ISBN already exists
    $check_stmt = $conn->prepare("SELECT id FROM books WHERE isbn = ?");
    $check_stmt->bind_param("s", $isbn);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Book with this ISBN already exists']));
    }
    $check_stmt->close();
    
    $insert_stmt = $conn->prepare("INSERT INTO books (title, author, isbn, copies, available_copies) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("sssii", $title, $author, $isbn, $copies, $copies);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Book added to catalog successfully!',
            'book_id' => $insert_stmt->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add book']);
    }
    $insert_stmt->close();

} elseif ($method === 'DELETE') {
    // 3. DELETE query - Admin (Library only) or SuperAdmin only
    if ($role !== 'super_admin' && ($role !== 'admin' || $_SESSION['department'] !== 'Library')) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Permission denied. Only Library admins or Super Admin can manage books.']));
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $book_id = isset($data['id']) ? intval($data['id']) : 0;
    
    if ($book_id <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Invalid Book ID']));
    }
    
    // Check if book has active/unreturned loans
    $check_stmt = $conn->prepare("SELECT id FROM book_loans WHERE book_id = ? AND status = 'Active'");
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Cannot delete book. There are active borrows outstanding.']));
    }
    $check_stmt->close();
    
    $delete_stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
    $delete_stmt->bind_param("i", $book_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Book deleted successfully!'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete book']);
    }
    $delete_stmt->close();
}

$conn->close();
?>
