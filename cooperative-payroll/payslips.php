
<?php
include 'config/database.php';

// Get payroll date from URL
$payroll_date = $_GET['payroll_date'] ?? date('Y-m-d');

// Get all payroll records for the selected date
$sql = "SELECT pc.*, e.employee_code, e.full_name, e.designation
        FROM payroll_calculations pc
        JOIN employees e ON pc.employee_id = e.employee_id
        WHERE DATE(pc.processed_at) = ?
        ORDER BY e.employee_code";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $payroll_date);
$stmt->execute();
$payroll_records = $stmt->get_result();

// Check if we're printing all
$print_all = isset($_GET['print_all']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Payslip Printing - Cooperative Payroll</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            .payslip-container { 
                page-break-after: always; 
                margin: 0;
                border: none;
            }
            body { margin: 0; padding: 0; }
        }
        
        .payslip-container {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            padding: 20mm;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            font-family: Arial, sans-serif;
            background: white;
        }
        
        .company-header {
            text-align: center;
            border-bottom: 3px double #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .payslip-title {
            font-size: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background: #f8f9fa;
            padding: 8px 15px;
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        .info-table .label {
            background: #f8f9fa;
            font-weight: bold;
            width: 30%;
        }
        
        .earnings-table, .deductions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .earnings-table th, .deductions-table th {
            background: #343a40;
            color: white;
            padding: 10px;
            text-align: left;
        }
        
        .earnings-table td, .deductions-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        .total-row {
            background: #e9ecef;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .signature-area {
            margin-top: 50px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            width: 200px;
            display: inline-block;
            margin: 0 20px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <?php if (!$print_all): ?>
    <div class="no-print" style="padding: 20px; text-align: center;">
        <div class="container">
            <h2>🖨️ Bulk Payslip Printing</h2>
            <p>Payroll Date: <strong><?php echo date('F d, Y', strtotime($payroll_date)); ?></strong></p>
            <p>Number of Employees: <strong><?php echo $payroll_records->num_rows; ?></strong></p>
            
            <div class="mt-4">
                <button onclick="printAllPayslips()" class="btn btn-success btn-lg">
                    <i class="fas fa-print"></i> Print All Payslips (<?php echo $payroll_records->num_rows; ?>)
                </button>
                <a href="payroll_details.php?date=<?php echo $payroll_date; ?>" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left"></i> Back to Details
                </a>
            </div>
            
            <div class="mt-4 alert alert-info">
                <h5>Printing Instructions:</h5>
                <ul class="text-start">
                    <li>Click "Print All Payslips" to generate all employee payslips</li>
                    <li>Each payslip will print on a separate page</li>
                    <li>Ensure your printer has enough paper for <?php echo $payroll_records->num_rows; ?> pages</li>
                    <li>Use "Back to Details" to review before printing</li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // Generate payslips for all employees
    $counter = 0;
    while ($record = $payroll_records->fetch_assoc()):
        $counter++;
        if ($counter > 1 && $print_all) {
            echo '<div class="page-break"></div>';
        }
    ?>
    <div class="payslip-container">
        <!-- Company Header -->
        <div class="company-header">
            <div class="company-name">COOPERATIVE PAYROLL SYSTEM</div>
            <div>123 Cooperative Street, City, Philippines</div>
            <div>Phone: (123) 456-7890 | Email: payroll@cooperative.com</div>
        </div>

        <!-- Payslip Title -->
        <div class="payslip-title">EMPLOYEE PAYSLIP</div>

        <!-- Employee Information -->
        <div class="section">
            <div class="section-title">EMPLOYEE INFORMATION</div>
            <table class="info-table">
                <tr>
                    <td class="label">Employee Code</td>
                    <td><?php echo $record['employee_code']; ?></td>
                    <td class="label">Pay Period</td>
                    <td><?php echo date('M d, Y', strtotime($record['pay_period_start'])); ?> - <?php echo date('M d, Y', strtotime($record['pay_period_end'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Full Name</td>
                    <td><?php echo $record['full_name']; ?></td>
                    <td class="label">Pay Date</td>
                    <td><?php echo date('F d, Y', strtotime($record['processed_at'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Designation</td>
                    <td><?php echo $record['designation']; ?></td>
                    <td class="label">Payslip ID</td>
                    <td>PS<?php echo str_pad($record['employee_id'], 4, '0', STR_PAD_LEFT) . date('Ymd', strtotime($record['processed_at'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Earnings & Deductions -->
        <div class="row" style="display: flex;">
            <div style="flex: 1; margin-right: 10px;">
                <div class="section-title">EARNINGS</div>
                <table class="earnings-table">
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="text-right">₱<?php echo number_format($record['gross_income'] * 0.7, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Overtime Pay</td>
                        <td class="text-right">₱<?php echo number_format($record['gross_income'] * 0.2, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Night Differential</td>
                        <td class="text-right">₱<?php echo number_format($record['gross_income'] * 0.1, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL EARNINGS</strong></td>
                        <td class="text-right"><strong>₱<?php echo number_format($record['gross_income'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
            
            <div style="flex: 1; margin-left: 10px;">
                <div class="section-title">DEDUCTIONS</div>
                <table class="deductions-table">
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                    <tr>
                        <td>SSS Contribution</td>
                        <td class="text-right">-₱<?php echo number_format($record['sss_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>PhilHealth</td>
                        <td class="text-right">-₱<?php echo number_format($record['philhealth_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Pag-IBIG</td>
                        <td class="text-right">-₱<?php echo number_format($record['pagibig_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Emergency Loan</td>
                        <td class="text-right">-₱<?php echo number_format($record['emergency_loan'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Petty Cash</td>
                        <td class="text-right">-₱<?php echo number_format($record['petty_cash'], 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL DEDUCTIONS</strong></td>
                        <td class="text-right"><strong>-₱<?php echo number_format($record['total_deductions'], 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Net Pay -->
        <div class="section">
            <table class="info-table">
                <tr class="total-row">
                    <td class="label" style="width: 30%;">NET PAY</td>
                    <td style="width: 70%; font-size: 18px; text-align: center;">
                        <strong>₱<?php echo number_format($record['net_income'], 2); ?></strong>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Signatures -->
        <div class="signature-area text-center">
            <div style="display: inline-block; margin: 0 40px;">
                <div class="signature-line"></div>
                <div>Employee Signature</div>
            </div>
            <div style="display: inline-block; margin: 0 40px;">
                <div class="signature-line"></div>
                <div>Authorized Signature</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            This is a computer-generated payslip. No signature required.<br>
            Generated on: <?php echo date('F d, Y g:i A'); ?>
        </div>
    </div>
    <?php endwhile; ?>

    <script>
        function printAllPayslips() {
            // Redirect to same page with print_all parameter
            window.location.href = 'payslips.php?payroll_date=<?php echo $payroll_date; ?>&print_all=1';
        }

        // Auto-print when print_all is set
        <?php if ($print_all): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
                // Optionally redirect back after printing
                setTimeout(function() {
                    // window.location.href = 'payroll_details.php?date=<?php echo $payroll_date; ?>';
                }, 1000);
            }, 500);
        };
        <?php endif; ?>
    </script>

    <?php if (!$print_all): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html>
