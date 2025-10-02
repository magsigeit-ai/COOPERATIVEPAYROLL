
<?php
include 'config/database.php';

// Get payroll date from URL
$payroll_date = $_GET['payroll_date'] ?? '';

// If no specific date, show selection interface
if (!$payroll_date) {
    // Get available payroll dates
    $payroll_dates = $conn->query("
        SELECT DISTINCT DATE(processed_at) as payroll_date 
        FROM payroll_calculations 
        ORDER BY processed_at DESC 
        LIMIT 10
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Printing - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .selection-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .selection-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .print-option {
            padding: 30px;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            margin: 10px 0;
            transition: all 0.3s;
        }
        .print-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">🖨️ Payslip Printing</span>
            <a href="reports.php" class="btn btn-light">← Back to Reports</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!$payroll_date): ?>
        <!-- Selection Interface -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card selection-card">
                    <div class="card-header bg-success text-white text-center">
                        <h4 class="mb-0"><i class="fas fa-print"></i> Select Printing Option</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="print-option">
                                    <i class="fas fa-user fa-3x text-primary mb-3"></i>
                                    <h5>Individual Payslip</h5>
                                    <p class="text-muted">Print payslip for one employee</p>
                                    <a href="print_payslip.php" class="btn btn-primary">
                                        <i class="fas fa-print"></i> Select Employee
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="print-option">
                                    <i class="fas fa-users fa-3x text-success mb-3"></i>
                                    <h5>Bulk Payslips</h5>
                                    <p class="text-muted">Print all payslips for a payroll run</p>
                                    
                                    <?php if ($payroll_dates->num_rows > 0): ?>
                                    <form method="GET" action="payslips.php" class="mt-3">
                                        <select name="payroll_date" class="form-select mb-2" required>
                                            <option value="">Select Payroll Date</option>
                                            <?php while ($date = $payroll_dates->fetch_assoc()): ?>
                                            <option value="<?php echo $date['payroll_date']; ?>">
                                                <?php echo date('F d, Y', strtotime($date['payroll_date'])); ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-print"></i> Print All
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div class="alert alert-warning mt-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        No payroll records found. Process a payroll first.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card selection-card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Recent Payrolls</h5>
                    </div>
                    <div class="card-body">
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
                        
                        if ($recent_payrolls->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Payroll Date</th>
                                        <th>Employees</th>
                                        <th>Total Net</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($payroll = $recent_payrolls->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payroll['payroll_date'])); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $payroll['employee_count']; ?></span></td>
                                        <td class="text-success fw-bold">₱<?php echo number_format($payroll['total_net'], 2); ?></td>
                                        <td>
                                            <a href="payslips.php?payroll_date=<?php echo $payroll['payroll_date']; ?>" 
                                               class="btn btn-sm btn-success" target="_blank">
                                                <i class="fas fa-print"></i> Print All
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
                            <h5>No Payroll Records</h5>
                            <p class="text-muted">Process a payroll to generate payslips.</p>
                            <a href="payroll.php" class="btn btn-primary">Process Payroll</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Bulk Printing Interface -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-print"></i> Bulk Payslip Printing
                    <span class="badge bg-light text-dark ms-2">
                        <?php echo date('F d, Y', strtotime($payroll_date)); ?>
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Get payroll summary
                $summary = $conn->query("
                    SELECT COUNT(*) as employee_count, SUM(net_income) as total_net
                    FROM payroll_calculations 
                    WHERE DATE(processed_at) = '$payroll_date'
                ")->fetch_assoc();
                ?>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Ready to Print</h6>
                    <p class="mb-0">
                        You are about to print <strong><?php echo $summary['employee_count']; ?> payslips</strong> 
                        for payroll dated <strong><?php echo date('F d, Y', strtotime($payroll_date)); ?></strong>.
                        Total net pay: <strong>₱<?php echo number_format($summary['total_net'], 2); ?></strong>
                    </p>
                </div>

                <div class="text-center py-4">
                    <i class="fas fa-print fa-4x text-primary mb-3"></i>
                    <h4>Ready to Print All Payslips</h4>
                    <p class="text-muted">Each payslip will print on a separate page.</p>
                    
                    <div class="mt-4">
                        <button onclick="printAllPayslips()" class="btn btn-success btn-lg me-3">
                            <i class="fas fa-print"></i> Print All Payslips
                        </button>
                        <a href="payslips.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> Back to Selection
                        </a>
                    </div>
                </div>

                <!-- Payslip List -->
                <div class="mt-4">
                    <h6>Payslips to be Printed:</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Gross Pay</th>
                                    <th>Deductions</th>
                                    <th>Net Pay</th>
                                    <th>Individual Print</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $payslips = $conn->query("
                                    SELECT pc.*, e.employee_code, e.full_name
                                    FROM payroll_calculations pc
                                    JOIN employees e ON pc.employee_id = e.employee_id
                                    WHERE DATE(pc.processed_at) = '$payroll_date'
                                    ORDER BY e.employee_code
                                ");
                                
                                while ($payslip = $payslips->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $payslip['employee_code']; ?></strong><br>
                                        <small><?php echo $payslip['full_name']; ?></small>
                                    </td>
                                    <td class="text-success">₱<?php echo number_format($payslip['gross_income'], 2); ?></td>
                                    <td class="text-danger">₱<?php echo number_format($payslip['total_deductions'], 2); ?></td>
                                    <td class="text-success fw-bold">₱<?php echo number_format($payslip['net_income'], 2); ?></td>
                                    <td>
                                        <a href="print_payslip.php?employee_id=<?php echo $payslip['employee_id']; ?>&payroll_date=<?php echo $payroll_date; ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function printAllPayslips() {
                // Open all individual payslips in new tabs for printing
                <?php
                $payslips = $conn->query("
                    SELECT pc.employee_id
                    FROM payroll_calculations pc
                    WHERE DATE(pc.processed_at) = '$payroll_date'
                ");
                
                while ($payslip = $payslips->fetch_assoc()) {
                    echo "window.open('print_payslip.php?employee_id={$payslip['employee_id']}&payroll_date=$payroll_date', '_blank');";
                }
                ?>
                
                // Show success message
                setTimeout(() => {
                    alert('All payslips opened for printing. Please check your print dialog for each tab.');
                }, 1000);
            }
        </script>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
