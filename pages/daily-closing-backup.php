```php
<?php

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();


// ==================================================
// 1. FIND ACTIVE DAILY CLOSING
// ==================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM daily_records
    WHERE status = 'Open'
    ORDER BY daily_id DESC
    LIMIT 1
");

$stmt->execute();

$dailyRecord = $stmt->fetch();

$dailyId = $dailyRecord['daily_id'] ?? null;
$businessDate = $dailyRecord['business_date'] ?? null;


// ==================================================
// 2. DEFAULT VALUES
// ==================================================

$message = '';
$messageType = '';

$walkInCustomers = 0;
$walkInPrice = 30.00;
$walkInSales = 0.00;

$totalDeliverySales = 0.00;
$totalDeliveryPayments = 0.00;
$totalOutstanding = 0.00;

$totalExpenses = 0.00;

$totalSales = 0.00;
$netSalesAfterExpenses = 0.00;


// ==================================================
// 3. GET WALK-IN SALES
// ==================================================

$dailySales = null;

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM daily_sales
        WHERE daily_id = ?
        LIMIT 1
    ");

    $stmt->execute([$dailyId]);

    $dailySales = $stmt->fetch();

    if ($dailySales) {

        $walkInCustomers =
            (int) ($dailySales['walk_in_customers'] ?? 0);

        $walkInPrice =
            (float) ($dailySales['walk_in_price'] ?? 30.00);

        $walkInSales =
            $walkInCustomers * $walkInPrice;
    }
}


// ==================================================
// 4. GET DELIVERY SALES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount_due), 0) AS total_delivery_sales
        FROM deliveries
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result = $stmt->fetch();

    $totalDeliverySales =
        (float) ($result['total_delivery_sales'] ?? 0);
}


// ==================================================
// 5. GET DELIVERY PAYMENTS
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0) AS total_delivery_payments
        FROM payments
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result = $stmt->fetch();

    $totalDeliveryPayments =
        (float) ($result['total_delivery_payments'] ?? 0);
}


// ==================================================
// 6. CALCULATE OUTSTANDING DELIVERY BALANCE
// ==================================================

$totalOutstanding =
    $totalDeliverySales -
    $totalDeliveryPayments;

if ($totalOutstanding < 0) {
    $totalOutstanding = 0;
}


// ==================================================
// 7. GET EXPENSES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0) AS total_expenses
        FROM expenses
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result = $stmt->fetch();

    $totalExpenses =
        (float) ($result['total_expenses'] ?? 0);
}


// ==================================================
// 8. SAVE WALK-IN SALES
// ==================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $dailyId !== null
    && ($_POST['action'] ?? '') === 'save_walk_in'
) {

    $inputCustomers =
        trim($_POST['walk_in_customers'] ?? '');

    $inputMoney =
        trim($_POST['walk_in_money'] ?? '');

    $customers = null;
    $money = null;


    // --------------------------------------------------
    // CUSTOMER COUNT ENTERED
    // --------------------------------------------------

    if ($inputCustomers !== '') {

        if (!ctype_digit($inputCustomers)) {

            $message =
                'Walk-in customers must be a whole number.';

            $messageType = 'danger';

        } else {

            $customers =
                (int) $inputCustomers;

            $money =
                $customers * $walkInPrice;
        }
    }


    // --------------------------------------------------
    // MONEY ENTERED
    // --------------------------------------------------

    elseif ($inputMoney !== '') {

        if (
            !is_numeric($inputMoney)
            || (float) $inputMoney < 0
        ) {

            $message =
                'Walk-in money must be a valid amount.';

            $messageType = 'danger';

        } else {

            $money =
                (float) $inputMoney;

            $calculatedCustomers =
                $money / $walkInPrice;


            // Make sure the money divides evenly
            // by the walk-in price.

            if (
                abs(
                    $calculatedCustomers
                    - round($calculatedCustomers)
                ) > 0.000001
            ) {

                $message =
                    'The walk-in money does not divide evenly by ₱'
                    . number_format($walkInPrice, 2)
                    . '. Please check the amount.';

                $messageType = 'danger';

            } else {

                $customers =
                    (int) round($calculatedCustomers);
            }
        }
    }


    // --------------------------------------------------
    // NOTHING ENTERED
    // --------------------------------------------------

    else {

        $message =
            'Enter either the number of customers or the money received.';

        $messageType = 'warning';
    }


    // --------------------------------------------------
    // SAVE
    // --------------------------------------------------

    if ($message === '') {

        if ($dailySales) {

            $stmt = $pdo->prepare("
                UPDATE daily_sales
                SET walk_in_customers = ?,
                    walk_in_price = ?
                WHERE daily_sales_id = ?
            ");

            $stmt->execute([
                $customers,
                $walkInPrice,
                $dailySales['daily_sales_id']
            ]);

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO daily_sales (
                    sales_date,
                    walk_in_customers,
                    walk_in_price,
                    daily_id
                )
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessDate,
                $customers,
                $walkInPrice,
                $dailyId
            ]);
        }


        $walkInCustomers =
            $customers;

        $walkInSales =
            $customers * $walkInPrice;


        $message =
            'Walk-in sales saved successfully.';

        $messageType =
            'success';
    }
}


// ==================================================
// 9. FINAL DAILY SALES TOTALS
// ==================================================

$totalSales =
    $walkInSales +
    $totalDeliverySales;


$netSalesAfterExpenses =
    $totalSales -
    $totalExpenses;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Daily Closing - Marcid Blue</title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

</head>

<body>

<div class="app">


    <!-- =================================================
         SIDEBAR
         ================================================= -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <img
                src="../assets/images/mb-logo.png"
                alt="Marcid Blue Logo"
            >

        </div>


        <nav class="sidebar-nav">

            <div class="nav-section-title">
                Main
            </div>


            <a
                href="home.php"
                class="nav-item"
            >
                🏠
                <span>Home</span>
            </a>


            <a
                href="#"
                class="nav-item"
            >
                👥
                <span>Customers</span>
            </a>


            <a
                href="#"
                class="nav-item"
            >
                📅
                <span>Daily Records</span>
            </a>


            <a
                href="daily-closing.php"
                class="nav-item active"
            >
                🧾
                <span>Daily Closing</span>
            </a>


            <div
                class="nav-section-title"
                style="margin-top: 25px;"
            >
                System
            </div>


            <a
                href="#"
                class="nav-item"
            >
                ⚙️
                <span>Settings</span>
            </a>


            <a
                href="#"
                class="nav-item"
            >
                🚪
                <span>Logout</span>
            </a>

        </nav>

    </aside>



    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-title">
                Daily Closing
            </div>


            <div class="topbar-user">

                👤

                <?php
                echo htmlspecialchars(
                    $_SESSION['full_name'] ?? 'Admin'
                );
                ?>

            </div>

        </header>



        <!-- PAGE -->

        <section class="page">


            <!-- =================================================
                 PAGE HEADER
                 ================================================= -->

            <div class="page-header">

                <h1 class="page-title">
                    Daily Closing
                </h1>


                <p class="page-subtitle">

                    <?php if ($businessDate): ?>

                        <?= date(
                            'l, F j, Y',
                            strtotime($businessDate)
                        ) ?>

                    <?php else: ?>

                        No active daily closing

                    <?php endif; ?>

                </p>

            </div>



            <?php if ($dailyRecord): ?>


                <!-- =================================================
                     DAILY STATUS
                     ================================================= -->

                <div class="card">

                    <div class="card-body">

                        <div
                            style="
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                gap: 20px;
                                flex-wrap: wrap;
                            "
                        >

                            <div>

                                <div class="summary-label">
                                    Active Daily Record
                                </div>


                                <div
                                    style="
                                        font-size: 20px;
                                        font-weight: 700;
                                    "
                                >

                                    <?= htmlspecialchars(
                                        $businessDate
                                    ) ?>

                                </div>

                            </div>


                            <div>

                                <span class="badge badge-warning">
                                    Open
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <br>



                <!-- =================================================
                     WALK-IN SALES
                     ================================================= -->

                <div class="card">

                    <div class="card-header">

                        <div class="card-title">
                            Walk-in Sales
                        </div>

                    </div>


                    <div class="card-body">


                        <?php if ($message !== ''): ?>

                            <div
                                class="alert alert-<?= htmlspecialchars(
                                    $messageType
                                ) ?>"
                            >

                                <?= htmlspecialchars($message) ?>

                            </div>

                        <?php endif; ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="save_walk_in"
                            >


                            <div class="summary-grid">


                                <!-- CUSTOMERS -->

                                <div>

                                    <label
                                        for="walk_in_customers"
                                        class="form-label"
                                    >
                                        Walk-in Customers
                                    </label>


                                    <input
                                        type="number"
                                        id="walk_in_customers"
                                        name="walk_in_customers"
                                        class="form-input"
                                        min="0"
                                        step="1"
                                        placeholder="Example: 150"
                                    >

                                </div>



                                <!-- MONEY -->

                                <div>

                                    <label
                                        for="walk_in_money"
                                        class="form-label"
                                    >
                                        Walk-in Money
                                    </label>


                                    <input
                                        type="number"
                                        id="walk_in_money"
                                        name="walk_in_money"
                                        class="form-input"
                                        min="0"
                                        step="0.01"
                                        placeholder="Example: 4500"
                                    >

                                </div>



                                <!-- PRICE -->

                                <div>

                                    <div class="summary-label">
                                        Price per Customer
                                    </div>


                                    <div class="summary-value">

                                        ₱<?= number_format(
                                            $walkInPrice,
                                            2
                                        ) ?>

                                    </div>

                                </div>



                                <!-- SALES -->

                                <div>

                                    <div class="summary-label">
                                        Walk-in Sales
                                    </div>


                                    <div class="summary-value">

                                        ₱<?= number_format(
                                            $walkInSales,
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            </div>



                            <div
                                style="
                                    display: flex;
                                    justify-content: flex-end;
                                    margin-top: 10px;
                                "
                            >

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Save Walk-in Sales
                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                <br>



                <!-- =================================================
                     DAILY SUMMARY
                     ================================================= -->

                <div class="summary-grid">


                    <!-- WALK-IN -->

                    <div class="card summary-card">

                        <div class="summary-label">
                            Walk-in Sales
                        </div>


                        <div class="summary-value">

                            ₱<?= number_format(
                                $walkInSales,
                                2
                            ) ?>

                        </div>


                        <div class="summary-description">

                            <?= number_format(
                                $walkInCustomers
                            ) ?>

                            customers ×

                            ₱<?= number_format(
                                $walkInPrice,
                                2
                            ) ?>

                        </div>

                    </div>



                    <!-- DELIVERY SALES -->

                    <div class="card summary-card">

                        <div class="summary-label">
                            Delivery Sales
                        </div>


                        <div class="summary-value">

                            ₱<?= number_format(
                                $totalDeliverySales,
                                2
                            ) ?>

                        </div>


                        <div class="summary-description">
                            Total delivery amount due
                        </div>

                    </div>



                    <!-- DELIVERY PAYMENTS -->

                    <div class="card summary-card">

                        <div class="summary-label">
                            Delivery Payments
                        </div>


                        <div class="summary-value">

                            ₱<?= number_format(
                                $totalDeliveryPayments,
                                2
                            ) ?>

                        </div>


                        <div class="summary-description">
                            Payments recorded today
                        </div>

                    </div>



                    <!-- OUTSTANDING -->

                    <div class="card summary-card">

                        <div class="summary-label">
                            Outstanding Debt
                        </div>


                        <div class="summary-value">

                            ₱<?= number_format(
                                $totalOutstanding,
                                2
                            ) ?>

                        </div>


                        <div class="summary-description">
                            Unpaid delivery balance
                        </div>

                    </div>

                </div>


                <br>



                <!-- =================================================
                     DAILY RECONCILIATION
                     ================================================= -->

                <div class="card">

                    <div class="card-header">

                        <div class="card-title">
                            Daily Reconciliation
                        </div>

                    </div>


                    <div class="card-body">


                        <div class="summary-grid">


                            <!-- TOTAL SALES -->

                            <div>

                                <div class="summary-label">
                                    Total Sales
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $totalSales,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Walk-in + deliveries
                                </div>

                            </div>



                            <!-- EXPENSES -->

                            <div>

                                <div class="summary-label">
                                    Station Expenses
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $totalExpenses,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Expenses recorded today
                                </div>

                            </div>



                            <!-- NET SALES -->

                            <div>

                                <div class="summary-label">
                                    Net Sales
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $netSalesAfterExpenses,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Sales minus expenses
                                </div>

                            </div>



                            <!-- DEBT -->

                            <div>

                                <div class="summary-label">
                                    Delivery Debt
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $totalOutstanding,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Amount still unpaid
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <br>



                <!-- =================================================
                     DELIVERY OVERVIEW
                     ================================================= -->

                <div class="card">

                    <div class="card-header">

                        <div class="card-title">
                            Delivery Overview
                        </div>

                    </div>


                    <div class="card-body">

                        <div class="summary-grid">


                            <div>

                                <div class="summary-label">
                                    Delivery Sales
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $totalDeliverySales,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Total amount due
                                </div>

                            </div>



                            <div>

                                <div class="summary-label">
                                    Payments Received
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $totalDeliveryPayments,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Payments linked to deliveries
                                </div>

                            </div>



                            <div>

                                <div class="summary-label">
                                    Outstanding
                                </div>


                                <div class="summary-value">

                                    ₱<?= number_format(
                                        $totalOutstanding,
                                        2
                                    ) ?>

                                </div>


                                <div class="summary-description">
                                    Customer balance remaining
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


            <?php else: ?>


                <!-- =================================================
                     NO ACTIVE DAILY CLOSING
                     ================================================= -->

                <div class="card">

                    <div class="card-body">

                        <div
                            style="
                                text-align: center;
                                padding: 30px 10px;
                            "
                        >

                            <div
                                style="
                                    font-size: 40px;
                                    margin-bottom: 15px;
                                "
                            >
                                🧾
                            </div>


                            <div
                                style="
                                    font-size: 20px;
                                    font-weight: 700;
                                    margin-bottom: 8px;
                                "
                            >
                                No Active Daily Closing
                            </div>


                            <p class="page-subtitle">
                                There is currently no open daily
                                record.
                            </p>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<script src="../assets/js/app.js"></script>

</body>

</html>
```
