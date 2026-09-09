<?php

date_default_timezone_set('Asia/Manila');
require_once '../auth/auth.php';
require_once '../config/database.php';
requireAdmin();

$stmt = $pdo->prepare(
    "SELECT *
     FROM daily_records
     WHERE status = 'Open'
     ORDER BY daily_id DESC
     LIMIT 1"
);
$stmt->execute();
$dailyRecord = $stmt->fetch();
$dailyId = $dailyRecord['daily_id'] ?? null;
$businessDate = $dailyRecord['business_date'] ?? null;

$walkInCustomers = 0;
$walkInPrice = 30.00;
$walkInSales = 0.00;
$walkInOtherSales = 0.00;
$message = '';
$messageType = '';

function loadCustomerAccounts(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT
            c.customer_id,
            c.customer_name,
            c.gallon_price,
            COALESCE(
                (
                    SELECT SUM(d.amount_due)
                    FROM deliveries d
                    WHERE d.customer_id = c.customer_id
                ),
                0
            ) AS total_due,
            COALESCE(
                (
                    SELECT SUM(p.amount)
                    FROM payments p
                    WHERE p.customer_id = c.customer_id
                ),
                0
            ) AS total_paid
         FROM customers c
         ORDER BY c.customer_name ASC"
    );

    $accounts = $stmt->fetchAll();
    $customers = [];
    $balances = [];

    foreach ($accounts as $account) {
        $key = strtolower(trim((string) $account['customer_name']));
        $due = (float) $account['total_due'];
        $paid = (float) $account['total_paid'];

        $balances[$key] = [
            'customer_id' => (int) $account['customer_id'],
            'customer_name' => $account['customer_name'],
            'gallon_price' => (float) $account['gallon_price'],
            'total_due' => $due,
            'total_paid' => $paid,
            'balance' => $due - $paid,
        ];

        $customers[] = [
            'customer_id' => (int) $account['customer_id'],
            'customer_name' => $account['customer_name'],
            'gallon_price' => (float) $account['gallon_price'],
        ];
    }

    return [$customers, $balances];
}

[$customers, $customerBalances] = loadCustomerAccounts($pdo);

if ($dailyId !== null) {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM daily_sales
         WHERE daily_id = ?
         LIMIT 1"
    );
    $stmt->execute([$dailyId]);
    $dailySales = $stmt->fetch();

    /*
     * Shop rate is always fixed at ₱30.
     *
     * Do not use old walk-in quantity/sales values
     * as the initial balance input.
     */
    $walkInPrice = 30.00;
    $walkInCustomers = 0;
    $walkInSales = 0.00;
    $walkInOtherSales = 0.00;
}

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
        $deliveryPrices = $_POST['delivery_price_per_gallon'] ?? [];
        $deliveryMethods = $_POST['delivery_method'] ?? [];

        $postedArrays = [
            'expenseCategories' => &$expenseCategories,
            'expenseNames' => &$expenseNames,
            'expenseAmounts' => &$expenseAmounts,
            'deliveryCustomerNames' => &$deliveryCustomerNames,
            'deliverySlimQuantities' => &$deliverySlimQuantities,
            'deliveryRoundQuantities' => &$deliveryRoundQuantities,
            'deliveryPayments' => &$deliveryPayments,
            'deliveryPrices' => &$deliveryPrices,
            'deliveryMethods' => &$deliveryMethods,
        ];

        foreach ($postedArrays as &$array) {
            if (!is_array($array)) {
                $array = [$array];
            }
        }

        unset($array);

        

        $allowedCategories = [
            'Food' => 'Food',
            'Gas' => 'Gas',
            'Cash Advance' => 'Cash Advance',
            'Others' => 'Miscellaneous',
        ];

        $expensesToSave = [];

        $expenseRowCount = max(
            count($expenseCategories),
            count($expenseNames),
            count($expenseAmounts)
        );

        for ($i = 0; $i < $expenseRowCount; $i++) {
    $category = trim(
        (string) ($expenseCategories[$i] ?? '')
    );

    $name = trim(
        (string) ($expenseNames[$i] ?? '')
    );

    $amount = trim(
        (string) ($expenseAmounts[$i] ?? '')
    );

    /*
     * Completely empty row.
     * Ignore it.
     */
    if (
        $category === ''
        && $name === ''
        && $amount === ''
    ) {
        continue;
    }

    /*
     * If the user started an expense row,
     * an expense category is required.
     */
    if (!isset($allowedCategories[$category])) {
        throw new Exception(
            'Please select an expense type for the expense row you started.'
        );
    }

    /*
     * Amount is required for every actual expense.
     */
    if (
        $amount === ''
        || !is_numeric($amount)
        || (float) $amount < 0
    ) {
        throw new Exception(
            'Please enter an amount for the expense you started.'
        );
    }

    /*
     * Cash Advance and Others require
     * a name/description.
     */
    if (
        (
            $category === 'Cash Advance'
            || $category === 'Others'
        )
        && $name === ''
    ) {
        throw new Exception(
            'Please enter a name or description for Cash Advance or Others.'
        );
    }

    $expensesToSave[] = [
        'category' => $allowedCategories[$category],
        'description' => in_array(
            $category,
            ['Cash Advance', 'Others'],
            true
        )
            ? $name
            : $category,
        'amount' => (float) $amount,
    ];
}

        $deliveryPaymentsToSave = [];

        $deliveryRowCount = max(
            count($deliveryCustomerNames),
            count($deliverySlimQuantities),
            count($deliveryRoundQuantities),
            count($deliveryPayments),
            count($deliveryPrices),
            count($deliveryMethods)
        );

        $allowedPaymentMethods = [
            'Cash',
            'GCash',
            'Bank Transfer',
            'Other',
        ];

        for ($i = 0; $i < $deliveryRowCount; $i++) {
            $customerName = trim(
                (string) ($deliveryCustomerNames[$i] ?? '')
            );

            $slimRaw = trim(
                (string) ($deliverySlimQuantities[$i] ?? '')
            );

            $roundRaw = trim(
                (string) ($deliveryRoundQuantities[$i] ?? '')
            );

            $paymentRaw = trim(
                (string) ($deliveryPayments[$i] ?? '')
            );

            $priceRaw = trim(
                (string) ($deliveryPrices[$i] ?? '')
            );

            $method = trim(
                (string) ($deliveryMethods[$i] ?? 'Cash')
            );

            if (
                $customerName === ''
                && $slimRaw === ''
                && $roundRaw === ''
                && $paymentRaw === ''
                && $priceRaw === ''
            ) {
                continue;
            }

            if ($customerName === '') {
                throw new Exception(
                    'Please enter or select a customer for every shop delivery payment row you started.'
                );
            }

            if (
                $slimRaw === ''
                && $roundRaw === ''
            ) {
                throw new Exception(
                    'Please enter Slim or Round quantities for every shop delivery payment row you started.'
                );
            }

            if (
                $slimRaw !== ''
                && (
                    !ctype_digit($slimRaw)
                    || (int) $slimRaw < 0
                )
            ) {
                throw new Exception(
                    'Slim quantity must be a whole number.'
                );
            }

            if (
                $roundRaw !== ''
                && (
                    !ctype_digit($roundRaw)
                    || (int) $roundRaw < 0
                )
            ) {
                throw new Exception(
                    'Round quantity must be a whole number.'
                );
            }

            $slimQuantity = $slimRaw === ''
                ? 0
                : (int) $slimRaw;

            $roundQuantity = $roundRaw === ''
                ? 0
                : (int) $roundRaw;

            $gallons =
                $slimQuantity
                + $roundQuantity;

            if ($gallons <= 0) {
                throw new Exception(
                    'Total gallons must be greater than zero for every shop delivery payment row.'
                );
            }

            $stmt = $pdo->prepare(
                "SELECT
                    customer_id,
                    gallon_price
                 FROM customers
                 WHERE LOWER(TRIM(customer_name))
                     = LOWER(TRIM(?))
                 LIMIT 1"
            );

            $stmt->execute([$customerName]);
            $customer = $stmt->fetch();

            if ($customer) {
                $customerId = (int) $customer['customer_id'];
                $pricePerGallon = (float) $customer['gallon_price'];

                if ($pricePerGallon <= 0) {
                    throw new Exception(
                        'This customer does not have a valid Price/Gal saved. Please update the customer price first.'
                    );
                }
            } else {
                if (
                    $priceRaw === ''
                    || !is_numeric($priceRaw)
                    || (float) $priceRaw <= 0
                ) {
                    throw new Exception(
                        'Please enter the Price/Gal for every new customer.'
                    );
                }

                $pricePerGallon = (float) $priceRaw;

                $stmt = $pdo->prepare(
                    "INSERT INTO customers
                        (customer_name, gallon_price)
                     VALUES
                        (?, ?)"
                );

                $stmt->execute([
                    $customerName,
                    $pricePerGallon,
                ]);

                $customerId = (int) $pdo->lastInsertId();
            }

            if ($paymentRaw === '') {
                $paymentAmount = 0.00;
            } elseif (
                !is_numeric($paymentRaw)
                || (float) $paymentRaw < 0
            ) {
                throw new Exception(
                    'Payment must be a valid amount.'
                );
            } else {
                $paymentAmount = (float) $paymentRaw;
            }

            if (!in_array(
                $method,
                $allowedPaymentMethods,
                true
            )) {
                throw new Exception(
                    'Please select a valid payment method.'
                );
            }

            $amountDue =
                $gallons * $pricePerGallon;

            $deliveryPaymentsToSave[] = [
                'customer_name' => $customerName,
                'customer_id' => $customerId,
                'slim_quantity' => $slimQuantity,
                'round_quantity' => $roundQuantity,
                'price_per_gallon' => $pricePerGallon,
                'amount_due' => $amountDue,
                'payment' => $paymentAmount,
                'method' => $method,
            ];
        }
                /*
         * =====================================================
         * SHOP-ONLY BALANCE
         *
         * Total Money Received
         * + Station Expenses
         * - Shop Delivery Payments
         * = Total Sales for Walk-in
         *
         * Total Sales for Walk-in
         * / ₱30
         * = Total Quantity of Walk-in
         * =====================================================
         */

        if (
            $inputMoney === ''
            || !is_numeric($inputMoney)
            || (float) $inputMoney < 0
        ) {
            throw new Exception(
                'Total Money Received must be a valid amount.'
            );
        }

        $moneyValue = (float) $inputMoney;

        $totalExpenses = 0.00;

        foreach ($expensesToSave as $expense) {
            $totalExpenses += $expense['amount'];
        }

        $totalDeliveryPayments = 0.00;

        foreach ($deliveryPaymentsToSave as $deliveryPayment) {
            $totalDeliveryPayments += $deliveryPayment['payment'];
        }

        $walkInSalesValue =
            $moneyValue
            + $totalExpenses
            - $totalDeliveryPayments;

        /*
         * The shop balance cannot be negative.
         */
        if ($walkInSalesValue < -0.005) {
            throw new Exception(
                'Shop-only balance cannot be negative.'
            );
        }

        /*
         * Avoid floating-point residue.
         */
        if (abs($walkInSalesValue) < 0.005) {
            $walkInSalesValue = 0.00;
        }

        /*
 * =====================================================
 * WALK-IN QUANTITY + OTHER SALES
 *
 * The shop rate is ₱30 per gallon.
 *
 * The quantity must always be a whole number.
 * Any remaining amount below ₱30 is treated as
 * Other / Additional Sales.
 *
 * Example:
 *
 * ₱5,510 balance
 * ÷ ₱30
 * = 183.666...
 *
 * Walk-in Quantity = 183
 * Walk-in Sales = ₱5,490
 * Other Sales = ₱20
 * =====================================================
 */

/*
 * Get the whole-number walk-in quantity.
 */
$customersValue =
    (int) floor(
        $walkInSalesValue / $walkInPrice
    );

/*
 * Calculate the actual walk-in sales.
 */
$walkInSalesValue =
    $customersValue * $walkInPrice;

/*
 * Everything remaining below ₱30 is
 * Other / Additional Sales.
 */
$otherSalesValue =
    $walkInSalesValue >= 0
        ? (
            (
                $moneyValue
                + $totalExpenses
                - $totalDeliveryPayments
            )
            - $walkInSalesValue
        )
        : 0.00;

/*
 * Avoid floating-point residue.
 */
if (abs($otherSalesValue) < 0.005) {
    $otherSalesValue = 0.00;
}

/*
 * Round currency values to two decimals.
 */
$otherSalesValue =
    round($otherSalesValue, 2);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT daily_sales_id
             FROM daily_sales
             WHERE daily_id = ?
             LIMIT 1"
        );

        $stmt->execute([$dailyId]);
        $existing = $stmt->fetch();

        if ($existing) {
    $stmt = $pdo->prepare(
        "UPDATE daily_sales
         SET
            walk_in_customers = ?,
            walk_in_price = ?,
            other_sales = ?
         WHERE daily_sales_id = ?"
    );

    $stmt->execute([
        $customersValue,
        $walkInPrice,
        $otherSalesValue,
        $existing['daily_sales_id'],
    ]);
}

         else {
            $stmt = $pdo->prepare(
                "INSERT INTO daily_sales
                    (
                        sales_date,
                        walk_in_customers,
                        walk_in_price,
                        other_sales,
                        daily_id
                    )
                 VALUES
                    (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $businessDate,
                $customersValue,
                $walkInPrice,
                $otherSalesValue,
                $dailyId,
            ]);
        
        }

        if (!empty($expensesToSave)) {
            $stmt = $pdo->prepare(
                "INSERT INTO expenses
                    (
                        expense_date,
                        category,
                        description,
                        amount,
                        daily_id,
                        expense_location
                    )
                 VALUES
                    (?, ?, ?, ?, ?, 'Station')"
            );

            foreach ($expensesToSave as $expense) {
                $stmt->execute([
                    $businessDate,
                    $expense['category'],
                    $expense['description'],
                    $expense['amount'],
                    $dailyId,
                ]);
            }
        }

        foreach ($deliveryPaymentsToSave as $deliveryPayment) {
            $stmt = $pdo->prepare(
                "INSERT INTO deliveries
                    (
                        customer_id,
                        delivery_date,
                        slim_quantity,
                        round_quantity,
                        price_per_gallon,
                        amount_due,
                        daily_id
                    )
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $deliveryPayment['customer_id'],
                $businessDate,
                $deliveryPayment['slim_quantity'],
                $deliveryPayment['round_quantity'],
                $deliveryPayment['price_per_gallon'],
                $deliveryPayment['amount_due'],
                $dailyId,
            ]);

            $deliveryId = (int) $pdo->lastInsertId();

            if ($deliveryPayment['payment'] > 0) {
                $stmt = $pdo->prepare(
                    "INSERT INTO payments
                        (
                            delivery_id,
                            customer_id,
                            payment_date,
                            amount,
                            payment_method,
                            daily_id,
                            collection_location
                        )
                     VALUES
                        (?, ?, ?, ?, ?, ?, 'Station')"
                );

                $stmt->execute([
                    $deliveryId,
                    $deliveryPayment['customer_id'],
                    $businessDate,
                    $deliveryPayment['payment'],
                    $deliveryPayment['method'],
                    $dailyId,
                ]);
            }
        }

        $pdo->commit();

        $walkInCustomers = $customersValue;

        $walkInSales =
            $customersValue * $walkInPrice;

        $walkInOtherSales =
            $otherSalesValue;

        $savedParts = [
            'Shop / Walk-in sales',
        ];

        if (count($expensesToSave) > 0) {
            $savedParts[] =
                count($expensesToSave)
                . ' station expense'
                . (
                    count($expensesToSave) === 1
                        ? ''
                        : 's'
                );
        }

        if (count($deliveryPaymentsToSave) > 0) {
            $savedParts[] =
                count($deliveryPaymentsToSave)
                . ' shop delivery payment'
                . (
                    count($deliveryPaymentsToSave) === 1
                        ? ''
                        : 's'
                );
        }

        $message =
            implode(' and ', $savedParts)
            . ' saved successfully.';

        $messageType = 'success';

        [$customers, $customerBalances] =
            loadCustomerAccounts($pdo);
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
    /* =========================================================
       SHOP / WALK-IN
       ========================================================= */

    .shop-walkin-layout {
        display: grid;
        grid-template-columns:
            minmax(110px, 0.65fr)
            minmax(190px, 1fr)
            minmax(180px, 1fr);
        gap: 18px;
        align-items: end;
    }

    .section-description {
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.5;
    font-weight: 400;
    }

    .shop-customers-field {
        max-width: 180px;
    }

    .shop-money-field {
        max-width: 230px;
    }

    .shop-computed-sales {
        padding: 8px 0 4px 8px;
    }

    .shop-other-sales {
    padding: 8px 0 4px 8px;
    }

    .shop-other-sales .summary-value {
    font-size: 18px;
    font-weight: 700;
    }


    /* =========================================================
       EXPENSES / DELIVERY PANELS
       ========================================================= */

    .expenses-panel,
    .delivery-payments-panel {
        margin-top: 28px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }

    .expenses-panel-header,
    .delivery-payments-panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 16px;
    }

    .expenses-title,
    .delivery-payments-title {
        color: var(--text);
        font-size: 16px;
        font-weight: 700;
        line-height: 1.4;
    }

    .expenses-subtitle,
    .delivery-payments-subtitle {
        margin-top: 4px;
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .expense-rows,
    .delivery-payment-rows {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }


    /* =========================================================
       EXPENSE ROW
       ========================================================= */

    .expense-row {
        display: grid;
        grid-template-columns:
            minmax(180px, 1fr)
            minmax(160px, 0.8fr)
            minmax(220px, 1.2fr)
            38px;
        gap: 14px;
        align-items: end;

        padding: 16px;

        background: var(--background);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
    }


    /* =========================================================
       DELIVERY PAYMENT ROW
       ========================================================= */

    .delivery-payment-row {
        display: grid;
        grid-template-columns:
            minmax(200px, 1.6fr)
            minmax(70px, 0.55fr)
            minmax(70px, 0.55fr)
            minmax(120px, 1fr)
            minmax(90px, 0.7fr)
            minmax(120px, 0.9fr)
            minmax(145px, 1fr)
            38px;
        gap: 12px;
        align-items: end;

        padding: 16px;

        background: var(--background);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
    }


    /* =========================================================
       FORM GROUPS
       ========================================================= */

    .expense-row .form-group,
    .delivery-payment-row .form-group {
        min-width: 0;
        margin: 0;
    }


    /* =========================================================
       UNIFIED FORM CONTROL HEIGHT
       ========================================================= */

    .expense-row .form-input,
    .delivery-payment-row .form-input,
    .delivery-balance {
        width: 100%;
        height: 38px;
        min-height: 38px;
        box-sizing: border-box;
    }


    /* =========================================================
       DROPDOWNS
       
       Explicitly reset vertical padding so the native select
       text is not pushed below the visible area.
       ========================================================= */

    .expense-row select.form-input,
    .delivery-payment-row select.form-input {
        width: 100%;
        height: 38px;
        min-height: 38px;

        /*
         * Important:
         * Remove vertical padding from the global .form-input
         * so the option text stays completely visible.
         */
        padding-top: 0;
        padding-bottom: 0;

        /*
         * Keep horizontal spacing.
         * Extra right padding gives the native dropdown arrow
         * enough room.
         */
        padding-left: 12px;
        padding-right: 32px;

        line-height: normal;
        box-sizing: border-box;

        vertical-align: middle;
    }

    .expense-category,
    .delivery-method {
        height: 38px;
        min-height: 38px;
        line-height: normal;
    }


    /* =========================================================
       REMOVE BUTTONS
       ========================================================= */

    .expense-remove,
    .delivery-payment-remove {
        width: 38px;
        height: 38px;
        min-width: 38px;
        min-height: 38px;

        margin: 0;
        padding: 0;

        display: flex;
        align-items: center;
        justify-content: center;

        align-self: end;
        justify-self: center;

        box-sizing: border-box;

        border: 1px solid var(--border);
        border-radius: var(--radius-sm);

        background: var(--surface);
        color: var(--danger);

        font-size: 19px;
        font-weight: 600;
        line-height: 1;

        cursor: pointer;

        transition:
            background 0.15s ease,
            border-color 0.15s ease;
    }

    .expense-remove:hover,
    .delivery-payment-remove:hover {
        background: var(--danger-light);
        border-color: var(--danger);
    }


    /* =========================================================
       BUTTONS
       ========================================================= */

    .add-expense-button,
    .add-delivery-payment-button {
        margin-top: 0;
    }

    .shop-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 22px;
    }


    /* =========================================================
       DELIVERY STATUS LEGEND
       ========================================================= */

    .delivery-status-legend {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        flex-wrap: wrap;
        gap: 12px;

        margin-top: 12px;

        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.5;
    }

    .delivery-status-legend .legend-label {
        margin-right: 2px;
        color: var(--text);
        font-weight: 700;
    }

    .delivery-status-item {
        white-space: nowrap;
        font-size: 14px;
        font-weight: 600;
    }

    .delivery-status-paid {
        color: var(--success);
    }

    .delivery-status-due {
        color: var(--warning);
    }

    .delivery-status-unpaid {
        color: var(--danger);
    }

    .delivery-status-overpaid {
        color: var(--primary);
    }


    /* =========================================================
       DELIVERY BALANCE
       ========================================================= */

    .delivery-balance-group {
        min-width: 0;
    }

    .delivery-balance {
        width: 100%;
        height: 38px;
        min-height: 38px;

        padding: 0 10px;

        display: flex;
        align-items: center;

        box-sizing: border-box;

        border: 1px solid var(--border);
        border-radius: var(--radius-sm);

        background: var(--surface);

        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;

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


    /* =========================================================
       PRICE NOTE
       ========================================================= */

    .delivery-price-note {
        display: block;
        margin-top: 4px;

        color: var(--text-muted);
        font-size: 11px;
        line-height: 1.3;
    }


    /* =========================================================
       MEDIUM SCREENS
       ========================================================= */

    @media (max-width: 1200px) {
        .delivery-payment-row {
            grid-template-columns:
                minmax(180px, 1.4fr)
                minmax(65px, 0.55fr)
                minmax(65px, 0.55fr)
                minmax(115px, 1fr)
                minmax(85px, 0.7fr)
                minmax(115px, 0.9fr)
                minmax(135px, 1fr)
                38px;

            gap: 10px;
            align-items: end;
        }
    }


    /* =========================================================
       TABLET
       ========================================================= */

    @media (max-width: 900px) {
        .shop-walkin-layout {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .shop-customers-field,
        .shop-money-field {
            max-width: none;
        }

        .shop-computed-sales {
            padding-left: 0;
        }

        .shop-other-sales {
        padding-left: 0;
        }


        /* Expense rows */

        .expense-row {
            grid-template-columns:
                1fr
                1fr;
        }

        .expense-row .expense-name-group {
            grid-column: 1 / -1;
        }

        .expense-remove {
            grid-column: 2;
            justify-self: end;
            align-self: end;
        }


        /* Delivery rows */

        .delivery-payment-row {
            grid-template-columns:
                1fr
                1fr
                1fr;

            gap: 12px;
        }

        .delivery-payment-row .delivery-customer-group {
            grid-column: 1 / -1;
        }

        .delivery-payment-row .delivery-payment-group,
        .delivery-payment-row .delivery-price-group {
            grid-column: span 1;
        }

        .delivery-payment-row .delivery-balance-group {
            grid-column: span 2;
        }

        .delivery-payment-remove {
            grid-column: 3;
            justify-self: end;
            align-self: end;
        }

        .delivery-status-legend {
            justify-content: flex-start;
        }
    }


    /* =========================================================
       MOBILE
       ========================================================= */

    @media (max-width: 650px) {
        .shop-walkin-layout {
            grid-template-columns: 1fr;
        }

        .expense-row,
        .delivery-payment-row {
            grid-template-columns: 1fr;
        }


        /* Expense */

        .expense-row .expense-name-group {
            grid-column: auto;
        }


        /* Delivery */

        .delivery-payment-row .delivery-customer-group,
        .delivery-payment-row .delivery-payment-group,
        .delivery-payment-row .delivery-price-group,
        .delivery-payment-row .delivery-balance-group {
            grid-column: auto;
        }


        /* Remove buttons */

        .expense-remove,
        .delivery-payment-remove {
            grid-column: auto;
            justify-self: start;
            align-self: start;
            margin: 0;
        }


        /* Headers */

        .expenses-panel-header,
        .delivery-payments-panel-header {
            flex-direction: column;
            align-items: stretch;
        }


        /* Actions */

        .shop-actions {
            justify-content: stretch;
        }

        .shop-actions .btn {
            flex: 1;
        }


        /* Status legend */

        .delivery-status-legend {
            gap: 8px 12px;
            font-size: 13px;
        }

        .delivery-status-item {
            font-size: 13px;
        }
    }

    .shop-draft-status {
    margin-top: 10px;
    color: var(--text-muted);
    font-size: 12px;
    text-align: right;
}
</style>
</head>
<body>
    <div class="app">
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
        <main class="main">
            <header class="topbar">
                <div class="topbar-title">
                    Daily Closing
                </div>
                <div class="topbar-user">
                    👤
                    <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>
                </div>
            </header>
            <section class="page">
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
                            <div class="summary-label">
                                Shop Sales
                            </div>
                            <div
                                class="summary-value"
                                id="dashboardShopSales"
                            >
                                ₱<?= number_format(
                                    $walkInSales,
                                    2
                                ) ?>
                            </div>
                            <div class="summary-description">
                                Current shop / walk-in sales
                            </div>
                        </div>
                        <div class="card summary-card">
                            <div class="summary-label">
                                Shop Customers
                            </div>
                            <div
                                class="summary-value"
                                id="dashboardShopCustomers"
                            >
                                <?= number_format(
                                    $walkInCustomers
                                ) ?>
                            </div>
                            <div class="summary-description">
                                Walk-in customers served
                            </div>
                        </div>
                        <div class="card summary-card">
                            <div class="summary-label">
                                Price per Customer
                            </div>
                            <div class="summary-value">
                                ₱<?= number_format(
                                    $walkInPrice,
                                    2
                                ) ?>
                            </div>
                            <div class="summary-description">
                                Current shop rate
                            </div>
                        </div>
                        <div class="card summary-card">
                            <div class="summary-label">
                                Daily Status
                            </div>
                            <div class="summary-value">
                                <span class="badge badge-warning">
                                    Open
                                </span>
                            </div>
                            <div class="summary-description">
                                <?= htmlspecialchars(
                                    $businessDate
                                ) ?>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">
                                    Shop / Walk-in
                                </div>
                                <div class="section-description">  
                                    Record regular customers, money received,
                                    station expenses, and delivery payments
                                    received at the shop.
                                </div>
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
                            <form
                                method="POST"
                                id="shopWalkInForm"
                            >
                                <input
                                    type="hidden"
                                    name="action"
                                    value="save_shop_walkin"
                                >
                                <div class="shop-walkin-layout">

    <div class="shop-money-field">
        <label
            for="walk_in_money"
            class="form-label"
        >
            Total Money Received (Shop only)
        </label>

        <input
            type="number"
            id="walk_in_money"
            name="walk_in_money"
            class="form-input"
            min="0"
            step="0.01"
            value=""
            placeholder="Enter amount"
        >
    </div>

    <div class="shop-customers-field">
        <label
            for="walk_in_customers"
            class="form-label"
        >
            Total Quantity of Walk-in
        </label>

        <input
            type="number"
            id="walk_in_customers"
            name="walk_in_customers"
            class="form-input"
            min="0"
            step="1"
            value="0"
            placeholder="Calculated"
            readonly
        >
    </div>

    <div class="shop-computed-sales">
    <div class="summary-label">
        Total Sales for Walk-in
    </div>

    <div
        class="summary-value"
        id="shopComputedSales"
    >
        ₱<?= number_format(
            $walkInSales,
            2
        ) ?>
    </div>
</div>

<div class="shop-other-sales">
    <div class="summary-label">
        Other / Additional Sales
    </div>

    <div
        class="summary-value"
        id="shopOtherSales"
    >
        ₱<?= number_format(
            $walkInOtherSales,
            2
        ) ?>
    </div>
</div>

</div>
                                <div class="expenses-panel">
                                    <div class="expenses-panel-header">
                                        <div>
                                            <div class="expenses-title">
                                                Station Expenses
                                            </div>
                                            <div class="expenses-subtitle">
                                                Add one or more expenses for
                                                today's station operation.
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
                                    <div
                                        id="expenseRows"
                                        class="expense-rows"
                                    >
                                        <div class="expense-row">
                                            <div class="form-group">
                                                <label class="form-label">
                                                    Expense
                                                </label>
                                                <select
                                                    name="expense_category[]"
                                                    class="form-input expense-category"
                                                >
                                                    <option value="">
                                                        No Expense
                                                    </option>
                                                    <option value="Food">
                                                        Food
                                                    </option>
                                                    <option value="Gas">
                                                        Gas
                                                    </option>
                                                    <option value="Cash Advance">
                                                        Cash Advance
                                                    </option>
                                                    <option value="Others">
                                                        Others
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">
                                                    Amount
                                                </label>
                                                <input
                                                    type="number"
                                                    name="expense_amount[]"
                                                    class="form-input expense-amount"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Enter amount"
                                                >
                                            </div>
                                            <div class="form-group expense-name-group">
                                                <label class="form-label expense-name-label">
                                                    Name / Description
                                                </label>
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
                                            <div class="delivery-payments-title">
                                                Shop Delivery Payments
                                            </div>
                                            <div class="delivery-payments-subtitle">
                                                Price/Gal is entered once for a
                                                new customer and saved for
                                                future transactions.
                                            </div>
                                            <div
                                                class="delivery-status-legend"
                                                aria-label="Delivery payment status legend"
                                            >
                                                <span class="legend-label">
                                                    Status:
                                                </span>
                                                <span class="delivery-status-item delivery-status-paid">
                                                    ● Paid
                                                </span>
                                                <span class="delivery-status-item delivery-status-due">
                                                    ● Due
                                                </span>
                                                <span class="delivery-status-item delivery-status-unpaid">
                                                    ● Unpaid
                                                </span>
                                                <span class="delivery-status-item delivery-status-overpaid">
                                                    ● Overpaid
                                                </span>
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
                                    <div
                                        id="deliveryPaymentRows"
                                        class="delivery-payment-rows"
                                    >
                                        <div class="delivery-payment-row">
                                            <div class="form-group delivery-customer-group">
                                                <label class="form-label">
                                                    Customer
                                                </label>
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
                                                <label class="form-label">
                                                    Slim
                                                </label>
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
                                                <label class="form-label">
                                                    Round
                                                </label>
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
                                                <label class="form-label">
                                                    Payment
                                                </label>
                                                <input
                                                    type="number"
                                                    name="delivery_payment[]"
                                                    class="form-input delivery-payment"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Optional"
                                                >
                                            </div>
                                            <div class="form-group delivery-price-group">
                                                <label class="form-label">
                                                    Price/Gal
                                                </label>
                                                <input
                                                    type="number"
                                                    name="delivery_price_per_gallon[]"
                                                    class="form-input delivery-price-input"
                                                    min="0"
                                                    step="0.01"
                                                    placeholder="Required for new customer"
                                                >
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">
                                                    Method
                                                </label>
                                                <select
                                                    name="delivery_method[]"
                                                    class="form-input delivery-method"
                                                >
                                                    <option
                                                        value="Cash"
                                                        selected
                                                    >
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
                                            </div>
                                            <div class="form-group delivery-balance-group">
                                                <label class="form-label">
                                                    Balance
                                                </label>
                                                <div
                                                    class="delivery-balance delivery-balance-neutral"
                                                    aria-live="polite"
                                                >
                                                    —
                                                </div>
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
                                        <option
                                            value="<?= htmlspecialchars(
                                                $customer['customer_name']
                                            ) ?>"
                                        ></option>
                                    <?php endforeach; ?>
                                </datalist>
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
    const form =
        document.getElementById('shopWalkInForm');

    if (!form) {
        return;
    }

    const customersInput =
        document.getElementById('walk_in_customers');

    const moneyInput =
        document.getElementById('walk_in_money');

    const computedSales =
        document.getElementById('shopComputedSales');
    
    const otherSales =
    document.getElementById('shopOtherSales');

    const dashboardSales =
        document.getElementById('dashboardShopSales');

    const dashboardCustomers =
        document.getElementById('dashboardShopCustomers');

    const expenseRows =
        document.getElementById('expenseRows');

    const addExpenseButton =
        document.getElementById('addExpenseButton');

    const deliveryPaymentRows =
        document.getElementById('deliveryPaymentRows');

    const addDeliveryPaymentButton =
        document.getElementById('addDeliveryPaymentButton');

    const computeButton =
        document.getElementById('shopComputeButton');

    const pricePerCustomer = 30;

    const customerAccounts =
        <?= json_encode(
            $customerBalances,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?>;


    /* =========================================================
       HELPERS
       ========================================================= */

    function formatCurrency(value) {
        return '₱'
            + Number(value || 0).toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }
            );
    }


    /* =========================================================
       EXPENSE
       ========================================================= */

    function updateExpenseRow(row) {
        if (!row) {
            return;
        }

        const category =
            row.querySelector('.expense-category');

        const name =
            row.querySelector('.expense-name');

        const label =
            row.querySelector('.expense-name-label');

        if (!category || !name || !label) {
            return;
        }

        const needsName =
            category.value === 'Cash Advance'
            || category.value === 'Others';

        name.disabled = !needsName;
        name.required = needsName;

        label.textContent = needsName
            ? 'Name / Description *'
            : 'Name / Description';

        if (needsName) {
            if (category.value === 'Cash Advance') {
                name.placeholder =
                    'Enter recipient name';
            } else {
                name.placeholder =
                    'Enter expense description';
            }
        } else {
            name.value = '';
            name.placeholder =
                'Not required for Food / Gas';
        }
    }


    function createExpenseRow() {
        const row =
            document.createElement('div');

        row.className =
            'expense-row';

        row.innerHTML = `
            <div class="form-group">
                <label class="form-label">
                    Expense
                </label>

                <select
                    name="expense_category[]"
                    class="form-input expense-category"
                >
                    <option value="">
                        No Expense
                    </option>

                    <option value="Food">
                        Food
                    </option>

                    <option value="Gas">
                        Gas
                    </option>

                    <option value="Cash Advance">
                        Cash Advance
                    </option>

                    <option value="Others">
                        Others
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Amount
                </label>

                <input
                    type="number"
                    name="expense_amount[]"
                    class="form-input expense-amount"
                    min="0"
                    step="0.01"
                    placeholder="Enter amount"
                >
            </div>

            <div class="form-group expense-name-group">
                <label class="form-label expense-name-label">
                    Name / Description
                </label>

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
            >
                ×
            </button>
        `;

        expenseRows.appendChild(row);

        updateExpenseRow(row);

        if (!restoringDraft) {
        const category =
        row.querySelector('.expense-category');

        if (category) {
        category.focus();
            }
        }
    }


    /* =========================================================
       CUSTOMER PRICE
       ========================================================= */

    function setPriceFromCustomer(row) {
        if (!row) {
            return;
        }

        const customerInput =
            row.querySelector('.delivery-customer');

        const priceInput =
            row.querySelector('.delivery-price-input');

        const priceNote =
            row.querySelector('.delivery-price-note');

        if (!customerInput || !priceInput) {
            return;
        }

        const name =
            customerInput.value
                .trim()
                .toLowerCase();

        const account =
            customerAccounts[name];

        if (
            account
            && Number(account.gallon_price) > 0
        ) {
            priceInput.value =
                Number(account.gallon_price)
                    .toFixed(2)
                    .replace(/\.00$/, '');

            priceInput.readOnly = true;
            priceInput.dataset.saved = '1';

            if (priceNote) {
                priceNote.textContent =
                    'Saved customer price';
            }

            return;
        }

        /*
         * New customer.
         * Allow the user to enter the price manually.
         */
        if (priceInput.dataset.saved === '1') {
            priceInput.value = '';
        }

        priceInput.readOnly = false;
        priceInput.dataset.saved = '0';

        if (priceNote) {
            priceNote.textContent =
                'Enter price for new customer';
        }
    }


    /* =========================================================
       DELIVERY STATUS
       ========================================================= */

    function updateDeliveryStatus(row) {
        if (!row) {
            return;
        }

        const customerInput =
            row.querySelector('.delivery-customer');

        const slimInput =
            row.querySelector('.delivery-slim');

        const roundInput =
            row.querySelector('.delivery-round');

        const paymentInput =
            row.querySelector('.delivery-payment');

        const priceInput =
            row.querySelector('.delivery-price-input');

        const balanceElement =
            row.querySelector('.delivery-balance');

        if (
            !customerInput
            || !balanceElement
        ) {
            return;
        }

        /*
         * Set saved customer price first.
         */
        setPriceFromCustomer(row);

        const slim =
            parseInt(
                slimInput?.value || '0',
                10
            ) || 0;

        const round =
            parseInt(
                roundInput?.value || '0',
                10
            ) || 0;

        const payment =
            parseFloat(
                paymentInput?.value || '0'
            ) || 0;

        const price =
            parseFloat(
                priceInput?.value || '0'
            ) || 0;

        const gallons =
            slim + round;

        const amountDue =
            gallons * price;

        const epsilon = 0.005;

        balanceElement.className =
            'delivery-balance '
            + 'delivery-balance-neutral';

        if (
            gallons <= 0
            || price <= 0
        ) {
            balanceElement.textContent = '—';
            return;
        }

        const difference =
            amountDue - payment;


        /*
         * No payment
         */
        if (payment <= epsilon) {
            balanceElement.className =
                'delivery-balance '
                + 'delivery-balance-unpaid';

            balanceElement.textContent =
                formatCurrency(amountDue)
                + ' Unpaid';

            return;
        }


        /*
         * Exact payment
         */
        if (
            Math.abs(difference)
            <= epsilon
        ) {
            balanceElement.className =
                'delivery-balance '
                + 'delivery-balance-paid';

            balanceElement.textContent =
                '✓ Paid';

            return;
        }


        /*
         * Payment is less than amount due
         */
        if (difference > epsilon) {
            balanceElement.className =
                'delivery-balance '
                + 'delivery-balance-due';

            balanceElement.textContent =
                formatCurrency(difference)
                + ' Due';

            return;
        }


        /*
         * Payment is greater than amount due
         */
        balanceElement.className =
            'delivery-balance '
            + 'delivery-balance-overpaid';

        balanceElement.textContent =
            formatCurrency(
                Math.abs(difference)
            )
            + ' Overpaid';
    }


    /* =========================================================
       DELIVERY ROW
       ========================================================= */

    function createDeliveryPaymentRow() {
        const row =
            document.createElement('div');

        row.className =
            'delivery-payment-row';

        row.innerHTML = `
            <div class="form-group delivery-customer-group">
                <label class="form-label">
                    Customer
                </label>

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
                <label class="form-label">
                    Slim
                </label>

                <input
                    type="number"
                    name="delivery_slim[]"
                    class="form-input delivery-slim"
                    min="0"
                    step="1"
                    placeholder="optional"
                >
            </div>

            <div class="form-group">
                <label class="form-label">
                    Round
                </label>

                <input
                    type="number"
                    name="delivery_round[]"
                    class="form-input delivery-round"
                    min="0"
                    step="1"
                    placeholder="optional"
                >
            </div>

            <div class="form-group delivery-payment-group">
                <label class="form-label">
                    Payment
                </label>

                <input
                    type="number"
                    name="delivery_payment[]"
                    class="form-input delivery-payment"
                    min="0"
                    step="0.01"
                    placeholder="Optional"
                >
            </div>

            <div class="form-group delivery-price-group">
                <label class="form-label">
                    Price/Gal
                </label>

                <input
                    type="number"
                    name="delivery_price_per_gallon[]"
                    class="form-input delivery-price-input"
                    min="0"
                    step="0.01"
                    placeholder="Required for new customer"
                >
            </div>

            <div class="form-group">
                <label class="form-label">
                    Method
                </label>

                <select
                    name="delivery_method[]"
                    class="form-input delivery-method"
                >
                    <option
                        value="Cash"
                        selected
                    >
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
            </div>

            <div class="form-group delivery-balance-group">
                <label class="form-label">
                    Balance
                </label>

                <div
                    class="delivery-balance delivery-balance-neutral"
                    aria-live="polite"
                >
                    —
                </div>
            </div>

            <button
                type="button"
                class="delivery-payment-remove"
                title="Remove payment"
                aria-label="Remove payment"
            >
                ×
            </button>
        `;

        deliveryPaymentRows.appendChild(row);

        updateDeliveryStatus(row);

        if (!restoringDraft) {
        const customerInput =
        row.querySelector('.delivery-customer');

        if (customerInput) {
        customerInput.focus();
            }
        }
    }


    /* =========================================================
       SHOP / WALK-IN COMPUTATION
       ========================================================= */

    function calculateShop() {
    const moneyValue =
        moneyInput.value.trim();

    /*
     * Total Money Received is required.
     */
    if (moneyValue === '') {
        computedSales.textContent =
            formatCurrency(0);

        otherSales.textContent =
            formatCurrency(0);

        dashboardSales.textContent =
            formatCurrency(0);

        dashboardCustomers.textContent =
            '0';

        customersInput.value =
            '0';

        return;
    }


    /*
     * Validate money.
     */
    if (
        !/^\d+(\.\d{1,2})?$/.test(
            moneyValue
        )
    ) {
        computedSales.textContent =
            'Invalid';

        otherSales.textContent =
            'Invalid';

        return;
    }


    const totalMoneyReceived =
        Math.max(
            0,
            parseFloat(moneyValue) || 0
        );


    /*
     * =====================================================
     * TOTAL EXPENSES
     * =====================================================
     */

    let totalExpenses = 0;

    expenseRows
        .querySelectorAll('.expense-amount')
        .forEach(function (input) {
            const amount =
                parseFloat(input.value) || 0;

            totalExpenses += amount;
        });


    /*
     * =====================================================
     * TOTAL SHOP DELIVERY PAYMENTS
     * =====================================================
     */

    let totalDeliveryPayments = 0;

    deliveryPaymentRows
        .querySelectorAll('.delivery-payment')
        .forEach(function (input) {
            const payment =
                parseFloat(input.value) || 0;

            totalDeliveryPayments += payment;
        });


    /*
     * =====================================================
     * TOTAL SHOP BALANCE
     *
     * Money Received
     * + Expenses
     * - Delivery Payments
     *
     * = Total Amount Accounted For
     * =====================================================
     */

    const totalBalance =
        totalMoneyReceived
        + totalExpenses
        - totalDeliveryPayments;


    /*
     * Negative balance is invalid.
     */
    if (totalBalance < -0.005) {
        computedSales.textContent =
            'Invalid';

        otherSales.textContent =
            'Invalid';

        customersInput.value =
            '0';

        dashboardSales.textContent =
            'Invalid';

        dashboardCustomers.textContent =
            '—';

        return;
    }


    /*
     * Remove tiny floating-point residue.
     */
    const balancedSales =
        Math.abs(totalBalance) < 0.005
            ? 0
            : totalBalance;


    /*
     * =====================================================
     * WALK-IN QUANTITY
     *
     * Always use the whole-number portion.
     *
     * Example:
     *
     * ₱5,510 / ₱30
     * = 183.666...
     *
     * Quantity = 183
     * =====================================================
     */

    const customers =
        Math.floor(
            balancedSales / pricePerCustomer
        );


    /*
     * =====================================================
     * WALK-IN SALES
     * =====================================================
     */

    const finalSales =
        customers * pricePerCustomer;


    /*
     * =====================================================
     * OTHER / ADDITIONAL SALES
     *
     * Whatever remains below ₱30.
     *
     * Example:
     *
     * ₱5,510 - ₱5,490
     * = ₱20
     * =====================================================
     */

    const additionalSales =
        Math.max(
            0,
            balancedSales - finalSales
        );


    /*
     * Update quantity.
     */
    customersInput.value =
        customers;


    /*
     * Update Walk-in Sales.
     */
    computedSales.textContent =
        formatCurrency(finalSales);


    /*
     * Update Other / Additional Sales.
     */
    otherSales.textContent =
        formatCurrency(additionalSales);


    /*
     * Dashboard Shop Sales should represent
     * the complete accounted shop balance,
     * including Other / Additional Sales.
     */
    dashboardSales.textContent =
        formatCurrency(balancedSales);


    /*
     * Dashboard customer count remains
     * the actual whole-number gallon quantity.
     */
    dashboardCustomers.textContent =
        customers.toLocaleString('en-PH');
}


    /* =========================================================
       COMPUTE BUTTON
       ========================================================= */

    if (computeButton) {
        computeButton.addEventListener(
            'click',
            calculateShop
        );
    }


    /* =========================================================
       WALK-IN INPUTS
       ========================================================= */


    moneyInput.addEventListener(
    'input',
    function () {
        if (
            moneyInput.value.trim()
            !== ''
        ) {
            customersInput.value = '0';
        }
      }
    );


    /* =========================================================
       ADD EXPENSE
       ========================================================= */

    if (addExpenseButton) {
        addExpenseButton.addEventListener(
            'click',
            createExpenseRow
        );
    }


    /* =========================================================
       EXPENSE EVENTS
       ========================================================= */

    expenseRows.addEventListener(
        'change',
        function (event) {
            if (
                event.target.classList.contains(
                    'expense-category'
                )
            ) {
                updateExpenseRow(
                    event.target.closest(
                        '.expense-row'
                    )
                );
            }
        }
    );


    expenseRows.addEventListener(
        'click',
        function (event) {
            const button =
                event.target.closest(
                    '.expense-remove'
                );

            if (!button) {
                return;
            }

            const rows =
                expenseRows.querySelectorAll(
                    '.expense-row'
                );

            const row =
                button.closest(
                    '.expense-row'
                );

            if (!row) {
                return;
            }


            /*
             * Keep one empty row.
             */
            if (rows.length === 1) {

                const category =
                    row.querySelector(
                        '.expense-category'
                    );

                const amount =
                    row.querySelector(
                        '.expense-amount'
                    );

                const name =
                    row.querySelector(
                        '.expense-name'
                    );

                if (category) {
                    category.value = '';
                }

                if (amount) {
                    amount.value = '';
                }

                if (name) {
                    name.value = '';
                }

                updateExpenseRow(row);

            } else {
                row.remove();
            }
        }
    );


    /* =========================================================
       ADD DELIVERY PAYMENT
       ========================================================= */

    if (addDeliveryPaymentButton) {
        addDeliveryPaymentButton.addEventListener(
            'click',
            createDeliveryPaymentRow
        );
    }


    /* =========================================================
       DELIVERY INPUT EVENTS
       ========================================================= */

    deliveryPaymentRows.addEventListener(
        'input',
        function (event) {

            const row =
                event.target.closest(
                    '.delivery-payment-row'
                );

            if (!row) {
                return;
            }


            /*
             * Customer changed.
             */
            if (
                event.target.matches(
                    '.delivery-customer'
                )
            ) {
                setPriceFromCustomer(row);
            }


            /*
             * Recalculate balance.
             */
            if (
                event.target.matches(
                    '.delivery-customer,'
                    + '.delivery-slim,'
                    + '.delivery-round,'
                    + '.delivery-payment,'
                    + '.delivery-price-input'
                )
            ) {
                updateDeliveryStatus(row);
            }
        }
    );


    deliveryPaymentRows.addEventListener(
        'change',
        function (event) {

            const row =
                event.target.closest(
                    '.delivery-payment-row'
                );

            if (!row) {
                return;
            }

            if (
                event.target.classList.contains(
                    'delivery-customer'
                )
            ) {
                updateDeliveryStatus(row);
            }
        }
    );


    /* =========================================================
       DELIVERY REMOVE BUTTON
       ========================================================= */

    deliveryPaymentRows.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.delivery-payment-remove'
                );

            if (!button) {
                return;
            }

            const rows =
                deliveryPaymentRows.querySelectorAll(
                    '.delivery-payment-row'
                );

            const row =
                button.closest(
                    '.delivery-payment-row'
                );

            if (!row) {
                return;
            }


            /*
             * Keep one empty row.
             */
            if (rows.length === 1) {

                const customer =
                    row.querySelector(
                        '.delivery-customer'
                    );

                const slim =
                    row.querySelector(
                        '.delivery-slim'
                    );

                const round =
                    row.querySelector(
                        '.delivery-round'
                    );

                const payment =
                    row.querySelector(
                        '.delivery-payment'
                    );

                const price =
                    row.querySelector(
                        '.delivery-price-input'
                    );

                const method =
                    row.querySelector(
                        '.delivery-method'
                    );

                if (customer) {
                    customer.value = '';
                }

                if (slim) {
                    slim.value = '';
                }

                if (round) {
                    round.value = '';
                }

                if (payment) {
                    payment.value = '';
                }

                if (price) {
                    price.value = '';
                    price.readOnly = false;
                    price.dataset.saved = '0';
                }

                if (method) {
                    method.value = 'Cash';
                }

                updateDeliveryStatus(row);

            } else {
                row.remove();
            }
        }
    );


    /* =========================================================
       INITIALIZE EXISTING EXPENSE ROWS
       ========================================================= */

    expenseRows
        .querySelectorAll('.expense-row')
        .forEach(function (row) {
            updateExpenseRow(row);
        });


    /* =========================================================
       INITIALIZE EXISTING DELIVERY ROWS
       ========================================================= */

    deliveryPaymentRows
        .querySelectorAll(
            '.delivery-payment-row'
        )
        .forEach(function (row) {
            updateDeliveryStatus(row);
        });

            /* =========================================================
       FORM DRAFT STORAGE
       ========================================================= */

    const draftStorageKey =
        'marcidBlueDailyClosingDraft_<?= htmlspecialchars($businessDate ?? 'none') ?>';

    let restoringDraft = false;


    function saveFormDraft() {
        if (restoringDraft) {
            return;
        }

        const formData =
            new FormData(form);

        const draft = {
            walk_in_money:
                moneyInput.value,

            expenses: [],

            deliveries: []
        };


        /*
         * Save expense rows.
         */
        expenseRows
            .querySelectorAll('.expense-row')
            .forEach(function (row) {

                const category =
                    row.querySelector(
                        '.expense-category'
                    );

                const amount =
                    row.querySelector(
                        '.expense-amount'
                    );

                const name =
                    row.querySelector(
                        '.expense-name'
                    );

                draft.expenses.push({
                    category:
                        category
                            ? category.value
                            : '',

                    amount:
                        amount
                            ? amount.value
                            : '',

                    name:
                        name
                            ? name.value
                            : ''
                });
            });


        /*
         * Save delivery rows.
         */
        deliveryPaymentRows
            .querySelectorAll(
                '.delivery-payment-row'
            )
            .forEach(function (row) {

                const customer =
                    row.querySelector(
                        '.delivery-customer'
                    );

                const slim =
                    row.querySelector(
                        '.delivery-slim'
                    );

                const round =
                    row.querySelector(
                        '.delivery-round'
                    );

                const payment =
                    row.querySelector(
                        '.delivery-payment'
                    );

                const price =
                    row.querySelector(
                        '.delivery-price-input'
                    );

                const method =
                    row.querySelector(
                        '.delivery-method'
                    );

                draft.deliveries.push({
                    customer:
                        customer
                            ? customer.value
                            : '',

                    slim:
                        slim
                            ? slim.value
                            : '',

                    round:
                        round
                            ? round.value
                            : '',

                    payment:
                        payment
                            ? payment.value
                            : '',

                    price:
                        price
                            ? price.value
                            : '',

                    method:
                        method
                            ? method.value
                            : 'Cash'
                });
            });


        localStorage.setItem(
            draftStorageKey,
            JSON.stringify(draft)
        );
    }


    function clearFormDraft() {
        localStorage.removeItem(
            draftStorageKey
        );
    }


    function restoreFormDraft() {
        const saved =
            localStorage.getItem(
                draftStorageKey
            );

        if (!saved) {
            return;
        }

        try {
            const draft =
                JSON.parse(saved);

            restoringDraft = true;


            /*
             * Restore money.
             */
            if (
                typeof draft.walk_in_money
                === 'string'
            ) {
                moneyInput.value =
                    draft.walk_in_money;
            }


            /*
             * Restore expenses.
             */
            if (
                Array.isArray(
                    draft.expenses
                )
            ) {
                expenseRows.innerHTML = '';

                draft.expenses.forEach(
                    function (expense) {

                        createExpenseRow();

                        const rows =
                            expenseRows.querySelectorAll(
                                '.expense-row'
                            );

                        const row =
                            rows[rows.length - 1];

                        const category =
                            row.querySelector(
                                '.expense-category'
                            );

                        const amount =
                            row.querySelector(
                                '.expense-amount'
                            );

                        const name =
                            row.querySelector(
                                '.expense-name'
                            );

                        if (category) {
                            category.value =
                                expense.category || '';
                        }

                        if (amount) {
                            amount.value =
                                expense.amount || '';
                        }

                        if (name) {
                            name.value =
                                expense.name || '';
                        }

                        updateExpenseRow(row);
                    }
                );

                /*
                 * Always keep one row available.
                 */
                if (
                    draft.expenses.length === 0
                ) {
                    createExpenseRow();
                }
            }


            /*
             * Restore deliveries.
             */
            if (
                Array.isArray(
                    draft.deliveries
                )
            ) {
                deliveryPaymentRows.innerHTML = '';

                draft.deliveries.forEach(
                    function (delivery) {

                        createDeliveryPaymentRow();

                        const rows =
                            deliveryPaymentRows.querySelectorAll(
                                '.delivery-payment-row'
                            );

                        const row =
                            rows[rows.length - 1];

                        const customer =
                            row.querySelector(
                                '.delivery-customer'
                            );

                        const slim =
                            row.querySelector(
                                '.delivery-slim'
                            );

                        const round =
                            row.querySelector(
                                '.delivery-round'
                            );

                        const payment =
                            row.querySelector(
                                '.delivery-payment'
                            );

                        const price =
                            row.querySelector(
                                '.delivery-price-input'
                            );

                        const method =
                            row.querySelector(
                                '.delivery-method'
                            );

                        if (customer) {
                            customer.value =
                                delivery.customer || '';
                        }

                        if (slim) {
                            slim.value =
                                delivery.slim || '';
                        }

                        if (round) {
                            round.value =
                                delivery.round || '';
                        }

                        if (payment) {
                            payment.value =
                                delivery.payment || '';
                        }

                        if (price) {
                            price.value =
                                delivery.price || '';
                        }

                        if (method) {
                            method.value =
                                delivery.method || 'Cash';
                        }

                        updateDeliveryStatus(row);
                    }
                );

                /*
                 * Always keep one row available.
                 */
                if (
                    draft.deliveries.length === 0
                ) {
                    createDeliveryPaymentRow();
                }
            }

            restoringDraft = false;


            /*
             * Recalculate the shop balance
             * after restoring everything.
             */
            calculateShop();

        } catch (error) {
            console.error(
                'Unable to restore Daily Closing draft:',
                error
            );

            localStorage.removeItem(
                draftStorageKey
            );

            restoringDraft = false;
        }
    }


    /*
     * Save whenever the user changes
     * anything in the form.
     */
    form.addEventListener(
        'input',
        saveFormDraft
    );

    form.addEventListener(
        'change',
        saveFormDraft
    );


    /*
     * If the PHP save was successful,
     * the database now contains the data.
     *
     * Clear the temporary browser draft.
     */
    const saveWasSuccessful =
        <?= $messageType === 'success'
            ? 'true'
            : 'false' ?>;

    if (saveWasSuccessful) {
        clearFormDraft();
    } else {
        restoreFormDraft();
    }

})();
</script>
</body>
</html>
