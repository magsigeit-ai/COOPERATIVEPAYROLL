<?php
include 'config/database.php';

// Handle bulk form submission
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Set default date to today
$default_date = date('Y-m-d');

if ($action == 'bulk_save') {
    $work_date = $_POST['work_date'];
    $success_count = 0;
    $updated_count = 0;
    
    foreach ($_POST['employees'] as $employee_id => $hours) {
        $regular = !empty($hours['regular']) ? floatval($hours['regular']) : 0;
        $overtime = !empty($hours['overtime']) ? floatval($hours['overtime']) : 0;
        $night = !empty($hours['night']) ? floatval($hours['night']) : 0;
        
        // Only process if there are any hours entered
        if ($regular > 0 || $overtime > 0 || $night > 0) {
            
            // Check if record already exists for this date/employee
            $check_sql = "SELECT time_id FROM time_records WHERE employee_id = ? AND work_date = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $employee_id, $work_date);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                // Update existing record
                $sql = "UPDATE time_records SET regular_hours = ?, overtime_hours = ?, night_differential_hours = ? 
                        WHERE time_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dddi", $regular, $overtime, $night, $existing['time_id']);
                if ($stmt->execute()) $updated_count++;
            } else {
                // Insert new record
                $sql = "INSERT INTO time_records (employee_id, work_date, regular_hours, overtime_hours, night_differential_hours) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddd", $employee_id, $work_date, $regular, $overtime, $night);
                if ($stmt->execute()) $success_count++;
            }
        }
    }
    
    $total_processed = $success_count + $updated_count;
    if ($total_processed > 0) {
        $success = "✅ Successfully processed $total_processed records! ";
        $success .= "($success_count new, $updated_count updated)";
    } else {
        $error = "⚠️ No time records were saved. Please enter hours for at least one employee.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Timekeeping - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .employee-row:hover {
            background-color: #f8f9fa;
        }
        .hours-input {
            width: 90px;
            text-align: center;
            font-weight: bold;
        }
        .bulk-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .quick-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1"><i class="fas fa-users-cog"></i> Bulk Timekeeping Entry</span>
            <div>
                <a href="timekeeping.php" class="btn btn-light"><i class="fas fa-user"></i> Single Entry</a>
                <a href="index.php" class="btn btn-light"><i class="fas fa-home"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="bulk-header text-center">
            <h2><i class="fas fa-rocket"></i> Quick Time Entry for All Employees</h2>
            <p class="lead mb-0">Enter hours for multiple employees at once. Perfect for end-of-day or end-of-week processing.</p>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="row">
                <div class="col-md-8">
                    <strong>Quick Actions:</strong>
                    <button type="button" class="btn btn-sm btn-success" onclick="setAllRegular(8)">
                        <i class="fas fa-play-circle"></i> Set All 8h Regular
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" onclick="setAllRegular(0)">
                        <i class="fas fa-times-circle"></i> Clear All Regular
                    </button>
                    <button type="button" class="btn btn-sm btn-info" onclick="setAllOvertime(2)">
                        <i class="fas fa-clock"></i> Set All 2h OT
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="clearAll()">
                        <i class="fas fa-broom"></i> Clear Everything
                    </button>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary stats-badge">
                        <i class="fas fa-users"></i> 
                        <?php 
                        $active_count = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status='Active'")->fetch_assoc()['total'];
                        echo $active_count . " Active Employees";
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Bulk Time Entry Form</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_save">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label"><strong><i class="fas fa-calendar-alt"></i> Work Date:</strong></label>
                            <input type="date" name="work_date" class="form-control form-control-lg" 
                                   value="<?php echo $default_date; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong><i class="fas fa-info-circle"></i> Instructions:</strong></label>
                            <ul class="list-unstyled small text-muted">
                                <li><i class="fas fa-check text-success"></i> Leave blank for no hours</li>
                                <li><i class="fas fa-sync text-warning"></i> Existing records will be updated</li>
                                <li><i class="fas fa-bolt text-primary"></i> Use quick actions for efficiency</li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Employee</th>
                                    <th>Designation</th>
                                    <th>Regular Hours</th>
                                    <th>Overtime Hours</th>
                                    <th>Night Diff Hours</th>
                                    <th>Daily Rate</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT * FROM employees WHERE status='Active' ORDER BY employee_code";
                                $result = $conn->query($sql);
                                $employee_count = 0;
                                
                                while ($employee = $result->fetch_assoc()):
                                    $employee_count++;
                                    
                                    // Check for existing time record for the default date
                                    $time_sql = "SELECT * FROM time_records WHERE employee_id = ? AND work_date = ?";
                                    $time_stmt = $conn->prepare($time_sql);
                                    $time_stmt->bind_param("is", $employee['employee_id'], $default_date);
                                    $time_stmt->execute();
                                    $time_record = $time_stmt->get_result()->fetch_assoc();
                                    
                                    $has_existing = !empty($time_record);
                                    $row_class = $has_existing ? 'table-warning' : '';
                                ?>
                                <tr class="employee-row <?php echo $row_class; ?>">
                                    <td>
                                        <strong class="text-primary"><?php echo $employee['employee_code']; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $employee['full_name']; ?></small>
                                        <input type="hidden" name="employees[<?php echo $employee['employee_id']; ?>][employee_id]" value="<?php echo $employee['employee_id']; ?>">
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $employee['designation']; ?></span>
                                    </td>
                                    <td>
                                        <input type="number" name="employees[<?php echo $employee['employee_id']; ?>][regular]" 
                                               class="form-control hours-input regular-hours" 
                                               value="<?php echo $time_record['regular_hours'] ?? ''; ?>" 
                                               step="0.5" min="0" max="24" placeholder="8.0"
                                               onchange="updateRowStatus(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="employees[<?php echo $employee['employee_id']; ?>][overtime]" 
                                               class="form-control hours-input overtime-hours" 
                                               value="<?php echo $time_record['overtime_hours'] ?? ''; ?>" 
                                               step="0.5" min="0" max="12" placeholder="0"
                                               onchange="updateRowStatus(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="employees[<?php echo $employee['employee_id']; ?>][night]" 
                                               class="form-control hours-input night-hours" 
                                               value="<?php echo $time_record['night_differential_hours'] ?? ''; ?>" 
                                               step="0.5" min="0" max="8" placeholder="0"
                                               onchange="updateRowStatus(this)">
                                    </td>
                                    <td class="text-success">
                                        <strong>₱<?php echo number_format($employee['base_daily_rate'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($has_existing): ?>
                                            <span class="badge bg-warning" id="status-<?php echo $employee['employee_id']; ?>">
                                                <i class="fas fa-sync"></i> Existing
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark" id="status-<?php echo $employee['employee_id']; ?>">
                                                <i class="fas fa-plus"></i> New
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded">
                        <div class="row">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Save All Time Records
                                </button>
                                <a href="timekeeping.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user"></i> Switch to Single Entry
                                </a>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Processing <?php echo $employee_count; ?> active employees
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Quick action functions
        function setAllRegular(hours) {
            const inputs = document.querySelectorAll('.regular-hours');
            inputs.forEach(input => {
                input.value = hours > 0 ? hours : '';
            });
            updateAllStatus();
            showToast(`Set all regular hours to ${hours}h`, 'success');
        }

        function setAllOvertime(hours) {
            const inputs = document.querySelectorAll('.overtime-hours');
            inputs.forEach(input => {
                input.value = hours > 0 ? hours : '';
            });
            updateAllStatus();
            showToast(`Set all overtime hours to ${hours}h`, 'info');
        }

        function clearAll() {
            document.querySelectorAll('.hours-input').forEach(input => {
                input.value = '';
            });
            updateAllStatus();
            showToast('Cleared all hours', 'warning');
        }

        function updateRowStatus(input) {
            const row = input.closest('tr');
            const employeeId = row.querySelector('input[name*="employee_id"]').value;
            const statusBadge = document.getElementById(`status-${employeeId}`);
            
            const regular = row.querySelector('.regular-hours').value;
            const overtime = row.querySelector('.overtime-hours').value;
            const night = row.querySelector('.night-hours').value;
            
            if (regular || overtime || night) {
                statusBadge.className = 'badge bg-success';
                statusBadge.innerHTML = '<i class="fas fa-edit"></i> Modified';
            } else {
                statusBadge.className = 'badge bg-light text-dark';
                statusBadge.innerHTML = '<i class="fas fa-plus"></i> New';
            }
        }

        function updateAllStatus() {
            document.querySelectorAll('.hours-input').forEach(input => {
                updateRowStatus(input);
            });
        }

        function showToast(message, type = 'info') {
            // Simple notification - you can enhance this with a proper toast library
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 3000);
        }

        // Auto-update status when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateAllStatus();
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>