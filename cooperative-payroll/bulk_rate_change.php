<?php
include 'config/database.php';

if ($_POST) {
    $employee_ids = $_POST['employee_ids'] ?? [];
    $new_rate = $_POST['new_rate'];
    $effective_date = $_POST['effective_date'];
    $reason = $_POST['reason'];
    $notes = $_POST['notes'];
    
    $success_count = 0;
    $error_count = 0;
    $results = [];
    
    foreach ($employee_ids as $employee_id) {
        // Get current rate
        $current_sql = "SELECT base_daily_rate, employee_code, full_name FROM employees WHERE employee_id = ?";
        $current_stmt = $conn->prepare($current_sql);
        $current_stmt->bind_param("i", $employee_id);
        $current_stmt->execute();
        $employee = $current_stmt->get_result()->fetch_assoc();
        $old_rate = $employee['base_daily_rate'];
        
        if ($old_rate != $new_rate) {
            // Update employee record
            $update_sql = "UPDATE employees SET base_daily_rate = ? WHERE employee_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $new_rate, $employee_id);
            
            if ($update_stmt->execute()) {
                // Add to rate history
                $history_sql = "INSERT INTO employee_rate_history 
                               (employee_id, old_rate, new_rate, change_date, effective_date, reason, notes, changed_by) 
                               VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'Bulk Update')";
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->bind_param("iddsss", $employee_id, $old_rate, $new_rate, $effective_date, $reason, $notes);
                
                if ($history_stmt->execute()) {
                    $success_count++;
                    $results[] = [
                        'employee' => $employee['employee_code'] . ' - ' . $employee['full_name'],
                        'status' => 'success',
                        'message' => 'Updated from ₱' . number_format($old_rate, 2) . ' to ₱' . number_format($new_rate, 2)
                    ];
                } else {
                    $error_count++;
                    $results[] = [
                        'employee' => $employee['employee_code'] . ' - ' . $employee['full_name'],
                        'status' => 'error',
                        'message' => 'Failed to save history'
                    ];
                }
            } else {
                $error_count++;
                $results[] = [
                    'employee' => $employee['employee_code'] . ' - ' . $employee['full_name'],
                    'status' => 'error', 
                    'message' => 'Failed to update rate'
                ];
            }
        } else {
            $results[] = [
                'employee' => $employee['employee_code'] . ' - ' . $employee['full_name'],
                'status' => 'warning',
                'message' => 'Rate unchanged (same as current)'
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Rate Change Results - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-<?php echo $error_count == 0 ? 'success' : 'warning'; ?> text-white">
                <h4><i class="fas fa-tasks"></i> Bulk Rate Change Results</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-<?php echo $error_count == 0 ? 'success' : 'warning'; ?>">
                    <h5>Summary</h5>
                    <p>
                        ✅ <strong>Successful:</strong> <?php echo $success_count; ?><br>
                        ❌ <strong>Errors:</strong> <?php echo $error_count; ?><br>
                        📋 <strong>Total Processed:</strong> <?php echo count($employee_ids); ?>
                    </p>
                </div>
                
                <h5>Detailed Results:</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                            <tr>
                                <td><?php echo $result['employee']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $result['status'] == 'success' ? 'success' : 
                                             ($result['status'] == 'warning' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($result['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $result['message']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <a href="employees.php" class="btn btn-primary">Back to Employee Management</a>
                    <a href="bulk_rate_change.php" class="btn btn-secondary">Perform Another Bulk Update</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>