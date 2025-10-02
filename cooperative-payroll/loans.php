<?php
include 'config/database.php';

// Handle form actions
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Add new loan
if ($action == 'add_loan') {
    $employee_id = $_POST['employee_id'];
    $loan_type = $_POST['loan_type'];
    $loan_amount = $_POST['loan_amount'];
    $monthly_deduction = $_POST['monthly_deduction'];
    $start_date = $_POST['start_date'];
    $purpose = $_POST['purpose'];
    $approved_by = $_POST['approved_by'];
    
    // Calculate end date (approximately)
    $months = ceil($loan_amount / $monthly_deduction);
    $end_date = date('Y-m-d', strtotime($start_date . " + $months months"));
    
    $sql = "INSERT INTO employee_loans 
            (employee_id, loan_type, loan_amount, remaining_balance, monthly_deduction, start_date, end_date, purpose, approved_by, approved_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdddssss", $employee_id, $loan_type, $loan_amount, $loan_amount, $monthly_deduction, $start_date, $end_date, $purpose, $approved_by);
    
    if ($stmt->execute()) {
        $success = "✅ Loan added successfully!";
    } else {
        $error = "❌ Error adding loan: " . $conn->error;
    }
}

// Record loan payment
if ($action == 'record_payment') {
    $loan_id = $_POST['loan_id'];
    $amount = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];
    $notes = $_POST['notes'];
    
    // Get current loan balance
    $loan_sql = "SELECT remaining_balance, loan_amount FROM employee_loans WHERE loan_id = ?";
    $loan_stmt = $conn->prepare($loan_sql);
    $loan_stmt->bind_param("i", $loan_id);
    $loan_stmt->execute();
    $loan = $loan_stmt->get_result()->fetch_assoc();
    
    $new_balance = $loan['remaining_balance'] - $amount;
    
    // Check if loan is fully paid
    if ($new_balance <= 0) {
        $status = 'Paid';
        $completion_message = " 🎉 LOAN FULLY PAID!";
        $new_balance = 0; // Prevent negative balance
    } else {
        $status = 'Active';
        $completion_message = "";
    }
    
    // Record payment
    $payment_sql = "INSERT INTO loan_payments (loan_id, payment_date, amount, pay_period_start, pay_period_end, notes) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("isdsss", $loan_id, $payment_date, $amount, $pay_period_start, $pay_period_end, $notes);
    
    if ($payment_stmt->execute()) {
        // Update loan balance and status
        $update_sql = "UPDATE employee_loans SET remaining_balance = ?, status = ? WHERE loan_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("dsi", $new_balance, $status, $loan_id);
        $update_stmt->execute();
        
        $payment_success = "✅ Payment recorded successfully!" . $completion_message;
    } else {
        $payment_error = "❌ Error recording payment: " . $conn->error;
    }
}

// Edit loan
if ($action == 'edit_loan') {
    $loan_id = $_POST['loan_id'];
    $loan_type = $_POST['loan_type'];
    $monthly_deduction = $_POST['monthly_deduction'];
    $purpose = $_POST['purpose'];
    $status = $_POST['status'];
    
    $sql = "UPDATE employee_loans 
            SET loan_type = ?, monthly_deduction = ?, purpose = ?, status = ? 
            WHERE loan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdssi", $loan_type, $monthly_deduction, $purpose, $status, $loan_id);
    
    if ($stmt->execute()) {
        $success = "✅ Loan updated successfully!";
    } else {
        $error = "❌ Error updating loan: " . $conn->error;
    }
}

// Delete loan
if ($action == 'delete_loan') {
    $loan_id = $_POST['loan_id'];
    
    // Check if loan has payment history
    $check_sql = "SELECT COUNT(*) as payment_count FROM loan_payments WHERE loan_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $loan_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result['payment_count'] > 0) {
        $error = "❌ Cannot delete loan with payment history. Mark as Cancelled instead.";
    } else {
        $delete_sql = "DELETE FROM employee_loans WHERE loan_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $loan_id);
        
        if ($delete_stmt->execute()) {
            $success = "✅ Loan deleted successfully!";
        } else {
            $error = "❌ Error deleting loan: " . $conn->error;
        }
    }
}

// Cancel loan
if ($action == 'cancel_loan') {
    $loan_id = $_POST['loan_id'];
    $cancellation_reason = $_POST['cancellation_reason'];
    
    $sql = "UPDATE employee_loans 
            SET status = 'Cancelled', purpose = CONCAT(purpose, ' [CANCELLED: ', ?, ']') 
            WHERE loan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $cancellation_reason, $loan_id);
    
    if ($stmt->execute()) {
        $success = "✅ Loan cancelled successfully!";
    } else {
        $error = "❌ Error cancelling loan: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .loan-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .loan-card:hover {
            transform: translateY(-2px);
        }
        .loan-emergency { border-left-color: #dc3545; }
        .loan-educational { border-left-color: #28a745; }
        .loan-consumer { border-left-color: #ffc107; }
        .loan-rice { border-left-color: #6f42c1; }
        .loan-petty { border-left-color: #17a2b8; }
        .loan-cbu { border-left-color: #6c757d; }
        .loan-savings { border-left-color: #20c997; }
        .status-active { background-color: #d4edda; }
        .status-paid { 
            background-color: #e8f5e8; 
            border-left-color: #28a745 !important;
        }
        .status-cancelled { 
            background-color: #f8d7da; 
            border-left-color: #dc3545 !important;
        }
        .progress-bar {
            height: 8px;
            border-radius: 4px;
        }
        .paid-badge {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .action-buttons .btn {
            margin: 1px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">💰 Loan Management</span>
            <a href="index.php" class="btn btn-light">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Success/Error Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($payment_success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $payment_success; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($payment_error)): ?>
            <div class="alert alert-danger"><?php echo $payment_error; ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body text-center">
                        <h2><i class="fas fa-hand-holding-usd"></i> Complete Loan Management</h2>
                        <p class="lead mb-0">Add, edit, delete, and track all employee loans</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add Loan Form & Stats -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Add New Loan</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_loan">
                            
                            <div class="mb-3">
                                <label class="form-label">Employee:</label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    <?php
                                    $employees = $conn->query("SELECT * FROM employees WHERE status='Active' ORDER BY full_name");
                                    while ($emp = $employees->fetch_assoc()) {
                                        echo "<option value='{$emp['employee_id']}'>{$emp['employee_code']} - {$emp['full_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Loan Type:</label>
                                <select name="loan_type" class="form-select" required>
                                    <option value="">Select Loan Type</option>
                                    <option value="Emergency Loan">🆘 Emergency Loan</option>
                                    <option value="Educational Loan">📚 Educational Loan</option>
                                    <option value="Consumer Loan">🛒 Consumer Loan</option>
                                    <option value="Rice Loan">🍚 Rice Loan</option>
                                    <option value="Petty Cash">💰 Petty Cash Advance</option>
                                    <option value="CBU">🏠 CBU (Capital Build-Up)</option>
                                    <option value="Savings">💵 Savings Deduction</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Loan Amount:</label>
                                        <input type="number" name="loan_amount" step="0.01" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Monthly Deduction:</label>
                                        <input type="number" name="monthly_deduction" step="0.01" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Start Date:</label>
                                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Purpose:</label>
                                <textarea name="purpose" class="form-control" rows="2" placeholder="Purpose of the loan..." required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Approved By:</label>
                                <input type="text" name="approved_by" class="form-control" value="Admin" required>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Add Loan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Loan Statistics</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_loans = $conn->query("SELECT COUNT(*) as total FROM employee_loans")->fetch_assoc()['total'];
                        $active_loans = $conn->query("SELECT COUNT(*) as total FROM employee_loans WHERE status='Active'")->fetch_assoc()['total'];
                        $paid_loans = $conn->query("SELECT COUNT(*) as total FROM employee_loans WHERE status='Paid'")->fetch_assoc()['total'];
                        $total_balance = $conn->query("SELECT COALESCE(SUM(remaining_balance), 0) as total FROM employee_loans WHERE status='Active'")->fetch_assoc()['total'];
                        ?>
                        <div class="mb-2">
                            <small>Total Loans:</small>
                            <div class="fw-bold text-primary"><?php echo $total_loans; ?></div>
                        </div>
                        <div class="mb-2">
                            <small>Active Loans:</small>
                            <div class="fw-bold text-warning"><?php echo $active_loans; ?></div>
                        </div>
                        <div class="mb-2">
                            <small>Paid Loans:</small>
                            <div class="fw-bold text-success"><?php echo $paid_loans; ?></div>
                        </div>
                        <div class="mb-2">
                            <small>Total Balance:</small>
                            <div class="fw-bold text-danger">₱<?php echo number_format($total_balance, 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loans List with Tabs -->
            <div class="col-md-8">
                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs" id="loanTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                            <i class="fas fa-sync"></i> Active Loans
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="paid-tab" data-bs-toggle="tab" data-bs-target="#paid" type="button" role="tab">
                            <i class="fas fa-check-circle"></i> Paid Loans
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                            <i class="fas fa-list"></i> All Loans
                        </button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content" id="loanTabsContent">
                    <!-- Active Loans Tab -->
                    <div class="tab-pane fade show active" id="active" role="tabpanel">
                        <div class="card mt-0">
                            <div class="card-body">
                                <?php
                                $active_loans_sql = "SELECT l.*, e.employee_code, e.full_name 
                                                   FROM employee_loans l 
                                                   JOIN employees e ON l.employee_id = e.employee_id 
                                                   WHERE l.status = 'Active' 
                                                   ORDER BY l.created_at DESC";
                                $active_loans_result = $conn->query($active_loans_sql);
                                ?>
                                
                                <?php if ($active_loans_result->num_rows > 0): ?>
                                    <div class="row">
                                        <?php while ($loan = $active_loans_result->fetch_assoc()): 
                                            $progress = (($loan['loan_amount'] - $loan['remaining_balance']) / $loan['loan_amount']) * 100;
                                            $loan_class = 'loan-' . strtolower(str_replace(' ', '-', explode(' ', $loan['loan_type'])[0]));
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card loan-card <?php echo $loan_class; ?> status-active">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title">
                                                            <?php echo $loan['loan_type']; ?>
                                                        </h6>
                                                        <span class="badge bg-success">Active</span>
                                                    </div>
                                                    
                                                    <p class="mb-1">
                                                        <strong><?php echo $loan['employee_code']; ?></strong> - <?php echo $loan['full_name']; ?>
                                                    </p>
                                                    <p class="mb-1 small text-muted"><?php echo $loan['purpose']; ?></p>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <small>Loan Amount:</small>
                                                            <div class="fw-bold">₱<?php echo number_format($loan['loan_amount'], 2); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small>Balance:</small>
                                                            <div class="fw-bold text-danger">₱<?php echo number_format($loan['remaining_balance'], 2); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <small>Monthly Deduction: <strong>₱<?php echo number_format($loan['monthly_deduction'], 2); ?></strong></small>
                                                    </div>
                                                    
                                                    <!-- Progress Bar -->
                                                    <div class="mb-2">
                                                        <div class="progress progress-bar">
                                                            <div class="progress-bar bg-success" role="progressbar" 
                                                                 style="width: <?php echo $progress; ?>%" 
                                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?php echo number_format($progress, 1); ?>% Paid</small>
                                                    </div>
                                                    
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="showPaymentModal(<?php echo $loan['loan_id']; ?>, '<?php echo $loan['employee_code']; ?>', <?php echo $loan['monthly_deduction']; ?>)">
                                                            <i class="fas fa-money-bill"></i> Pay
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" 
                                                                onclick="showEditModal(<?php echo $loan['loan_id']; ?>, '<?php echo $loan['loan_type']; ?>', <?php echo $loan['monthly_deduction']; ?>, '<?php echo $loan['purpose']; ?>')">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="showCancelModal(<?php echo $loan['loan_id']; ?>, '<?php echo $loan['employee_code']; ?>')">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5>No Active Loans</h5>
                                        <p class="text-muted">Add a new loan using the form on the left.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Paid Loans Tab -->
                    <div class="tab-pane fade" id="paid" role="tabpanel">
                        <div class="card mt-0">
                            <div class="card-body">
                                <?php
                                $paid_loans_sql = "SELECT l.*, e.employee_code, e.full_name 
                                                 FROM employee_loans l 
                                                 JOIN employees e ON l.employee_id = e.employee_id 
                                                 WHERE l.status = 'Paid' 
                                                 ORDER BY l.created_at DESC";
                                $paid_loans_result = $conn->query($paid_loans_sql);
                                ?>
                                
                                <?php if ($paid_loans_result->num_rows > 0): ?>
                                    <div class="row">
                                        <?php while ($loan = $paid_loans_result->fetch_assoc()): 
                                            $loan_class = 'loan-' . strtolower(str_replace(' ', '-', explode(' ', $loan['loan_type'])[0]));
                                        ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card loan-card <?php echo $loan_class; ?> status-paid">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title text-success">
                                                            <i class="fas fa-check-circle"></i> <?php echo $loan['loan_type']; ?>
                                                        </h6>
                                                        <span class="badge paid-badge">FULLY PAID</span>
                                                    </div>
                                                    
                                                    <p class="mb-1">
                                                        <strong><?php echo $loan['employee_code']; ?></strong> - <?php echo $loan['full_name']; ?>
                                                    </p>
                                                    <p class="mb-1 small text-muted"><?php echo $loan['purpose']; ?></p>
                                                    
                                                    <div class="row mb-2">
                                                        <div class="col-6">
                                                            <small>Original Amount:</small>
                                                            <div class="fw-bold">₱<?php echo number_format($loan['loan_amount'], 2); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small>Status:</small>
                                                            <div class="fw-bold text-success">COMPLETED</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="text-center mt-3">
                                                        <i class="fas fa-trophy fa-2x text-warning"></i>
                                                        <p class="text-success mb-0"><strong>Loan Successfully Paid!</strong></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                                        <h5>No Paid Loans</h5>
                                        <p class="text-muted">No loans have been fully paid yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- All Loans Tab -->
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        <div class="card mt-0">
                            <div class="card-body">
                                <?php
                                $all_loans_sql = "SELECT l.*, e.employee_code, e.full_name 
                                                FROM employee_loans l 
                                                JOIN employees e ON l.employee_id = e.employee_id 
                                                ORDER BY l.created_at DESC";
                                $all_loans_result = $conn->query($all_loans_sql);
                                ?>
                                
                                <?php if ($all_loans_result->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Loan Type</th>
                                                    <th>Amount</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($loan = $all_loans_result->fetch_assoc()): 
                                                    $status_badge = $loan['status'] == 'Active' ? 'bg-success' : 
                                                                   ($loan['status'] == 'Paid' ? 'paid-badge' : 'bg-danger');
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo $loan['employee_code']; ?></strong><br>
                                                        <small><?php echo $loan['full_name']; ?></small>
                                                    </td>
                                                    <td><?php echo $loan['loan_type']; ?></td>
                                                    <td class="fw-bold">₱<?php echo number_format($loan['loan_amount'], 2); ?></td>
                                                    <td class="<?php echo $loan['status'] == 'Active' ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                        ₱<?php echo number_format($loan['remaining_balance'], 2); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $status_badge; ?>">
                                                            <?php echo $loan['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <?php if ($loan['status'] == 'Active'): ?>
                                                                <button class="btn btn-sm btn-outline-primary" 
                                                                        onclick="showPaymentModal(<?php echo $loan['loan_id']; ?>, '<?php echo $loan['employee_code']; ?>', <?php echo $loan['monthly_deduction']; ?>)">
                                                                    <i class="fas fa-money-bill"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-warning" 
                                                                        onclick="showEditModal(<?php echo $loan['loan_id']; ?>, '<?php echo $loan['loan_type']; ?>', <?php echo $loan['monthly_deduction']; ?>, '<?php echo $loan['purpose']; ?>')">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    onclick="showDeleteModal(<?php echo $loan['loan_id']; ?>, '<?php echo $loan['employee_code']; ?>', '<?php echo $loan['loan_type']; ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5>No Loans Found</h5>
                                        <p class="text-muted">Add a new loan using the form on the left.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-money-bill"></i> Record Loan Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="action" value="record_payment">
                        <input type="hidden" name="loan_id" id="payment_loan_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Employee:</label>
                            <input type="text" id="payment_employee" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Amount:</label>
                            <input type="number" name="amount" id="payment_amount" step="0.01" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Date:</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Pay Period Start:</label>
                                    <input type="date" name="pay_period_start" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Pay Period End:</label>
                                    <input type="date" name="pay_period_end" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes:</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Payment notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="document.getElementById('paymentForm').submit()">
                        <i class="fas fa-save"></i> Record Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Loan Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Loan Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editForm">
                        <input type="hidden" name="action" value="edit_loan">
                        <input type="hidden" name="loan_id" id="edit_loan_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Loan Type:</label>
                            <select name="loan_type" id="edit_loan_type" class="form-select" required>
                                <option value="Emergency Loan">🆘 Emergency Loan</option>
                                <option value="Educational Loan">📚 Educational Loan</option>
                                <option value="Consumer Loan">🛒 Consumer Loan</option>
                                <option value="Rice Loan">🍚 Rice Loan</option>
                                <option value="Petty Cash">💰 Petty Cash Advance</option>
                                <option value="CBU">🏠 CBU (Capital Build-Up)</option>
                                <option value="Savings">💵 Savings Deduction</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Monthly Deduction:</label>
                            <input type="number" name="monthly_deduction" id="edit_monthly_deduction" step="0.01" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Purpose:</label>
                            <textarea name="purpose" id="edit_purpose" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status:</label>
                            <select name="status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Paid">Paid</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="document.getElementById('editForm').submit()">
                        <i class="fas fa-save"></i> Update Loan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Loan Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash"></i> Delete Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete_loan">
                        <input type="hidden" name="loan_id" id="delete_loan_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> You are about to delete a loan.
                        </div>
                        
                        <p>Are you sure you want to delete the loan for <strong id="delete_employee_info"></strong>?</p>
                        <p class="text-danger"><small>This action cannot be undone.</small></p>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteForm').submit()">
                        <i class="fas fa-trash"></i> Delete Loan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Loan Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times"></i> Cancel Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="cancelForm">
                        <input type="hidden" name="action" value="cancel_loan">
                        <input type="hidden" name="loan_id" id="cancel_loan_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> You are about to cancel a loan.
                        </div>
                        
                        <p>Cancelling loan for: <strong id="cancel_employee_info"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Cancellation Reason:</label>
                            <textarea name="cancellation_reason" class="form-control" rows="3" placeholder="Reason for cancellation..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('cancelForm').submit()">
                        <i class="fas fa-times"></i> Cancel Loan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Payment Modal
        function showPaymentModal(loanId, employeeCode, monthlyDeduction) {
            document.getElementById('payment_loan_id').value = loanId;
            document.getElementById('payment_employee').value = employeeCode;
            document.getElementById('payment_amount').value = monthlyDeduction;
            
            var paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        }

        // Edit Modal
        function showEditModal(loanId, loanType, monthlyDeduction, purpose) {
            document.getElementById('edit_loan_id').value = loanId;
            document.getElementById('edit_loan_type').value = loanType;
            document.getElementById('edit_monthly_deduction').value = monthlyDeduction;
            document.getElementById('edit_purpose').value = purpose;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        // Delete Modal
        function showDeleteModal(loanId, employeeCode, loanType) {
            document.getElementById('delete_loan_id').value = loanId;
            document.getElementById('delete_employee_info').textContent = employeeCode + ' - ' + loanType;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Cancel Modal
        function showCancelModal(loanId, employeeCode) {
            document.getElementById('cancel_loan_id').value = loanId;
            document.getElementById('cancel_employee_info').textContent = employeeCode;
            
            var cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
            cancelModal.show();
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>