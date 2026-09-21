<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../config/database.php';

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

function getCurrentDebt(
    PDO $pdo,
    int $dailyId,
    array $draft
): void {

    $dailyRecord = getOpenDailyRecord(
        $pdo,
        $dailyId
    );

    if (!$dailyRecord) {
        respond([
            'success' => false,
            'message' => 'No open daily record is available.'
        ], 404);
    }

    $businessDate =
        $dailyRecord['business_date'];


    /*
     * Historical customer balances.
     *
     * Only transactions before today's business date
     * are included here. Today's draft is added separately.
     */
    $stmt = $pdo->prepare(
        "SELECT
            c.customer_id,
            c.customer_name,

            COALESCE(
                (
                    SELECT SUM(d.amount_due)
                    FROM deliveries d
                    WHERE d.customer_id = c.customer_id
                      AND d.delivery_date < ?
                ),
                0
            ) AS historical_due,

            COALESCE(
                (
                    SELECT SUM(p.amount)
                    FROM payments p
                    WHERE p.customer_id = c.customer_id
                      AND p.payment_date < ?
                ),
                0
            ) AS historical_paid

         FROM customers c

         WHERE TRIM(c.customer_name) <> ''

         ORDER BY c.customer_name ASC"
    );

    $stmt->execute([
        $businessDate,
        $businessDate
    ]);


    $customers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $balances = [];


    foreach ($customers as $customer) {

        $customerId =
            (int) $customer['customer_id'];

        $historicalDue =
            round(
                (float) $customer['historical_due'],
                2
            );

        $historicalPaid =
            round(
                (float) $customer['historical_paid'],
                2
            );

        $historicalBalance =
            round(
                $historicalDue -
                $historicalPaid,
                2
            );

        $balances[$customerId] = [
            'customer_id' =>
                $customerId,

            'customer_name' =>
                $customer['customer_name'],

            'historical_balance' =>
                $historicalBalance,

            'today_due' =>
                0.00,

            'today_payment' =>
                0.00,

            'current_balance' =>
                $historicalBalance,

            'credit' =>
                0.00
        ];
    }


    /*
     * Today's draft deliveries.
     */
    $draftShopDeliveries =
        $draft['shop']['deliveries']
        ?? [];

    $draftDriverDeliveries =
        $draft['driver']['deliveries']
        ?? [];


    $todayDeliveries = array_merge(
        is_array($draftShopDeliveries)
            ? $draftShopDeliveries
            : [],
        is_array($draftDriverDeliveries)
            ? $draftDriverDeliveries
            : []
    );


    foreach ($todayDeliveries as $delivery) {

        $customerName =
            trim(
                (string) (
                    $delivery['customer']
                    ?? ''
                )
            );

        if ($customerName === '') {
            continue;
        }

        $slim =
            max(
                0,
                (float) (
                    $delivery['slim']
                    ?? 0
                )
            );

        $round =
            max(
                0,
                (float) (
                    $delivery['round']
                    ?? 0
                )
            );

        $price =
            max(
                0,
                (float) (
                    $delivery['price']
                    ?? 0
                )
            );

        $todayDue =
            round(
                ($slim + $round) * $price,
                2
            );


        if ($todayDue <= 0) {
            continue;
        }


        $customerKey = null;

        foreach ($balances as $id => $balance) {

            if (
                mb_strtolower(
                    trim($balance['customer_name'])
                )
                ===
                mb_strtolower(
                    $customerName
                )
            ) {
                $customerKey = $id;
                break;
            }
        }


        /*
         * A customer can be typed before the master
         * customer record exists.
         */
        if ($customerKey === null) {

            $customerKey =
                'new:' .
                mb_strtolower(
                    $customerName
                );

            if (!isset($balances[$customerKey])) {

                $balances[$customerKey] = [
                    'customer_id' =>
                        0,

                    'customer_name' =>
                        $customerName,

                    'historical_balance' =>
                        0.00,

                    'today_due' =>
                        0.00,

                    'today_payment' =>
                        0.00,

                    'current_balance' =>
                        0.00,

                    'credit' =>
                        0.00
                ];
            }
        }


        $balances[$customerKey]['today_due'] =
            round(
                $balances[$customerKey]['today_due']
                + $todayDue,
                2
            );
    }


    /*
     * Today's actual payments.
     *
     * The payment is money received today,
     * regardless of which debt it eventually settles.
     */
    $todayPayment = function (
        array $deliveryRows
    ) use (
        &$balances
    ): void {

        foreach ($deliveryRows as $delivery) {

            $customerName =
                trim(
                    (string) (
                        $delivery['customer']
                        ?? ''
                    )
                );

            if ($customerName === '') {
                continue;
            }

            $payment =
                max(
                    0,
                    (float) (
                        $delivery['payment']
                        ?? 0
                    )
                );

            if ($payment <= 0) {
                continue;
            }


            $customerKey = null;

            foreach ($balances as $id => $balance) {

                if (
                    mb_strtolower(
                        trim($balance['customer_name'])
                    )
                    ===
                    mb_strtolower(
                        $customerName
                    )
                ) {
                    $customerKey = $id;
                    break;
                }
            }


            if ($customerKey === null) {

                $customerKey =
                    'new:' .
                    mb_strtolower(
                        $customerName
                    );

                if (!isset($balances[$customerKey])) {

                    $balances[$customerKey] = [
                        'customer_id' => 0,
                        'customer_name' => $customerName,
                        'historical_balance' => 0.00,
                        'today_due' => 0.00,
                        'today_payment' => 0.00,
                        'current_balance' => 0.00,
                        'credit' => 0.00
                    ];
                }
            }


            $balances[$customerKey]['today_payment'] =
                round(
                    $balances[$customerKey]['today_payment']
                    + $payment,
                    2
                );
        }
    };


    $todayPayment(
        is_array($draftShopDeliveries)
            ? $draftShopDeliveries
            : []
    );

    $todayPayment(
        is_array($draftDriverDeliveries)
            ? $draftDriverDeliveries
            : []
    );


    /*
     * Final customer-level balance:
     *
     * historical debt
     * + today's delivery
     * - today's payment
     *
     * Positive = debt
     * Negative = customer credit
     */
    $result = [];

    $totalDebt = 0.00;
    $totalCredit = 0.00;


    foreach ($balances as $balance) {

        $grossBalance =
            round(
                $balance['historical_balance']
                + $balance['today_due']
                - $balance['today_payment'],
                2
            );


        $currentDebt =
            max(
                0,
                $grossBalance
            );

        $credit =
            max(
                0,
                -$grossBalance
            );


        if (
            $currentDebt <= 0 &&
            $credit <= 0
        ) {
            continue;
        }


        $balance['current_balance'] =
            $currentDebt;

        $balance['credit'] =
            $credit;


        $totalDebt =
            round(
                $totalDebt +
                $currentDebt,
                2
            );

        $totalCredit =
            round(
                $totalCredit +
                $credit,
                2
            );


        $result[] = $balance;
    }


    usort(
        $result,
        function (
            array $a,
            array $b
        ): int {

            if (
                $a['current_balance'] ===
                $b['current_balance']
            ) {
                return strcasecmp(
                    $a['customer_name'],
                    $b['customer_name']
                );
            }

            return
                $a['current_balance'] <
                $b['current_balance']
                    ? 1
                    : -1;
        }
    );


    respond([
        'success' => true,

        'daily_id' =>
            (int) $dailyRecord['daily_id'],

        'business_date' =>
            $businessDate,

        'total_debt' =>
            round($totalDebt, 2),

        'total_credit' =>
            round($totalCredit, 2),

        'customers' =>
            $result
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

function finalizeDailyClosing(
    PDO $pdo,
    int $dailyId
): void {

    /*
     * Only an Open daily record can be finalized.
     */
    $stmt = $pdo->prepare(
        "SELECT
            daily_id,
            business_date,
            status
         FROM daily_records
         WHERE daily_id = ?
         LIMIT 1"
    );

    $stmt->execute([
        $dailyId
    ]);

    $dailyRecord =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$dailyRecord) {
        respond([
            'success' => false,
            'message' =>
                'Daily record not found.'
        ], 404);
    }


    if ($dailyRecord['status'] !== 'Open') {
        respond([
            'success' => false,
            'message' =>
                'This daily record is no longer open.'
        ], 409);
    }


    /*
     * The frontend will provide the final result
     * after running the existing Daily Closing calculations.
     */
    $closingResult =
        trim(
            (string) (
                $_POST['closing_result'] ?? ''
            )
        );


    $allowedResults = [
        'Pending',
        'Balanced',
        'Short',
        'Over'
    ];


    if (
        !in_array(
            $closingResult,
            $allowedResults,
            true
        )
    ) {
        respond([
            'success' => false,
            'message' =>
                'Invalid closing result.'
        ], 400);
    }


    /*
     * Actual station cash is optional for now.
     *
     * The existing Daily Closing calculation will determine
     * what value should be sent here when we connect the
     * Finalize & Close Day button.
     */
    $actualStationCashRaw =
        trim(
            (string) (
                $_POST['actual_station_cash']
                ?? ''
            )
        );


    $actualStationCash = null;


    if ($actualStationCashRaw !== '') {

        if (
            !is_numeric(
                $actualStationCashRaw
            )
        ) {
            respond([
                'success' => false,
                'message' =>
                    'Invalid actual station cash.'
            ], 400);
        }


        $actualStationCash =
            round(
                (float) $actualStationCashRaw,
                2
            );


        if ($actualStationCash < 0) {
            respond([
                'success' => false,
                'message' =>
                    'Actual station cash cannot be negative.'
            ], 400);
        }
    }


    /*
     * Use the authenticated admin account.
     */
    $savedBy =
        isset($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : 0;


    if ($savedBy <= 0) {
        respond([
            'success' => false,
            'message' =>
                'Unable to identify the current admin user.'
        ], 401);
    }


    try {

        $pdo->beginTransaction();


        /*
         * Finalize the daily record.
         *
         * Open → Saved
         *
         * The closing result is stored independently
         * from the daily record status.
         */
        $stmt =
            $pdo->prepare(
                "UPDATE daily_records
                 SET
                    status = 'Saved',
                    closing_result = ?,
                    actual_station_cash = ?,
                    saved_at = NOW(),
                    saved_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE daily_id = ?
                   AND status = 'Open'"
            );


        $stmt->execute([
            $closingResult,
            $actualStationCash,
            $savedBy,
            $dailyId
        ]);


        if ($stmt->rowCount() !== 1) {

            throw new RuntimeException(
                'The daily record could not be finalized.'
            );
        }


        /*
         * The draft is no longer needed because the day
         * has now been permanently saved.
         */
        $stmt =
            $pdo->prepare(
                "DELETE FROM daily_closing_drafts
                 WHERE daily_id = ?"
            );


        $stmt->execute([
            $dailyId
        ]);


        $pdo->commit();


        respond([
            'success' => true,
            'action' => 'finalize',
            'daily_id' =>
                $dailyId,
            'business_date' =>
                $dailyRecord['business_date'],
            'status' =>
                'Saved',
            'closing_result' =>
                $closingResult,
            'actual_station_cash' =>
                $actualStationCash,
            'saved_at' =>
                date('Y-m-d H:i:s'),
            'saved_by' =>
                $savedBy
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        throw $e;
    }
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

        if ($action === 'get_current_debt') {

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


        if ($dailyId <= 0) {
            respond([
                'success' => false,
                'message' =>
                    'Invalid daily record.'
            ], 400);
        }


        $draftRaw =
            (string) (
                $_GET['draft'] ?? ''
            );


        $draft = [];


        if ($draftRaw !== '') {

            $decoded =
                json_decode(
                    $draftRaw,
                    true
                );

            if (is_array($decoded)) {
                $draft = $decoded;
                }
            }


            getCurrentDebt(
                $pdo,
                $dailyId,
                $draft
                );
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

        if ($action === 'finalize') {

            finalizeDailyClosing(
                $pdo,
                $dailyId
            );
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