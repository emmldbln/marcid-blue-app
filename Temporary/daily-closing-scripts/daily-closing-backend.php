<?php

/*
 * =========================================================
 * MARCID BLUE
 * TEMPORARY DAILY CLOSING BACKEND
 *
 * Standalone backend for:
 *
 *     Temporary/daily-closing-redesign.php
 *
 * This file does NOT call the old daily-closing endpoints.
 *
 * Supported actions:
 *
 *     GET
 *       - context
 *       - load_draft
 *
 *     POST
 *       - save_draft
 *       - save_customer
 *       - reset
 *       - finalize
 *
 * =========================================================
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');


/*
 * =========================================================
 * RESPONSE HELPERS
 * =========================================================
 */

function jsonResponse(
    array $data,
    int $status = 200
): void {

    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


function jsonFail(
    string $message,
    int $status = 400
): void {

    jsonResponse(
        [
            'success' => false,
            'message' => $message
        ],
        $status
    );

}


/*
 * =========================================================
 * GENERAL HELPERS
 * =========================================================
 */

function money($value): float
{
    if (
        $value === null ||
        $value === ''
    ) {
        return 0.00;
    }

    if (!is_numeric($value)) {
        return 0.00;
    }

    return round(
        (float) $value,
        2
    );
}


function requestJson(): array
{
    $raw = file_get_contents('php://input');

    if (
        $raw === false ||
        trim($raw) === ''
    ) {
        return [];
    }

    $decoded =
        json_decode(
            $raw,
            true
        );

    if (!is_array($decoded)) {
        throw new Exception(
            'Invalid JSON request.'
        );
    }

    return $decoded;
}


/*
 * =========================================================
 * DAILY RECORD
 * =========================================================
 */

function getOpenDailyRecord(
    PDO $pdo
): array {

    $stmt = $pdo->query(
        "SELECT
            daily_id,
            business_date,
            status
         FROM daily_records
         WHERE status = 'Open'
         ORDER BY business_date DESC, daily_id DESC
         LIMIT 1"
    );

    $record =
        $stmt->fetch();

    if (!$record) {

        throw new Exception(
            'No open daily record is available.'
        );

    }

    return $record;

}


function getDailyRecord(
    PDO $pdo,
    int $dailyId,
    bool $forUpdate = false
): array {

    $sql =
        "SELECT
            daily_id,
            business_date,
            status
         FROM daily_records
         WHERE daily_id = ?
         LIMIT 1";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt =
        $pdo->prepare($sql);

    $stmt->execute(
        [$dailyId]
    );

    $record =
        $stmt->fetch();

    if (!$record) {

        throw new Exception(
            'Daily record was not found.'
        );

    }

    return $record;

}


/*
 * =========================================================
 * DRAFT TABLE
 * =========================================================
 */

function ensureDraftTable(
    PDO $pdo
): void {

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS daily_closing_drafts (

            draft_id INT NOT NULL AUTO_INCREMENT,

            daily_id INT NOT NULL,

            draft_data LONGTEXT NOT NULL,

            created_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at TIMESTAMP NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (draft_id),

            UNIQUE KEY uq_daily_closing_draft_daily_id
                (daily_id),

            CONSTRAINT fk_daily_closing_draft_daily
                FOREIGN KEY (daily_id)
                REFERENCES daily_records(daily_id)
                ON DELETE CASCADE

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci"
    );

}


/*
 * =========================================================
 * DEFAULT DRAFT
 * =========================================================
 */

function emptyDraft(): array
{
    return [

        'walk_in_money' => 0,

        'walk_in_customers' => 0,

        'expenses' => [],

        'deliveries' => [],

        'driver' => [

            'money_received' => 0,

            'expenses' => [],

            'deliveries' => []

        ]

    ];
}


/*
 * =========================================================
 * NORMALIZE DRAFT
 *
 * Makes sure the frontend cannot accidentally send
 * unexpected structures into the database.
 * =========================================================
 */

function normalizeDraft(
    array $input
): array {

    $draft =
        emptyDraft();


    /*
     * -----------------------------------------------------
     * SHOP
     * -----------------------------------------------------
     */

    $draft['walk_in_money'] =
        max(
            0,
            money(
                $input['walk_in_money'] ?? 0
            )
        );


    $draft['walk_in_customers'] =
        max(
            0,
            (int) (
                $input['walk_in_customers'] ?? 0
            )
        );


    /*
     * -----------------------------------------------------
     * SHOP EXPENSES
     * -----------------------------------------------------
     */

    $expenses =
        is_array(
            $input['expenses'] ?? null
        )
            ? $input['expenses']
            : [];


    foreach ($expenses as $expense) {

        if (!is_array($expense)) {
            continue;
        }


        $category =
            trim(
                (string) (
                    $expense['category'] ?? ''
                )
            );


        $name =
            trim(
                (string) (
                    $expense['name'] ?? ''
                )
            );


        $amount =
            money(
                $expense['amount'] ?? 0
            );


        /*
         * Completely empty row.
         */

        if (
            $category === '' &&
            $name === '' &&
            $amount == 0
        ) {
            continue;
        }


        $draft['expenses'][] = [

            'category' =>
                $category,

            'name' =>
                $name,

            'amount' =>
                $amount

        ];

    }


    /*
     * -----------------------------------------------------
     * SHOP DELIVERIES
     * -----------------------------------------------------
     */

    $deliveries =
        is_array(
            $input['deliveries'] ?? null
        )
            ? $input['deliveries']
            : [];


    foreach ($deliveries as $delivery) {

        if (!is_array($delivery)) {
            continue;
        }


        $customer =
            trim(
                (string) (
                    $delivery['customer'] ?? ''
                )
            );


        $slim =
            max(
                0,
                (int) (
                    $delivery['slim'] ?? 0
                )
            );


        $round =
            max(
                0,
                (int) (
                    $delivery['round'] ?? 0
                )
            );


        $payment =
            max(
                0,
                money(
                    $delivery['payment'] ?? 0
                )
            );


        $price =
            max(
                0,
                money(
                    $delivery['price'] ?? 0
                )
            );


        $method =
            trim(
                (string) (
                    $delivery['method'] ?? 'Cash'
                )
            );


        if (
            $customer === '' &&
            $slim === 0 &&
            $round === 0 &&
            $payment == 0 &&
            $price == 0
        ) {
            continue;
        }


        $draft['deliveries'][] = [

            'customer' =>
                $customer,

            'slim' =>
                $slim,

            'round' =>
                $round,

            'payment' =>
                $payment,

            'price' =>
                $price,

            'method' =>
                $method

        ];

    }


    /*
     * -----------------------------------------------------
     * DRIVER
     * -----------------------------------------------------
     */

    $driver =
        is_array(
            $input['driver'] ?? null
        )
            ? $input['driver']
            : [];


    $draft['driver']['money_received'] =
        max(
            0,
            money(
                $driver['money_received'] ?? 0
            )
        );


    /*
     * -----------------------------------------------------
     * DRIVER EXPENSES
     * -----------------------------------------------------
     */

    $driverExpenses =
        is_array(
            $driver['expenses'] ?? null
        )
            ? $driver['expenses']
            : [];


    foreach ($driverExpenses as $expense) {

        if (!is_array($expense)) {
            continue;
        }


        $category =
            trim(
                (string) (
                    $expense['category'] ?? ''
                )
            );


        $name =
            trim(
                (string) (
                    $expense['name'] ?? ''
                )
            );


        $amount =
            money(
                $expense['amount'] ?? 0
            );


        if (
            $category === '' &&
            $name === '' &&
            $amount == 0
        ) {
            continue;
        }


        $draft['driver']['expenses'][] = [

            'category' =>
                $category,

            'name' =>
                $name,

            'amount' =>
                $amount

        ];

    }


    /*
     * -----------------------------------------------------
     * DRIVER DELIVERIES
     * -----------------------------------------------------
     */

    $driverDeliveries =
        is_array(
            $driver['deliveries'] ?? null
        )
            ? $driver['deliveries']
            : [];


    foreach ($driverDeliveries as $delivery) {

        if (!is_array($delivery)) {
            continue;
        }


        $customer =
            trim(
                (string) (
                    $delivery['customer'] ?? ''
                )
            );


        $slim =
            max(
                0,
                (int) (
                    $delivery['slim'] ?? 0
                )
            );


        $round =
            max(
                0,
                (int) (
                    $delivery['round'] ?? 0
                )
            );


        $payment =
            max(
                0,
                money(
                    $delivery['payment'] ?? 0
                )
            );


        $price =
            max(
                0,
                money(
                    $delivery['price'] ?? 0
                )
            );


        $method =
            trim(
                (string) (
                    $delivery['method'] ?? 'Cash'
                )
            );


        if (
            $customer === '' &&
            $slim === 0 &&
            $round === 0 &&
            $payment == 0 &&
            $price == 0
        ) {
            continue;
        }


        $draft['driver']['deliveries'][] = [

            'customer' =>
                $customer,

            'slim' =>
                $slim,

            'round' =>
                $round,

            'payment' =>
                $payment,

            'price' =>
                $price,

            'method' =>
                $method

        ];

    }


    return $draft;

}


/*
 * =========================================================
 * CUSTOMER LOOKUP
 * =========================================================
 */

function findCustomer(
    PDO $pdo,
    string $name
): ?array {

    $name =
        trim($name);

    if ($name === '') {
        return null;
    }


    $stmt =
        $pdo->prepare(
            "SELECT
                customer_id,
                customer_name,
                gallon_price
             FROM customers
             WHERE LOWER(TRIM(customer_name))
                 = LOWER(TRIM(?))
             LIMIT 1"
        );


    $stmt->execute(
        [$name]
    );


    $customer =
        $stmt->fetch();


    if (!$customer) {
        return null;
    }


    return $customer;

}


/*
 * =========================================================
 * CUSTOMER AUTOCOMPLETE
 * =========================================================
 */

function getCustomers(
    PDO $pdo
): array {

    $stmt =
        $pdo->query(
            "SELECT
                customer_id,
                customer_name,
                gallon_price
             FROM customers
             ORDER BY customer_name ASC"
        );


    return
        $stmt->fetchAll();

}


/*
 * =========================================================
 * SAVE / UPDATE CUSTOMER
 * =========================================================
 *
 * Existing customers are NOT overwritten automatically.
 *
 * This is important because entering a delivery should
 * load the saved price for an existing customer.
 *
 * New customers are inserted with their entered price.
 * =========================================================
 */

function saveCustomer(
    PDO $pdo,
    string $name,
    $price
): array {

    $name =
        trim($name);


    if ($name === '') {

        throw new Exception(
            'Customer name is required.'
        );

    }


    if (
        $price === '' ||
        !is_numeric($price) ||
        (float) $price <= 0
    ) {

        throw new Exception(
            'A valid Price/Gal is required.'
        );

    }


    $price =
        money($price);


    $existing =
        findCustomer(
            $pdo,
            $name
        );


    if ($existing) {

        return [

            'customer_id' =>
                (int) $existing['customer_id'],

            'customer_name' =>
                $existing['customer_name'],

            'gallon_price' =>
                money(
                    $existing['gallon_price']
                ),

            'created' =>
                false,

            'message' =>
                'Existing customer loaded.'

        ];

    }


    $stmt =
        $pdo->prepare(
            "INSERT INTO customers
                (
                    customer_name,
                    gallon_price
                )
             VALUES
                (
                    ?,
                    ?
                )"
        );


    $stmt->execute(
        [
            $name,
            $price
        ]
    );


    return [

        'customer_id' =>
            (int) $pdo->lastInsertId(),

        'customer_name' =>
            $name,

        'gallon_price' =>
            $price,

        'created' =>
            true,

        'message' =>
            'Customer saved successfully.'

    ];

}


/*
 * =========================================================
 * VALIDATION HELPERS FOR FINALIZATION
 * =========================================================
 */

function normalizeExpenseForFinalize(
    array $expenses,
    string $source
): array {

    $allowedCategories = [

        'Food' =>
            'Food',

        'Gas' =>
            'Gas',

        'Cash Advance' =>
            'Cash Advance',

        'Others' =>
            'Miscellaneous',

        'Miscellaneous' =>
            'Miscellaneous'

    ];


    $normalized = [];

    $total = 0.00;


    foreach ($expenses as $expense) {

        if (!is_array($expense)) {
            continue;
        }


        $category =
            trim(
                (string) (
                    $expense['category'] ?? ''
                )
            );


        $name =
            trim(
                (string) (
                    $expense['name'] ?? ''
                )
            );


        $amountRaw =
            $expense['amount'] ?? '';


        if (
            $category === '' &&
            $name === '' &&
            (
                $amountRaw === '' ||
                money($amountRaw) == 0
            )
        ) {
            continue;
        }


        if (
            !isset(
                $allowedCategories[$category]
            )
        ) {

            throw new Exception(
                'Invalid ' .
                $source .
                ' expense category.'
            );

        }


        if (
            $amountRaw === '' ||
            !is_numeric($amountRaw) ||
            (float) $amountRaw < 0
        ) {

            throw new Exception(
                'Invalid ' .
                $source .
                ' expense amount.'
            );

        }


        if (
            in_array(
                $category,
                [
                    'Cash Advance',
                    'Others'
                ],
                true
            ) &&
            $name === ''
        ) {

            throw new Exception(
                $source .
                ' Cash Advance and Others require a description.'
            );

        }


        $amount =
            money($amountRaw);


        $normalized[] = [

            'category' =>
                $allowedCategories[$category],

            'description' =>
                in_array(
                    $category,
                    [
                        'Cash Advance',
                        'Others'
                    ],
                    true
                )
                    ? $name
                    : $category,

            'amount' =>
                $amount

        ];


        $total +=
            $amount;

    }


    return [

        'items' =>
            $normalized,

        'total' =>
            money($total)

    ];

}


/*
 * =========================================================
 * CUSTOMER RESOLUTION FOR FINALIZATION
 * =========================================================
 */

function resolveCustomerForFinalize(
    PDO $pdo,
    string $name,
    $priceRaw
): array {

    $name =
        trim($name);


    if ($name === '') {

        throw new Exception(
            'Every delivery must have a customer.'
        );

    }


    $stmt =
        $pdo->prepare(
            "SELECT
                customer_id,
                customer_name,
                gallon_price
             FROM customers
             WHERE LOWER(TRIM(customer_name))
                 = LOWER(TRIM(?))
             LIMIT 1
             FOR UPDATE"
        );


    $stmt->execute(
        [$name]
    );


    $customer =
        $stmt->fetch();


    /*
     * Existing customer:
     *
     * Use the saved database price.
     */

    if ($customer) {

        $price =
            money(
                $customer['gallon_price']
            );


        if ($price <= 0) {

            throw new Exception(
                'Customer "' .
                $name .
                '" has no valid saved Price/Gal.'
            );

        }


        return [

            (int) $customer['customer_id'],

            $price,

            $customer['customer_name']

        ];

    }


    /*
     * New customer:
     *
     * Price must have been entered.
     */

    if (
        $priceRaw === '' ||
        !is_numeric($priceRaw) ||
        (float) $priceRaw <= 0
    ) {

        throw new Exception(
            'A new customer requires a valid Price/Gal.'
        );

    }


    $price =
        money($priceRaw);


    $stmt =
        $pdo->prepare(
            "INSERT INTO customers
                (
                    customer_name,
                    gallon_price
                )
             VALUES
                (
                    ?,
                    ?
                )"
        );


    $stmt->execute(
        [
            $name,
            $price
        ]
    );


    return [

        (int) $pdo->lastInsertId(),

        $price,

        $name

    ];

}


/*
 * =========================================================
 * FINALIZE DELIVERY DATA
 * =========================================================
 */

function normalizeDeliveriesForFinalize(
    PDO $pdo,
    array $shopDeliveries,
    array $driverDeliveries
): array {

    $allowedMethods = [

        'Cash',
        'GCash',
        'Bank Transfer',
        'Other'

    ];


    $normalized = [];

    $shopPaymentTotal = 0.00;

    $shopCount =
        count($shopDeliveries);


    $combined =
        array_merge(
            $shopDeliveries,
            $driverDeliveries
        );


    foreach (
        $combined as $index => $delivery
    ) {

        if (!is_array($delivery)) {
            continue;
        }


        $customerName =
            trim(
                (string) (
                    $delivery['customer'] ?? ''
                )
            );


        $slimRaw =
            trim(
                (string) (
                    $delivery['slim'] ?? ''
                )
            );


        $roundRaw =
            trim(
                (string) (
                    $delivery['round'] ?? ''
                )
            );


        $paymentRaw =
            trim(
                (string) (
                    $delivery['payment'] ?? ''
                )
            );


        $priceRaw =
            trim(
                (string) (
                    $delivery['price'] ?? ''
                )
            );


        $method =
            trim(
                (string) (
                    $delivery['method'] ?? 'Cash'
                )
            );


        /*
         * Ignore a completely empty row.
         */

        if (
            $customerName === '' &&
            $slimRaw === '' &&
            $roundRaw === '' &&
            $paymentRaw === '' &&
            $priceRaw === ''
        ) {
            continue;
        }


        if ($customerName === '') {

            throw new Exception(
                'Every delivery row must have a customer.'
            );

        }


        if (
            $slimRaw !== '' &&
            (
                !ctype_digit($slimRaw) ||
                (int) $slimRaw < 0
            )
        ) {

            throw new Exception(
                'Slim quantity must be a whole number.'
            );

        }


        if (
            $roundRaw !== '' &&
            (
                !ctype_digit($roundRaw) ||
                (int) $roundRaw < 0
            )
        ) {

            throw new Exception(
                'Round quantity must be a whole number.'
            );

        }


        $slim =
            $slimRaw === ''
                ? 0
                : (int) $slimRaw;


        $round =
            $roundRaw === ''
                ? 0
                : (int) $roundRaw;


        $gallons =
            $slim + $round;


        if ($gallons <= 0) {

            throw new Exception(
                'Every delivery row must contain at least one gallon.'
            );

        }


        if (
            $paymentRaw !== '' &&
            (
                !is_numeric($paymentRaw) ||
                (float) $paymentRaw < 0
            )
        ) {

            throw new Exception(
                'Invalid delivery payment.'
            );

        }


        $payment =
            $paymentRaw === ''
                ? 0.00
                : money($paymentRaw);


        if (
            !in_array(
                $method,
                $allowedMethods,
                true
            )
        ) {

            throw new Exception(
                'Invalid delivery payment method.'
            );

        }


        [
            $customerId,
            $price,
            $resolvedCustomerName
        ] =
            resolveCustomerForFinalize(
                $pdo,
                $customerName,
                $priceRaw
            );


        $amountDue =
            money(
                $gallons * $price
            );


        $isShopDelivery =
            $index < $shopCount;


        $location =
            $isShopDelivery
                ? 'Station'
                : 'Driver';


        $normalized[] = [

            'customer_id' =>
                $customerId,

            'customer_name' =>
                $resolvedCustomerName,

            'slim' =>
                $slim,

            'round' =>
                $round,

            'price' =>
                $price,

            'amount_due' =>
                $amountDue,

            'payment' =>
                $payment,

            'method' =>
                $method,

            'collection_location' =>
                $location

        ];


        if ($isShopDelivery) {

            $shopPaymentTotal +=
                $payment;

        }

    }


    return [

        'items' =>
            $normalized,

        'shop_payment_total' =>
            money(
                $shopPaymentTotal
            )

    ];

}


/*
 * =========================================================
 * ACTION: CONTEXT
 *
 * Returns:
 *
 * - current open daily record
 * - customer list
 * =========================================================
 */

function actionContext(
    PDO $pdo
): void {

    $daily =
        getOpenDailyRecord(
            $pdo
        );


    $customers =
        getCustomers(
            $pdo
        );


    jsonResponse(

        [

            'success' =>
                true,

            'daily_id' =>
                (int) $daily['daily_id'],

            'business_date' =>
                $daily['business_date'],

            'status' =>
                $daily['status'],

            'customers' =>
                $customers

        ]

    );

}


/*
 * =========================================================
 * ACTION: LOAD DRAFT
 * =========================================================
 */

function actionLoadDraft(
    PDO $pdo
): void {

    ensureDraftTable(
        $pdo
    );


    $daily =
        getOpenDailyRecord(
            $pdo
        );


    $stmt =
        $pdo->prepare(
            "SELECT
                draft_data,
                updated_at
             FROM daily_closing_drafts
             WHERE daily_id = ?
             LIMIT 1"
        );


    $stmt->execute(
        [
            $daily['daily_id']
        ]
    );


    $row =
        $stmt->fetch();


    if (!$row) {

        jsonResponse(

            [

                'success' =>
                    true,

                'has_draft' =>
                    false,

                'daily_id' =>
                    (int) $daily['daily_id'],

                'business_date' =>
                    $daily['business_date'],

                'draft' =>
                    emptyDraft()

            ]

        );

    }


    $draft =
        json_decode(
            $row['draft_data'],
            true
        );


    if (!is_array($draft)) {

        throw new Exception(
            'The saved daily draft is invalid.'
        );

    }


    jsonResponse(

        [

            'success' =>
                true,

            'has_draft' =>
                true,

            'daily_id' =>
                (int) $daily['daily_id'],

            'business_date' =>
                $daily['business_date'],

            'updated_at' =>
                $row['updated_at'],

            'draft' =>
                normalizeDraft($draft)

        ]

    );

}


/*
 * =========================================================
 * ACTION: SAVE DRAFT
 * =========================================================
 */

function actionSaveDraft(
    PDO $pdo
): void {

    $request =
        requestJson();


    $dailyId =
        (int) (
            $request['daily_id'] ?? 0
        );


    if ($dailyId <= 0) {

        throw new Exception(
            'Invalid daily record.'
        );

    }


    $daily =
        getDailyRecord(
            $pdo,
            $dailyId
        );


    if ($daily['status'] !== 'Open') {

        throw new Exception(
            'This daily record is already closed.'
        );

    }


    ensureDraftTable(
        $pdo
    );


    $draft =
        normalizeDraft(
            $request['draft'] ?? []
        );


    $json =
        json_encode(
            $draft,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    if ($json === false) {

        throw new Exception(
            'Unable to encode the daily draft.'
        );

    }


    $stmt =
        $pdo->prepare(
            "INSERT INTO daily_closing_drafts
                (
                    daily_id,
                    draft_data
                )
             VALUES
                (
                    ?,
                    ?
                )
             ON DUPLICATE KEY UPDATE
                draft_data = VALUES(draft_data),
                updated_at = CURRENT_TIMESTAMP"
        );


    $stmt->execute(
        [
            $dailyId,
            $json
        ]
    );


    jsonResponse(

        [

            'success' =>
                true,

            'daily_id' =>
                $dailyId,

            'business_date' =>
                $daily['business_date'],

            'message' =>
                'Draft saved.'

        ]

    );

}


/*
 * =========================================================
 * ACTION: SAVE CUSTOMER
 * =========================================================
 */

function actionSaveCustomer(
    PDO $pdo
): void {

    $request =
        requestJson();


    $name =
        trim(
            (string) (
                $request['customer_name'] ?? ''
            )
        );


    $price =
        $request['gallon_price'] ??
        '';


    $customer =
        saveCustomer(
            $pdo,
            $name,
            $price
        );


    jsonResponse(

        [

            'success' =>
                true,

            'customer' =>
                $customer

        ]

    );

}


/*
 * =========================================================
 * ACTION: RESET
 * =========================================================
 *
 * Deletes:
 *
 * - draft
 * - payments
 * - deliveries
 * - expenses
 * - daily sales
 *
 * The daily record itself remains OPEN.
 * =========================================================
 */

function actionReset(
    PDO $pdo
): void {

    $daily =
        getOpenDailyRecord(
            $pdo
        );


    $pdo->beginTransaction();


    try {

        ensureDraftTable(
            $pdo
        );


        $daily =
            getDailyRecord(
                $pdo,
                (int) $daily['daily_id'],
                true
            );


        if ($daily['status'] !== 'Open') {

            throw new Exception(
                'This daily record is already closed.'
            );

        }


        /*
         * Payments must be deleted first because
         * they reference deliveries.
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM payments
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [
                $daily['daily_id']
            ]
        );


        /*
         * Then deliveries.
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM deliveries
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [
                $daily['daily_id']
            ]
        );


        /*
         * Expenses.
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM expenses
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [
                $daily['daily_id']
            ]
        );


        /*
         * Daily sales.
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM daily_sales
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [
                $daily['daily_id']
            ]
        );


        /*
         * Draft.
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM daily_closing_drafts
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [
                $daily['daily_id']
            ]
        );


        $pdo->commit();


        jsonResponse(

            [

                'success' =>
                    true,

                'daily_id' =>
                    (int) $daily['daily_id'],

                'business_date' =>
                    $daily['business_date'],

                'message' =>
                    'Daily closing has been reset.'

            ]

        );

    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();

        }


        throw $e;

    }

}


/*
 * =========================================================
 * ACTION: FINALIZE
 * =========================================================
 */

function actionFinalize(
    PDO $pdo
): void {

    ensureDraftTable(
        $pdo
    );


    $request =
        requestJson();


    $dailyId =
        (int) (
            $request['daily_id'] ?? 0
        );


    if ($dailyId <= 0) {

        throw new Exception(
            'Invalid daily record.'
        );

    }


    $pdo->beginTransaction();


    try {

        /*
         * Lock daily record.
         */

        $daily =
            getDailyRecord(
                $pdo,
                $dailyId,
                true
            );


        if (
            $daily['status'] !==
            'Open'
        ) {

            throw new Exception(
                'This daily record is already closed.'
            );

        }


        /*
         * Load autosaved draft.
         */

        $stmt =
            $pdo->prepare(
                "SELECT
                    draft_data
                 FROM daily_closing_drafts
                 WHERE daily_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );


        $stmt->execute(
            [$dailyId]
        );


        $draftRow =
            $stmt->fetch();


        if (!$draftRow) {

            throw new Exception(
                'No autosaved daily draft exists. Please enter the daily information and wait for it to autosave before closing the day.'
            );

        }


        $draft =
            json_decode(
                $draftRow['draft_data'],
                true
            );


        if (!is_array($draft)) {

            throw new Exception(
                'The saved daily draft is invalid.'
            );

        }


        $draft =
            normalizeDraft(
                $draft
            );


        $businessDate =
            $daily['business_date'];


        /*
         * -----------------------------------------------------
         * SHOP MONEY
         * -----------------------------------------------------
         */

        $walkInMoney =
            money(
                $draft['walk_in_money']
            );


        /*
         * -----------------------------------------------------
         * EXPENSES
         * -----------------------------------------------------
         */

        $shopExpensesResult =
            normalizeExpenseForFinalize(
                $draft['expenses'],
                'Station'
            );


        $driverExpensesResult =
            normalizeExpenseForFinalize(
                $draft['driver']['expenses'],
                'Driver'
            );


        $shopExpenses =
            $shopExpensesResult['items'];


        $shopExpenseTotal =
            $shopExpensesResult['total'];


        $driverExpenses =
            $driverExpensesResult['items'];


        $driverExpenseTotal =
            $driverExpensesResult['total'];


        /*
         * -----------------------------------------------------
         * DELIVERIES
         * -----------------------------------------------------
         */

        $deliveryResult =
            normalizeDeliveriesForFinalize(
                $pdo,
                $draft['deliveries'],
                $draft['driver']['deliveries']
            );


        $deliveries =
            $deliveryResult['items'];


        $shopDeliveryPaymentTotal =
            $deliveryResult[
                'shop_payment_total'
            ];


        /*
         * -----------------------------------------------------
         * DRIVER MONEY
         * -----------------------------------------------------
         */

        $driverMoney =
            money(
                $draft['driver']['money_received']
            );


        /*
         * -----------------------------------------------------
         * SHOP COMPUTATION
         *
         * The existing business rule is retained:
         *
         * Shop money
         * + station expenses
         * - shop delivery payments
         *
         * = money available for walk-in sales.
         * -----------------------------------------------------
         */

        $shopBalance =
            money(
                $walkInMoney +
                $shopExpenseTotal -
                $shopDeliveryPaymentTotal
            );


        if (
            $shopBalance < -0.005
        ) {

            throw new Exception(
                'Shop balance cannot be negative.'
            );

        }


        if (
            abs($shopBalance) < 0.005
        ) {

            $shopBalance = 0.00;

        }


        $walkInCustomers =
            (int) floor(
                $shopBalance / 30.00
            );


        $walkInSales =
            money(
                $walkInCustomers * 30.00
            );


        $otherSales =
            money(
                $shopBalance -
                $walkInSales
            );


        /*
         * -----------------------------------------------------
         * DRIVER DELIVERY PAYMENTS
         * -----------------------------------------------------
         */

        $driverDeliveryPaymentTotal =
            0.00;


        foreach (
            $draft['driver']['deliveries']
            as $delivery
        ) {

            if (!is_array($delivery)) {
                continue;
            }


            $paymentRaw =
                trim(
                    (string) (
                        $delivery['payment'] ?? ''
                    )
                );


            if (
                $paymentRaw !== '' &&
                is_numeric($paymentRaw)
            ) {

                $driverDeliveryPaymentTotal +=
                    money($paymentRaw);

            }

        }


        $totalExpectedDeliveryMoney =
            money(
                $shopDeliveryPaymentTotal +
                $driverDeliveryPaymentTotal
            );


        /*
         * Driver remittance.
         */

        $effectiveReceived =
            money(
                $driverMoney +
                $driverExpenseTotal +
                $shopDeliveryPaymentTotal
            );


        $remittanceDifference =
            money(
                $effectiveReceived -
                $totalExpectedDeliveryMoney
            );


        $driverTotalSales =
            money(
                $driverMoney +
                $driverExpenseTotal
            );


        /*
         * -----------------------------------------------------
         * DAILY SALES
         * -----------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "INSERT INTO daily_sales
                    (
                        sales_date,
                        walk_in_customers,
                        walk_in_price,
                        other_shop_payment,
                        other_sales,
                        daily_id
                    )
                 VALUES
                    (
                        ?,
                        ?,
                        30.00,
                        ?,
                        ?,
                        ?
                    )
                 ON DUPLICATE KEY UPDATE

                    walk_in_customers =
                        VALUES(walk_in_customers),

                    walk_in_price =
                        VALUES(walk_in_price),

                    other_shop_payment =
                        VALUES(other_shop_payment),

                    other_sales =
                        VALUES(other_sales),

                    daily_id =
                        VALUES(daily_id)"
            );


        $stmt->execute(
            [

                $businessDate,

                $walkInCustomers,

                $walkInMoney,

                $otherSales,

                $dailyId

            ]
        );


        /*
         * -----------------------------------------------------
         * REMOVE EXISTING PERMANENT DAILY DATA
         * -----------------------------------------------------
         *
         * This makes finalization idempotent if the backend
         * ever needs to retry before the transaction commits.
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM payments
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [$dailyId]
        );


        $stmt =
            $pdo->prepare(
                "DELETE FROM deliveries
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [$dailyId]
        );


        $stmt =
            $pdo->prepare(
                "DELETE FROM expenses
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [$dailyId]
        );


        /*
         * -----------------------------------------------------
         * INSERT EXPENSES
         * -----------------------------------------------------
         */

        $expenseInsert =
            $pdo->prepare(
                "INSERT INTO expenses
                    (
                        expense_date,
                        category,
                        description,
                        amount,
                        daily_id
                    )
                 VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )"
            );


        foreach (
            $shopExpenses as $expense
        ) {

            $expenseInsert->execute(
                [

                    $businessDate,

                    $expense['category'],

                    $expense['description'],

                    $expense['amount'],

                    $dailyId

                ]
            );

        }


        foreach (
            $driverExpenses as $expense
        ) {

            $expenseInsert->execute(
                [

                    $businessDate,

                    $expense['category'],

                    '[Driver] ' .
                    $expense['description'],

                    $expense['amount'],

                    $dailyId

                ]
            );

        }


        /*
         * -----------------------------------------------------
         * INSERT DELIVERIES + PAYMENTS
         * -----------------------------------------------------
         */

        $deliveryInsert =
            $pdo->prepare(
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
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )"
            );


        $paymentInsert =
            $pdo->prepare(
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
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )"
            );


        foreach (
            $deliveries as $delivery
        ) {

            $deliveryInsert->execute(
                [

                    $delivery['customer_id'],

                    $businessDate,

                    $delivery['slim'],

                    $delivery['round'],

                    $delivery['price'],

                    $delivery['amount_due'],

                    $dailyId

                ]
            );


            $deliveryId =
                (int) $pdo->lastInsertId();


            /*
             * Do not create a zero-peso payment.
             */

            if (
                $delivery['payment'] > 0
            ) {

                $paymentInsert->execute(
                    [

                        $deliveryId,

                        $delivery['customer_id'],

                        $businessDate,

                        $delivery['payment'],

                        $delivery['method'],

                        $dailyId,

                        $delivery[
                            'collection_location'
                        ]

                    ]
                );

            }

        }


        /*
         * -----------------------------------------------------
         * CLOSE DAILY RECORD
         * -----------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "UPDATE daily_records
                 SET status = 'Closed'
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [$dailyId]
        );


        /*
         * -----------------------------------------------------
         * REMOVE DRAFT
         * -----------------------------------------------------
         */

        $stmt =
            $pdo->prepare(
                "DELETE FROM daily_closing_drafts
                 WHERE daily_id = ?"
            );


        $stmt->execute(
            [$dailyId]
        );


        $pdo->commit();


        jsonResponse(

            [

                'success' =>
                    true,

                'daily_id' =>
                    $dailyId,

                'business_date' =>
                    $businessDate,

                'walk_in_customers' =>
                    $walkInCustomers,

                'walk_in_sales' =>
                    $walkInSales,

                'other_sales' =>
                    $otherSales,

                'driver_total_sales' =>
                    $driverTotalSales,

                'expected_delivery_money' =>
                    $totalExpectedDeliveryMoney,

                'remittance_difference' =>
                    $remittanceDifference,

                'message' =>
                    'Daily record finalized and closed successfully.'

            ]

        );

    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();

        }


        throw $e;

    }

}


/*
 * =========================================================
 * ROUTER
 * =========================================================
 */

try {

    $action =
        trim(
            (string) (
                $_GET['action'] ??
                $_POST['action'] ??
                ''
            )
        );


    /*
     * -----------------------------------------------------
     * GET ACTIONS
     * -----------------------------------------------------
     */

    if (
        $_SERVER['REQUEST_METHOD'] ===
        'GET'
    ) {

        switch ($action) {

            case 'context':

                actionContext(
                    $pdo
                );

                break;


            case 'load_draft':

                actionLoadDraft(
                    $pdo
                );

                break;


            default:

                jsonFail(
                    'Unknown GET action.',
                    404
                );

        }

    }


    /*
     * -----------------------------------------------------
     * POST ACTIONS
     * -----------------------------------------------------
     */

    if (
        $_SERVER['REQUEST_METHOD'] ===
        'POST'
    ) {

        switch ($action) {

            case 'save_draft':

                actionSaveDraft(
                    $pdo
                );

                break;


            case 'save_customer':

                actionSaveCustomer(
                    $pdo
                );

                break;


            case 'reset':

                actionReset(
                    $pdo
                );

                break;


            case 'finalize':

                actionFinalize(
                    $pdo
                );

                break;


            default:

                jsonFail(
                    'Unknown POST action.',
                    404
                );

        }

    }


    /*
     * Anything other than GET/POST.
     */

    if (
        !in_array(
            $_SERVER['REQUEST_METHOD'],
            ['GET', 'POST'],
            true
        )
    ) {

        jsonFail(
            'Method not allowed.',
            405
        );

    }


} catch (Throwable $e) {

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    jsonFail(
        $e->getMessage(),
        400
    );

}