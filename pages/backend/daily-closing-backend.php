<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../auth/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');


/*
 * =============================================================
 * RESPONSE HELPER
 * =============================================================
 */

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


/*
 * =============================================================
 * DAILY CLOSING SERVICE
 * =============================================================
 */

final class DailyClosingService
{
    private PDO $pdo;

    private const MONEY_TOLERANCE = 0.009;


    /*
     * ---------------------------------------------------------
     * CONSTRUCTOR
     * ---------------------------------------------------------
     */

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    /*
     * ---------------------------------------------------------
     * MONEY
     * ---------------------------------------------------------
     */

    private function money($value): float
    {
        if (
            $value === null ||
            $value === '' ||
            $value === '---' ||
            $value === '—' ||
            $value === '–'
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
    }


    /*
     * ---------------------------------------------------------
     * QUANTITY
     * ---------------------------------------------------------
     */

    private function quantity($value): int
    {
        if (
            $value === null ||
            $value === '' ||
            $value === '---' ||
            $value === '—' ||
            $value === '–'
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
    }


    /*
     * ---------------------------------------------------------
     * CUSTOMER NAME
     * ---------------------------------------------------------
     */

    private function customerName($value): string
    {
        $name = trim(
            (string) $value
        );

        if ($name === '') {
            throw new InvalidArgumentException(
                'Customer name is required for every delivery.'
            );
        }

        return $name;
    }


    /*
     * ---------------------------------------------------------
     * CUSTOMER
     * ---------------------------------------------------------
     */

    private function findCustomer(
        string $customerName
    ): array {

        $stmt = $this->pdo->prepare(
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

        $customers =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    }


    /*
     * ---------------------------------------------------------
     * ENSURE DRAFT TABLE
     * ---------------------------------------------------------
     */

    public function ensureDraftTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS daily_closing_drafts (
                draft_id INT NOT NULL AUTO_INCREMENT,
                daily_id INT NOT NULL,
                draft_data LONGTEXT NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (draft_id),

                UNIQUE KEY uq_daily_closing_draft_daily_id (
                    daily_id
                ),

                KEY idx_daily_closing_draft_daily_id (
                    daily_id
                ),

                CONSTRAINT fk_daily_closing_draft_daily
                    FOREIGN KEY (daily_id)
                    REFERENCES daily_records (daily_id)
                    ON DELETE CASCADE

            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci"
        );
    }


    /*
     * ---------------------------------------------------------
     * ENSURE CREDIT TABLE
     * ---------------------------------------------------------
     *
     * IMPORTANT:
     *
     * customer_credits is treated as a ledger.
     *
     * Positive amount:
     *     credit added
     *
     * Negative amount:
     *     credit consumed
     *
     * Example:
     *
     *     +140
     *      -70
     *     -----
     *      +70 available
     */

    public function ensureCreditTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS customer_credits (
                credit_id INT NOT NULL AUTO_INCREMENT,
                customer_id INT NOT NULL,
                credit_date DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
                collection_location VARCHAR(50) NOT NULL DEFAULT 'Station',
                daily_id INT NULL,
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (credit_id),

                KEY idx_customer_credits_customer_id (
                    customer_id
                ),

                KEY idx_customer_credits_credit_date (
                    credit_date
                ),

                KEY idx_customer_credits_daily_id (
                    daily_id
                ),

                CONSTRAINT fk_customer_credits_customer
                    FOREIGN KEY (customer_id)
                    REFERENCES customers (customer_id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_customer_credits_daily
                    FOREIGN KEY (daily_id)
                    REFERENCES daily_records (daily_id)
                    ON DELETE SET NULL

            ) ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_0900_ai_ci"
        );
    }


    /*
     * ---------------------------------------------------------
     * GET DAILY RECORD
     * ---------------------------------------------------------
     */

    private function getDailyRecord(
        int $dailyId = 0
    ): ?array {

        if ($dailyId > 0) {

            $stmt = $this->pdo->prepare(
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

        } else {

            $stmt = $this->pdo->query(
                "SELECT
                    daily_id,
                    business_date,
                    status
                 FROM daily_records
                 ORDER BY business_date DESC, daily_id DESC
                 LIMIT 1"
            );
        }

        $record =
            $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }


    /*
     * ---------------------------------------------------------
     * GET OPEN DAILY RECORD
     * ---------------------------------------------------------
     */

    public function getOpenDailyRecord(
        int $dailyId = 0
    ): ?array {

        if ($dailyId > 0) {

            $stmt = $this->pdo->prepare(
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

            $stmt = $this->pdo->query(
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
            $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }


    /*
     * =========================================================
     * CUSTOMER LIST
     * =========================================================
     */

    public function getCustomers(): void
    {
        $stmt = $this->pdo->query(
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
            'customers' =>
                $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }


    /*
     * =========================================================
     * CUSTOMER PRICE
     * =========================================================
     */

    public function saveCustomerPrice(): void
    {
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

        $stmt = $this->pdo->prepare(
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
            $stmt->fetch(PDO::FETCH_ASSOC);

        if ($customer) {

            $stmt = $this->pdo->prepare(
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

        $stmt = $this->pdo->prepare(
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
                (int) $this->pdo->lastInsertId(),
            'customer_name' =>
                $customerName,
            'gallon_price' =>
                $price
        ]);
    }


    /*
     * =========================================================
     * GET TODAY'S DRAFT DELIVERIES
     * =========================================================
     */

    private function getDraftDeliveries(
        array $draft
    ): array {

        $shop =
            is_array($draft['shop'] ?? null)
                ? $draft['shop']
                : [];

        $driver =
            is_array($draft['driver'] ?? null)
                ? $draft['driver']
                : [];

        $shopDeliveries =
            is_array($shop['deliveries'] ?? null)
                ? $shop['deliveries']
                : [];

        $driverDeliveries =
            is_array($driver['deliveries'] ?? null)
                ? $driver['deliveries']
                : [];

        return array_merge(
            $shopDeliveries,
            $driverDeliveries
        );
    }


    /*
     * =========================================================
     * LOAD HISTORICAL DEBT
     * =========================================================
     */

    private function loadHistoricalDebts(
        string $businessDate
    ): array {

        $stmt = $this->pdo->prepare(
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

        $rows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        $debts = [];
        $buckets = [];

        foreach ($rows as $row) {

            $original =
                round(
                    (float) $row['amount_due'],
                    2
                );

            $paid =
                round(
                    (float) $row['total_paid'],
                    2
                );

            $remaining =
                round(
                    $original - $paid,
                    2
                );

            if ($original <= 0) {
                continue;
            }

            if ($remaining <= self::MONEY_TOLERANCE) {
                continue;
            }

            $index =
                count($debts);

            $debts[] = [

                'delivery_id' =>
                    (int) $row['delivery_id'],

                'customer_id' =>
                    (int) $row['customer_id'],

                'customer_name' =>
                    $row['customer_name'],

                'date_incurred' =>
                    $row['delivery_date'],

                'original_amount' =>
                    $original,

                'total_paid' =>
                    $paid,

                'remaining_amount' =>
                    $remaining,

                'draft_payment' =>
                    0.00,

                'status' =>
                    'Outstanding',

                'is_draft' =>
                    false
            ];

            $customerId =
                (int) $row['customer_id'];

            if (!isset($buckets[$customerId])) {
                $buckets[$customerId] = [];
            }

            $buckets[$customerId][] = [
                'type' => 'historical',
                'index' => $index
            ];
        }

        return [
            'debts' => $debts,
            'buckets' => $buckets
        ];
    }


    /*
     * =========================================================
     * LOAD SAVED CUSTOMER CREDIT
     * =========================================================
     */

    private function loadCreditBalances(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                cc.customer_id,
                c.customer_name,
                SUM(cc.amount) AS total_credit

             FROM customer_credits cc

             INNER JOIN customers c
                ON c.customer_id = cc.customer_id

             GROUP BY
                cc.customer_id,
                c.customer_name

             HAVING
                SUM(cc.amount) > 0.009

             ORDER BY
                c.customer_name ASC"
        );

        $rows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        $balances = [];
        $names = [];

        foreach ($rows as $row) {

            $customerId =
                (int) $row['customer_id'];

            $amount =
                round(
                    (float) $row['total_credit'],
                    2
                );

            if ($amount <= self::MONEY_TOLERANCE) {
                continue;
            }

            $balances[$customerId] =
                $amount;

            $names[$customerId] =
                $row['customer_name'];
        }

        return [
            'balances' => $balances,
            'names' => $names
        ];
    }


    /*
     * =========================================================
     * BUILD CUSTOMER LOOKUP
     * =========================================================
     */

    private function buildCustomerLookup(
        array $deliveries
    ): array {

        $lookup = [];

        foreach ($deliveries as $delivery) {

            if (!is_array($delivery)) {
                continue;
            }

            $name =
                trim(
                    (string) (
                        $delivery['customer']
                        ?? ''
                    )
                );

            if ($name === '') {
                continue;
            }

            $customerId =
                (int) (
                    $delivery['customer_id']
                    ?? $delivery['customerId']
                    ?? 0
                );

            if ($customerId > 0) {

                $lookup[$name] =
                    $customerId;

                continue;
            }

            if (isset($lookup[$name])) {
                continue;
            }

            $stmt = $this->pdo->prepare(
                "SELECT
                    customer_id
                 FROM customers
                 WHERE LOWER(TRIM(customer_name)) =
                       LOWER(TRIM(?))
                 LIMIT 1"
            );

            $stmt->execute([
                $name
            ]);

            $customer =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {

                $lookup[$name] =
                    (int) $customer['customer_id'];
            }
        }

        return $lookup;
    }


    /*
     * =========================================================
     * CURRENT DEBT / CREDIT PREVIEW
     * =========================================================
     */

    public function getCurrentDebt(
        int $dailyId,
        array $draft
    ): void {

        $dailyRecord =
            $this->getDailyRecord($dailyId);

        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'No daily record is available.'
            ], 404);
        }

        $businessDate =
            $dailyRecord['business_date'];


        /*
         * -----------------------------------------------------
         * HISTORICAL DEBT
         * -----------------------------------------------------
         */

        $historical =
            $this->loadHistoricalDebts(
                $businessDate
            );

        $historicalDebts =
            $historical['debts'];

        $customerDebtBuckets =
            $historical['buckets'];


        /*
         * -----------------------------------------------------
         * TODAY'S DELIVERIES
         * -----------------------------------------------------
         */

        $todayDeliveries =
            $this->getDraftDeliveries(
                $draft
            );


        /*
         * -----------------------------------------------------
         * CUSTOMER LOOKUP
         * -----------------------------------------------------
         */

        $customerLookup =
            $this->buildCustomerLookup(
                $todayDeliveries
            );


        /*
         * -----------------------------------------------------
         * SAVED CREDIT POOL
         * -----------------------------------------------------
         */

        $creditData =
            $this->loadCreditBalances();

        $availableCredits =
            $creditData['balances'];

        $originalCredits =
            $creditData['balances'];

        $creditCustomerNames =
            $creditData['names'];


        /*
         * -----------------------------------------------------
         * DRAFT CREDIT
         * -----------------------------------------------------
         */

        $draftCredits = [];

        $draftCreditUsed = [];


        /*
         * -----------------------------------------------------
         * TODAY'S DRAFT DEBT
         * -----------------------------------------------------
         */

        $draftDebts = [];


        /*
         * -----------------------------------------------------
         * PROCESS EACH DELIVERY
         * -----------------------------------------------------
         *
         * IMPORTANT ORDER:
         *
         * 1. Current delivery payment
         * 2. Existing credit for unpaid current delivery
         * 3. Add current delivery to debt buckets
         * 4. Excess payment pays older debt
         * 5. Remaining excess creates credit
         *
         * This allows an earlier overpayment to become available
         * to a later delivery on the same day.
         */

        foreach ($todayDeliveries as $delivery) {

            if (!is_array($delivery)) {
                continue;
            }

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

            $customerId =
                (int) (
                    $delivery['customer_id']
                    ?? $delivery['customerId']
                    ?? 0
                );

            if ($customerId <= 0) {

                $customerId =
                    (int) (
                        $customerLookup[$customerName]
                        ?? 0
                    );
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

            $amountDue =
                round(
                    ($slim + $round) * $price,
                    2
                );

            if ($amountDue <= 0) {
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

            $paymentRemaining =
                round(
                    $payment,
                    2
                );


            /*
             * ================================================
             * 1. PAY CURRENT DELIVERY
             * ================================================
             */

            $currentPayment =
                min(
                    $paymentRemaining,
                    $amountDue
                );

            $currentPayment =
                round(
                    $currentPayment,
                    2
                );

            $paymentRemaining =
                round(
                    $paymentRemaining -
                    $currentPayment,
                    2
                );

            $currentRemaining =
                round(
                    $amountDue -
                    $currentPayment,
                    2
                );


            /*
             * ================================================
             * 2. USE EXISTING / DRAFT CREDIT
             * ================================================
             *
             * Credit can cover an unpaid current delivery.
             *
             * Example:
             *
             * Credit = 140
             * Delivery = 70
             * Payment = 0
             *
             * Result:
             *
             * Credit = 70
             * Debt = 0
             */

            if (
                $currentRemaining >
                self::MONEY_TOLERANCE
                &&
                $customerId > 0
            ) {

                $availableCredit =
                    round(
                        (float) (
                            $availableCredits[$customerId]
                            ?? 0
                        ),
                        2
                    );

                if (
                    $availableCredit >
                    self::MONEY_TOLERANCE
                ) {

                    $creditUsed =
                        min(
                            $currentRemaining,
                            $availableCredit
                        );

                    $creditUsed =
                        round(
                            $creditUsed,
                            2
                        );

                    if (
                        $creditUsed >
                        self::MONEY_TOLERANCE
                    ) {

                        $currentRemaining =
                            round(
                                $currentRemaining -
                                $creditUsed,
                                2
                            );

                        $availableCredits[$customerId] =
                            round(
                                $availableCredit -
                                $creditUsed,
                                2
                            );

                        if (
                            !isset(
                                $draftCreditUsed[$customerId]
                            )
                        ) {
                            $draftCreditUsed[$customerId] =
                                0.00;
                        }

                        $draftCreditUsed[$customerId] =
                            round(
                                $draftCreditUsed[$customerId] +
                                $creditUsed,
                                2
                            );
                    }
                }
            }


            /*
             * ================================================
             * ADD CURRENT DELIVERY TO DEBT BUCKET
             * ================================================
             */

            $draftDebtIndex =
                count($draftDebts);

            $draftDebts[] = [

                'delivery_id' =>
                    0,

                'customer_id' =>
                    $customerId,

                'customer_name' =>
                    $customerName,

                'date_incurred' =>
                    $businessDate,

                'original_amount' =>
                    $amountDue,

                'total_paid' =>
                    $currentPayment,

                'remaining_amount' =>
                    $currentRemaining,

                'status' =>
                    'Outstanding',

                'is_draft' =>
                    true
            ];

            if ($customerId > 0) {

                if (
                    !isset(
                        $customerDebtBuckets[$customerId]
                    )
                ) {
                    $customerDebtBuckets[$customerId] = [];
                }

                $customerDebtBuckets[$customerId][] = [
                    'type' =>
                        'draft',

                    'index' =>
                        $draftDebtIndex
                ];
            }


            /*
             * ================================================
             * 3. EXCESS PAYMENT → OLDEST DEBT
             * ================================================
             */

            if (
                $paymentRemaining >
                self::MONEY_TOLERANCE
                &&
                $customerId > 0
                &&
                isset(
                    $customerDebtBuckets[$customerId]
                )
            ) {

                foreach (
                    $customerDebtBuckets[$customerId]
                    as $bucket
                ) {

                    if (
                        $paymentRemaining <=
                        self::MONEY_TOLERANCE
                    ) {
                        break;
                    }

                    $bucketRemaining =
                        0.00;

                    if (
                        $bucket['type'] ===
                        'historical'
                    ) {

                        $bucketIndex =
                            $bucket['index'];

                        $bucketRemaining =
                            $historicalDebts[
                                $bucketIndex
                            ]['remaining_amount'];

                    } else {

                        $bucketIndex =
                            $bucket['index'];

                        $bucketRemaining =
                            $draftDebts[
                                $bucketIndex
                            ]['remaining_amount'];
                    }

                    if (
                        $bucketRemaining <=
                        self::MONEY_TOLERANCE
                    ) {
                        continue;
                    }

                    $debtPayment =
                        min(
                            $paymentRemaining,
                            $bucketRemaining
                        );

                    $debtPayment =
                        round(
                            $debtPayment,
                            2
                        );

                    if (
                        $debtPayment <=
                        self::MONEY_TOLERANCE
                    ) {
                        continue;
                    }

                    if (
                        $bucket['type'] ===
                        'historical'
                    ) {

                        $historicalDebts[
                            $bucketIndex
                        ]['total_paid'] =
                            round(
                                $historicalDebts[
                                    $bucketIndex
                                ]['total_paid']
                                + $debtPayment,
                                2
                            );

                        $historicalDebts[
                            $bucketIndex
                        ]['remaining_amount'] =
                            round(
                                $historicalDebts[
                                    $bucketIndex
                                ]['remaining_amount']
                                - $debtPayment,
                                2
                            );

                        $historicalDebts[
                            $bucketIndex
                        ]['draft_payment'] =
                            round(
                                $historicalDebts[
                                    $bucketIndex
                                ]['draft_payment']
                                + $debtPayment,
                                2
                            );

                    } else {

                        $draftDebts[
                            $bucketIndex
                        ]['total_paid'] =
                            round(
                                $draftDebts[
                                    $bucketIndex
                                ]['total_paid']
                                + $debtPayment,
                                2
                            );

                        $draftDebts[
                            $bucketIndex
                        ]['remaining_amount'] =
                            round(
                                $draftDebts[
                                    $bucketIndex
                                ]['remaining_amount']
                                - $debtPayment,
                                2
                            );
                    }

                    $paymentRemaining =
                        round(
                            $paymentRemaining -
                            $debtPayment,
                            2
                        );
                }
            }


            /*
             * ================================================
             * 4. REMAINING EXCESS → DRAFT CREDIT
             * ================================================
             */

            if (
                $paymentRemaining >
                self::MONEY_TOLERANCE
                &&
                $customerId > 0
            ) {

                if (
                    !isset(
                        $draftCredits[$customerId]
                    )
                ) {

                    $draftCredits[$customerId] = [

                        'customer_id' =>
                            $customerId,

                        'customer_name' =>
                            $customerName,

                        'draft_credit' =>
                            0.00
                    ];
                }

                $draftCredits[$customerId][
                    'draft_credit'
                ] =
                    round(
                        $draftCredits[$customerId][
                            'draft_credit'
                        ]
                        + $paymentRemaining,
                        2
                    );


                /*
                 * Make the newly-created draft credit
                 * immediately available to later deliveries.
                 */

                $availableCredits[$customerId] =
                    round(
                        (
                            $availableCredits[$customerId]
                            ?? 0
                        )
                        + $paymentRemaining,
                        2
                    );

                $creditCustomerNames[$customerId] =
                    $customerName;
            }
        }


        /*
         * -----------------------------------------------------
         * BUILD DEBT RESPONSE
         * -----------------------------------------------------
         */

        $debts = [];
        $totalDebt = 0.00;


        foreach ($historicalDebts as $debt) {

            $remaining =
                round(
                    (float) $debt['remaining_amount'],
                    2
                );

            $draftPayment =
                round(
                    (float) (
                        $debt['draft_payment']
                        ?? 0
                    ),
                    2
                );

            if (
                $remaining <=
                self::MONEY_TOLERANCE
                &&
                $draftPayment <=
                self::MONEY_TOLERANCE
            ) {
                continue;
            }

            $debt['remaining_amount'] =
                max(
                    0.00,
                    $remaining
                );

            $debt['draft_payment'] =
                $draftPayment;

            $debt['is_draft_paid'] =
                (
                    $remaining <=
                    self::MONEY_TOLERANCE
                    &&
                    $draftPayment >
                    self::MONEY_TOLERANCE
                );

            $debts[] =
                $debt;

            $totalDebt =
                round(
                    $totalDebt +
                    $remaining,
                    2
                );
        }


        foreach ($draftDebts as $debt) {

            $remaining =
                round(
                    (float) $debt['remaining_amount'],
                    2
                );

            if (
                $remaining <=
                self::MONEY_TOLERANCE
            ) {
                continue;
            }

            $debt['remaining_amount'] =
                $remaining;

            $debts[] =
                $debt;

            $totalDebt =
                round(
                    $totalDebt +
                    $remaining,
                    2
                );
        }


        /*
         * -----------------------------------------------------
         * BUILD CREDIT RESPONSE
         * -----------------------------------------------------
         */

        $creditsByCustomer = [];


        /*
         * Existing credit
         */

        foreach (
            $originalCredits
            as $customerId =>
            $originalCredit
        ) {

            $currentCredit =
                round(
                    (float) (
                        $availableCredits[$customerId]
                        ?? 0
                    ),
                    2
                );

            $used =
                round(
                    (float) (
                        $draftCreditUsed[$customerId]
                        ?? 0
                    ),
                    2
                );

            /*
             * Positive draft credit means today's activity
             * increased the customer's credit.
             *
             * A reduction is represented separately through
             * draft_credit_used.
             */

            $draftCredit =
                round(
                    max(
                        0,
                        $currentCredit -
                        $originalCredit
                    ),
                    2
                );

            $creditsByCustomer[$customerId] = [

                'customer_id' =>
                    (int) $customerId,

                'customer_name' =>
                    $creditCustomerNames[$customerId]
                    ?? 'Unknown Customer',

                'original_credit' =>
                    round(
                        $originalCredit,
                        2
                    ),

                'draft_credit' =>
                    $draftCredit,

                'draft_credit_used' =>
                    $used,

                'total_credit' =>
                    $currentCredit,

                'is_draft_credit' =>
                    (
                        $draftCredit >
                        self::MONEY_TOLERANCE
                        ||
                        $used >
                        self::MONEY_TOLERANCE
                    )
            ];
        }


        /*
         * New draft-only credit
         */

        foreach (
            $draftCredits
            as $customerId =>
            $draftCredit
        ) {

            $currentCredit =
                round(
                    (float) (
                        $availableCredits[$customerId]
                        ?? 0
                    ),
                    2
                );

            $used =
                round(
                    (float) (
                        $draftCreditUsed[$customerId]
                        ?? 0
                    ),
                    2
                );

            if (
                isset(
                    $creditsByCustomer[$customerId]
                )
            ) {

                $creditsByCustomer[$customerId][
                    'draft_credit'
                ] =
                    round(
                        max(
                            0,
                            $currentCredit -
                            $creditsByCustomer[$customerId][
                                'original_credit'
                            ]
                        ),
                        2
                    );

                $creditsByCustomer[$customerId][
                    'draft_credit_used'
                ] =
                    $used;

                $creditsByCustomer[$customerId][
                    'total_credit'
                ] =
                    $currentCredit;

                $creditsByCustomer[$customerId][
                    'is_draft_credit'
                ] = true;

            } else {

                $creditsByCustomer[$customerId] = [

                    'customer_id' =>
                        (int) $customerId,

                    'customer_name' =>
                        $draftCredit['customer_name'],

                    'original_credit' =>
                        0.00,

                    'draft_credit' =>
                        $currentCredit,

                    'draft_credit_used' =>
                        $used,

                    'total_credit' =>
                        $currentCredit,

                    'is_draft_credit' =>
                        true
                ];
            }
        }


        /*
         * Keep a zero-credit row if today's draft consumed
         * the customer's previous credit.
         *
         * This lets the frontend show:
         *
         *     ₱140 → ₱0
         */

        foreach (
            $creditsByCustomer
            as $customerId =>
            $credit
        ) {

            $current =
                round(
                    (float) $credit['total_credit'],
                    2
                );
            /*
            * Do not show a zero-credit row when the credit was created
            * and completely consumed within today's draft.
            *
            * Example:
            *
            *     Today's overpayment   +₱140
            *     Today's later debt    -₱140
            *     Remaining credit       ₱0
            *
            * There is no actual customer credit to display.
            *
            * However, if the customer had existing credit before today,
            * keep the row so the UI can show:
            *
            *     ₱140 → ₱0
            */

            foreach (
                $creditsByCustomer
                as $customerId =>
                $credit
            ) {

                $current =
                    round(
                        (float) (
                            $credit['total_credit']
                            ?? 0
                        ),
                        2
                    );

                $original =
                    round(
                        (float) (
                            $credit['original_credit']
                            ?? 0
                        ),
                        2
                    );

                /*
                * No existing credit + no remaining credit:
                * remove the row completely.
                */
                if (
                    $original <=
                    self::MONEY_TOLERANCE
                    &&
                    $current <=
                    self::MONEY_TOLERANCE
                ) {

                    unset(
                        $creditsByCustomer[$customerId]
                    );

                    continue;
                }

                /*
                * Existing credit that was completely consumed:
                *
                *     ₱140 → ₱0
                *
                * remains visible.
                */
            }
        }


        $credits =
            array_values(
                $creditsByCustomer
            );


        usort(
            $credits,
            function (
                array $a,
                array $b
            ): int {

                return strcasecmp(
                    $a['customer_name'],
                    $b['customer_name']
                );
            }
        );


        $totalCredit =
            0.00;

        foreach ($credits as $credit) {

            $totalCredit =
                round(
                    $totalCredit +
                    (float) $credit['total_credit'],
                    2
                );
        }


        /*
         * -----------------------------------------------------
         * SORT DEBT
         * -----------------------------------------------------
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
         * -----------------------------------------------------
         * GROUP DEBT BY DATE
         * -----------------------------------------------------
         */

        $groupedDebts = [];

        foreach ($debts as $debt) {

            $date =
                $debt['date_incurred'];

            if (!isset($groupedDebts[$date])) {
                $groupedDebts[$date] = [];
            }

            $groupedDebts[$date][] =
                $debt;
        }


        respond([

            'success' =>
                true,

            'daily_id' =>
                (int) $dailyRecord['daily_id'],

            'business_date' =>
                $businessDate,

            'total_debt' =>
                round(
                    $totalDebt,
                    2
                ),

            'total_credit' =>
                round(
                    $totalCredit,
                    2
                ),

            'credits' =>
                $credits,

            'debts' =>
                $debts,

            'grouped_debts' =>
                $groupedDebts
        ]);
    }


    /*
     * =========================================================
     * DAILY CONTEXT
     * =========================================================
     */

    public function getDailyContext(): void
    {
        $today =
            date('Y-m-d');

        $stmt = $this->pdo->prepare(
            "SELECT
                daily_id,
                business_date,
                status,
                closing_result,
                actual_station_cash,
                saved_at
             FROM daily_records
             WHERE business_date = ?
             LIMIT 1"
        );

        $stmt->execute([
            $today
        ]);

        $dailyRecord =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'No daily record exists for today.'
            ], 404);
        }

        respond([
            'success' => true,

            'daily_id' =>
                (int) $dailyRecord['daily_id'],

            'business_date' =>
                $dailyRecord['business_date'],

            'status' =>
                $dailyRecord['status'],

            'closing_result' =>
                $dailyRecord['closing_result'],

            'actual_station_cash' =>
                $dailyRecord['actual_station_cash'] !== null
                    ? (float) $dailyRecord['actual_station_cash']
                    : null,

            'saved_at' =>
                $dailyRecord['saved_at']
        ]);
    }


    /*
     * =========================================================
     * LOAD DAILY CLOSING
     * =========================================================
     */

    public function loadDailyClosing(
        int $requestedDailyId
    ): void {

        $dailyRecord =
            $this->getOpenDailyRecord(
                $requestedDailyId
            );


        /*
         * -----------------------------------------------------
         * CREATE TODAY'S OPEN RECORD
         * -----------------------------------------------------
         */

        if (!$dailyRecord) {

            $today =
                date('Y-m-d');

            $stmt =
                $this->pdo->prepare(
                    "SELECT
                        daily_id,
                        business_date,
                        status
                     FROM daily_records
                     WHERE business_date = ?
                     LIMIT 1"
                );

            $stmt->execute([
                $today
            ]);

            $todayRecord =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$todayRecord) {

                $stmt =
                    $this->pdo->prepare(
                        "INSERT INTO daily_records (
                            business_date,
                            status,
                            closing_result
                         )
                         VALUES (
                            ?,
                            'Open',
                            'Pending'
                         )"
                    );

                $stmt->execute([
                    $today
                ]);

                $newDailyId =
                    (int) $this->pdo->lastInsertId();

                $dailyRecord =
                    $this->getOpenDailyRecord(
                        $newDailyId
                    );

            } else {

                $dailyRecord = null;
            }
        }


        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'No open daily record is available.'
            ], 404);
        }


        /*
         * -----------------------------------------------------
         * LOAD AUTOSAVE DRAFT
         * -----------------------------------------------------
         */

        $stmt =
            $this->pdo->prepare(
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
            $stmt->fetch(PDO::FETCH_ASSOC);


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


    /*
     * =========================================================
     * SAVE AUTOSAVE DRAFT
     * =========================================================
     */

    public function saveDraft(
        int $dailyId
    ): void {

        $dailyRecord =
            $this->getOpenDailyRecord(
                $dailyId
            );

        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'The selected daily record is not open.'
            ], 409);
        }

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
            $this->pdo->prepare(
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


    /*
     * =========================================================
     * RESET
     * =========================================================
     */

    public function resetDraft(
        int $dailyId
    ): void {

        $dailyRecord =
            $this->getOpenDailyRecord(
                $dailyId
            );

        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'The selected daily record is not open.'
            ], 409);
        }

        $stmt =
            $this->pdo->prepare(
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


    /*
     * =========================================================
     * INSERT PAYMENT
     * =========================================================
     */

    private function insertPayment(
        int $deliveryId,
        int $customerId,
        string $paymentDate,
        float $amount,
        string $method,
        string $location,
        ?string $notes,
        int $dailyId
    ): void {

        if ($amount <= self::MONEY_TOLERANCE) {
            return;
        }

        $stmt =
            $this->pdo->prepare(
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
            $paymentDate,
            $amount,
            $method,
            $location,
            $notes,
            $dailyId
        ]);
    }


    /*
     * =========================================================
     * INSERT CREDIT LEDGER ENTRY
     * =========================================================
     */

    private function insertCreditEntry(
        int $customerId,
        string $date,
        float $amount,
        string $method,
        string $location,
        int $dailyId,
        string $notes
    ): void {

        if (
            abs($amount) <=
            self::MONEY_TOLERANCE
        ) {
            return;
        }

        $stmt =
            $this->pdo->prepare(
                "INSERT INTO customer_credits (
                    customer_id,
                    credit_date,
                    amount,
                    payment_method,
                    collection_location,
                    daily_id,
                    notes
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
            $customerId,
            $date,
            $amount,
            $method,
            $location,
            $dailyId,
            $notes
        ]);
    }


    /*
     * =========================================================
     * FINALIZE DAILY CLOSING
     * =========================================================
     */

    public function finalizeDailyClosing(
        int $dailyId
    ): void {

        $dailyRecord =
            $this->getDailyRecord(
                $dailyId
            );

        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'Daily record not found.'
            ], 404);
        }

        if (
            $dailyRecord['status'] !==
            'Open'
        ) {

            respond([
                'success' => false,
                'message' =>
                    'This daily record is no longer open.'
            ], 409);
        }


        /*
         * -----------------------------------------------------
         * LOAD SAVED DRAFT
         * -----------------------------------------------------
         */

        $stmt =
            $this->pdo->prepare(
                "SELECT
                    draft_data
                 FROM daily_closing_drafts
                 WHERE daily_id = ?
                 LIMIT 1"
            );

        $stmt->execute([
            $dailyId
        ]);

        $draftRow =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$draftRow) {

            respond([
                'success' => false,
                'message' =>
                    'There is no saved Daily Closing draft to finalize.'
            ], 400);
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
                    'The saved Daily Closing draft is invalid.'
            ], 400);
        }


        /*
         * -----------------------------------------------------
         * BASIC DRAFT STRUCTURE
         * -----------------------------------------------------
         */

        $shop =
            is_array($draft['shop'] ?? null)
                ? $draft['shop']
                : [];

        $driver =
            is_array($draft['driver'] ?? null)
                ? $draft['driver']
                : [];

        $shopDeliveries =
            is_array($shop['deliveries'] ?? null)
                ? $shop['deliveries']
                : [];

        $driverDeliveries =
            is_array($driver['deliveries'] ?? null)
                ? $driver['deliveries']
                : [];

        $shopExpenses =
            is_array($shop['expenses'] ?? null)
                ? $shop['expenses']
                : [];

        $driverExpenses =
            is_array($driver['expenses'] ?? null)
                ? $driver['expenses']
                : [];

        /*
        * -----------------------------------------------------
        * DRIVER REMITTANCE
        * -----------------------------------------------------
        *
        * Match the existing Daily Closing calculation:
        *
        * Effective received:
        *
        *   Driver Money Received
        * + Driver Expenses
        * + Shop Delivery Payments
        *
        * Expected:
        *
        *   Shop Delivery Payments
        * + Driver Delivery Payments
        *
        * Positive = Over
        * Negative = Short
        * Zero     = Balanced
        */

        $driverMoneyReceived =
            $this->money(
                $driver['money_received']
                    ?? 0
            );


        $driverExpensesTotal =
            round(
                array_sum(
                    array_map(
                        function ($expense): float {

                            return $this->money(
                                $expense['amount']
                                    ?? 0
                            );

                        },
                        $driverExpenses
                    )
                ),
                2
            );


        $shopDeliveryPaymentsTotal =
            round(
                array_sum(
                    array_map(
                        function ($delivery): float {

                            return $this->money(
                                $delivery['payment']
                                    ?? 0
                            );

                        },
                        $shopDeliveries
                    )
                ),
                2
            );


        $driverDeliveryPaymentsTotal =
            round(
                array_sum(
                    array_map(
                        function ($delivery): float {

                            return $this->money(
                                $delivery['payment']
                                    ?? 0
                            );

                        },
                        $driverDeliveries
                    )
                ),
                2
            );
        
        $driverMoneyReceived =
            $this->money(
                $_POST['driver_money_received']
                    ?? 0
            );

        $effectiveReceived =
            round(
                $driverMoneyReceived
                + $driverExpensesTotal
                + $shopDeliveryPaymentsTotal,
                2
            );


        $expectedDriverRemittance =
            round(
                $shopDeliveryPaymentsTotal
                + $driverDeliveryPaymentsTotal,
                2
            );


        $driverRemittanceDifference =
            round(
                $effectiveReceived
                - $expectedDriverRemittance,
                2
            );


        if (
            abs($effectiveReceived)
                <= self::MONEY_TOLERANCE
            &&
            abs($expectedDriverRemittance)
                <= self::MONEY_TOLERANCE
        ) {

            $driverRemittanceStatus = null;

        } elseif (
            abs($driverRemittanceDifference)
                <= self::MONEY_TOLERANCE
        ) {

            $driverRemittanceStatus =
                'Balanced';

        } elseif (
            $driverRemittanceDifference < 0
        ) {

            $driverRemittanceStatus =
                'Short';

        } else {

            $driverRemittanceStatus =
                'Over';
        }

        /*
         * -----------------------------------------------------
         * CLOSING RESULT
         * -----------------------------------------------------
         */

        $closingResult =
            trim(
                (string) (
                    $_POST['closing_result'] ?? ''
                )
            );

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
         * -----------------------------------------------------
         * ACTUAL STATION CASH
         * -----------------------------------------------------
         */

        $actualStationCashRaw =
            trim(
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
         * -----------------------------------------------------
         * AUTHENTICATED USER
         * -----------------------------------------------------
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
         * -----------------------------------------------------
         * FINALIZATION CREDIT POOL
         * -----------------------------------------------------
         *
         * This is the in-memory customer credit balance.
         *
         * Positive overpayment increases it.
         * Credit usage decreases it.
         *
         * SQL changes happen only inside the transaction.
         */

        $creditData =
            $this->loadCreditBalances();

        $creditBalances =
            $creditData['balances'];


        /*
         * -----------------------------------------------------
         * TRANSACTION
         * -----------------------------------------------------
         */

        try {

            $this->pdo->beginTransaction();


            /*
             * ================================================
             * 1. WALK-IN SALES
             * ================================================
             */

            $walkInCustomers =
                $this->quantity(
                    $shop['walk_in_customers']
                    ?? 0
                );

            $walkInPrice =
                30.00;

            $walkInSales =
                round(
                    $walkInCustomers *
                    $walkInPrice,
                    2
                );

            if ($walkInCustomers > 0) {

                $stmt =
                    $this->pdo->prepare(
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
             * ================================================
             * DELIVERY PROCESSOR
             * ================================================
             */

            $insertDelivery =
                function (
                    array $delivery,
                    string $collectionLocation
                ) use (
                    $dailyRecord,
                    $dailyId,
                    &$creditBalances
                ): void {

                    $customerName =
                        $this->customerName(
                            $delivery['customer']
                            ?? ''
                        );

                    $customer =
                        $this->findCustomer(
                            $customerName
                        );

                    $customerId =
                        (int) $customer['customer_id'];


                    $slim =
                        $this->quantity(
                            $delivery['slim']
                            ?? 0
                        );

                    $round =
                        $this->quantity(
                            $delivery['round']
                            ?? 0
                        );

                    $price =
                        $this->money(
                            $delivery['price']
                            ?? 0
                        );

                    $payment =
                        $this->money(
                            $delivery['payment']
                            ?? 0
                        );

                    $method =
                        trim(
                            (string) (
                                $delivery['method']
                                ?? ''
                            )
                        );

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
                     * Completely empty rows are ignored.
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

                    $amountDue =
                        round(
                            $gallons *
                            $price,
                            2
                        );


                    /*
                     * ==========================================
                     * SAVE DELIVERY
                     * ==========================================
                     */

                    $stmt =
                        $this->pdo->prepare(
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
                        (int) $this->pdo->lastInsertId();


                    /*
                     * ==========================================
                     * PAYMENT REMAINING
                     * ==========================================
                     */

                    $paymentRemaining =
                        round(
                            $payment,
                            2
                        );


                    /*
                     * ==========================================
                     * STEP 1
                     * CURRENT DELIVERY
                     * ==========================================
                     */

                    $currentPayment =
                        min(
                            $paymentRemaining,
                            $amountDue
                        );

                    $currentPayment =
                        round(
                            $currentPayment,
                            2
                        );

                    if (
                        $currentPayment >
                        self::MONEY_TOLERANCE
                    ) {

                        $this->insertPayment(
                            $deliveryId,
                            $customerId,
                            $dailyRecord['business_date'],
                            $currentPayment,
                            $method,
                            $collectionLocation,
                            null,
                            $dailyId
                        );
                    }

                    $paymentRemaining =
                        round(
                            $paymentRemaining -
                            $currentPayment,
                            2
                        );

                    $currentRemaining =
                        round(
                            $amountDue -
                            $currentPayment,
                            2
                        );


                    /*
                     * ==========================================
                     * STEP 2
                     * EXISTING CUSTOMER CREDIT
                     * ==========================================
                     *
                     * If the current delivery is unpaid,
                     * available credit covers it.
                     */

                    if (
                        $currentRemaining >
                        self::MONEY_TOLERANCE
                    ) {

                        $availableCredit =
                            round(
                                (float) (
                                    $creditBalances[
                                        $customerId
                                    ] ?? 0
                                ),
                                2
                            );

                        if (
                            $availableCredit >
                            self::MONEY_TOLERANCE
                        ) {

                            $creditUsed =
                                min(
                                    $currentRemaining,
                                    $availableCredit
                                );

                            $creditUsed =
                                round(
                                    $creditUsed,
                                    2
                                );

                            if (
                                $creditUsed >
                                self::MONEY_TOLERANCE
                            ) {

                                /*
                                 * Negative ledger entry.
                                 */

                                $this->insertCreditEntry(
                                    $customerId,
                                    $dailyRecord['business_date'],
                                    -$creditUsed,
                                    'Credit',
                                    $collectionLocation,
                                    $dailyId,
                                    'Customer credit applied to delivery'
                                );

                                $creditBalances[
                                    $customerId
                                ] =
                                    round(
                                        $availableCredit -
                                        $creditUsed,
                                        2
                                    );

                                $currentRemaining =
                                    round(
                                        $currentRemaining -
                                        $creditUsed,
                                        2
                                    );
                            }
                        }
                    }


                    /*
                     * ==========================================
                     * STEP 3
                     * EXCESS PAYMENT → OLDEST DEBT
                     * ==========================================
                     *
                     * This intentionally happens after the
                     * current delivery is settled.
                     *
                     * Previous deliveries include:
                     *
                     * - previous dates
                     * - earlier deliveries today
                     */

                    if (
                        $paymentRemaining >
                        self::MONEY_TOLERANCE
                    ) {

                        $stmt =
                            $this->pdo->prepare(
                                "SELECT
                                    d.delivery_id,
                                    d.amount_due,

                                    COALESCE(
                                        SUM(p.amount),
                                        0
                                    ) AS total_paid

                                 FROM deliveries d

                                 LEFT JOIN payments p
                                    ON p.delivery_id =
                                       d.delivery_id

                                 WHERE d.customer_id = ?
                                   AND d.delivery_id < ?

                                 GROUP BY
                                    d.delivery_id,
                                    d.amount_due,
                                    d.delivery_date

                                 HAVING
                                    d.amount_due -
                                    COALESCE(
                                        SUM(p.amount),
                                        0
                                    ) > 0.009

                                 ORDER BY
                                    d.delivery_date ASC,
                                    d.delivery_id ASC

                                 FOR UPDATE"
                            );

                        $stmt->execute([
                            $customerId,
                            $deliveryId
                        ]);

                        $previousDebts =
                            $stmt->fetchAll(
                                PDO::FETCH_ASSOC
                            );


                        foreach (
                            $previousDebts
                            as $previousDebt
                        ) {

                            if (
                                $paymentRemaining <=
                                self::MONEY_TOLERANCE
                            ) {
                                break;
                            }

                            $previousAmountDue =
                                round(
                                    (float)
                                    $previousDebt['amount_due'],
                                    2
                                );

                            $previousTotalPaid =
                                round(
                                    (float)
                                    $previousDebt['total_paid'],
                                    2
                                );

                            $previousRemaining =
                                round(
                                    $previousAmountDue -
                                    $previousTotalPaid,
                                    2
                                );

                            if (
                                $previousRemaining <=
                                self::MONEY_TOLERANCE
                            ) {
                                continue;
                            }

                            $debtPayment =
                                min(
                                    $paymentRemaining,
                                    $previousRemaining
                                );

                            $debtPayment =
                                round(
                                    $debtPayment,
                                    2
                                );

                            if (
                                $debtPayment <=
                                self::MONEY_TOLERANCE
                            ) {
                                continue;
                            }

                            $this->insertPayment(
                                (int)
                                $previousDebt['delivery_id'],

                                $customerId,

                                $dailyRecord['business_date'],

                                $debtPayment,

                                $method,

                                $collectionLocation,

                                'Payment toward previous debt',

                                $dailyId
                            );

                            $paymentRemaining =
                                round(
                                    $paymentRemaining -
                                    $debtPayment,
                                    2
                                );
                        }
                    }


                    /*
                     * ==========================================
                     * STEP 4
                     * REMAINING EXCESS → CREDIT
                     * ==========================================
                     */

                    if (
                        $paymentRemaining >
                        self::MONEY_TOLERANCE
                    ) {

                        $paymentRemaining =
                            round(
                                $paymentRemaining,
                                2
                            );

                        $this->insertCreditEntry(
                            $customerId,
                            $dailyRecord['business_date'],
                            $paymentRemaining,
                            $method,
                            $collectionLocation,
                            $dailyId,
                            'Customer credit from overpayment'
                        );

                        /*
                         * Make this new credit available
                         * immediately to later deliveries
                         * in today's finalization.
                         */

                        $creditBalances[
                            $customerId
                        ] =
                            round(
                                (
                                    $creditBalances[
                                        $customerId
                                    ] ?? 0
                                )
                                + $paymentRemaining,
                                2
                            );
                    }
                };


            /*
             * ================================================
             * 2. SHOP DELIVERIES
             * ================================================
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
             * ================================================
             * 3. DRIVER DELIVERIES
             * ================================================
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
             * ================================================
             * EXPENSE PROCESSOR
             * ================================================
             */

            $insertExpense =
                function (
                    array $expense,
                    string $location
                ): void {

                    $category =
                        trim(
                            (string) (
                                $expense['category']
                                ?? ''
                            )
                        );

                    $amount =
                        $this->money(
                            $expense['amount']
                            ?? 0
                        );

                    $description =
                        trim(
                            (string) (
                                $expense['name']
                                ?? ''
                            )
                        );


                    /*
                     * Completely empty rows are ignored.
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


                    if (
                        $category ===
                        'Others'
                    ) {
                        $category =
                            'Miscellaneous';
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


                    $stmt =
                        $this->pdo->prepare(
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


            /*
             * ================================================
             * 4. SHOP EXPENSES
             * ================================================
             */

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
             * ================================================
             * 5. DRIVER EXPENSES
             * ================================================
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
             * ================================================
             * 6. FINALIZE DAILY RECORD
             * ================================================
             */

            $stmt =
                $this->pdo->prepare(
                    "UPDATE daily_records
                     SET
                        status = 'Saved',
                        closing_result = ?,
                        actual_station_cash = ?,
                        driver_remittance_status = ?,
                        driver_remittance_difference = ?,
                        saved_at = NOW(),
                        saved_by = ?,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE daily_id = ?
                        AND status = 'Open'"
                );

            $stmt->execute([
                $closingResult,
                $actualStationCash,
                $driverRemittanceStatus,
                $driverRemittanceDifference,
                $savedBy,
                $dailyId
            ]);

            if ($stmt->rowCount() !== 1) {

                throw new RuntimeException(
                    'The daily record could not be finalized.'
                );
            }


            /*
             * ================================================
             * 7. DELETE TEMPORARY DRAFT
             * ================================================
             */

            $stmt =
                $this->pdo->prepare(
                    "DELETE FROM daily_closing_drafts
                     WHERE daily_id = ?"
                );

            $stmt->execute([
                $dailyId
            ]);


            /*
             * ================================================
             * COMMIT
             * ================================================
             */

            $this->pdo->commit();


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
                'driver_remittance_status' =>
                    $driverRemittanceStatus,
                'driver_remittance_difference' =>
                    $driverRemittanceDifference,
                'saved_at' =>
                    date('Y-m-d H:i:s'),
                'saved_by' =>
                    $savedBy
            ]);

        } catch (Throwable $e) {

            if (
                $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            respond([
                'success' => false,
                'message' =>
                    $e->getMessage()
            ], 400);
        }
    }
}


/*
 * =============================================================
 * REQUEST ROUTER
 * =============================================================
 */

try {

    $service =
        new DailyClosingService(
            $pdo
        );


    /*
     * ---------------------------------------------------------
     * REQUIRED TABLES
     * ---------------------------------------------------------
     */

    $service->ensureDraftTable();
    $service->ensureCreditTable();


    $method =
        strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );


    /*
     * =========================================================
     * GET
     * =========================================================
     */

    if ($method === 'GET') {

        $action =
            trim(
                (string) (
                    $_GET['action'] ?? ''
                )
            );


        /*
         * -----------------------------------------------------
         * CUSTOMER LIST
         * -----------------------------------------------------
         */

        if (
            $action ===
            'get_customers'
        ) {

            $service->getCustomers();
        }


        /*
         * -----------------------------------------------------
         * CURRENT DEBT
         * -----------------------------------------------------
         */

        if (
            $action ===
            'get_current_debt'
        ) {

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

            $service->getCurrentDebt(
                $dailyId,
                $draft
            );
        }


        /*
         * -----------------------------------------------------
         * DAILY CONTEXT
         * -----------------------------------------------------
         */

        if (
            $action ===
            'get_daily_context'
        ) {

            $service->getDailyContext();
        }


        /*
         * -----------------------------------------------------
         * LOAD DAILY CLOSING
         * -----------------------------------------------------
         */

        $requestedDailyId =
            filter_input(
                INPUT_GET,
                'daily_id',
                FILTER_VALIDATE_INT
            );

        $requestedDailyId =
            $requestedDailyId
                ? (int) $requestedDailyId
                : 0;

        $service->loadDailyClosing(
            $requestedDailyId
        );
    }


    /*
     * =========================================================
     * POST
     * =========================================================
     */

    if ($method === 'POST') {

        $action =
            trim(
                (string) (
                    $_POST['action'] ?? ''
                )
            );


        /*
         * -----------------------------------------------------
         * SAVE CUSTOMER PRICE
         * -----------------------------------------------------
         */

        if (
            $action ===
            'save_customer_price'
        ) {

            $service->saveCustomerPrice();
        }


        /*
         * -----------------------------------------------------
         * DAILY ID
         * -----------------------------------------------------
         */

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


        /*
         * -----------------------------------------------------
         * SAVE
         * -----------------------------------------------------
         */

        if (
            $action ===
            'save'
        ) {

            $service->saveDraft(
                $dailyId
            );
        }


        /*
         * -----------------------------------------------------
         * FINALIZE
         * -----------------------------------------------------
         */

        if (
            $action ===
            'finalize'
        ) {

            $service->finalizeDailyClosing(
                $dailyId
            );
        }


        /*
         * -----------------------------------------------------
         * RESET
         * -----------------------------------------------------
         */

        if (
            $action ===
            'reset'
        ) {

            $service->resetDraft(
                $dailyId
            );
        }


        /*
         * -----------------------------------------------------
         * UNKNOWN ACTION
         * -----------------------------------------------------
         */

        respond([
            'success' => false,
            'message' =>
                'Unknown action.'
        ], 400);
    }


    /*
     * =========================================================
     * METHOD NOT ALLOWED
     * =========================================================
     */

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