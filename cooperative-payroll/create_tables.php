<?php
// EMERGENCY FIX - Direct database connection
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'cooperative_payroll';

$conn = new mysqli($host, $user, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $database";
$conn->query($sql);
$conn->select_db($database);

echo "Database connected!<br>";

// 1. Employees Table
$sql = "CREATE TABLE IF NOT EXISTS employees (
    employee_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    designation VARCHAR(50) NOT NULL,
    category VARCHAR(20) NOT NULL,
    base_daily_rate DECIMAL(10,2) NOT NULL,
    date_hired DATE,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    sss_number VARCHAR(20),
    philhealth_number VARCHAR(20),
    pagibig_number VARCHAR(20),
    tin_number VARCHAR(20),
    bank_account VARCHAR(50),
    company_assigned VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Employees table created successfully!<br>";
} else {
    echo "❌ Error creating employees table: " . $conn->error . "<br>";
}

// 2. Employee Preferences Table
$sql = "CREATE TABLE IF NOT EXISTS employee_preferences (
    pref_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    preferred_sss_amount DECIMAL(10,2) DEFAULT 50.00,
    preferred_philhealth_amount DECIMAL(10,2) DEFAULT 0.00,
    preferred_pagibig_amount DECIMAL(10,2) DEFAULT 0.00,
    effective_date DATE DEFAULT CURRENT_DATE,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Employee preferences table created!<br>";
} else {
    echo "❌ Error creating preferences table: " . $conn->error . "<br>";
}

// 3. Daily Rates Table
$sql = "CREATE TABLE IF NOT EXISTS daily_rates (
    rate_id INT PRIMARY KEY AUTO_INCREMENT,
    day_of_week VARCHAR(10) NOT NULL,
    multiplier DECIMAL(4,2) NOT NULL,
    overtime_hourly_rate DECIMAL(10,2) NOT NULL,
    effective_date DATE DEFAULT '2024-01-01'
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Daily rates table created!<br>";
} else {
    echo "❌ Error creating rates table: " . $conn->error . "<br>";
}

// Insert sample rate data
$rates_data = [
    ['Monday', 1.0, 78.91],
    ['Tuesday', 1.0, 78.91],
    ['Wednesday', 1.0, 78.91],
    ['Thursday', 1.0, 78.91],
    ['Friday', 1.0, 78.91],
    ['Saturday', 1.0, 78.91],
    ['Sunday', 1.3, 102.58]
];

foreach ($rates_data as $rate) {
    $sql = "INSERT IGNORE INTO daily_rates (day_of_week, multiplier, overtime_hourly_rate) 
            VALUES ('$rate[0]', $rate[1], $rate[2])";
    $conn->query($sql);
}
echo "✅ Daily rates inserted!<br>";

// 4. Time Records Table
$sql = "CREATE TABLE IF NOT EXISTS time_records (
    time_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT,
    work_date DATE NOT NULL,
    regular_hours DECIMAL(4,2) DEFAULT 8.00,
    overtime_hours DECIMAL(4,2) DEFAULT 0.00,
    night_differential_hours DECIMAL(4,2) DEFAULT 0.00,
    is_holiday BOOLEAN DEFAULT FALSE,
    holiday_type VARCHAR(20),
    late_minutes INT DEFAULT 0,
    absent BOOLEAN DEFAULT FALSE,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Time records table created!<br>";
} else {
    echo "❌ Error creating time records: " . $conn->error . "<br>";
}

// 5. Insert Sample Employees
$employees = [
    ['FARM001', 'Juan Dela Cruz', 'Farmer', 'CAT 2', 505.00],
    ['FARM002', 'Pedro Santos', 'Farmer', 'CAT 2', 505.00],
    ['FARM003', 'Maria Garcia', 'Farmer', 'CAT 2', 505.00],
    ['DRIV001', 'Antonio Reyes', 'Driver', 'CAT 2', 510.00],
    ['DRIV002', 'Roberto Lim', 'Driver', 'CAT 2', 510.00]
];

foreach ($employees as $emp) {
    $sql = "INSERT IGNORE INTO employees (employee_code, full_name, designation, category, base_daily_rate) 
            VALUES ('$emp[0]', '$emp[1]', '$emp[2]', '$emp[3]', $emp[4])";
    $conn->query($sql);
}
echo "✅ Sample employees inserted!<br>";

// 6. Insert Sample Employee Preferences
$preferences = [
    [1, 125.00, 100.00, 100.00],
    [2, 80.00, 100.00, 50.00],
    [3, 125.00, 80.00, 100.00],
    [4, 150.00, 120.00, 100.00],
    [5, 50.00, 100.00, 50.00]
];

foreach ($preferences as $pref) {
    $sql = "INSERT IGNORE INTO employee_preferences (employee_id, preferred_sss_amount, preferred_philhealth_amount, preferred_pagibig_amount) 
            VALUES ($pref[0], $pref[1], $pref[2], $pref[3])";
    $conn->query($sql);
}
echo "✅ Employee preferences inserted!<br>";

echo "<h3>🎉 DATABASE SETUP COMPLETED SUCCESSFULLY!</h3>";
echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h4>✅ Your payroll system is now ready!</h4>";
echo "<p><strong>5 Employees</strong> have been added (3 Farmers, 2 Drivers)</p>";
echo "<p><strong>Daily rates</strong> have been configured (including Sunday premium)</p>";
echo "<p><strong>Employee preferences</strong> for SSS, PhilHealth, Pag-IBIG have been set</p>";
echo "</div>";

echo "<a href='index.php' class='btn btn-success' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>🚀 GO TO DASHBOARD</a>";

$conn->close();
?>