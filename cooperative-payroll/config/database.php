
<?php
// Set Philippine timezone
date_default_timezone_set('Asia/Manila');
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'cooperative_payroll';

// Create connection
$conn = new mysqli($host, $user, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($database);

// echo "Database connected successfully!<br>";
?>