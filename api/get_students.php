<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare("SELECT s.id, s.student_id, s.name, d.name AS department FROM students s LEFT JOIN departments d ON s.department_id = d.id ORDER BY s.student_id");
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    'success' => true,
    'students' => $students
]);

$conn->close();
?>
