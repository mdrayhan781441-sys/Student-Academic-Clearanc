<?php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/session.php';

if (!isSuperAdmin()) {
    die(json_encode(['success' => false, 'message' => 'Forbidden']));
}

// Total Dues Calculation
$total_dues_query = "SELECT 
    SUM(library_due) as total_library_due,
    SUM(hostel_due) as total_hostel_due,
    SUM(tuition_due) as total_tuition_due,
    (SUM(library_due) + SUM(hostel_due) + SUM(tuition_due)) as grand_total_due
    FROM dues";
$total_dues = $conn->query($total_dues_query)->fetch_assoc();

// Total Paid Calculation
$total_paid_query = "SELECT 
    SUM(library_paid) as total_library_paid,
    SUM(hostel_paid) as total_hostel_paid,
    SUM(tuition_paid) as total_tuition_paid,
    SUM(total_paid) as grand_total_paid
    FROM payments";
$total_paid = $conn->query($total_paid_query)->fetch_assoc();

// Department-wise Breakdown
$dept_query = "SELECT 
    dept.name AS department,
    SUM(d.library_due + d.hostel_due + d.tuition_due) as dept_total_due,
    SUM(p.total_paid) as dept_total_paid
    FROM students s
    LEFT JOIN departments dept ON s.department_id = dept.id
    LEFT JOIN dues d ON s.id = d.student_id
    LEFT JOIN payments p ON s.id = p.student_id
    GROUP BY dept.name";
$dept_result = $conn->query($dept_query);
$dept_breakdown = [];
while ($row = $dept_result->fetch_assoc()) {
    $dept_breakdown[] = $row;
}

// Prepare HTML for "PDF-like" print view
$report_html = "
    <div class='report-container' style='padding: 40px; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif; background: white;'>
        <div style='display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1e293b; padding-bottom: 20px; margin-bottom: 30px;'>
            <div>
                <h1 style='margin: 0; color: #1e293b;'>Financial Clearance Report</h1>
                <p style='margin: 5px 0 0 0; color: #64748b;'>University Management System</p>
            </div>
            <div style='text-align: right;'>
                <p style='margin: 0; font-weight: bold;'>Date: " . date('F j, Y') . "</p>
                <p style='margin: 5px 0 0 0; color: #64748b;'>Time: " . date('H:i:s') . "</p>
            </div>
        </div>
        
        <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px;'>
            <div style='background: #f8fafc; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                <h3 style='margin-top: 0; color: #334155; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px;'>Expected Revenue (Dues)</h3>
                <div style='display: flex; justify-content: space-between; margin: 10px 0;'><span>Library:</span> <strong>₹" . number_format($total_dues['total_library_due'], 2) . "</strong></div>
                <div style='display: flex; justify-content: space-between; margin: 10px 0;'><span>Hostel:</span> <strong>₹" . number_format($total_dues['total_hostel_due'], 2) . "</strong></div>
                <div style='display: flex; justify-content: space-between; margin: 10px 0;'><span>Tuition:</span> <strong>₹" . number_format($total_dues['total_tuition_due'], 2) . "</strong></div>
                <div style='margin-top: 20px; padding-top: 15px; border-top: 2px dashed #cbd5e1; display: flex; justify-content: space-between; font-size: 1.2em;'>
                    <span><strong>Total:</strong></span> 
                    <span style='color: #1e293b;'><strong>₹" . number_format($total_dues['grand_total_due'], 2) . "</strong></span>
                </div>
            </div>
            
            <div style='background: #f0fdf4; padding: 25px; border-radius: 12px; border: 1px solid #bbf7d0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                <h3 style='margin-top: 0; color: #166534; border-bottom: 1px solid #bbf7d0; padding-bottom: 10px;'>Actual Collection (Paid)</h3>
                <div style='display: flex; justify-content: space-between; margin: 10px 0;'><span>Library:</span> <strong>₹" . number_format($total_paid['total_library_paid'], 2) . "</strong></div>
                <div style='display: flex; justify-content: space-between; margin: 10px 0;'><span>Hostel:</span> <strong>₹" . number_format($total_paid['total_hostel_paid'], 2) . "</strong></div>
                <div style='display: flex; justify-content: space-between; margin: 10px 0;'><span>Tuition:</span> <strong>₹" . number_format($total_paid['total_tuition_paid'], 2) . "</strong></div>
                <div style='margin-top: 20px; padding-top: 15px; border-top: 2px dashed #bbf7d0; display: flex; justify-content: space-between; font-size: 1.2em;'>
                    <span><strong>Total:</strong></span> 
                    <span style='color: #15803d;'><strong>₹" . number_format($total_paid['grand_total_paid'], 2) . "</strong></span>
                </div>
            </div>
        </div>

        <div style='margin-top: 40px; background: #fff7ed; padding: 20px; border-radius: 8px; border: 1px solid #ffedd5; text-align: center;'>
            <p style='margin: 0; font-size: 1.1em;'>Overall Outstanding Balance: <strong style='color: #c2410c; font-size: 1.4em;'>₹" . number_format($total_dues['grand_total_due'] - $total_paid['grand_total_paid'], 2) . "</strong></p>
        </div>

        <h3 style='margin-top: 50px; color: #1e293b;'>Departmental Performance Breakdown</h3>
        <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
            <thead>
                <tr style='background: #1e293b; color: white;'>
                    <th style='padding: 15px; border: 1px solid #e2e8f0; text-align: left;'>Department</th>
                    <th style='padding: 15px; border: 1px solid #e2e8f0; text-align: right;'>Total Expected</th>
                    <th style='padding: 15px; border: 1px solid #e2e8f0; text-align: right;'>Total Collected</th>
                    <th style='padding: 15px; border: 1px solid #e2e8f0; text-align: right;'>Collection Rate</th>
                    <th style='padding: 15px; border: 1px solid #e2e8f0; text-align: right;'>Pending</th>
                </tr>
            </thead>
            <tbody>";

foreach ($dept_breakdown as $dept) {
    $pending = $dept['dept_total_due'] - $dept['dept_total_paid'];
    $rate = $dept['dept_total_due'] > 0 ? ($dept['dept_total_paid'] / $dept['dept_total_due']) * 100 : 100;
    $report_html .= "
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 15px; border: 1px solid #e2e8f0; font-weight: bold;'>" . $dept['department'] . "</td>
                    <td style='padding: 15px; border: 1px solid #e2e8f0; text-align: right;'>₹" . number_format($dept['dept_total_due'], 2) . "</td>
                    <td style='padding: 15px; border: 1px solid #e2e8f0; text-align: right; color: #15803d;'>₹" . number_format($dept['dept_total_paid'], 2) . "</td>
                    <td style='padding: 15px; border: 1px solid #e2e8f0; text-align: right;'>" . number_format($rate, 1) . "%</td>
                    <td style='padding: 15px; border: 1px solid #e2e8f0; text-align: right; font-weight: bold; color: " . ($pending > 0 ? '#ef4444' : '#22c55e') . "'>₹" . number_format($pending, 2) . "</td>
                </tr>";
}

$report_html .= "
            </tbody>
        </table>
        
        <div style='margin-top: 60px; display: flex; justify-content: space-between;'>
            <div style='text-align: center; width: 200px;'>
                <div style='border-bottom: 1px solid #000; margin-bottom: 5px;'></div>
                <p>Prepared By (Bursar)</p>
            </div>
            <div style='text-align: center; width: 200px;'>
                <div style='border-bottom: 1px solid #000; margin-bottom: 5px;'></div>
                <p>Verified By (Registrar)</p>
            </div>
            <div style='text-align: center; width: 200px;'>
                <div style='border-bottom: 1px solid #000; margin-bottom: 5px;'></div>
                <p>Approved By (Vice Chancellor)</p>
            </div>
        </div>
    </div>
";

echo json_encode([
    'success' => true,
    'html' => $report_html,
    'totals' => [
        'due' => $total_dues['grand_total_due'],
        'paid' => $total_paid['grand_total_paid']
    ]
]);
