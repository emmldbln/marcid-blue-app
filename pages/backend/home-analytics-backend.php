<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../../auth/auth.php';
require_once '../../config/database.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Response
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

/*
|--------------------------------------------------------------------------
| POST helpers
|--------------------------------------------------------------------------
*/

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

function postFloat(
    string $key,
    float $default = 0.0
): float {
    return (float)($_POST[$key] ?? $default);
}

/*
|--------------------------------------------------------------------------
| Database helpers
|--------------------------------------------------------------------------
*/

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
| Date helpers
|--------------------------------------------------------------------------
*/

function validDate(string $date): bool
{
    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
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

function formatPeriodRange(
    string $start,
    string $end
): string {
    return dateObject($start)->format('M j, Y')
        . ' – '
        . dateObject($end)->format('M j, Y');
}

/*
|--------------------------------------------------------------------------
| Accounting Period Defaults
|--------------------------------------------------------------------------
|
| September 2026:
|   August 15, 2026 -> September 15, 2026
|
| October 2026:
|   September 16, 2026 -> October 15, 2026
|
| November 2026:
|   October 16, 2026 -> November 15, 2026
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
    if ($year < 2026) {
        return false;
    }

    if ($year === 2026 && $month < 9) {
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
     * September 2026 is the special starting period.
     */
    if ($year === 2026 && $month === 9) {

        return [
            'start' => '2026-08-15',
            'end'   => '2026-09-15'
        ];
    }

    $current = new DateTimeImmutable(
        sprintf(
            '%04d-%02d-15',
            $year,
            $month
        )
    );

    $start = $current
        ->modify('-1 day')
        ->modify('first day of this month')
        ->modify('+15 days');

    /*
     * Easier and safer:
     * the start is the 16th of the previous month.
     */
    $start = $current
        ->modify('-1 month')
        ->modify('first day of this month')
        ->modify('+15 days');

    return [
        'start' =>
            $start->format('Y-m-d'),

        'end' =>
            $current->format('Y-m-d')
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

            created_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (period_id),

            UNIQUE KEY unique_analytics_period
                (
                    analytics_year,
                    analytics_month
                ),

            KEY idx_period_dates
                (
                    period_start,
                    period_end
                )

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS analytics_manual_expenses (

            expense_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

            period_id INT UNSIGNED NOT NULL,

            end_period_id INT UNSIGNED NULL,

            category VARCHAR(100) NOT NULL,

            description VARCHAR(255) NOT NULL,

            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,

            expense_date DATE NOT NULL,

            expense_end_date DATE NULL,

            notes TEXT NULL,

            created_by INT UNSIGNED NULL,

            created_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL
                DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (expense_id),

            KEY idx_manual_expenses_period
                (period_id),

            KEY idx_manual_expenses_end_period
                (end_period_id),

            KEY idx_manual_expenses_date
                (expense_date),

            CONSTRAINT fk_manual_expenses_period
                FOREIGN KEY (period_id)
                REFERENCES analytics_periods(period_id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT,

            CONSTRAINT fk_manual_expenses_end_period
                FOREIGN KEY (end_period_id)
                REFERENCES analytics_periods(period_id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT

        ) ENGINE=InnoDB
          DEFAULT CHARSET=utf8mb4
          COLLATE=utf8mb4_unicode_ci"
    );
}

/*
|--------------------------------------------------------------------------
| Ensure default September 2026 period
|--------------------------------------------------------------------------
*/

function ensureDefaultPeriod(PDO $pdo): void
{
    $dates = defaultPeriodDates(2026, 9);

    $stmt = $pdo->prepare(
        "INSERT INTO analytics_periods
        (
            analytics_year,
            analytics_month,
            period_start,
            period_end
        )
        VALUES
        (
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

/*
|--------------------------------------------------------------------------
| Create future period if needed
|--------------------------------------------------------------------------
*/

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

    $dates =
        defaultPeriodDates(
            $year,
            $month
        );

    $stmt = $pdo->prepare(
        "INSERT INTO analytics_periods
        (
            analytics_year,
            analytics_month,
            period_start,
            period_end
        )
        VALUES
        (
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

/*
|--------------------------------------------------------------------------
| Get period
|--------------------------------------------------------------------------
*/

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

    if (
        !validDate((string)$period['period_start']) ||
        !validDate((string)$period['period_end'])
    ) {
        throw new InvalidArgumentException(
            'Invalid accounting period dates.'
        );
    }

    if (
        $period['period_start']
        >
        $period['period_end']
    ) {
        throw new InvalidArgumentException(
            'Accounting period start date cannot be after the end date.'
        );
    }

    return $period;
}

/*
|--------------------------------------------------------------------------
| Get periods
|--------------------------------------------------------------------------
*/

function getPeriods(PDO $pdo): array
{
    ensureDefaultPeriod($pdo);

    /*
     * Generate every accounting period from
     * September 2026 through the next calendar month.
     *
     * Existing periods are never overwritten.
     */

    $today = new DateTimeImmutable('today');

    $startYear = 2026;
    $startMonth = 9;

    $targetYear =
        (int)$today
            ->modify('+1 month')
            ->format('Y');

    $targetMonth =
        (int)$today
            ->modify('+1 month')
            ->format('n');

    $cursor = new DateTimeImmutable(
        sprintf(
            '%04d-%02d-01',
            $startYear,
            $startMonth
        )
    );

    $target = new DateTimeImmutable(
        sprintf(
            '%04d-%02d-01',
            $targetYear,
            $targetMonth
        )
    );

    while ($cursor <= $target) {

        $year =
            (int)$cursor->format('Y');

        $month =
            (int)$cursor->format('n');

        if (
            isAllowedAnalyticsMonth(
                $year,
                $month
            )
        ) {

            ensurePeriod(
                $pdo,
                $year,
                $month
            );
        }

        $cursor =
            $cursor->modify('+1 month');
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
         ORDER BY
            period_start DESC"
    );

    $periods = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $start =
            (string)$row['period_start'];

        $end =
            (string)$row['period_end'];

        $year =
            (int)$row['analytics_year'];

        $month =
            (int)$row['analytics_month'];

        $periods[] = [

            'period_id' =>
                (int)$row['period_id'],

            'year' =>
                $year,

            'month' =>
                $month,

            /*
             * Human-readable date range.
             */
            'label' =>
                formatPeriodRange(
                    $start,
                    $end
                ),

            /*
             * Human-readable accounting month.
             */
            'month_label' =>
                dateObject(
                    sprintf(
                        '%04d-%02d-01',
                        $year,
                        $month
                    )
                )->format('F Y'),

            /*
             * Keep the backend's existing names.
             */
            'start' =>
                $start,

            'end' =>
                $end,

            /*
             * Also expose explicit names so the
             * frontend can use them safely.
             */
            'period_start' =>
                $start,

            'period_end' =>
                $end
        ];
    }

    return $periods;
}

/*
|--------------------------------------------------------------------------
| Save period
|--------------------------------------------------------------------------
*/

function savePeriod(PDO $pdo): void
{
    $periodId =
        postInt('period_id');

    $start =
        postString('period_start');

    $end =
        postString('period_end');

    if ($periodId <= 0) {
        respond([
            'success' => false,
            'message' => 'Invalid accounting period.'
        ], 400);
    }

    if (
        !validDate($start) ||
        !validDate($end)
    ) {
        respond([
            'success' => false,
            'message' => 'Please enter valid start and end dates.'
        ], 400);
    }

    if ($start > $end) {
        respond([
            'success' => false,
            'message' => 'Start date cannot be after the end date.'
        ], 400);
    }

    $period =
        getPeriod(
            $pdo,
            $periodId
        );

    /*
     * Do not allow the period's identity
     * to be changed here. Only its date range.
     */
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
        'period' => getPeriod($pdo, $periodId)
    ]);
}

/*
|--------------------------------------------------------------------------
| Walk-in revenue
|--------------------------------------------------------------------------
*/

function calculateWalkInRevenue(
    PDO $pdo,
    string $start,
    string $endExclusive
): float {

    if (
        !tableExists(
            $pdo,
            'daily_records'
        )
    ) {
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
                $endExclusive
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
                $endExclusive
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
| Daily walk-ins
|--------------------------------------------------------------------------
*/

function calculateDailyWalkIns(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {

    $result = [];

    if (
        !tableExists(
            $pdo,
            'daily_records'
        )
    ) {
        return $result;
    }

    $expression = null;

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

            $expression =
                "COALESCE($column,0)";

            break;
        }
    }

    if (!$expression) {

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

                $expression =
                    "COALESCE($column,0) * 30";

                break;
            }
        }
    }

    if (!$expression) {
        return $result;
    }

    $stmt = $pdo->prepare(
        "SELECT
            business_date,
            $expression AS revenue
         FROM daily_records
         WHERE business_date >= ?
           AND business_date < ?
         ORDER BY business_date ASC"
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
            (string)$row['business_date']
        ] = money(
            (float)$row['revenue']
        );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Deliveries
|--------------------------------------------------------------------------
*/

function calculateDeliveryStats(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {

    $result = [
        'revenue' => 0.00,
        'slim' => 0.00,
        'round' => 0.00,
        'deliveries' => 0
    ];

    if (
        !tableExists(
            $pdo,
            'deliveries'
        )
    ) {
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

    $amount =
        columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        )
            ? 'amount_due'
            : null;

    $slim =
        columnExists(
            $pdo,
            'deliveries',
            'slim_quantity'
        )
            ? 'slim_quantity'
            : null;

    $round =
        columnExists(
            $pdo,
            'deliveries',
            'round_quantity'
        )
            ? 'round_quantity'
            : null;

    $select = [
        'COUNT(*) AS delivery_count'
    ];

    $select[] =
        $amount
            ? "COALESCE(SUM($amount),0) AS revenue"
            : "0 AS revenue";

    $select[] =
        $slim
            ? "COALESCE(SUM($slim),0) AS slim"
            : "0 AS slim";

    $select[] =
        $round
            ? "COALESCE(SUM($round),0) AS round"
            : "0 AS round";

    $stmt = $pdo->prepare(
        "SELECT
            " . implode(',', $select) . "
         FROM deliveries
         WHERE delivery_date >= ?
           AND delivery_date < ?"
    );

    $stmt->execute([
        $start,
        $endExclusive
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC)
        ?: [];

    return [
        'revenue' =>
            money(
                (float)($row['revenue'] ?? 0)
            ),

        'slim' =>
            money(
                (float)($row['slim'] ?? 0)
            ),

        'round' =>
            money(
                (float)($row['round'] ?? 0)
            ),

        'deliveries' =>
            (int)($row['delivery_count'] ?? 0)
    ];
}

/*
|--------------------------------------------------------------------------
| Daily deliveries
|--------------------------------------------------------------------------
*/

function calculateDailyDeliveries(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {

    $result = [];

    if (
        !tableExists(
            $pdo,
            'deliveries'
        ) ||
        !columnExists(
            $pdo,
            'deliveries',
            'delivery_date'
        )
    ) {
        return $result;
    }

    $amount =
        columnExists(
            $pdo,
            'deliveries',
            'amount_due'
        )
            ? 'amount_due'
            : null;

    $slim =
        columnExists(
            $pdo,
            'deliveries',
            'slim_quantity'
        )
            ? 'slim_quantity'
            : null;

    $round =
        columnExists(
            $pdo,
            'deliveries',
            'round_quantity'
        )
            ? 'round_quantity'
            : null;

    $stmt = $pdo->prepare(
        "SELECT
            delivery_date,
            " .
            (
                $amount
                    ? "COALESCE($amount,0)"
                    : "0"
            )
            . " AS revenue,

            " .
            (
                $slim
                    ? "COALESCE($slim,0)"
                    : "0"
            )
            . " AS slim,

            " .
            (
                $round
                    ? "COALESCE($round,0)"
                    : "0"
            )
            . " AS round

         FROM deliveries

         WHERE delivery_date >= ?
           AND delivery_date < ?

         ORDER BY delivery_date ASC"
    );

    $stmt->execute([
        $start,
        $endExclusive
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
| Daily Closing Expenses
|--------------------------------------------------------------------------
*/

function calculateExpenses(
    PDO $pdo,
    string $start,
    string $endExclusive
): float {

    if (
        !tableExists(
            $pdo,
            'expenses'
        )
    ) {
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
        $endExclusive
    ]);

    return money(
        (float)$stmt->fetchColumn()
    );
}

/*
|--------------------------------------------------------------------------
| Daily Closing expenses by date
|--------------------------------------------------------------------------
*/

function calculateDailyExpenses(
    PDO $pdo,
    string $start,
    string $endExclusive
): array {

    $result = [];

    if (
        !tableExists(
            $pdo,
            'expenses'
        )
    ) {
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
            ) AS amount
         FROM expenses
         WHERE $dateColumn >= ?
           AND $dateColumn < ?
         GROUP BY $dateColumn
         ORDER BY $dateColumn"
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
            (string)$row['expense_date']
        ] =
            money(
                (float)$row['amount']
            );
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Manual Expense allocation
|--------------------------------------------------------------------------
|
| An expense may cover one period or two periods.
|
| Example:
|
| Expense:
| Aug 15 -> Oct 15
| Amount: ₱3,000
|
| The amount is allocated according to the number
| of covered days inside each accounting period.
|
|--------------------------------------------------------------------------
*/

function manualExpenseCoverage(
    array $expense
): array {

    $start =
        (string)$expense['expense_date'];

    $end =
        !empty($expense['expense_end_date'])
            ? (string)$expense['expense_end_date']
            : $start;

    if (!validDate($start)) {
        return [];
    }

    if (!validDate($end)) {
        $end = $start;
    }

    if ($end < $start) {
        $end = $start;
    }

    return [
        'start' => $start,
        'end' => $end
    ];
}

function overlapDays(
    string $aStart,
    string $aEnd,
    string $bStart,
    string $bEnd
): int {

    $startA = dateObject($aStart);
    $endA = dateObject($aEnd);

    $startB = dateObject($bStart);
    $endB = dateObject($bEnd);

    $start =
        $startA > $startB
            ? $startA
            : $startB;

    $end =
        $endA < $endB
            ? $endA
            : $endB;

    if ($start > $end) {
        return 0;
    }

    return (int)$start
        ->diff($end)
        ->days + 1;
}

function getManualExpenses(
    PDO $pdo,
    int $periodId
): array {

    getPeriod(
        $pdo,
        $periodId
    );

    $stmt = $pdo->prepare(
        "SELECT
            e.expense_id,
            e.period_id,
            e.category,
            e.description,
            e.amount,
            e.expense_date,
            e.expense_end_date,

            p.analytics_year AS period_year,
            p.analytics_month AS period_month,
            p.period_start AS period_start,
            p.period_end AS period_end

         FROM analytics_manual_expenses e

         INNER JOIN analytics_periods p
            ON p.period_id = e.period_id

         WHERE e.period_id = ?

         ORDER BY
            e.expense_date DESC,
            e.expense_id DESC"
    );

    $stmt->execute([
        $periodId
    ]);

    $result = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $expense
    ) {

        $expenseDate =
            (string)$expense['expense_date'];

        $expenseEndDate =
            !empty($expense['expense_end_date'])
                ? (string)$expense['expense_end_date']
                : $expenseDate;

        if (!validDate($expenseDate)) {
            continue;
        }

        if (!validDate($expenseEndDate)) {
            $expenseEndDate =
                $expenseDate;
        }

        if ($expenseEndDate < $expenseDate) {
            $expenseEndDate =
                $expenseDate;
        }

        $result[] = [
            'expense_id' =>
                (int)$expense['expense_id'],

            'category' =>
                (string)$expense['category'],

            'description' =>
                (string)$expense['description'],

            'amount' =>
                money(
                    (float)$expense['amount']
                ),

            'allocated_amount' =>
                money(
                    (float)$expense['amount']
                ),

            'expense_date' =>
                $expenseDate,

            'expense_end_date' =>
                $expenseEndDate,

            'period_id' =>
                (int)$expense['period_id'],

            'period_label' =>
                formatPeriodRange(
                    (string)$expense['period_start'],
                    (string)$expense['period_end']
                ),

            'notes' =>
                ''
        ];
    }

    return $result;
}

function calculateManualExpenseTotal(
    PDO $pdo,
    int $periodId
): float {

    $expenses =
        getManualExpenses(
            $pdo,
            $periodId
        );

    $total = 0.00;

    foreach ($expenses as $expense) {
        $total +=
            (float)$expense['amount'];
    }

    return money($total);
}

/*
|--------------------------------------------------------------------------
| Manual expenses by date
|--------------------------------------------------------------------------
*/

function calculateDailyManualExpenses(
    PDO $pdo,
    int $periodId
): array {

    $period =
        getPeriod(
            $pdo,
            $periodId
        );

    $expenses =
        getManualExpenses(
            $pdo,
            $periodId
        );

    $result = [];

    foreach ($expenses as $expense) {

        $coverage =
            manualExpenseCoverage(
                $expense
            );

        if (!$coverage) {
            continue;
        }

        $totalDays =
            overlapDays(
                $coverage['start'],
                $coverage['end'],
                $coverage['start'],
                $coverage['end']
            );

        if ($totalDays <= 0) {
            continue;
        }

        $overlapStart =
            $coverage['start']
            >
            $period['period_start']
                ? $coverage['start']
                : $period['period_start'];

        $overlapEnd =
            $coverage['end']
            <
            $period['period_end']
                ? $coverage['end']
                : $period['period_end'];

        if ($overlapStart > $overlapEnd) {
            continue;
        }

        $allocated =
            (float)$expense['amount']
            *
            (
                overlapDays(
                    $overlapStart,
                    $overlapEnd,
                    $coverage['start'],
                    $coverage['end']
                )
                /
                $totalDays
            );

        $days =
            overlapDays(
                $overlapStart,
                $overlapEnd,
                $overlapStart,
                $overlapEnd
            );

        if ($days <= 0) {
            continue;
        }

        $perDay =
            $allocated / $days;

        $date =
            dateObject($overlapStart);

        $last =
            dateObject($overlapEnd);

        while ($date <= $last) {

            $key =
                $date->format('Y-m-d');

            if (!isset($result[$key])) {
                $result[$key] = 0.00;
            }

            $result[$key] += $perDay;

            $date =
                $date->modify('+1 day');
        }
    }

    foreach ($result as $date => $amount) {
        $result[$date] =
            money($amount);
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Save manual expense
|--------------------------------------------------------------------------
*/

function saveManualExpense(PDO $pdo): void
{
    $expenseId =
        postInt('expense_id');

    $periodId =
        postInt('period_id');

    $endPeriodId =
        postInt('end_period_id');

    $category =
        postString('category');

    $description =
        postString('description');

    $amount =
        postFloat('amount');

    $expenseDate =
        postString('expense_date');

    $expenseEndDate =
        postString('expense_end_date');

    $notes =
        postString('notes');

    if ($periodId <= 0) {
        respond([
            'success' => false,
            'message' => 'Please select Accounting Period 1.'
        ], 400);
    }

    getPeriod(
        $pdo,
        $periodId
    );

    if ($endPeriodId > 0) {

        getPeriod(
            $pdo,
            $endPeriodId
        );
    } else {
        $endPeriodId = null;
    }

    if ($category === '') {
        respond([
            'success' => false,
            'message' => 'Please select an expense category.'
        ], 400);
    }

    if ($description === '') {
        respond([
            'success' => false,
            'message' => 'Please enter a description.'
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

    if ($expenseEndDate === '') {
        $expenseEndDate =
            $expenseDate;
    }

    if (!validDate($expenseEndDate)) {
        respond([
            'success' => false,
            'message' => 'Please enter a valid expense end date.'
        ], 400);
    }

    if ($expenseEndDate < $expenseDate) {
        respond([
            'success' => false,
            'message' => 'Expense end date cannot be before expense date.'
        ], 400);
    }

    /*
     * If a second accounting period is selected,
     * its start must not be before Period 1.
     */
    if ($endPeriodId !== null) {

        $period1 =
            getPeriod(
                $pdo,
                $periodId
            );

        $period2 =
            getPeriod(
                $pdo,
                $endPeriodId
            );

        if (
            $period2['period_start']
            <
            $period1['period_start']
        ) {
            respond([
                'success' => false,
                'message' =>
                    'Accounting Period 2 must be the same as or later than Accounting Period 1.'
            ], 400);
        }
    }

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
            money($amount),
            $expenseDate,
            $expenseEndDate,
            $notes !== '' ? $notes : null,
            $expenseId
        ]);

    } else {

        $stmt = $pdo->prepare(
            "INSERT INTO analytics_manual_expenses
            (
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
            VALUES
            (
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

        $createdBy = null;

        if (
            isset($_SESSION['user_id'])
        ) {
            $createdBy =
                (int)$_SESSION['user_id'];
        }

        $stmt->execute([
            $periodId,
            $endPeriodId,
            $category,
            $description,
            money($amount),
            $expenseDate,
            $expenseEndDate,
            $notes !== '' ? $notes : null,
            $createdBy
        ]);
    }

    respond([
        'success' => true,
        'message' =>
            $expenseId > 0
                ? 'Manual expense updated.'
                : 'Manual expense added.'
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
        'message' => 'Manual expense deleted.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Top customers
|--------------------------------------------------------------------------
*/

function calculateTopCustomers(
    PDO $pdo,
    string $start,
    string $endExclusive,
    int $limit = 5
): array {

    if (
        !tableExists(
            $pdo,
            'deliveries'
        ) ||
        !tableExists(
            $pdo,
            'customers'
        )
    ) {
        return [];
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
        )
    ) {
        return [];
    }

    $limit =
        max(
            1,
            min(
                $limit,
                20
            )
        );

    $stmt = $pdo->prepare(
        "SELECT
            c.customer_id,
            c.customer_name,
            COUNT(d.delivery_id) AS deliveries,
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

         ORDER BY revenue DESC

         LIMIT $limit"
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
                )
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Outstanding balances
|--------------------------------------------------------------------------
*/

function calculateOutstandingBalances(
    PDO $pdo,
    int $limit = 10
): array {

    if (
        !tableExists($pdo, 'deliveries') ||
        !tableExists($pdo, 'payments') ||
        !tableExists($pdo, 'customers')
    ) {
        return [];
    }

    $limit =
        max(
            1,
            min(
                $limit,
                50
            )
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
                        pt.paid_amount,
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

         ) pt

            ON pt.delivery_id =
               d.delivery_id

         GROUP BY
            c.customer_id,
            c.customer_name

         HAVING balance > 0

         ORDER BY balance DESC

         LIMIT $limit"
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
| Recent customers
|--------------------------------------------------------------------------
*/

function calculateRecentCustomers(
    PDO $pdo,
    int $limit = 8
): array {

    if (
        !tableExists(
            $pdo,
            'customers'
        )
    ) {
        return [];
    }

    $limit =
        max(
            1,
            min(
                $limit,
                20
            )
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
                $row['created_at']
                    ?? null
        ];
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

function actionDashboard(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    $periodId =
        postInt('period_id');

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

    $walkIn =
        calculateWalkInRevenue(
            $pdo,
            $start,
            $endExclusive
        );

    $delivery =
        calculateDeliveryStats(
            $pdo,
            $start,
            $endExclusive
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

    $totalRevenue =
        money(
            $walkIn
            +
            $delivery['revenue']
        );

    $totalExpenses =
        money(
            $dailyClosingExpenses
            +
            $manualExpenses
        );

    $netRevenue =
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
                $walkIn,

            'delivery_revenue' =>
                $delivery['revenue'],

            'daily_closing_expenses' =>
                $dailyClosingExpenses,

            'manual_expenses' =>
                $manualExpenses,

            'total_expenses' =>
                $totalExpenses,

            'net_revenue' =>
                $netRevenue,

            'slim_gallons' =>
                $delivery['slim'],

            'round_gallons' =>
                $delivery['round'],

            'total_gallons' =>
                money(
                    $delivery['slim']
                    +
                    $delivery['round']
                ),

            'total_deliveries' =>
                $delivery['deliveries']
        ],

        'manual_expenses_list' =>
            getManualExpenses(
                $pdo,
                $periodId
            ),

        'top_customers' =>
            calculateTopCustomers(
                $pdo,
                $start,
                $endExclusive,
                5
            ),

        'outstanding_balances' =>
            calculateOutstandingBalances(
                $pdo,
                10
            ),

        'recent_customers' =>
            calculateRecentCustomers(
                $pdo,
                8
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| Daily chart
|--------------------------------------------------------------------------
*/

function actionDailyChart(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    $periodId =
        postInt('period_id');

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

    $walkIns =
        calculateDailyWalkIns(
            $pdo,
            $start,
            $endExclusive
        );

    $deliveries =
        calculateDailyDeliveries(
            $pdo,
            $start,
            $endExclusive
        );

    $closingExpenses =
        calculateDailyExpenses(
            $pdo,
            $start,
            $endExclusive
        );

    $manualExpenses =
        calculateDailyManualExpenses(
            $pdo,
            $periodId
        );

    $days = [];

    $date =
        dateObject($start);

    $last =
        dateObject($end);

    while ($date <= $last) {

        $dateKey =
            $date->format('Y-m-d');

        $walkIn =
            $walkIns[$dateKey]
            ?? 0.00;

        $delivery =
            $deliveries[$dateKey]
            ??
            [
                'revenue' => 0.00,
                'slim' => 0.00,
                'round' => 0.00,
                'deliveries' => 0
            ];

        $closingExpense =
            $closingExpenses[$dateKey]
            ?? 0.00;

        $manualExpense =
            $manualExpenses[$dateKey]
            ?? 0.00;

        $revenue =
            money(
                $walkIn
                +
                $delivery['revenue']
            );

        $totalExpense =
            money(
                $closingExpense
                +
                $manualExpense
            );

        $days[] = [
            'date' =>
                $dateKey,

            'label' =>
                $date->format('M j'),

            'revenue' =>
                $revenue,

            'walk_in_revenue' =>
                money($walkIn),

            'delivery_revenue' =>
                money(
                    $delivery['revenue']
                ),

            'daily_closing_expenses' =>
                money($closingExpense),

            'manual_expenses' =>
                money($manualExpense),

            'expenses' =>
                $totalExpense,

            'net_revenue' =>
                money(
                    $revenue
                    -
                    $totalExpense
                ),

            'slim_gallons' =>
                money(
                    $delivery['slim']
                ),

            'round_gallons' =>
                money(
                    $delivery['round']
                ),

            'total_gallons' =>
                money(
                    $delivery['slim']
                    +
                    $delivery['round']
                )
        ];

        $date =
            $date->modify('+1 day');
    }

    respond([
        'success' => true,

        'period' => [
            'start' => $start,
            'end' => $end
        ],

        'days' => $days
    ]);
}

/*
|--------------------------------------------------------------------------
| Periods
|--------------------------------------------------------------------------
*/

function actionPeriods(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    respond([
        'success' => true,
        'periods' =>
            getPeriods($pdo)
    ]);
}

/*
|--------------------------------------------------------------------------
| Manual expenses
|--------------------------------------------------------------------------
*/

function actionManualExpenses(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    $periodId =
        postInt('period_id');

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
            ),

        'total' =>
            calculateManualExpenseTotal(
                $pdo,
                $periodId
            )
    ]);
}

/*
|--------------------------------------------------------------------------
| History
|--------------------------------------------------------------------------
*/

function actionHistory(PDO $pdo): void
{
    ensureAnalyticsSchema($pdo);

    $periods =
        getPeriods($pdo);

    $history = [];

    foreach ($periods as $period) {

        $periodId =
            (int)$period['period_id'];

        $start =
            $period['start'];

        $end =
            $period['end'];

        $endExclusive =
            exclusiveEnd($end);

        $walkIn =
            calculateWalkInRevenue(
                $pdo,
                $start,
                $endExclusive
            );

        $delivery =
            calculateDeliveryStats(
                $pdo,
                $start,
                $endExclusive
            );

        $dailyExpenses =
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

        $revenue =
            money(
                $walkIn
                +
                $delivery['revenue']
            );

        $expenses =
            money(
                $dailyExpenses
                +
                $manualExpenses
            );

        $history[] = [
            'period_id' =>
                $periodId,

            'year' =>
                $period['year'],

            'month' =>
                $period['month'],

            'month_label' =>
                $period['month_label'],

            'start' =>
                $start,

            'end' =>
                $end,

            'range' =>
                $period['label'],

            'revenue' =>
                $revenue,

            'expenses' =>
                $expenses,

            'net_revenue' =>
                money(
                    $revenue
                    -
                    $expenses
                ),

            'gallons' =>
                money(
                    $delivery['slim']
                    +
                    $delivery['round']
                )
        ];
    }

    respond([
        'success' => true,
        'history' => $history
    ]);
}

/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

try {

    ensureAnalyticsSchema($pdo);

    $action =
        postString(
            'action',
            'periods'
        );

    switch ($action) {

        case 'periods':
            actionPeriods($pdo);
            break;

        case 'save_period':
            savePeriod($pdo);
            break;

        case 'dashboard':
            actionDashboard($pdo);
            break;

        case 'daily_chart':
            actionDailyChart($pdo);
            break;

        case 'manual_expenses':
            actionManualExpenses($pdo);
            break;

        case 'save_manual_expense':
            saveManualExpense($pdo);
            break;

        case 'delete_manual_expense':
            deleteManualExpense($pdo);
            break;

        case 'history':
            actionHistory($pdo);
            break;

        default:
            respond([
                'success' => false,
                'message' =>
                    'Unknown analytics action.'
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