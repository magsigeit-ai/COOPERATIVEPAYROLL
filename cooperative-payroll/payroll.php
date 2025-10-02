[file name]: payroll.php
[file content begin]
<?php
include 'config/database.php';

// Handle payroll processing
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Check if late_deduction column exists
$check_late_column = $conn->query("SHOW COLUMNS FROM payroll_calculations LIKE 'late_deduction'");
$has_late_column = ($check_late_column->num_rows > 0);

// Process payroll
if ($action == 'process_payroll') {
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];
    $payroll_name = $_POST['payroll_name'] ?: "Payroll " . date('M d', strtotime($pay_period_start)) . " - " . date('M d, Y', strtotime($pay_period_end));
    
    // Check if payroll already exists for this period
    $check_sql = "SELECT COUNT(*) as count FROM payroll_calculations 
                  WHERE pay_period_start = ? AND pay_period_end = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $pay_period_start, $pay_period_end);
    $check_stmt->execute();
    $existing_payroll = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing_payroll['count'] > 0) {
        $error = "⚠️ Payroll already exists for this period! Use a different date range.";
    } else {
        // Get all active employees
        $employees = $conn->query("SELECT * FROM employees WHERE status='Active'");
        $processed_count = 0;
        $error_count = 0;
        
        while ($employee = $employees->fetch_assoc()) {
            $employee_id = $employee['employee_id'];
            
            // Calculate gross income for the period
            $gross_income = calculateEmployeePayroll($employee_id, $pay_period_start, $pay_period_end);
            
            if ($gross_income > 0) {
                // Get employee preferences for deductions
                $pref_sql = "SELECT * FROM employee_preferences WHERE employee_id = ?";
                $pref_stmt = $conn->prepare($pref_sql);
                $pref_stmt->bind_param("i", $employee_id);
                $pref_stmt->execute();
                $preferences = $pref_stmt->get_result()->fetch_assoc();
                
                // Calculate total deductions
                $sss_amount = $preferences['preferred_sss_amount'] ?? 50.00;
                $philhealth_amount = $preferences['preferred_philhealth_amount'] ?? 100.00;
                $pagibig_amount = $preferences['preferred_pagibig_amount'] ?? 100.00;
                
                // Get active loans for this employee
                $loan_sql = "SELECT SUM(monthly_deduction) as total_loan_deduction 
                            FROM employee_loans 
                            WHERE employee_id = ? AND status = 'Active'";
                $loan_stmt = $conn->prepare($loan_sql);
                $loan_stmt->bind_param("i", $employee_id);
                $loan_stmt->execute();
                $loan_deduction = $loan_stmt->get_result()->fetch_assoc()['total_loan_deduction'] ?? 0;
                
                // Calculate late deduction
                $late_deduction = calculateLateDeduction($employee_id, $pay_period_start, $pay_period_end);
                
                $total_deductions = $sss_amount + $philhealth_amount + $pagibig_amount + $loan_deduction + $late_deduction;
                $net_income = $gross_income - $total_deductions;
                
                // Check if payroll_name column exists
                $check_column = $conn->query("SHOW COLUMNS FROM payroll_calculations LIKE 'payroll_name'");
                $has_payroll_name = ($check_column->num_rows > 0);
                
                if ($has_payroll_name && $has_late_column) {
                    // Save with both payroll_name and late_deduction
                    $sql = "INSERT INTO payroll_calculations 
                            (employee_id, pay_period_start, pay_period_end, payroll_name, gross_income, 
                             sss_amount, philhealth_amount, pagibig_amount, emergency_loan, petty_cash, late_deduction,
                             total_deductions, net_income, processed_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isssdddddddddd", $employee_id, $pay_period_start, $pay_period_end, $payroll_name, $gross_income,
                                     $sss_amount, $philhealth_amount, $pagibig_amount, $loan_deduction, 0, $late_deduction,
                                     $total_deductions, $net_income);
                } elseif ($has_payroll_name) {
                    // Save with payroll_name only (include late in total deductions)
                    $sql = "INSERT INTO payroll_calculations 
                            (employee_id, pay_period_start, pay_period_end, payroll_name, gross_income, 
                             sss_amount, philhealth_amount, pagibig_amount, emergency_loan, petty_cash,
                             total_deductions, net_income, processed_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isssddddddddd", $employee_id, $pay_period_start, $pay_period_end, $payroll_name, $gross_income,
                                     $sss_amount, $philhealth_amount, $pagibig_amount, $loan_deduction, 0,
                                     $total_deductions, $net_income);
                } else {
                    // Save without payroll_name
                    $sql = "INSERT INTO payroll_calculations 
                            (employee_id, pay_period_start, pay_period_end, gross_income, 
                             sss_amount, philhealth_amount, pagibig_amount, emergency_loan, petty_cash,
                             total_deductions, net_income, processed_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("issdddddddd", $employee_id, $pay_period_start, $pay_period_end, $gross_income,
                                     $sss_amount, $philhealth_amount, $pagibig_amount, $loan_deduction, 0,
                                     $total_deductions, $net_income);
                }
                
                if ($stmt->execute()) {
                    $processed_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++; // No time records found
            }
        }
        
        if ($processed_count > 0) {
            $success = "✅ Payroll '$payroll_name' processed successfully!<br>";
            $success .= "📊 <strong>$processed_count employees</strong> processed<br>";
            if ($error_count > 0) {
                $success .= "⚠️ <strong>$error_count employees</strong> had no time records";
            }
        } else {
            $error = "❌ No payroll data processed. Check if time records exist for the selected period.";
        }
    }
}

// Quick Process - Common periods
if ($action == 'quick_process') {
    $period_type = $_POST['period_type'];
    
    switch($period_type) {
        case 'last_week':
            $pay_period_start = date('Y-m-d', strtotime('last week monday'));
            $pay_period_end = date('Y-m-d', strtotime('last week sunday'));
            break;
        case 'this_week':
            $pay_period_start = date('Y-m-d', strtotime('monday this week'));
            $pay_period_end = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'last_month':
            $pay_period_start = date('Y-m-01', strtotime('last month'));
            $pay_period_end = date('Y-m-t', strtotime('last month'));
            break;
        case 'this_month':
            $pay_period_start = date('Y-m-01');
            $pay_period_end = date('Y-m-t');
            break;
        default:
            $pay_period_start = date('Y-m-01');
            $pay_period_end = date('Y-m-t');
    }
    
    // Auto-fill the form with these dates
    $auto_fill_dates = [
        'pay_period_start' => $pay_period_start,
        'pay_period_end' => $pay_period_end,
        'payroll_name' => "Payroll " . date('M d', strtotime($pay_period_start)) . " - " . date('M d, Y', strtotime($pay_period_end))
    ];
}

// Delete payroll run
if ($action == 'delete_payroll') {
    $payroll_date = $_POST['payroll_date'];
    $payroll_name = $_POST['payroll_name'] ?? 'Unknown';
    
    $sql = "DELETE FROM payroll_calculations WHERE DATE(processed_at) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $payroll_date);
    
    if ($stmt->execute()) {
        $success = "🗑️ Payroll '$payroll_name' deleted successfully!";
    } else {
        $error = "❌ Error deleting payroll: " . $conn->error;
    }
}

// Function to calculate employee payroll
function calculateEmployeePayroll($employee_id, $start_date, $end_date) {
    global $conn;
    
    $total_gross = 0;
    
    // Get employee details
    $emp_sql = "SELECT * FROM employees WHERE employee_id = ?";
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $employee = $emp_stmt->get_result()->fetch_assoc();
    
    if (!$employee) return 0;
    
    // Get time records for the period
    $time_sql = "SELECT * FROM time_records 
                 WHERE employee_id = ? AND work_date BETWEEN ? AND ? 
                 ORDER BY work_date";
    $time_stmt = $conn->prepare($time_sql);
    $time_stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $time_stmt->execute();
    $time_records = $time_stmt->get_result();
    
    while ($record = $time_records->fetch_assoc()) {
        $daily_pay = calculateDailyPay($employee_id, $record['work_date'], 
                                     $record['regular_hours'], $record['overtime_hours'], 
                                     $record['night_differential_hours']);
        $total_gross += $daily_pay['total_daily_pay'];
    }
    
    return $total_gross;
}

// Function to calculate late deduction
function calculateLateDeduction($employee_id, $start_date, $end_date) {
    global $conn;
    
    $total_late_deduction = 0;
    
    // Check if late_minutes column exists
    $check_late_minutes = $conn->query("SHOW COLUMNS FROM time_records LIKE 'late_minutes'");
    $has_late_minutes = ($check_late_minutes->num_rows > 0);
    
    if (!$has_late_minutes) {
        return 0; // Return 0 if column doesn't exist
    }
    
    // Get time records with late minutes
    $time_sql = "SELECT work_date, late_minutes, absent 
                 FROM time_records 
                 WHERE employee_id = ? AND work_date BETWEEN ? AND ? AND late_minutes > 15";
    $time_stmt = $conn->prepare($time_sql);
    $time_stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $time_stmt->execute();
    $time_records = $time_stmt->get_result();
    
    while ($record = $time_records->fetch_assoc()) {
        // Check if absent column exists
        $absent = isset($record['absent']) ? $record['absent'] : 0;
        
        if ($absent == 0) { // Only calculate for non-absent days
            $effective_late_minutes = $record['late_minutes'] - 15; // 15-minute grace period
            if ($effective_late_minutes > 0) {
                $daily_deduction = $effective_late_minutes * 5; // ₱5 per minute
                // Apply maximum deduction per day (₱500)
                $daily_deduction = min($daily_deduction, 500);
                $total_late_deduction += $daily_deduction;
            }
        }
    }
    
    return $total_late_deduction;
}

// Reuse the calculateDailyPay function
function calculateDailyPay($employee_id, $work_date, $regular_hours, $overtime_hours, $night_hours) {
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
    
    return [
        'basic_pay' => $basic_pay,
        'overtime_pay' => $overtime_pay,
        'night_diff_pay' => $night_diff_pay,
        'total_daily_pay' => $basic_pay + $overtime_pay + $night_diff_pay
    ];
}

// Get filter parameters
$filter_month = $_GET['month'] ?? date('Y-m');
$search_term = $_GET['search'] ?? '';

// Check if payroll_name column exists for queries
$check_column = $conn->query("SHOW COLUMNS FROM payroll_calculations LIKE 'payroll_name'");
$has_payroll_name = ($check_column->num_rows > 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payroll-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .payroll-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .quick-process-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
        }
        .action-buttons .btn {
            margin: 2px;
            font-size: 0.8rem;
        }
        .preview-box {
            background: #e8f5e8;
            border: 2px dashed #28a745;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        .late-deduction-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">💰 Payroll Management</span>
            <a href="index.php" class="btn btn-light">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Quick Process Options -->
        <div class="quick-process-card">
            <h5><i class="fas fa-bolt"></i> Quick Process</h5>
            <p class="mb-3">Process payroll for common periods with one click:</p>
            <form method="POST" class="row g-2">
                <input type="hidden" name="action" value="quick_process">
                <div class="col-md-3">
                    <button type="submit" name="period_type" value="this_week" class="btn btn-light w-100">
                        📅 This Week
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="period_type" value="last_week" class="btn btn-light w-100">
                        📅 Last Week
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="period_type" value="this_month" class="btn btn-light w-100">
                        📅 This Month
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="period_type" value="last_month" class="btn btn-light w-100">
                        📅 Last Month
                    </button>
                </div>
            </form>
        </div>

        <div class="row">
            <!-- Process Payroll Panel -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-play-circle"></i> Process New Payroll</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="payrollForm">
                            <input type="hidden" name="action" value="process_payroll">
                            
                            <?php if ($has_payroll_name): ?>
                            <div class="mb-3">
                                <label class="form-label">Payroll Name:</label>
                                <input type="text" name="payroll_name" class="form-control" 
                                       value="<?php echo $auto_fill_dates['payroll_name'] ?? ''; ?>" 
                                       placeholder="e.g., November 2024 Payroll">
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Start Date:</label>
                                <input type="date" name="pay_period_start" class="form-control" 
                                       value="<?php echo $auto_fill_dates['pay_period_start'] ?? date('Y-m-01'); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">End Date:</label>
                                <input type="date" name="pay_period_end" class="form-control" 
                                       value="<?php echo $auto_fill_dates['pay_period_end'] ?? date('Y-m-t'); ?>" required>
                            </div>

                            <!-- Late Deduction Info -->
                            <div class="late-deduction-info">
                                <h6><i class="fas fa-clock"></i> Late Deduction Policy</h6>
                                <small class="text-muted">
                                    • 15-minute grace period<br>
                                    • ₱5.00 deduction per minute after grace period<br>
                                    • Maximum ₱500.00 deduction per day<br>
                                    • No deduction for absent days
                                </small>
                            </div>

                            <!-- Preview Section -->
                            <div class="preview-box" id="previewSection" style="display: none;">
                                <h6><i class="fas fa-search"></i> Payroll Preview</h6>
                                <div id="previewContent">
                                    <!-- Preview content will be loaded here -->
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="button" onclick="loadPreview()" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Preview First
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-play"></i> Process Payroll
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Payroll Stats</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_payrolls = $conn->query("SELECT COUNT(DISTINCT DATE(processed_at)) as total FROM payroll_calculations")->fetch_assoc()['total'];
                        $this_month_total = $conn->query("SELECT COALESCE(SUM(net_income), 0) as total FROM payroll_calculations WHERE DATE_FORMAT(processed_at, '%Y-%m') = '" . date('Y-m') . "'")->fetch_assoc()['total'];
                        $active_employees = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status='Active'")->fetch_assoc()['total'];
                        $pending_time = $conn->query("SELECT COUNT(DISTINCT employee_id) as total FROM time_records WHERE work_date BETWEEN '" . date('Y-m-01') . "' AND '" . date('Y-m-t') . "'")->fetch_assoc()['total'];
                        
                        // Safe late deduction total calculation
                        if ($has_late_column) {
                            $total_late_deductions = $conn->query("SELECT COALESCE(SUM(late_deduction), 0) as total FROM payroll_calculations WHERE DATE_FORMAT(processed_at, '%Y-%m') = '" . date('Y-m') . "'")->fetch_assoc()['total'];
                        } else {
                            $total_late_deductions = 0;
                        }
                        ?>
                        <div class="stats-card">
                            <div class="text-primary fw-bold"><?php echo $total_payrolls; ?></div>
                            <small>Total Payroll Runs</small>
                        </div>
                        <div class="stats-card">
                            <div class="text-success fw-bold">₱<?php echo number_format($this_month_total, 2); ?></div>
                            <small>This Month's Total</small>
                        </div>
                        <div class="stats-card">
                            <div class="text-warning fw-bold"><?php echo $active_employees; ?></div>
                            <small>Active Employees</small>
                        </div>
                        <div class="stats-card">
                            <div class="text-danger fw-bold">₱<?php echo number_format($total_late_deductions, 2); ?></div>
                            <small>Late Deductions</small>
                        </div>
                    </div>
                </div>

                <!-- Import Data Card -->
                <div class="card mt-3">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-file-import"></i> Import Data</h6>
                    </div>
                    <div class="card-body text-center">
                        <p>Got data from clients? Import it quickly!</p>
                        <a href="import_data.php" class="btn btn-warning w-100">
                            <i class="fas fa-upload"></i> Import Time Records
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payroll History -->
            <div class="col-md-8">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Filter by Month:</label>
                                <input type="month" name="month" class="form-control" value="<?php echo $filter_month; ?>" 
                                       onchange="window.location.href = 'payroll.php?month=' + this.value">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search:</label>
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search payroll..." value="<?php echo $search_term; ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="searchPayrolls()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payroll List -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Payroll History</h5>
                        <span class="badge bg-light text-dark">
                            <?php echo $total_payrolls; ?> Runs
                        </span>
                    </div>
                    <div class="card-body">
                        <?php
                        // Build query based on whether payroll_name exists
                        if ($has_payroll_name) {
                            if ($has_late_column) {
                                $payroll_sql = "SELECT 
                                                DATE(processed_at) as payroll_date,
                                                payroll_name,
                                                COUNT(*) as employee_count,
                                                SUM(gross_income) as total_gross,
                                                SUM(net_income) as total_net,
                                                SUM(late_deduction) as total_late,
                                                MIN(pay_period_start) as period_start,
                                                MAX(pay_period_end) as period_end
                                              FROM payroll_calculations 
                                              WHERE DATE_FORMAT(processed_at, '%Y-%m') = ?
                                              GROUP BY DATE(processed_at), payroll_name
                                              ORDER BY processed_at DESC";
                            } else {
                                $payroll_sql = "SELECT 
                                                DATE(processed_at) as payroll_date,
                                                payroll_name,
                                                COUNT(*) as employee_count,
                                                SUM(gross_income) as total_gross,
                                                SUM(net_income) as total_net,
                                                0 as total_late,
                                                MIN(pay_period_start) as period_start,
                                                MAX(pay_period_end) as period_end
                                              FROM payroll_calculations 
                                              WHERE DATE_FORMAT(processed_at, '%Y-%m') = ?
                                              GROUP BY DATE(processed_at), payroll_name
                                              ORDER BY processed_at DESC";
                            }
                        } else {
                            if ($has_late_column) {
                                $payroll_sql = "SELECT 
                                                DATE(processed_at) as payroll_date,
                                                COUNT(*) as employee_count,
                                                SUM(gross_income) as total_gross,
                                                SUM(net_income) as total_net,
                                                SUM(late_deduction) as total_late,
                                                MIN(pay_period_start) as period_start,
                                                MAX(pay_period_end) as period_end
                                              FROM payroll_calculations 
                                              WHERE DATE_FORMAT(processed_at, '%Y-%m') = ?
                                              GROUP BY DATE(processed_at)
                                              ORDER BY processed_at DESC";
                            } else {
                                $payroll_sql = "SELECT 
                                                DATE(processed_at) as payroll_date,
                                                COUNT(*) as employee_count,
                                                SUM(gross_income) as total_gross,
                                                SUM(net_income) as total_net,
                                                0 as total_late,
                                                MIN(pay_period_start) as period_start,
                                                MAX(pay_period_end) as period_end
                                              FROM payroll_calculations 
                                              WHERE DATE_FORMAT(processed_at, '%Y-%m') = ?
                                              GROUP BY DATE(processed_at)
                                              ORDER BY processed_at DESC";
                            }
                        }
                        
                        $payroll_stmt = $conn->prepare($payroll_sql);
                        $payroll_stmt->bind_param("s", $filter_month);
                        $payroll_stmt->execute();
                        $payroll_runs = $payroll_stmt->get_result();
                        ?>

                        <?php if ($payroll_runs->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($payroll = $payroll_runs->fetch_assoc()): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card payroll-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title text-primary">
                                                    <?php 
                                                    if ($has_payroll_name && !empty($payroll['payroll_name'])) {
                                                        echo $payroll['payroll_name'];
                                                    } else {
                                                        echo 'Payroll ' . date('M d, Y', strtotime($payroll['payroll_date']));
                                                    }
                                                    ?>
                                                </h6>
                                                <span class="badge bg-success">
                                                    <?php echo $payroll['employee_count']; ?> employees
                                                </span>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">Period:</small><br>
                                                <strong><?php echo date('M d', strtotime($payroll['period_start'])); ?> - <?php echo date('M d, Y', strtotime($payroll['period_end'])); ?></strong>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">Total Net Pay:</small>
                                                <div class="fw-bold text-success fs-5">
                                                    ₱<?php echo number_format($payroll['total_net'], 2); ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($payroll['total_late'] > 0): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">Late Deductions:</small>
                                                <div class="fw-bold text-danger">
                                                    ₱<?php echo number_format($payroll['total_late'], 2); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="action-buttons">
                                                <a href="payroll_details.php?date=<?php echo $payroll['payroll_date']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                                <a href="payslips.php?payroll_date=<?php echo $payroll['payroll_date']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-print"></i> Print All
                                                </a>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete('<?php echo $payroll['payroll_date']; ?>', '<?php echo addslashes($payroll['payroll_name'] ?? 'Payroll'); ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>No Payroll Records Found</h5>
                                <p class="text-muted">Process your first payroll using the form on the left.</p>
                            </div>
                        <?php endif; ?>
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
                        <input type="hidden" name="action" value="delete_payroll">
                        <input type="hidden" name="payroll_date" id="delete_payroll_date">
                        <input type="hidden" name="payroll_name" id="delete_payroll_name">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> You are about to delete an entire payroll run.
                        </div>
                        
                        <p>Are you sure you want to delete <strong id="delete_payroll_info"></strong>?</p>
                        <p class="text-danger"><small>This will remove all payroll calculations for this date. This action cannot be undone.</small></p>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteForm').submit()">
                        <i class="fas fa-trash"></i> Delete Payroll
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function searchPayrolls() {
            const searchTerm = document.querySelector('input[name="search"]').value;
            const currentMonth = document.querySelector('input[name="month"]').value;
            let url = 'payroll.php?month=' + currentMonth;
            if (searchTerm) {
                url += '&search=' + encodeURIComponent(searchTerm);
            }
            window.location.href = url;
        }

        // Enter key search
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchPayrolls();
            }
        });

        // Delete confirmation
        function confirmDelete(payrollDate, payrollName) {
            document.getElementById('delete_payroll_date').value = payrollDate;
            document.getElementById('delete_payroll_name').value = payrollName;
            document.getElementById('delete_payroll_info').textContent = payrollName + ' (' + payrollDate + ')';
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Preview functionality
        function loadPreview() {
            const startDate = document.querySelector('input[name="pay_period_start"]').value;
            const endDate = document.querySelector('input[name="pay_period_end"]').value;
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }

            const previewSection = document.getElementById('previewSection');
            const previewContent = document.getElementById('previewContent');
            
            previewContent.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading preview...</div>';
            previewSection.style.display = 'block';

            // Simple AJAX-like preview
            setTimeout(() => {
                previewContent.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        This will process payroll for the period:<br>
                        <strong>${startDate} to ${endDate}</strong><br><br>
                        The system will:
                        <ul class="mb-0">
                            <li>Calculate pay for all active employees</li>
                            <li>Apply SSS, PhilHealth, Pag-IBIG deductions</li>
                            <li>Include active loan deductions</li>
                            <li>Apply late deductions (₱5/min after 15-min grace)</li>
                            <li>Generate individual payroll records</li>
                        </ul>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-play"></i> Process Now
                        </button>
                    </div>
                `;
            }, 1000);
        }

        // Auto-scroll to form after quick process
        <?php if (isset($auto_fill_dates)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('payrollForm').scrollIntoView({ behavior: 'smooth' });
        });
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
[file content end]