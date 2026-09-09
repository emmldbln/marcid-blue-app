<?php

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

// ==================================================
// ACTIVE DAILY RECORD
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
// SHOP / WALK-IN DEFAULTS
// ==================================================
$walkInCustomers = 0;
$walkInPrice = 30.00;
$walkInSales = 0.00;
$message = '';
$messageType = '';

// ==================================================
// LOAD CUSTOMERS + ACCOUNT BALANCES
// ==================================================
$customers = [];
$customerAccounts = [];
$customerBalances = [];

$stmt = $pdo->query("
    SELECT
        c.customer_id,
        c.customer_name,
        COALESCE((
            SELECT SUM(d.amount_due)
            FROM deliveries d
            WHERE d.customer_id = c.customer_id
        ), 0) AS total_due,
        COALESCE((
            SELECT SUM(p.amount)
            FROM payments p
            WHERE p.customer_id = c.customer_id
        ), 0) AS total_paid
    FROM customers c
    ORDER BY c.customer_name ASC
");
$customerAccounts = $stmt->fetchAll();

foreach ($customerAccounts as $account) {
    $nameKey = strtolower(trim((string)$account['customer_name']));
    $totalDue = (float)$account['total_due'];
    $totalPaid = (float)$account['total_paid'];

    $customerBalances[$nameKey] = [
        'customer_id' => (int)$account['customer_id'],
        'total_due' => $totalDue,
        'total_paid' => $totalPaid,
        'balance' => $totalDue - $totalPaid
    ];

    $customers[] = [
        'customer_id' => (int)$account['customer_id'],
        'customer_name' => $account['customer_name']
    ];
}

// ==================================================
// LOAD EXISTING SHOP / WALK-IN SALES
// ==================================================
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
        $walkInCustomers = (int)($dailySales['walk_in_customers'] ?? 0);
        $walkInPrice = (float)($dailySales['walk_in_price'] ?? 30.00);
        $walkInSales = $walkInCustomers * $walkInPrice;
    }
}

// ==================================================
// SAVE SHOP / WALK-IN + STATION EXPENSES + SHOP
// DELIVERY PAYMENTS
// ==================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $dailyId !== null
    && ($_POST['action'] ?? '') === 'save_shop_walkin'
) {
    try {
        $inputCustomers = trim($_POST['walk_in_customers'] ?? '');
        $inputMoney = trim($_POST['walk_in_money'] ?? '');

        $expenseCategories = $_POST['expense_category'] ?? [];
        $expenseNames = $_POST['expense_name'] ?? [];
        $expenseAmounts = $_POST['expense_amount'] ?? [];

        $deliveryCustomerNames = $_POST['delivery_customer'] ?? [];
        $deliverySlimQuantities = $_POST['delivery_slim'] ?? [];
        $deliveryRoundQuantities = $_POST['delivery_round'] ?? [];
        $deliveryPayments = $_POST['delivery_payment'] ?? [];
        $deliveryMethods = $_POST['delivery_method'] ?? [];

        foreach ([
            'expenseCategories' => &$expenseCategories,
            'expenseNames' => &$expenseNames,
            'expenseAmounts' => &$expenseAmounts,
            'deliveryCustomerNames' => &$deliveryCustomerNames,
            'deliverySlimQuantities' => &$deliverySlimQuantities,
            'deliveryRoundQuantities' => &$deliveryRoundQuantities,
            'deliveryPayments' => &$deliveryPayments,
            'deliveryMethods' => &$deliveryMethods
        ] as &$array) {
            if (!is_array($array)) {
                $array = [$array];
            }
        }
        unset($array);

        if ($inputCustomers !== '') {
            if (!ctype_digit($inputCustomers)) {
                throw new Exception('Shop customers must be a whole number.');
            }

            $customersValue = (int)$inputCustomers;
            $moneyValue = $customersValue * $walkInPrice;
        } elseif ($inputMoney !== '') {
            if (!is_numeric($inputMoney) || (float)$inputMoney < 0) {
                throw new Exception('Shop money must be a valid amount.');
            }

            $moneyValue = (float)$inputMoney;
            $calculatedCustomers = $moneyValue / $walkInPrice;

            if (abs($calculatedCustomers - round($calculatedCustomers)) > 0.000001) {
                throw new Exception(
                    'Shop money must divide evenly by ₱'
                    . number_format($walkInPrice, 2)
                    . ' per customer.'
                );
            }

            $customersValue = (int)round($calculatedCustomers);
        } else {
            $customersValue = 0;
            $moneyValue = 0;
        }

        $allowedCategories = [
            'Food' => 'Food',
            'Gas' => 'Gas',
            'Cash Advance' => 'Cash Advance',
            'Others' => 'Miscellaneous'
        ];

        $expensesToSave = [];
        $rowCount = max(count($expenseCategories), count($expenseNames), count($expenseAmounts));

        for ($i = 0; $i < $rowCount; $i++) {
            $category = trim((string)($expenseCategories[$i] ?? ''));
            $name = trim((string)($expenseNames[$i] ?? ''));
            $amount = trim((string)($expenseAmounts[$i] ?? ''));

            if ($category === '' && $name === '' && $amount === '') {
                continue;
            }

            if ($amount === '') {
                throw new Exception('Please enter an amount for every expense row you started.');
            }

            if (!is_numeric($amount) || (float)$amount < 0) {
                throw new Exception('Every expense amount must be a valid amount.');
            }

            if (!isset($allowedCategories[$category])) {
                throw new Exception('Please select an expense type for every expense row.');
            }

            if (($category === 'Cash Advance' || $category === 'Others') && $name === '') {
                throw new Exception('Please enter a name or description for Cash Advance or Others.');
            }

            $description = in_array($category, ['Cash Advance', 'Others'], true)
                ? $name
                : $category;

            $expensesToSave[] = [
                'category' => $allowedCategories[$category],
                'description' => $description,
                'amount' => (float)$amount
            ];
        }

        // Validate and prepare Shop delivery payments.
        $deliveryPaymentsToSave = [];
        $deliveryRowCount = max(
            count($deliveryCustomerNames),
            count($deliverySlimQuantities),
            count($deliveryRoundQuantities),
            count($deliveryPayments),
            count($deliveryMethods)
        );

        $allowedPaymentMethods = ['Cash', 'GCash', 'Bank Transfer', 'Other'];

        for ($i = 0; $i < $deliveryRowCount; $i++) {
            $customerName = trim((string)($deliveryCustomerNames[$i] ?? ''));
            $slimRaw = trim((string)($deliverySlimQuantities[$i] ?? ''));
            $roundRaw = trim((string)($deliveryRoundQuantities[$i] ?? ''));
            $paymentRaw = trim((string)($deliveryPayments[$i] ?? ''));
            $method = trim((string)($deliveryMethods[$i] ?? 'Cash'));

            if ($customerName === '' && $slimRaw === '' && $roundRaw === '' && $paymentRaw === '') {
                continue;
            }

            if ($customerName === '') {
                throw new Exception('Please enter or select a customer for every shop delivery payment row you started.');
            }

            if ($slimRaw === '' && $roundRaw === '') {
                throw new Exception('Please enter Slim or Round gallons for every shop delivery payment row you started.');
            }

            if ($slimRaw !== '' && (!ctype_digit($slimRaw) || (int)$slimRaw < 0)) {
                throw new Exception('Slim quantity must be a whole number.');
            }

            if ($roundRaw !== '' && (!ctype_digit($roundRaw) || (int)$roundRaw < 0)) {
                throw new Exception('Round quantity must be a whole number.');
            }

            $slimQuantity = $slimRaw === '' ? 0 : (int)$slimRaw;
            $roundQuantity = $roundRaw === '' ? 0 : (int)$roundRaw;
            $gallons = $slimQuantity + $roundQuantity;

            if ($gallons <= 0) {
                throw new Exception('Total gallons must be greater than zero for every shop delivery payment row.');
            }

            if ($paymentRaw === '' || !is_numeric($paymentRaw) || (float)$paymentRaw <= 0) {
                throw new Exception('Please enter a valid payment amount for every shop delivery payment row.');
            }

            $paymentAmount = (float)$paymentRaw;

            if (!in_array($method, $allowedPaymentMethods, true)) {
                throw new Exception('Please select a valid payment method.');
            }

            $impliedPrice = $paymentAmount / $gallons;
            if (abs($impliedPrice - round($impliedPrice)) > 0.000001) {
                throw new Exception(
                    'Payment for ' . $customerName . ' does not produce a whole-number price per gallon. '
                    . 'Please check the Slim, Round, and Payment Made values.'
                );
            }

            $deliveryPaymentsToSave[] = [
                'customer_name' => $customerName,
                'slim_quantity' => $slimQuantity,
                'round_quantity' => $roundQuantity,
                'price_per_gallon' => (float)round($impliedPrice),
                'payment' => $paymentAmount,
                'method' => $method
            ];
        }

        $pdo->beginTransaction();

        // Save/update Shop / Walk-in sales.
        $stmt = $pdo->prepare("
            SELECT daily_sales_id
            FROM daily_sales
            WHERE daily_id = ?
            LIMIT 1
        ");
        $stmt->execute([$dailyId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE daily_sales
                SET walk_in_customers = ?,
                    walk_in_price = ?
                WHERE daily_sales_id = ?
            ");
            $stmt->execute([
                $customersValue,
                $walkInPrice,
                $existing['daily_sales_id']
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

        // Save each Station expense as its own record.
        if (!empty($expensesToSave)) {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (
                    expense_date,
                    category,
                    description,
                    amount,
                    daily_id,
                    expense_location
                )
                VALUES (?, ?, ?, ?, ?, 'Station')
            ");

            foreach ($expensesToSave as $expense) {
                $stmt->execute([
                    $businessDate,
                    $expense['category'],
                    $expense['description'],
                    $expense['amount'],
                    $dailyId
                ]);
            }
        }

        // Save each Shop delivery payment as a delivery + Station payment.
        if (!empty($deliveryPaymentsToSave)) {
            foreach ($deliveryPaymentsToSave as $deliveryPayment) {
                $stmt = $pdo->prepare("
                    SELECT customer_id
                    FROM customers
                    WHERE LOWER(TRIM(customer_name)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                $stmt->execute([$deliveryPayment['customer_name']]);
                $customer = $stmt->fetch();

                if ($customer) {
                    $customerId = (int)$customer['customer_id'];
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (customer_name, gallon_price)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([
                        $deliveryPayment['customer_name'],
                        $deliveryPayment['price_per_gallon']
                    ]);
                    $customerId = (int)$pdo->lastInsertId();
                }

                // Record the delivery using the transaction's calculated historical price.
                $amountDue = ($deliveryPayment['slim_quantity'] + $deliveryPayment['round_quantity'])
                    * $deliveryPayment['price_per_gallon'];

                $stmt = $pdo->prepare("
                    INSERT INTO deliveries (
                        customer_id,
                        delivery_date,
                        slim_quantity,
                        round_quantity,
                        price_per_gallon,
                        amount_due,
                        daily_id
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $customerId,
                    $businessDate,
                    $deliveryPayment['slim_quantity'],
                    $deliveryPayment['round_quantity'],
                    $deliveryPayment['price_per_gallon'],
                    $amountDue,
                    $dailyId
                ]);

                $deliveryId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        delivery_id,
                        customer_id,
                        payment_date,
                        amount,
                        payment_method,
                        daily_id,
                        collection_location
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 'Station')
                ");
                $stmt->execute([
                    $deliveryId,
                    $customerId,
                    $businessDate,
                    $deliveryPayment['payment'],
                    $deliveryPayment['method'],
                    $dailyId
                ]);
            }
        }

        $pdo->commit();

        $walkInCustomers = $customersValue;
        $walkInSales = $customersValue * $walkInPrice;

        $savedParts = ['Shop / Walk-in sales'];

        $expenseCount = count($expensesToSave);
        if ($expenseCount > 0) {
            $savedParts[] = $expenseCount . ' station expense' . ($expenseCount === 1 ? '' : 's');
        }

        $deliveryPaymentCount = count($deliveryPaymentsToSave);
        if ($deliveryPaymentCount > 0) {
            $savedParts[] = $deliveryPaymentCount . ' shop delivery payment' . ($deliveryPaymentCount === 1 ? '' : 's');
        }

        $message = implode(' and ', $savedParts) . ' saved successfully.';
        $messageType = 'success';

        // Refresh customer list and account balances after saving.
        $customers = [];
        $customerBalances = [];

        $stmt = $pdo->query("
            SELECT
                c.customer_id,
                c.customer_name,
                COALESCE((
                    SELECT SUM(d.amount_due)
                    FROM deliveries d
                    WHERE d.customer_id = c.customer_id
                ), 0) AS total_due,
                COALESCE((
                    SELECT SUM(p.amount)
                    FROM payments p
                    WHERE p.customer_id = c.customer_id
                ), 0) AS total_paid
            FROM customers c
            ORDER BY c.customer_name ASC
        ");
        $customerAccounts = $stmt->fetchAll();

        foreach ($customerAccounts as $account) {
            $nameKey = strtolower(trim((string)$account['customer_name']));
            $totalDue = (float)$account['total_due'];
            $totalPaid = (float)$account['total_paid'];

            $customerBalances[$nameKey] = [
                'customer_id' => (int)$account['customer_id'],
                'total_due' => $totalDue,
                'total_paid' => $totalPaid,
                'balance' => $totalDue - $totalPaid
            ];

            $customers[] = [
                'customer_id' => (int)$account['customer_id'],
                'customer_name' => $account['customer_name']
            ];
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Closing - Marcid Blue</title>
    <link rel="stylesheet" href="../assets/css/app.css">
    <style>
        .shop-walkin-layout {
            display: grid;
            grid-template-columns: minmax(110px, 0.65fr) minmax(190px, 1fr) minmax(180px, 1fr);
            gap: 18px;
            align-items: end;
        }

        .shop-customers-field {
            max-width: 150px;
        }

        .shop-money-field {
            max-width: 230px;
        }

        .shop-computed-sales {
            padding: 8px 0 4px 8px;
        }

        .expenses-panel,
        .delivery-payments-panel {
            margin-top: 24px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .expenses-panel-header,
        .delivery-payments-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }

        .expenses-title,
        .delivery-payments-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .expenses-subtitle,
        .delivery-payments-subtitle {
            margin-top: 3px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .expense-rows,
        .delivery-payment-rows {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .expense-row {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(160px, 0.8fr) minmax(220px, 1.2fr) 38px;
            gap: 12px;
            align-items: end;
            padding: 14px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
        }

        .delivery-payment-row {
            display: grid;
            grid-template-columns: minmax(210px, 1.5fr) minmax(75px, 0.55fr) minmax(75px, 0.55fr) minmax(145px, 1fr) minmax(125px, 0.8fr) minmax(125px, 0.85fr) 38px;
            gap: 10px;
            align-items: end;
            padding: 14px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
        }

        .expense-row .form-group,
        .delivery-payment-row .form-group {
            min-width: 0;
        }

        .expense-row .form-input:disabled {
            background: var(--surface);
            color: var(--text-muted);
            opacity: 0.72;
            cursor: not-allowed;
        }

        .expense-remove,
        .delivery-payment-remove {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--danger);
            font-size: 18px;
            cursor: pointer;
            transition: 0.15s ease;
        }

        .expense-remove:hover,
        .delivery-payment-remove:hover {
            background: var(--danger-light);
            border-color: var(--danger);
        }

        .add-expense-button,
        .add-delivery-payment-button {
            margin-top: 12px;
        }

        .shop-compute-message {
            margin-top: 14px;
        }

        .shop-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .delivery-status-legend {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 11px;
        }

        .delivery-status-legend .legend-label {
            font-weight: 600;
            margin-right: 2px;
        }

        .delivery-status-item {
            white-space: nowrap;
        }

        .delivery-status-item::first-letter {
            font-size: 10px;
        }

        .delivery-status-paid { color: var(--success); }
        .delivery-status-due { color: var(--warning); }
        .delivery-status-unpaid { color: var(--danger); }
        .delivery-status-overpaid { color: var(--primary); }

        .delivery-price-preview {
            margin-top: 5px;
            color: var(--text-muted);
            font-size: 11px;
            line-height: 1.2;
        }

        .delivery-balance-group {
            min-width: 0;
        }

        .delivery-balance {
            min-height: 38px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .delivery-balance-neutral {
            color: var(--text-muted);
            font-weight: 500;
        }

        .delivery-balance-paid {
            color: var(--success);
            background: var(--success-light);
            border-color: rgba(46, 155, 91, 0.18);
        }

        .delivery-balance-due {
            color: var(--warning);
            background: var(--warning-light);
            border-color: rgba(229, 154, 36, 0.18);
        }

        .delivery-balance-unpaid {
            color: var(--danger);
            background: var(--danger-light);
            border-color: rgba(217, 83, 79, 0.18);
        }

        .delivery-balance-overpaid {
            color: var(--primary-dark);
            background: var(--primary-light);
            border-color: rgba(22, 135, 201, 0.18);
        }

        @media (max-width: 1200px) {
            .delivery-payment-row {
                grid-template-columns: 1.5fr 0.65fr 0.65fr 1fr 0.8fr 0.85fr 38px;
            }
        }

        @media (max-width: 900px) {
            .shop-walkin-layout {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .shop-computed-sales {
                padding-left: 0;
            }

            .expense-row {
                grid-template-columns: 1fr 1fr;
            }

            .expense-row .expense-name-group {
                grid-column: 1 / -1;
            }

            .expense-remove {
                grid-column: 2;
                justify-self: end;
            }

            .delivery-payment-row {
                grid-template-columns: 1fr 1fr 1fr;
            }

            .delivery-payment-row .delivery-customer-group {
                grid-column: 1 / -1;
            }

            .delivery-payment-row .delivery-payment-group {
                grid-column: span 2;
            }

            .delivery-payment-row .delivery-balance-group {
                grid-column: span 2;
            }

            .delivery-payment-remove {
                justify-self: end;
            }

            .delivery-status-legend {
                justify-content: flex-start;
            }
        }

        @media (max-width: 650px) {
            .shop-walkin-layout {
                grid-template-columns: 1fr;
            }

            .shop-customers-field,
            .shop-money-field {
                max-width: none;
            }

            .expense-row,
            .delivery-payment-row {
                grid-template-columns: 1fr;
            }

            .expense-row .expense-name-group,
            .delivery-payment-row .delivery-customer-group,
            .delivery-payment-row .delivery-payment-group,
            .delivery-payment-row .delivery-balance-group {
                grid-column: auto;
            }

            .expense-remove,
            .delivery-payment-remove {
                grid-column: auto;
                justify-self: start;
            }

            .expenses-panel-header,
            .delivery-payments-panel-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .shop-actions {
                justify-content: stretch;
            }

            .shop-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>

<div class="app">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="../assets/images/mb-logo.png" alt="Marcid Blue Logo">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">Main</div>

            <a href="home.php" class="nav-item">
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

            <a href="daily-closing.php" class="nav-item active">
                🧾
                <span>Daily Closing</span>
            </a>

            <div class="nav-section-title" style="margin-top: 25px;">System</div>

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

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">Daily Closing</div>
            <div class="topbar-user">
                👤
                <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>
            </div>
        </header>

        <section class="page">
            <div class="page-header">
                <h1 class="page-title">Daily Closing</h1>
                <p class="page-subtitle">
                    <?php if ($businessDate): ?>
                        <?= date('l, F j, Y', strtotime($businessDate)) ?>
                    <?php else: ?>
                        No active daily closing
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!$dailyRecord): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-warning">
                            No active daily record is available.
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <div class="summary-grid">
                    <div class="card summary-card">
                        <div class="summary-label">Shop Sales</div>
                        <div class="summary-value" id="dashboardShopSales">
                            ₱<?= number_format($walkInSales, 2) ?>
                        </div>
                        <div class="summary-description">Current shop / walk-in sales</div>
                    </div>

                    <div class="card summary-card">
                        <div class="summary-label">Shop Customers</div>
                        <div class="summary-value" id="dashboardShopCustomers">
                            <?= number_format($walkInCustomers) ?>
                        </div>
                        <div class="summary-description">Walk-in customers served</div>
                    </div>

                    <div class="card summary-card">
                        <div class="summary-label">Price per Customer</div>
                        <div class="summary-value">
                            ₱<?= number_format($walkInPrice, 2) ?>
                        </div>
                        <div class="summary-description">Current shop rate</div>
                    </div>

                    <div class="card summary-card">
                        <div class="summary-label">Daily Status</div>
                        <div class="summary-value">
                            <span class="badge badge-warning">Open</span>
                        </div>
                        <div class="summary-description">
                            <?= htmlspecialchars($businessDate) ?>
                        </div>
                    </div>
                </div>

                <br>

                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Shop / Walk-in</div>
                            <div class="section-description">
                                Record regular customers, money received, station expenses, and delivery payments received at the shop.
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="shopWalkInForm">
                            <input type="hidden" name="action" value="save_shop_walkin">

                            <div class="shop-walkin-layout">
                                <div class="shop-customers-field">
                                    <label for="walk_in_customers" class="form-label">
                                        Customers <span class="summary-description">(× ₱30)</span>
                                    </label>
                                    <input
                                        type="number"
                                        id="walk_in_customers"
                                        name="walk_in_customers"
                                        class="form-input"
                                        min="0"
                                        step="1"
                                        value="<?= $walkInCustomers > 0 ? htmlspecialchars($walkInCustomers) : '' ?>"
                                        placeholder="Example: 150"
                                    >
                                </div>

                                <div class="shop-money-field">
                                    <label for="walk_in_money" class="form-label">
                                        Money Received
                                    </label>
                                    <input
                                        type="number"
                                        id="walk_in_money"
                                        name="walk_in_money"
                                        class="form-input"
                                        min="0"
                                        step="0.01"
                                        value="<?= $walkInSales > 0 ? htmlspecialchars(number_format($walkInSales, 2, '.', '')) : '' ?>"
                                        placeholder="Example: 4500"
                                    >
                                </div>

                                <div class="shop-computed-sales">
                                    <div class="summary-label">Computed Sales</div>
                                    <div class="summary-value" id="shopComputedSales">
                                        ₱<?= number_format($walkInSales, 2) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="expenses-panel">
                                <div class="expenses-panel-header">
                                    <div>
                                        <div class="expenses-title">Station Expenses</div>
                                        <div class="expenses-subtitle">
                                            Add one or more expenses for today's station operation.
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-secondary add-expense-button"
                                        id="addExpenseButton"
                                    >
                                        + Expenses
                                    </button>
                                </div>

                                <div id="expenseRows" class="expense-rows">
                                    <div class="expense-row">
                                        <div class="form-group">
                                            <label class="form-label">Expense</label>
                                            <select name="expense_category[]" class="form-input expense-category">
                                                <option value="">No Expense</option>
                                                <option value="Food">Food</option>
                                                <option value="Gas">Gas</option>
                                                <option value="Cash Advance">Cash Advance</option>
                                                <option value="Others">Others</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Amount</label>
                                            <input
                                                type="number"
                                                name="expense_amount[]"
                                                class="form-input expense-amount"
                                                min="0"
                                                step="0.01"
                                                placeholder="Example: 500"
                                            >
                                        </div>

                                        <div class="form-group expense-name-group">
                                            <label class="form-label expense-name-label">Name / Description</label>
                                            <input
                                                type="text"
                                                name="expense_name[]"
                                                class="form-input expense-name"
                                                maxlength="255"
                                                placeholder="Only needed for Cash Advance / Others"
                                                disabled
                                            >
                                        </div>

                                        <button
                                            type="button"
                                            class="expense-remove"
                                            title="Remove expense"
                                            aria-label="Remove expense"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="delivery-payments-panel">
                                <div class="delivery-payments-panel-header">
                                    <div>
                                        <div class="delivery-payments-title">Shop Delivery Payments</div>
                                        <div class="delivery-payments-subtitle">
                                            Record delivery customers who paid at the shop. Price per gallon is calculated from the transaction.
                                        </div>
                                        <div class="delivery-status-legend" aria-label="Delivery payment status legend">
                                            <span class="legend-label">Status:</span>
                                            <span class="delivery-status-item delivery-status-paid">● Paid</span>
                                            <span class="delivery-status-item delivery-status-due">● Due</span>
                                            <span class="delivery-status-item delivery-status-unpaid">● Unpaid</span>
                                            <span class="delivery-status-item delivery-status-overpaid">● Overpaid</span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn btn-secondary add-delivery-payment-button"
                                        id="addDeliveryPaymentButton"
                                    >
                                        + Payment
                                    </button>
                                </div>

                                <div id="deliveryPaymentRows" class="delivery-payment-rows">
                                    <div class="delivery-payment-row">
                                        <div class="form-group delivery-customer-group">
                                            <label class="form-label">Customer</label>
                                            <input
                                                type="text"
                                                name="delivery_customer[]"
                                                class="form-input delivery-customer"
                                                list="shopDeliveryCustomerList"
                                                maxlength="100"
                                                placeholder="Select or enter customer"
                                                autocomplete="off"
                                            >
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Slim</label>
                                            <input
                                                type="number"
                                                name="delivery_slim[]"
                                                class="form-input delivery-slim"
                                                min="0"
                                                step="1"
                                                placeholder="0"
                                            >
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Round</label>
                                            <input
                                                type="number"
                                                name="delivery_round[]"
                                                class="form-input delivery-round"
                                                min="0"
                                                step="1"
                                                placeholder="0"
                                            >
                                        </div>

                                        <div class="form-group delivery-payment-group">
                                            <label class="form-label">Payment Made</label>
                                            <input
                                                type="number"
                                                name="delivery_payment[]"
                                                class="form-input delivery-payment"
                                                min="0"
                                                step="0.01"
                                                placeholder="Example: 770"
                                            >
                                            <div class="delivery-price-preview">Price/Gal —</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">Method</label>
                                            <select name="delivery_method[]" class="form-input delivery-method">
                                                <option value="Cash" selected>Cash</option>
                                                <option value="GCash">GCash</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>

                                        <div class="form-group delivery-balance-group">
                                            <label class="form-label">Balance</label>
                                            <div class="delivery-balance delivery-balance-neutral" aria-live="polite">—</div>
                                        </div>

                                        <button
                                            type="button"
                                            class="delivery-payment-remove"
                                            title="Remove payment"
                                            aria-label="Remove payment"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <datalist id="shopDeliveryCustomerList">
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= htmlspecialchars($customer['customer_name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>

                            <div
                                id="shopComputeMessage"
                                class="summary-description shop-compute-message"
                            >
                                Enter customers or money, then press Compute.
                            </div>

                            <div class="shop-actions">
                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    id="shopComputeButton"
                                >
                                    Compute
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    id="shopSaveButton"
                                >
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>
        </section>
    </main>
</div>

<script>
(function () {
    const form = document.getElementById('shopWalkInForm');
    if (!form) return;

    const customersInput = document.getElementById('walk_in_customers');
    const moneyInput = document.getElementById('walk_in_money');
    const computedSales = document.getElementById('shopComputedSales');
    const dashboardSales = document.getElementById('dashboardShopSales');
    const dashboardCustomers = document.getElementById('dashboardShopCustomers');
    const computeMessage = document.getElementById('shopComputeMessage');
    const expenseRows = document.getElementById('expenseRows');
    const addExpenseButton = document.getElementById('addExpenseButton');
    const deliveryPaymentRows = document.getElementById('deliveryPaymentRows');
    const addDeliveryPaymentButton = document.getElementById('addDeliveryPaymentButton');
    const pricePerCustomer = <?= json_encode($walkInPrice) ?>;
    const customerAccounts = <?= json_encode($customerBalances, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function formatCurrency(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateExpenseRow(row) {
        const category = row.querySelector('.expense-category');
        const name = row.querySelector('.expense-name');
        const label = row.querySelector('.expense-name-label');

        if (!category || !name || !label) return;

        const needsName = category.value === 'Cash Advance' || category.value === 'Others';
        name.disabled = !needsName;
        name.required = needsName;
        label.textContent = needsName ? 'Name / Description *' : 'Name / Description';

        if (needsName) {
            name.placeholder = category.value === 'Cash Advance'
                ? 'Enter recipient name'
                : 'Enter expense description';
        } else {
            name.value = '';
            name.placeholder = 'Not required for Food / Gas';
        }
    }

    function updateDeliveryStatus(row) {
        const customerInput = row.querySelector('.delivery-customer');
        const slimInput = row.querySelector('.delivery-slim');
        const roundInput = row.querySelector('.delivery-round');
        const paymentInput = row.querySelector('.delivery-payment');
        const balanceElement = row.querySelector('.delivery-balance');
        const pricePreview = row.querySelector('.delivery-price-preview');

        if (!customerInput || !balanceElement) return;

        const customerName = customerInput.value.trim().toLowerCase();
        const slim = parseInt(slimInput?.value || '0', 10) || 0;
        const round = parseInt(roundInput?.value || '0', 10) || 0;
        const payment = parseFloat(paymentInput?.value || '0') || 0;
        const gallons = slim + round;

        if (pricePreview) {
            if (gallons > 0 && payment > 0) {
                const price = payment / gallons;
                if (Math.abs(price - Math.round(price)) < 0.000001) {
                    pricePreview.textContent = 'Price/Gal ' + formatCurrency(price);
                } else {
                    pricePreview.textContent = 'Price/Gal ' + formatCurrency(price) + ' · check amount';
                }
            } else {
                pricePreview.textContent = 'Price/Gal —';
            }
        }

        balanceElement.className = 'delivery-balance delivery-balance-neutral';

        if (!customerName) {
            balanceElement.textContent = '—';
            return;
        }

        const account = customerAccounts[customerName];

        if (!account) {
            balanceElement.textContent = '—';
            return;
        }

        const balance = Number(account.balance || 0);
        const epsilon = 0.005;

        if (balance > epsilon) {
            if (Number(account.total_paid || 0) <= epsilon) {
                balanceElement.className = 'delivery-balance delivery-balance-unpaid';
                balanceElement.textContent = '₱' + balance.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' Unpaid';
            } else {
                balanceElement.className = 'delivery-balance delivery-balance-due';
                balanceElement.textContent = '₱' + balance.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' Due';
            }
        } else if (balance < -epsilon) {
            const overpaid = Math.abs(balance);
            balanceElement.className = 'delivery-balance delivery-balance-overpaid';
            balanceElement.textContent = '₱' + overpaid.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' Overpaid';
        } else {
            balanceElement.className = 'delivery-balance delivery-balance-paid';
            balanceElement.textContent = '✓ Paid';
        }
    }

    function calculateShop() {
        const customersValue = customersInput.value.trim();
        const moneyValue = moneyInput.value.trim();

        let customers = 0;
        let sales = 0;

        if (customersValue !== '') {
            customers = Math.max(0, parseInt(customersValue, 10) || 0);
            sales = customers * pricePerCustomer;

            moneyInput.value = sales > 0 ? sales.toFixed(2) : '';
            computeMessage.textContent = customers + ' customers × ' + formatCurrency(pricePerCustomer) + ' = ' + formatCurrency(sales);
        } else if (moneyValue !== '') {
            sales = Math.max(0, parseFloat(moneyValue) || 0);
            const calculatedCustomers = sales / pricePerCustomer;

            if (Math.abs(calculatedCustomers - Math.round(calculatedCustomers)) > 0.000001) {
                computedSales.textContent = formatCurrency(sales);
                computeMessage.textContent = 'Money received does not divide evenly by ' + formatCurrency(pricePerCustomer) + ' per customer.';
                return;
            }

            customers = Math.round(calculatedCustomers);
            customersInput.value = customers > 0 ? customers : '';
            computeMessage.textContent = formatCurrency(sales) + ' = ' + customers + ' customers × ' + formatCurrency(pricePerCustomer);
        } else {
            computeMessage.textContent = 'Enter customers or money, then press Compute.';
        }

        computedSales.textContent = formatCurrency(sales);
        dashboardSales.textContent = formatCurrency(sales);
        dashboardCustomers.textContent = customers.toLocaleString('en-PH');
    }

    function createExpenseRow() {
        const row = document.createElement('div');
        row.className = 'expense-row';
        row.innerHTML = `
            <div class="form-group">
                <label class="form-label">Expense</label>
                <select name="expense_category[]" class="form-input expense-category">
                    <option value="">No Expense</option>
                    <option value="Food">Food</option>
                    <option value="Gas">Gas</option>
                    <option value="Cash Advance">Cash Advance</option>
                    <option value="Others">Others</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Amount</label>
                <input
                    type="number"
                    name="expense_amount[]"
                    class="form-input expense-amount"
                    min="0"
                    step="0.01"
                    placeholder="Example: 500"
                >
            </div>

            <div class="form-group expense-name-group">
                <label class="form-label expense-name-label">Name / Description</label>
                <input
                    type="text"
                    name="expense_name[]"
                    class="form-input expense-name"
                    maxlength="255"
                    placeholder="Not required for Food / Gas"
                    disabled
                >
            </div>

            <button
                type="button"
                class="expense-remove"
                title="Remove expense"
                aria-label="Remove expense"
            >×</button>
        `;

        expenseRows.appendChild(row);
        updateExpenseRow(row);
        row.querySelector('.expense-category').focus();
    }

    function createDeliveryPaymentRow() {
        const row = document.createElement('div');
        row.className = 'delivery-payment-row';
        row.innerHTML = `
            <div class="form-group delivery-customer-group">
                <label class="form-label">Customer</label>
                <input
                    type="text"
                    name="delivery_customer[]"
                    class="form-input delivery-customer"
                    list="shopDeliveryCustomerList"
                    maxlength="100"
                    placeholder="Select or enter customer"
                    autocomplete="off"
                >
            </div>

            <div class="form-group">
                <label class="form-label">Slim</label>
                <input
                    type="number"
                    name="delivery_slim[]"
                    class="form-input delivery-slim"
                    min="0"
                    step="1"
                    placeholder="0"
                >
            </div>

            <div class="form-group">
                <label class="form-label">Round</label>
                <input
                    type="number"
                    name="delivery_round[]"
                    class="form-input delivery-round"
                    min="0"
                    step="1"
                    placeholder="0"
                >
            </div>

            <div class="form-group delivery-payment-group">
                <label class="form-label">Payment Made</label>
                <input
                    type="number"
                    name="delivery_payment[]"
                    class="form-input delivery-payment"
                    min="0"
                    step="0.01"
                    placeholder="Example: 770"
                >
                <div class="delivery-price-preview">Price/Gal —</div>
            </div>

            <div class="form-group">
                <label class="form-label">Method</label>
                <select name="delivery_method[]" class="form-input delivery-method">
                    <option value="Cash" selected>Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group delivery-balance-group">
                <label class="form-label">Balance</label>
                <div class="delivery-balance delivery-balance-neutral" aria-live="polite">—</div>
            </div>

            <button
                type="button"
                class="delivery-payment-remove"
                title="Remove payment"
                aria-label="Remove payment"
            >×</button>
        `;

        deliveryPaymentRows.appendChild(row);
        updateDeliveryStatus(row);
        row.querySelector('.delivery-customer').focus();
    }

    document.getElementById('shopComputeButton').addEventListener('click', calculateShop);

    customersInput.addEventListener('input', function () {
        if (customersInput.value.trim() !== '') {
            moneyInput.value = '';
        }
    });

    moneyInput.addEventListener('input', function () {
        if (moneyInput.value.trim() !== '') {
            customersInput.value = '';
        }
    });

    addExpenseButton.addEventListener('click', createExpenseRow);
    addDeliveryPaymentButton.addEventListener('click', createDeliveryPaymentRow);

    expenseRows.addEventListener('change', function (event) {
        if (event.target.classList.contains('expense-category')) {
            updateExpenseRow(event.target.closest('.expense-row'));
        }
    });

    expenseRows.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.expense-remove');
        if (!removeButton) return;

        const rows = expenseRows.querySelectorAll('.expense-row');
        const row = removeButton.closest('.expense-row');

        if (rows.length === 1) {
            row.querySelector('.expense-category').value = '';
            row.querySelector('.expense-amount').value = '';
            row.querySelector('.expense-name').value = '';
            updateExpenseRow(row);
            return;
        }

        row.remove();
    });

    deliveryPaymentRows.addEventListener('input', function (event) {
        if (
            event.target.classList.contains('delivery-customer')
            || event.target.classList.contains('delivery-slim')
            || event.target.classList.contains('delivery-round')
            || event.target.classList.contains('delivery-payment')
        ) {
            updateDeliveryStatus(event.target.closest('.delivery-payment-row'));
        }
    });

    deliveryPaymentRows.addEventListener('change', function (event) {
        if (event.target.classList.contains('delivery-customer')) {
            updateDeliveryStatus(event.target.closest('.delivery-payment-row'));
        }
    });

    deliveryPaymentRows.addEventListener('click', function (event) {
        const removeButton = event.target.closest('.delivery-payment-remove');
        if (!removeButton) return;

        const rows = deliveryPaymentRows.querySelectorAll('.delivery-payment-row');
        const row = removeButton.closest('.delivery-payment-row');

        if (rows.length === 1) {
            row.querySelector('.delivery-customer').value = '';
            row.querySelector('.delivery-slim').value = '';
            row.querySelector('.delivery-round').value = '';
            row.querySelector('.delivery-payment').value = '';
            row.querySelector('.delivery-method').value = 'Cash';
            updateDeliveryStatus(row);
            return;
        }

        row.remove();
    });

    expenseRows.querySelectorAll('.expense-row').forEach(updateExpenseRow);
    deliveryPaymentRows.querySelectorAll('.delivery-payment-row').forEach(updateDeliveryStatus);
})();
</script>

</body>
</html>
