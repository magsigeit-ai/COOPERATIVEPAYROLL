<?php
include 'config/database.php';

// Get employee ID from URL
$employee_id = $_GET['employee_id'] ?? 0;

// Get employee details
$employee_sql = "SELECT * FROM employees WHERE employee_id = ?";
$employee_stmt = $conn->prepare($employee_sql);
$employee_stmt->bind_param("i", $employee_id);
$employee_stmt->execute();
$employee = $employee_stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("Employee not found!");
}

// Get rate history
$history_sql = "SELECT * FROM employee_rate_history 
                WHERE employee_id = ? 
                ORDER BY effective_date DESC, created_at DESC";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $employee_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate History - Cooperative Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #007bff;
            transform: translateX(-50%);
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        .timeline-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            width: 45%;
        }
        .timeline-item:nth-child(odd) .timeline-content {
            float: left;
        }
        .timeline-item:nth-child(even) .timeline-content {
            float: right;
        }
        .timeline-content::after {
            content: '';
            position: absolute;
            top: 20px;
            width: 20px;
            height: 20px;
            background: #007bff;
            border-radius: 50%;
        }
        .timeline-item:nth-child(odd) .timeline-content::after {
            right: -10px;
        }
        .timeline-item:nth-child(even) .timeline-content::after {
            left: -10px;
        }
        .rate-increase { border-left: 4px solid #28a745; }
        .rate-decrease { border-left: 4px solid #dc3545; }
        .rate-same { border-left: 4px solid #6c757d; }
        .employee-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1"><i class="fas fa-history"></i> Rate History</span>
            <div>
                <a href="employees.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back to Employees</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Employee Header -->
        <div class="employee-header text-center">
            <h2><?php echo $employee['full_name']; ?></h2>
            <p class="lead mb-1"><?php echo $employee['employee_code']; ?> - <?php echo $employee['designation']; ?></p>
            <h3 class="mb-0">Current Rate: ₱<?php echo number_format($employee['base_daily_rate'], 2); ?></h3>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <?php echo $history_result->num_rows; ?>
                        </h5>
                        <p class="card-text">Rate Changes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            ₱<?php echo number_format($employee['base_daily_rate'], 2); ?>
                        </h5>
                        <p class="card-text">Current Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <?php 
                            $first_rate_sql = "SELECT new_rate FROM employee_rate_history 
                                             WHERE employee_id = ? 
                                             ORDER BY effective_date ASC 
                                             LIMIT 1";
                            $first_stmt = $conn->prepare($first_rate_sql);
                            $first_stmt->bind_param("i", $employee_id);
                            $first_stmt->execute();
                            $first_rate = $first_stmt->get_result()->fetch_assoc();
                            echo $first_rate ? '₱' . number_format($first_rate['new_rate'], 2) : 'N/A';
                            ?>
                        </h5>
                        <p class="card-text">Starting Rate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <?php
                            $increase_sql = "SELECT COUNT(*) as increases FROM employee_rate_history 
                                           WHERE employee_id = ? AND new_rate > old_rate";
                            $increase_stmt = $conn->prepare($increase_sql);
                            $increase_stmt->bind_param("i", $employee_id);
                            $increase_stmt->execute();
                            $increases = $increase_stmt->get_result()->fetch_assoc();
                            echo $increases['increases'];
                            ?>
                        </h5>
                        <p class="card-text">Rate Increases</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rate History Timeline -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-timeline"></i> Rate Change Timeline</h5>
            </div>
            <div class="card-body">
                <?php if ($history_result->num_rows > 0): ?>
                    <div class="timeline">
                        <?php while ($history = $history_result->fetch_assoc()): 
                            $change_class = '';
                            if ($history['new_rate'] > $history['old_rate']) {
                                $change_class = 'rate-increase';
                            } elseif ($history['new_rate'] < $history['old_rate']) {
                                $change_class = 'rate-decrease';
                            } else {
                                $change_class = 'rate-same';
                            }
                            
                            $change_amount = $history['new_rate'] - $history['old_rate'];
                            $change_percentage = $history['old_rate'] > 0 ? 
                                (($change_amount / $history['old_rate']) * 100) : 0;
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-content <?php echo $change_class; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <?php if ($change_amount > 0): ?>
                                                <i class="fas fa-arrow-up text-success"></i>
                                                Increase
                                            <?php elseif ($change_amount < 0): ?>
                                                <i class="fas fa-arrow-down text-danger"></i>
                                                Decrease
                                            <?php else: ?>
                                                <i class="fas fa-equals text-secondary"></i>
                                                No Change
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1">
                                            <strong>From:</strong> ₱<?php echo number_format($history['old_rate'], 2); ?><br>
                                            <strong>To:</strong> ₱<?php echo number_format($history['new_rate'], 2); ?>
                                        </p>
                                        <?php if ($change_amount != 0): ?>
                                            <span class="badge 
                                                <?php echo $change_amount > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $change_amount > 0 ? '+' : ''; ?>
                                                ₱<?php echo number_format(abs($change_amount), 2); ?>
                                                (<?php echo number_format(abs($change_percentage), 1); ?>%)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($history['effective_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <p class="mb-1"><strong>Reason:</strong> <?php echo $history['reason']; ?></p>
                                    <?php if (!empty($history['notes'])): ?>
                                        <p class="mb-1"><strong>Notes:</strong> <?php echo $history['notes']; ?></p>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Changed by: <?php echo $history['changed_by']; ?> 
                                        on <?php echo date('M d, Y', strtotime($history['change_date'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div style="clear: both;"></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5>No Rate History Found</h5>
                        <p class="text-muted">This employee has no rate change history.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-4">
            <a href="employees.php?action=change_rate&employee_id=<?php echo $employee_id; ?>" 
               class="btn btn-warning">
               <i class="fas fa-edit"></i> Change Current Rate
            </a>
            <a href="employees.php" class="btn btn-secondary">
                <i class="fas fa-users"></i> Back to All Employees
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>