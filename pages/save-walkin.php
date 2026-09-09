<?php

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daily-closing.php');
    exit;
}

$inputCustomers = trim($_POST['walk_in_customers'] ?? '');
$inputMoney = trim($_POST['walk_in_money'] ?? '');

$walkInPrice = 30.00;
$customersValue = 0;

try {

    // --------------------------------------------------
    // Validate Walk-in input only.
    // --------------------------------------------------

    if ($inputCustomers !== '') {

        if (!ctype_digit($inputCustomers) || (int)$inputCustomers < 0) {
            throw new Exception('Walk-in customers must be a whole number.');
        }

        $customersValue = (int)$inputCustomers;

    } elseif ($inputMoney !== '') {

        if (!is_numeric($inputMoney) || (float)$inputMoney < 0) {
            throw new Exception('Walk-in money must be a valid amount.');
        }

        $moneyValue = (float)$inputMoney;
        $calculatedCustomers = $moneyValue / $walkInPrice;

        if (abs($calculatedCustomers - round($calculatedCustomers)) > 0.000001) {
            throw new Exception(
                'Walk-in money must divide evenly by ₱'
                . number_format($walkInPrice, 2)
                . '.'
            );
        }

        $customersValue = (int)round($calculatedCustomers);
    }

    // --------------------------------------------------
    // Find the active daily record.
    // --------------------------------------------------

    $stmt = $pdo->prepare("
        SELECT daily_id, business_date
        FROM daily_records
        WHERE status = 'Open'
        ORDER BY daily_id DESC
        LIMIT 1
    ");

    $stmt->execute();
    $dailyRecord = $stmt->fetch();

    if (!$dailyRecord) {
        throw new Exception('No active daily closing record is available.');
    }

    $dailyId = (int)$dailyRecord['daily_id'];
    $businessDate = $dailyRecord['business_date'];

    $pdo->beginTransaction();

    // --------------------------------------------------
    // Save ONLY the regular Walk-in sales record.
    // Driver deliveries, expenses, gallon sales and
    // reconciliation are intentionally untouched.
    // --------------------------------------------------

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

    $pdo->commit();

    header('Location: daily-closing.php?walkin_saved=1');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = urlencode($e->getMessage());
    header('Location: daily-closing.php?walkin_error=' . $message);
    exit;
}
