<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative Payroll System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .dashboard-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            height: 180px;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row welcome-header">
            <div class="col-12 text-center">
                <h1>🏢 Cooperative Payroll System</h1>
                <p class="lead">Automated Payroll Management for Your Cooperative</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="row">
                    <!-- Timekeeping Card -->
                    <div class="col-md-3 mb-4">
                        <div class="card dashboard-card bg-success text-white">
                            <div class="card-body text-center d-flex flex-column">
                                <h5>⏰ Timekeeping</h5>
                                <p class="flex-grow-1">Daily time records entry</p>
                                <a href="timekeeping.php" class="btn btn-light mt-auto">Enter Records</a>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Card -->
                    <div class="col-md-3 mb-4">
                        <div class="card dashboard-card bg-primary text-white">
                            <div class="card-body text-center d-flex flex-column">
                                <h5>💰 Payroll</h5>
                                <p class="flex-grow-1">Process employee payroll</p>
                                <a href="payroll.php" class="btn btn-light mt-auto">Process Payroll</a>
                            </div>
                        </div>
                    </div>

                    <!-- Employees Card -->
                    <div class="col-md-3 mb-4">
                        <div class="card dashboard-card bg-warning text-dark">
                            <div class="card-body text-center d-flex flex-column">
                                <h5>👥 Employees</h5>
                                <p class="flex-grow-1">Manage worker information</p>
                                <a href="employees.php" class="btn btn-dark mt-auto">Manage</a>
                            </div>
                        </div>
                    </div>

                    <!-- Reports Card -->
                    <div class="col-md-3 mb-4">
                        <div class="card dashboard-card bg-info text-white">
                            <div class="card-body text-center d-flex flex-column">
                                <h5>📊 Reports</h5>
                                <p class="flex-grow-1">View payroll reports</p>
                                <a href="reports.php" class="btn btn-light mt-auto">View Reports</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">🚀 Quick Setup</h5>
                            </div>
                            <div class="card-body">
                                <p>First time setup? Run the database installer:</p>
                                <a href="create_tables.php" class="btn btn-success">Initialize Database</a>
                                <a href="timekeeping.php" class="btn btn-outline-primary">Start Timekeeping</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>