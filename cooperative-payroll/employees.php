<?php
include 'config/database.php';

// Handle form submissions
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
}

// Add new employee with duplicate check
if ($action == 'add_employee') {
    $employee_code = $_POST['employee_code'];
    $full_name = $_POST['full_name'];
    $designation = $_POST['designation'];
    $category = $_POST['category'];
    $base_daily_rate = $_POST['base_daily_rate'];
    
    // Check for duplicate employee code
    $check_sql = "SELECT COUNT(*) as count FROM employees WHERE employee_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $employee_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error = "❌ Employee code '$employee_code' already exists! Please use a unique code.";
    } else {
        // Proceed with adding employee
        $sql = "INSERT INTO employees (employee_code, full_name, designation, category, base_daily_rate) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssd", $employee_code, $full_name, $designation, $category, $base_daily_rate);
        
        if ($stmt->execute()) {
            $new_employee_id = $stmt->insert_id;
            $success = "✅ Employee added successfully!";
            
            // Add initial rate history
            $rate_sql = "INSERT INTO employee_rate_history 
                        (employee_id, old_rate, new_rate, change_date, effective_date, reason, notes) 
                        VALUES (?, 0, ?, CURDATE(), CURDATE(), 'Initial Rate', 'New employee creation')";
            $rate_stmt = $conn->prepare($rate_sql);
            $rate_stmt->bind_param("id", $new_employee_id, $base_daily_rate);
            $rate_stmt->execute();
            
            // Add default preferences
            $pref_sql = "INSERT INTO employee_preferences (employee_id, preferred_sss_amount, preferred_philhealth_amount, preferred_pagibig_amount) 
                         VALUES (?, 50.00, 100.00, 100.00)";
            $pref_stmt = $conn->prepare($pref_sql);
            $pref_stmt->bind_param("i", $new_employee_id);
            $pref_stmt->execute();
            
        } else {
            $error = "Error adding employee: " . $conn->error;
        }
    }
}

// Edit employee
if ($action == 'edit_employee') {
    $employee_id = $_POST['employee_id'];
    $employee_code = $_POST['employee_code'];
    $full_name = $_POST['full_name'];
    $designation = $_POST['designation'];
    $category = $_POST['category'];
    $base_daily_rate = $_POST['base_daily_rate'];
    $status = $_POST['status'];
    
    // Get current data to compare changes
    $current_sql = "SELECT * FROM employees WHERE employee_id = ?";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bind_param("i", $employee_id);
    $current_stmt->execute();
    $current_employee = $current_stmt->get_result()->fetch_assoc();
    
    $sql = "UPDATE employees 
            SET employee_code = ?, full_name = ?, designation = ?, category = ?, base_daily_rate = ?, status = ?
            WHERE employee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssdsi", $employee_code, $full_name, $designation, $category, $base_daily_rate, $status, $employee_id);
    
    if ($stmt->execute()) {
        // Check if rate changed and add to history
        if ($current_employee['base_daily_rate'] != $base_daily_rate) {
            $history_sql = "INSERT INTO employee_rate_history 
                           (employee_id, old_rate, new_rate, change_date, effective_date, reason, notes, changed_by) 
                           VALUES (?, ?, ?, CURDATE(), CURDATE(), 'Manual Update', 'Employee profile update', 'Admin')";
            $history_stmt = $conn->prepare($history_sql);
            $history_stmt->bind_param("idd", $employee_id, $current_employee['base_daily_rate'], $base_daily_rate);
            $history_stmt->execute();
        }
        
        $success = "Employee updated successfully!";
    } else {
        $error = "Error updating employee: " . $conn->error;
    }
}

// Handle deduction update
if ($action == 'update_deductions') {
    $employee_id = $_POST['employee_id'];
    $sss_amount = $_POST['sss_amount'];
    $philhealth_amount = $_POST['philhealth_amount'];
    $pagibig_amount = $_POST['pagibig_amount'];
    
    // Check if preferences exist
    $check_sql = "SELECT COUNT(*) as count FROM employee_preferences WHERE employee_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $employee_id);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();
    
    if ($exists['count'] > 0) {
        // Update existing
        $sql = "UPDATE employee_preferences 
                SET preferred_sss_amount = ?, preferred_philhealth_amount = ?, preferred_pagibig_amount = ? 
                WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddi", $sss_amount, $philhealth_amount, $pagibig_amount, $employee_id);
    } else {
        // Insert new
        $sql = "INSERT INTO employee_preferences (employee_id, preferred_sss_amount, preferred_philhealth_amount, preferred_pagibig_amount) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iddd", $employee_id, $sss_amount, $philhealth_amount, $pagibig_amount);
    }
    
    if ($stmt->execute()) {
        $deduction_success = "✅ Deductions updated successfully!";
    } else {
        $deduction_error = "Error updating deductions: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .deduction-card {
            border-left: 4px solid #17a2b8;
        }
        .edit-form {
            background: #fff3cd;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
        }
        .employee-row:hover {
            background-color: #f8f9fa;
        }
        .total-deductions {
            background: #e7f3ff;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            border-left: 4px solid #007bff;
        }
        .status-active {
            background-color: #28a745 !important;
        }
        .status-inactive {
            background-color: #dc3545 !important;
        }
        .deductions-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 0.75em;
            padding: 3px 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">👥 Employee Management</span>
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
        
        <?php if (isset($deduction_success)): ?>
            <div class="alert alert-success"><?php echo $deduction_success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($deduction_error)): ?>
            <div class="alert alert-danger"><?php echo $deduction_error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Employee Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5>➕ Add New Employee</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_employee">
                            
                            <div class="mb-3">
                                <label class="form-label">Employee Code:</label>
                                <input type="text" name="employee_code" id="employee_code" class="form-control" 
                                       placeholder="Ex: FARM004" required onblur="checkEmployeeCode()">
                                <div id="code_feedback"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name:</label>
                                <input type="text" name="full_name" class="form-control" placeholder="Ex: Jose Rizal" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Designation:</label>
                                <select name="designation" class="form-select" required>
                                    <option value="">Select Designation</option>
                                    <option value="Farmer">Farmer</option>
                                    <option value="Driver">Driver</option>
                                    <option value="Lead Man">Lead Man</option>
                                    <option value="Supervisor">Supervisor</option>
                                    <option value="Manager">Manager</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Category:</label>
                                <select name="category" class="form-select" required>
                                    <option value="CAT 2">CAT 2</option>
                                    <option value="CAT 1">CAT 1</option>
                                    <option value="REGULAR">REGULAR</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Base Daily Rate:</label>
                                <input type="number" name="base_daily_rate" step="0.01" class="form-control" placeholder="505.00" required>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">Add Employee</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Employee List & Management -->
            <div class="col-md-8">
                <!-- Employee List -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">📋 Employee Master List</h5>
                        </div>
                        <div>
                            <button class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#bulkRateModal">
                                <i class="fas fa-users"></i> Bulk Rate Change
                            </button>
                            <span class="badge bg-light text-dark">
                                <?php 
                                $total_employees = $conn->query("SELECT COUNT(*) as total FROM employees WHERE status='Active'")->fetch_assoc()['total'];
                                echo $total_employees . " Active";
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Category</th>
                                        <th>Daily Rate</th>
                                        <th>Deductions</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = $conn->query("SELECT e.*, 
                                                           p.preferred_sss_amount, p.preferred_philhealth_amount, p.preferred_pagibig_amount
                                                           FROM employees e 
                                                           LEFT JOIN employee_preferences p ON e.employee_id = p.employee_id 
                                                           WHERE e.status = 'Active' OR e.status = 'Inactive'
                                                           GROUP BY e.employee_id
                                                           ORDER BY e.employee_code");
                                    while ($row = $result->fetch_assoc()) {
                                        $total_deductions = ($row['preferred_sss_amount'] ?? 0) + 
                                                           ($row['preferred_philhealth_amount'] ?? 0) + 
                                                           ($row['preferred_pagibig_amount'] ?? 0);
                                        
                                        $status_class = $row['status'] == 'Active' ? 'status-active' : 'status-inactive';
                                        
                                        echo "<tr class='employee-row'>";
                                        echo "<td><strong>{$row['employee_code']}</strong></td>";
                                        echo "<td>{$row['full_name']}</td>";
                                        echo "<td><span class='badge bg-secondary'>{$row['designation']}</span></td>";
                                        echo "<td>{$row['category']}</td>";
                                        echo "<td class='text-success'><strong>₱" . number_format($row['base_daily_rate'], 2) . "</strong></td>";
                                        
                                        // Deductions Column
                                        echo "<td>";
                                        if ($total_deductions > 0) {
                                            echo "<span class='deductions-badge'>₱" . number_format($total_deductions, 2) . "</span>";
                                            echo "<br><small class='text-muted'>";
                                            $deduction_parts = [];
                                            if ($row['preferred_sss_amount'] > 0) $deduction_parts[] = "SSS: ₱" . number_format($row['preferred_sss_amount'], 2);
                                            if ($row['preferred_philhealth_amount'] > 0) $deduction_parts[] = "Phil: ₱" . number_format($row['preferred_philhealth_amount'], 2);
                                            if ($row['preferred_pagibig_amount'] > 0) $deduction_parts[] = "PagIBIG: ₱" . number_format($row['preferred_pagibig_amount'], 2);
                                            echo implode(" | ", $deduction_parts);
                                            echo "</small>";
                                        } else {
                                            echo "<span class='text-muted'>No deductions</span>";
                                        }
                                        echo "</td>";
                                        
                                        // Status Column with color coding
                                        echo "<td><span class='badge {$status_class}'>{$row['status']}</span></td>";
                                        
                                        // Actions Column
                                        echo "<td>";
                                        echo "<button class='btn btn-sm btn-outline-warning' 
                                                onclick='showEditForm({$row['employee_id']}, \"{$row['employee_code']}\", \"{$row['full_name']}\", \"{$row['designation']}\", \"{$row['category']}\", {$row['base_daily_rate']}, \"{$row['status']}\")'>
                                                <i class='fas fa-edit'></i> Edit
                                              </button>";
                                        echo "<button class='btn btn-sm btn-outline-info ms-1' 
                                                onclick='showDeductionForm({$row['employee_id']}, \"{$row['full_name']}\", 
                                                " . ($row['preferred_sss_amount'] ?? 0) . ", 
                                                " . ($row['preferred_philhealth_amount'] ?? 0) . ", 
                                                " . ($row['preferred_pagibig_amount'] ?? 0) . ")'>
                                                <i class='fas fa-calculator'></i> Deduct
                                              </button>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Edit Employee Form (Initially Hidden) -->
                <div class="edit-form" id="editForm" style="display: none;">
                    <div class="card-header bg-warning text-dark">
                        <h5>✏️ Edit Employee</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="editEmployeeForm">
                            <input type="hidden" name="action" value="edit_employee">
                            <input type="hidden" name="employee_id" id="edit_employee_id">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Employee Code:</label>
                                        <input type="text" name="employee_code" id="edit_employee_code" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status:</label>
                                        <select name="status" id="edit_status" class="form-select" required>
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name:</label>
                                <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Designation:</label>
                                        <select name="designation" id="edit_designation" class="form-select" required>
                                            <option value="Farmer">Farmer</option>
                                            <option value="Driver">Driver</option>
                                            <option value="Lead Man">Lead Man</option>
                                            <option value="Supervisor">Supervisor</option>
                                            <option value="Manager">Manager</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Category:</label>
                                        <select name="category" id="edit_category" class="form-select" required>
                                            <option value="CAT 2">CAT 2</option>
                                            <option value="CAT 1">CAT 1</option>
                                            <option value="REGULAR">REGULAR</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Base Daily Rate:</label>
                                <input type="number" name="base_daily_rate" id="edit_base_daily_rate" step="0.01" class="form-control" required>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Update Employee
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="hideEditForm()">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Deduction Form (Initially Hidden) -->
                <div class="card mt-4 deduction-card" id="deductionCard" style="display: none;">
                    <div class="card-header bg-info text-white">
                        <h5>💰 Set Employee Deductions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="deductionForm">
                            <input type="hidden" name="action" value="update_deductions">
                            <input type="hidden" name="employee_id" id="deduct_employee_id">
                            
                            <div class="alert alert-primary">
                                <strong>Employee:</strong> <span id="deduct_employee_name"></span>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">SSS Contribution:</label>
                                        <input type="number" name="sss_amount" id="sss_amount" step="0.01" class="form-control" 
                                               placeholder="0.00" required oninput="calculateTotalDeductions()">
                                        <small class="text-muted">Monthly SSS deduction</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">PhilHealth:</label>
                                        <input type="number" name="philhealth_amount" id="philhealth_amount" step="0.01" class="form-control" 
                                               placeholder="0.00" required oninput="calculateTotalDeductions()">
                                        <small class="text-muted">Monthly PhilHealth contribution</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Pag-IBIG:</label>
                                        <input type="number" name="pagibig_amount" id="pagibig_amount" step="0.01" class="form-control" 
                                               placeholder="0.00" required oninput="calculateTotalDeductions()">
                                        <small class="text-muted">Monthly Pag-IBIG contribution</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total Deductions Display -->
                            <div class="total-deductions">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Total Monthly Deductions:</strong>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span id="total_deductions_display" class="fw-bold text-danger">₱0.00</span>
                                    </div>
                                </div>
                                <small class="text-muted">This amount will be deducted from each payroll</small>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> Save Deductions
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="hideDeductionForm()">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Rate Change Modal -->
    <div class="modal fade" id="bulkRateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-users"></i> Bulk Rate Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="bulk_rate_change.php">
                        <!-- Bulk rate change form content -->
                        <div class="mb-3">
                            <label class="form-label">Select Employees:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">Select All Employees</label>
                            </div>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px;">
                                <?php
                                $employees = $conn->query("SELECT employee_id, employee_code, full_name FROM employees WHERE status='Active' ORDER BY employee_code");
                                while ($emp = $employees->fetch_assoc()) {
                                    echo "<div class='form-check'>";
                                    echo "<input class='form-check-input employee-check' type='checkbox' name='employee_ids[]' value='{$emp['employee_id']}' id='emp_{$emp['employee_id']}'>";
                                    echo "<label class='form-check-label' for='emp_{$emp['employee_id']}'>";
                                    echo "{$emp['employee_code']} - {$emp['full_name']}";
                                    echo "</label>";
                                    echo "</div>";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Daily Rate:</label>
                            <input type="number" name="new_rate" step="0.01" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Effective Date:</label>
                            <input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Change:</label>
                            <select name="reason" class="form-select" required>
                                <option value="Annual Increase">Annual Increase</option>
                                <option value="Adjustment">Adjustment</option>
                                <option value="Promotion">Promotion</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes:</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-sync-alt"></i> Update Rates in Bulk
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Edit Form Functions
        function showEditForm(employeeId, employeeCode, fullName, designation, category, baseDailyRate, status) {
            document.getElementById('edit_employee_id').value = employeeId;
            document.getElementById('edit_employee_code').value = employeeCode;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_designation').value = designation;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_base_daily_rate').value = baseDailyRate;
            document.getElementById('edit_status').value = status;
            
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('deductionCard').style.display = 'none';
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function hideEditForm() {
            document.getElementById('editForm').style.display = 'none';
        }

        // Deduction Form Functions
        function showDeductionForm(employeeId, employeeName, sssAmount, philhealthAmount, pagibigAmount) {
            document.getElementById('deduct_employee_id').value = employeeId;
            document.getElementById('deduct_employee_name').innerText = employeeName;
            document.getElementById('sss_amount').value = sssAmount || 0;
            document.getElementById('philhealth_amount').value = philhealthAmount || 0;
            document.getElementById('pagibig_amount').value = pagibigAmount || 0;
            
            document.getElementById('deductionCard').style.display = 'block';
            document.getElementById('editForm').style.display = 'none';
            
            // Calculate initial total
            calculateTotalDeductions();
            
            // Scroll to form
            document.getElementById('deductionCard').scrollIntoView({ behavior: 'smooth' });
        }
        
        function hideDeductionForm() {
            document.getElementById('deductionCard').style.display = 'none';
        }
        
        function calculateTotalDeductions() {
            const sss = parseFloat(document.getElementById('sss_amount').value) || 0;
            const philhealth = parseFloat(document.getElementById('philhealth_amount').value) || 0;
            const pagibig = parseFloat(document.getElementById('pagibig_amount').value) || 0;
            
            const total = sss + philhealth + pagibig;
            document.getElementById('total_deductions_display').innerText = '₱' + total.toFixed(2);
        }

        // Bulk selection functionality
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const employeeChecks = document.querySelectorAll('.employee-check');
            
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    employeeChecks.forEach(check => {
                        check.checked = selectAll.checked;
                    });
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Real-time employee code validation
    function checkEmployeeCode() {
        const employeeCode = document.getElementById('employee_code').value;
        const feedback = document.getElementById('code_feedback');
        
        if (employeeCode.length < 3) {
            feedback.innerHTML = '<small class="text-warning">Employee code must be at least 3 characters</small>';
            return;
        }
        
        // AJAX check for duplicate
        fetch('check_employee_code.php?code=' + encodeURIComponent(employeeCode))
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    feedback.innerHTML = '<small class="text-danger">❌ This employee code already exists!</small>';
                } else {
                    feedback.innerHTML = '<small class="text-success">✅ Code available</small>';
                }
            })
            .catch(error => {
                console.error('Error checking employee code:', error);
            });
    }

    // Add event listener for real-time checking
    document.addEventListener('DOMContentLoaded', function() {
        const codeInput = document.getElementById('employee_code');
        if (codeInput) {
            codeInput.addEventListener('blur', checkEmployeeCode);
            codeInput.addEventListener('input', function() {
                document.getElementById('code_feedback').innerHTML = '';
            });
        }
    });
    </script>
</body>
</html>