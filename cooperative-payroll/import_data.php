
<?php
include 'config/database.php';

$success = '';
$error = '';
$import_stats = [];

// Handle file upload
if ($_POST['action'] == 'upload_data') {
    $upload_type = $_POST['upload_type'];
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];
    
    if (isset($_FILES['data_file']) && $_FILES['data_file']['error'] == 0) {
        $file = $_FILES['data_file']['tmp_name'];
        $file_type = pathinfo($_FILES['data_file']['name'], PATHINFO_EXTENSION);
        
        if ($file_type == 'csv' || $file_type == 'xlsx' || $file_type == 'xls') {
            // Process the file based on type
            if ($upload_type == 'time_records') {
                $import_stats = importTimeRecords($file, $file_type, $pay_period_start, $pay_period_end);
            } elseif ($upload_type == 'employee_data') {
                $import_stats = importEmployeeData($file, $file_type);
            }
            
            if ($import_stats['success_count'] > 0) {
                $success = "✅ Data imported successfully!<br>";
                $success .= "📊 Success: {$import_stats['success_count']} | ";
                $success .= "⏭️ Skipped: {$import_stats['skip_count']} | ";
                $success .= "❌ Errors: {$import_stats['error_count']}";
            } else {
                $error = "❌ No data was imported. Please check your file format.";
            }
        } else {
            $error = "❌ Please upload a CSV or Excel file.";
        }
    } else {
        $error = "❌ Please select a file to upload.";
    }
}

// Function to import time records
function importTimeRecords($file, $file_type, $start_date, $end_date) {
    global $conn;
    
    $stats = ['success_count' => 0, 'skip_count' => 0, 'error_count' => 0];
    $processed_employees = [];
    
    // Read CSV file
    if ($file_type == 'csv') {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle); // Skip header
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 5) {
                    $employee_code = trim($data[0]);
                    $work_date = trim($data[1]);
                    $regular_hours = floatval($data[2]);
                    $overtime_hours = floatval($data[3]);
                    $night_hours = floatval($data[4]);
                    $late_minutes = isset($data[5]) ? intval($data[5]) : 0;
                    
                    // Validate date is within pay period
                    if ($work_date < $start_date || $work_date > $end_date) {
                        $stats['skip_count']++;
                        continue;
                    }
                    
                    // Get employee ID
                    $employee_id = getEmployeeIdByCode($employee_code);
                    if ($employee_id) {
                        // Check if record already exists
                        $check_sql = "SELECT time_id FROM time_records WHERE employee_id = ? AND work_date = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("is", $employee_id, $work_date);
                        $check_stmt->execute();
                        $existing = $check_stmt->get_result()->fetch_assoc();
                        
                        if ($existing) {
                            // Update existing record
                            $sql = "UPDATE time_records SET regular_hours = ?, overtime_hours = ?, night_differential_hours = ?, late_minutes = ? WHERE time_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ddddi", $regular_hours, $overtime_hours, $night_hours, $late_minutes, $existing['time_id']);
                        } else {
                            // Insert new record
                            $sql = "INSERT INTO time_records (employee_id, work_date, regular_hours, overtime_hours, night_differential_hours, late_minutes) VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("isdddd", $employee_id, $work_date, $regular_hours, $overtime_hours, $night_hours, $late_minutes);
                        }
                        
                        if ($stmt->execute()) {
                            $stats['success_count']++;
                            $processed_employees[$employee_code] = true;
                        } else {
                            $stats['error_count']++;
                        }
                    } else {
                        $stats['error_count']++;
                    }
                }
            }
            fclose($handle);
        }
    }
    
    return $stats;
}

// Function to import employee data
function importEmployeeData($file, $file_type) {
    global $conn;
    
    $stats = ['success_count' => 0, 'skip_count' => 0, 'error_count' => 0];
    
    if ($file_type == 'csv') {
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle); // Skip header
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 5) {
                    $employee_code = trim($data[0]);
                    $full_name = trim($data[1]);
                    $designation = trim($data[2]);
                    $category = trim($data[3]);
                    $base_daily_rate = floatval($data[4]);
                    
                    // Check if employee already exists
                    $check_sql = "SELECT employee_id FROM employees WHERE employee_code = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("s", $employee_code);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result()->fetch_assoc();
                    
                    if ($existing) {
                        // Update existing employee
                        $sql = "UPDATE employees SET full_name = ?, designation = ?, category = ?, base_daily_rate = ? WHERE employee_code = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssds", $full_name, $designation, $category, $base_daily_rate, $employee_code);
                    } else {
                        // Insert new employee
                        $sql = "INSERT INTO employees (employee_code, full_name, designation, category, base_daily_rate, status) VALUES (?, ?, ?, ?, ?, 'Active')";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssd", $employee_code, $full_name, $designation, $category, $base_daily_rate);
                    }
                    
                    if ($stmt->execute()) {
                        $stats['success_count']++;
                        
                        // Add to rate history if new employee
                        if (!$existing) {
                            $new_employee_id = $stmt->insert_id;
                            $rate_sql = "INSERT INTO employee_rate_history (employee_id, old_rate, new_rate, change_date, effective_date, reason) VALUES (?, 0, ?, CURDATE(), CURDATE(), 'Initial Import')";
                            $rate_stmt = $conn->prepare($rate_sql);
                            $rate_stmt->bind_param("id", $new_employee_id, $base_daily_rate);
                            $rate_stmt->execute();
                        }
                    } else {
                        $stats['error_count']++;
                    }
                }
            }
            fclose($handle);
        }
    }
    
    return $stats;
}

// Helper function to get employee ID by code
function getEmployeeIdByCode($employee_code) {
    global $conn;
    
    $sql = "SELECT employee_id FROM employees WHERE employee_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $employee_code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['employee_id'] : null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Import - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 3px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            margin: 20px 0;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #0056b3;
        }
        .template-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card {
            background: #28a745;
            color: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">📤 Data Import System</span>
            <div>
                <a href="payroll.php" class="btn btn-light me-2">💰 Payroll</a>
                <a href="index.php" class="btn btn-light">🏠 Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Import Statistics -->
        <?php if (!empty($import_stats) && $import_stats['success_count'] > 0): ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card bg-success">
                    <h3><?php echo $import_stats['success_count']; ?></h3>
                    <small>Successfully Imported</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card bg-warning">
                    <h3><?php echo $import_stats['skip_count']; ?></h3>
                    <small>Skipped Records</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card bg-danger">
                    <h3><?php echo $import_stats['error_count']; ?></h3>
                    <small>Errors</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Upload Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-upload"></i> Upload Data File</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="action" value="upload_data">
                            
                            <div class="mb-3">
                                <label class="form-label">Import Type:</label>
                                <select name="upload_type" class="form-select" required onchange="toggleDateFields()">
                                    <option value="">Select Import Type</option>
                                    <option value="time_records">⏰ Time Records</option>
                                    <option value="employee_data">👥 Employee Data</option>
                                </select>
                            </div>
                            
                            <div id="dateFields" style="display: none;">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Pay Period Start:</label>
                                        <input type="date" name="pay_period_start" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pay Period End:</label>
                                        <input type="date" name="pay_period_end" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="upload-area">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5>Drop your file here or click to browse</h5>
                                <p class="text-muted">Supports CSV, Excel files</p>
                                <input type="file" name="data_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-upload"></i> Import Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Templates & Instructions -->
            <div class="col-md-6">
                <!-- Time Records Template -->
                <div class="template-card">
                    <h5><i class="fas fa-clock"></i> Time Records Template</h5>
                    <p>Format your CSV file with these columns:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-dark">
                            <thead>
                                <tr>
                                    <th>employee_code</th>
                                    <th>work_date</th>
                                    <th>regular_hours</th>
                                    <th>overtime_hours</th>
                                    <th>night_hours</th>
                                    <th>late_minutes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>FARM001</td>
                                    <td>2024-01-15</td>
                                    <td>8</td>
                                    <td>2</td>
                                    <td>0</td>
                                    <td>15</td>
                                </tr>
                                <tr>
                                    <td>DRIV002</td>
                                    <td>2024-01-15</td>
                                    <td>8</td>
                                    <td>0</td>
                                    <td>3</td>
                                    <td>0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <a href="download_template.php?type=time_records" class="btn btn-light btn-sm">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>

                <!-- Employee Data Template -->
                <div class="template-card mt-3">
                    <h5><i class="fas fa-users"></i> Employee Data Template</h5>
                    <p>Format your CSV file with these columns:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-dark">
                            <thead>
                                <tr>
                                    <th>employee_code</th>
                                    <th>full_name</th>
                                    <th>designation</th>
                                    <th>category</th>
                                    <th>base_daily_rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>FARM001</td>
                                    <td>Juan Dela Cruz</td>
                                    <td>Farmer</td>
                                    <td>CAT 2</td>
                                    <td>505.00</td>
                                </tr>
                                <tr>
                                    <td>DRIV002</td>
                                    <td>Maria Santos</td>
                                    <td>Driver</td>
                                    <td>CAT 1</td>
                                    <td>510.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <a href="download_template.php?type=employee_data" class="btn btn-light btn-sm">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>

                <!-- Quick Actions -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="payroll.php" class="btn btn-success">
                                <i class="fas fa-calculator"></i> Process Payroll After Import
                            </a>
                            <a href="timekeeping.php" class="btn btn-primary">
                                <i class="fas fa-clock"></i> View Time Records
                            </a>
                            <a href="employees.php" class="btn btn-warning">
                                <i class="fas fa-users"></i> Manage Employees
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDateFields() {
            const importType = document.querySelector('select[name="upload_type"]').value;
            const dateFields = document.getElementById('dateFields');
            
            if (importType === 'time_records') {
                dateFields.style.display = 'block';
            } else {
                dateFields.style.display = 'none';
            }
        }

        // Drag and drop functionality
        const uploadArea = document.querySelector('.upload-area');
        const fileInput = document.querySelector('input[type="file"]');

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.background = '#e9ecef';
            uploadArea.style.borderColor = '#0056b3';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.background = '#f8f9fa';
            uploadArea.style.borderColor = '#007bff';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.background = '#f8f9fa';
            uploadArea.style.borderColor = '#007bff';
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
            }
        });

        // Show file name when selected
        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                uploadArea.innerHTML = `
                    <i class="fas fa-file-alt fa-3x text-success mb-3"></i>
                    <h5>${this.files[0].name}</h5>
                    <p class="text-muted">File selected. Click "Import Data" to proceed.</p>
                    <input type="file" name="data_file" class="form-control" accept=".csv,.xlsx,.xls" required style="display: none;">
                `;
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
