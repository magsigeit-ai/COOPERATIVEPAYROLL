[file name]: update_late_deduction.php
[file content begin]
<?php
include 'config/database.php';

echo "<h3>🔄 Adding Late Deduction Features to Database</h3>";

// Add late_minutes and absent columns to time_records
$columns_to_add = [
    'late_minutes' => "ALTER TABLE time_records ADD COLUMN late_minutes INT DEFAULT 0",
    'absent' => "ALTER TABLE time_records ADD COLUMN absent BOOLEAN DEFAULT FALSE"
];

foreach ($columns_to_add as $column => $sql) {
    $check = $conn->query("SHOW COLUMNS FROM time_records LIKE '$column'");
    if ($check->num_rows == 0) {
        if ($conn->query($sql)) {
            echo "✅ Added $column column to time_records<br>";
        } else {
            echo "❌ Error adding $column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ $column column already exists<br>";
    }
}

// Add late_deduction column to payroll_calculations
$check_late = $conn->query("SHOW COLUMNS FROM payroll_calculations LIKE 'late_deduction'");
if ($check_late->num_rows == 0) {
    $sql = "ALTER TABLE payroll_calculations ADD COLUMN late_deduction DECIMAL(10,2) DEFAULT 0.00";
    if ($conn->query($sql)) {
        echo "✅ Added late_deduction column to payroll_calculations<br>";
    } else {
        echo "❌ Error adding late_deduction: " . $conn->error . "<br>";
    }
} else {
    echo "✅ late_deduction column already exists<br>";
}

echo "<h4>🎉 Late Deduction Setup Complete!</h4>";
echo "<a href='timekeeping.php' class='btn btn-primary'>Go to Timekeeping</a>";
echo " <a href='payroll.php' class='btn btn-success'>Go to Payroll</a>";
?>
[file content end]