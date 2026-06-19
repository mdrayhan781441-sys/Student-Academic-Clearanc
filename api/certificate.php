<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Generate/Issue certificate
    requireRole('super_admin');
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['request_id'])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Missing request ID']));
    }
    
    $request_id = intval($data['request_id']);
    
    // Get request and student details
    $stmt = $conn->prepare("SELECT s.id as student_db_id, s.student_id, s.name, s.email, dept.name AS department, s.cgpa,
                            d.library_due, d.hostel_due, d.tuition_due,
                            p.total_paid
                            FROM clearance_requests cr
                            JOIN students s ON cr.student_id = s.id
                            LEFT JOIN departments dept ON s.department_id = dept.id
                            LEFT JOIN dues d ON s.id = d.student_id
                            LEFT JOIN payments p ON s.id = p.student_id
                            WHERE cr.id = ? AND cr.status = 'Approved'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Request not found or not approved']));
    }
    
    $student = $result->fetch_assoc();
    $student_db_id = $student['student_db_id'];
    
    // Generate certificate HTML
    $certificate_date = date('j F Y');
    $certificate_html = generateCertificateHTML($student, $certificate_date);
    
    // Save certificate to database
    $stmt = $conn->prepare("INSERT INTO certificates (student_id, certificate_data, issued_date) VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE certificate_data = VALUES(certificate_data), issued_date = NOW()");
    $stmt->bind_param("is", $student_db_id, $certificate_html);
    
    if ($stmt->execute()) {
        // Update clearance request status
        $stmt = $conn->prepare("UPDATE clearance_requests SET status = 'Approved', super_admin_action = 'Approved', super_admin_timestamp = NOW() WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Certificate generated successfully!',
            'certificate_id' => $stmt->insert_id
        ]);
    } else {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'Failed to generate certificate']));
    }
    
} elseif ($method === 'GET') {
    // Download certificate
    requireRole('student');
    
    $user_id = getCurrentUserId();
    
    // First, verify that the student's latest clearance request has been approved by the Super Admin
    $req_stmt = $conn->prepare("SELECT super_admin_action FROM clearance_requests WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
    $req_stmt->bind_param("i", $user_id);
    $req_stmt->execute();
    $req_res = $req_stmt->get_result();
    
    if ($req_res->num_rows === 0) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'No clearance request found for your account']));
    }
    
    $req = $req_res->fetch_assoc();
    if ($req['super_admin_action'] !== 'Approved') {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Access denied: Your certificate requires final Super Admin signature before download']));
    }
    
    $req_stmt->close();
    
    $stmt = $conn->prepare("SELECT c.certificate_data, s.name FROM certificates c
                            JOIN students s ON c.student_id = s.id
                            WHERE c.student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Certificate not found']));
    }
    
    $certificate = $result->fetch_assoc();
    
    // Return certificate HTML
    echo json_encode([
        'success' => true,
        'certificate' => $certificate['certificate_data'],
        'student_name' => $certificate['name']
    ]);
}

// Function to generate certificate HTML
function generateCertificateHTML($student, $date) {
    $html = <<<EOT
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Clearance Certificate</title>
    <style>
        body {
            font-family: 'Georgia', serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .certificate {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border: 2px solid #1a5490;
            border-radius: 10px;
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1a5490;
            padding-bottom: 20px;
            margin-bottom: 40px;
        }
        .logo {
            font-size: 14px;
            color: #1a5490;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .title {
            font-size: 36px;
            color: #1a5490;
            font-weight: bold;
            margin: 20px 0;
        }
        .subtitle {
            font-size: 18px;
            color: #666;
            margin: 10px 0;
        }
        .content {
            margin: 40px 0;
            line-height: 1.8;
            text-align: center;
        }
        .certificate-text {
            font-size: 16px;
            color: #333;
            margin: 30px 0;
        }
        .student-info {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 30px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            margin: 10px 0;
            font-size: 15px;
        }
        .info-label {
            width: 150px;
            font-weight: bold;
            color: #1a5490;
        }
        .info-value {
            flex: 1;
            color: #333;
        }
        .statement {
            font-size: 16px;
            color: #333;
            margin: 30px 0;
            font-style: italic;
            padding: 20px;
            background: #fff9e6;
            border-left: 4px solid #1a5490;
        }
        .signature-section {
            display: flex;
            justify-content: space-around;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #ccc;
        }
        .signature-box {
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 150px;
            margin-top: 40px;
        }
        .date {
            text-align: center;
            margin-top: 30px;
            color: #666;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <div class="logo">EDUCATIONAL INSTITUTION</div>
            <div class="title">ACADEMIC CLEARANCE CERTIFICATE</div>
            <div class="subtitle">Student Clearance & Dues Verification</div>
        </div>
        
        <div class="content">
            <div class="certificate-text">
                This is to certify that
            </div>
            
            <div class="student-info">
                <div class="info-row">
                    <div class="info-label">Student Name:</div>
                    <div class="info-value">{$student['name']}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Student ID:</div>
                    <div class="info-value">{$student['student_id']}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Department:</div>
                    <div class="info-value">{$student['department']}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">CGPA:</div>
                    <div class="info-value">{$student['cgpa']}</div>
                </div>
            </div>
            
            <div class="statement">
                <strong>DECLARATION:</strong><br>
                This certifies that the above-mentioned student has successfully cleared all academic and financial dues including Library Clearance, Hostel Clearance, and Tuition Fees. The student is eligible for graduation and is authorized to proceed with degree conferment and academic clearance procedures.
            </div>
            
            <div class="certificate-text" style="font-weight: bold;">
                All dues have been satisfactorily settled.
            </div>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div>Admin</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div>Super Admin</div>
                </div>
            </div>
            
            <div class="date">
                <strong>Date of Issue:</strong> {$date}
            </div>
            
            <div class="footer">
                This certificate is digitally verified and valid for all official purposes.
            </div>
        </div>
    </div>
</body>
</html>
EOT;
    return $html;
}

$conn->close();
?>
