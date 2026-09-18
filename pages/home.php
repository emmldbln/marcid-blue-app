<?php

require_once '../auth/auth.php';

requireAdmin();

$currentPage = 'home';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcid Blue - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>

<body>
<div class="app">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="main">
        <header class="topbar">
            <div class="topbar-title">Dashboard</div>
            <div class="topbar-user">
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
            </div>
        </header>

        <section class="page">
            <div class="page-header">
                <h1 class="page-title">Good afternoon, Admin</h1>
                <p class="page-subtitle">
                    <?php echo date('l, F j, Y'); ?>
                </p>
            </div>

            <div class="summary-grid">
                <div class="card summary-card">
                    <div class="summary-label">Today's Sales</div>
                    <div class="summary-value">₱3,750</div>
                    <div class="summary-description">Walk-ins + deliveries</div>
                </div>

                <div class="card summary-card">
                    <div class="summary-label">Money Received</div>
                    <div class="summary-value">₱3,000</div>
                    <div class="summary-description">Payments received today</div>
                </div>

                <div class="card summary-card">
                    <div class="summary-label">Outstanding Debt</div>
                    <div class="summary-value">₱750</div>
                    <div class="summary-description">Unpaid customer balance</div>
                </div>

                <div class="card summary-card">
                    <div class="summary-label">Expenses</div>
                    <div class="summary-value">₱500</div>
                    <div class="summary-description">Today's expenses</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Daily Closing</div>
                </div>

                <div class="card-body">
                    <p class="page-subtitle">
                        Review today's transactions and complete the daily closing.
                    </p>
                    <br>
                    <a href="daily-closing.php" class="btn btn-primary">
                        Open Daily Closing
                    </a>
                    <a href="daily-records.php" class="btn btn-outline">
                        View Records
                    </a>
                </div>
            </div>

            <br>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Status Preview</div>
                </div>

                <div class="card-body">
                    <span class="badge badge-success">Saved</span>
                    <span class="badge badge-warning">Open</span>
                    <span class="badge badge-danger">Discrepancy</span>
                    <span class="badge badge-primary">Debt</span>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>