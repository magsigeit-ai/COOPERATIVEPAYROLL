<?php
include 'config/database.php';

echo "<h3>🔄 Fixing Missing Late Deduction Column</h3>";

// Check if late_deduction column exists
$check_sql = "SHOW COLUMNS FROM payroll_calculations LIKE 'late_deduction'";
$result = $conn->query($check_sql);

if ($result->num_rows == 0) {
    // Add the missing column
    $alter_sql = "ALTER TABLE payroll_calculations ADD COLUMN late_deduction DECIMAL(10,2) DEFAULT 0.00";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "✅ Successfully added 'late_deduction' column to payroll_calculations table!<br>";
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "✅ 'late_deduction' column already exists!<br>";
}

// Also check for other missing columns
$columns_to_check = [
    'late_minutes' => 'time_records',
    'absent' => 'time_records'
];

foreach ($columns_to_check as $column => $table) {
    $check = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if ($check->num_rows == 0) {
        if ($column == 'late_minutes') {
            $sql = "ALTER TABLE $table ADD COLUMN $column INT DEFAULT 0";
        } else {
            $sql = "ALTER TABLE $table ADD COLUMN $column BOOLEAN DEFAULT FALSE";
        }
        
        if ($conn->query($sql)) {
            echo "✅ Added $column column to $table<br>";
        } else {
            echo "❌ Error adding $column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ $column column already exists in $table<br>";
    }
}

echo "<h4>🎉 Database Fix Complete!</h4>";
echo "<a href='payroll.php' class='btn btn-success'>Go to Payroll</a>";
echo " <a href='import_data.php' class='btn btn-primary'>Go to Import</a>";
?>