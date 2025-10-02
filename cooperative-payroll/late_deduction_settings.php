
<?php
include 'config/database.php';

// Handle settings update
if ($_POST['action'] == 'update_late_settings') {
    $deduction_per_minute = $_POST['deduction_per_minute'];
    $grace_period = $_POST['grace_period'];
    $max_deduction_per_day = $_POST['max_deduction_per_day'];
    
    // Save to settings table (create if not exists)
    $conn->query("CREATE TABLE IF NOT EXISTS late_deduction_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        deduction_per_minute DECIMAL(10,2) DEFAULT 5.00,
        grace_period INT DEFAULT 15,
        max_deduction_per_day DECIMAL(10,2) DEFAULT 500.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Check if settings exist
    $check = $conn->query("SELECT COUNT(*) as count FROM late_deduction_settings");
    if ($check->fetch_assoc()['count'] == 0) {
        $sql = "INSERT INTO late_deduction_settings (deduction_per_minute, grace_period, max_deduction_per_day) VALUES (?, ?, ?)";
    } else {
        $sql = "UPDATE late_deduction_settings SET deduction_per_minute = ?, grace_period = ?, max_deduction_per_day = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("did", $deduction_per_minute, $grace_period, $max_deduction_per_day);
    
    if ($stmt->execute()) {
        $success = "✅ Late deduction settings updated successfully!";
    }
}

// Get current settings
$settings = $conn->query("SELECT * FROM late_deduction_settings LIMIT 1")->fetch_assoc();
if (!$settings) {
    // Default settings
    $settings = [
        'deduction_per_minute' => 5.00,
        'grace_period' => 15,
        'max_deduction_per_day' => 500.00
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Late Deduction Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-warning">
                <h5>⚙️ Late Deduction Settings</h5>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_late_settings">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Deduction per Minute (₱):</label>
                                <input type="number" name="deduction_per_minute" class="form-control" 
                                       value="<?php echo $settings['deduction_per_minute']; ?>" step="0.01" min="0" required>
                                <small class="text-muted">Amount deducted per minute of late</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Grace Period (minutes):</label>
                                <input type="number" name="grace_period" class="form-control" 
                                       value="<?php echo $settings['grace_period']; ?>" min="0" max="60" required>
                                <small class="text-muted">No deduction within this period</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Deduction per Day (₱):</label>
                                <input type="number" name="max_deduction_per_day" class="form-control" 
                                       value="<?php echo $settings['max_deduction_per_day']; ?>" step="0.01" min="0" required>
                                <small class="text-muted">Maximum deduction for one day</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Example Calculation:</strong><br>
                        If employee is 30 minutes late with 15-minute grace period:<br>
                        <strong>Effective late minutes:</strong> 30 - 15 = 15 minutes<br>
                        <strong>Deduction:</strong> 15 minutes × ₱<?php echo $settings['deduction_per_minute']; ?> = ₱<?php echo 15 * $settings['deduction_per_minute']; ?><br>
                        <strong>Capped at:</strong> ₱<?php echo $settings['max_deduction_per_day']; ?> per day
                    </div>
                    
                    <button type="submit" class="btn btn-warning">Save Settings</button>
                    <a href="payroll.php" class="btn btn-secondary">Back to Payroll</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
