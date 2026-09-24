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

    $businessDate = $dailyRecord['business_date'];

    /*
     * Get every historical delivery that was created
     * before today's business date.
     *
     * Each delivery is treated as its own debt record.
     * Payments are matched through payments.delivery_id.
     */
    $stmt = $pdo->prepare(
        "SELECT
            d.delivery_id,
            d.customer_id,
            c.customer_name,
            d.delivery_date,
            d.amount_due,

            COALESCE(
                SUM(p.amount),
                0
            ) AS total_paid

         FROM deliveries d

         INNER JOIN customers c
            ON c.customer_id = d.customer_id

         LEFT JOIN payments p
            ON p.delivery_id = d.delivery_id

         WHERE d.delivery_date < ?

         GROUP BY
            d.delivery_id,
            d.customer_id,
            c.customer_name,
            d.delivery_date,
            d.amount_due

         ORDER BY
            d.delivery_date ASC,
            d.delivery_id ASC"
    );

    $stmt->execute([
        $businessDate
    ]);

    $deliveryRows = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
     * Build the active debt list.
     */
    $debts = [];

    $totalDebt = 0.00;


    foreach ($deliveryRows as $delivery) {

        $originalAmount = round(
            (float) $delivery['amount_due'],
            2
        );

        $totalPaid = round(
            (float) $delivery['total_paid'],
            2
        );

        $remaining = round(
            $originalAmount - $totalPaid,
            2
        );


        /*
         * Ignore fully paid deliveries.
         *
         * These will later be available through
         * the Debt History endpoint.
         */
        if ($remaining <= 0) {
            continue;
        }


        /*
         * Ignore invalid/empty delivery amounts.
         */
        if ($originalAmount <= 0) {
            continue;
        }


        $debts[] = [
            'delivery_id' =>
                (int) $delivery['delivery_id'],

            'customer_id' =>
                (int) $delivery['customer_id'],

            'customer_name' =>
                $delivery['customer_name'],

            'date_incurred' =>
                $delivery['delivery_date'],

            'original_amount' =>
                $originalAmount,

            'total_paid' =>
                $totalPaid,

            'remaining_amount' =>
                $remaining,

            'status' =>
                'Outstanding'
        ];


        $totalDebt = round(
            $totalDebt + $remaining,
            2
        );
    }


    /*
     * Today's draft deliveries.
     *
     * These have not been permanently inserted into
     * the deliveries table yet, so we display them
     * temporarily as today's outstanding debt.
     *
     * They will become permanent delivery records
     * when Finalize & Close Day is connected.
     */
    $draftShopDeliveries =
        $draft['shop']['deliveries']
        ?? [];

    $draftDriverDeliveries =
        $draft['driver']['deliveries']
        ?? [];


    $todayDraftDeliveries = array_merge(
        is_array($draftShopDeliveries)
            ? $draftShopDeliveries
            : [],
        is_array($draftDriverDeliveries)
            ? $draftDriverDeliveries
            : []
    );


    /*
     * Track today's draft debts separately.
     */
    foreach ($todayDraftDeliveries as $delivery) {

        $customerName = trim(
            (string) (
                $delivery['customer']
                ?? ''
            )
        );

        if ($customerName === '') {
            continue;
        }


        $slim = max(
            0,
            (float) (
                $delivery['slim']
                ?? 0
            )
        );

        $round = max(
            0,
            (float) (
                $delivery['round']
                ?? 0
            )
        );

        $price = max(
            0,
            (float) (
                $delivery['price']
                ?? 0
            )
        );

        $amountDue = round(
            ($slim + $round) * $price,
            2
        );


        if ($amountDue <= 0) {
            continue;
        }


        $payment = max(
            0,
            (float) (
                $delivery['payment']
                ?? 0
            )
        );


        /*
         * Today's draft payment is applied against
         * today's draft delivery.
         */
        $remaining = round(
            $amountDue - $payment,
            2
        );


        /*
         * If today's delivery has been fully paid,
         * it does not belong in Current Debt.
         */
        if ($remaining <= 0) {
            continue;
        }


        $debts[] = [
            'delivery_id' =>
                0,

            'customer_id' =>
                0,

            'customer_name' =>
                $customerName,

            'date_incurred' =>
                $businessDate,

            'original_amount' =>
                $amountDue,

            'total_paid' =>
                $payment,

            'remaining_amount' =>
                $remaining,

            'status' =>
                'Outstanding',

            'is_draft' =>
                true
        ];


        $totalDebt = round(
            $totalDebt + $remaining,
            2
        );
    }


    /*
     * Sort all outstanding debts by their
     * original debt date.
     */
    usort(
        $debts,
        function (
            array $a,
            array $b
        ): int {

            $dateCompare =
                strcmp(
                    $a['date_incurred'],
                    $b['date_incurred']
                );

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcasecmp(
                $a['customer_name'],
                $b['customer_name']
            );
        }
    );


    /*
     * Group debts by their original debt date.
     *
     * The frontend can use this to display:
     *
     * September 21, 2026
     *   Allyssa
     *   Matyline
     *
     * September 22, 2026
     *   Cloud
     */
    $groupedDebts = [];


    foreach ($debts as $debt) {

        $date = $debt['date_incurred'];

        if (!isset($groupedDebts[$date])) {
            $groupedDebts[$date] = [];
        }

        $groupedDebts[$date][] = $debt;
    }


    respond([
        'success' => true,

        'daily_id' =>
            (int) $dailyRecord['daily_id'],

        'business_date' =>
            $businessDate,

        'total_debt' =>
            round($totalDebt, 2),

        'total_credit' =>
            0.00,

        'debts' =>
            $debts,

        'grouped_debts' =>
            $groupedDebts
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
     * =========================================================
     * LOAD DAILY RECORD
     * =========================================================
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

    $dailyRecord = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$dailyRecord) {
        respond([
            'success' => false,
            'message' => 'Daily record not found.'
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
     * =========================================================
     * LOAD THE SAVED AUTOSAVE DRAFT
     * =========================================================
     */

    $stmt = $pdo->prepare(
        "SELECT
            draft_data
         FROM daily_closing_drafts
         WHERE daily_id = ?
         LIMIT 1"
    );

    $stmt->execute([
        $dailyId
    ]);

    $draftRow = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$draftRow) {
        respond([
            'success' => false,
            'message' =>
                'There is no saved Daily Closing draft to finalize.'
        ], 400);
    }

    $draft = json_decode(
        $draftRow['draft_data'],
        true
    );

    if (!is_array($draft)) {
        respond([
            'success' => false,
            'message' =>
                'The saved Daily Closing draft is invalid.'
        ], 400);
    }


    /*
     * =========================================================
     * BASIC DRAFT STRUCTURE
     * =========================================================
     */

    $shop = $draft['shop'] ?? [];
    $driver = $draft['driver'] ?? [];

    if (!is_array($shop)) {
        $shop = [];
    }

    if (!is_array($driver)) {
        $driver = [];
    }

    $shopDeliveries =
        isset($shop['deliveries']) &&
        is_array($shop['deliveries'])
            ? $shop['deliveries']
            : [];

    $driverDeliveries =
        isset($driver['deliveries']) &&
        is_array($driver['deliveries'])
            ? $driver['deliveries']
            : [];

    $shopExpenses =
        isset($shop['expenses']) &&
        is_array($shop['expenses'])
            ? $shop['expenses']
            : [];

    $driverExpenses =
        isset($driver['expenses']) &&
        is_array($driver['expenses'])
            ? $driver['expenses']
            : [];


    /*
     * =========================================================
     * CLOSING RESULT
     * =========================================================
     *
     * The frontend will eventually send this when the
     * Finalize button is connected.
     */

    $closingResult = trim(
        (string) (
            $_POST['closing_result'] ?? ''
        )
    );

    /*
    * Finalize currently does not require a manual
    * closing-result input.
    *
    * Until actual closing verification is implemented,
    * an empty result is stored as Pending.
    */
    if ($closingResult === '') {
        $closingResult = 'Pending';
    }

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
     * =========================================================
     * ACTUAL STATION CASH
     * =========================================================
     */

    $actualStationCashRaw = trim(
        (string) (
            $_POST['actual_station_cash'] ?? ''
        )
    );

    $actualStationCash = null;

    if ($actualStationCashRaw !== '') {

        if (!is_numeric($actualStationCashRaw)) {
            respond([
                'success' => false,
                'message' =>
                    'Invalid actual station cash.'
            ], 400);
        }

        $actualStationCash = round(
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
     * =========================================================
     * AUTHENTICATED USER
     * =========================================================
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


    /*
     * =========================================================
     * HELPERS
     * =========================================================
     */

    $cleanMoney = static function (
        $value
    ): float {

        if (
            $value === null ||
            $value === ''
        ) {
            return 0.00;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                'A money value is invalid.'
            );
        }

        $amount = round(
            (float) $value,
            2
        );

        if ($amount < 0) {
            throw new InvalidArgumentException(
                'Money values cannot be negative.'
            );
        }

        return $amount;
    };


    $cleanQuantity = static function (
        $value
    ): int {

        if (
            $value === null ||
            $value === ''
        ) {
            return 0;
        }

        if (
            !is_numeric($value) ||
            (float) $value < 0
        ) {
            throw new InvalidArgumentException(
                'Delivery quantities must be zero or greater.'
            );
        }

        return (int) round(
            (float) $value
        );
    };


    $cleanCustomerName = static function (
        $value
    ): string {

        $name = trim(
            (string) $value
        );

        if ($name === '') {
            throw new InvalidArgumentException(
                'Customer name is required for every delivery.'
            );
        }

        return $name;
    };


    /*
     * Resolve a customer by exact name, ignoring
     * capitalization and surrounding spaces.
     */
    $findCustomer = function (
        string $customerName
    ) use ($pdo): array {

        $stmt = $pdo->prepare(
            "SELECT
                customer_id,
                customer_name
             FROM customers
             WHERE LOWER(TRIM(customer_name))
                   = LOWER(TRIM(?))
             ORDER BY customer_id ASC"
        );

        $stmt->execute([
            $customerName
        ]);

        $customers = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (count($customers) === 0) {
            throw new InvalidArgumentException(
                'Customer "' .
                $customerName .
                '" does not exist in the Customer List.'
            );
        }

        if (count($customers) > 1) {
            throw new InvalidArgumentException(
                'Customer "' .
                $customerName .
                '" appears more than once in the Customer List.'
            );
        }

        return $customers[0];
    };


    /*
     * =========================================================
     * TRANSACTION
     * =========================================================
     */

    try {

        $pdo->beginTransaction();


        /*
         * =====================================================
         * 1. SAVE WALK-IN SALES
         * =====================================================
         */

        $walkInCustomers = $cleanQuantity(
            $shop['walk_in_customers'] ?? 0
        );

        $walkInPrice = 30.00;

        /*
         * Use the fixed ₱30 walk-in price.
         */
        $walkInSales = round(
            $walkInCustomers * $walkInPrice,
            2
        );


        /*
         * Avoid creating a meaningless record when there
         * were no walk-in customers.
         */
        if ($walkInCustomers > 0) {

            $stmt = $pdo->prepare(
                "INSERT INTO daily_sales (
                    sales_date,
                    walk_in_customers,
                    walk_in_price,
                    other_shop_payment,
                    notes,
                    daily_id,
                    other_sales
                 )
                 VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                 )"
            );

            $stmt->execute([
                $dailyRecord['business_date'],
                $walkInCustomers,
                $walkInPrice,
                0.00,
                null,
                $dailyId,
                0.00
            ]);
        }


        /*
         * =====================================================
         * 2. DELIVERY INSERT HELPER
         * =====================================================
         *
         * This handles both Station and Driver deliveries.
         */

        $insertDelivery = function (
            array $delivery,
            string $collectionLocation
        ) use (
            $pdo,
            $dailyRecord,
            $dailyId,
            $cleanMoney,
            $cleanQuantity,
            $cleanCustomerName,
            $findCustomer
        ): void {

            $customerName =
                $cleanCustomerName(
                    $delivery['customer'] ?? ''
                );

            $customer =
                $findCustomer(
                    $customerName
                );

            $customerId =
                (int) $customer['customer_id'];

            $slim =
                $cleanQuantity(
                    $delivery['slim'] ?? 0
                );

            $round =
                $cleanQuantity(
                    $delivery['round'] ?? 0
                );

            $price =
                $cleanMoney(
                    $delivery['price'] ?? 0
                );

            $payment =
                $cleanMoney(
                    $delivery['payment'] ?? 0
                );

            $method =
                trim(
                    (string) (
                        $delivery['method']
                        ?? ''
                    )
                );

            /*
             * Empty method means Cash because the UI
             * defaults to Cash.
             */
            if ($method === '') {
                $method = 'Cash';
            }

            $allowedMethods = [
                'Cash',
                'GCash',
                'Bank Transfer',
                'Other'
            ];

            if (
                !in_array(
                    $method,
                    $allowedMethods,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Invalid payment method for customer "' .
                    $customerName .
                    '".'
                );
            }

            $gallons =
                $slim + $round;

            /*
             * Ignore completely empty rows.
             */
            if (
                $gallons <= 0 &&
                $payment <= 0
            ) {
                return;
            }

            if ($gallons <= 0) {
                throw new InvalidArgumentException(
                    'Customer "' .
                    $customerName .
                    '" has a payment but no delivery quantity.'
                );
            }

            if ($price <= 0) {
                throw new InvalidArgumentException(
                    'Customer "' .
                    $customerName .
                    '" must have a valid Price/Gal.'
                );
            }

            $amountDue = round(
                $gallons * $price,
                2
            );

            /*
             * Payment may be zero for an unpaid delivery.
             * Overpayment is allowed because the existing UI
             * explicitly supports an Over balance.
             */
            if ($payment < 0) {
                throw new InvalidArgumentException(
                    'Payment cannot be negative for customer "' .
                    $customerName .
                    '".'
                );
            }

            $stmt = $pdo->prepare(
                "INSERT INTO deliveries (
                    customer_id,
                    delivery_date,
                    slim_quantity,
                    round_quantity,
                    price_per_gallon,
                    amount_due,
                    notes,
                    daily_id
                 )
                 VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                 )"
            );

            $stmt->execute([
                $customerId,
                $dailyRecord['business_date'],
                $slim,
                $round,
                $price,
                $amountDue,
                null,
                $dailyId
            ]);

            $deliveryId =
                (int) $pdo->lastInsertId();


            /*
             * Save an actual payment only when money
             * was received.
             */
            if ($payment > 0) {

                $stmt = $pdo->prepare(
                    "INSERT INTO payments (
                        delivery_id,
                        customer_id,
                        payment_date,
                        amount,
                        payment_method,
                        collection_location,
                        notes,
                        daily_id
                     )
                     VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                     )"
                );

                $stmt->execute([
                    $deliveryId,
                    $customerId,
                    $dailyRecord['business_date'],
                    $payment,
                    $method,
                    $collectionLocation,
                    null,
                    $dailyId
                ]);
            }
        };


        /*
         * =====================================================
         * 3. SAVE SHOP DELIVERIES
         * =====================================================
         */

        foreach (
            $shopDeliveries
            as $delivery
        ) {

            if (!is_array($delivery)) {
                continue;
            }

            $insertDelivery(
                $delivery,
                'Station'
            );
        }


        /*
         * =====================================================
         * 4. SAVE DRIVER DELIVERIES
         * =====================================================
         */

        foreach (
            $driverDeliveries
            as $delivery
        ) {

            if (!is_array($delivery)) {
                continue;
            }

            $insertDelivery(
                $delivery,
                'Driver'
            );
        }


        /*
         * =====================================================
         * 5. SAVE SHOP EXPENSES
         * =====================================================
         */

        $insertExpense = function (
            array $expense,
            string $location
        ) use (
            $pdo,
            $dailyRecord,
            $dailyId,
            $cleanMoney
        ): void {

            $category =
                trim(
                    (string) (
                        $expense['category']
                        ?? ''
                    )
                );

            $amount =
                $cleanMoney(
                    $expense['amount'] ?? 0
                );

            $description =
                trim(
                    (string) (
                        $expense['name']
                        ?? ''
                    )
                );

            /*
             * Ignore completely empty expense rows.
             */
            if (
                $category === '' &&
                $amount <= 0 &&
                $description === ''
            ) {
                return;
            }

            $allowedCategories = [
                'Gas',
                'Food',
                'Cash Advance',
                'Miscellaneous',
                'Others'
            ];

            /*
             * The current UI contains "Others", while
             * the database uses "Miscellaneous".
             */
            if ($category === 'Others') {
                $category = 'Miscellaneous';
            }

            if (
                !in_array(
                    $category,
                    $allowedCategories,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Invalid expense category.'
                );
            }

            if ($amount <= 0) {
                throw new InvalidArgumentException(
                    'Expense amount must be greater than zero.'
                );
            }

            $stmt = $pdo->prepare(
                "INSERT INTO expenses (
                    expense_date,
                    category,
                    expense_location,
                    description,
                    amount,
                    notes,
                    daily_id
                 )
                 VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                 )"
            );

            $stmt->execute([
                $dailyRecord['business_date'],
                $category,
                $location,
                $description !== ''
                    ? $description
                    : null,
                $amount,
                null,
                $dailyId
            ]);
        };


        foreach (
            $shopExpenses
            as $expense
        ) {

            if (!is_array($expense)) {
                continue;
            }

            $insertExpense(
                $expense,
                'Station'
            );
        }


        /*
         * =====================================================
         * 6. SAVE DRIVER EXPENSES
         * =====================================================
         */

        foreach (
            $driverExpenses
            as $expense
        ) {

            if (!is_array($expense)) {
                continue;
            }

            $insertExpense(
                $expense,
                'Driver'
            );
        }


        /*
         * =====================================================
         * 7. FINALIZE DAILY RECORD
         * =====================================================
         */

        $stmt = $pdo->prepare(
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
         * =====================================================
         * 8. DELETE TEMPORARY DRAFT
         * =====================================================
         *
         * This is deliberately last.
         *
         * If anything above fails, the transaction rolls back
         * and the draft remains available.
         */

        $stmt = $pdo->prepare(
            "DELETE FROM daily_closing_drafts
             WHERE daily_id = ?"
        );

        $stmt->execute([
            $dailyId
        ]);


        /*
         * =====================================================
         * COMMIT
         * =====================================================
         */

        $pdo->commit();


        respond([
            'success' => true,
            'action' => 'finalize',
            'daily_id' => $dailyId,
            'business_date' =>
                $dailyRecord['business_date'],
            'status' => 'Saved',
            'closing_result' => $closingResult,
            'actual_station_cash' =>
                $actualStationCash,
            'saved_at' =>
                date('Y-m-d H:i:s'),
            'saved_by' => $savedBy
        ]);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        respond([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
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