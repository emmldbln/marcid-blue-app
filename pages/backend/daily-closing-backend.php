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
 * CUSTOMER LIST
 * =============================================================
 */

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


/*
 * =============================================================
 * ENSURE DRAFT TABLE
 * =============================================================
 */

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
 * =============================================================
 * ENSURE CUSTOMER CREDIT TABLE
 * =============================================================
 *
 * Customer credits are created when:
 *
 *     current delivery
 *          +
 *     previous outstanding debt
 *
 * have both been fully paid and money is still remaining.
 *
 * Credits are NOT automatically applied to future deliveries yet.
 *
 * They remain available until we implement that separate feature.
 */

function ensureCreditTable(
    PDO $pdo
): void {

    $pdo->exec(
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
 * =============================================================
 * CURRENT DEBT
 * =============================================================
 */

function getCurrentDebt(
        PDO $pdo,
        int $dailyId,
        array $draft
    ): void {

    /*
     * =========================================================
     * GET BUSINESS DATE
     * =========================================================
     */

    $dailyRecord = null;


    if ($dailyId > 0) {

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
    }


    /*
     * If the supplied Daily ID cannot be found,
     * use the most recent Daily Record.
     */

    if (!$dailyRecord) {

        $stmt = $pdo->query(
            "SELECT
                daily_id,
                business_date,
                status
             FROM daily_records
             ORDER BY business_date DESC, daily_id DESC
             LIMIT 1"
        );

        $dailyRecord =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );
    }


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
     * =========================================================
     * PERMANENT HISTORICAL DEBT
     * =========================================================
     *
     * Load all previously-created deliveries that still have
     * an outstanding balance.
     *
     * These are later adjusted by today's draft payments.
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

    $deliveryRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
     * =========================================================
     * BUILD HISTORICAL DEBT BUCKETS
     * =========================================================
     *
     * We keep these separately so draft payments can be
     * temporarily allocated against them.
     */

    $historicalDebts = [];

    $customerDebtBuckets = [];


    foreach ($deliveryRows as $delivery) {

        $originalAmount =
            round(
                (float) $delivery['amount_due'],
                2
            );

        $totalPaid =
            round(
                (float) $delivery['total_paid'],
                2
            );

        $remaining =
            round(
                $originalAmount - $totalPaid,
                2
            );


        if ($originalAmount <= 0) {
            continue;
        }


        if ($remaining <= 0) {
            continue;
        }


        $debtIndex =
            count($historicalDebts);


        $historicalDebts[] = [

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

            'draft_payment' =>
                0.00,

            'status' =>
                'Outstanding',

            'is_draft' =>
                false
        ];


        $customerId =
            (int) $delivery['customer_id'];


        if (!isset($customerDebtBuckets[$customerId])) {
            $customerDebtBuckets[$customerId] = [];
        }


        $customerDebtBuckets[$customerId][] = [
            'type' => 'historical',
            'index' => $debtIndex
        ];
    }


    /*
     * =========================================================
     * TODAY'S DRAFT DELIVERIES
     * =========================================================
     */

    $draftShopDeliveries =
        $draft['shop']['deliveries']
        ?? [];


    $draftDriverDeliveries =
        $draft['driver']['deliveries']
        ?? [];


    $todayDraftDeliveries =
        array_merge(
            is_array($draftShopDeliveries)
                ? $draftShopDeliveries
                : [],

            is_array($draftDriverDeliveries)
                ? $draftDriverDeliveries
                : []
        );


    /*
     * =========================================================
     * CUSTOMER LOOKUP
     * =========================================================
     *
     * Draft rows may contain customer_id/customerId.
     * If they do not, resolve the customer using the name.
     */

    $customerLookup = [];


    foreach ($todayDraftDeliveries as $delivery) {

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


        if ($customerId > 0) {

            $customerLookup[$customerName] =
                $customerId;

            continue;
        }


        if (isset($customerLookup[$customerName])) {
            continue;
        }


        $stmt = $pdo->prepare(
            "SELECT
                customer_id
             FROM customers
             WHERE customer_name = ?
             LIMIT 1"
        );

        $stmt->execute([
            $customerName
        ]);


        $foundCustomer =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($foundCustomer) {

            $customerLookup[$customerName] =
                (int) $foundCustomer['customer_id'];
        }
    }


    /*
     * =========================================================
     * DRAFT DEBT + DRAFT CREDIT PREVIEW
     * =========================================================
     *
     * This is the important part.
     *
     * For every draft delivery:
     *
     * 1. Payment covers the current delivery.
     * 2. Remaining payment covers older debt.
     * 3. Any remaining money becomes temporary credit.
     *
     * Nothing is written to MySQL here.
     */

    $draftDebts = [];

    $draftCredits = [];


    foreach ($todayDraftDeliveries as $delivery) {

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
         * -----------------------------------------------------
         * STEP 1
         * Pay the current draft delivery.
         * -----------------------------------------------------
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
         * -----------------------------------------------------
         * STEP 2
         * Use remaining payment against older debt.
         *
         * This includes:
         *
         * - Historical debt
         * - Earlier draft deliveries today
         * -----------------------------------------------------
         */

        if (
            $paymentRemaining > 0.009
            && $customerId > 0
            && isset(
                $customerDebtBuckets[$customerId]
            )
        ) {

            foreach (
                $customerDebtBuckets[$customerId]
                as $bucket
            ) {

                if ($paymentRemaining <= 0.009) {
                    break;
                }


                $bucketRemaining = 0.00;


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


                if ($bucketRemaining <= 0) {
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
         * -----------------------------------------------------
         * STEP 3
         * Anything still remaining is customer credit.
         * -----------------------------------------------------
         */

        if (
            $paymentRemaining > 0.009
            && $customerId > 0
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

                    'total_credit' =>
                        0.00
                ];
            }


            $draftCredits[$customerId][
                'total_credit'
            ] =
                round(
                    $draftCredits[$customerId][
                        'total_credit'
                    ]
                    + $paymentRemaining,
                    2
                );
        }


        /*
         * -----------------------------------------------------
         * Add current draft delivery to the debt list.
         *
         * Important:
         *
         * The delivery is added AFTER its own payment
         * is processed, so its remaining amount is correct.
         * -----------------------------------------------------
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


        /*
         * Add this draft delivery as an older debt bucket
         * for subsequent deliveries from the same customer.
         */

        if ($customerId > 0) {

            if (
                !isset(
                    $customerDebtBuckets[$customerId]
                )
            ) {

                $customerDebtBuckets[$customerId] = [];
            }


            /*
             * Insert at the end because deliveries are
             * processed in their draft order.
             */

            $customerDebtBuckets[$customerId][] = [

                'type' =>
                    'draft',

                'index' =>
                    $draftDebtIndex
            ];
        }
    }


    /*
     * =========================================================
     * BUILD ACTIVE DEBT
     * =========================================================
     */

    $debts = [];

    $totalDebt = 0.00;


    /*
     * Historical debt after today's draft payments.
     */

    foreach (
        $historicalDebts
        as $debt
    ) {

        $remaining =
            round(
                (float) $debt['remaining_amount'],
                2
            );

        $draftPayment =
            round(
                (float) ($debt['draft_payment'] ?? 0),
                2
            );

        /*
        * Keep a historical debt visible when today's draft
        * payment has fully settled it.
        *
        * This is temporary UI state only.
        * The payment is not permanent until Finalize.
        */
        if (
            $remaining <= 0.009
            && $draftPayment <= 0.009
        ) {
            continue;
        }

        $debt['remaining_amount'] =
            max(0.00, $remaining);

        $debt['draft_payment'] =
            $draftPayment;

        $debt['is_draft_paid'] =
            $remaining <= 0.009
            && $draftPayment > 0.009;

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
     * Today's draft debt after payment allocation.
     */

    foreach (
        $draftDebts
        as $debt
    ) {

        $remaining =
            round(
                (float) $debt['remaining_amount'],
                2
            );


        if ($remaining <= 0.009) {
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
     * =========================================================
     * CUSTOMER CREDITS FROM DATABASE
     * =========================================================
     */

    $stmt = $pdo->query(
        "SELECT
            cc.customer_id,
            c.customer_name,
            SUM(cc.amount) AS total_credit

         FROM customer_credits cc

         INNER JOIN customers c
            ON c.customer_id = cc.customer_id

         WHERE cc.amount > 0

         GROUP BY
            cc.customer_id,
            c.customer_name

         ORDER BY
            c.customer_name ASC"
    );


    $creditRows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    $creditsByCustomer = [];


    foreach (
        $creditRows
        as $credit
    ) {

        $customerCredit =
            round(
                (float) $credit['total_credit'],
                2
            );


        if ($customerCredit <= 0) {
            continue;
        }


        $customerId =
            (int) $credit['customer_id'];


        $creditsByCustomer[$customerId] = [

            'customer_id' =>
                $customerId,

            'customer_name' =>
                $credit['customer_name'],

            'total_credit' =>
                $customerCredit
        ];
    }


    /*
     * =========================================================
     * ADD TODAY'S DRAFT CREDITS
     * =========================================================
     */

    foreach (
        $draftCredits
        as $customerId =>
        $draftCredit
    ) {

        $draftAmount =
            round(
                (float) $draftCredit['total_credit'],
                2
            );


        if ($draftAmount <= 0.009) {
            continue;
        }


        if (
            isset(
                $creditsByCustomer[$customerId]
            )
        ) {

            $creditsByCustomer[$customerId][
                'total_credit'
            ] =
                round(
                    $creditsByCustomer[$customerId][
                        'total_credit'
                    ]
                    + $draftAmount,
                    2
                );

        } else {

            $creditsByCustomer[$customerId] = [

                'customer_id' =>
                    $customerId,

                'customer_name' =>
                    $draftCredit['customer_name'],

                'total_credit' =>
                    $draftAmount
            ];
        }
    }


    /*
     * =========================================================
     * FINAL CREDIT LIST
     * =========================================================
     */

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


    $totalCredit = 0.00;


    foreach (
        $credits
        as $credit
    ) {

        $totalCredit =
            round(
                $totalCredit +
                (float) $credit['total_credit'],
                2
            );
    }


    /*
     * =========================================================
     * SORT DEBT
     * =========================================================
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
     * =========================================================
     * GROUP DEBT BY ORIGINAL DATE
     * =========================================================
     */

    $groupedDebts = [];


    foreach (
        $debts
        as $debt
    ) {

        $date =
            $debt['date_incurred'];


        if (!isset($groupedDebts[$date])) {
            $groupedDebts[$date] = [];
        }


        $groupedDebts[$date][] =
            $debt;
    }


    /*
     * =========================================================
     * RESPONSE
     * =========================================================
     */

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
 * =============================================================
 * GET OPEN DAILY RECORD
 * =============================================================
 */

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


/*
 * =============================================================
 * SAVE CUSTOMER PRICE
 * =============================================================
 */

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


/*
 * =============================================================
 * FINALIZE DAILY CLOSING
 * =============================================================
 */

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
     * =========================================================
     * LOAD SAVED AUTOSAVE DRAFT
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


    $draftRow =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


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
     * =========================================================
     * BASIC DRAFT STRUCTURE
     * =========================================================
     */

    $shop =
        $draft['shop']
        ?? [];


    $driver =
        $draft['driver']
        ?? [];


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
     * Actual closing verification will be implemented later.
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
     * =========================================================
     * ACTUAL STATION CASH
     * =========================================================
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
     * CLEAN MONEY
     * =========================================================
     */

    $cleanMoney =
        static function (
            $value
        ): float {

            /*
             * Empty display placeholders are treated as zero.
             *
             * These are UI-only values and are not actual money.
             */

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


            $amount =
                round(
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


    /*
     * =========================================================
     * CLEAN QUANTITY
     * =========================================================
     */

    $cleanQuantity =
        static function (
            $value
        ): int {

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
        };


    /*
     * =========================================================
     * CLEAN CUSTOMER NAME
     * =========================================================
     */

    $cleanCustomerName =
        static function (
            $value
        ): string {

            $name =
                trim(
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
     * =========================================================
     * FIND CUSTOMER
     * =========================================================
     */

    $findCustomer =
        function (
            string $customerName
        ) use ($pdo): array {

            $stmt =
                $pdo->prepare(
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
                $stmt->fetchAll(
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

        $walkInCustomers =
            $cleanQuantity(
                $shop['walk_in_customers']
                ?? 0
            );


        $walkInPrice =
            30.00;


        $walkInSales =
            round(
                $walkInCustomers * $walkInPrice,
                2
            );


        if ($walkInCustomers > 0) {

            $stmt =
                $pdo->prepare(
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
         */

        $insertDelivery =
            function (
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
                        $delivery['customer']
                        ?? ''
                    );


                $customer =
                    $findCustomer(
                        $customerName
                    );


                $customerId =
                    (int) $customer['customer_id'];


                $slim =
                    $cleanQuantity(
                        $delivery['slim']
                        ?? 0
                    );


                $round =
                    $cleanQuantity(
                        $delivery['round']
                        ?? 0
                    );


                $price =
                    $cleanMoney(
                        $delivery['price']
                        ?? 0
                    );


                $payment =
                    $cleanMoney(
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
                        $gallons * $price,
                        2
                    );


                /*
                * =====================================================
                * PAYMENT VALIDATION
                * =====================================================
                */

                if ($payment < 0) {

                    throw new InvalidArgumentException(
                        'Payment cannot be negative for customer "' .
                        $customerName .
                        '".'
                    );
                }


                /*
                * =====================================================
                * SAVE DELIVERY
                * =====================================================
                */

                $stmt =
                    $pdo->prepare(
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
                * =====================================================
                * PAYMENT ALLOCATION
                * =====================================================
                *
                * Payment is classified in this exact order:
                *
                * 1. Current delivery
                * 2. Oldest outstanding delivery belonging
                *    to the same customer
                * 3. Customer Credit
                *
                * IMPORTANT:
                *
                * Previous debt includes:
                *
                * - older dates
                * - earlier deliveries from TODAY
                *
                * The current delivery itself is excluded because
                * only delivery_id values lower than the current
                * delivery_id are considered.
                */


                if ($payment <= 0) {
                    return;
                }


                /*
                * =====================================================
                * STEP 1
                * CURRENT DELIVERY PAYMENT
                * =====================================================
                *
                * Always pay the delivery that the payment was
                * entered against first.
                */

                $paymentRemaining =
                    round(
                        $payment,
                        2
                    );


                $todayPayment =
                    min(
                        $paymentRemaining,
                        $amountDue
                    );


                if ($todayPayment > 0) {

                    $stmt =
                        $pdo->prepare(
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
                        $todayPayment,
                        $method,
                        $collectionLocation,
                        null,
                        $dailyId
                    ]);


                    $paymentRemaining =
                        round(
                            $paymentRemaining -
                            $todayPayment,
                            2
                        );
                }


                /*
                * =====================================================
                * STEP 2
                * PREVIOUS OUTSTANDING DEBT
                * =====================================================
                *
                * Any remaining payment is now used against the
                * customer's oldest unpaid delivery.
                *
                * This includes earlier deliveries made TODAY.
                *
                * delivery_id < current delivery_id guarantees that
                * the current delivery is never selected here.
                */

                if ($paymentRemaining > 0.009) {

                    $stmt =
                        $pdo->prepare(
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

                        if ($paymentRemaining <= 0.009) {
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


                        if ($previousRemaining <= 0.009) {
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


                        if ($debtPayment <= 0.009) {
                            continue;
                        }


                        /*
                        * The payment is attached to the ORIGINAL
                        * delivery that created the debt.
                        */

                        $stmt =
                            $pdo->prepare(
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
                            (int)
                            $previousDebt['delivery_id'],

                            $customerId,

                            $dailyRecord['business_date'],

                            $debtPayment,

                            $method,

                            $collectionLocation,

                            'Payment toward previous debt',

                            $dailyId
                        ]);


                        $paymentRemaining =
                            round(
                                $paymentRemaining -
                                $debtPayment,
                                2
                            );
                    }
                }


                /*
                * =====================================================
                * STEP 3
                * TRUE OVERPAYMENT → CUSTOMER CREDIT
                * =====================================================
                *
                * At this point:
                *
                * - Current delivery is fully paid
                * - All previous outstanding debt has been paid
                *
                * Therefore, anything remaining is TRUE overpayment
                * and becomes customer credit.
                */

                if ($paymentRemaining > 0.009) {

                    $paymentRemaining =
                        round(
                            $paymentRemaining,
                            2
                        );


                    $stmt =
                        $pdo->prepare(
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

                        $dailyRecord['business_date'],

                        $paymentRemaining,

                        $method,

                        $collectionLocation,

                        $dailyId,

                        'Customer credit from overpayment'
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
         * 5. SAVE EXPENSE INSERT HELPER
         * =====================================================
         */

        $insertExpense =
            function (
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
                 * Completely empty expense rows are ignored.
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
                 * UI uses Others while database uses
                 * Miscellaneous.
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


                $stmt =
                    $pdo->prepare(
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
         * =====================================================
         * 6. SAVE SHOP EXPENSES
         * =====================================================
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
         * =====================================================
         * 7. SAVE DRIVER EXPENSES
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
         * 8. FINALIZE DAILY RECORD
         * =====================================================
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
         * =====================================================
         * 9. DELETE TEMPORARY DRAFT
         * =====================================================
         */

        $stmt =
            $pdo->prepare(
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


        respond([
            'success' => false,
            'message' =>
                $e->getMessage()
        ], 400);
    }
}


/*
 * =============================================================
 * REQUEST ROUTER
 * =============================================================
 */

try {

    /*
     * =========================================================
     * ENSURE REQUIRED TABLES
     * =========================================================
     *
     * These must run before GET and POST.
     *
     * This means simply opening Daily Closing will make sure
     * the required tables exist.
     */

    ensureDraftTable($pdo);
    ensureCreditTable($pdo);


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

        if ($action === 'get_customers') {

            getCustomers($pdo);
        }


        /*
         * -----------------------------------------------------
         * CURRENT DEBT
         * -----------------------------------------------------
         */

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


        /*
         * -----------------------------------------------------
         * DAILY CONTEXT
         * -----------------------------------------------------
         */

        if ($action === 'get_daily_context') {

            $today =
                date('Y-m-d');


            $stmt =
                $pdo->prepare("
                    SELECT
                        daily_id,
                        business_date,
                        status,
                        closing_result,
                        actual_station_cash,
                        saved_at
                    FROM daily_records
                    WHERE business_date = ?
                    LIMIT 1
                ");


            $stmt->execute([
                $today
            ]);


            $dailyRecord =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


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


        $dailyRecord =
            getOpenDailyRecord(
                $pdo,
                $requestedDailyId
            );


        /*
         * =====================================================
         * CREATE TODAY'S OPEN RECORD
         * =====================================================
         */

        if (!$dailyRecord) {

            $today =
                date('Y-m-d');


            /*
             * Check whether today's record already exists.
             */

            $stmt =
                $pdo->prepare(
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
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
             * Today's record does not exist.
             *
             * Create a new Open record.
             */

            if (!$todayRecord) {

                $stmt =
                    $pdo->prepare(
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
                    (int) $pdo->lastInsertId();


                /*
                 * Load newly-created record.
                 */

                $stmt =
                    $pdo->prepare(
                        "SELECT
                            daily_id,
                            business_date,
                            status
                         FROM daily_records
                         WHERE daily_id = ?
                         LIMIT 1"
                    );


                $stmt->execute([
                    $newDailyId
                ]);


                $dailyRecord =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );

            } else {

                /*
                 * Today's record already exists but is not Open.
                 *
                 * Do not reopen it automatically.
                 */

                $dailyRecord = null;
            }
        }


        /*
         * =====================================================
         * NO OPEN DAILY RECORD
         * =====================================================
         */

        if (!$dailyRecord) {

            respond([
                'success' => false,
                'message' =>
                    'No open daily record is available.'
            ], 404);
        }


        /*
         * =====================================================
         * LOAD AUTOSAVE DRAFT
         * =====================================================
         */

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


        /*
         * No draft means new blank Daily Closing.
         */

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


        /*
         * =====================================================
         * DECODE DRAFT
         * =====================================================
         */

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


        /*
         * =====================================================
         * RETURN DRAFT
         * =====================================================
         */

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

            saveCustomerPrice($pdo);
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
         * REQUIRE OPEN DAILY RECORD
         * -----------------------------------------------------
         */

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


        /*
         * -----------------------------------------------------
         * SAVE AUTOSAVE DRAFT
         * -----------------------------------------------------
         */

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


        /*
         * -----------------------------------------------------
         * FINALIZE
         * -----------------------------------------------------
         */

        if ($action === 'finalize') {

            finalizeDailyClosing(
                $pdo,
                $dailyId
            );
        }


        /*
         * -----------------------------------------------------
         * RESET
         * -----------------------------------------------------
         */

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