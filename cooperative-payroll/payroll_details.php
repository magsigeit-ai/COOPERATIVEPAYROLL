
<?php
include 'config/database.php';

// Get payroll date from URL
$payroll_date = $_GET['date'] ?? date('Y-m-d');

// Check if payroll_name column exists
$check_column = $conn->query("SHOW COLUMNS FROM payroll_calculations LIKE 'payroll_name'");
$has_payroll_name = ($check_column->num_rows > 0);

// Get payroll summary for the selected date
if ($has_payroll_name) {
    $summary_sql = "SELECT 
                    DATE(processed_at) as payroll_date,
                    payroll_name,
                    COUNT(*) as employee_count,
                    SUM(gross_income) as total_gross,
                    SUM(total_deductions) as total_deductions,
                    SUM(net_income) as total_net,
                    MIN(pay_period_start) as period_start,
                    MAX(pay_period_end) as period_end
                  FROM payroll_calculations 
                  WHERE DATE(processed_at) = ?
                  GROUP BY DATE(processed_at), payroll_name";
} else {
    $summary_sql = "SELECT 
                    DATE(processed_at) as payroll_date,
                    COUNT(*) as employee_count,
                    SUM(gross_income) as total_gross,
                    SUM(total_deductions) as total_deductions,
                    SUM(net_income) as total_net,
                    MIN(pay_period_start) as period_start,
                    MAX(pay_period_end) as period_end
                  FROM payroll_calculations 
                  WHERE DATE(processed_at) = ?
                  GROUP BY DATE(processed_at)";
}

$summary_stmt = $conn->prepare($summary_sql);
$summary_stmt->bind_param("s", $payroll_date);
$summary_stmt->execute();
$payroll_summary = $summary_stmt->get_result()->fetch_assoc();

if (!$payroll_summary) {
    die("Payroll data not found for the selected date!");
}

// Get detailed payroll records for this date
if ($has_payroll_name) {
    $details_sql = "SELECT pc.*, e.employee_code, e.full_name, e.designation
                   FROM payroll_calculations pc
                   JOIN employees e ON pc.employee_id = e.employee_id
                   WHERE DATE(pc.processed_at) = ?
                   ORDER BY e.employee_code";
} else {
    $details_sql = "SELECT pc.*, e.employee_code, e.full_name, e.designation
                   FROM payroll_calculations pc
                   JOIN employees e ON pc.employee_id = e.employee_id
                   WHERE DATE(pc.processed_at) = ?
                   ORDER BY e.employee_code";
}

$details_stmt = $conn->prepare($details_sql);
$details_stmt->bind_param("s", $payroll_date);
$details_stmt->execute();
$payroll_details = $details_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Details - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin: 5px;
        }
        .print-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        @media print {
            .no-print { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            .table { font-size: 12px; }
            .summary-card { background: #667eea !important; -webkit-print-color-adjust: exact; }
        }
        .employee-row:hover {
            background-color: #f8f9fa;
        }
        .deduction-breakdown {
            font-size: 0.8em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary no-print">
        <div class="container">
            <span class="navbar-brand mb-0 h1">📊 Payroll Details</span>
            <div>
                <a href="payroll.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Payroll
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Print Header -->
        <div class="print-header no-print">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4>Payroll Run Details</h4>
                    <p class="mb-0 text-muted">Comprehensive breakdown of payroll calculations</p>
                </div>
                <div class="col-md-6 text-end">
                    <button onclick="window.print()" class="btn btn-success">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button onclick="exportToExcel()" class="btn btn-primary">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Payroll Summary -->
        <div class="summary-card">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4><?php echo $payroll_summary['employee_count']; ?></h4>
                        <small>Employees</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4>₱<?php echo number_format($payroll_summary['total_gross'], 2); ?></h4>
                        <small>Total Gross</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4>₱<?php echo number_format($payroll_summary['total_deductions'], 2); ?></h4>
                        <small>Total Deductions</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4>₱<?php echo number_format($payroll_summary['total_net'], 2); ?></h4>
                        <small>Total Net Pay</small>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <strong>Payroll Date:</strong> <?php echo date('F d, Y', strtotime($payroll_summary['payroll_date'])); ?>
                </div>
                <div class="col-md-6">
                    <strong>Pay Period:</strong> 
                    <?php echo date('M d, Y', strtotime($payroll_summary['period_start'])); ?> - 
                    <?php echo date('M d, Y', strtotime($payroll_summary['period_end'])); ?>
                </div>
            </div>
            <?php if ($has_payroll_name && !empty($payroll_summary['payroll_name'])): ?>
            <div class="row mt-2">
                <div class="col-md-12">
                    <strong>Payroll Name:</strong> <?php echo $payroll_summary['payroll_name']; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detailed Payroll Records -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Employee Payroll Breakdown
                    <span class="badge bg-light text-dark ms-2">
                        <?php echo $payroll_details->num_rows; ?> records
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="payrollTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Gross Income</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($detail = $payroll_details->fetch_assoc()): ?>
                            <tr class="employee-row">
                                <td>
                                    <strong><?php echo $detail['employee_code']; ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $detail['full_name']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $detail['designation']; ?></span>
                                </td>
                                <td class="text-success fw-bold">
                                    ₱<?php echo number_format($detail['gross_income'], 2); ?>
                                </td>
                                <td>
                                    <span class="text-danger fw-bold">
                                        ₱<?php echo number_format($detail['total_deductions'], 2); ?>
                                    </span>
                                    <br>
                                    <small class="deduction-breakdown">
                                        SSS: ₱<?php echo number_format($detail['sss_amount'], 2); ?> | 
                                        PhilHealth: ₱<?php echo number_format($detail['philhealth_amount'], 2); ?> | 
                                        Pag-IBIG: ₱<?php echo number_format($detail['pagibig_amount'], 2); ?>
                                        <?php if ($detail['emergency_loan'] > 0 || $detail['petty_cash'] > 0): ?>
                                        <br>Loans: ₱<?php echo number_format($detail['emergency_loan'] + $detail['petty_cash'], 2); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td class="text-success fw-bold fs-6">
                                    ₱<?php echo number_format($detail['net_income'], 2); ?>
                                </td>
                                <td class="no-print">
                                    <a href="print_payslip.php?employee_id=<?php echo $detail['employee_id']; ?>&payroll_date=<?php echo $payroll_date; ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-print"></i> Print Payslip
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-success fw-bold">
                            <tr>
                                <td colspan="2" class="text-end">TOTALS:</td>
                                <td>₱<?php echo number_format($payroll_summary['total_gross'], 2); ?></td>
                                <td>₱<?php echo number_format($payroll_summary['total_deductions'], 2); ?></td>
                                <td>₱<?php echo number_format($payroll_summary['total_net'], 2); ?></td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4 no-print">
            <div class="col-md-12 text-center">
                <div class="btn-group">
                    <a href="payslips.php?payroll_date=<?php echo $payroll_date; ?>" 
                       class="btn btn-warning">
                        <i class="fas fa-print"></i> Print All Payslips
                    </a>
                    <a href="payroll.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Payroll List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportToExcel() {
            // Create a temporary table for export
            let table = document.getElementById('payrollTable');
            let html = table.outerHTML;
            
            // Create download link
            let url = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(html);
            let downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'payroll_details_<?php echo $payroll_date; ?>.xls';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        // Auto-print if print parameter is set
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('print')) {
            window.print();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
