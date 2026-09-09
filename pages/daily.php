<?php

date_default_timezone_set('Asia/Manila');

require_once '../config/database.php';

$today = date('Y-m-d');

// --------------------------------------------------
// 1. Get or create today's daily record
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM daily_records
    WHERE business_date = ?
");

$stmt->execute([$today]);

$dailyRecord = $stmt->fetch();

if (!$dailyRecord) {

    $stmt = $pdo->prepare("
        INSERT INTO daily_records (business_date, status)
        VALUES (?, 'Open')
    ");

    $stmt->execute([$today]);

    $dailyId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT *
        FROM daily_records
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $dailyRecord = $stmt->fetch();
}

$dailyId = $dailyRecord['daily_id'];


// --------------------------------------------------
// 2. Get today's walk-in sales record
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT *
    FROM daily_sales
    WHERE daily_id = ?
");

$stmt->execute([$dailyId]);

$dailySales = $stmt->fetch();


// --------------------------------------------------
// 3. Default walk-in values
// --------------------------------------------------

$walkInCustomers = $dailySales['walk_in_customers'] ?? 0;
$walkInPrice = $dailySales['walk_in_price'] ?? 30.00;

$walkInSales = $walkInCustomers * $walkInPrice;


// --------------------------------------------------
// 4. Handle form submission
// --------------------------------------------------

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $inputCustomers = trim($_POST['walk_in_customers'] ?? '');
    $inputMoney = trim($_POST['walk_in_money'] ?? '');

    $customers = null;
    $money = null;

    // ----------------------------------------------
    // If customers were entered
    // ----------------------------------------------

    if ($inputCustomers !== '') {

        if (!ctype_digit($inputCustomers)) {
            $message = 'Walk-in customers must be a whole number.';
        } else {
            $customers = (int) $inputCustomers;
            $money = $customers * $walkInPrice;
        }
    }

    // ----------------------------------------------
    // If money was entered instead
    // ----------------------------------------------

    elseif ($inputMoney !== '') {

        if (!is_numeric($inputMoney) || $inputMoney < 0) {
            $message = 'Walk-in money must be a valid amount.';
        } else {

            $money = (float) $inputMoney;

            $calculatedCustomers = $money / $walkInPrice;

            // Make sure money divides evenly by ₱30
            if (fmod($money, $walkInPrice) != 0) {

                $message = 'The walk-in money does not divide evenly by ₱30. Please check the amount.';

            } else {

                $customers = (int) $calculatedCustomers;
            }
        }
    }

    else {
        $message = 'Enter either the number of customers or the money received.';
    }


    // ----------------------------------------------
    // Save if everything is valid
    // ----------------------------------------------

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
                $today,
                $customers,
                $walkInPrice,
                $dailyId
            ]);
        }

        $walkInCustomers = $customers;
        $walkInSales = $customers * $walkInPrice;

        $message = 'Walk-in sales saved successfully.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daily Record - Marcid Blue</title>

</head>

<body>

    <h1>Marcid Blue Water Station</h1>

    <h2>Daily Record</h2>

    <p>
        Date:
        <strong>
            <?= htmlspecialchars($dailyRecord['business_date']) ?>
        </strong>
    </p>

    <p>
        Status:
        <strong>
            <?= htmlspecialchars($dailyRecord['status']) ?>
        </strong>
    </p>


    <hr>


    <h2>Walk-in Sales</h2>

    <?php if ($message !== ''): ?>

        <p>
            <strong>
                <?= htmlspecialchars($message) ?>
            </strong>
        </p>

    <?php endif; ?>


    <form method="POST">

        <div>

            <label for="walk_in_customers">
                Walk-in Customers
            </label>

            <input
                type="number"
                id="walk_in_customers"
                name="walk_in_customers"
                min="0"
                step="1"
                placeholder="Example: 100"
            >

        </div>


        <br>


        <div>

            <label for="walk_in_money">
                OR Walk-in Money Received
            </label>

            <input
                type="number"
                id="walk_in_money"
                name="walk_in_money"
                min="0"
                step="0.01"
                placeholder="Example: 3000"
            >

        </div>


        <br>


        <p>
            Price per Customer:
            <strong>
                ₱<?= number_format($walkInPrice, 2) ?>
            </strong>
        </p>


        <p>
            Walk-in Customers:
            <strong>
                <?= number_format($walkInCustomers) ?>
            </strong>
        </p>


        <p>
            Walk-in Sales:
            <strong>
                ₱<?= number_format($walkInSales, 2) ?>
            </strong>
        </p>


        <button type="submit">
            Save Walk-in Sales
        </button>

    </form>

</body>

</html>