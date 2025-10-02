<?php
include 'config/database.php';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="payroll_data_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Get data based on type
$type = $_GET['type'] ?? 'employees';

switch($type) {
    case 'employees':
        exportEmployees($conn);
        break;
    case 'payroll':
        exportPayroll($conn);
        break;
    case 'loans':
        exportLoans($conn);
        break;
    case 'timekeeping':
        exportTimekeeping($conn);
        break;
}

function exportEmployees($conn) {
    $result = $conn->query("SELECT e.*, p.preferred_sss_amount, p.preferred_philhealth_amount, p.preferred_pagibig_amount 
                           FROM employees e 
                           LEFT JOIN employee_preferences p ON e.employee_id = p.employee_id 
                           ORDER BY e.employee_code");
    
    echo "Employee Code\tFull Name\tDesignation\tCategory\tDaily Rate\tSSS\tPhilHealth\tPag-IBIG\tStatus\tDate Hired\n";
    
    while($row = $result->fetch_assoc()) {
        echo $row['employee_code'] . "\t";
        echo $row['full_name'] . "\t";
        echo $row['designation'] . "\t";
        echo $row['category'] . "\t";
        echo $row['base_daily_rate'] . "\t";
        echo ($row['preferred_sss_amount'] ?? 0) . "\t";
        echo ($row['preferred_philhealth_amount'] ?? 0) . "\t";
        echo ($row['preferred_pagibig_amount'] ?? 0) . "\t";
        echo $row['status'] . "\t";
        echo $row['date_hired'] . "\n";
    }
}

function exportPayroll($conn) {
    $period = $_GET['period'] ?? date('Y-m');
    
    $sql = "SELECT e.employee_code, e.full_name, p.* 
            FROM payroll_calculations p 
            JOIN employees e ON p.employee_id = e.employee_id 
            WHERE DATE_FORMAT(p.processed_at, '%Y-%m') = ? 
            ORDER BY p.processed_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Employee Code\tEmployee Name\tPay Period\tGross Income\tSSS\tPhilHealth\tPag-IBIG\tEmergency Loan\tPetty Cash\tTotal Deductions\tNet Income\tProcessed Date\n";
    
    while($row = $result->fetch_assoc()) {
        echo $row['employee_code'] . "\t";
        echo $row['full_name'] . "\t";
        echo $row['pay_period_start'] . " to " . $row['pay_period_end'] . "\t";
        echo $row['gross_income'] . "\t";
        echo $row['sss_amount'] . "\t";
        echo $row['philhealth_amount'] . "\t";
        echo $row['pagibig_amount'] . "\t";
        echo $row['emergency_loan'] . "\t";
        echo $row['petty_cash'] . "\t";
        echo $row['total_deductions'] . "\t";
        echo $row['net_income'] . "\t";
        echo $row['processed_at'] . "\n";
    }
}

function exportLoans($conn) {
    $result = $conn->query("SELECT l.*, e.employee_code, e.full_name 
                           FROM employee_loans l 
                           JOIN employees e ON l.employee_id = e.employee_id 
                           ORDER BY l.status, l.created_at DESC");
    
    echo "Employee Code\tEmployee Name\tLoan Type\tLoan Amount\tRemaining Balance\tMonthly Deduction\tStart Date\tEnd Date\tStatus\tPurpose\n";
    
    while($row = $result->fetch_assoc()) {
        echo $row['employee_code'] . "\t";
        echo $row['full_name'] . "\t";
        echo $row['loan_type'] . "\t";
        echo $row['loan_amount'] . "\t";
        echo $row['remaining_balance'] . "\t";
        echo $row['monthly_deduction'] . "\t";
        echo $row['start_date'] . "\t";
        echo $row['end_date'] . "\t";
        echo $row['status'] . "\t";
        echo $row['purpose'] . "\n";
    }
}

function exportTimekeeping($conn) {
    $period = $_GET['period'] ?? date('Y-m');
    
    $sql = "SELECT t.*, e.employee_code, e.full_name 
            FROM time_records t 
            JOIN employees e ON t.employee_id = e.employee_id 
            WHERE DATE_FORMAT(t.work_date, '%Y-%m') = ? 
            ORDER BY t.work_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $period);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Employee Code\tEmployee Name\tWork Date\tRegular Hours\tOvertime Hours\tNight Hours\tRemarks\n";
    
    while($row = $result->fetch_assoc()) {
        echo $row['employee_code'] . "\t";
        echo $row['full_name'] . "\t";
        echo $row['work_date'] . "\t";
        echo $row['regular_hours'] . "\t";
        echo $row['overtime_hours'] . "\t";
        echo $row['night_differential_hours'] . "\t";
        echo ($row['remarks'] ?? '') . "\n";
    }
}
?>