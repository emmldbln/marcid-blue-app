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
// 2. DEFAULTS
// ==================================================

$message = '';
$messageType = '';

$walkInCustomers = 0;
$walkInPrice = 30.00;
$walkInSales = 0.00;

$gallonDefaultPrice = 220.00;
$gallonCost = 190.00;

$totalDeliverySales = 0.00;
$totalDeliveryPayments = 0.00;
$totalDeliveryOutstanding = 0.00;

$totalGallonRevenue = 0.00;
$totalGallonCost = 0.00;
$totalGallonProfit = 0.00;

$totalExpenses = 0.00;
$totalStationExpenses = 0.00;
$totalDriverExpenses = 0.00;

$stationDeliveryPayments = 0.00;
$driverDeliveryPayments = 0.00;

$stationGallonCash = 0.00;
$driverGallonCash = 0.00;

$actualStationCash = null;

$deliveryRows = [];
$expenseRows = [];
$gallonRows = [];


// ==================================================
// 3. LOAD CUSTOMERS
// ==================================================

$customers = [];

$stmt = $pdo->query("
    SELECT
        customer_id,
        customer_name,
        contact_number,
        address,
        gallon_price
    FROM customers
    ORDER BY customer_name ASC
");

$customers = $stmt->fetchAll();


// ==================================================
// 4. SAVE DAILY CLOSING WORKSPACE
// ==================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $dailyId !== null
    && ($_POST['action'] ?? '') === 'save_workspace'
) {

    try {

        $pdo->beginTransaction();


        // ==================================================
        // WALK-IN SALES
        // ==================================================

        $inputCustomers = trim(
            $_POST['walk_in_customers'] ?? ''
        );

        $inputMoney = trim(
            $_POST['walk_in_money'] ?? ''
        );


        $customersValue = 0;


        if ($inputCustomers !== '') {

            if (
                !ctype_digit($inputCustomers)
                || (int)$inputCustomers < 0
            ) {
                throw new Exception(
                    'Walk-in customers must be a whole number.'
                );
            }

            $customersValue = (int)$inputCustomers;

        } elseif ($inputMoney !== '') {

            if (
                !is_numeric($inputMoney)
                || (float)$inputMoney < 0
            ) {
                throw new Exception(
                    'Walk-in money must be a valid amount.'
                );
            }

            $moneyValue = (float)$inputMoney;

            $calculatedCustomers =
                $moneyValue / $walkInPrice;

            if (
                abs(
                    $calculatedCustomers
                    - round($calculatedCustomers)
                ) > 0.000001
            ) {
                throw new Exception(
                    'Walk-in money must divide evenly by ₱'
                    . number_format($walkInPrice, 2)
                    . '.'
                );
            }

            $customersValue =
                (int)round($calculatedCustomers);

        }


        $stmt = $pdo->prepare("
            SELECT daily_sales_id
            FROM daily_sales
            WHERE daily_id = ?
            LIMIT 1
        ");

        $stmt->execute([$dailyId]);

        $existingDailySales = $stmt->fetch();


        if ($existingDailySales) {

            $stmt = $pdo->prepare("
                UPDATE daily_sales
                SET walk_in_customers = ?,
                    walk_in_price = ?
                WHERE daily_sales_id = ?
            ");

            $stmt->execute([
                $customersValue,
                $walkInPrice,
                $existingDailySales['daily_sales_id']
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
                $customersValue,
                $walkInPrice,
                $dailyId
            ]);
        }


        // ==================================================
        // HELPER: CUSTOMER
        // ==================================================

        $getCustomerId = function ($customerName) use ($pdo) {

            $customerName = trim($customerName);

            if ($customerName === '') {
                return null;
            }


            $stmt = $pdo->prepare("
                SELECT customer_id
                FROM customers
                WHERE LOWER(customer_name) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([$customerName]);

            $existing = $stmt->fetch();

            if ($existing) {
                return (int)$existing['customer_id'];
            }


            $stmt = $pdo->prepare("
                INSERT INTO customers (
                    customer_name,
                    gallon_price
                )
                VALUES (?, ?)
            ");

            $stmt->execute([
                $customerName,
                $gallonDefaultPrice
            ]);

            return (int)$pdo->lastInsertId();
        };


        // ==================================================
        // DELIVERY ROWS
        // ==================================================

        $deliveryIds =
            $_POST['delivery_id'] ?? [];

        $deliveryCustomers =
            $_POST['delivery_customer'] ?? [];

        $deliverySlim =
            $_POST['delivery_slim'] ?? [];

        $deliveryRound =
            $_POST['delivery_round'] ?? [];

        $deliveryPrices =
            $_POST['delivery_price'] ?? [];

        $deliveryPayments =
            $_POST['delivery_payment'] ?? [];

        $deliveryMethods =
            $_POST['delivery_payment_method'] ?? [];

        $deliveryLocations =
            $_POST['delivery_collection_location'] ?? [];

        $deliveryNotes =
            $_POST['delivery_notes'] ?? [];


        $deliveryCount =
            max(
                count($deliveryCustomers),
                count($deliveryIds)
            );


        for ($i = 0; $i < $deliveryCount; $i++) {

            $deliveryId =
                (int)($deliveryIds[$i] ?? 0);

            $customerName =
                trim($deliveryCustomers[$i] ?? '');

            $slim =
                (int)($deliverySlim[$i] ?? 0);

            $round =
                (int)($deliveryRound[$i] ?? 0);

            $priceInput =
                trim($deliveryPrices[$i] ?? '');

            $paymentInput =
                trim($deliveryPayments[$i] ?? '');

            $paymentMethod =
                $deliveryMethods[$i] ?? 'Cash';

            $collectionLocation =
                $deliveryLocations[$i] ?? 'Driver';

            $notes =
                trim($deliveryNotes[$i] ?? '');


            $hasData =
                $customerName !== ''
                || $slim > 0
                || $round > 0
                || $priceInput !== ''
                || $paymentInput !== ''
                || $notes !== '';


            if (!$hasData) {
                continue;
            }


            if ($slim < 0 || $round < 0) {
                throw new Exception(
                    'Delivery quantities cannot be negative.'
                );
            }


            $quantity =
                $slim + $round;


            if ($quantity <= 0) {
                throw new Exception(
                    'Every delivery row with a customer or price must have at least one gallon.'
                );
            }


            if (
                $priceInput === ''
                || !is_numeric($priceInput)
                || (float)$priceInput < 0
            ) {
                throw new Exception(
                    'Please enter a valid delivery price per gallon.'
                );
            }


            $price =
                (float)$priceInput;


            $amountDue =
                $quantity * $price;


            $customerId =
                $getCustomerId($customerName);


            if (
                !in_array(
                    $paymentMethod,
                    [
                        'Cash',
                        'GCash',
                        'Bank Transfer',
                        'Other'
                    ],
                    true
                )
            ) {
                $paymentMethod = 'Cash';
            }


            if (
                !in_array(
                    $collectionLocation,
                    [
                        'Station',
                        'Driver'
                    ],
                    true
                )
            ) {
                $collectionLocation = 'Driver';
            }


            // --------------------------------------------------
            // UPDATE EXISTING DELIVERY
            // --------------------------------------------------

            if ($deliveryId > 0) {

                $stmt = $pdo->prepare("
                    UPDATE deliveries
                    SET customer_id = ?,
                        delivery_date = ?,
                        slim_quantity = ?,
                        round_quantity = ?,
                        price_per_gallon = ?,
                        amount_due = ?,
                        notes = ?
                    WHERE delivery_id = ?
                      AND daily_id = ?
                ");

                $stmt->execute([
                    $customerId,
                    $businessDate,
                    $slim,
                    $round,
                    $price,
                    $amountDue,
                    $notes !== '' ? $notes : null,
                    $deliveryId,
                    $dailyId
                ]);

            } else {

                // --------------------------------------------------
                // CREATE NEW DELIVERY
                // --------------------------------------------------

                $stmt = $pdo->prepare("
                    INSERT INTO deliveries (
                        customer_id,
                        delivery_date,
                        slim_quantity,
                        round_quantity,
                        price_per_gallon,
                        amount_due,
                        notes,
                        daily_id
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $customerId,
                    $businessDate,
                    $slim,
                    $round,
                    $price,
                    $amountDue,
                    $notes !== '' ? $notes : null,
                    $dailyId
                ]);

                $deliveryId =
                    (int)$pdo->lastInsertId();
            }


            // --------------------------------------------------
            // PAYMENT
            // --------------------------------------------------

            if (
                $paymentInput !== ''
                && is_numeric($paymentInput)
                && (float)$paymentInput > 0
            ) {

                $paymentAmount =
                    (float)$paymentInput;


                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        delivery_id,
                        customer_id,
                        payment_date,
                        amount,
                        payment_method,
                        collection_location,
                        daily_id
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $deliveryId,
                    $customerId,
                    $businessDate,
                    $paymentAmount,
                    $paymentMethod,
                    $collectionLocation,
                    $dailyId
                ]);
            }
        }


        // ==================================================
        // EXPENSES
        // ==================================================

        $expenseCategories =
            $_POST['expense_category'] ?? [];

        $expenseAmounts =
            $_POST['expense_amount'] ?? [];

        $expenseLocations =
            $_POST['expense_location'] ?? [];

        $expenseDescriptions =
            $_POST['expense_description'] ?? [];

        $expenseNotes =
            $_POST['expense_notes'] ?? [];


        $expenseCount =
            count($expenseCategories);


        for ($i = 0; $i < $expenseCount; $i++) {

            $category =
                $expenseCategories[$i] ?? '';

            $amountInput =
                trim($expenseAmounts[$i] ?? '');

            $location =
                $expenseLocations[$i] ?? 'Station';

            $description =
                trim($expenseDescriptions[$i] ?? '');

            $notes =
                trim($expenseNotes[$i] ?? '');


            if ($amountInput === '') {
                continue;
            }


            if (
                !is_numeric($amountInput)
                || (float)$amountInput <= 0
            ) {
                throw new Exception(
                    'Expense amount must be greater than zero.'
                );
            }


            if (
                !in_array(
                    $category,
                    [
                        'Gas',
                        'Food',
                        'Cash Advance',
                        'Miscellaneous'
                    ],
                    true
                )
            ) {
                throw new Exception(
                    'Invalid expense category.'
                );
            }


            if (
                !in_array(
                    $location,
                    [
                        'Station',
                        'Driver'
                    ],
                    true
                )
            ) {
                $location = 'Station';
            }


            $stmt = $pdo->prepare("
                INSERT INTO expenses (
                    expense_date,
                    category,
                    description,
                    amount,
                    notes,
                    expense_location,
                    daily_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessDate,
                $category,
                $description !== '' ? $description : null,
                (float)$amountInput,
                $notes !== '' ? $notes : null,
                $location,
                $dailyId
            ]);
        }


        // ==================================================
        // GALLON SALES
        // ==================================================

        $gallonCustomers =
            $_POST['gallon_customer'] ?? [];

        $gallonQuantities =
            $_POST['gallon_quantity'] ?? [];

        $gallonPrices =
            $_POST['gallon_price'] ?? [];

        $gallonChannels =
            $_POST['gallon_channel'] ?? [];

        $gallonMethods =
            $_POST['gallon_payment_method'] ?? [];

        $gallonLocations =
            $_POST['gallon_collection_location'] ?? [];

        $gallonNotes =
            $_POST['gallon_notes'] ?? [];


        $gallonCount =
            count($gallonQuantities);


        for ($i = 0; $i < $gallonCount; $i++) {

            $quantity =
                (int)($gallonQuantities[$i] ?? 0);

            $priceInput =
                trim($gallonPrices[$i] ?? '');

            $customerName =
                trim($gallonCustomers[$i] ?? '');

            $channel =
                $gallonChannels[$i] ?? 'Shop';

            $paymentMethod =
                $gallonMethods[$i] ?? 'Cash';

            $location =
                $gallonLocations[$i] ?? 'Station';

            $notes =
                trim($gallonNotes[$i] ?? '');


            $hasData =
                $quantity > 0
                || $priceInput !== ''
                || $customerName !== ''
                || $notes !== '';


            if (!$hasData) {
                continue;
            }


            if ($quantity <= 0) {
                throw new Exception(
                    'Gallon sale quantity must be at least 1.'
                );
            }


            if (
                $priceInput === ''
                || !is_numeric($priceInput)
                || (float)$priceInput < 0
            ) {
                throw new Exception(
                    'Please enter a valid gallon selling price.'
                );
            }


            $sellingPrice =
                (float)$priceInput;


            $totalAmount =
                $quantity * $sellingPrice;


            $totalCost =
                $quantity * $gallonCost;


            $totalProfit =
                $totalAmount - $totalCost;


            $customerId =
                $getCustomerId($customerName);


            if (
                !in_array(
                    $channel,
                    [
                        'Shop',
                        'Delivery'
                    ],
                    true
                )
            ) {
                $channel = 'Shop';
            }


            if (
                !in_array(
                    $paymentMethod,
                    [
                        'Cash',
                        'GCash',
                        'Bank Transfer',
                        'Other'
                    ],
                    true
                )
            ) {
                $paymentMethod = 'Cash';
            }


            if (
                !in_array(
                    $location,
                    [
                        'Station',
                        'Driver'
                    ],
                    true
                )
            ) {
                $location = 'Station';
            }


            $stmt = $pdo->prepare("
                INSERT INTO gallon_sales (
                    sale_date,
                    quantity,
                    cost_per_gallon,
                    selling_price_per_gallon,
                    total_amount,
                    total_cost,
                    total_profit,
                    sale_channel,
                    customer_id,
                    payment_method,
                    collection_location,
                    daily_id,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $businessDate,
                $quantity,
                $gallonCost,
                $sellingPrice,
                $totalAmount,
                $totalCost,
                $totalProfit,
                $channel,
                $customerId,
                $paymentMethod,
                $location,
                $dailyId,
                $notes !== '' ? $notes : null
            ]);
        }


        // ==================================================
        // ACTUAL STATION CASH
        // ==================================================

        $actualCashInput =
            trim($_POST['actual_station_cash'] ?? '');


        if ($actualCashInput !== '') {

            if (
                !is_numeric($actualCashInput)
                || (float)$actualCashInput < 0
            ) {
                throw new Exception(
                    'Actual station cash must be a valid amount.'
                );
            }


            $actualStationCash =
                (float)$actualCashInput;


            $stmt = $pdo->prepare("
                UPDATE daily_records
                SET actual_station_cash = ?
                WHERE daily_id = ?
            ");

            $stmt->execute([
                $actualStationCash,
                $dailyId
            ]);

        } else {

            $stmt = $pdo->prepare("
                UPDATE daily_records
                SET actual_station_cash = NULL
                WHERE daily_id = ?
            ");

            $stmt->execute([
                $dailyId
            ]);

            $actualStationCash = null;
        }


        $pdo->commit();


        $message =
            'Daily closing information saved successfully.';

        $messageType =
            'success';


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        $message =
            $e->getMessage();

        $messageType =
            'danger';
    }
}


// ==================================================
// 5. RELOAD DAILY RECORD
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM daily_records
        WHERE daily_id = ?
        LIMIT 1
    ");

    $stmt->execute([$dailyId]);

    $dailyRecord =
        $stmt->fetch();

    $actualStationCash =
        $dailyRecord['actual_station_cash'] !== null
            ? (float)$dailyRecord['actual_station_cash']
            : null;
}


// ==================================================
// 6. LOAD WALK-IN SALES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM daily_sales
        WHERE daily_id = ?
        LIMIT 1
    ");

    $stmt->execute([$dailyId]);

    $dailySales =
        $stmt->fetch();


    if ($dailySales) {

        $walkInCustomers =
            (int)$dailySales['walk_in_customers'];

        $walkInPrice =
            (float)$dailySales['walk_in_price'];

        $walkInSales =
            $walkInCustomers * $walkInPrice;
    }
}


// ==================================================
// 7. LOAD DELIVERIES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            d.*,
            c.customer_name
        FROM deliveries d
        LEFT JOIN customers c
            ON c.customer_id = d.customer_id
        WHERE d.daily_id = ?
        ORDER BY d.delivery_id ASC
    ");

    $stmt->execute([$dailyId]);

    $deliveryRows =
        $stmt->fetchAll();
}


// ==================================================
// 8. DELIVERY SALES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount_due), 0)
            AS total_delivery_sales
        FROM deliveries
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result =
        $stmt->fetch();

    $totalDeliverySales =
        (float)$result['total_delivery_sales'];
}


// ==================================================
// 9. DELIVERY PAYMENTS
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0)
            AS total_delivery_payments,

            COALESCE(
                SUM(
                    CASE
                        WHEN collection_location = 'Station'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS station_payments,

            COALESCE(
                SUM(
                    CASE
                        WHEN collection_location = 'Driver'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS driver_payments

        FROM payments
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result =
        $stmt->fetch();


    $totalDeliveryPayments =
        (float)$result['total_delivery_payments'];

    $stationDeliveryPayments =
        (float)$result['station_payments'];

    $driverDeliveryPayments =
        (float)$result['driver_payments'];
}


$totalDeliveryOutstanding =
    $totalDeliverySales -
    $totalDeliveryPayments;

if ($totalDeliveryOutstanding < 0) {
    $totalDeliveryOutstanding = 0;
}


// ==================================================
// 10. LOAD EXPENSES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM expenses
        WHERE daily_id = ?
        ORDER BY expense_id ASC
    ");

    $stmt->execute([$dailyId]);

    $expenseRows =
        $stmt->fetchAll();


    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount), 0) AS total_expenses,

            COALESCE(
                SUM(
                    CASE
                        WHEN expense_location = 'Station'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS station_expenses,

            COALESCE(
                SUM(
                    CASE
                        WHEN expense_location = 'Driver'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS driver_expenses

        FROM expenses
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result =
        $stmt->fetch();


    $totalExpenses =
        (float)$result['total_expenses'];

    $totalStationExpenses =
        (float)$result['station_expenses'];

    $totalDriverExpenses =
        (float)$result['driver_expenses'];
}


// ==================================================
// 11. LOAD GALLON SALES
// ==================================================

if ($dailyId !== null) {

    $stmt = $pdo->prepare("
        SELECT
            gs.*,
            c.customer_name
        FROM gallon_sales gs
        LEFT JOIN customers c
            ON c.customer_id = gs.customer_id
        WHERE gs.daily_id = ?
        ORDER BY gs.gallon_sale_id ASC
    ");

    $stmt->execute([$dailyId]);

    $gallonRows =
        $stmt->fetchAll();


    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(total_amount), 0)
                AS total_revenue,

            COALESCE(SUM(total_cost), 0)
                AS total_cost,

            COALESCE(SUM(total_profit), 0)
                AS total_profit,

            COALESCE(
                SUM(
                    CASE
                        WHEN collection_location = 'Station'
                         AND payment_method = 'Cash'
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS station_cash,

            COALESCE(
                SUM(
                    CASE
                        WHEN collection_location = 'Driver'
                         AND payment_method = 'Cash'
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS driver_cash

        FROM gallon_sales
        WHERE daily_id = ?
    ");

    $stmt->execute([$dailyId]);

    $result =
        $stmt->fetch();


    $totalGallonRevenue =
        (float)$result['total_revenue'];

    $totalGallonCost =
        (float)$result['total_cost'];

    $totalGallonProfit =
        (float)$result['total_profit'];

    $stationGallonCash =
        (float)$result['station_cash'];

    $driverGallonCash =
        (float)$result['driver_cash'];
}


// ==================================================
// 12. RECONCILIATION
// ==================================================
//
// Walk-in sales are currently assumed to be cash because
// daily_sales does not yet have a payment method field.
//
// Station cash:
//
// Walk-in sales
// + station delivery cash payments
// + station gallon cash
// - station expenses
//
// This is the expected physical station cash.
//

$expectedStationCash =
    $walkInSales
    + $stationDeliveryPayments
    + $stationGallonCash
    - $totalStationExpenses;


$stationDifference = null;

if ($actualStationCash !== null) {

    $stationDifference =
        $actualStationCash -
        $expectedStationCash;
}


// ==================================================
// 13. DRIVER NET
// ==================================================

$driverNet =
    $driverDeliveryPayments
    + $driverGallonCash
    - $totalDriverExpenses;


// ==================================================
// 14. TOTAL BUSINESS REVENUE
// ==================================================

$totalBusinessRevenue =
    $walkInSales
    + $totalDeliverySales
    + $totalGallonRevenue;


$totalBusinessProfitKnown =
    $totalGallonProfit;


// ==================================================
// 15. PREPARE INITIAL BLANK DELIVERY ROWS
// ==================================================

$blankDeliveryRows = 10;

if (count($deliveryRows) < $blankDeliveryRows) {

    $blankDeliveryRows =
        $blankDeliveryRows - count($deliveryRows);

    for ($i = 0; $i < $blankDeliveryRows; $i++) {

        $deliveryRows[] = [
            'delivery_id' => 0,
            'customer_name' => '',
            'slim_quantity' => 0,
            'round_quantity' => 0,
            'price_per_gallon' => '',
            'notes' => ''
        ];
    }
}


// ==================================================
// 16. BLANK GALLON ROWS
// ==================================================

$gallonRows[] = [
    'gallon_sale_id' => 0,
    'customer_name' => '',
    'quantity' => '',
    'selling_price_per_gallon' => $gallonDefaultPrice,
    'sale_channel' => 'Shop',
    'payment_method' => 'Cash',
    'collection_location' => 'Station',
    'notes' => ''
];


// ==================================================
// 17. BLANK EXPENSE ROW
// ==================================================

$expenseRows[] = [
    'expense_id' => 0,
    'category' => 'Food',
    'amount' => '',
    'description' => '',
    'expense_location' => 'Station',
    'notes' => ''
];

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

    <style>

        .section-card {
            margin-bottom: 20px;
        }

        .section-description {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .section-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .data-table th {
            text-align: left;
            padding: 10px;
            font-size: 12px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table .form-input {
            width: 100%;
            min-width: 90px;
        }

        .delivery-row-existing {
            background: var(--primary-light);
        }

        .calculated-amount {
            font-weight: 700;
            white-space: nowrap;
        }

        .row-number {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }

        .reconciliation-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .reconciliation-box {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            background: var(--surface);
        }

        .reconciliation-box .label {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 5px;
        }

        .reconciliation-box .value {
            font-size: 22px;
            font-weight: 700;
        }

        .profit-value {
            font-weight: 700;
        }

        .save-bar {
            position: sticky;
            bottom: 15px;
            z-index: 20;
            margin-top: 20px;
        }

        .save-bar-inner {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 15px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .small-note {
            color: var(--text-muted);
            font-size: 12px;
        }

        .remove-row {
            border: 0;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }

        @media (max-width: 800px) {

            .reconciliation-grid {
                grid-template-columns: 1fr;
            }

            .save-bar-inner {
                align-items: stretch;
            }

        }

    </style>

</head>


<body>

<div class="app">


    <!-- ==================================================
         SIDEBAR
         ================================================== -->

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



    <!-- ==================================================
         MAIN
         ================================================== -->

    <main class="main">


        <header class="topbar">

            <div class="topbar-title">
                Daily Closing
            </div>


            <div class="topbar-user">

                👤

                <?= htmlspecialchars(
                    $_SESSION['full_name'] ?? 'Admin'
                ) ?>

            </div>

        </header>



        <section class="page">


            <!-- ==================================================
                 HEADER
                 ================================================== -->

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



            <?php if ($message !== ''): ?>

                <div
                    class="alert alert-<?= htmlspecialchars(
                        $messageType
                    ) ?>"
                    style="margin-bottom: 20px;"
                >

                    <?= htmlspecialchars($message) ?>

                </div>

            <?php endif; ?>



            <?php if ($dailyRecord): ?>


                <!-- ==================================================
                     ACTIVE STATUS
                     ================================================== -->

                <div class="card section-card">

                    <div class="card-body">

                        <div
                            style="
                                display:flex;
                                justify-content:space-between;
                                align-items:center;
                                gap:20px;
                                flex-wrap:wrap;
                            "
                        >

                            <div>

                                <div class="summary-label">
                                    Active Daily Record
                                </div>

                                <div
                                    style="
                                        font-size:20px;
                                        font-weight:700;
                                    "
                                >
                                    <?= htmlspecialchars(
                                        $businessDate
                                    ) ?>
                                </div>

                            </div>


                            <span class="badge badge-warning">
                                Open
                            </span>

                        </div>

                    </div>

                </div>



                <!-- ==================================================
                     QUICK SUMMARY
                     ================================================== -->

                <div class="summary-grid">


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
                            Water delivery sales
                        </div>

                    </div>


                    <div class="card summary-card">

                        <div class="summary-label">
                            Gallon Sales
                        </div>

                        <div class="summary-value">
                            ₱<?= number_format(
                                $totalGallonRevenue,
                                2
                            ) ?>
                        </div>

                        <div class="summary-description">
                            Profit:
                            ₱<?= number_format(
                                $totalGallonProfit,
                                2
                            ) ?>
                        </div>

                    </div>


                    <div class="card summary-card">

                        <div class="summary-label">
                            Total Revenue
                        </div>

                        <div class="summary-value">
                            ₱<?= number_format(
                                $totalBusinessRevenue,
                                2
                            ) ?>
                        </div>

                        <div class="summary-description">
                            All sales categories
                        </div>

                    </div>

                </div>



                <br>



                <!-- ==================================================
                     WALK-IN / STATION
                     ================================================== -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="save_workspace"
                    >


                    <div class="card section-card">

                        <div class="card-header">

                            <div>

                                <div class="card-title">
                                    Station / Walk-in Sales
                                </div>

                                <div class="section-description">
                                    Regular water sales at the station.
                                    Gallon sales are recorded separately below.
                                </div>

                            </div>

                        </div>


                        <div class="card-body">

                            <div class="summary-grid">


                                <div>

                                    <label
                                        class="form-label"
                                        for="walk_in_customers"
                                    >
                                        Walk-in Customers
                                    </label>

                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        class="form-input"
                                        id="walk_in_customers"
                                        name="walk_in_customers"
                                        value="<?= htmlspecialchars(
                                            (string)$walkInCustomers
                                        ) ?>"
                                    >

                                </div>


                                <div>

                                    <label
                                        class="form-label"
                                        for="walk_in_money"
                                    >
                                        Or Walk-in Money
                                    </label>

                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="form-input"
                                        id="walk_in_money"
                                        name="walk_in_money"
                                        placeholder="Example: 4500"
                                    >

                                </div>


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

                        </div>

                    </div>



                    <!-- ==================================================
                         DRIVER DELIVERIES
                         ================================================== -->

                    <div class="card section-card">

                        <div class="card-header">

                            <div>

                                <div class="card-title">
                                    Driver Deliveries
                                </div>

                                <div class="section-description">
                                    Delivery water sales are separate from
                                    station walk-in sales and gallon sales.
                                </div>

                            </div>


                            <div class="section-actions">

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="addDeliveryRows(1)"
                                >
                                    +1
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="addDeliveryRows(5)"
                                >
                                    +5
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="addDeliveryRows(10)"
                                >
                                    +10
                                </button>

                            </div>

                        </div>


                        <div class="card-body">

                            <div class="table-wrap">

                                <table class="data-table">

                                    <thead>

                                        <tr>

                                            <th>#</th>

                                            <th>
                                                Customer
                                            </th>

                                            <th>
                                                Slim
                                            </th>

                                            <th>
                                                Round
                                            </th>

                                            <th>
                                                Price / Gal
                                            </th>

                                            <th>
                                                Amount Due
                                            </th>

                                            <th>
                                                Payment
                                            </th>

                                            <th>
                                                Method
                                            </th>

                                            <th>
                                                Collection
                                            </th>

                                            <th>
                                                Notes
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody id="deliveryRows">

                                        <?php foreach (
                                            $deliveryRows
                                            as $index => $row
                                        ): ?>

                                            <tr
                                                class="<?= (
                                                    !empty(
                                                        $row['delivery_id']
                                                    )
                                                    ? 'delivery-row-existing'
                                                    : ''
                                                ) ?>"
                                            >

                                                <td>

                                                    <span class="row-number">
                                                        <?= $index + 1 ?>
                                                    </span>

                                                    <input
                                                        type="hidden"
                                                        name="delivery_id[]"
                                                        value="<?= (int)(
                                                            $row['delivery_id']
                                                            ?? 0
                                                        ) ?>"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="text"
                                                        class="form-input customer-input delivery-customer"
                                                        name="delivery_customer[]"
                                                        list="customerList"
                                                        autocomplete="off"
                                                        value="<?= htmlspecialchars(
                                                            $row['customer_name']
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Customer"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input delivery-qty slim"
                                                        name="delivery_slim[]"
                                                        min="0"
                                                        step="1"
                                                        value="<?= (int)(
                                                            $row['slim_quantity']
                                                            ?? 0
                                                        ) ?>"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input delivery-qty round"
                                                        name="delivery_round[]"
                                                        min="0"
                                                        step="1"
                                                        value="<?= (int)(
                                                            $row['round_quantity']
                                                            ?? 0
                                                        ) ?>"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input delivery-price"
                                                        name="delivery_price[]"
                                                        min="0"
                                                        step="0.01"
                                                        value="<?= htmlspecialchars(
                                                            (string)(
                                                                $row['price_per_gallon']
                                                                ?? ''
                                                            )
                                                        ) ?>"
                                                        placeholder="30.00"
                                                    >

                                                </td>


                                                <td>

                                                    <span class="calculated-amount">
                                                        ₱0.00
                                                    </span>

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input"
                                                        name="delivery_payment[]"
                                                        min="0"
                                                        step="0.01"
                                                        placeholder="0.00"
                                                    >

                                                </td>


                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="delivery_payment_method[]"
                                                    >

                                                        <option value="Cash">
                                                            Cash
                                                        </option>

                                                        <option value="GCash">
                                                            GCash
                                                        </option>

                                                        <option value="Bank Transfer">
                                                            Bank Transfer
                                                        </option>

                                                        <option value="Other">
                                                            Other
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="delivery_collection_location[]"
                                                    >

                                                        <option value="Driver">
                                                            Driver
                                                        </option>

                                                        <option value="Station">
                                                            Station
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <input
                                                        type="text"
                                                        class="form-input"
                                                        name="delivery_notes[]"
                                                        value="<?= htmlspecialchars(
                                                            $row['notes']
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Optional"
                                                    >

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>


                            <div class="small-note" style="margin-top:10px;">
                                Existing delivery rows are highlighted.
                                Blank rows are only for entry and are not
                                saved until you fill them.
                            </div>

                        </div>

                    </div>



                    <!-- ==================================================
                         GALLON SALES
                         ================================================== -->

                    <div class="card section-card">

                        <div class="card-header">

                            <div>

                                <div class="card-title">
                                    Gallon Sales
                                </div>

                                <div class="section-description">
                                    Separate from regular walk-in and
                                    delivery water sales. Empty gallon cost
                                    is fixed at ₱190.
                                </div>

                            </div>


                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="addGallonRow()"
                            >
                                + Add Gallon Sale
                            </button>

                        </div>


                        <div class="card-body">

                            <div class="table-wrap">

                                <table class="data-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Customer
                                            </th>

                                            <th>
                                                Quantity
                                            </th>

                                            <th>
                                                Selling Price / Gal
                                            </th>

                                            <th>
                                                Revenue
                                            </th>

                                            <th>
                                                Cost
                                            </th>

                                            <th>
                                                Profit
                                            </th>

                                            <th>
                                                Channel
                                            </th>

                                            <th>
                                                Payment
                                            </th>

                                            <th>
                                                Collection
                                            </th>

                                            <th>
                                                Notes
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody id="gallonRows">

                                        <?php foreach (
                                            $gallonRows
                                            as $row
                                        ): ?>

                                            <tr>

                                                <td>

                                                    <input
                                                        type="text"
                                                        class="form-input"
                                                        name="gallon_customer[]"
                                                        list="customerList"
                                                        autocomplete="off"
                                                        value="<?= htmlspecialchars(
                                                            $row['customer_name']
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Optional"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input gallon-quantity"
                                                        name="gallon_quantity[]"
                                                        min="1"
                                                        step="1"
                                                        value="<?= htmlspecialchars(
                                                            (string)(
                                                                $row['quantity']
                                                                ?? ''
                                                            )
                                                        ) ?>"
                                                        placeholder="2"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input gallon-price"
                                                        name="gallon_price[]"
                                                        min="0"
                                                        step="0.01"
                                                        value="<?= htmlspecialchars(
                                                            (string)(
                                                                $row[
                                                                    'selling_price_per_gallon'
                                                                ]
                                                                ?? $gallonDefaultPrice
                                                            )
                                                        ) ?>"
                                                    >

                                                </td>


                                                <td>

                                                    <span class="gallon-revenue">
                                                        ₱0.00
                                                    </span>

                                                </td>


                                                <td>

                                                    <span class="gallon-cost">
                                                        ₱0.00
                                                    </span>

                                                </td>


                                                <td>

                                                    <span class="gallon-profit profit-value">
                                                        ₱0.00
                                                    </span>

                                                </td>


                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="gallon_channel[]"
                                                    >

                                                        <option value="Shop">
                                                            Shop
                                                        </option>

                                                        <option value="Delivery">
                                                            Delivery
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="gallon_payment_method[]"
                                                    >

                                                        <option value="Cash">
                                                            Cash
                                                        </option>

                                                        <option value="GCash">
                                                            GCash
                                                        </option>

                                                        <option value="Bank Transfer">
                                                            Bank Transfer
                                                        </option>

                                                        <option value="Other">
                                                            Other
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="gallon_collection_location[]"
                                                    >

                                                        <option value="Station">
                                                            Station
                                                        </option>

                                                        <option value="Driver">
                                                            Driver
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <input
                                                        type="text"
                                                        class="form-input"
                                                        name="gallon_notes[]"
                                                        value="<?= htmlspecialchars(
                                                            $row['notes']
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Optional"
                                                    >

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>


                            <div class="small-note" style="margin-top:10px;">
                                Example: Mark buys 2 gallons at ₱225 each.
                                Revenue = ₱450, cost = ₱380, profit = ₱70.
                                This does not become delivery water sales
                                or two walk-in customers.
                            </div>

                        </div>

                    </div>



                    <!-- ==================================================
                         EXPENSES
                         ================================================== -->

                    <div class="card section-card">

                        <div class="card-header">

                            <div>

                                <div class="card-title">
                                    Expenses
                                </div>

                                <div class="section-description">
                                    Add expenses only when needed.
                                </div>

                            </div>


                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="addExpenseRow()"
                            >
                                + Add Expense
                            </button>

                        </div>


                        <div class="card-body">

                            <div class="table-wrap">

                                <table class="data-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Category
                                            </th>

                                            <th>
                                                Amount
                                            </th>

                                            <th>
                                                Location
                                            </th>

                                            <th>
                                                Description
                                            </th>

                                            <th>
                                                Notes
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody id="expenseRows">

                                        <?php foreach (
                                            $expenseRows
                                            as $row
                                        ): ?>

                                            <tr>

                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="expense_category[]"
                                                    >

                                                        <option value="Gas"
                                                            <?= (
                                                                ($row[
                                                                    'category'
                                                                ] ?? '')
                                                                === 'Gas'
                                                            )
                                                            ? 'selected'
                                                            : ''
                                                        ?>
                                                        >
                                                            Gas
                                                        </option>

                                                        <option value="Food"
                                                            <?= (
                                                                ($row[
                                                                    'category'
                                                                ] ?? '')
                                                                === 'Food'
                                                            )
                                                            ? 'selected'
                                                            : ''
                                                        ?>
                                                        >
                                                            Food
                                                        </option>

                                                        <option value="Cash Advance"
                                                            <?= (
                                                                ($row[
                                                                    'category'
                                                                ] ?? '')
                                                                === 'Cash Advance'
                                                            )
                                                            ? 'selected'
                                                            : ''
                                                        ?>
                                                        >
                                                            Cash Advance
                                                        </option>

                                                        <option value="Miscellaneous"
                                                            <?= (
                                                                ($row[
                                                                    'category'
                                                                ] ?? '')
                                                                === 'Miscellaneous'
                                                            )
                                                            ? 'selected'
                                                            : ''
                                                        ?>
                                                        >
                                                            Miscellaneous
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <input
                                                        type="number"
                                                        class="form-input"
                                                        name="expense_amount[]"
                                                        min="0"
                                                        step="0.01"
                                                        value="<?= htmlspecialchars(
                                                            (string)(
                                                                $row['amount']
                                                                ?? ''
                                                            )
                                                        ) ?>"
                                                        placeholder="100.00"
                                                    >

                                                </td>


                                                <td>

                                                    <select
                                                        class="form-input"
                                                        name="expense_location[]"
                                                    >

                                                        <option value="Station"
                                                            <?= (
                                                                ($row[
                                                                    'expense_location'
                                                                ] ?? 'Station')
                                                                === 'Station'
                                                            )
                                                            ? 'selected'
                                                            : ''
                                                        ?>
                                                        >
                                                            Station
                                                        </option>

                                                        <option value="Driver"
                                                            <?= (
                                                                ($row[
                                                                    'expense_location'
                                                                ] ?? '')
                                                                === 'Driver'
                                                            )
                                                            ? 'selected'
                                                            : ''
                                                        ?>
                                                        >
                                                            Driver
                                                        </option>

                                                    </select>

                                                </td>


                                                <td>

                                                    <input
                                                        type="text"
                                                        class="form-input"
                                                        name="expense_description[]"
                                                        value="<?= htmlspecialchars(
                                                            $row[
                                                                'description'
                                                            ]
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Food"
                                                    >

                                                </td>


                                                <td>

                                                    <input
                                                        type="text"
                                                        class="form-input"
                                                        name="expense_notes[]"
                                                        value="<?= htmlspecialchars(
                                                            $row['notes']
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Optional"
                                                    >

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>



                    <!-- ==================================================
                         RECONCILIATION
                         ================================================== -->

                    <div class="card section-card">

                        <div class="card-header">

                            <div>

                                <div class="card-title">
                                    Daily Reconciliation
                                </div>

                                <div class="section-description">
                                    Compare the expected station cash
                                    against the physical cash counted.
                                </div>

                            </div>

                        </div>


                        <div class="card-body">


                            <div class="reconciliation-grid">


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Walk-in Cash
                                    </div>

                                    <div class="value">
                                        ₱<?= number_format(
                                            $walkInSales,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Station Delivery Payments
                                    </div>

                                    <div class="value">
                                        ₱<?= number_format(
                                            $stationDeliveryPayments,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Station Gallon Cash
                                    </div>

                                    <div class="value">
                                        ₱<?= number_format(
                                            $stationGallonCash,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Station Expenses
                                    </div>

                                    <div class="value">
                                        ₱<?= number_format(
                                            $totalStationExpenses,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Expected Station Cash
                                    </div>

                                    <div class="value">
                                        ₱<?= number_format(
                                            $expectedStationCash,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div class="reconciliation-box">

                                    <label
                                        class="form-label"
                                        for="actual_station_cash"
                                    >
                                        Actual Station Cash
                                    </label>

                                    <input
                                        type="number"
                                        id="actual_station_cash"
                                        name="actual_station_cash"
                                        class="form-input"
                                        min="0"
                                        step="0.01"
                                        value="<?= (
                                            $actualStationCash !== null
                                        )
                                            ? htmlspecialchars(
                                                number_format(
                                                    $actualStationCash,
                                                    2,
                                                    '.',
                                                    ''
                                                )
                                            )
                                            : ''
                                        ?>"
                                        placeholder="Enter physical cash"
                                    >

                                </div>


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Station Difference
                                    </div>

                                    <div class="value">

                                        <?php if (
                                            $stationDifference !== null
                                        ): ?>

                                            ₱<?= number_format(
                                                $stationDifference,
                                                2
                                            ) ?>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="reconciliation-box">

                                    <div class="label">
                                        Driver Net
                                    </div>

                                    <div class="value">
                                        ₱<?= number_format(
                                            $driverNet,
                                            2
                                        ) ?>
                                    </div>

                                    <div class="small-note">
                                        Driver collections + driver gallon
                                        cash − driver expenses
                                    </div>

                                </div>

                            </div>


                            <br>


                            <div class="summary-grid">


                                <div>

                                    <div class="summary-label">
                                        Total Delivery Payments
                                    </div>

                                    <div class="summary-value">
                                        ₱<?= number_format(
                                            $totalDeliveryPayments,
                                            2
                                        ) ?>
                                    </div>

                                    <div class="summary-description">
                                        Station + Driver collections
                                    </div>

                                </div>


                                <div>

                                    <div class="summary-label">
                                        Outstanding Delivery Debt
                                    </div>

                                    <div class="summary-value">
                                        ₱<?= number_format(
                                            $totalDeliveryOutstanding,
                                            2
                                        ) ?>
                                    </div>

                                    <div class="summary-description">
                                        Delivery sales not yet paid
                                    </div>

                                </div>


                                <div>

                                    <div class="summary-label">
                                        Total Expenses
                                    </div>

                                    <div class="summary-value">
                                        ₱<?= number_format(
                                            $totalExpenses,
                                            2
                                        ) ?>
                                    </div>

                                </div>


                                <div>

                                    <div class="summary-label">
                                        Gallon Profit
                                    </div>

                                    <div class="summary-value">
                                        ₱<?= number_format(
                                            $totalGallonProfit,
                                            2
                                        ) ?>
                                    </div>

                                    <div class="summary-description">
                                        Revenue − ₱190 cost per gallon
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>



                    <!-- ==================================================
                         SAVE BAR
                         ================================================== -->

                    <div class="save-bar">

                        <div class="save-bar-inner">

                            <div>

                                <strong>
                                    Daily Closing is Open
                                </strong>

                                <div class="small-note">
                                    Saving records does not close the day.
                                </div>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Save Daily Closing
                            </button>

                        </div>

                    </div>


                </form>


            <?php else: ?>


                <!-- ==================================================
                     NO ACTIVE RECORD
                     ================================================== -->

                <div class="card">

                    <div class="card-body">

                        <div
                            style="
                                text-align:center;
                                padding:40px 10px;
                            "
                        >

                            <div
                                style="
                                    font-size:40px;
                                    margin-bottom:15px;
                                "
                            >
                                🧾
                            </div>


                            <div
                                style="
                                    font-size:20px;
                                    font-weight:700;
                                    margin-bottom:8px;
                                "
                            >
                                No Active Daily Closing
                            </div>


                            <p class="page-subtitle">
                                There is currently no open daily record.
                            </p>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


        </section>

    </main>

</div>


<!-- ==================================================
     CUSTOMER LIST
     ================================================== -->

<datalist id="customerList">

    <?php foreach ($customers as $customer): ?>

        <option value="<?= htmlspecialchars(
            $customer['customer_name']
        ) ?>"></option>

    <?php endforeach; ?>

</datalist>


<script>


// ==================================================
// CUSTOMER PRICE DATA
// ==================================================

const customerPrices = {

    <?php foreach ($customers as $customer): ?>

    <?= json_encode(
        strtolower(trim($customer['customer_name']))
    ) ?>:
    <?= json_encode(
        (float)$customer['gallon_price']
    ) ?>,

    <?php endforeach; ?>

};


// ==================================================
// DELIVERY ROW CALCULATION
// ==================================================

function calculateDeliveryRow(row) {

    const slim =
        parseFloat(
            row.querySelector('.slim')?.value || 0
        );

    const round =
        parseFloat(
            row.querySelector('.round')?.value || 0
        );

    const price =
        parseFloat(
            row.querySelector('.delivery-price')?.value || 0
        );


    const amount =
        (slim + round) * price;


    const output =
        row.querySelector('.calculated-amount');


    if (output) {

        output.textContent =
            '₱' +
            amount.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
    }
}


function calculateAllDeliveryRows() {

    document
        .querySelectorAll('#deliveryRows tr')
        .forEach(
            calculateDeliveryRow
        );
}


// ==================================================
// AUTO PRICE FROM CUSTOMER
// ==================================================

function applyCustomerPrice(input) {

    const customerName =
        input.value
            .trim()
            .toLowerCase();


    const price =
        customerPrices[customerName];


    if (
        price !== undefined
        && price !== null
    ) {

        const row =
            input.closest('tr');

        const priceInput =
            row.querySelector(
                '.delivery-price'
            );


        if (
            priceInput
            && (
                priceInput.value === ''
                || priceInput.dataset.autoPrice === 'true'
            )
        ) {

            priceInput.value =
                price;

            priceInput.dataset.autoPrice =
                'true';

            calculateDeliveryRow(row);
        }
    }
}


// ==================================================
// ADD DELIVERY ROWS
// ==================================================

function addDeliveryRows(count) {

    const tbody =
        document.getElementById(
            'deliveryRows'
        );


    const currentRows =
        tbody.querySelectorAll('tr').length;


    for (
        let i = 0;
        i < count;
        i++
    ) {

        const rowNumber =
            currentRows + i + 1;


        const row =
            document.createElement('tr');


        row.innerHTML = `

            <td>

                <span class="row-number">
                    ${rowNumber}
                </span>

                <input
                    type="hidden"
                    name="delivery_id[]"
                    value="0"
                >

            </td>


            <td>

                <input
                    type="text"
                    class="form-input customer-input delivery-customer"
                    name="delivery_customer[]"
                    list="customerList"
                    autocomplete="off"
                    placeholder="Customer"
                >

            </td>


            <td>

                <input
                    type="number"
                    class="form-input delivery-qty slim"
                    name="delivery_slim[]"
                    min="0"
                    step="1"
                    value="0"
                >

            </td>


            <td>

                <input
                    type="number"
                    class="form-input delivery-qty round"
                    name="delivery_round[]"
                    min="0"
                    step="1"
                    value="0"
                >

            </td>


            <td>

                <input
                    type="number"
                    class="form-input delivery-price"
                    name="delivery_price[]"
                    min="0"
                    step="0.01"
                    placeholder="30.00"
                >

            </td>


            <td>

                <span class="calculated-amount">
                    ₱0.00
                </span>

            </td>


            <td>

                <input
                    type="number"
                    class="form-input"
                    name="delivery_payment[]"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                >

            </td>


            <td>

                <select
                    class="form-input"
                    name="delivery_payment_method[]"
                >

                    <option value="Cash">
                        Cash
                    </option>

                    <option value="GCash">
                        GCash
                    </option>

                    <option value="Bank Transfer">
                        Bank Transfer
                    </option>

                    <option value="Other">
                        Other
                    </option>

                </select>

            </td>


            <td>

                <select
                    class="form-input"
                    name="delivery_collection_location[]"
                >

                    <option value="Driver">
                        Driver
                    </option>

                    <option value="Station">
                        Station
                    </option>

                </select>

            </td>


            <td>

                <input
                    type="text"
                    class="form-input"
                    name="delivery_notes[]"
                    placeholder="Optional"
                >

            </td>

        `;


        tbody.appendChild(row);
    }


    calculateAllDeliveryRows();
}


// ==================================================
// GALLON CALCULATION
// ==================================================

function calculateGallonRow(row) {

    const quantity =
        parseFloat(
            row.querySelector(
                '.gallon-quantity'
            )?.value || 0
        );

    const price =
        parseFloat(
            row.querySelector(
                '.gallon-price'
            )?.value || 0
        );


    const costPerGallon =
        <?= json_encode($gallonCost) ?>;


    const revenue =
        quantity * price;

    const cost =
        quantity * costPerGallon;

    const profit =
        revenue - cost;


    const revenueElement =
        row.querySelector(
            '.gallon-revenue'
        );

    const costElement =
        row.querySelector(
            '.gallon-cost'
        );

    const profitElement =
        row.querySelector(
            '.gallon-profit'
        );


    if (revenueElement) {

        revenueElement.textContent =
            '₱' +
            revenue.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
    }


    if (costElement) {

        costElement.textContent =
            '₱' +
            cost.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
    }


    if (profitElement) {

        profitElement.textContent =
            '₱' +
            profit.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );
    }
}


function calculateAllGallonRows() {

    document
        .querySelectorAll('#gallonRows tr')
        .forEach(
            calculateGallonRow
        );
}


// ==================================================
// ADD GALLON ROW
// ==================================================

function addGallonRow() {

    const tbody =
        document.getElementById(
            'gallonRows'
        );


    const row =
        document.createElement('tr');


    row.innerHTML = `

        <td>

            <input
                type="text"
                class="form-input"
                name="gallon_customer[]"
                list="customerList"
                autocomplete="off"
                placeholder="Optional"
            >

        </td>


        <td>

            <input
                type="number"
                class="form-input gallon-quantity"
                name="gallon_quantity[]"
                min="1"
                step="1"
                placeholder="2"
            >

        </td>


        <td>

            <input
                type="number"
                class="form-input gallon-price"
                name="gallon_price[]"
                min="0"
                step="0.01"
                value="<?= htmlspecialchars(
                    (string)$gallonDefaultPrice
                ) ?>"
            >

        </td>


        <td>

            <span class="gallon-revenue">
                ₱0.00
            </span>

        </td>


        <td>

            <span class="gallon-cost">
                ₱0.00
            </span>

        </td>


        <td>

            <span class="gallon-profit profit-value">
                ₱0.00
            </span>

        </td>


        <td>

            <select
                class="form-input"
                name="gallon_channel[]"
            >

                <option value="Shop">
                    Shop
                </option>

                <option value="Delivery">
                    Delivery
                </option>

            </select>

        </td>


        <td>

            <select
                class="form-input"
                name="gallon_payment_method[]"
            >

                <option value="Cash">
                    Cash
                </option>

                <option value="GCash">
                    GCash
                </option>

                <option value="Bank Transfer">
                    Bank Transfer
                </option>

                <option value="Other">
                    Other
                </option>

            </select>

        </td>


        <td>

            <select
                class="form-input"
                name="gallon_collection_location[]"
            >

                <option value="Station">
                    Station
                </option>

                <option value="Driver">
                    Driver
                </option>

            </select>

        </td>


        <td>

            <input
                type="text"
                class="form-input"
                name="gallon_notes[]"
                placeholder="Optional"
            >

        </td>

    `;


    tbody.appendChild(row);

    calculateGallonRow(row);
}


// ==================================================
// ADD EXPENSE ROW
// ==================================================

function addExpenseRow() {

    const tbody =
        document.getElementById(
            'expenseRows'
        );


    const row =
        document.createElement('tr');


    row.innerHTML = `

        <td>

            <select
                class="form-input"
                name="expense_category[]"
            >

                <option value="Gas">
                    Gas
                </option>

                <option value="Food">
                    Food
                </option>

                <option value="Cash Advance">
                    Cash Advance
                </option>

                <option value="Miscellaneous">
                    Miscellaneous
                </option>

            </select>

        </td>


        <td>

            <input
                type="number"
                class="form-input"
                name="expense_amount[]"
                min="0"
                step="0.01"
                placeholder="100.00"
            >

        </td>


        <td>

            <select
                class="form-input"
                name="expense_location[]"
            >

                <option value="Station">
                    Station
                </option>

                <option value="Driver">
                    Driver
                </option>

            </select>

        </td>


        <td>

            <input
                type="text"
                class="form-input"
                name="expense_description[]"
                placeholder="Food"
            >

        </td>


        <td>

            <input
                type="text"
                class="form-input"
                name="expense_notes[]"
                placeholder="Optional"
            >

        </td>

    `;


    tbody.appendChild(row);
}


// ==================================================
// EVENT HANDLERS
// ==================================================

document.addEventListener(
    'input',
    function(event) {

        const target =
            event.target;


        if (
            target.classList.contains(
                'delivery-qty'
            )
            || target.classList.contains(
                'delivery-price'
            )
        ) {

            calculateDeliveryRow(
                target.closest('tr')
            );
        }


        if (
            target.classList.contains(
                'gallon-quantity'
            )
            || target.classList.contains(
                'gallon-price'
            )
        ) {

            calculateGallonRow(
                target.closest('tr')
            );
        }


        if (
            target.classList.contains(
                'delivery-price'
            )
        ) {

            target.dataset.autoPrice =
                'false';
        }


        if (
            target.classList.contains(
                'delivery-customer'
            )
        ) {

            applyCustomerPrice(
                target
            );
        }

    }
);


// ==================================================
// INITIAL CALCULATIONS
// ==================================================

calculateAllDeliveryRows();

calculateAllGallonRows();


// ==================================================
// MONEY -> WALK-IN CUSTOMERS
// ==================================================

const walkInMoney =
    document.getElementById(
        'walk_in_money'
    );

const walkInCustomersInput =
    document.getElementById(
        'walk_in_customers'
    );


if (walkInMoney) {

    walkInMoney.addEventListener(
        'input',
        function() {

            if (
                this.value !== ''
                && walkInCustomersInput
            ) {

                const money =
                    parseFloat(
                        this.value
                    );


                const price =
                    <?= json_encode(
                        $walkInPrice
                    ) ?>;


                if (
                    !isNaN(money)
                    && price > 0
                ) {

                    const customers =
                        money / price;


                    if (
                        Number.isInteger(
                            customers
                        )
                    ) {

                        walkInCustomersInput.value =
                            customers;
                    }
                }
            }

        }
    );

}

</script>


<script src="../assets/js/app.js"></script>

</body>

</html>