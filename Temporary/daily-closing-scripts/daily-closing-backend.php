<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function respond(
    array $data,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function getCustomers(
    PDO $pdo
): void {
    $stmt = $pdo->query(
        "SELECT
            customer_id,
            customer_name,
            gallon_price
         FROM customers
         WHERE TRIM(customer_name) <> ''
         ORDER BY customer_name ASC"
    );

    respond([
        'success' => true,
        'customers' => $stmt->fetchAll(
            PDO::FETCH_ASSOC
        )
    ]);
}

function getOpenDailyRecord(
    PDO $pdo,
    int $dailyId = 0
): ?array {
    if ($dailyId > 0) {
        $stmt = $pdo->prepare(
            "SELECT
                daily_id,
                business_date,
                status
             FROM daily_records
             WHERE daily_id = ?
               AND status = 'Open'
             LIMIT 1"
        );

        $stmt->execute([
            $dailyId
        ]);
    } else {
        $stmt = $pdo->query(
            "SELECT
                daily_id,
                business_date,
                status
             FROM daily_records
             WHERE status = 'Open'
             ORDER BY daily_id DESC
             LIMIT 1"
        );
    }

    $record =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return $record ?: null;
}

function ensureDraftTable(
    PDO $pdo
): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS daily_closing_drafts (
            draft_id INT NOT NULL AUTO_INCREMENT,
            daily_id INT NOT NULL,
            draft_data LONGTEXT NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (draft_id),
            UNIQUE KEY uq_daily_closing_draft_daily_id (daily_id),
            KEY idx_daily_closing_draft_daily_id (daily_id),
            CONSTRAINT fk_daily_closing_draft_daily
                FOREIGN KEY (daily_id)
                REFERENCES daily_records (daily_id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_0900_ai_ci"
    );
}

function saveCustomerPrice(
    PDO $pdo
): void {
    $customerName =
        trim(
            (string) (
                $_POST['customer_name'] ?? ''
            )
        );

    $priceRaw =
        trim(
            (string) (
                $_POST['gallon_price'] ?? ''
            )
        );

    if ($customerName === '') {
        respond([
            'success' => false,
            'message' =>
                'Customer name is required.'
        ], 400);
    }

    if (
        $priceRaw === '' ||
        !is_numeric($priceRaw) ||
        (float) $priceRaw <= 0
    ) {
        respond([
            'success' => false,
            'message' =>
                'Price/Gal must be greater than zero.'
        ], 400);
    }

    $price =
        round(
            (float) $priceRaw,
            2
        );

    $stmt =
        $pdo->prepare(
            "SELECT
                customer_id,
                customer_name
             FROM customers
             WHERE LOWER(TRIM(customer_name)) =
                   LOWER(TRIM(?))
             LIMIT 1"
        );

    $stmt->execute([
        $customerName
    ]);

    $customer =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($customer) {
        $stmt =
            $pdo->prepare(
                "UPDATE customers
                 SET gallon_price = ?
                 WHERE customer_id = ?"
            );

        $stmt->execute([
            $price,
            (int) $customer['customer_id']
        ]);

        respond([
            'success' => true,
            'action' => 'updated',
            'customer_id' =>
                (int) $customer['customer_id'],
            'customer_name' =>
                $customer['customer_name'],
            'gallon_price' =>
                $price
        ]);
    }

    $stmt =
        $pdo->prepare(
            "INSERT INTO customers (
                customer_name,
                gallon_price
             )
             VALUES (?, ?)"
        );

    $stmt->execute([
        $customerName,
        $price
    ]);

    respond([
        'success' => true,
        'action' => 'created',
        'customer_id' =>
            (int) $pdo->lastInsertId(),
        'customer_name' =>
            $customerName,
        'gallon_price' =>
            $price
    ]);
}

try {
    $method =
        strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );

    /*
     * Customer list does not require a daily_id.
     */
    if ($method === 'GET') {
        $action =
            trim(
                (string) (
                    $_GET['action'] ?? ''
                )
            );

        if ($action === 'get_customers') {
            getCustomers($pdo);
        }

        $dailyId =
            filter_input(
                INPUT_GET,
                'daily_id',
                FILTER_VALIDATE_INT
            );

        $dailyId =
            $dailyId
                ? (int) $dailyId
                : 0;

        $dailyRecord =
            getOpenDailyRecord(
                $pdo,
                $dailyId
            );

        if (!$dailyRecord) {
            respond([
                'success' => false,
                'message' =>
                    'No open daily record is available.'
            ], 404);
        }

        ensureDraftTable($pdo);

        $stmt =
            $pdo->prepare(
                "SELECT
                    draft_data,
                    updated_at
                 FROM daily_closing_drafts
                 WHERE daily_id = ?
                 LIMIT 1"
            );

        $stmt->execute([
            (int) $dailyRecord['daily_id']
        ]);

        $draftRow =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$draftRow) {
            respond([
                'success' => true,
                'has_draft' => false,
                'daily_id' =>
                    (int) $dailyRecord['daily_id'],
                'business_date' =>
                    $dailyRecord['business_date'],
                'status' =>
                    $dailyRecord['status']
            ]);
        }

        $draft =
            json_decode(
                $draftRow['draft_data'],
                true
            );

        if (!is_array($draft)) {
            respond([
                'success' => false,
                'message' =>
                    'Saved draft data is invalid.'
            ], 500);
        }

        respond([
            'success' => true,
            'has_draft' => true,
            'daily_id' =>
                (int) $dailyRecord['daily_id'],
            'business_date' =>
                $dailyRecord['business_date'],
            'status' =>
                $dailyRecord['status'],
            'updated_at' =>
                $draftRow['updated_at'],
            'draft' =>
                $draft
        ]);
    }

    if ($method === 'POST') {
        $action =
            trim(
                (string) (
                    $_POST['action'] ?? ''
                )
            );

        /*
         * Customer master price is independent
         * from the daily closing draft.
         */
        if (
            $action ===
            'save_customer_price'
        ) {
            saveCustomerPrice($pdo);
        }

        $dailyId =
            filter_input(
                INPUT_POST,
                'daily_id',
                FILTER_VALIDATE_INT
            );

        $dailyId =
            $dailyId
                ? (int) $dailyId
                : 0;

        if ($dailyId <= 0) {
            respond([
                'success' => false,
                'message' =>
                    'Invalid daily record.'
            ], 400);
        }

        $dailyRecord =
            getOpenDailyRecord(
                $pdo,
                $dailyId
            );

        if (!$dailyRecord) {
            respond([
                'success' => false,
                'message' =>
                    'The selected daily record is not open.'
            ], 409);
        }

        ensureDraftTable($pdo);

        if ($action === 'save') {
            $draftRaw =
                (string) (
                    $_POST['draft'] ?? ''
                );

            if ($draftRaw === '') {
                respond([
                    'success' => false,
                    'message' =>
                        'Draft data is required.'
                ], 400);
            }

            $draft =
                json_decode(
                    $draftRaw,
                    true
                );

            if (!is_array($draft)) {
                respond([
                    'success' => false,
                    'message' =>
                        'Invalid draft data.'
                ], 400);
            }

            $encodedDraft =
                json_encode(
                    $draft,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

            if ($encodedDraft === false) {
                respond([
                    'success' => false,
                    'message' =>
                        'Unable to encode draft data.'
                ], 500);
            }

            $stmt =
                $pdo->prepare(
                    "INSERT INTO daily_closing_drafts (
                        daily_id,
                        draft_data
                     )
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE
                        draft_data =
                            VALUES(draft_data),
                        updated_at =
                            CURRENT_TIMESTAMP"
                );

            $stmt->execute([
                $dailyId,
                $encodedDraft
            ]);

            respond([
                'success' => true,
                'action' => 'save',
                'daily_id' =>
                    $dailyId,
                'business_date' =>
                    $dailyRecord['business_date']
            ]);
        }

        if ($action === 'reset') {
            $stmt =
                $pdo->prepare(
                    "DELETE FROM daily_closing_drafts
                     WHERE daily_id = ?"
                );

            $stmt->execute([
                $dailyId
            ]);

            respond([
                'success' => true,
                'action' => 'reset',
                'daily_id' =>
                    $dailyId
            ]);
        }

        respond([
            'success' => false,
            'message' =>
                'Unknown action.'
        ], 400);
    }

    respond([
        'success' => false,
        'message' =>
            'Method not allowed.'
    ], 405);

} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' =>
            $e->getMessage()
    ], 500);
}