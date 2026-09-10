<?php

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed.'
        ]);
        exit;
    }

    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    $priceRaw = trim((string) ($_POST['gallon_price'] ?? ''));

    if ($customerName === '') {
        throw new Exception('Customer name is required.');
    }

    if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw <= 0) {
        throw new Exception('Price/Gal must be greater than zero.');
    }

    $pricePerGallon = round((float) $priceRaw, 2);

    $stmt = $pdo->prepare(
        "SELECT customer_id
         FROM customers
         WHERE LOWER(TRIM(customer_name)) = LOWER(TRIM(?))
         LIMIT 1"
    );

    $stmt->execute([$customerName]);
    $customer = $stmt->fetch();

    if ($customer) {
        $customerId = (int) $customer['customer_id'];

        $stmt = $pdo->prepare(
            "UPDATE customers
             SET gallon_price = ?
             WHERE customer_id = ?"
        );

        $stmt->execute([
            $pricePerGallon,
            $customerId,
        ]);

        echo json_encode([
            'success' => true,
            'action' => 'updated',
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'gallon_price' => $pricePerGallon,
            'message' => 'Customer Price/Gal updated successfully.'
        ]);
        exit;
    }

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

    echo json_encode([
        'success' => true,
        'action' => 'created',
        'customer_id' => $customerId,
        'customer_name' => $customerName,
        'gallon_price' => $pricePerGallon,
        'message' => 'New customer and Price/Gal saved successfully.'
    ]);
} catch (Throwable $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
