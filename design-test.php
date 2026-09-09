<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Marcid Blue - Design Test</title>

    <link rel="stylesheet" href="assets/css/app.css">
</head>

<body>

<div class="app">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <div class="brand-icon">💧</div>
            <div class="brand-text">MARCID BLUE</div>
        </div>

        <nav class="sidebar-nav">

            <div class="nav-section-title">
                Main
            </div>

            <a href="#" class="nav-item active">
                🏠
                <span>Home</span>
            </a>

            <a href="#" class="nav-item">
                👥
                <span>Customers</span>
            </a>

            <a href="#" class="nav-item">
                📅
                <span>Daily Records</span>
            </a>

            <a href="#" class="nav-item">
                🧾
                <span>Daily Closing</span>
            </a>

            <div class="nav-section-title" style="margin-top: 25px;">
                System
            </div>

            <a href="#" class="nav-item">
                ⚙️
                <span>Settings</span>
            </a>

            <a href="#" class="nav-item">
                🚪
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- Main Content -->
    <main class="main">

        <!-- Header -->
        <header class="topbar">

            <div class="topbar-title">
                Dashboard
            </div>

            <div class="topbar-user">
                👤 Marcid Blue Admin
            </div>

        </header>


        <!-- Page -->
        <section class="page">

            <div class="page-header">

                <h1 class="page-title">
                    Good afternoon, Admin
                </h1>

                <p class="page-subtitle">
                    Wednesday, September 9, 2026
                </p>

            </div>


            <!-- Summary -->
            <div class="summary-grid">

                <div class="card summary-card">

                    <div class="summary-label">
                        Today's Sales
                    </div>

                    <div class="summary-value">
                        ₱3,750
                    </div>

                    <div class="summary-description">
                        Walk-ins + deliveries
                    </div>

                </div>


                <div class="card summary-card">

                    <div class="summary-label">
                        Money Received
                    </div>

                    <div class="summary-value">
                        ₱3,000
                    </div>

                    <div class="summary-description">
                        Payments received today
                    </div>

                </div>


                <div class="card summary-card">

                    <div class="summary-label">
                        Outstanding Debt
                    </div>

                    <div class="summary-value">
                        ₱750
                    </div>

                    <div class="summary-description">
                        Unpaid customer balance
                    </div>

                </div>


                <div class="card summary-card">

                    <div class="summary-label">
                        Expenses
                    </div>

                    <div class="summary-value">
                        ₱500
                    </div>

                    <div class="summary-description">
                        Today's expenses
                    </div>

                </div>

            </div>


            <!-- Daily Closing -->
            <div class="card">

                <div class="card-header">

                    <div class="card-title">
                        Daily Closing
                    </div>

                </div>

                <div class="card-body">

                    <p class="page-subtitle">
                        Review today's transactions and complete the daily closing.
                    </p>

                    <br>

                    <button class="btn btn-primary">
                        Open Daily Closing
                    </button>

                    <button class="btn btn-outline">
                        View Records
                    </button>

                </div>

            </div>


            <br>


            <!-- Status Test -->
            <div class="card">

                <div class="card-header">
                    <div class="card-title">
                        Status Preview
                    </div>
                </div>

                <div class="card-body">

                    <span class="badge badge-success">
                        Saved
                    </span>

                    <span class="badge badge-warning">
                        Open
                    </span>

                    <span class="badge badge-danger">
                        Discrepancy
                    </span>

                    <span class="badge badge-primary">
                        Debt
                    </span>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="assets/js/app.js"></script>

</body>
</html>