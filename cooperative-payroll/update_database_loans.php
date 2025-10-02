<?php
include 'config/database.php';

echo "<h3>🔄 Creating Loan Management Tables</h3>";

// Create employee_loans table
$sql = "CREATE TABLE IF NOT EXISTS employee_loans (
    loan_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    loan_type VARCHAR(50) NOT NULL,
    loan_amount DECIMAL(10,2) NOT NULL,
    remaining_balance DECIMAL(10,2) NOT NULL,
    monthly_deduction DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('Active', 'Paid', 'Cancelled') DEFAULT 'Active',
    purpose TEXT,
    approved_by VARCHAR(100),
    approved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Created employee_loans table successfully!<br>";
} else {
    echo "❌ Error creating employee_loans table: " . $conn->error . "<br>";
}

// Create loan_payments table
$sql = "CREATE TABLE IF NOT EXISTS loan_payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    pay_period_start DATE,
    pay_period_end DATE,
    payment_method VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES employee_loans(loan_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Created loan_payments table successfully!<br>";
} else {
    echo "❌ Error creating loan_payments table: " . $conn->error . "<br>";
}

// Insert sample loan data for testing
$sample_loans = [
    [1, 'Emergency Loan', 5000.00, 4500.00, 500.00, '2024-01-15', '2024-11-15', 'Active', 'Medical emergency', 'Admin', '2024-01-10'],
    [2, 'Educational Loan', 10000.00, 8000.00, 1000.00, '2024-02-01', '2024-12-01', 'Active', 'Children education', 'Admin', '2024-01-25'],
    [3, 'Consumer Loan', 3000.00, 3000.00, 300.00, '2024-03-01', '2025-03-01', 'Active', 'Appliance purchase', 'Admin', '2024-02-15'],
    [4, 'Rice Loan', 2000.00, 1500.00, 500.00, '2024-01-20', '2024-05-20', 'Active', 'Monthly rice allowance', 'Admin', '2024-01-18']
];

$sample_count = 0;
foreach ($sample_loans as $loan) {
    $check_sql = "SELECT COUNT(*) as count FROM employee_loans WHERE employee_id = ? AND loan_type = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $loan[0], $loan[1]);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    
    if ($exists['count'] == 0) {
        $insert_sql = "INSERT INTO employee_loans 
                      (employee_id, loan_type, loan_amount, remaining_balance, monthly_deduction, start_date, end_date, status, purpose, approved_by, approved_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isdddssssss", $loan[0], $loan[1], $loan[2], $loan[3], $loan[4], $loan[5], $loan[6], $loan[7], $loan[8], $loan[9], $loan[10]);
        
        if ($insert_stmt->execute()) {
            $sample_count++;
        }
    }
}

echo "✅ Inserted $sample_count sample loans for testing!<br>";

echo "<h4>🎉 Loan Management Database Setup Complete!</h4>";
echo "<a href='loans.php' class='btn btn-success'>Go to Loan Management</a>";

$conn->close();
?>