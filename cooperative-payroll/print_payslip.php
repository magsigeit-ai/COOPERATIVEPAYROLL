<?php
include 'config/database.php';

// Get employee and payroll data
$employee_id = $_GET['employee_id'] ?? 0;
$payroll_date = $_GET['payroll_date'] ?? date('Y-m-d');

// Get employee data
$employee_sql = "SELECT e.*, p.preferred_sss_amount, p.preferred_philhealth_amount, p.preferred_pagibig_amount 
                FROM employees e 
                LEFT JOIN employee_preferences p ON e.employee_id = p.employee_id 
                WHERE e.employee_id = ?";
$employee_stmt = $conn->prepare($employee_sql);
$employee_stmt->bind_param("i", $employee_id);
$employee_stmt->execute();
$employee = $employee_stmt->get_result()->fetch_assoc();

// Get payroll data
$payroll_sql = "SELECT * FROM payroll_calculations 
                WHERE employee_id = ? AND DATE(processed_at) = ? 
                ORDER BY processed_at DESC LIMIT 1";
$payroll_stmt = $conn->prepare($payroll_sql);
$payroll_stmt->bind_param("is", $employee_id, $payroll_date);
$payroll_stmt->execute();
$payroll = $payroll_stmt->get_result()->fetch_assoc();

if (!$employee || !$payroll) {
    die("Data not found for printing!");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo $employee['full_name']; ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            .payslip-container { box-shadow: none; border: none; }
        }
        
        .payslip-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
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
    </style>
</head>
<body>
    <div class="no-print" style="padding: 20px; text-align: center;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Payslip</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>

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
                    <td><?php echo $employee['employee_code']; ?></td>
                    <td class="label">Pay Period</td>
                    <td><?php echo date('M d, Y', strtotime($payroll['pay_period_start'])); ?> - <?php echo date('M d, Y', strtotime($payroll['pay_period_end'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Full Name</td>
                    <td><?php echo $employee['full_name']; ?></td>
                    <td class="label">Pay Date</td>
                    <td><?php echo date('F d, Y', strtotime($payroll['processed_at'])); ?></td>
                </tr>
                <tr>
                    <td class="label">Designation</td>
                    <td><?php echo $employee['designation']; ?></td>
                    <td class="label">Payslip ID</td>
                    <td>PS<?php echo str_pad($employee_id, 4, '0', STR_PAD_LEFT) . date('Ymd', strtotime($payroll['processed_at'])); ?></td>
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
                        <td class="text-right">₱<?php echo number_format($payroll['gross_income'] * 0.7, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Overtime Pay</td>
                        <td class="text-right">₱<?php echo number_format($payroll['gross_income'] * 0.2, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Night Differential</td>
                        <td class="text-right">₱<?php echo number_format($payroll['gross_income'] * 0.1, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL EARNINGS</strong></td>
                        <td class="text-right"><strong>₱<?php echo number_format($payroll['gross_income'], 2); ?></strong></td>
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
                        <td class="text-right">-₱<?php echo number_format($payroll['sss_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>PhilHealth</td>
                        <td class="text-right">-₱<?php echo number_format($payroll['philhealth_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Pag-IBIG</td>
                        <td class="text-right">-₱<?php echo number_format($payroll['pagibig_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Emergency Loan</td>
                        <td class="text-right">-₱<?php echo number_format($payroll['emergency_loan'], 2); ?></td>
                    </tr>
                    <tr>
                        <td>Petty Cash</td>
                        <td class="text-right">-₱<?php echo number_format($payroll['petty_cash'], 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>TOTAL DEDUCTIONS</strong></td>
                        <td class="text-right"><strong>-₱<?php echo number_format($payroll['total_deductions'], 2); ?></strong></td>
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
                        <strong>₱<?php echo number_format($payroll['net_income'], 2); ?></strong>
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

    <script>
        // Auto-print when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
</body>
</html>