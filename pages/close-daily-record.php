<?php

date_default_timezone_set('Asia/Manila');
require_once '../auth/auth.php';
require_once '../config/database.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function jsonFail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function money($value): float
{
    $number = is_numeric($value) ? (float) $value : 0.0;
    return round($number, 2);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonFail('Method not allowed.', 405);
    }

    $dailyId = filter_input(INPUT_POST, 'daily_id', FILTER_VALIDATE_INT);
    if (!$dailyId) {
        jsonFail('Invalid daily record.');
    }

    $stmt = $pdo->prepare(
        "SELECT daily_id, business_date, status
         FROM daily_records
         WHERE daily_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $pdo->beginTransaction();
    $stmt->execute([$dailyId]);
    $dailyRecord = $stmt->fetch();

    if (!$dailyRecord) {
        throw new Exception('Daily record was not found.');
    }

    if ($dailyRecord['status'] !== 'Open') {
        throw new Exception('This daily record is already closed.');
    }

    $businessDate = $dailyRecord['business_date'];

    $draftTableExists = $pdo->query("SHOW TABLES LIKE 'daily_closing_drafts'")->fetchColumn();
    if (!$draftTableExists) {
        throw new Exception('The daily closing draft table is not available.');
    }

    $stmt = $pdo->prepare(
        "SELECT draft_data
         FROM daily_closing_drafts
         WHERE daily_id = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$dailyId]);
    $draftRow = $stmt->fetch();

    if (!$draftRow) {
        throw new Exception('No autosaved daily draft exists. Enter the daily information and wait for it to autosave before closing the day.');
    }

    $draft = json_decode($draftRow['draft_data'], true);
    if (!is_array($draft)) {
        throw new Exception('The saved daily draft is invalid.');
    }

    $walkInMoney = money($draft['walk_in_money'] ?? 0);
    if ($walkInMoney < 0) {
        throw new Exception('Shop Total Money Received cannot be negative.');
    }

    $shopExpenses = is_array($draft['expenses'] ?? null) ? $draft['expenses'] : [];
    $shopDeliveries = is_array($draft['deliveries'] ?? null) ? $draft['deliveries'] : [];
    $driver = is_array($draft['driver'] ?? null) ? $draft['driver'] : [];
    $driverMoney = money($driver['money_received'] ?? 0);
    if ($driverMoney < 0) {
        throw new Exception('Driver Total Money Received cannot be negative.');
    }

    $driverExpenses = is_array($driver['expenses'] ?? null) ? $driver['expenses'] : [];
    $driverDeliveries = is_array($driver['deliveries'] ?? null) ? $driver['deliveries'] : [];

    $allowedExpenseCategories = [
        'Food' => 'Food',
        'Gas' => 'Gas',
        'Cash Advance' => 'Cash Advance',
        'Others' => 'Miscellaneous',
        'Miscellaneous' => 'Miscellaneous',
    ];

    $allowedMethods = ['Cash', 'GCash', 'Bank Transfer', 'Other'];

    $normalizedShopExpenses = [];
    $shopExpenseTotal = 0.0;
    foreach ($shopExpenses as $expense) {
        if (!is_array($expense)) continue;
        $category = trim((string) ($expense['category'] ?? ''));
        $name = trim((string) ($expense['name'] ?? ''));
        $amountRaw = trim((string) ($expense['amount'] ?? ''));
        if ($category === '' && $name === '' && $amountRaw === '') continue;
        if (!isset($allowedExpenseCategories[$category])) throw new Exception('Invalid Station expense category.');
        if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) throw new Exception('Invalid Station expense amount.');
        if (in_array($category, ['Cash Advance', 'Others'], true) && $name === '') throw new Exception('Cash Advance and Others require a description.');
        $amount = money($amountRaw);
        $normalizedShopExpenses[] = [
            'category' => $allowedExpenseCategories[$category],
            'description' => in_array($category, ['Cash Advance', 'Others'], true) ? $name : $category,
            'amount' => $amount,
        ];
        $shopExpenseTotal += $amount;
    }

    $normalizedDriverExpenses = [];
    $driverExpenseTotal = 0.0;
    foreach ($driverExpenses as $expense) {
        if (!is_array($expense)) continue;
        $category = trim((string) ($expense['category'] ?? ''));
        $name = trim((string) ($expense['name'] ?? ''));
        $amountRaw = trim((string) ($expense['amount'] ?? ''));
        if ($category === '' && $name === '' && $amountRaw === '') continue;
        if (!isset($allowedExpenseCategories[$category])) throw new Exception('Invalid Driver expense category.');
        if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw < 0) throw new Exception('Invalid Driver expense amount.');
        if (in_array($category, ['Cash Advance', 'Others'], true) && $name === '') throw new Exception('Driver Cash Advance and Others require a description.');
        $amount = money($amountRaw);
        $normalizedDriverExpenses[] = [
            'category' => $allowedExpenseCategories[$category],
            'description' => in_array($category, ['Cash Advance', 'Others'], true) ? $name : $category,
            'amount' => $amount,
        ];
        $driverExpenseTotal += $amount;
    }

    $resolveCustomer = function (string $name, $priceRaw) use ($pdo): array {
        $name = trim($name);
        if ($name === '') throw new Exception('Every delivery must have a customer.');

        $stmt = $pdo->prepare(
            "SELECT customer_id, gallon_price
             FROM customers
             WHERE LOWER(TRIM(customer_name)) = LOWER(TRIM(?))
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$name]);
        $customer = $stmt->fetch();

        if ($customer) {
            $customerId = (int) $customer['customer_id'];
            $price = (float) $customer['gallon_price'];
            if ($price <= 0) throw new Exception('Customer "' . $name . '" has no valid saved Price/Gal.');
            return [$customerId, $price];
        }

        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw <= 0) {
            throw new Exception('A new customer requires a valid Price/Gal.');
        }

        $price = money($priceRaw);
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, gallon_price) VALUES (?, ?)");
        $stmt->execute([$name, $price]);
        return [(int) $pdo->lastInsertId(), $price];
    };

    $normalizedDeliveries = [];
    $shopDeliveryPaymentsTotal = 0.0;
    $shopDeliveryCount = count($shopDeliveries);

    foreach (array_merge($shopDeliveries, $driverDeliveries) as $index => $delivery) {
        if (!is_array($delivery)) continue;
        $customerName = trim((string) ($delivery['customer'] ?? ''));
        $slimRaw = trim((string) ($delivery['slim'] ?? ''));
        $roundRaw = trim((string) ($delivery['round'] ?? ''));
        $paymentRaw = trim((string) ($delivery['payment'] ?? ''));
        $priceRaw = trim((string) ($delivery['price'] ?? ''));
        $method = trim((string) ($delivery['method'] ?? 'Cash'));

        if ($customerName === '' && $slimRaw === '' && $roundRaw === '' && $paymentRaw === '' && $priceRaw === '') continue;
        if ($customerName === '') throw new Exception('Every delivery row must have a customer.');
        if ($slimRaw !== '' && (!ctype_digit($slimRaw) || (int) $slimRaw < 0)) throw new Exception('Slim quantity must be a whole number.');
        if ($roundRaw !== '' && (!ctype_digit($roundRaw) || (int) $roundRaw < 0)) throw new Exception('Round quantity must be a whole number.');

        $slim = $slimRaw === '' ? 0 : (int) $slimRaw;
        $round = $roundRaw === '' ? 0 : (int) $roundRaw;
        $gallons = $slim + $round;
        if ($gallons <= 0) throw new Exception('Every delivery row must contain at least one gallon.');
        if ($paymentRaw !== '' && (!is_numeric($paymentRaw) || (float) $paymentRaw < 0)) throw new Exception('Invalid delivery payment.');
        $payment = $paymentRaw === '' ? 0.0 : money($paymentRaw);
        if (!in_array($method, $allowedMethods, true)) throw new Exception('Invalid delivery payment method.');

        [$customerId, $price] = $resolveCustomer($customerName, $priceRaw);
        $amountDue = money($gallons * $price);
        $isShopDelivery = $index < $shopDeliveryCount;

        $normalizedDeliveries[] = [
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'slim' => $slim,
            'round' => $round,
            'price' => $price,
            'amount_due' => $amountDue,
            'payment' => $payment,
            'method' => $method,
            'collection_location' => $isShopDelivery ? 'Station' : 'Driver',
        ];

        if ($isShopDelivery) {
            $shopDeliveryPaymentsTotal += $payment;
        }
    }

    $shopBalance = money($walkInMoney + $shopExpenseTotal - $shopDeliveryPaymentsTotal);
    if ($shopBalance < -0.005) throw new Exception('Shop balance cannot be negative.');
    if (abs($shopBalance) < 0.005) $shopBalance = 0.0;
    $walkInCustomers = (int) floor($shopBalance / 30.00);
    $walkInSales = money($walkInCustomers * 30.00);
    $otherSales = money($shopBalance - $walkInSales);

    $driverDeliveryPaymentsTotal = 0.0;
    foreach ($driverDeliveries as $delivery) {
        if (!is_array($delivery)) continue;
        $paymentRaw = trim((string) ($delivery['payment'] ?? ''));
        if ($paymentRaw !== '' && is_numeric($paymentRaw)) $driverDeliveryPaymentsTotal += money($paymentRaw);
    }

    $totalExpectedDeliveryMoney = money($shopDeliveryPaymentsTotal + $driverDeliveryPaymentsTotal);

    // Shop-collected delivery payments count as effectively received for remittance
    // because they are already included in the expected delivery money.
    $effectiveReceived = money($driverMoney + $driverExpenseTotal + $shopDeliveryPaymentsTotal);
    $remittanceDifference = money($effectiveReceived - $totalExpectedDeliveryMoney);
    $driverTotalSales = money($driverMoney + $driverExpenseTotal);

    $stmt = $pdo->prepare(
        "INSERT INTO daily_sales
            (sales_date, walk_in_customers, walk_in_price, other_shop_payment, other_sales, daily_id)
         VALUES (?, ?, 30.00, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            walk_in_customers = VALUES(walk_in_customers),
            walk_in_price = VALUES(walk_in_price),
            other_shop_payment = VALUES(other_shop_payment),
            other_sales = VALUES(other_sales),
            daily_id = VALUES(daily_id)"
    );
    $stmt->execute([$businessDate, $walkInCustomers, $walkInMoney, $otherSales, $dailyId]);

    $stmt = $pdo->prepare("DELETE FROM expenses WHERE daily_id = ?");
    $stmt->execute([$dailyId]);
    $stmt = $pdo->prepare("DELETE FROM payments WHERE daily_id = ?");
    $stmt->execute([$dailyId]);
    $stmt = $pdo->prepare("DELETE FROM deliveries WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $expenseInsert = $pdo->prepare(
        "INSERT INTO expenses (expense_date, category, description, amount, daily_id)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($normalizedShopExpenses as $expense) {
        $expenseInsert->execute([$businessDate, $expense['category'], $expense['description'], $expense['amount'], $dailyId]);
    }
    foreach ($normalizedDriverExpenses as $expense) {
        $expenseInsert->execute([$businessDate, $expense['category'], '[Driver] ' . $expense['description'], $expense['amount'], $dailyId]);
    }

    $deliveryInsert = $pdo->prepare(
        "INSERT INTO deliveries
            (customer_id, delivery_date, slim_quantity, round_quantity, price_per_gallon, amount_due, daily_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $paymentInsert = $pdo->prepare(
        "INSERT INTO payments
            (delivery_id, customer_id, payment_date, amount, payment_method, daily_id, collection_location)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($normalizedDeliveries as $delivery) {
        $deliveryInsert->execute([
            $delivery['customer_id'],
            $businessDate,
            $delivery['slim'],
            $delivery['round'],
            $delivery['price'],
            $delivery['amount_due'],
            $dailyId,
        ]);
        $deliveryId = (int) $pdo->lastInsertId();
        if ($delivery['payment'] > 0) {
            $paymentInsert->execute([
                $deliveryId,
                $delivery['customer_id'],
                $businessDate,
                $delivery['payment'],
                $delivery['method'],
                $dailyId,
                $delivery['collection_location'],
            ]);
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE daily_records
         SET status = 'Closed'
         WHERE daily_id = ?"
    );
    $stmt->execute([$dailyId]);

    $stmt = $pdo->prepare("DELETE FROM daily_closing_drafts WHERE daily_id = ?");
    $stmt->execute([$dailyId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'daily_id' => (int) $dailyId,
        'business_date' => $businessDate,
        'walk_in_customers' => $walkInCustomers,
        'walk_in_sales' => $walkInSales,
        'other_sales' => $otherSales,
        'driver_total_sales' => $driverTotalSales,
        'expected_delivery_money' => $totalExpectedDeliveryMoney,
        'remittance_difference' => $remittanceDifference,
        'message' => 'Daily record finalized and closed successfully.'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonFail($e->getMessage());
}
