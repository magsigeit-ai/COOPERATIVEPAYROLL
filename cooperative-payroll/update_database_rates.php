<?php
include 'config/database.php';

echo "<h3>🔄 Updating Database for Rate Management System</h3>";

// Create employee_rate_history table
$sql = "CREATE TABLE IF NOT EXISTS employee_rate_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    old_rate DECIMAL(10,2),
    new_rate DECIMAL(10,2),
    change_date DATE,
    effective_date DATE,
    reason VARCHAR(100),
    changed_by VARCHAR(50) DEFAULT 'System',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Created employee_rate_history table successfully!<br>";
} else {
    echo "❌ Error creating table: " . $conn->error . "<br>";
}

// Insert initial rate history for existing employees
$employees_sql = "SELECT * FROM employees";
$result = $conn->query($employees_sql);

$initial_count = 0;
while ($employee = $result->fetch_assoc()) {
    $check_sql = "SELECT COUNT(*) as count FROM employee_rate_history WHERE employee_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $employee['employee_id']);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    
    if ($exists['count'] == 0) {
        // Insert initial rate record
        $insert_sql = "INSERT INTO employee_rate_history 
                      (employee_id, old_rate, new_rate, change_date, effective_date, reason, notes) 
                      VALUES (?, 0, ?, CURDATE(), CURDATE(), 'Initial Rate', 'Initial employee rate')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("id", $employee['employee_id'], $employee['base_daily_rate']);
        
        if ($insert_stmt->execute()) {
            $initial_count++;
        }
    }
}

echo "✅ Inserted initial rate history for $initial_count employees<br>";

echo "<h4>🎉 Rate Management Database Setup Complete!</h4>";
echo "<a href='employees.php' class='btn btn-success'>Continue to Employee Management</a>";

$conn->close();
?>