<?php

/**
 * Marcid Blue
 * Temporary Daily Actions Tester
 *
 * PURPOSE:
 * - Inspect the current Open Daily Record
 * - Inspect its autosaved draft
 * - Test the Finalize calculations WITHOUT permanently changing data
 * - Test the Reset operation in a controlled way
 *
 * IMPORTANT:
 * Finalize TEST is read-only.
 * It does NOT close the day.
 *
 * Reset TEST is also read-only.
 * It does NOT delete anything.
 */

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

$message = '';
$messageType = '';

$dailyRecord = null;
$draft = null;
$calculation = null;

/*
|--------------------------------------------------------------------------
| Load current open daily record
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT *
     FROM daily_records
     WHERE status = 'Open'
     ORDER BY daily_id DESC
     LIMIT 1"
);

$stmt->execute();

$dailyRecord = $stmt->fetch();

if ($dailyRecord) {

    $dailyId = (int) $dailyRecord['daily_id'];

    /*
     * Load autosaved draft
     */
    $draftTableExists = (bool) $pdo
        ->query("SHOW TABLES LIKE 'daily_closing_drafts'")
        ->fetchColumn();

    if ($draftTableExists) {

        $stmt = $pdo->prepare(
            "SELECT *
             FROM daily_closing_drafts
             WHERE daily_id = ?
             LIMIT 1"
        );

        $stmt->execute([$dailyId]);

        $draftRow = $stmt->fetch();

        if ($draftRow) {

            $draft = json_decode(
                $draftRow['draft_data'],
                true
            );

            if (!is_array($draft)) {
                $draft = null;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function money($value): float
{
    if (!is_numeric($value)) {
        return 0.00;
    }

    return round((float) $value, 2);
}


/*
|--------------------------------------------------------------------------
| TEST FINALIZE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'test_finalize'
) {

    try {

        if (!$dailyRecord) {
            throw new Exception(
                'There is currently no Open Daily Record.'
            );
        }

        if (!$draft) {
            throw new Exception(
                'There is no autosaved draft for the current day.'
            );
        }

        /*
         * ----------------------------------------------------------
         * SHOP
         * ----------------------------------------------------------
         */

        $walkInMoney = money(
            $draft['walk_in_money'] ?? 0
        );

        if ($walkInMoney < 0) {
            throw new Exception(
                'Shop Total Money Received cannot be negative.'
            );
        }

        $shopExpenses =
            is_array($draft['expenses'] ?? null)
                ? $draft['expenses']
                : [];

        $shopDeliveries =
            is_array($draft['deliveries'] ?? null)
                ? $draft['deliveries']
                : [];

        /*
         * ----------------------------------------------------------
         * DRIVER
         * ----------------------------------------------------------
         */

        $driver =
            is_array($draft['driver'] ?? null)
                ? $draft['driver']
                : [];

        $driverMoney = money(
            $driver['money_received'] ?? 0
        );

        if ($driverMoney < 0) {
            throw new Exception(
                'Driver Total Money Received cannot be negative.'
            );
        }

        $driverExpenses =
            is_array($driver['expenses'] ?? null)
                ? $driver['expenses']
                : [];

        $driverDeliveries =
            is_array($driver['deliveries'] ?? null)
                ? $driver['deliveries']
                : [];


        /*
         * ----------------------------------------------------------
         * EXPENSE CALCULATION
         * ----------------------------------------------------------
         */

        $shopExpenseTotal = 0.00;

        foreach ($shopExpenses as $expense) {

            if (!is_array($expense)) {
                continue;
            }

            $amount = money(
                $expense['amount'] ?? 0
            );

            $shopExpenseTotal += $amount;
        }


        $driverExpenseTotal = 0.00;

        foreach ($driverExpenses as $expense) {

            if (!is_array($expense)) {
                continue;
            }

            $amount = money(
                $expense['amount'] ?? 0
            );

            $driverExpenseTotal += $amount;
        }


        /*
         * ----------------------------------------------------------
         * SHOP DELIVERY PAYMENTS
         * ----------------------------------------------------------
         */

        $shopDeliveryPaymentsTotal = 0.00;

        foreach ($shopDeliveries as $delivery) {

            if (!is_array($delivery)) {
                continue;
            }

            $payment = money(
                $delivery['payment'] ?? 0
            );

            $shopDeliveryPaymentsTotal += $payment;
        }


        /*
         * ----------------------------------------------------------
         * DRIVER DELIVERY PAYMENTS
         * ----------------------------------------------------------
         */

        $driverDeliveryPaymentsTotal = 0.00;

        foreach ($driverDeliveries as $delivery) {

            if (!is_array($delivery)) {
                continue;
            }

            $payment = money(
                $delivery['payment'] ?? 0
            );

            $driverDeliveryPaymentsTotal += $payment;
        }


        /*
         * ----------------------------------------------------------
         * SHOP BALANCE
         *
         * Total Money Received
         * + Expenses
         * - Shop Delivery Payments
         * ----------------------------------------------------------
         */

        $shopBalance = money(
            $walkInMoney
            + $shopExpenseTotal
            - $shopDeliveryPaymentsTotal
        );

        if ($shopBalance < -0.005) {
            throw new Exception(
                'Shop balance would become negative.'
            );
        }

        if (abs($shopBalance) < 0.005) {
            $shopBalance = 0.00;
        }


        /*
         * ----------------------------------------------------------
         * WALK-IN
         * ----------------------------------------------------------
         */

        $walkInPrice = 30.00;

        $walkInCustomers = (int) floor(
            $shopBalance / $walkInPrice
        );

        $walkInSales = money(
            $walkInCustomers * $walkInPrice
        );

        $otherSales = money(
            $shopBalance - $walkInSales
        );


        /*
         * ----------------------------------------------------------
         * DRIVER TOTAL SALES
         * ----------------------------------------------------------
         */

        $driverTotalSales = money(
            $driverMoney
            + $driverExpenseTotal
        );


        /*
         * ----------------------------------------------------------
         * DELIVERY TOTALS
         * ----------------------------------------------------------
         */

        $totalExpectedDeliveryMoney = money(
            $shopDeliveryPaymentsTotal
            + $driverDeliveryPaymentsTotal
        );


        /*
         * This mirrors the current Finalize endpoint.
         */
        $effectiveReceived = money(
            $driverMoney
            + $driverExpenseTotal
            + $shopDeliveryPaymentsTotal
        );

        $remittanceDifference = money(
            $effectiveReceived
            - $totalExpectedDeliveryMoney
        );


        /*
         * ----------------------------------------------------------
         * PREPARE RESULT
         * ----------------------------------------------------------
         */

        $calculation = [

            'daily_id' =>
                (int) $dailyRecord['daily_id'],

            'business_date' =>
                $dailyRecord['business_date'],

            'status' =>
                $dailyRecord['status'],

            'walk_in_money' =>
                $walkInMoney,

            'shop_expenses' =>
                $shopExpenseTotal,

            'shop_delivery_payments' =>
                $shopDeliveryPaymentsTotal,

            'shop_balance' =>
                $shopBalance,

            'walk_in_customers' =>
                $walkInCustomers,

            'walk_in_sales' =>
                $walkInSales,

            'other_sales' =>
                $otherSales,

            'driver_money' =>
                $driverMoney,

            'driver_expenses' =>
                $driverExpenseTotal,

            'driver_delivery_payments' =>
                $driverDeliveryPaymentsTotal,

            'driver_total_sales' =>
                $driverTotalSales,

            'expected_delivery_money' =>
                $totalExpectedDeliveryMoney,

            'effective_received' =>
                $effectiveReceived,

            'remittance_difference' =>
                $remittanceDifference,

            'shop_delivery_count' =>
                count($shopDeliveries),

            'driver_delivery_count' =>
                count($driverDeliveries),

            'shop_expense_count' =>
                count($shopExpenses),

            'driver_expense_count' =>
                count($driverExpenses),
        ];

        $message =
            'Finalize calculation test completed successfully. No database records were changed.';

        $messageType = 'success';

    } catch (Throwable $e) {

        $message = $e->getMessage();
        $messageType = 'error';
    }
}


/*
|--------------------------------------------------------------------------
| TEST RESET
|--------------------------------------------------------------------------
|
| This DOES NOT delete anything.
|
| It only reports what the REAL reset endpoint WOULD delete.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'test_reset'
) {

    try {

        if (!$dailyRecord) {
            throw new Exception(
                'There is currently no Open Daily Record.'
            );
        }

        $dailyId =
            (int) $dailyRecord['daily_id'];


        /*
         * Count draft
         */

        $draftCount = 0;

        $draftTableExists = (bool) $pdo
            ->query("SHOW TABLES LIKE 'daily_closing_drafts'")
            ->fetchColumn();

        if ($draftTableExists) {

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM daily_closing_drafts
                 WHERE daily_id = ?"
            );

            $stmt->execute([$dailyId]);

            $draftCount =
                (int) $stmt->fetchColumn();
        }


        /*
         * Count payments
         */

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM payments
             WHERE daily_id = ?"
        );

        $stmt->execute([$dailyId]);

        $paymentCount =
            (int) $stmt->fetchColumn();


        /*
         * Count deliveries
         */

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM deliveries
             WHERE daily_id = ?"
        );

        $stmt->execute([$dailyId]);

        $deliveryCount =
            (int) $stmt->fetchColumn();


        /*
         * Count expenses
         */

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM expenses
             WHERE daily_id = ?"
        );

        $stmt->execute([$dailyId]);

        $expenseCount =
            (int) $stmt->fetchColumn();


        /*
         * Count daily sales
         */

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM daily_sales
             WHERE daily_id = ?"
        );

        $stmt->execute([$dailyId]);

        $dailySalesCount =
            (int) $stmt->fetchColumn();


        $calculation = [

            'daily_id' =>
                $dailyId,

            'status' =>
                $dailyRecord['status'],

            'draft_rows' =>
                $draftCount,

            'payment_rows_to_delete' =>
                $paymentCount,

            'delivery_rows_to_delete' =>
                $deliveryCount,

            'expense_rows_to_delete' =>
                $expenseCount,

            'daily_sales_rows_to_delete' =>
                $dailySalesCount,
        ];

        $message =
            'Reset test completed. Nothing was deleted.';

        $messageType = 'success';

    } catch (Throwable $e) {

        $message = $e->getMessage();
        $messageType = 'error';
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

    <title>Marcid Blue - Daily Actions Test</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px;
            background: #f4f7fb;
            color: #172033;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #657085;
            margin-bottom: 30px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e3e8f0;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow:
                0 4px 14px rgba(20, 40, 80, 0.05);
        }

        .card h2 {
            margin-top: 0;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .open {
            background: #e8f7ee;
            color: #18794e;
        }

        .closed {
            background: #fceaea;
            color: #b42318;
        }

        .warning {
            background: #fff6df;
            color: #8a5a00;
            padding: 14px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .success {
            background: #e8f7ee;
            color: #18794e;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .error {
            background: #fceaea;
            color: #b42318;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(180px, 1fr));

            gap: 14px;
        }

        .info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: bold;
        }

        button {
            border: 0;
            border-radius: 9px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin-right: 8px;
        }

        .btn-finalize {
            background: #1f6feb;
            color: white;
        }

        .btn-reset {
            background: #d92d20;
            color: white;
        }

        .btn-finalize:hover,
        .btn-reset:hover {
            opacity: 0.88;
        }

        pre {
            background: #101828;
            color: #e6edf3;
            padding: 18px;
            border-radius: 10px;
            overflow-x: auto;
            line-height: 1.5;
        }

        .note {
            font-size: 13px;
            color: #667085;
            margin-top: 10px;
        }

    </style>

</head>

<body>

<div class="container">

    <h1>Marcid Blue Daily Actions Tester</h1>

    <div class="subtitle">
        Temporary testing page — this page does not finalize or reset your real day.
    </div>


    <?php if ($message !== ''): ?>

        <div class="<?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <!-- CURRENT DAILY RECORD -->

    <div class="card">

        <h2>Current Daily Record</h2>

        <?php if (!$dailyRecord): ?>

            <div class="warning">
                No Open Daily Record was found.
            </div>

        <?php else: ?>

            <div class="info-grid">

                <div class="info">

                    <div class="info-label">
                        Daily ID
                    </div>

                    <div class="info-value">
                        <?= (int) $dailyRecord['daily_id'] ?>
                    </div>

                </div>


                <div class="info">

                    <div class="info-label">
                        Business Date
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars(
                            $dailyRecord['business_date']
                        ) ?>
                    </div>

                </div>


                <div class="info">

                    <div class="info-label">
                        Status
                    </div>

                    <div class="info-value">

                        <span class="status open">
                            <?= htmlspecialchars(
                                $dailyRecord['status']
                            ) ?>
                        </span>

                    </div>

                </div>


                <div class="info">

                    <div class="info-label">
                        Autosaved Draft
                    </div>

                    <div class="info-value">

                        <?php if ($draft): ?>

                            YES

                        <?php else: ?>

                            NO

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <!-- FINALIZE TEST -->

    <div class="card">

        <h2>Finalize & Close Day — Calculation Test</h2>

        <p>
            This runs the important calculation portion of the current
            Finalize logic using the autosaved draft.
        </p>

        <div class="warning">
            <strong>Safe test:</strong>
            This does NOT insert, update, delete, or close anything.
        </div>

        <br>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="test_finalize"
            >

            <button
                type="submit"
                class="btn-finalize"
            >
                Test Finalize
            </button>

        </form>

    </div>


    <!-- RESET TEST -->

    <div class="card">

        <h2>Reset — Deletion Test</h2>

        <p>
            This checks what the current Reset button would delete
            for the current Open Daily Record.
        </p>

        <div class="warning">
            <strong>Safe test:</strong>
            This does NOT delete anything.
        </div>

        <br>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="test_reset"
            >

            <button
                type="submit"
                class="btn-reset"
            >
                Test Reset
            </button>

        </form>

    </div>


    <?php if ($calculation !== null): ?>

        <div class="card">

            <h2>Test Result</h2>

            <pre><?=
                htmlspecialchars(
                    json_encode(
                        $calculation,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES
                    )
                )
            ?></pre>

            <div class="note">
                These are the values calculated or records detected
                by the test. No permanent database changes were made.
            </div>

        </div>

    <?php endif; ?>


    <!-- RAW DRAFT -->

    <?php if ($draft !== null): ?>

        <div class="card">

            <h2>Current Autosaved Draft</h2>

            <p class="note">
                This is the actual JSON currently stored for the
                Open Daily Record.
            </p>

            <pre><?=
                htmlspecialchars(
                    json_encode(
                        $draft,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES
                    )
                )
            ?></pre>

        </div>

    <?php endif; ?>


</div>

</body>

</html>