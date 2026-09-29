<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function postString(
    string $key,
    string $default = ''
): string {
    return trim(
        (string)($_POST[$key] ?? $default)
    );
}

function postInt(
    string $key,
    int $default = 0
): int {
    return (int)($_POST[$key] ?? $default);
}

function tableExists(
    PDO $pdo,
    string $table
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?"
    );

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(
    PDO $pdo,
    string $table,
    string $column
): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?"
    );

    $stmt->execute([
        $table,
        $column
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function money(float $value): float
{
    return round($value, 2);
}

/*
|--------------------------------------------------------------------------
| Date Helpers
|--------------------------------------------------------------------------
*/

function monthStart(
    int $year,
    int $month
): string {
    return sprintf(
        '%04d-%02d-01',
        $year,
        $month
    );
}

function nextMonthStart(
    int $year,
    int $month
): string {
    $date = new DateTimeImmutable(
        sprintf(
            '%04d-%02d-01',
            $year,
            $month
        )
    );

    return $date
        ->modify('+1 month')
        ->format('Y-m-d');
}

function monthEnd(
    int $year,
    int $month
): string {
    return (new DateTimeImmutable(
        sprintf(
            '%04d-%02d-01',
            $year,
            $month
        )
    ))
        ->modify('last day of this month')
        ->format('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| Analytics Table
|--------------------------------------------------------------------------
*/

function ensureAnalyticsSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS monthly_analytics (

            analytics_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            analytics_year SMALLINT UNSIGNED NOT NULL,

            analytics_month TINYINT UNSIGNED NOT NULL,

            total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            walk_in_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            delivery_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            total_expenses DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            net_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            slim_gallons DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            round_gallons DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            total_gallons DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            total_deliveries INT UNSIGNED NOT NULL DEFAULT 0,

            customers_served INT UNSIGNED NOT NULL DEFAULT 0,

            new_customers INT UNSIGNED NOT NULL DEFAULT 0,

            customers_with_debt INT UNSIGNED NOT NULL DEFAULT 0,

            outstanding_debt DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (analytics_id),

            UNIQUE KEY unique_analytics_month
                (
                    analytics_year,
                    analytics_month
                )

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}

/*
|--------------------------------------------------------------------------
| Walk-in Revenue
|--------------------------------------------------------------------------
*/

function calculateWalkInRevenue(
    PDO $pdo,
    string $start,
    string $end
): float {

    if (!tableExists($pdo, 'daily_records')) {
        return 0.00;
    }

    foreach ([
        'walk_in_revenue',
        'walk_in_sales',
        'walk_in_amount'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'daily_records',
                $column
            )
        ) {

            $stmt = $pdo->prepare(
                "SELECT
                    COALESCE(
                        SUM($column),
                        0
                    )

                 FROM daily_records

                 WHERE business_date >= ?
                   AND business_date < ?"
            );

            $stmt->execute([
                $start,
                $end
            ]);

            return money(
                (float)$stmt->fetchColumn()
            );
        }
    }

    foreach ([
        'walk_in_count',
        'walkins',
        'walk_in_quantity'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'daily_records',
                $column
            )
        ) {

            $stmt = $pdo->prepare(
                "SELECT
                    COALESCE(
                        SUM($column),
                        0
                    )

                 FROM daily_records

                 WHERE business_date >= ?
                   AND business_date < ?"
            );

            $stmt->execute([
                $start,
                $end
            ]);

            return money(
                (float)$stmt->fetchColumn() * 30
            );
        }
    }

    return 0.00;
}

/*
|--------------------------------------------------------------------------
| Daily Walk-in Data
|--------------------------------------------------------------------------
*/

function calculateDailyWalkIns(
    PDO $pdo,
    string $start,
    string $end
): array {

    $result = [];

    if (!tableExists($pdo, 'daily_records')) {
        return $result;
    }

    $valueExpression = null;

    foreach ([
        'walk_in_revenue',
        'walk_in_sales',
        'walk_in_amount'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'daily_records',
                $column
            )
        ) {

            $valueExpression =
                "COALESCE($column, 0)";

            break;
        }
    }

    if (!$valueExpression) {

        foreach ([
            'walk_in_count',
            'walkins',
            'walk_in_quantity'
        ] as $column) {

            if (
                columnExists(
                    $pdo,
                    'daily_records',
                    $column
                )
            ) {

                $valueExpression =
                    "COALESCE($column, 0) * 30";

                break;
            }
        }
    }

    if (!$valueExpression) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT
            business_date,
            $valueExpression AS revenue

         FROM daily_records

         WHERE business_date >= ?
           AND business_date < ?

         ORDER BY business_date ASC"
    );

    $stmt->execute([
        $start,
        $end
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $date = (string)$row['business_date'];

        $result[$date] =
            money(
                (float)$row['revenue']
            );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Delivery Analysis
|--------------------------------------------------------------------------
*/

function calculateDeliveryStats(
    PDO $pdo,
    string $start,
    string $end
): array {

    $result = [
        'revenue' => 0.00,
        'slim' => 0.00,
        'round' => 0.00,
        'deliveries' => 0
    ];

    if (!tableExists($pdo, 'deliveries')) {
        return $result;
    }

    $slimColumn =
        columnExists(
            $pdo,
            'deliveries',
            'slim_quantity'
        )
            ? 'slim_quantity'
            : null;

    $roundColumn =
        columnExists(
            $pdo,
            'deliveries',
            'round_quantity'
        )
            ? 'round_quantity'
            : null;

    $amountColumn =
        columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        )
            ? 'amount_due'
            : null;

    $dateColumn =
        columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        )
            ? 'delivery_date'
            : null;

    if (!$dateColumn) {
        return $result;
    }

    $select = [
        "COUNT(*) AS delivery_count"
    ];

    if ($amountColumn) {
        $select[] =
            "COALESCE(
                SUM($amountColumn),
                0
            ) AS delivery_revenue";
    }

    if ($slimColumn) {
        $select[] =
            "COALESCE(
                SUM($slimColumn),
                0
            ) AS slim_gallons";
    }

    if ($roundColumn) {
        $select[] =
            "COALESCE(
                SUM($roundColumn),
                0
            ) AS round_gallons";
    }

    $stmt = $pdo->prepare(
        "SELECT
            " . implode(',', $select) . "

         FROM deliveries

         WHERE $dateColumn >= ?
           AND $dateColumn < ?"
    );

    $stmt->execute([
        $start,
        $end
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    $result['deliveries'] =
        (int)($row['delivery_count'] ?? 0);

    $result['revenue'] =
        money(
            (float)($row['delivery_revenue'] ?? 0)
        );

    $result['slim'] =
        money(
            (float)($row['slim_gallons'] ?? 0)
        );

    $result['round'] =
        money(
            (float)($row['round_gallons'] ?? 0)
        );

    return $result;
}

/*
|--------------------------------------------------------------------------
| Daily Delivery Data
|--------------------------------------------------------------------------
*/

function calculateDailyDeliveries(
    PDO $pdo,
    string $start,
    string $end
): array {

    $result = [];

    if (!tableExists($pdo, 'deliveries')) {
        return $result;
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        )
    ) {
        return $result;
    }

    $amountColumn =
        columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        )
            ? 'amount_due'
            : null;

    $slimColumn =
        columnExists(
            $pdo,
            'deliveries',
            'slim_quantity'
        )
            ? 'slim_quantity'
            : null;

    $roundColumn =
        columnExists(
            $pdo,
            'deliveries',
            'round_quantity'
        )
            ? 'round_quantity'
            : null;

    $select = [
        "delivery_date"
    ];

    if ($amountColumn) {
        $select[] =
            "COALESCE(
                $amountColumn,
                0
            ) AS revenue";
    } else {
        $select[] =
            "0 AS revenue";
    }

    if ($slimColumn) {
        $select[] =
            "COALESCE(
                $slimColumn,
                0
            ) AS slim";
    } else {
        $select[] =
            "0 AS slim";
    }

    if ($roundColumn) {
        $select[] =
            "COALESCE(
                $roundColumn,
                0
            ) AS round";
    } else {
        $select[] =
            "0 AS round";
    }

    $stmt = $pdo->prepare(
        "SELECT
            " . implode(',', $select) . "

         FROM deliveries

         WHERE delivery_date >= ?
           AND delivery_date < ?

         ORDER BY delivery_date ASC"
    );

    $stmt->execute([
        $start,
        $end
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $date =
            (string)$row['delivery_date'];

        if (!isset($result[$date])) {
            $result[$date] = [
                'revenue' => 0.00,
                'slim' => 0.00,
                'round' => 0.00,
                'deliveries' => 0
            ];
        }

        $result[$date]['revenue'] +=
            (float)$row['revenue'];

        $result[$date]['slim'] +=
            (float)$row['slim'];

        $result[$date]['round'] +=
            (float)$row['round'];

        $result[$date]['deliveries']++;
    }

    foreach ($result as $date => $data) {

        $result[$date]['revenue'] =
            money($data['revenue']);

        $result[$date]['slim'] =
            money($data['slim']);

        $result[$date]['round'] =
            money($data['round']);
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Expenses
|--------------------------------------------------------------------------
*/

function calculateExpenses(
    PDO $pdo,
    string $start,
    string $end
): float {

    if (!tableExists($pdo, 'expenses')) {
        return 0.00;
    }

    $dateColumn = null;

    foreach ([
        'expense_date',
        'date',
        'business_date'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'expenses',
                $column
            )
        ) {
            $dateColumn = $column;
            break;
        }
    }

    if (!$dateColumn) {
        return 0.00;
    }

    $amountColumn = null;

    foreach ([
        'amount',
        'expense_amount'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'expenses',
                $column
            )
        ) {
            $amountColumn = $column;
            break;
        }
    }

    if (!$amountColumn) {
        return 0.00;
    }

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(
                SUM($amountColumn),
                0
            )

         FROM expenses

         WHERE $dateColumn >= ?
           AND $dateColumn < ?"
    );

    $stmt->execute([
        $start,
        $end
    ]);

    return money(
        (float)$stmt->fetchColumn()
    );
}

/*
|--------------------------------------------------------------------------
| Daily Expenses
|--------------------------------------------------------------------------
*/

function calculateDailyExpenses(
    PDO $pdo,
    string $start,
    string $end
): array {

    $result = [];

    if (!tableExists($pdo, 'expenses')) {
        return $result;
    }

    $dateColumn = null;

    foreach ([
        'expense_date',
        'date',
        'business_date'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'expenses',
                $column
            )
        ) {
            $dateColumn = $column;
            break;
        }
    }

    if (!$dateColumn) {
        return $result;
    }

    $amountColumn = null;

    foreach ([
        'amount',
        'expense_amount'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'expenses',
                $column
            )
        ) {
            $amountColumn = $column;
            break;
        }
    }

    if (!$amountColumn) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT
            $dateColumn AS expense_date,
            COALESCE(
                SUM($amountColumn),
                0
            ) AS expenses

         FROM expenses

         WHERE $dateColumn >= ?
           AND $dateColumn < ?

         GROUP BY $dateColumn

         ORDER BY $dateColumn ASC"
    );

    $stmt->execute([
        $start,
        $end
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $date =
            (string)$row['expense_date'];

        $result[$date] =
            money(
                (float)$row['expenses']
            );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Customer Statistics
|--------------------------------------------------------------------------
*/

function calculateCustomerStats(
    PDO $pdo,
    string $start,
    string $end
): array {

    $result = [
        'served' => 0,
        'new' => 0
    ];

    if (!tableExists($pdo, 'customers')) {
        return $result;
    }

    if (
        tableExists($pdo, 'deliveries') &&
        columnExists(
            $pdo,
            'deliveries',
            'customer_id'
        ) &&
        columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        )
    ) {

        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT customer_id)

             FROM deliveries

             WHERE customer_id IS NOT NULL
               AND delivery_date >= ?
               AND delivery_date < ?"
        );

        $stmt->execute([
            $start,
            $end
        ]);

        $result['served'] =
            (int)$stmt->fetchColumn();
    }

    if (
        columnExists(
            $pdo,
            'customers',
            'created_at'
        )
    ) {

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)

             FROM customers

             WHERE created_at >= ?
               AND created_at < ?"
        );

        $stmt->execute([
            $start,
            $end
        ]);

        $result['new'] =
            (int)$stmt->fetchColumn();
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Outstanding Debt — Current
|--------------------------------------------------------------------------
*/

function calculateOutstandingDebt(
    PDO $pdo
): array {

    $result = [
        'total' => 0.00,
        'customers' => 0
    ];

    if (
        !tableExists($pdo, 'deliveries') ||
        !tableExists($pdo, 'payments')
    ) {
        return $result;
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_id'
        )
    ) {
        return $result;
    }

    $stmt = $pdo->query(
        "SELECT

            d.customer_id,

            COALESCE(
                d.amount_due,
                0
            )
            -
            COALESCE(
                SUM(p.amount),
                0
            ) AS remaining

         FROM deliveries d

         LEFT JOIN payments p
            ON p.delivery_id =
               d.delivery_id

         GROUP BY
            d.delivery_id,
            d.customer_id,
            d.amount_due"
    );

    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    $customers = [];

    foreach ($rows as $row) {

        $remaining =
            max(
                (float)$row['remaining'],
                0
            );

        if ($remaining <= 0) {
            continue;
        }

        $result['total'] +=
            $remaining;

        $customerId =
            (int)$row['customer_id'];

        if ($customerId > 0) {
            $customers[$customerId] = true;
        }
    }

    $result['total'] =
        money($result['total']);

    $result['customers'] =
        count($customers);

    return $result;
}

/*
|--------------------------------------------------------------------------
| Historical Outstanding Debt
|--------------------------------------------------------------------------
|
| Calculates the debt that existed at the END of the selected month.
|
| Delivery is included when it was created on/before the selected
| month-end.
|
| Payments are included when they were made on/before the selected
| month-end.
|
|--------------------------------------------------------------------------
*/

function calculateHistoricalOutstandingDebt(
    PDO $pdo,
    string $end
): array {

    $result = [
        'total' => 0.00,
        'customers' => 0
    ];

    if (
        !tableExists($pdo, 'deliveries') ||
        !tableExists($pdo, 'payments')
    ) {
        return $result;
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_id'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'customer_id'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        ) ||
        !columnExists(
            $pdo,
            'payments',
            'payment_date'
        ) ||
        !columnExists(
            $pdo,
            'payments',
            'delivery_id'
        )
    ) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT

            d.delivery_id,

            d.customer_id,

            COALESCE(
                d.amount_due,
                0
            ) AS amount_due,

            COALESCE(
                (
                    SELECT SUM(p.amount)

                    FROM payments p

                    WHERE p.delivery_id =
                          d.delivery_id

                      AND p.payment_date < ?
                ),
                0
            ) AS paid_amount

         FROM deliveries d

         WHERE d.delivery_date < ?"
    );

    $stmt->execute([
        $end,
        $end
    ]);

    $customers = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $remaining =
            (float)$row['amount_due']
            -
            (float)$row['paid_amount'];

        $remaining =
            max(
                $remaining,
                0
            );

        if ($remaining <= 0) {
            continue;
        }

        $result['total'] +=
            $remaining;

        $customerId =
            (int)$row['customer_id'];

        if ($customerId > 0) {
            $customers[$customerId] = true;
        }
    }

    $result['total'] =
        money($result['total']);

    $result['customers'] =
        count($customers);

    return $result;
}

/*
|--------------------------------------------------------------------------
| Top Customers
|--------------------------------------------------------------------------
*/

function calculateTopCustomers(
    PDO $pdo,
    string $start,
    string $end,
    int $limit = 5
): array {

    $result = [];

    if (
        !tableExists($pdo, 'deliveries') ||
        !tableExists($pdo, 'customers')
    ) {
        return $result;
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'customer_id'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        ) ||
        !columnExists(
            $pdo,
            'customers',
            'customer_id'
        ) ||
        !columnExists(
            $pdo,
            'customers',
            'customer_name'
        )
    ) {
        return $result;
    }

    $limit =
        max(
            1,
            min($limit, 20)
        );

    $stmt = $pdo->prepare(
        "SELECT

            c.customer_id,

            c.customer_name,

            COUNT(d.delivery_id)
                AS deliveries,

            COALESCE(
                SUM(d.amount_due),
                0
            ) AS revenue,

            COALESCE(
                SUM(d.slim_quantity),
                0
            ) AS slim_gallons,

            COALESCE(
                SUM(d.round_quantity),
                0
            ) AS round_gallons

         FROM deliveries d

         INNER JOIN customers c
            ON c.customer_id =
               d.customer_id

         WHERE d.customer_id IS NOT NULL
           AND d.delivery_date >= ?
           AND d.delivery_date < ?

         GROUP BY
            c.customer_id,
            c.customer_name

         ORDER BY
            revenue DESC

         LIMIT $limit"
    );

    $stmt->execute([
        $start,
        $end
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $result[] = [
            'customer_id' =>
                (int)$row['customer_id'],

            'customer_name' =>
                (string)$row['customer_name'],

            'deliveries' =>
                (int)$row['deliveries'],

            'revenue' =>
                money(
                    (float)$row['revenue']
                ),

            'slim_gallons' =>
                money(
                    (float)$row['slim_gallons']
                ),

            'round_gallons' =>
                money(
                    (float)$row['round_gallons']
                ),

            'total_gallons' =>
                money(
                    (float)$row['slim_gallons']
                    +
                    (float)$row['round_gallons']
                )
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Outstanding Customer Balances
|--------------------------------------------------------------------------
*/

function calculateOutstandingBalances(
    PDO $pdo,
    int $limit = 10
): array {

    $result = [];

    if (
        !tableExists($pdo, 'deliveries') ||
        !tableExists($pdo, 'payments') ||
        !tableExists($pdo, 'customers')
    ) {
        return $result;
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_id'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'customer_id'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        ) ||
        !columnExists(
            $pdo,
            'customers',
            'customer_id'
        ) ||
        !columnExists(
            $pdo,
            'customers',
            'customer_name'
        )
    ) {
        return $result;
    }

    $limit =
        max(
            1,
            min($limit, 50)
        );

    $stmt = $pdo->query(
        "SELECT

            c.customer_id,

            c.customer_name,

            COALESCE(
                SUM(d.amount_due),
                0
            )
            -
            COALESCE(
                SUM(
                    COALESCE(
                        payment_totals.paid_amount,
                        0
                    )
                ),
                0
            ) AS balance

         FROM customers c

         INNER JOIN deliveries d
            ON d.customer_id =
               c.customer_id

         LEFT JOIN (

            SELECT
                delivery_id,
                SUM(amount) AS paid_amount

            FROM payments

            GROUP BY delivery_id

         ) payment_totals

            ON payment_totals.delivery_id =
               d.delivery_id

         GROUP BY
            c.customer_id,
            c.customer_name

         HAVING balance > 0

         ORDER BY
            balance DESC

         LIMIT $limit"
    );

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $result[] = [
            'customer_id' =>
                (int)$row['customer_id'],

            'customer_name' =>
                (string)$row['customer_name'],

            'balance' =>
                money(
                    (float)$row['balance']
                )
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Recent Customers
|--------------------------------------------------------------------------
*/

function calculateRecentCustomers(
    PDO $pdo,
    int $limit = 8
): array {

    $result = [];

    if (!tableExists($pdo, 'customers')) {
        return $result;
    }

    if (
        !columnExists(
            $pdo,
            'customers',
            'customer_id'
        ) ||
        !columnExists(
            $pdo,
            'customers',
            'customer_name'
        )
    ) {
        return $result;
    }

    $limit =
        max(
            1,
            min($limit, 20)
        );

    $dateColumn = null;

    foreach ([
        'created_at',
        'created_date'
    ] as $column) {

        if (
            columnExists(
                $pdo,
                'customers',
                $column
            )
        ) {
            $dateColumn = $column;
            break;
        }
    }

    if (!$dateColumn) {

        $stmt = $pdo->query(
            "SELECT
                customer_id,
                customer_name

             FROM customers

             ORDER BY customer_id DESC

             LIMIT $limit"
        );

    } else {

        $stmt = $pdo->query(
            "SELECT
                customer_id,
                customer_name,
                $dateColumn AS created_at

             FROM customers

             ORDER BY $dateColumn DESC

             LIMIT $limit"
        );
    }

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $result[] = [
            'customer_id' =>
                (int)$row['customer_id'],

            'customer_name' =>
                (string)$row['customer_name'],

            'created_at' =>
                isset($row['created_at'])
                    ? (string)$row['created_at']
                    : null
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Generate Monthly Analytics
|--------------------------------------------------------------------------
*/

function generateMonthlyAnalytics(
    PDO $pdo,
    int $year,
    int $month
): array {

    $start =
        monthStart(
            $year,
            $month
        );

    $end =
        nextMonthStart(
            $year,
            $month
        );

    $walkInRevenue =
        calculateWalkInRevenue(
            $pdo,
            $start,
            $end
        );

    $delivery =
        calculateDeliveryStats(
            $pdo,
            $start,
            $end
        );

    $expenses =
        calculateExpenses(
            $pdo,
            $start,
            $end
        );

    $customers =
        calculateCustomerStats(
            $pdo,
            $start,
            $end
        );

    /*
     * Important:
     * Historical analytics use debt as of the end
     * of the selected month rather than today's debt.
     */

    $debt =
        calculateHistoricalOutstandingDebt(
            $pdo,
            $end
        );

    $totalRevenue =
        money(
            $walkInRevenue
            +
            $delivery['revenue']
        );

    $netRevenue =
        money(
            $totalRevenue
            -
            $expenses
        );

    $totalGallons =
        money(
            $delivery['slim']
            +
            $delivery['round']
        );

    $stmt = $pdo->prepare(
        "INSERT INTO monthly_analytics
            (
                analytics_year,
                analytics_month,

                total_revenue,
                walk_in_revenue,
                delivery_revenue,

                total_expenses,
                net_revenue,

                slim_gallons,
                round_gallons,
                total_gallons,

                total_deliveries,
                customers_served,
                new_customers,

                customers_with_debt,
                outstanding_debt
            )

         VALUES
            (
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?
            )

         ON DUPLICATE KEY UPDATE

            total_revenue =
                VALUES(total_revenue),

            walk_in_revenue =
                VALUES(walk_in_revenue),

            delivery_revenue =
                VALUES(delivery_revenue),

            total_expenses =
                VALUES(total_expenses),

            net_revenue =
                VALUES(net_revenue),

            slim_gallons =
                VALUES(slim_gallons),

            round_gallons =
                VALUES(round_gallons),

            total_gallons =
                VALUES(total_gallons),

            total_deliveries =
                VALUES(total_deliveries),

            customers_served =
                VALUES(customers_served),

            new_customers =
                VALUES(new_customers),

            customers_with_debt =
                VALUES(customers_with_debt),

            outstanding_debt =
                VALUES(outstanding_debt)"
    );

    $stmt->execute([
        $year,
        $month,

        $totalRevenue,
        $walkInRevenue,
        $delivery['revenue'],

        $expenses,
        $netRevenue,

        $delivery['slim'],
        $delivery['round'],
        $totalGallons,

        $delivery['deliveries'],
        $customers['served'],
        $customers['new'],

        $debt['customers'],
        $debt['total']
    ]);

    return [
        'year' =>
            $year,

        'month' =>
            $month,

        'total_revenue' =>
            $totalRevenue,

        'walk_in_revenue' =>
            $walkInRevenue,

        'delivery_revenue' =>
            $delivery['revenue'],

        'total_expenses' =>
            $expenses,

        'net_revenue' =>
            $netRevenue,

        'slim_gallons' =>
            $delivery['slim'],

        'round_gallons' =>
            $delivery['round'],

        'total_gallons' =>
            $totalGallons,

        'total_deliveries' =>
            $delivery['deliveries'],

        'customers_served' =>
            $customers['served'],

        'new_customers' =>
            $customers['new'],

        'customers_with_debt' =>
            $debt['customers'],

        'outstanding_debt' =>
            $debt['total']
    ];
}

/*
|--------------------------------------------------------------------------
| Dashboard Data
|--------------------------------------------------------------------------
*/

function actionDashboard(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    $year =
        postInt(
            'year',
            (int)date('Y')
        );

    $month =
        postInt(
            'month',
            (int)date('n')
        );

    if (
        $year < 2000 ||
        $year > 2100 ||
        $month < 1 ||
        $month > 12
    ) {

        respond([
            'success' => false,
            'message' => 'Invalid month.'
        ], 400);
    }

    /*
     * Always regenerate the selected month so the
     * dashboard reflects the latest records.
     */

    $analytics =
        generateMonthlyAnalytics(
            $pdo,
            $year,
            $month
        );

    $start =
        monthStart(
            $year,
            $month
        );

    $end =
        nextMonthStart(
            $year,
            $month
        );

    $topCustomers =
        calculateTopCustomers(
            $pdo,
            $start,
            $end,
            5
        );

    $outstandingBalances =
        calculateOutstandingBalances(
            $pdo,
            10
        );

    $recentCustomers =
        calculateRecentCustomers(
            $pdo,
            8
        );

    respond([
        'success' => true,

        'analytics' =>
            $analytics,

        'top_customers' =>
            $topCustomers,

        'outstanding_balances' =>
            $outstandingBalances,

        'recent_customers' =>
            $recentCustomers
    ]);
}

/*
|--------------------------------------------------------------------------
| Daily Chart
|--------------------------------------------------------------------------
*/

function actionDailyChart(PDO $pdo): void
{
    $year =
        postInt(
            'year',
            (int)date('Y')
        );

    $month =
        postInt(
            'month',
            (int)date('n')
        );

    if (
        $year < 2000 ||
        $year > 2100 ||
        $month < 1 ||
        $month > 12
    ) {

        respond([
            'success' => false,
            'message' => 'Invalid month.'
        ], 400);
    }

    $start =
        monthStart(
            $year,
            $month
        );

    $end =
        nextMonthStart(
            $year,
            $month
        );

    $walkIns =
        calculateDailyWalkIns(
            $pdo,
            $start,
            $end
        );

    $deliveries =
        calculateDailyDeliveries(
            $pdo,
            $start,
            $end
        );

    $expenses =
        calculateDailyExpenses(
            $pdo,
            $start,
            $end
        );

    $days =
        [];

    $date =
        new DateTimeImmutable(
            $start
        );

    $endDate =
        new DateTimeImmutable(
            $end
        );

    while ($date < $endDate) {

        $dateKey =
            $date->format('Y-m-d');

        $deliveryData =
            $deliveries[$dateKey]
            ??
            [
                'revenue' => 0.00,
                'slim' => 0.00,
                'round' => 0.00,
                'deliveries' => 0
            ];

        $walkIn =
            $walkIns[$dateKey]
            ?? 0.00;

        $expense =
            $expenses[$dateKey]
            ?? 0.00;

        $revenue =
            money(
                $walkIn
                +
                $deliveryData['revenue']
            );

        $days[] = [
            'date' =>
                $dateKey,

            'day' =>
                (int)$date->format('j'),

            'label' =>
                $date->format('M j'),

            'revenue' =>
                $revenue,

            'walk_in_revenue' =>
                money($walkIn),

            'delivery_revenue' =>
                money(
                    $deliveryData['revenue']
                ),

            'expenses' =>
                money($expense),

            'net_revenue' =>
                money(
                    $revenue
                    -
                    $expense
                ),

            'slim_gallons' =>
                money(
                    $deliveryData['slim']
                ),

            'round_gallons' =>
                money(
                    $deliveryData['round']
                ),

            'total_gallons' =>
                money(
                    $deliveryData['slim']
                    +
                    $deliveryData['round']
                ),

            'deliveries' =>
                (int)$deliveryData['deliveries']
        ];

        $date =
            $date->modify('+1 day');
    }

    respond([
        'success' => true,

        'year' =>
            $year,

        'month' =>
            $month,

        'days' =>
            $days
    ]);
}

/*
|--------------------------------------------------------------------------
| Top Customers
|--------------------------------------------------------------------------
*/

function actionTopCustomers(PDO $pdo): void
{
    $year =
        postInt(
            'year',
            (int)date('Y')
        );

    $month =
        postInt(
            'month',
            (int)date('n')
        );

    $limit =
        max(
            1,
            min(
                postInt(
                    'limit',
                    5
                ),
                20
            )
        );

    if (
        $year < 2000 ||
        $year > 2100 ||
        $month < 1 ||
        $month > 12
    ) {

        respond([
            'success' => false,
            'message' => 'Invalid month.'
        ], 400);
    }

    $customers =
        calculateTopCustomers(
            $pdo,
            monthStart(
                $year,
                $month
            ),
            nextMonthStart(
                $year,
                $month
            ),
            $limit
        );

    respond([
        'success' => true,

        'customers' =>
            $customers
    ]);
}

/*
|--------------------------------------------------------------------------
| Outstanding Balances
|--------------------------------------------------------------------------
*/

function actionOutstandingBalances(PDO $pdo): void
{
    $limit =
        max(
            1,
            min(
                postInt(
                    'limit',
                    10
                ),
                50
            )
        );

    $balances =
        calculateOutstandingBalances(
            $pdo,
            $limit
        );

    respond([
        'success' => true,

        'balances' =>
            $balances
    ]);
}

/*
|--------------------------------------------------------------------------
| Recent Customers
|--------------------------------------------------------------------------
*/

function actionRecentCustomers(PDO $pdo): void
{
    $limit =
        max(
            1,
            min(
                postInt(
                    'limit',
                    8
                ),
                20
            )
        );

    $customers =
        calculateRecentCustomers(
            $pdo,
            $limit
        );

    respond([
        'success' => true,

        'customers' =>
            $customers
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Ensure Schema
|--------------------------------------------------------------------------
*/

function actionEnsureSchema(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    respond([
        'success' => true,

        'message' =>
            'Monthly analytics table is ready.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Generate Month
|--------------------------------------------------------------------------
*/

function actionGenerateMonth(PDO $pdo): void
{
    $year =
        postInt(
            'year',
            (int)date('Y')
        );

    $month =
        postInt(
            'month',
            (int)date('n')
        );

    if (
        $year < 2000 ||
        $year > 2100 ||
        $month < 1 ||
        $month > 12
    ) {

        respond([
            'success' => false,
            'message' =>
                'Invalid month.'
        ], 400);
    }

    ensureAnalyticsSchema($pdo);

    $analytics =
        generateMonthlyAnalytics(
            $pdo,
            $year,
            $month
        );

    respond([
        'success' => true,

        'analytics' =>
            $analytics
    ]);
}

/*
|--------------------------------------------------------------------------
| Action: Get History
|--------------------------------------------------------------------------
*/

function actionHistory(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    $limit =
        max(
            1,
            min(
                postInt(
                    'limit',
                    24
                ),
                120
            )
        );

    $stmt = $pdo->prepare(
        "SELECT *

         FROM monthly_analytics

         ORDER BY
            analytics_year DESC,
            analytics_month DESC

         LIMIT $limit"
    );

    $stmt->execute();

    respond([
        'success' => true,

        'analytics' =>
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

try {

    $action =
        postString(
            'action',
            'ensure_schema'
        );

    switch ($action) {

        case 'ensure_schema':

            actionEnsureSchema($pdo);

            break;

        case 'generate_month':

            actionGenerateMonth($pdo);

            break;

        case 'dashboard':

            actionDashboard($pdo);

            break;

        case 'daily_chart':

            actionDailyChart($pdo);

            break;

        case 'top_customers':

            actionTopCustomers($pdo);

            break;

        case 'outstanding_balances':

            actionOutstandingBalances($pdo);

            break;

        case 'recent_customers':

            actionRecentCustomers($pdo);

            break;

        case 'history':

            actionHistory($pdo);

            break;

        default:

            respond([
                'success' => false,

                'message' =>
                    'Unknown action.'
            ], 400);
    }

} catch (Throwable $e) {

    respond([
        'success' => false,

        'message' =>
            $e->getMessage(),

        'error_type' =>
            get_class($e)
    ], 500);
}