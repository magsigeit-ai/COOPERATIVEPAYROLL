<?php
// check_employee_code.php
include 'config/database.php';

header('Content-Type: application/json');

if (isset($_GET['code'])) {
    $employee_code = trim($_GET['code']);
    
    $sql = "SELECT COUNT(*) as count FROM employees WHERE employee_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode(['exists' => $result['count'] > 0]);
} else {
    echo json_encode(['exists' => false]);
}
?>