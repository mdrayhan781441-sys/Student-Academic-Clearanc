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
$user_id = getCurrentUserId(); // Integer ID of the active student/admin

if ($method === 'GET') {
    // 1. Fetch loans (JOIN query)
    if ($role === 'student') {
        // Fetch loans for the logged-in student
        $query = "SELECT l.id, l.borrow_date, l.due_date, l.return_date, l.fine_amount, l.status, l.duration_days, b.title, b.author, b.isbn 
                  FROM book_loans l 
                  JOIN books b ON l.book_id = b.id 
                  WHERE l.student_id = ? 
                  ORDER BY l.borrow_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    } else {
        // Only Library Admin or Super Admin can view all loans
        if ($role !== 'super_admin' && $_SESSION['department'] !== 'Library') {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Permission denied']));
        }
        
        $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
        if ($filter === 'pending') {
            // Fetch only pending requests for borrow, renew, and return
            $query = "SELECT l.id, l.borrow_date, l.due_date, l.return_date, l.fine_amount, l.status, l.duration_days, 
                             b.title, b.isbn, s.name as student_name, s.student_id as student_uid 
                      FROM book_loans l 
                      JOIN books b ON l.book_id = b.id 
                      JOIN students s ON l.student_id = s.id 
                      WHERE l.status IN ('Pending Borrow', 'Pending Renewal', 'Pending Return')
                      ORDER BY l.borrow_date DESC";
            $stmt = $conn->prepare($query);
        } else {
            // Fetch all loans for Library Admin / Super Admin
            $query = "SELECT l.id, l.borrow_date, l.due_date, l.return_date, l.fine_amount, l.status, l.duration_days, 
                             b.title, b.isbn, s.name as student_name, s.student_id as student_uid 
                      FROM book_loans l 
                      JOIN books b ON l.book_id = b.id 
                      JOIN students s ON l.student_id = s.id 
                      ORDER BY l.status ASC, l.borrow_date DESC";
            $stmt = $conn->prepare($query);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    $now = new DateTime();
    while ($row = $result->fetch_assoc()) {
        // Calculate dynamic overdue fine in real-time if due date is passed and not returned yet
        if ($row['status'] !== 'Returned') {
            $due = new DateTime($row['due_date']);
            if ($now > $due) {
                $diff_seconds = $now->getTimestamp() - $due->getTimestamp();
                if ($diff_seconds > 0) {
                    $days = ceil($diff_seconds / 86400);
                    $row['fine_amount'] = $days * 10.00;
                    if ($row['status'] === 'Active') {
                        $row['status'] = 'Overdue';
                    }
                }
            }
        }
        $loans[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'loans' => $loans
    ]);
    $stmt->close();

} elseif ($method === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    // --- ACTION: RENEW REQUEST (Student only) ---
    if ($action === 'renew') {
        if ($role !== 'student') {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Only students can request renewals']));
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        $loan_id = isset($data['loan_id']) ? intval($data['loan_id']) : 0;
        
        if ($loan_id <= 0) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid loan ID']));
        }
        
        $conn->begin_transaction();
        try {
            $loan_stmt = $conn->prepare("SELECT student_id, status FROM book_loans WHERE id = ? FOR UPDATE");
            $loan_stmt->bind_param("i", $loan_id);
            $loan_stmt->execute();
            $loan_res = $loan_stmt->get_result();
            
            if ($loan_res->num_rows === 0) {
                throw new Exception("Loan record not found");
            }
            
            $loan = $loan_res->fetch_assoc();
            if (intval($loan['student_id']) !== intval($user_id)) {
                throw new Exception("You are not authorized to renew this loan");
            }
            if ($loan['status'] !== 'Active' && $loan['status'] !== 'Overdue') {
                throw new Exception("Only active or overdue book loans can be renewed");
            }
            $loan_stmt->close();
            
            $renew_stmt = $conn->prepare("UPDATE book_loans SET status = 'Pending Renewal' WHERE id = ?");
            $renew_stmt->bind_param("i", $loan_id);
            $renew_stmt->execute();
            $renew_stmt->close();
            
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Renewal request submitted successfully! Pending Library Admin approval.'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- ACTION: RETURN REQUEST (Student only) ---
    if ($action === 'request_return') {
        if ($role !== 'student') {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Only students can request return approvals']));
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        $loan_id = isset($data['loan_id']) ? intval($data['loan_id']) : 0;
        
        if ($loan_id <= 0) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid loan ID']));
        }
        
        $conn->begin_transaction();
        try {
            $loan_stmt = $conn->prepare("SELECT student_id, status FROM book_loans WHERE id = ? FOR UPDATE");
            $loan_stmt->bind_param("i", $loan_id);
            $loan_stmt->execute();
            $loan_res = $loan_stmt->get_result();
            
            if ($loan_res->num_rows === 0) {
                throw new Exception("Loan record not found");
            }
            
            $loan = $loan_res->fetch_assoc();
            if (intval($loan['student_id']) !== intval($user_id)) {
                throw new Exception("You are not authorized to return this book");
            }
            if ($loan['status'] !== 'Active' && $loan['status'] !== 'Overdue') {
                throw new Exception("Only active or overdue loans can be returned");
            }
            $loan_stmt->close();
            
            $return_stmt = $conn->prepare("UPDATE book_loans SET status = 'Pending Return' WHERE id = ?");
            $return_stmt->bind_param("i", $loan_id);
            $return_stmt->execute();
            $return_stmt->close();
            
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Return request submitted successfully! Pending Library Admin approval.'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- ACTION: APPROVE REQUEST (Library Admin/Super Admin) ---
    if ($action === 'approve') {
        if ($role !== 'super_admin' && ($role !== 'admin' || $_SESSION['department'] !== 'Library')) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Permission denied']));
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        $loan_id = isset($data['loan_id']) ? intval($data['loan_id']) : 0;
        
        if ($loan_id <= 0) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid loan ID']));
        }
        
        $conn->begin_transaction();
        try {
            $loan_stmt = $conn->prepare("SELECT l.*, b.available_copies, b.title FROM book_loans l JOIN books b ON l.book_id = b.id WHERE l.id = ? FOR UPDATE");
            $loan_stmt->bind_param("i", $loan_id);
            $loan_stmt->execute();
            $loan_res = $loan_stmt->get_result();
            if ($loan_res->num_rows === 0) {
                throw new Exception("Loan record not found");
            }
            $loan = $loan_res->fetch_assoc();
            $loan_stmt->close();
            
            $status = $loan['status'];
            $student_id = $loan['student_id'];
            $book_id = $loan['book_id'];
            $book_title = $loan['title'];
            
            if ($status === 'Pending Borrow') {
                if ($loan['available_copies'] <= 0) {
                    throw new Exception("This book is currently out of stock");
                }
                // Decrement copies
                $update_book = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?");
                $update_book->bind_param("i", $book_id);
                $update_book->execute();
                $update_book->close();
                
                // Update loan to Active using approved duration_days
                $duration = isset($data['duration_days']) ? intval($data['duration_days']) : intval($loan['duration_days']);
                $has_due_date = $loan['due_date'] && $loan['due_date'] !== '0000-00-00 00:00:00';
                if ($duration <= 0 && !$has_due_date) {
                    $duration = 14;
                }
                
                $use_custom_due = false;
                $admin_overrode_duration = isset($data['duration_days']) && intval($data['duration_days']) !== intval($loan['duration_days']);
                if (!$admin_overrode_duration && $has_due_date) {
                    $use_custom_due = true;
                }
                
                if ($use_custom_due) {
                    $update_loan = $conn->prepare("UPDATE book_loans SET status = 'Active', borrow_date = NOW(), duration_days = ? WHERE id = ?");
                    $update_loan->bind_param("ii", $duration, $loan_id);
                } else {
                    $update_loan = $conn->prepare("UPDATE book_loans SET status = 'Active', borrow_date = NOW(), duration_days = ?, due_date = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
                    $update_loan->bind_param("iii", $duration, $duration, $loan_id);
                }
                $update_loan->execute();
                $update_loan->close();
                
                // Notify student
                $due_date_str = $use_custom_due ? date('Y-m-d H:i:s', strtotime($loan['due_date'])) : date('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));
                $notif_title = "Book Borrow Approved";
                $notif_msg = "Your request to borrow '" . $book_title . "' has been approved! Due date: " . $due_date_str;
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $msg = "Borrow request approved successfully.";
            } elseif ($status === 'Pending Renewal') {
                // Update due date and set Active
                $update_loan = $conn->prepare("UPDATE book_loans SET status = 'Active', due_date = DATE_ADD(due_date, INTERVAL 7 DAY) WHERE id = ?");
                $update_loan->bind_param("i", $loan_id);
                $update_loan->execute();
                $update_loan->close();
                
                // Notify student
                $notif_title = "Book Renewal Approved";
                $notif_msg = "Your request to renew '" . $book_title . "' has been approved! Due date extended by 7 days.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $msg = "Renewal request approved successfully.";
            } elseif ($status === 'Pending Return') {
                // Calculate overdue fine (₹10 per day or part thereof past the due date)
                $fine = 0.00;
                $now = new DateTime();
                $due = new DateTime($loan['due_date']);
                if ($now > $due) {
                    $diff_seconds = $now->getTimestamp() - $due->getTimestamp();
                    if ($diff_seconds > 0) {
                        $days = ceil($diff_seconds / 86400);
                        $fine = $days * 10.00;
                    }
                }
                
                // Increment copies
                $update_book = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
                $update_book->bind_param("i", $book_id);
                $update_book->execute();
                $update_book->close();
                
                // Update loan
                $update_loan = $conn->prepare("UPDATE book_loans SET return_date = NOW(), fine_amount = ?, status = 'Returned' WHERE id = ?");
                $update_loan->bind_param("di", $fine, $loan_id);
                $update_loan->execute();
                $update_loan->close();
                
                // Process fine
                if ($fine > 0) {
                    $dues_check = $conn->prepare("SELECT id FROM dues WHERE student_id = ?");
                    $dues_check->bind_param("i", $student_id);
                    $dues_check->execute();
                    $dues_exists = $dues_check->get_result()->num_rows > 0;
                    $dues_check->close();
                    
                    if ($dues_exists) {
                        $update_due = $conn->prepare("UPDATE dues SET library_due = library_due + ? WHERE student_id = ?");
                        $update_due->bind_param("di", $fine, $student_id);
                        $update_due->execute();
                        $update_due->close();
                    } else {
                        $insert_due = $conn->prepare("INSERT INTO dues (student_id, library_due, hostel_due, tuition_due) VALUES (?, ?, 0.00, 0.00)");
                        $insert_due->bind_param("id", $student_id, $fine);
                        $insert_due->execute();
                        $insert_due->close();
                    }
                    
                    // Notify student of fine
                    $notif_title = "Library Fine Assessed";
                    $notif_msg = "A library fine of ₹" . number_format($fine, 2) . " has been added to your dues for returning '" . $book_title . "' late.";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                    $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                    
                    // Auto-hold/reject clearance
                    $hold_stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Rejected', admin_rejection_reason = ?, admin_timestamp = NOW() WHERE student_id = ? AND status IN ('Pending', 'Approved')");
                    $hold_reason = "Clearance put on hold due to newly assessed library fine of ₹" . number_format($fine, 2) . ". Please clear all dues and resubmit your request.";
                    $hold_stmt->bind_param("si", $hold_reason, $student_id);
                    $hold_stmt->execute();
                    $hold_stmt->close();
                }
                
                // Notify student of return approval
                $notif_title = "Book Return Approved";
                $notif_msg = "Your request to return '" . $book_title . "' has been approved and the book is successfully checked in.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $msg = "Return request approved successfully.";
            } else {
                throw new Exception("This request cannot be approved in its current state");
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- ACTION: REJECT REQUEST (Library Admin/Super Admin) ---
    if ($action === 'reject') {
        if ($role !== 'super_admin' && ($role !== 'admin' || $_SESSION['department'] !== 'Library')) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Permission denied']));
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        $loan_id = isset($data['loan_id']) ? intval($data['loan_id']) : 0;
        
        if ($loan_id <= 0) {
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid loan ID']));
        }
        
        $conn->begin_transaction();
        try {
            $loan_stmt = $conn->prepare("SELECT l.*, b.title FROM book_loans l JOIN books b ON l.book_id = b.id WHERE l.id = ? FOR UPDATE");
            $loan_stmt->bind_param("i", $loan_id);
            $loan_stmt->execute();
            $loan_res = $loan_stmt->get_result();
            if ($loan_res->num_rows === 0) {
                throw new Exception("Loan record not found");
            }
            $loan = $loan_res->fetch_assoc();
            $loan_stmt->close();
            
            $status = $loan['status'];
            $student_id = $loan['student_id'];
            $book_title = $loan['title'];
            
            if ($status === 'Pending Borrow') {
                // Just delete the loan request
                $delete_stmt = $conn->prepare("DELETE FROM book_loans WHERE id = ?");
                $delete_stmt->bind_param("i", $loan_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // Notify student
                $notif_title = "Book Borrow Request Rejected";
                $notif_msg = "Your request to borrow '" . $book_title . "' was rejected by the Library Admin.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $msg = "Borrow request rejected successfully.";
            } elseif ($status === 'Pending Renewal') {
                // Revert to Active
                $update_loan = $conn->prepare("UPDATE book_loans SET status = 'Active' WHERE id = ?");
                $update_loan->bind_param("i", $loan_id);
                $update_loan->execute();
                $update_loan->close();
                
                // Notify student
                $notif_title = "Book Renewal Request Rejected";
                $notif_msg = "Your request to renew '" . $book_title . "' was rejected by the Library Admin.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $msg = "Renewal request rejected successfully.";
            } elseif ($status === 'Pending Return') {
                // Revert to Active or Overdue
                $now = new DateTime();
                $due = new DateTime($loan['due_date']);
                $new_status = ($now > $due) ? 'Overdue' : 'Active';
                
                $update_loan = $conn->prepare("UPDATE book_loans SET status = ? WHERE id = ?");
                $update_loan->bind_param("si", $new_status, $loan_id);
                $update_loan->execute();
                $update_loan->close();
                
                // Notify student
                $notif_title = "Book Return Request Rejected";
                $notif_msg = "Your request to return '" . $book_title . "' was rejected by the Library Admin. Please check in with the library counter.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, user_role, title, message, is_read) VALUES (?, 'student', ?, ?, 0)");
                $notif_stmt->bind_param("iss", $student_id, $notif_title, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $msg = "Return request rejected successfully.";
            } else {
                throw new Exception("This request cannot be rejected in its current state");
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // --- ACTION: SUBMIT BORROW REQUEST (Student only) ---
    if ($role !== 'student') {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Only students can borrow books']));
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $book_id = isset($data['book_id']) ? intval($data['book_id']) : 0;
    
    if ($book_id <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Invalid book ID']));
    }
    
    $conn->begin_transaction();
    try {
        // Check if book exists and has copies
        $book_stmt = $conn->prepare("SELECT available_copies FROM books WHERE id = ? FOR UPDATE");
        $book_stmt->bind_param("i", $book_id);
        $book_stmt->execute();
        $book_res = $book_stmt->get_result();
        
        if ($book_res->num_rows === 0) {
            throw new Exception("Book not found");
        }
        
        $book = $book_res->fetch_assoc();
        if ($book['available_copies'] <= 0) {
            throw new Exception("This book is currently out of stock");
        }
        $book_stmt->close();
        
        // Check if student already has a pending/active loan for this book
        $loan_check = $conn->prepare("SELECT id FROM book_loans WHERE student_id = ? AND book_id = ? AND status IN ('Pending Borrow', 'Active', 'Pending Renewal', 'Pending Return')");
        $loan_check->bind_param("ii", $user_id, $book_id);
        $loan_check->execute();
        if ($loan_check->get_result()->num_rows > 0) {
            throw new Exception("You already have a pending request or active loan for this book");
        }
        $loan_check->close();
        
        // Insert borrow request with status = 'Pending Borrow', custom duration_days and custom due_date
        $duration_days = isset($data['duration_days']) ? intval($data['duration_days']) : 14;
        $due_date = isset($data['due_date']) ? sanitize($data['due_date']) : null;
        if ($duration_days <= 0 && !$due_date) {
            $duration_days = 14;
        }
        if ($due_date) {
            $borrow_stmt = $conn->prepare("INSERT INTO book_loans (student_id, book_id, status, duration_days, due_date) VALUES (?, ?, 'Pending Borrow', ?, ?)");
            $borrow_stmt->bind_param("iiis", $user_id, $book_id, $duration_days, $due_date);
        } else {
            $borrow_stmt = $conn->prepare("INSERT INTO book_loans (student_id, book_id, status, duration_days) VALUES (?, ?, 'Pending Borrow', ?)");
            $borrow_stmt->bind_param("iii", $user_id, $book_id, $duration_days);
        }
        $borrow_stmt->execute();
        $borrow_stmt->close();
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Borrow request submitted successfully! Pending Library Admin approval.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} elseif ($method === 'PUT') {
    // Keep standard Direct Admin Check In / Return Book as fallback option
    if ($role !== 'super_admin' && ($role !== 'admin' || $_SESSION['department'] !== 'Library')) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Permission denied. Only Library admins or Super Admin can check in books.']));
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $loan_id = isset($data['loan_id']) ? intval($data['loan_id']) : 0;
    
    if ($loan_id <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Invalid loan ID']));
    }
    
    $conn->begin_transaction();
    try {
        $loan_stmt = $conn->prepare("SELECT student_id, book_id, due_date, status FROM book_loans WHERE id = ? FOR UPDATE");
        $loan_stmt->bind_param("i", $loan_id);
        $loan_stmt->execute();
        $loan_res = $loan_stmt->get_result();
        
        if ($loan_res->num_rows === 0) {
            throw new Exception("Borrow record not found");
        }
        
        $loan = $loan_res->fetch_assoc();
        if ($loan['status'] === 'Returned') {
            throw new Exception("This book has already been returned");
        }
        $loan_stmt->close();
        
        $student_id = $loan['student_id'];
        $book_id = $loan['book_id'];
        
        // Calculate overdue fine (₹10 per day or part thereof past the due date)
        $fine = 0.00;
        $now = new DateTime();
        $due = new DateTime($loan['due_date']);
        if ($now > $due) {
            $diff_seconds = $now->getTimestamp() - $due->getTimestamp();
            if ($diff_seconds > 0) {
                $days = ceil($diff_seconds / 86400);
                $fine = $days * 10.00;
            }
        }
        
        // Increment book copies
        $update_book = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?");
        $update_book->bind_param("i", $book_id);
        $update_book->execute();
        $update_book->close();
        
        // Update loan status to Returned
        $update_loan = $conn->prepare("UPDATE book_loans SET return_date = NOW(), fine_amount = ?, status = 'Returned' WHERE id = ?");
        $update_loan->bind_param("di", $fine, $loan_id);
        $update_loan->execute();
        $update_loan->close();
        
        // Assess fine if overdue
        if ($fine > 0) {
            $dues_check = $conn->prepare("SELECT id FROM dues WHERE student_id = ?");
            $dues_check->bind_param("i", $student_id);
            $dues_check->execute();
            $dues_exists = $dues_check->get_result()->num_rows > 0;
            $dues_check->close();
            
            if ($dues_exists) {
                $update_due = $conn->prepare("UPDATE dues SET library_due = library_due + ? WHERE student_id = ?");
                $update_due->bind_param("di", $fine, $student_id);
                $update_due->execute();
                $update_due->close();
            } else {
                $insert_due = $conn->prepare("INSERT INTO dues (student_id, library_due, hostel_due, tuition_due) VALUES (?, ?, 0.00, 0.00)");
                $insert_due->bind_param("id", $student_id, $fine);
                $insert_due->execute();
                $insert_due->close();
            }
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Book returned successfully!' . ($fine > 0 ? " Fine of ₹" . number_format($fine, 2) . " applied." : "")
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>
