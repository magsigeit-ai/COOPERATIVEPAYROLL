<?php
include 'config/database.php';

echo "<h3>🔄 Updating Payroll Table Structure</h3>";

// Check if payroll_name column exists
$check_sql = "SHOW COLUMNS FROM payroll_calculations LIKE 'payroll_name'";
$result = $conn->query($check_sql);

if ($result->num_rows == 0) {
    // Add payroll_name column
    $alter_sql = "ALTER TABLE payroll_calculations ADD COLUMN payroll_name VARCHAR(100) AFTER pay_period_end";
    if ($conn->query($alter_sql) === TRUE) {
        echo "✅ Added payroll_name column successfully!<br>";
        
        // Update existing records with default payroll names
        $update_sql = "UPDATE payroll_calculations 
                      SET payroll_name = CONCAT('Payroll ', DATE_FORMAT(pay_period_start, '%M %d'), ' - ', DATE_FORMAT(pay_period_end, '%M %d, %Y'))";
        if ($conn->query($update_sql)) {
            echo "✅ Updated existing records with payroll names!<br>";
        }
    } else {
        echo "❌ Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "✅ payroll_name column already exists!<br>";
}

echo "<h4>🎉 Payroll Table Update Complete!</h4>";
echo "<a href='payroll.php' class='btn btn-success'>Go to Payroll Management</a>";

$conn->close();
?>