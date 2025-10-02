
<?php
include 'config/database.php';

// Handle form submissions
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Add new time record
if ($action == 'add_time_record') {
    $employee_id = $_POST['employee_id'];
    $work_date = $_POST['work_date'];
    $regular_hours = $_POST['regular_hours'];
    $overtime_hours = $_POST['overtime_hours'];
    $night_hours = $_POST['night_hours'];
    $late_minutes = $_POST['late_minutes'] ?? 0;
    $absent = isset($_POST['absent']) ? 1 : 0;
    
    // If absent, set hours to 0
    if ($absent) {
        $regular_hours = 0;
        $overtime_hours = 0;
        $night_hours = 0;
        $late_minutes = 0;
    }
    
    // Check if record already exists for this employee and date
    $check_sql = "SELECT time_id FROM time_records WHERE employee_id = ? AND work_date = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $employee_id, $work_date);
    $check_stmt->execute();
    $existing_record = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing_record) {
        // Update existing record
        $sql = "UPDATE time_records SET regular_hours = ?, overtime_hours = ?, night_differential_hours = ?, late_minutes = ?, absent = ? WHERE time_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddddii", $regular_hours, $overtime_hours, $night_hours, $late_minutes, $absent, $existing_record['time_id']);
        
        if ($stmt->execute()) {
            $success = "🔄 Time record updated successfully!";
        } else {
            $error = "❌ Error updating time record: " . $conn->error;
        }
    } else {
        // Insert new record
        $sql = "INSERT INTO time_records (employee_id, work_date, regular_hours, overtime_hours, night_differential_hours, late_minutes, absent) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isddddi", $employee_id, $work_date, $regular_hours, $overtime_hours, $night_hours, $late_minutes, $absent);
        
        if ($stmt->execute()) {
            $success = "✅ Time record saved successfully!";
        } else {
            $error = "❌ Error saving time record: " . $conn->error;
        }
    }
}

// Delete time record
if ($action == 'delete_time_record') {
    $time_id = $_POST['time_id'];
    
    $sql = "DELETE FROM time_records WHERE time_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $time_id);
    
    if ($stmt->execute()) {
        $success = "🗑️ Time record deleted successfully!";
    } else {
        $error = "❌ Error deleting time record: " . $conn->error;
    }
}

// Quick action: Set default hours for all active employees
if ($action == 'quick_fill_all') {
    $work_date = $_POST['work_date'];
    $regular_hours = $_POST['regular_hours'];
    
    $employees_sql = "SELECT employee_id FROM employees WHERE status = 'Active'";
    $employees_result = $conn->query($employees_sql);
    
    $processed = 0;
    $updated = 0;
    
    while ($employee = $employees_result->fetch_assoc()) {
        // Check if record exists
        $check_sql = "SELECT time_id FROM time_records WHERE employee_id = ? AND work_date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $employee['employee_id'], $work_date);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing
            $update_sql = "UPDATE time_records SET regular_hours = ? WHERE time_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $regular_hours, $existing['time_id']);
            if ($update_stmt->execute()) $updated++;
        } else {
            // Insert new
            $insert_sql = "INSERT INTO time_records (employee_id, work_date, regular_hours) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isd", $employee['employee_id'], $work_date, $regular_hours);
            if ($insert_stmt->execute()) $processed++;
        }
    }
    
    $success = "⚡ Quick fill completed! $processed new records, $updated records updated.";
}

// Calculate daily pay for display
function calculateDailyPay($employee_id, $work_date, $regular_hours, $overtime_hours, $night_hours, $late_minutes = 0) {
    global $conn;
    
    // Get employee details
    $emp_sql = "SELECT * FROM employees WHERE employee_id = ?";
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $employee = $emp_stmt->get_result()->fetch_assoc();
    
    if (!$employee) return null;
    
    // Get day rate multiplier
    $day_of_week = date('l', strtotime($work_date));
    $rate_sql = "SELECT * FROM daily_rates WHERE day_of_week = ?";
    $rate_stmt = $conn->prepare($rate_sql);
    $rate_stmt->bind_param("s", $day_of_week);
    $rate_stmt->execute();
    $day_rate = $rate_stmt->get_result()->fetch_assoc();
    
    if (!$day_rate) return null;
    
    $base_rate = $employee['base_daily_rate'];
    
    // Calculate basic pay (pro-rated for partial days)
    $basic_pay = $base_rate * $day_rate['multiplier'] * ($regular_hours / 8);
    
    // Calculate overtime
    $overtime_pay = $overtime_hours * $day_rate['overtime_hourly_rate'];
    
    // Calculate night differential (10% of basic + overtime)
    $night_diff_pay = ($basic_pay + $overtime_pay) * 0.10 * $night_hours;
    
    // Calculate late deduction (₱5 per minute after 15-minute grace period)
    $effective_late_minutes = max(0, $late_minutes - 15);
    $late_deduction = $effective_late_minutes * 5; // ₱5 per minute
    
    $total_daily_pay = $basic_pay + $overtime_pay + $night_diff_pay - $late_deduction;
    
    return [
        'basic_pay' => $basic_pay,
        'overtime_pay' => $overtime_pay,
        'night_diff_pay' => $night_diff_pay,
        'late_deduction' => $late_deduction,
        'total_daily_pay' => $total_daily_pay,
        'day_type' => $day_of_week,
        'multiplier' => $day_rate['multiplier']
    ];
}

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_employee = $_GET['employee'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timekeeping - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .preview-box {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        .calculation-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .time-record-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }
        .time-record-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .quick-actions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-badge {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .hours-badge {
            font-size: 0.9em;
            font-weight: bold;
        }
        .late-badge {
            background: #dc3545;
            color: white;
        }
        .absent-badge {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">⏰ Timekeeping System</span>
            <div>
                <a href="bulk_timekeeping.php" class="btn btn-light me-2">
                    <i class="fas fa-users"></i> Bulk Entry
                </a>
                <a href="index.php" class="btn btn-light">← Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Success/Error Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-2"><i class="fas fa-bolt"></i> Quick Actions</h5>
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="action" value="quick_fill_all">
                        <div class="col-auto">
                            <input type="date" name="work_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-auto">
                            <select name="regular_hours" class="form-select">
                                <option value="8">8 Hours (Full Day)</option>
                                <option value="4">4 Hours (Half Day)</option>
                                <option value="0">0 Hours (Absent)</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-magic"></i> Fill All Employees
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <?php
                    $today_count = $conn->query("SELECT COUNT(*) as total FROM time_records WHERE work_date = CURDATE()")->fetch_assoc()['total'];
                    $active_employees = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status='Active'")->fetch_assoc()['total'];
                    $today_late = $conn->query("SELECT COUNT(*) as total FROM time_records WHERE work_date = CURDATE() AND late_minutes > 15")->fetch_assoc()['total'];
                    ?>
                    <span class="badge bg-light text-dark stats-badge me-1">
                        <i class="fas fa-clock"></i> Today: <?php echo $today_count; ?>/<?php echo $active_employees; ?>
                    </span>
                    <span class="badge late-badge stats-badge">
                        <i class="fas fa-running"></i> Late: <?php echo $today_late; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Time Entry Form -->
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📝 Quick Time Entry</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="timeForm">
                            <input type="hidden" name="action" value="add_time_record">
                            
                            <div class="mb-3">
                                <label class="form-label">Employee:</label>
                                <select name="employee_id" class="form-select" id="employeeSelect" required>
                                    <option value="">Select Employee</option>
                                    <?php
                                    $result = $conn->query("SELECT * FROM employees WHERE status='Active' ORDER BY employee_code");
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='{$row['employee_id']}' data-rate='{$row['base_daily_rate']}'>
                                                {$row['employee_code']} - {$row['full_name']} (₱{$row['base_daily_rate']}/day)
                                              </option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Work Date:</label>
                                <input type="date" name="work_date" class="form-control" id="workDate" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Regular Hours:</label>
                                    <input type="number" name="regular_hours" class="form-control" id="regularHours" value="8" step="0.5" min="0" max="24" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Overtime Hours:</label>
                                    <input type="number" name="overtime_hours" class="form-control" id="overtimeHours" value="0" step="0.5" min="0" max="12">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Night Diff Hours:</label>
                                    <input type="number" name="night_hours" class="form-control" id="nightHours" value="0" step="0.5" min="0" max="8">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Late Minutes:</label>
                                    <input type="number" name="late_minutes" class="form-control" id="lateMinutes" value="0" min="0" max="480" placeholder="0">
                                    <small class="text-muted">Minutes late to work</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="absent" id="absentCheck" onchange="toggleAbsent()">
                                        <label class="form-check-label text-danger" for="absentCheck">
                                            <strong>Mark as Absent</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Time Record
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Pay Preview -->
                <div class="preview-box" id="payPreview" style="display: none;">
                    <h6>💰 Daily Pay Calculation</h6>
                    <div id="calculationDetails">
                        <!-- Calculation will appear here -->
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Today's Summary</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $today_stats = $conn->query("
                            SELECT 
                                COUNT(*) as total_records,
                                SUM(regular_hours) as total_regular,
                                SUM(overtime_hours) as total_overtime,
                                SUM(night_differential_hours) as total_night,
                                SUM(late_minutes) as total_late_minutes,
                                SUM(CASE WHEN late_minutes > 15 THEN 1 ELSE 0 END) as late_employees
                            FROM time_records 
                            WHERE work_date = CURDATE()
                        ")->fetch_assoc();
                        ?>
                        <div class="row text-center">
                            <div class="col-4 mb-3">
                                <div class="text-primary fw-bold"><?php echo $today_stats['total_records']; ?></div>
                                <small>Records Today</small>
                            </div>
                            <div class="col-4 mb-3">
                                <div class="text-success fw-bold"><?php echo number_format($today_stats['total_regular'], 1); ?>h</div>
                                <small>Regular Hours</small>
                            </div>
                            <div class="col-4 mb-3">
                                <div class="text-warning fw-bold"><?php echo $today_stats['late_employees']; ?></div>
                                <small>Late Employees</small>
                            </div>
                            <div class="col-6">
                                <div class="text-warning fw-bold"><?php echo number_format($today_stats['total_overtime'], 1); ?>h</div>
                                <small>Overtime Hours</small>
                            </div>
                            <div class="col-6">
                                <div class="text-info fw-bold"><?php echo number_format($today_stats['total_night'], 1); ?>h</div>
                                <small>Night Hours</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Time Records with Management -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📋 Recent Time Records</h5>
                        <div>
                            <span class="badge bg-light text-dark">
                                Last 30 days
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Filter by Date:</label>
                                <input type="date" class="form-control" id="filterDate" value="<?php echo $filter_date; ?>" 
                                       onchange="window.location.href = 'timekeeping.php?date=' + this.value">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Filter by Employee:</label>
                                <select class="form-select" id="filterEmployee" onchange="filterRecords()">
                                    <option value="">All Employees</option>
                                    <?php
                                    $employees = $conn->query("SELECT * FROM employees WHERE status='Active' ORDER BY employee_code");
                                    while ($emp = $employees->fetch_assoc()) {
                                        $selected = $emp['employee_id'] == $filter_employee ? 'selected' : '';
                                        echo "<option value='{$emp['employee_id']}' $selected>{$emp['employee_code']} - {$emp['full_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Regular</th>
                                        <th>OT</th>
                                        <th>Night</th>
                                        <th>Late</th>
                                        <th>Total Pay</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recordsTable">
                                    <?php
                                    // Build query with filters
                                    $sql = "SELECT tr.*, e.employee_code, e.full_name, e.base_daily_rate 
                                            FROM time_records tr 
                                            JOIN employees e ON tr.employee_id = e.employee_id 
                                            WHERE tr.work_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                                    
                                    if (!empty($filter_date)) {
                                        $sql .= " AND tr.work_date = '$filter_date'";
                                    }
                                    if (!empty($filter_employee)) {
                                        $sql .= " AND tr.employee_id = '$filter_employee'";
                                    }
                                    
                                    $sql .= " ORDER BY tr.work_date DESC, tr.created_at DESC";
                                    
                                    $result = $conn->query($sql);
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $pay_calc = calculateDailyPay($row['employee_id'], $row['work_date'], $row['regular_hours'], $row['overtime_hours'], $row['night_differential_hours'], $row['late_minutes']);
                                            $total_pay = $pay_calc ? $pay_calc['total_daily_pay'] : 0;
                                            
                                            echo "<tr>";
                                            echo "<td><small>" . date('M d', strtotime($row['work_date'])) . "</small><br><strong>" . date('D', strtotime($row['work_date'])) . "</strong></td>";
                                            echo "<td><strong>{$row['employee_code']}</strong><br><small>{$row['full_name']}</small></td>";
                                            
                                            // Regular hours with absent indicator
                                            if ($row['absent']) {
                                                echo "<td><span class='badge absent-badge hours-badge'>ABSENT</span></td>";
                                            } else {
                                                echo "<td><span class='badge bg-success hours-badge'>{$row['regular_hours']}h</span></td>";
                                            }
                                            
                                            echo "<td>" . ($row['overtime_hours'] > 0 ? "<span class='badge bg-warning hours-badge'>{$row['overtime_hours']}h</span>" : "-") . "</td>";
                                            echo "<td>" . ($row['night_differential_hours'] > 0 ? "<span class='badge bg-info hours-badge'>{$row['night_differential_hours']}h</span>" : "-") . "</td>";
                                            
                                            // Late minutes
                                            if ($row['late_minutes'] > 0) {
                                                $effective_late = max(0, $row['late_minutes'] - 15);
                                                $late_deduction = $effective_late * 5;
                                                echo "<td><span class='badge late-badge hours-badge' title='Deduction: ₱{$late_deduction}'>{$row['late_minutes']}m</span></td>";
                                            } else {
                                                echo "<td>-</td>";
                                            }
                                            
                                            echo "<td class='text-success fw-bold'>₱" . number_format($total_pay, 2) . "</td>";
                                            echo "<td>
                                                    <div class='btn-group btn-group-sm'>
                                                        <button class='btn btn-outline-warning' onclick='editRecord({$row['time_id']}, {$row['employee_id']}, \"{$row['work_date']}\", {$row['regular_hours']}, {$row['overtime_hours']}, {$row['night_differential_hours']}, {$row['late_minutes']}, {$row['absent']})' title='Edit'>
                                                            <i class='fas fa-edit'></i>
                                                        </button>
                                                        <button class='btn btn-outline-danger' onclick='confirmDelete({$row['time_id']}, \"{$row['employee_code']}\", \"{$row['work_date']}\")' title='Delete'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center py-4'>
                                                <i class='fas fa-inbox fa-2x text-muted mb-3'></i><br>
                                                No time records found
                                              </td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_time_record">
                        <input type="hidden" name="time_id" id="delete_time_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> You are about to delete a time record.
                        </div>
                        
                        <p>Are you sure you want to delete the time record for <strong id="delete_employee_info"></strong>?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteForm').submit()">
                        <i class="fas fa-trash"></i> Delete Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Real-time calculation preview
        function updatePayPreview() {
            const employeeSelect = document.getElementById('employeeSelect');
            const workDate = document.getElementById('workDate').value;
            const regularHours = parseFloat(document.getElementById('regularHours').value) || 0;
            const overtimeHours = parseFloat(document.getElementById('overtimeHours').value) || 0;
            const nightHours = parseFloat(document.getElementById('nightHours').value) || 0;
            const lateMinutes = parseInt(document.getElementById('lateMinutes').value) || 0;
            const absentCheck = document.getElementById('absentCheck').checked;
            
            if (!employeeSelect.value || !workDate) {
                document.getElementById('payPreview').style.display = 'none';
                return;
            }
            
            // If absent, show different message
            if (absentCheck) {
                const preview = document.getElementById('payPreview');
                const details = document.getElementById('calculationDetails');
                
                details.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Employee marked as ABSENT</strong><br>
                        No pay will be calculated for this day.
                    </div>
                `;
                preview.style.display = 'block';
                return;
            }
            
            // Simple client-side calculation for preview
            const baseRate = parseFloat(employeeSelect.selectedOptions[0].getAttribute('data-rate'));
            const dayOfWeek = new Date(workDate).getDay();
            const isSunday = (dayOfWeek === 0);
            
            const multiplier = isSunday ? 1.3 : 1.0;
            const otRate = isSunday ? 102.58 : 78.91;
            
            const basicPay = baseRate * multiplier * (regularHours / 8);
            const overtimePay = overtimeHours * otRate;
            const nightDiffPay = (basicPay + overtimePay) * 0.10 * nightHours;
            
            // Late deduction calculation (₱5 per minute after 15-minute grace period)
            const effectiveLateMinutes = Math.max(0, lateMinutes - 15);
            const lateDeduction = effectiveLateMinutes * 5;
            
            const totalPay = basicPay + overtimePay + nightDiffPay - lateDeduction;
            
            const preview = document.getElementById('payPreview');
            const details = document.getElementById('calculationDetails');
            
            details.innerHTML = `
                <div class="calculation-item">
                    <span>Basic Pay (${isSunday ? 'Sunday' : 'Regular'}):</span>
                    <span>₱${basicPay.toFixed(2)}</span>
                </div>
                <div class="calculation-item">
                    <span>Overtime Pay:</span>
                    <span>₱${overtimePay.toFixed(2)}</span>
                </div>
                <div class="calculation-item">
                    <span>Night Differential:</span>
                    <span>₱${nightDiffPay.toFixed(2)}</span>
                </div>
                ${lateDeduction > 0 ? `
                <div class="calculation-item text-danger">
                    <span>Late Deduction (${effectiveLateMinutes} mins):</span>
                    <span>-₱${lateDeduction.toFixed(2)}</span>
                </div>
                ` : ''}
                <hr>
                <div class="calculation-item" style="font-weight: bold;">
                    <span>Total Daily Pay:</span>
                    <span>₱${totalPay.toFixed(2)}</span>
                </div>
                ${lateDeduction > 0 ? `
                <small class="text-muted">* 15-minute grace period applied</small>
                ` : ''}
            `;
            
            preview.style.display = 'block';
        }
        
        // Toggle absent functionality
        function toggleAbsent() {
            const absentCheck = document.getElementById('absentCheck');
            const regularHours = document.getElementById('regularHours');
            const overtimeHours = document.getElementById('overtimeHours');
            const nightHours = document.getElementById('nightHours');
            const lateMinutes = document.getElementById('lateMinutes');
            
            if (absentCheck.checked) {
                regularHours.value = '0';
                overtimeHours.value = '0';
                nightHours.value = '0';
                lateMinutes.value = '0';
                regularHours.disabled = true;
                overtimeHours.disabled = true;
                nightHours.disabled = true;
                lateMinutes.disabled = true;
            } else {
                regularHours.disabled = false;
                overtimeHours.disabled = false;
                nightHours.disabled = false;
                lateMinutes.disabled = false;
                if (regularHours.value === '0') regularHours.value = '8';
            }
            updatePayPreview();
        }
        
        // Delete record confirmation
        function confirmDelete(timeId, employeeCode, workDate) {
            document.getElementById('delete_time_id').value = timeId;
            document.getElementById('delete_employee_info').textContent = employeeCode + ' on ' + workDate;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Edit record - populate form
        function editRecord(timeId, employeeId, workDate, regularHours, overtimeHours, nightHours, lateMinutes, absent) {
            document.getElementById('employeeSelect').value = employeeId;
            document.getElementById('workDate').value = workDate;
            document.getElementById('regularHours').value = regularHours;
            document.getElementById('overtimeHours').value = overtimeHours;
            document.getElementById('nightHours').value = nightHours;
            document.getElementById('lateMinutes').value = lateMinutes;
            document.getElementById('absentCheck').checked = absent;
            
            // Update disabled state based on absent
            toggleAbsent();
            
            // Update preview
            updatePayPreview();
            
            // Scroll to form
            document.getElementById('timeForm').scrollIntoView({ behavior: 'smooth' });
            
            // Show message
            showToast('Record loaded for editing. Update values and click Save.', 'info');
        }
        
        // Filter records
        function filterRecords() {
            const employeeId = document.getElementById('filterEmployee').value;
            const date = document.getElementById('filterDate').value;
            
            let url = 'timekeeping.php?date=' + date;
            if (employeeId) {
                url += '&employee=' + employeeId;
            }
            window.location.href = url;
        }
        
        // Simple toast notification
        function showToast(message, type = 'info') {
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
            }, 4000);
        }
        
        // Add event listeners
        document.getElementById('employeeSelect').addEventListener('change', updatePayPreview);
        document.getElementById('workDate').addEventListener('change', updatePayPreview);
        document.getElementById('regularHours').addEventListener('input', updatePayPreview);
        document.getElementById('overtimeHours').addEventListener('input', updatePayPreview);
        document.getElementById('nightHours').addEventListener('input', updatePayPreview);
        document.getElementById('lateMinutes').addEventListener('input', updatePayPreview);
        document.getElementById('absentCheck').addEventListener('change', updatePayPreview);
        
        // Initial calculation
        updatePayPreview();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
