
<?php
$type = $_GET['type'] ?? 'time_records';

if ($type == 'time_records') {
    $filename = "time_records_template.csv";
    $data = "employee_code,work_date,regular_hours,overtime_hours,night_hours,late_minutes\n" .
            "FARM001,2024-01-15,8,2,0,15\n" .
            "DRIV002,2024-01-15,8,0,3,0\n" .
            "FARM003,2024-01-15,8,1,2,30\n" .
            "DRIV001,2024-01-15,4,0,0,0\n" .
            "FARM002,2024-01-15,8,0,0,10";
} elseif ($type == 'employee_data') {
    $filename = "employee_data_template.csv";
    $data = "employee_code,full_name,designation,category,base_daily_rate\n" .
            "FARM001,Juan Dela Cruz,Farmer,CAT 2,505.00\n" .
            "DRIV002,Maria Santos,Driver,CAT 1,510.00\n" .
            "FARM003,Pedro Reyes,Farmer,CAT 2,505.00\n" .
            "DRIV001,Antonio Lim,Driver,CAT 1,510.00\n" .
            "FARM002,Roberto Garcia,Farmer,CAT 2,505.00";
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $data;
exit;
?>
