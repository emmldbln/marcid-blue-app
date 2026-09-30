<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Response helpers
|--------------------------------------------------------------------------
*/

function respond(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function postString(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function postInt(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $default);
}

function postFloat(string $key, float $default = 0.0): float
{
    return (float)($_POST[$key] ?? $default);
}

/*
|--------------------------------------------------------------------------
| Database helpers
|--------------------------------------------------------------------------
*/

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?"
    );

    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
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
| Date helpers
|--------------------------------------------------------------------------
*/

function validDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parsed = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}

function dateObject(string $date): DateTimeImmutable
{
    if (!validDate($date)) {
        throw new InvalidArgumentException(
            'Invalid date: ' . $date
        );
    }

    return new DateTimeImmutable($date);
}

function exclusiveEnd(string $inclusiveEnd): string
{
    return dateObject($inclusiveEnd)
        ->modify('+1 day')
        ->format('Y-m-d');
}

function formatPeriodRange(string $start, string $end): string
{
    return dateObject($start)->format('M j, Y')
        . ' – '
        . dateObject($end)->format('M j, Y');
}

/*
|--------------------------------------------------------------------------
| Accounting period rules
|--------------------------------------------------------------------------
*/

function minimumAnalyticsYear(): int
{
    return 2026;
}

function minimumAnalyticsMonth(): int
{
    return 9;
}

function isAllowedAnalyticsMonth(
    int $year,
    int $month
): bool {
    if ($year < minimumAnalyticsYear()) {
        return false;
    }

    if (
        $year === minimumAnalyticsYear()
        && $month < minimumAnalyticsMonth()
    ) {
        return false;
    }

    return $month >= 1 && $month <= 12;
}

function defaultPeriodDates(
    int $year,
    int $month
): array {
    if (!isAllowedAnalyticsMonth($year, $month)) {
        throw new InvalidArgumentException(
            'Analytics periods can only start from September 2026.'
        );
    }

    /*
     * First accounting period:
     *
     * September 2026
     * August 15, 2026 -> September 15, 2026
     */
    if ($year === 2026 && $month === 9) {
        return [
            'start' => '2026-08-15',
            'end'   => '2026-09-15'
        ];
    }

    /*
     * Standard periods:
     *
     * Previous month's 16th
     * ->
     * Current month's 15th
     */
    $end = new DateTimeImmutable(
        sprintf(
            '%04d-%02d-15',
            $year,
            $month
        )
    );

    $start = $end
        ->modify('-1 month')
        ->modify('+1 day');

    return [
        'start' => $start->format('Y-m-d'),
        'end'   => $end->format('Y-m-d')
    ];
}

/*
|--------------------------------------------------------------------------
| Analytics schema
|--------------------------------------------------------------------------
*/

function ensureAnalyticsSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS analytics_periods (
            period_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            analytics_year SMALLINT UNSIGNED NOT NULL,
            analytics_month TINYINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (period_id),
            UNIQUE KEY unique_analytics_period (
                analytics_year,
                analytics_month
            ),
            KEY idx_period_dates (
                period_start,
                period_end
            )
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS analytics_manual_expenses (
            expense_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            period_id INT UNSIGNED NOT NULL,
            end_period_id INT UNSIGNED DEFAULT NULL,
            category VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            expense_date DATE NOT NULL,
            expense_end_date DATE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (expense_id),
            KEY idx_manual_expenses_period (period_id),
            KEY idx_manual_expenses_date (expense_date),
            KEY idx_manual_expenses_end_period (end_period_id),
            CONSTRAINT fk_manual_expenses_period
                FOREIGN KEY (period_id)
                REFERENCES analytics_periods(period_id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,
            CONSTRAINT fk_manual_expenses_end_period
                FOREIGN KEY (end_period_id)
                REFERENCES analytics_periods(period_id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        )
        ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_unicode_ci"
    );
}

/*
|--------------------------------------------------------------------------
| Period creation
|--------------------------------------------------------------------------
*/

function ensureDefaultPeriod(PDO $pdo): void
{
    $dates = defaultPeriodDates(2026, 9);

    $stmt = $pdo->prepare(
        "INSERT INTO analytics_periods (
            analytics_year,
            analytics_month,
            period_start,
            period_end
        )
        VALUES (
            2026,
            9,
            ?,
            ?
        )
        ON DUPLICATE KEY UPDATE
            period_id = period_id"
    );

    $stmt->execute([
        $dates['start'],
        $dates['end']
    ]);
}

function ensurePeriod(
    PDO $pdo,
    int $year,
    int $month
): int {
    if (!isAllowedAnalyticsMonth($year, $month)) {
        throw new InvalidArgumentException(
            'Invalid accounting period.'
        );
    }

    $stmt = $pdo->prepare(
        "SELECT period_id
         FROM analytics_periods
         WHERE analytics_year = ?
           AND analytics_month = ?
         LIMIT 1"
    );

    $stmt->execute([
        $year,
        $month
    ]);

    $existing = $stmt->fetchColumn();

    if ($existing !== false) {
        return (int)$existing;
    }

    $dates = defaultPeriodDates(
        $year,
        $month
    );

    $stmt = $pdo->prepare(
        "INSERT INTO analytics_periods (
            analytics_year,
            analytics_month,
            period_start,
            period_end
        )
        VALUES (
            ?,
            ?,
            ?,
            ?
        )"
    );

    $stmt->execute([
        $year,
        $month,
        $dates['start'],
        $dates['end']
    ]);

    return (int)$pdo->lastInsertId();
}

function getPeriod(
    PDO $pdo,
    int $periodId
): array {
    $stmt = $pdo->prepare(
        "SELECT
            period_id,
            analytics_year,
            analytics_month,
            period_start,
            period_end
         FROM analytics_periods
         WHERE period_id = ?
         LIMIT 1"
    );

    $stmt->execute([$periodId]);

    $period = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$period) {
        throw new InvalidArgumentException(
            'Invalid accounting period.'
        );
    }

    if (
        !isAllowedAnalyticsMonth(
            (int)$period['analytics_year'],
            (int)$period['analytics_month']
        )
    ) {
        throw new InvalidArgumentException(
            'Invalid accounting period.'
        );
    }

    return $period;
}

function getPeriods(PDO $pdo): array
{
    ensureDefaultPeriod($pdo);

    $today = new DateTimeImmutable('today');

    /*
     * Generate periods from September 2026
     * through the next calendar month.
     */
    $cursor = new DateTimeImmutable('2026-09-01');
    $target = $today
        ->modify('+1 month')
        ->modify('first day of this month');

    while ($cursor <= $target) {
        ensurePeriod(
            $pdo,
            (int)$cursor->format('Y'),
            (int)$cursor->format('n')
        );

        $cursor = $cursor->modify('+1 month');
    }

    $stmt = $pdo->query(
        "SELECT
            period_id,
            analytics_year,
            analytics_month,
            period_start,
            period_end
         FROM analytics_periods
         WHERE
            analytics_year > 2026
            OR (
                analytics_year = 2026
                AND analytics_month >= 9
            )
         ORDER BY period_start DESC"
    );

    $periods = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $start = (string)$row['period_start'];
        $end   = (string)$row['period_end'];

        $year  = (int)$row['analytics_year'];
        $month = (int)$row['analytics_month'];

        $periods[] = [
            'period_id'    => (int)$row['period_id'],
            'year'         => $year,
            'month'        => $month,
            'start'        => $start,
            'end'          => $end,
            'period_start' => $start,
            'period_end'   => $end,
            'label'        => formatPeriodRange($start, $end),
            'month_label'  => dateObject(
                sprintf(
                    '%04d-%02d-01',
                    $year,
                    $month
                )
            )->format('F Y')
        ];
    }

    return $periods;
}

/*
|--------------------------------------------------------------------------
| Save accounting period
|--------------------------------------------------------------------------
*/

function savePeriod(PDO $pdo): void
{
    $periodId = postInt('period_id');
    $start    = postString('period_start');
    $end      = postString('period_end');

    if ($periodId <= 0) {
        respond([
            'success' => false,
            'message' => 'Invalid accounting period.'
        ], 400);
    }

    if (
        !validDate($start)
        || !validDate($end)
    ) {
        respond([
            'success' => false,
            'message' => 'Please enter valid period dates.'
        ], 400);
    }

    if ($start > $end) {
        respond([
            'success' => false,
            'message' =>
                'Start date cannot be after the end date.'
        ], 400);
    }

    getPeriod(
        $pdo,
        $periodId
    );

    $stmt = $pdo->prepare(
        "UPDATE analytics_periods
         SET
            period_start = ?,
            period_end = ?
         WHERE period_id = ?"
    );

    $stmt->execute([
        $start,
        $end,
        $periodId
    ]);

    respond([
        'success' => true,
        'message' => 'Accounting period saved.',
        'period'  => getPeriod(
            $pdo,
            $periodId
        )
    ]);
}

/*
|--------------------------------------------------------------------------
| DAILY CLOSING REVENUE
|--------------------------------------------------------------------------
|
| The Daily Closing application stores shop/walk-in sales in:
|
| daily_sales
|
| Relevant columns:
|
| sales_date
| walk_in_customers
| walk_in_price
| other_shop_payment
| other_sales
|
| Delivery collections are handled separately through:
|
| deliveries
| payments
|
|--------------------------------------------------------------------------
*/

function dailySalesRevenueExpression(
    PDO $pdo
): ?string {
    if (!tableExists($pdo, 'daily_sales')) {
        return null;
    }

    $parts = [];

    if (
        columnExists(
            $pdo,
            'daily_sales',
            'walk_in_customers'
        )
        &&
        columnExists(
            $pdo,
            'daily_sales',
            'walk_in_price'
        )
    ) {
        $parts[] =
            "(COALESCE(walk_in_customers, 0)
              * COALESCE(walk_in_price, 0))";
    }

    if (
        columnExists(
            $pdo,
            'daily_sales',
            'other_shop_payment'
        )
    ) {
        $parts[] =
            "COALESCE(other_shop_payment, 0)";
    }

    if (
        columnExists(
            $pdo,
            'daily_sales',
            'other_sales'
        )
    ) {
        $parts[] =
            "COALESCE(other_sales, 0)";
    }

    if (!$parts) {
        return null;
    }

    return implode(' + ', $parts);
}

function calculateWalkInRevenue(
    PDO $pdo,
    string $start,
    string $endExclusive
): float {
    if (
        !tableExists($pdo, 'daily_sales')
        || !columnExists(
            $pdo,
            'daily_sales',
            'sales_date'
        )
    ) {
        return 0.00;
    }

    $expression =
        dailySalesRevenueExpression($pdo);

    if ($expression === null) {
        return 0.00;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(
            SUM($expression),
            0
        )
        FROM daily_sales
        WHERE sales_date >= ?
          AND sales_date < ?"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    return money(
        (float)$stmt->fetchColumn()
    );
}

function calculateDailyWalkInRevenue(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {
    $result = [];

    if (
        !tableExists($pdo, 'daily_sales')
        || !columnExists(
            $pdo,
            'daily_sales',
            'sales_date'
        )
    ) {
        return $result;
    }

    $expression =
        dailySalesRevenueExpression($pdo);

    if ($expression === null) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sales_date,
            COALESCE(
                SUM($expression),
                0
            ) AS revenue
         FROM daily_sales
         WHERE sales_date >= ?
           AND sales_date < ?
         GROUP BY sales_date
         ORDER BY sales_date"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $result[
            $row['sales_date']
        ] = money(
            (float)$row['revenue']
        );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| DELIVERY REVENUE
|--------------------------------------------------------------------------
|
| Revenue for the accounting dashboard uses actual payments received
| through Daily Closing, rather than expected delivery amount_due.
|
| This means:
|
| unpaid delivery      = 0 revenue
| partially paid       = actual amount received
| fully paid           = actual amount received
| overpaid             = actual amount received
|
|--------------------------------------------------------------------------
*/

function calculateDeliveryRevenue(
    PDO $pdo,
    string $start,
    string $endExclusive
): float {
    if (
        !tableExists($pdo, 'deliveries')
        || !tableExists($pdo, 'payments')
    ) {
        return 0.00;
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_id'
        )
        || !columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        )
        || !columnExists(
            $pdo,
            'payments',
            'delivery_id'
        )
        || !columnExists(
            $pdo,
            'payments',
            'amount'
        )
        || !columnExists(
            $pdo,
            'payments',
            'payment_date'
        )
    ) {
        return 0.00;
    }

    /*
     * Payment date determines when money was actually received.
     *
     * This is important for accounting-period revenue.
     */
    $stmt = $pdo->prepare(
        "SELECT COALESCE(
            SUM(p.amount),
            0
        )
        FROM payments p
        WHERE p.payment_date >= ?
          AND p.payment_date < ?
          AND p.delivery_id IS NOT NULL"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    return money(
        (float)$stmt->fetchColumn()
    );
}

function calculateDailyDeliveryRevenue(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {
    $result = [];

    if (
        !tableExists($pdo, 'payments')
        || !columnExists(
            $pdo,
            'payments',
            'payment_date'
        )
        || !columnExists(
            $pdo,
            'payments',
            'amount'
        )
    ) {
        return $result;
    }

    $deliveryFilter = '';

    if (
        columnExists(
            $pdo,
            'payments',
            'delivery_id'
        )
    ) {
        $deliveryFilter =
            ' AND p.delivery_id IS NOT NULL';
    } else {
        /*
         * If delivery_id does not exist, do not treat all
         * payments as delivery revenue.
         */
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT
            p.payment_date,
            COALESCE(
                SUM(p.amount),
                0
            ) AS revenue
         FROM payments p
         WHERE p.payment_date >= ?
           AND p.payment_date < ?
           $deliveryFilter
         GROUP BY p.payment_date
         ORDER BY p.payment_date"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $result[
            $row['payment_date']
        ] = money(
            (float)$row['revenue']
        );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Combined daily revenue
|--------------------------------------------------------------------------
*/

function calculateDailyRevenue(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {
    $walkIns = calculateDailyWalkInRevenue(
        $pdo,
        $start,
        $endExclusive
    );

    $deliveries = calculateDailyDeliveryRevenue(
        $pdo,
        $start,
        $endExclusive
    );

    $dates = array_unique(
        array_merge(
            array_keys($walkIns),
            array_keys($deliveries)
        )
    );

    $result = [];

    foreach ($dates as $date) {
        $walkIn = $walkIns[$date] ?? 0.00;
        $delivery = $deliveries[$date] ?? 0.00;

        $result[$date] = [
            'walk_in_revenue' =>
                money($walkIn),

            'delivery_revenue' =>
                money($delivery),

            'revenue' =>
                money(
                    $walkIn + $delivery
                )
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| DAILY CLOSING EXPENSES
|--------------------------------------------------------------------------
*/

function expenseDateColumn(PDO $pdo): ?string
{
    if (!tableExists($pdo, 'expenses')) {
        return null;
    }

    foreach ([
        'expense_date',
        'date',
        'transaction_date',
        'created_date'
    ] as $column) {
        if (
            columnExists(
                $pdo,
                'expenses',
                $column
            )
        ) {
            return $column;
        }
    }

    return null;
}

function expenseAmountColumn(PDO $pdo): ?string
{
    if (!tableExists($pdo, 'expenses')) {
        return null;
    }

    foreach ([
        'amount',
        'expense_amount',
        'total'
    ] as $column) {
        if (
            columnExists(
                $pdo,
                'expenses',
                $column
            )
        ) {
            return $column;
        }
    }

    return null;
}

function calculateExpenses(
    PDO $pdo,
    string $start,
    string $endExclusive
): float {
    $dateColumn =
        expenseDateColumn($pdo);

    $amountColumn =
        expenseAmountColumn($pdo);

    if (
        $dateColumn === null
        || $amountColumn === null
    ) {
        return 0.00;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(
            SUM(`$amountColumn`),
            0
        )
        FROM expenses
        WHERE `$dateColumn` >= ?
          AND `$dateColumn` < ?"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    return money(
        (float)$stmt->fetchColumn()
    );
}

function calculateDailyExpenses(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {
    $result = [];

    $dateColumn =
        expenseDateColumn($pdo);

    $amountColumn =
        expenseAmountColumn($pdo);

    if (
        $dateColumn === null
        || $amountColumn === null
    ) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT
            `$dateColumn` AS expense_date,
            COALESCE(
                SUM(`$amountColumn`),
                0
            ) AS amount
         FROM expenses
         WHERE `$dateColumn` >= ?
           AND `$dateColumn` < ?
         GROUP BY `$dateColumn`
         ORDER BY `$dateColumn`"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $result[
            $row['expense_date']
        ] = money(
            (float)$row['amount']
        );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| MANUAL ANALYTICS EXPENSES
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| The actual expense_date is authoritative.
|
| We do NOT allocate the expense across dates.
| We do NOT modify expense_date when the selected period changes.
|
| A manual expense belongs to one accounting period.
|
| Example:
|
| Period: Aug 15 -> Sep 15
| Expense date: Sep 15
| Amount: ₱5,000
|
| It stays on Sep 15.
|
|--------------------------------------------------------------------------
*/

function getManualExpenses(
    PDO $pdo,
    int $periodId
): array {
    if (
        !tableExists(
            $pdo,
            'analytics_manual_expenses'
        )
    ) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            e.expense_id,
            e.period_id,
            e.category,
            e.description,
            e.amount,
            e.expense_date,
            e.expense_end_date,
            e.notes,
            e.created_at,
            e.updated_at
         FROM analytics_manual_expenses e
         WHERE e.period_id = ?
         ORDER BY
            e.expense_date ASC,
            e.expense_id ASC"
    );

    $stmt->execute([
        $periodId
    ]);

    $expenses = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $expenses[] = [
            'expense_id' =>
                (int)$row['expense_id'],

            'period_id' =>
                (int)$row['period_id'],

            'category' =>
                (string)$row['category'],

            'description' =>
                (string)$row['description'],

            'amount' =>
                money((float)$row['amount']),

            'expense_date' =>
                (string)$row['expense_date'],

            'expense_end_date' =>
                $row['expense_end_date']
                    ? (string)$row['expense_end_date']
                    : null,

            'notes' =>
                $row['notes'] !== null
                    ? (string)$row['notes']
                    : null,

            'created_at' =>
                (string)$row['created_at'],

            'updated_at' =>
                (string)$row['updated_at']
        ];
    }

    return $expenses;
}

function calculateManualExpenseTotal(
    PDO $pdo,
    int $periodId
): float {
    if (
        !tableExists(
            $pdo,
            'analytics_manual_expenses'
        )
    ) {
        return 0.00;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(
            SUM(amount),
            0
        )
        FROM analytics_manual_expenses
        WHERE period_id = ?"
    );

    $stmt->execute([
        $periodId
    ]);

    return money(
        (float)$stmt->fetchColumn()
    );
}

function calculateDailyManualExpenses(
    PDO $pdo,
    int $periodId,
    string $start,
    string $endExclusive
): array {
    $result = [];

    if (
        !tableExists(
            $pdo,
            'analytics_manual_expenses'
        )
    ) {
        return $result;
    }

    /*
     * No allocation.
     *
     * Group directly by the stored expense_date.
     */
    $stmt = $pdo->prepare(
        "SELECT
            expense_date,
            COALESCE(
                SUM(amount),
                0
            ) AS amount
         FROM analytics_manual_expenses
         WHERE period_id = ?
           AND expense_date >= ?
           AND expense_date < ?
         GROUP BY expense_date
         ORDER BY expense_date"
    );

    $stmt->execute([
        $periodId,
        $start,
        $endExclusive
    ]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $result[
            $row['expense_date']
        ] = money(
            (float)$row['amount']
        );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Save manual Analytics expense
|--------------------------------------------------------------------------
|
| The UI now has:
|
| Period
| Category
| Description
| Amount
| Expense Date
|
| Old fields such as end_period_id, expense_end_date and notes
| remain in the database for compatibility, but new records do
| not use them.
|--------------------------------------------------------------------------
*/

function saveManualExpense(PDO $pdo): void
{
    $expenseId =
        postInt('expense_id');

    $periodId =
        postInt('period_id');

    $category =
        postString('category');

    $description =
        postString('description');

    $amount =
        postFloat('amount');

    $expenseDate =
        postString('expense_date');

    if ($periodId <= 0) {
        respond([
            'success' => false,
            'message' => 'Please select an accounting period.'
        ], 400);
    }

    $period =
        getPeriod(
            $pdo,
            $periodId
        );

    if ($category === '') {
        respond([
            'success' => false,
            'message' => 'Please enter an expense category.'
        ], 400);
    }

    if ($description === '') {
        respond([
            'success' => false,
            'message' => 'Please enter an expense description.'
        ], 400);
    }

    if ($amount <= 0) {
        respond([
            'success' => false,
            'message' => 'Expense amount must be greater than zero.'
        ], 400);
    }

    if (!validDate($expenseDate)) {
        respond([
            'success' => false,
            'message' => 'Please enter a valid expense date.'
        ], 400);
    }

    /*
     * The expense date must belong to the selected accounting period.
     */
    if (
        $expenseDate < $period['period_start']
        || $expenseDate > $period['period_end']
    ) {
        respond([
            'success' => false,
            'message' =>
                'The expense date must be within the selected accounting period.'
        ], 400);
    }

    /*
     * Deliberately force the old multi-period fields to NULL.
     *
     * The actual date remains exactly what the user entered.
     */
    $endPeriodId = null;
    $expenseEndDate = null;
    $notes = null;

    if ($expenseId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE analytics_manual_expenses
             SET
                period_id = ?,
                end_period_id = ?,
                category = ?,
                description = ?,
                amount = ?,
                expense_date = ?,
                expense_end_date = ?,
                notes = ?
             WHERE expense_id = ?"
        );

        $stmt->execute([
            $periodId,
            $endPeriodId,
            $category,
            $description,
            $amount,
            $expenseDate,
            $expenseEndDate,
            $notes,
            $expenseId
        ]);

        respond([
            'success' => true,
            'message' => 'Expense updated.'
        ]);
    }

    $createdBy = null;

    /*
     * Try to use the authenticated admin/user ID when available.
     */
    foreach ([
        $_SESSION['user_id'] ?? null,
        $_SESSION['admin_id'] ?? null
    ] as $sessionUserId) {
        if (
            $sessionUserId !== null
            && (int)$sessionUserId > 0
        ) {
            $createdBy = (int)$sessionUserId;
            break;
        }
    }

    $stmt = $pdo->prepare(
        "INSERT INTO analytics_manual_expenses (
            period_id,
            end_period_id,
            category,
            description,
            amount,
            expense_date,
            expense_end_date,
            notes,
            created_by
        )
        VALUES (
            ?,
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
        $periodId,
        $endPeriodId,
        $category,
        $description,
        $amount,
        $expenseDate,
        $expenseEndDate,
        $notes,
        $createdBy
    ]);

    respond([
        'success' => true,
        'message' => 'Expense added.',
        'expense_id' =>
            (int)$pdo->lastInsertId()
    ]);
}

/*
|--------------------------------------------------------------------------
| Delete manual expense
|--------------------------------------------------------------------------
*/

function deleteManualExpense(PDO $pdo): void
{
    $expenseId =
        postInt('expense_id');

    if ($expenseId <= 0) {
        respond([
            'success' => false,
            'message' => 'Invalid expense.'
        ], 400);
    }

    $stmt = $pdo->prepare(
        "DELETE FROM analytics_manual_expenses
         WHERE expense_id = ?"
    );

    $stmt->execute([
        $expenseId
    ]);

    respond([
        'success' => true,
        'message' => 'Expense deleted.'
    ]);
}

/*
|--------------------------------------------------------------------------
| TOP CUSTOMERS
|--------------------------------------------------------------------------
|
| Kept independent from the financial revenue calculation.
| This remains based on delivery records / amount due.
|--------------------------------------------------------------------------
*/

function calculateTopCustomers(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {
    if (
        !tableExists($pdo, 'deliveries')
        || !tableExists($pdo, 'customers')
    ) {
        return [];
    }

    if (
        !columnExists(
            $pdo,
            'deliveries',
            'customer_id'
        )
        || !columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        )
        || !columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        )
    ) {
        return [];
    }

    $nameColumn = null;

    foreach ([
        'name',
        'customer_name',
        'full_name'
    ] as $column) {
        if (
            columnExists(
                $pdo,
                'customers',
                $column
            )
        ) {
            $nameColumn = $column;
            break;
        }
    }

    if ($nameColumn === null) {
        return [];
    }

    $slimExpression = '0';
    $roundExpression = '0';

    if (
        columnExists(
            $pdo,
            'deliveries',
            'slim_quantity'
        )
    ) {
        $slimExpression =
            'COALESCE(d.slim_quantity, 0)';
    }

    if (
        columnExists(
            $pdo,
            'deliveries',
            'round_quantity'
        )
    ) {
        $roundExpression =
            'COALESCE(d.round_quantity, 0)';
    }

    $stmt = $pdo->prepare(
        "SELECT
            d.customer_id,
            c.`$nameColumn` AS customer_name,
            COUNT(*) AS delivery_count,
            COALESCE(
                SUM(d.amount_due),
                0
            ) AS total_amount,
            COALESCE(
                SUM($slimExpression),
                0
            ) AS slim_quantity,
            COALESCE(
                SUM($roundExpression),
                0
            ) AS round_quantity
         FROM deliveries d
         INNER JOIN customers c
            ON c.customer_id = d.customer_id
         WHERE d.delivery_date >= ?
           AND d.delivery_date < ?
         GROUP BY
            d.customer_id,
            c.`$nameColumn`
         ORDER BY
            total_amount DESC,
            delivery_count DESC
         LIMIT 5"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    $result = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $result[] = [
            'customer_id' =>
                (int)$row['customer_id'],

            'customer_name' =>
                (string)$row['customer_name'],

            'delivery_count' =>
                (int)$row['delivery_count'],

            'total_amount' =>
                money((float)$row['total_amount']),

            'slim_quantity' =>
                (int)$row['slim_quantity'],

            'round_quantity' =>
                (int)$row['round_quantity']
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| OUTSTANDING BALANCES
|--------------------------------------------------------------------------
|
| Kept as an overall customer balance.
|--------------------------------------------------------------------------
*/

function calculateOutstandingBalances(
    PDO $pdo
): array {
    if (
        !tableExists($pdo, 'customers')
        || !tableExists($pdo, 'deliveries')
    ) {
        return [];
    }

    if (
        !tableExists($pdo, 'payments')
        || !columnExists(
            $pdo,
            'deliveries',
            'customer_id'
        )
        || !columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        )
    ) {
        return [];
    }

    $customerNameColumn = null;

    foreach ([
        'name',
        'customer_name',
        'full_name'
    ] as $column) {
        if (
            columnExists(
                $pdo,
                'customers',
                $column
            )
        ) {
            $customerNameColumn = $column;
            break;
        }
    }

    if ($customerNameColumn === null) {
        return [];
    }

    $paymentJoin = '';

    if (
        columnExists(
            $pdo,
            'payments',
            'delivery_id'
        )
        && columnExists(
            $pdo,
            'deliveries',
            'delivery_id'
        )
        && columnExists(
            $pdo,
            'payments',
            'amount'
        )
    ) {
        $paymentJoin =
            "LEFT JOIN (
                SELECT
                    delivery_id,
                    COALESCE(
                        SUM(amount),
                        0
                    ) AS paid_amount
                FROM payments
                WHERE delivery_id IS NOT NULL
                GROUP BY delivery_id
            ) p
                ON p.delivery_id = d.delivery_id";
    } else {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT
            d.customer_id,
            c.`$customerNameColumn` AS customer_name,
            COALESCE(
                SUM(d.amount_due),
                0
            ) AS expected_amount,
            COALESCE(
                SUM(COALESCE(p.paid_amount, 0)),
                0
            ) AS paid_amount
         FROM deliveries d
         INNER JOIN customers c
            ON c.customer_id = d.customer_id
         $paymentJoin
         GROUP BY
            d.customer_id,
            c.`$customerNameColumn`
         HAVING
            (
                COALESCE(SUM(d.amount_due), 0)
                -
                COALESCE(
                    SUM(COALESCE(p.paid_amount, 0)),
                    0
                )
            ) > 0
         ORDER BY
            (
                COALESCE(SUM(d.amount_due), 0)
                -
                COALESCE(
                    SUM(COALESCE(p.paid_amount, 0)),
                    0
                )
            ) DESC"
    );

    $result = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $expected =
            (float)$row['expected_amount'];

        $paid =
            (float)$row['paid_amount'];

        $balance =
            $expected - $paid;

        if ($balance <= 0) {
            continue;
        }

        $result[] = [
            'customer_id' =>
                (int)$row['customer_id'],

            'customer_name' =>
                (string)$row['customer_name'],

            'expected_amount' =>
                money($expected),

            'paid_amount' =>
                money($paid),

            'outstanding_balance' =>
                money($balance)
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| RECENT CUSTOMERS
|--------------------------------------------------------------------------
*/

function calculateRecentCustomers(
    PDO $pdo
): array {
    if (
        !tableExists($pdo, 'customers')
    ) {
        return [];
    }

    $nameColumn = null;

    foreach ([
        'name',
        'customer_name',
        'full_name'
    ] as $column) {
        if (
            columnExists(
                $pdo,
                'customers',
                $column
            )
        ) {
            $nameColumn = $column;
            break;
        }
    }

    if ($nameColumn === null) {
        return [];
    }

    $dateColumn = null;

    foreach ([
        'created_at',
        'created_date',
        'date_created'
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

    if ($dateColumn === null) {
        return [];
    }

    $idColumn = 'customer_id';

    if (
        !columnExists(
            $pdo,
            'customers',
            $idColumn
        )
    ) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT
            `$idColumn` AS customer_id,
            `$nameColumn` AS customer_name,
            `$dateColumn` AS created_at
         FROM customers
         ORDER BY `$dateColumn` DESC
         LIMIT 5"
    );

    $result = [];

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
                (string)$row['created_at']
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

function actionDashboard(
    PDO $pdo,
    int $periodId
): void {
    $period =
        getPeriod(
            $pdo,
            $periodId
        );

    $start =
        $period['period_start'];

    $end =
        $period['period_end'];

    $endExclusive =
        exclusiveEnd($end);

    $walkInRevenue =
        calculateWalkInRevenue(
            $pdo,
            $start,
            $endExclusive
        );

    $deliveryRevenue =
        calculateDeliveryRevenue(
            $pdo,
            $start,
            $endExclusive
        );

    $totalRevenue =
        money(
            $walkInRevenue
            +
            $deliveryRevenue
        );

    $dailyClosingExpenses =
        calculateExpenses(
            $pdo,
            $start,
            $endExclusive
        );

    $manualExpenses =
        calculateManualExpenseTotal(
            $pdo,
            $periodId
        );

    $totalExpenses =
        money(
            $dailyClosingExpenses
            +
            $manualExpenses
        );

    $netProfit =
        money(
            $totalRevenue
            -
            $totalExpenses
        );

    respond([
        'success' => true,

        'period' => [
            'period_id' =>
                (int)$period['period_id'],

            'year' =>
                (int)$period['analytics_year'],

            'month' =>
                (int)$period['analytics_month'],

            'start' =>
                $start,

            'end' =>
                $end,

            'period_start' =>
                $start,

            'period_end' =>
                $end,

            'label' =>
                formatPeriodRange(
                    $start,
                    $end
                )
        ],

        'analytics' => [
            'total_revenue' =>
                $totalRevenue,

            'walk_in_revenue' =>
                money($walkInRevenue),

            'delivery_revenue' =>
                money($deliveryRevenue),

            'daily_closing_expenses' =>
                money($dailyClosingExpenses),

            'manual_expenses' =>
                money($manualExpenses),

            'total_expenses' =>
                $totalExpenses,

            'net_profit' =>
                $netProfit,

            /*
             * Kept as an alias for compatibility with
             * older frontend code.
             */
            'net_revenue' =>
                $netProfit
        ],

        'top_customers' =>
            calculateTopCustomers(
                $pdo,
                $start,
                $endExclusive
            ),

        'outstanding_balances' =>
            calculateOutstandingBalances(
                $pdo
            ),

        'recent_customers' =>
            calculateRecentCustomers(
                $pdo
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| DAILY CHART
|--------------------------------------------------------------------------
|
| One row per calendar day.
|
| Revenue:
|   Daily Closing walk-in/shop revenue
|   +
|   actual delivery payments received
|
| Expenses:
|   Daily Closing expenses
|   +
|   Manual Analytics expenses
|
| Net Profit:
|   Revenue - Total Expenses
|
| The date is never shifted.
|--------------------------------------------------------------------------
*/

function actionDailyChart(
    PDO $pdo,
    int $periodId
): void {
    $period =
        getPeriod(
            $pdo,
            $periodId
        );

    $start =
        $period['period_start'];

    $end =
        $period['period_end'];

    $endExclusive =
        exclusiveEnd($end);

    $dailyRevenue =
        calculateDailyRevenue(
            $pdo,
            $start,
            $endExclusive
        );

    $dailyClosingExpenses =
        calculateDailyExpenses(
            $pdo,
            $start,
            $endExclusive
        );

    $dailyManualExpenses =
        calculateDailyManualExpenses(
            $pdo,
            $periodId,
            $start,
            $endExclusive
        );

    $days = [];

    $cursor =
        dateObject($start);

    $last =
        dateObject($end);

    while ($cursor <= $last) {
        $date =
            $cursor->format('Y-m-d');

        $walkInRevenue =
            $dailyRevenue[$date]['walk_in_revenue']
            ?? 0.00;

        $deliveryRevenue =
            $dailyRevenue[$date]['delivery_revenue']
            ?? 0.00;

        $revenue =
            money(
                $walkInRevenue
                +
                $deliveryRevenue
            );

        $closingExpenses =
            $dailyClosingExpenses[$date]
            ?? 0.00;

        $manualExpenses =
            $dailyManualExpenses[$date]
            ?? 0.00;

        $totalExpenses =
            money(
                $closingExpenses
                +
                $manualExpenses
            );

        $netProfit =
            money(
                $revenue
                -
                $totalExpenses
            );

        $days[] = [
            'date' =>
                $date,

            'label' =>
                $cursor->format('M j'),

            'walk_in_revenue' =>
                money($walkInRevenue),

            'delivery_revenue' =>
                money($deliveryRevenue),

            'revenue' =>
                $revenue,

            'daily_closing_expenses' =>
                money($closingExpenses),

            'manual_expenses' =>
                money($manualExpenses),

            'expenses' =>
                $totalExpenses,

            'total_expenses' =>
                $totalExpenses,

            'net_profit' =>
                $netProfit,

            /*
             * Compatibility alias.
             */
            'net_revenue' =>
                $netProfit
        ];

        $cursor =
            $cursor->modify('+1 day');
    }

    respond([
        'success' => true,

        'period' => [
            'period_id' =>
                (int)$period['period_id'],

            'start' =>
                $start,

            'end' =>
                $end
        ],

        'days' =>
            $days
    ]);
}

/*
|--------------------------------------------------------------------------
| Manual expenses action
|--------------------------------------------------------------------------
*/

function actionManualExpenses(
    PDO $pdo,
    int $periodId
): void {
    getPeriod(
        $pdo,
        $periodId
    );

    respond([
        'success' => true,

        'expenses' =>
            getManualExpenses(
                $pdo,
                $periodId
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| Historical data
|--------------------------------------------------------------------------
|
| Keep the existing history endpoint available.
| It now reports the same accounting figures used by the dashboard.
|--------------------------------------------------------------------------
*/

function actionHistory(
    PDO $pdo,
    int $periodId
): void {
    $period =
        getPeriod(
            $pdo,
            $periodId
        );

    $start =
        $period['period_start'];

    $end =
        $period['period_end'];

    $endExclusive =
        exclusiveEnd($end);

    $dailyRevenue =
        calculateDailyRevenue(
            $pdo,
            $start,
            $endExclusive
        );

    $dailyClosingExpenses =
        calculateDailyExpenses(
            $pdo,
            $start,
            $endExclusive
        );

    $dailyManualExpenses =
        calculateDailyManualExpenses(
            $pdo,
            $periodId,
            $start,
            $endExclusive
        );

    $history = [];

    $cursor =
        dateObject($start);

    $last =
        dateObject($end);

    while ($cursor <= $last) {
        $date =
            $cursor->format('Y-m-d');

        $walkIn =
            $dailyRevenue[$date]['walk_in_revenue']
            ?? 0.00;

        $delivery =
            $dailyRevenue[$date]['delivery_revenue']
            ?? 0.00;

        $revenue =
            money(
                $walkIn + $delivery
            );

        $closing =
            $dailyClosingExpenses[$date]
            ?? 0.00;

        $manual =
            $dailyManualExpenses[$date]
            ?? 0.00;

        $expenses =
            money(
                $closing + $manual
            );

        $netProfit =
            money(
                $revenue - $expenses
            );

        $history[] = [
            'date' =>
                $date,

            'label' =>
                $cursor->format('M j'),

            'revenue' =>
                $revenue,

            'daily_closing_expenses' =>
                money($closing),

            'manual_expenses' =>
                money($manual),

            'expenses' =>
                $expenses,

            'total_expenses' =>
                $expenses,

            'net_profit' =>
                $netProfit,

            'net_revenue' =>
                $netProfit
        ];

        $cursor =
            $cursor->modify('+1 day');
    }

    respond([
        'success' => true,
        'history' => $history
    ]);
}

/*
|--------------------------------------------------------------------------
| Main dispatcher
|--------------------------------------------------------------------------
*/

try {
    ensureAnalyticsSchema($pdo);

    $action =
        postString('action');

    switch ($action) {
        case 'periods':
            respond([
                'success' => true,
                'periods' =>
                    getPeriods($pdo)
            ]);
            break;

        case 'save_period':
            savePeriod($pdo);
            break;

        case 'dashboard':
            $periodId =
                postInt('period_id');

            if ($periodId <= 0) {
                respond([
                    'success' => false,
                    'message' =>
                        'Please select an accounting period.'
                ], 400);
            }

            actionDashboard(
                $pdo,
                $periodId
            );
            break;

        case 'daily_chart':
            $periodId =
                postInt('period_id');

            if ($periodId <= 0) {
                respond([
                    'success' => false,
                    'message' =>
                        'Please select an accounting period.'
                ], 400);
            }

            actionDailyChart(
                $pdo,
                $periodId
            );
            break;

        case 'manual_expenses':
            $periodId =
                postInt('period_id');

            if ($periodId <= 0) {
                respond([
                    'success' => false,
                    'message' =>
                        'Please select an accounting period.'
                ], 400);
            }

            actionManualExpenses(
                $pdo,
                $periodId
            );
            break;

        case 'save_manual_expense':
            saveManualExpense($pdo);
            break;

        case 'delete_manual_expense':
            deleteManualExpense($pdo);
            break;

        case 'history':
            $periodId =
                postInt('period_id');

            if ($periodId <= 0) {
                respond([
                    'success' => false,
                    'message' =>
                        'Please select an accounting period.'
                ], 400);
            }

            actionHistory(
                $pdo,
                $periodId
            );
            break;

        default:
            respond([
                'success' => false,
                'message' =>
                    'Unknown analytics action.'
            ], 400);
    }
} catch (Throwable $e) {
    error_log(
        'Home analytics error: '
        . $e->getMessage()
    );

    respond([
        'success' => false,
        'message' =>
            'Unable to load analytics data.'
    ], 500);
}