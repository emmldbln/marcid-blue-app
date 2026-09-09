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
// SAVE SHOP / WALK-IN + STATION EXPENSE
// ==================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $dailyId !== null
    && ($_POST['action'] ?? '') === 'save_shop_walkin'
) {
    try {
        $inputCustomers = trim($_POST['walk_in_customers'] ?? '');
        $inputMoney = trim($_POST['walk_in_money'] ?? '');
        $expenseCategory = trim($_POST['expense_category'] ?? '');
        $expenseName = trim($_POST['expense_name'] ?? '');
        $expenseAmount = trim($_POST['expense_amount'] ?? '');

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

        // Expense is optional. If an amount is entered, require a valid expense type.
        if ($expenseAmount !== '') {
            if (!is_numeric($expenseAmount) || (float)$expenseAmount < 0) {
                throw new Exception('Expense amount must be a valid amount.');
            }

            $expenseAmountValue = (float)$expenseAmount;

            $allowedCategories = [
                'Food' => 'Food',
                'Gas' => 'Gas',
                'Cash Advance' => 'Cash Advance',
                'Others' => 'Miscellaneous'
            ];

            if (!isset($allowedCategories[$expenseCategory])) {
                throw new Exception('Please select an expense type.');
            }

            if (($expenseCategory === 'Cash Advance' || $expenseCategory === 'Others') && $expenseName === '') {
                throw new Exception('Please enter a name or description for this expense.');
            }

            $expenseDescription = in_array($expenseCategory, ['Cash Advance', 'Others'], true)
                ? $expenseName
                : $expenseCategory;
            $expenseDbCategory = $allowedCategories[$expenseCategory];
        }

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

        if ($expenseAmount !== '') {
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
            $stmt->execute([
                $businessDate,
                $expenseDbCategory,
                $expenseDescription,
                $expenseAmountValue,
                $dailyId
            ]);
        }

        $walkInCustomers = $customersValue;
        $walkInSales = $customersValue * $walkInPrice;
        $message = $expenseAmount !== ''
            ? 'Shop / Walk-in sales and station expense saved successfully.'
            : 'Shop / Walk-in sales saved successfully.';
        $messageType = 'success';
    } catch (Throwable $e) {
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
        .shop-walkin-grid {
            display: grid;
            grid-template-columns: minmax(110px, 0.7fr) minmax(180px, 1fr) minmax(180px, 1fr) minmax(180px, 1fr) minmax(170px, 0.9fr);
            gap: 16px;
            align-items: end;
        }

        .shop-customers-field {
            max-width: 150px;
        }

        .shop-money-field {
            max-width: 230px;
        }

        .expense-name-field {
            margin-top: 12px;
        }

        .shop-computed-sales {
            padding-left: 8px;
        }

        @media (max-width: 1050px) {
            .shop-walkin-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .shop-computed-sales {
                padding-left: 0;
            }
        }

        @media (max-width: 650px) {
            .shop-walkin-grid {
                grid-template-columns: 1fr;
            }

            .shop-customers-field,
            .shop-money-field {
                max-width: none;
            }
        }
    </style>
</head>
<body>

<div class="app">

    <!-- =================================================
         SIDEBAR / DASHBOARD NAVIGATION
         ================================================= -->
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

        <!-- =================================================
             TOP DASHBOARD
             ================================================= -->
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

                <!-- TOP DASHBOARD SUMMARY -->
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

                <!-- =================================================
                     SHOP / WALK-IN ONLY
                     ================================================= -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Shop / Walk-in</div>
                            <div class="section-description">
                                Record regular customers, money received, and station expenses.
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

                            <div class="shop-walkin-grid">
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

                                <div>
                                    <label for="expense_category" class="form-label">
                                        Expense
                                    </label>
                                    <select
                                        id="expense_category"
                                        name="expense_category"
                                        class="form-input"
                                    >
                                        <option value="">No Expense</option>
                                        <option value="Food">Food</option>
                                        <option value="Gas">Gas</option>
                                        <option value="Cash Advance">Cash Advance</option>
                                        <option value="Others">Others</option>
                                    </select>

                                    <div id="expenseNameWrap" class="expense-name-field" style="display: none;">
                                        <label for="expense_name" class="form-label" id="expenseNameLabel">
                                            Name / Description
                                        </label>
                                        <input
                                            type="text"
                                            id="expense_name"
                                            name="expense_name"
                                            class="form-input"
                                            maxlength="255"
                                            placeholder="Enter name or description"
                                        >
                                    </div>
                                </div>

                                <div>
                                    <label for="expense_amount" class="form-label">
                                        Expense Amount
                                    </label>
                                    <input
                                        type="number"
                                        id="expense_amount"
                                        name="expense_amount"
                                        class="form-input"
                                        min="0"
                                        step="0.01"
                                        placeholder="Example: 500"
                                    >
                                </div>

                                <div class="shop-computed-sales">
                                    <div class="summary-label">Computed Sales</div>
                                    <div class="summary-value" id="shopComputedSales">
                                        ₱<?= number_format($walkInSales, 2) ?>
                                    </div>
                                </div>
                            </div>

                            <div
                                id="shopComputeMessage"
                                class="summary-description"
                                style="margin-top: 12px;"
                            >
                                Enter customers or money, then press Compute.
                            </div>

                            <div
                                style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px;"
                            >
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
document.addEventListener('DOMContentLoaded', function () {
    const customersInput = document.getElementById('walk_in_customers');
    const moneyInput = document.getElementById('walk_in_money');
    const expenseCategory = document.getElementById('expense_category');
    const expenseNameWrap = document.getElementById('expenseNameWrap');
    const expenseNameInput = document.getElementById('expense_name');
    const expenseNameLabel = document.getElementById('expenseNameLabel');
    const computeButton = document.getElementById('shopComputeButton');
    const computedSales = document.getElementById('shopComputedSales');
    const computeMessage = document.getElementById('shopComputeMessage');
    const dashboardSales = document.getElementById('dashboardShopSales');
    const dashboardCustomers = document.getElementById('dashboardShopCustomers');

    if (!customersInput || !moneyInput || !computeButton) {
        return;
    }

    const price = <?= json_encode($walkInPrice) ?>;

    function money(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateExpenseName() {
        if (!expenseCategory || !expenseNameWrap) {
            return;
        }

        const needsName = expenseCategory.value === 'Cash Advance' || expenseCategory.value === 'Others';
        expenseNameWrap.style.display = needsName ? 'block' : 'none';

        if (!needsName && expenseNameInput) {
            expenseNameInput.value = '';
        }

        if (expenseNameLabel) {
            expenseNameLabel.textContent = expenseCategory.value === 'Cash Advance'
                ? 'Name'
                : 'Name / Description';
        }

        if (expenseNameInput) {
            expenseNameInput.placeholder = expenseCategory.value === 'Cash Advance'
                ? 'Enter name'
                : 'Enter name or description';
        }
    }

    function computeShop() {
        const customerText = customersInput.value.trim();
        const moneyText = moneyInput.value.trim();

        let customers = 0;
        let sales = 0;

        if (customerText !== '') {
            if (!/^\d+$/.test(customerText)) {
                computedSales.textContent = '—';
                computeMessage.textContent = 'Customers must be a whole number.';
                return false;
            }

            customers = parseInt(customerText, 10);
            sales = customers * price;
            moneyInput.value = sales.toFixed(2);
        } else if (moneyText !== '') {
            const moneyValue = parseFloat(moneyText);

            if (!Number.isFinite(moneyValue) || moneyValue < 0) {
                computedSales.textContent = '—';
                computeMessage.textContent = 'Money received must be a valid amount.';
                return false;
            }

            const calculatedCustomers = moneyValue / price;

            if (!Number.isInteger(calculatedCustomers)) {
                computedSales.textContent = '—';
                computeMessage.textContent =
                    'Money must divide evenly by ' + money(price) + ' per customer.';
                return false;
            }

            customers = calculatedCustomers;
            sales = moneyValue;
            customersInput.value = customers;
        }

        computedSales.textContent = money(sales);
        computeMessage.textContent =
            customers.toLocaleString('en-PH') + ' customers × ' + money(price) + ' = ' + money(sales);

        if (dashboardSales) {
            dashboardSales.textContent = money(sales);
        }

        if (dashboardCustomers) {
            dashboardCustomers.textContent = customers.toLocaleString('en-PH');
        }

        return true;
    }

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

    if (expenseCategory) {
        expenseCategory.addEventListener('change', updateExpenseName);
        updateExpenseName();
    }

    computeButton.addEventListener('click', computeShop);
});
</script>

</body>
</html>
