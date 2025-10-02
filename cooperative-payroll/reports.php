
<?php
include 'config/database.php';

// Get filter parameters
$report_type = $_GET['type'] ?? 'summary';
$report_month = $_GET['month'] ?? date('Y-m');
$employee_id = $_GET['employee'] ?? '';

// Get summary statistics
$total_employees = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status='Active'")->fetch_assoc()['total'];
$total_payrolls = $conn->query("SELECT COUNT(DISTINCT DATE(processed_at)) as total FROM payroll_calculations")->fetch_assoc()['total'];
$total_net_pay = $conn->query("SELECT COALESCE(SUM(net_income), 0) as total FROM payroll_calculations")->fetch_assoc()['total'];
$this_month_total = $conn->query("SELECT COALESCE(SUM(net_income), 0) as total FROM payroll_calculations WHERE DATE_FORMAT(processed_at, '%Y-%m') = '" . $report_month . "'")->fetch_assoc()['total'];

// Get recent payroll for quick access
$recent_payroll = $conn->query("SELECT MAX(processed_at) as latest FROM payroll_calculations")->fetch_assoc()['latest'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Payslips - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        
        .report-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .quick-action-btn {
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            background: white;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .quick-action-btn:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        .export-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">📊 Reports & Payslips</span>
            <a href="index.php" class="btn btn-light">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card stat-1">
                    <h5>👥 Employees</h5>
                    <h2><?php echo $total_employees; ?></h2>
                    <small>Active Workers</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-2">
                    <h5>💰 Payrolls</h5>
                    <h2><?php echo $total_payrolls; ?></h2>
                    <small>Processed</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-3">
                    <h5>📈 Total Paid</h5>
                    <h2>₱<?php echo number_format($total_net_pay, 2); ?></h2>
                    <small>All Time</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card stat-4">
                    <h5>🕒 This Month</h5>
                    <h2>₱<?php echo number_format($this_month_total, 2); ?></h2>
                    <small>Current Month</small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card report-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="payroll.php" class="text-decoration-none">
                                    <div class="quick-action-btn">
                                        <i class="fas fa-money-bill-wave fa-2x text-primary mb-2"></i>
                                        <h6>Payroll Summary</h6>
                                        <small class="text-muted">View all payroll runs</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="employees.php" class="text-decoration-none">
                                    <div class="quick-action-btn">
                                        <i class="fas fa-users fa-2x text-success mb-2"></i>
                                        <h6>Employee List</h6>
                                        <small class="text-muted">Manage employees</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="payslips.php" class="text-decoration-none">
                                    <div class="quick-action-btn">
                                        <i class="fas fa-print fa-2x text-warning mb-2"></i>
                                        <h6>Print Payslips</h6>
                                        <small class="text-muted">Generate payslips</small>
                                    </div>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="loans.php" class="text-decoration-none">
                                    <div class="quick-action-btn">
                                        <i class="fas fa-hand-holding-usd fa-2x text-info mb-2"></i>
                                        <h6>Loan Reports</h6>
                                        <small class="text-muted">View loan status</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Navigation -->
        <div class="row mt-4">
            <div class="col-md-12">
                <ul class="nav nav-pills mb-3" id="reportsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="payslips-tab" data-bs-toggle="tab" data-bs-target="#payslips" type="button" role="tab">
                            <i class="fas fa-print"></i> Payslips
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                            <i class="fas fa-chart-bar"></i> Payroll Reports
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="employee-tab" data-bs-toggle="tab" data-bs-target="#employee" type="button" role="tab">
                            <i class="fas fa-users"></i> Employee Reports
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="reportsTabContent">
                    <!-- Payslips Tab -->
                    <div class="tab-pane fade show active" id="payslips" role="tabpanel">
                        <div class="card report-card">
                            <div class="card-body">
                                <h5><i class="fas fa-print"></i> Payslip Generation</h5>
                                <p class="text-muted">Generate and print employee payslips</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-user fa-3x text-primary mb-3"></i>
                                                <h6>Individual Payslip</h6>
                                                <p class="text-muted">Print payslip for one employee</p>
                                                <form method="GET" action="print_payslip.php" target="_blank" class="row g-2">
                                                    <div class="col-md-8">
                                                        <select name="employee_id" class="form-select" required>
                                                            <option value="">Select Employee</option>
                                                            <?php
                                                            $employees = $conn->query("SELECT * FROM employees WHERE status='Active' ORDER BY employee_code");
                                                            while ($emp = $employees->fetch_assoc()) {
                                                                echo "<option value='{$emp['employee_id']}'>{$emp['employee_code']} - {$emp['full_name']}</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="submit" class="btn btn-primary w-100">
                                                            <i class="fas fa-print"></i> Print
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-users fa-3x text-success mb-3"></i>
                                                <h6>Bulk Payslips</h6>
                                                <p class="text-muted">Print all payslips for a payroll run</p>
                                                <form method="GET" action="payslips.php" target="_blank" class="row g-2">
                                                    <div class="col-md-8">
                                                        <select name="payroll_date" class="form-select" required>
                                                            <option value="">Select Payroll Date</option>
                                                            <?php
                                                            $payroll_dates = $conn->query("
                                                                SELECT DISTINCT DATE(processed_at) as payroll_date 
                                                                FROM payroll_calculations 
                                                                ORDER BY processed_at DESC 
                                                                LIMIT 10
                                                            ");
                                                            while ($date = $payroll_dates->fetch_assoc()) {
                                                                echo "<option value='{$date['payroll_date']}'>" . date('M d, Y', strtotime($date['payroll_date'])) . "</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="submit" class="btn btn-success w-100">
                                                            <i class="fas fa-print"></i> Print All
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Reports Tab -->
                    <div class="tab-pane fade" id="payroll" role="tabpanel">
                        <div class="card report-card">
                            <div class="card-body">
                                <h5><i class="fas fa-chart-bar"></i> Payroll Reports</h5>
                                <p class="text-muted">Comprehensive payroll analysis and reports</p>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <a href="payroll.php" class="text-decoration-none">
                                            <div class="quick-action-btn h-100">
                                                <i class="fas fa-list fa-2x text-primary mb-2"></i>
                                                <h6>Payroll History</h6>
                                                <small class="text-muted">View all payroll runs with details</small>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="export_excel.php?type=payroll&period=<?php echo date('Y-m'); ?>" class="text-decoration-none">
                                            <div class="quick-action-btn h-100">
                                                <i class="fas fa-file-excel fa-2x text-success mb-2"></i>
                                                <h6>Export to Excel</h6>
                                                <small class="text-muted">Download payroll data as Excel</small>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="payroll_details.php?date=<?php echo date('Y-m-d'); ?>" class="text-decoration-none">
                                            <div class="quick-action-btn h-100">
                                                <i class="fas fa-search fa-2x text-info mb-2"></i>
                                                <h6>Detailed View</h6>
                                                <small class="text-muted">Detailed payroll breakdown</small>
                                            </div>
                                        </a>
                                    </div>
                                </div>

                                <!-- Recent Payroll Summary -->
                                <div class="mt-4">
                                    <h6>Recent Payroll Activity</h6>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Employees</th>
                                                    <th>Total Net</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $recent_payrolls = $conn->query("
                                                    SELECT 
                                                        DATE(processed_at) as payroll_date,
                                                        COUNT(*) as employee_count,
                                                        SUM(net_income) as total_net
                                                    FROM payroll_calculations 
                                                    GROUP BY DATE(processed_at)
                                                    ORDER BY processed_at DESC 
                                                    LIMIT 5
                                                ");
                                                
                                                if ($recent_payrolls->num_rows > 0) {
                                                    while ($payroll = $recent_payrolls->fetch_assoc()) {
                                                        echo "<tr>";
                                                        echo "<td>" . date('M d, Y', strtotime($payroll['payroll_date'])) . "</td>";
                                                        echo "<td><span class='badge bg-secondary'>{$payroll['employee_count']}</span></td>";
                                                        echo "<td class='text-success fw-bold'>₱" . number_format($payroll['total_net'], 2) . "</td>";
                                                        echo "<td>
                                                                <a href='payroll_details.php?date={$payroll['payroll_date']}' class='btn btn-sm btn-outline-primary'>
                                                                    <i class='fas fa-eye'></i> View
                                                                </a>
                                                              </td>";
                                                        echo "</tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='4' class='text-center'>No payroll records found</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Reports Tab -->
                    <div class="tab-pane fade" id="employee" role="tabpanel">
                        <div class="card report-card">
                            <div class="card-body">
                                <h5><i class="fas fa-users"></i> Employee Reports</h5>
                                <p class="text-muted">Employee management and reporting</p>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <a href="employees.php" class="text-decoration-none">
                                            <div class="quick-action-btn h-100">
                                                <i class="fas fa-list fa-2x text-primary mb-2"></i>
                                                <h6>Employee Master List</h6>
                                                <small class="text-muted">View and manage all employees</small>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="export_excel.php?type=employees" class="text-decoration-none">
                                            <div class="quick-action-btn h-100">
                                                <i class="fas fa-file-excel fa-2x text-success mb-2"></i>
                                                <h6>Export Employee Data</h6>
                                                <small class="text-muted">Download employee list as Excel</small>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="timekeeping.php" class="text-decoration-none">
                                            <div class="quick-action-btn h-100">
                                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                                <h6>Timekeeping Report</h6>
                                                <small class="text-muted">View attendance and hours</small>
                                            </div>
                                        </a>
                                    </div>
                                </div>

                                <!-- Employee Summary -->
                                <div class="mt-4">
                                    <h6>Employee Summary</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6>By Designation</h6>
                                                    <?php
                                                    $designations = $conn->query("
                                                        SELECT designation, COUNT(*) as count 
                                                        FROM employees 
                                                        WHERE status='Active' 
                                                        GROUP BY designation
                                                    ");
                                                    while ($dept = $designations->fetch_assoc()) {
                                                        echo "<div class='d-flex justify-content-between mb-1'>
                                                                <span>{$dept['designation']}</span>
                                                                <span class='badge bg-primary'>{$dept['count']}</span>
                                                              </div>";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <h6>By Category</h6>
                                                    <?php
                                                    $categories = $conn->query("
                                                        SELECT category, COUNT(*) as count 
                                                        FROM employees 
                                                        WHERE status='Active' 
                                                        GROUP BY category
                                                    ");
                                                    while ($cat = $categories->fetch_assoc()) {
                                                        echo "<div class='d-flex justify-content-between mb-1'>
                                                                <span>{$cat['category']}</span>
                                                                <span class='badge bg-success'>{$cat['count']}</span>
                                                              </div>";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Data Section -->
        <div class="export-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4><i class="fas fa-file-export"></i> Export Data</h4>
                    <p class="mb-0">Download your data in Excel format for external analysis or record keeping.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="export_excel.php?type=employees" class="btn btn-light">
                            <i class="fas fa-users"></i> Employees
                        </a>
                        <a href="export_excel.php?type=payroll&period=<?php echo date('Y-m'); ?>" class="btn btn-light">
                            <i class="fas fa-money-bill-wave"></i> Payroll
                        </a>
                        <a href="export_excel.php?type=loans" class="btn btn-light">
                            <i class="fas fa-hand-holding-usd"></i> Loans
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
