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
// SAVE SHOP / WALK-IN + STATION EXPENSES
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

        if (!is_array($expenseCategories)) {
            $expenseCategories = [$expenseCategories];
        }
        if (!is_array($expenseNames)) {
            $expenseNames = [$expenseNames];
        }
        if (!is_array($expenseAmounts)) {
            $expenseAmounts = [$expenseAmounts];
        }

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

            // Completely empty expense rows are ignored.
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

        $walkInCustomers = $customersValue;
        $walkInSales = $customersValue * $walkInPrice;

        $expenseCount = count($expensesToSave);
        if ($expenseCount > 0) {
            $message = 'Shop / Walk-in sales and ' . $expenseCount . ' station expense'
                . ($expenseCount === 1 ? '' : 's')
                . ' saved successfully.';
        } else {
            $message = 'Shop / Walk-in sales saved successfully.';
        }

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

        .expenses-panel {
            margin-top: 24px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .expenses-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }

        .expenses-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .expenses-subtitle {
            margin-top: 3px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .expense-rows {
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

        .expense-row .form-group {
            min-width: 0;
        }

        .expense-row .form-input:disabled {
            background: var(--surface);
            color: var(--text-muted);
            opacity: 0.72;
            cursor: not-allowed;
        }

        .expense-remove {
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

        .expense-remove:hover {
            background: var(--danger-light);
            border-color: var(--danger);
        }

        .add-expense-button {
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
        }

        @media (max-width: 650px) {
            .shop-walkin-layout {
                grid-template-columns: 1fr;
            }

            .shop-customers-field,
            .shop-money-field {
                max-width: none;
            }

            .expense-row {
                grid-template-columns: 1fr;
            }

            .expense-row .expense-name-group {
                grid-column: auto;
            }

            .expense-remove {
                grid-column: auto;
                justify-self: start;
            }

            .expenses-panel-header {
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
    const pricePerCustomer = <?= json_encode($walkInPrice) ?>;

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

    expenseRows.querySelectorAll('.expense-row').forEach(updateExpenseRow);
})();
</script>

</body>
</html>
