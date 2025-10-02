<?php
// index.php - SIMPLIFIED DASHBOARD
include 'config/database.php';

// Get essential stats for accounting team
$total_employees = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status='Active'")->fetch_assoc()['total'];
$today_records = $conn->query("SELECT COUNT(*) as total FROM time_records WHERE DATE(work_date) = CURDATE()")->fetch_assoc()['total'];
$monthly_payroll = $conn->query("SELECT COALESCE(SUM(net_income), 0) as total FROM payroll_calculations WHERE MONTH(processed_at) = MONTH(CURDATE())")->fetch_assoc()['total'];

// Get recent payroll activity
$recent_payrolls = $conn->query("
    SELECT pc.processed_at, pc.pay_period_start, pc.pay_period_end, 
           COUNT(pc.employee_id) as employee_count,
           SUM(pc.net_income) as total_net
    FROM payroll_calculations pc
    WHERE pc.processed_at IS NOT NULL
    GROUP BY DATE(pc.processed_at)
    ORDER BY pc.processed_at DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Dashboard - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            text-align: center;
        }
        .stat-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        
        .action-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }
        
        .recent-activity {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 25px 0;
            margin-bottom: 30px;
            border-radius: 0 0 15px 15px;
        }
    </style>
</head>
<body>
    <!-- Simplified Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-tachometer-alt"></i> Payroll Dashboard</h1>
                    <p class="lead mb-0">Efficient payroll management for accounting team</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <?php echo date('F d, Y'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Key Metrics - Simplified -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card stat-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $total_employees; ?></h3>
                            <p class="mb-0">Active Workers</p>
                        </div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card stat-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $today_records; ?></h3>
                            <p class="mb-0">Today's Records</p>
                        </div>
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card stat-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3>₱<?php echo number_format($monthly_payroll, 2); ?></h3>
                            <p class="mb-0">This Month's Payroll</p>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Actions - Clean & Focused -->
        <div class="row mt-4">
            <div class="col-md-12">
                <h4 class="mb-3">Quick Actions</h4>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="timekeeping.php" class="text-decoration-none">
                            <div class="action-card">
                                <i class="fas fa-clock fa-3x text-primary mb-3"></i>
                                <h5>Enter Time</h5>
                                <p class="text-muted">Record work hours</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="payroll.php" class="text-decoration-none">
                            <div class="action-card">
                                <i class="fas fa-calculator fa-3x text-success mb-3"></i>
                                <h5>Process Payroll</h5>
                                <p class="text-muted">Run payroll calculations</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="employees.php" class="text-decoration-none">
                            <div class="action-card">
                                <i class="fas fa-list fa-3x text-warning mb-3"></i>
                                <h5>Worker List</h5>
                                <p class="text-muted">Manage employees</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports.php" class="text-decoration-none">
                            <div class="action-card">
                                <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                                <h5>View Reports</h5>
                                <p class="text-muted">Generate reports & payslips</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payroll Activity - Essential Only -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> Recent Payroll Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_payrolls->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Payroll Date</th>
                                            <th>Pay Period</th>
                                            <th>Employees</th>
                                            <th>Total Net Pay</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($payroll = $recent_payrolls->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M d, Y', strtotime($payroll['processed_at'])); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo date('M d', strtotime($payroll['pay_period_start'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($payroll['pay_period_end'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $payroll['employee_count']; ?> employees</span>
                                            </td>
                                            <td class="text-success fw-bold">
                                                ₱<?php echo number_format($payroll['total_net'], 2); ?>
                                            </td>
                                            <td>
                                                <a href="payslips.php?payroll_date=<?php echo date('Y-m-d', strtotime($payroll['processed_at'])); ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View Payslips
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5>No Payroll History</h5>
                                <p class="text-muted">Process your first payroll to see activity here.</p>
                                <a href="payroll.php" class="btn btn-primary">Process Payroll</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>