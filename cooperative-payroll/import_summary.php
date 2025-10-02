
<?php
include 'config/database.php';

// Get latest import stats
$latest_imports = $conn->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(regular_hours) as total_regular,
        SUM(overtime_hours) as total_overtime,
        SUM(night_differential_hours) as total_night,
        SUM(late_minutes) as total_late,
        MAX(work_date) as latest_date,
        MIN(work_date) as earliest_date
    FROM time_records 
    WHERE work_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc();

$recent_employees = $conn->query("
    SELECT COUNT(DISTINCT employee_id) as unique_employees
    FROM time_records 
    WHERE work_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc();
?>

<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Recent Import Summary</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="text-primary fw-bold"><?php echo $latest_imports['total_records']; ?></div>
                <small>Records Imported</small>
            </div>
            <div class="col-md-3">
                <div class="text-success fw-bold"><?php echo $recent_employees['unique_employees']; ?></div>
                <small>Unique Employees</small>
            </div>
            <div class="col-md-3">
                <div class="text-warning fw-bold"><?php echo number_format($latest_imports['total_regular'], 1); ?>h</div>
                <small>Regular Hours</small>
            </div>
            <div class="col-md-3">
                <div class="text-danger fw-bold"><?php echo $latest_imports['total_late']; ?>m</div>
                <small>Late Minutes</small>
            </div>
        </div>
        <?php if ($latest_imports['earliest_date']): ?>
        <div class="mt-2 text-center">
            <small class="text-muted">
                Period: <?php echo date('M d', strtotime($latest_imports['earliest_date'])); ?> - 
                <?php echo date('M d, Y', strtotime($latest_imports['latest_date'])); ?>
            </small>
        </div>
        <?php endif; ?>
    </div>
</div>
