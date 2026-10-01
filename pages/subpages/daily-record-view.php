<?php

declare(strict_types=1);

require_once '../../auth/auth.php';
requireAdmin();

require_once '../../config/database.php';


/*
 * =========================================================
 * DAILY RECORD REPOSITORY
 * =========================================================
 */

final class DailyRecordRepository
{
    public function __construct(
        private PDO $pdo
    ) {
    }


    /*
     * ---------------------------------------------------------
     * DAILY RECORD
     * ---------------------------------------------------------
     */

    public function getDailyRecord(int $dailyId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                daily_id,
                business_date,
                status,
                closing_result,
                actual_station_cash,
                driver_remittance_status,
                driver_remittance_difference,
                created_at,
                updated_at,
                saved_at,
                saved_by
             FROM daily_records
             WHERE daily_id = ?
             LIMIT 1"
        );

        $stmt->execute([$dailyId]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }


    /*
     * ---------------------------------------------------------
     * SALES
     * ---------------------------------------------------------
     */

    public function getSales(
        string $businessDate,
        int $dailyId
    ): array {

        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(walk_in_customers), 0)
                    AS walk_in_customers,

                COALESCE(
                    SUM(
                        walk_in_customers * walk_in_price
                    ),
                    0
                ) AS walk_in_total,

                COALESCE(
                    SUM(other_shop_payment),
                    0
                ) AS other_shop_payment,

                COALESCE(
                    SUM(other_sales),
                    0
                ) AS other_sales

             FROM daily_sales

             WHERE sales_date = ?
               AND daily_id = ?"
        );

        $stmt->execute([
            $businessDate,
            $dailyId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'walk_in_customers' => 0,
            'walk_in_total' => 0,
            'other_shop_payment' => 0,
            'other_sales' => 0
        ];
    }


    /*
     * ---------------------------------------------------------
     * DELIVERIES + PAYMENTS
     * ---------------------------------------------------------
     */

    public function getDeliveries(int $dailyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                d.delivery_id,
                d.customer_id,
                c.customer_name,
                d.delivery_date,
                d.slim_quantity,
                d.round_quantity,
                d.price_per_gallon,
                d.amount_due,
                d.notes,
                d.created_at,

                COALESCE(
                    payment_totals.amount_received,
                    0
                ) AS amount_received

             FROM deliveries d

             LEFT JOIN customers c
                ON c.customer_id = d.customer_id

             LEFT JOIN (
                SELECT
                    p.delivery_id,
                    SUM(p.amount) AS amount_received

                FROM payments p

                WHERE p.daily_id = ?

                GROUP BY p.delivery_id

             ) payment_totals
                ON payment_totals.delivery_id =
                   d.delivery_id

             WHERE d.daily_id = ?

             ORDER BY d.delivery_id ASC"
        );

        $stmt->execute([
            $dailyId,
            $dailyId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * ---------------------------------------------------------
     * EXPENSES
     * ---------------------------------------------------------
     */

    public function getExpenses(int $dailyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                expense_id,
                expense_date,
                category,
                expense_location,
                description,
                amount,
                notes,
                created_at

             FROM expenses

             WHERE daily_id = ?

             ORDER BY expense_id ASC"
        );

        $stmt->execute([$dailyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

     /*
     * ---------------------------------------------------------
     * PAYROLL
     * ---------------------------------------------------------
     *
     * Finalized payroll is stored in daily_payroll.
     *
     * payroll_amount = Remaining Payroll
     *
     * Cash Advance is already stored separately in expenses.
     *
     * Daily Rate is intentionally not displayed or used.
     */

    public function getPayroll(
        int $dailyId,
        array $expenses
    ): array {

        $stmt = $this->pdo->prepare(
            "SELECT
                dp.employee_id,
                e.full_name AS employee_name,
                dp.payroll_amount

             FROM daily_payroll dp

             INNER JOIN employees e
                ON e.employee_id = dp.employee_id

             WHERE dp.daily_id = ?

             ORDER BY
                e.full_name ASC,
                dp.employee_id ASC"
        );

        $stmt->execute([
            $dailyId
        ]);

        $payrollRows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
         * -----------------------------------------------------
         * CASH ADVANCES
         * -----------------------------------------------------
         */

        $cashAdvances = [];

        foreach ($expenses as $expense) {

            if (
                trim(
                    (string) (
                        $expense['category']
                        ?? ''
                    )
                ) !== 'Cash Advance'
            ) {
                continue;
            }


            $employeeName =
                trim(
                    (string) (
                        $expense['description']
                        ?? ''
                    )
                );


            if ($employeeName === '') {
                continue;
            }


            $key =
                strtolower(
                    $employeeName
                );


            if (!isset($cashAdvances[$key])) {
                $cashAdvances[$key] = 0.00;
            }


            $cashAdvances[$key] +=
                (float) (
                    $expense['amount']
                    ?? 0
                );
        }


        /*
         * -----------------------------------------------------
         * COMBINE PAYROLL VALUES
         * -----------------------------------------------------
         */

        foreach ($payrollRows as &$payroll) {

            $employeeName =
                trim(
                    (string) (
                        $payroll['employee_name']
                        ?? ''
                    )
                );


            $key =
                strtolower(
                    $employeeName
                );


            $cashAdvance =
                round(
                    (float) (
                        $cashAdvances[$key]
                        ?? 0
                    ),
                    2
                );


            $remainingPayroll =
                round(
                    (float) (
                        $payroll['payroll_amount']
                        ?? 0
                    ),
                    2
                );


            $totalPayroll =
                round(
                    $cashAdvance +
                    $remainingPayroll,
                    2
                );


            $payroll['cash_advance'] =
                $cashAdvance;


            $payroll['remaining_payroll'] =
                $remainingPayroll;


            $payroll['total_payroll'] =
                $totalPayroll;
        }

        unset($payroll);


        return $payrollRows;
    }


    /*
     * ---------------------------------------------------------
     * DEBT CREATED
     * ---------------------------------------------------------
     */

    public function getDebts(int $dailyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                d.delivery_id,
                d.customer_id,
                c.customer_name,
                d.delivery_date,
                d.slim_quantity,
                d.round_quantity,
                d.amount_due,

                COALESCE(
                    SUM(
                        CASE
                            WHEN p.daily_id = d.daily_id
                            THEN p.amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS amount_paid,

                (
                    d.amount_due -
                    COALESCE(
                        SUM(
                            CASE
                                WHEN p.daily_id = d.daily_id
                                THEN p.amount
                                ELSE 0
                            END
                        ),
                        0
                    )
                ) AS remaining_balance

             FROM deliveries d

             LEFT JOIN customers c
                ON c.customer_id = d.customer_id

             LEFT JOIN payments p
                ON p.delivery_id = d.delivery_id

             WHERE d.daily_id = ?

             GROUP BY
                d.delivery_id,
                d.customer_id,
                c.customer_name,
                d.delivery_date,
                d.slim_quantity,
                d.round_quantity,
                d.amount_due

             HAVING remaining_balance > 0

             ORDER BY d.delivery_id ASC"
        );

        $stmt->execute([$dailyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * ---------------------------------------------------------
     * CREDIT
     * ---------------------------------------------------------
     */

    public function getCredits(int $dailyId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                cc.customer_id,
                c.customer_name,
                SUM(cc.amount) AS credit_amount

             FROM customer_credits cc

             INNER JOIN customers c
                ON c.customer_id = cc.customer_id

             WHERE cc.daily_id = ?

             GROUP BY
                cc.customer_id,
                c.customer_name

             HAVING credit_amount > 0.009

             ORDER BY c.customer_name ASC"
        );

        $stmt->execute([$dailyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /*
     * ---------------------------------------------------------
     * TOTALS
     * ---------------------------------------------------------
     */

    public function calculateDeliveryQuantity(
        array $deliveries
    ): int {

        return array_sum(
            array_map(
                static fn(array $delivery): int =>
                    (int) ($delivery['slim_quantity'] ?? 0)
                    +
                    (int) ($delivery['round_quantity'] ?? 0),
                $deliveries
            )
        );
    }


    public function calculatePaymentTotal(
        array $deliveries
    ): float {

        return round(
            array_sum(
                array_map(
                    static fn(array $delivery): float =>
                        (float) (
                            $delivery['amount_received'] ?? 0
                        ),
                    $deliveries
                )
            ),
            2
        );
    }


    public function calculateExpenseTotal(
        array $expenses
    ): float {

        return round(
            array_sum(
                array_map(
                    static fn(array $expense): float =>
                        (float) ($expense['amount'] ?? 0),
                    $expenses
                )
            ),
            2
        );
    }


    public function calculateDebtTotal(
        array $debts
    ): float {

        return round(
            array_sum(
                array_map(
                    static fn(array $debt): float =>
                        (float) (
                            $debt['remaining_balance'] ?? 0
                        ),
                    $debts
                )
            ),
            2
        );
    }


    public function calculateCreditTotal(
        array $credits
    ): float {

        return round(
            array_sum(
                array_map(
                    static fn(array $credit): float =>
                        (float) (
                            $credit['credit_amount'] ?? 0
                        ),
                    $credits
                )
            ),
            2
        );
    }
}


/*
 * =========================================================
 * DAILY RECORD FORMATTER
 * =========================================================
 */

final class DailyRecordFormatter
{
    public static function escape(
        mixed $value
    ): string {

        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }


    public static function money(
        mixed $value
    ): string {

        return '₱' . number_format(
            (float) ($value ?? 0),
            2
        );
    }


    public static function date(
        ?string $date
    ): string {

        if (!$date) {
            return '—';
        }

        $timestamp = strtotime($date);

        if (!$timestamp) {
            return self::escape($date);
        }

        return date(
            'F j, Y',
            $timestamp
        );
    }


    public static function deliveryDescription(
        int $slim,
        int $round
    ): string {

        $parts = [];

        if ($slim > 0) {
            $parts[] = $slim . ' Slim';
        }

        if ($round > 0) {
            $parts[] = $round . ' Round';
        }

        return $parts
            ? implode(' + ', $parts)
            : '—';
    }


        public static function recordLabel(
        int $count
    ): string {

        return $count . ' ' .
            ($count === 1 ? 'record' : 'records');
    }


    public static function driverRemittance(
        ?string $status,
        mixed $difference
    ): string {

        $status = trim(
            (string) ($status ?? '')
        );

        if ($status === '') {
            return '—';
        }

        $difference = round(
            (float) ($difference ?? 0),
            2
        );

        if ($status === 'Balanced') {
            return 'Balanced';
        }

        if ($status === 'Short') {
            return 'Short ' .
                self::money(abs($difference));
        }

        if ($status === 'Over') {
            return 'Over ' .
                self::money(abs($difference));
        }

        return self::escape($status);
    }
}


/*
 * =========================================================
 * LOAD DAILY RECORD
 * =========================================================
 */

$dailyId = filter_input(
    INPUT_GET,
    'daily_id',
    FILTER_VALIDATE_INT
);

if (!$dailyId || $dailyId <= 0) {
    header('Location: ../daily-records.php');
    exit;
}


$repository = new DailyRecordRepository($pdo);

$daily = $repository->getDailyRecord($dailyId);

if (!$daily) {
    header('Location: ../daily-records.php');
    exit;
}


/*
 * =========================================================
 * LOAD REPORT DATA
 * =========================================================
 */

$sales = $repository->getSales(
    $daily['business_date'],
    $dailyId
);

$deliveries = $repository->getDeliveries(
    $dailyId
);

$expenses = $repository->getExpenses(
    $dailyId
);

$payroll = $repository->getPayroll(
    $dailyId,
    $expenses
);

$debts = $repository->getDebts(
    $dailyId
);

$credits = $repository->getCredits(
    $dailyId
);


/*
 * =========================================================
 * CALCULATE REPORT TOTALS
 * =========================================================
 */

$paymentTotal =
    $repository->calculatePaymentTotal(
        $deliveries
    );

$deliveryTotalQuantity =
    $repository->calculateDeliveryQuantity(
        $deliveries
    );

$expenseTotal =
    $repository->calculateExpenseTotal(
        $expenses
    );

$debtCreatedTotal =
    $repository->calculateDebtTotal(
        $debts
    );

$creditTotal =
    $repository->calculateCreditTotal(
        $credits
    );

    $payrollCashAdvanceTotal = round(
    array_sum(
        array_map(
            static fn(array $row): float =>
                (float) (
                    $row['cash_advance']
                    ?? 0
                ),
            $payroll
        )
    ),
    2
);

$remainingPayrollTotal = round(
    array_sum(
        array_map(
            static fn(array $row): float =>
                (float) (
                    $row['remaining_payroll']
                    ?? 0
                ),
            $payroll
        )
    ),
    2
);

$payrollTotal = round(
    array_sum(
        array_map(
            static fn(array $row): float =>
                (float) (
                    $row['total_payroll']
                    ?? 0
                ),
            $payroll
        )
    ),
    2
);


/*
 * =========================================================
 * DISPLAY VALUES
 * =========================================================
 */

$walkInGallons = (int) (
    $sales['walk_in_customers'] ?? 0
);

$walkInTotal = (float) (
    $sales['walk_in_total'] ?? 0
);

$otherShopPayment = (float) (
    $sales['other_shop_payment'] ?? 0
);

$otherSales = (float) (
    $sales['other_sales'] ?? 0
);


/*
 * =========================================================
 * NET PROFIT
 * =========================================================
 *
 * Preserved from the existing report logic:
 *
 * Walk-In Total
 * + Other Sales
 * + Delivery Payments Received
 *
 * Expenses are displayed separately.
 *
 * =========================================================
 */

$totalRevenue = round(
    $walkInTotal
    + $otherSales
    + $paymentTotal,
    2
);

$netProfit = round(
    $totalRevenue
    - $expenseTotal
    - $remainingPayrollTotal,
    2
);


/*
 * =========================================================
 * SIDEBAR CONFIGURATION
 * =========================================================
 */

$currentPage = 'daily-records';
$pageRoot = '../';
$assetRoot = '../../';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Daily Record | Marcid Blue Binangonan
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/app.css"
    >

    <style>

        /* =========================================================
           DAILY RECORD VIEW
           ========================================================= */

        .record-page {
            padding: 32px;
        }

        .record-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .record-toolbar-left h2 {
            margin: 0;
            font-size: 24px;
        }

        .record-toolbar-left p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .record-toolbar-actions {
            display: flex;
            gap: 10px;
        }


        /* =========================================================
           REPORT PAPER
           ========================================================= */

        .daily-record-paper {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow:
                0 8px 30px
                rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }


        /* =========================================================
           REPORT HEADER
           ========================================================= */

        .report-header {
            padding: 30px 40px 26px;
            border-bottom: 3px solid #1687c9;
        }

        .report-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .report-brand-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .report-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
            flex: 0 0 58px;
        }

        .report-brand-text {
            min-width: 0;
        }

        .report-brand-name {
            font-size: 24px;
            line-height: 1.2;
            font-weight: 700;
            color: #1687c9;
            letter-spacing: 0.2px;
        }

        .report-brand-subtitle {
            margin-top: 3px;
            font-size: 12px;
            color: #6b7280;
        }

        .report-brand-date {
            margin-top: 5px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }

        .report-document-title {
            flex-shrink: 0;
            text-align: right;
        }

        .report-document-title h1 {
            margin: 0;
            font-size: 22px;
            color: #111827;
            letter-spacing: 0.5px;
        }

        .report-document-title p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #6b7280;
        }


        /* =========================================================
           REPORT BODY
           ========================================================= */

        .report-body {
            padding: 32px 40px 40px;
        }

        .report-section {
            margin-bottom: 26px;
        }

        .report-section:last-child {
            margin-bottom: 0;
        }

        .report-section-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
            padding-bottom: 9px;
            border-bottom: 1px solid #dfe3e8;
        }

        .report-section-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .report-section-header span {
            font-size: 11px;
            color: #6b7280;
        }


        /* =========================================================
           SUMMARY
           ========================================================= */

        .walkin-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            overflow: hidden;
        }

        .walkin-item {
            padding: 18px 20px;
            border-right: 1px solid #e5e7eb;
        }

        .walkin-item:last-child {
            border-right: 0;
        }

        .walkin-label {
            display: block;
            margin-bottom: 7px;
            font-size: 11px;
            color: #6b7280;
        }

        .walkin-value {
            font-size: 19px;
            font-weight: 700;
            color: #111827;
        }

                .driver-remittance-value {
            white-space: nowrap;
        }

        .driver-remittance-balanced {
            color: #15803d;
        }

        .driver-remittance-short {
            color: #c2410c;
        }

        .driver-remittance-over {
            color: #1687c9;
        }


        /* =========================================================
            SHARED TABLE
            ========================================================= */

            .report-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 12px;
            }

            .report-table th {
                padding: 8px 6px;
                background: #f8fafc;
                border-top: 1px solid #e5e7eb;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                color: #64748b;
            }

            .report-table td {
                padding: 8px 6px;
                border-bottom: 1px solid #edf0f2;
                color: #374151;
                vertical-align: middle;
            }

            .report-table tbody tr:last-child td {
                border-bottom: 1px solid #e5e7eb;
            }

            .report-table .number {
                text-align: right;
            }

            .report-table .center {
                text-align: center;
            }

            .report-table .strong {
                font-weight: 600;
                color: #111827;
            }


        /* =========================================================
           DELIVERY + PAYMENT TABLE
           ========================================================= */

        .delivery-payment-table th:first-child,
        .delivery-payment-table td:first-child {
            width: 36%;
        }

        .delivery-payment-table th:nth-child(2),
        .delivery-payment-table td:nth-child(2),
        .delivery-payment-table th:nth-child(3),
        .delivery-payment-table td:nth-child(3) {
            width: 10%;
        }

        .delivery-payment-table th:nth-child(4),
        .delivery-payment-table td:nth-child(4) {
            width: 18%;
        }

        .delivery-payment-table th:nth-child(5),
        .delivery-payment-table td:nth-child(5) {
            width: 26%;
        }


        /* =========================================================
           DEBT + CREDIT TABLE
           ========================================================= */

        .debt-credit-table th,
        .debt-credit-table td {
            width: 20%;
        }

        .debt-credit-table th:nth-child(-n + 2),
        .debt-credit-table td:nth-child(-n + 2) {
            text-align: left;
        }

        .debt-credit-table th:nth-child(n + 3),
        .debt-credit-table td:nth-child(n + 3) {
            text-align: right;
        }


        /* =========================================================
           EXPENSES TABLE
           ========================================================= */

        .expenses-table th,
        .expenses-table td {
            width: 25%;
        }

        /* =========================================================
           PAYROLL TABLE
           ========================================================= */

        .payroll-table th:first-child,
        .payroll-table td:first-child {
            width: 34%;
        }

        .payroll-table th:nth-child(2),
        .payroll-table td:nth-child(2),
        .payroll-table th:nth-child(3),
        .payroll-table td:nth-child(3),
        .payroll-table th:nth-child(4),
        .payroll-table td:nth-child(4) {
            width: 22%;
        }


        /* =========================================================
           TOTAL ROW
           ========================================================= */

        .report-total-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .report-total {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-width: 210px;
            padding: 9px 12px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        .report-total-label {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-total-value {
            margin-left: 0;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
        }


        /* =========================================================
           DEBT TOTAL
           ========================================================= */

        .debt-total {
            background: #fff7ed;
            border-color: #fed7aa;
        }

        .debt-total .report-total-label,
        .debt-total .report-total-value {
            color: #c2410c;
        }


        /* =========================================================
           CREDIT TOTAL
           ========================================================= */

        .credit-total {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .credit-total .report-total-label,
        .credit-total .report-total-value {
            color: #047857;
        }


        /* =========================================================
           EMPTY STATE
           ========================================================= */

        .report-empty {
            padding: 18px;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }


        /* =========================================================
           REPORT FOOTER
           ========================================================= */

        .report-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 40px;
            border-top: 1px solid #e5e7eb;
            background: #fafafa;
        }

        .report-footer-note {
            font-size: 10px;
            line-height: 1.5;
            color: #6b7280;
        }


        /* =========================================================
           RESPONSIVE
           ========================================================= */

        @media (max-width: 800px) {

            .record-page {
                padding: 20px;
            }

            .record-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .report-header {
                padding: 26px 24px 22px;
            }

            .report-brand {
                flex-direction: column;
                align-items: flex-start;
            }

            .report-brand-left {
                width: 100%;
            }

            .report-document-title {
                text-align: left;
            }

            .report-body {
                padding: 24px;
            }

            .walkin-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .walkin-item {
                border-right: 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .walkin-item:last-child {
                border-bottom: 0;
            }

            .report-table {
                min-width: 700px;
            }

            .report-footer {
                padding: 20px 24px;
            }
        }


        /* =========================================================
           SMALL MOBILE
           ========================================================= */

        @media (max-width: 520px) {

            .report-brand-left {
                gap: 10px;
            }

            .report-logo {
                width: 48px;
                height: 48px;
                flex-basis: 48px;
            }

            .report-brand-name {
                font-size: 20px;
            }

            .report-brand-subtitle,
            .report-brand-date {
                font-size: 11px;
            }

            .walkin-grid {
                grid-template-columns: 1fr;
            }

            .walkin-item {
                border-right: 0;
            }
        }


        /* =========================================================
           PRINT
           ========================================================= */

        @media print {

        @page {
            size: A4;
            margin: 12mm;
        }

        body {
            background: #ffffff !important;
        }

        .sidebar,
        .topbar,
        .record-toolbar {
            display: none !important;
        }

        .main {
            margin: 0 !important;
            padding: 0 !important;
        }

        .record-page {
            padding: 0 !important;
        }

        .daily-record-paper {
            max-width: none;
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }

        .report-header {
            padding: 0 0 18px;
        }

        .report-body {
            padding: 20px 0 0;
        }

        .report-section {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .report-table {
            min-width: 0;
        }

        /* Keep summary cards side-by-side when printing */
        .walkin-grid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 10px !important;
            width: 100% !important;

            break-inside: avoid;
            page-break-inside: avoid;
        }

        .walkin-card {
            width: auto !important;
            min-width: 0 !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .report-footer {
            padding: 18px 0 0;
            background: #ffffff;
        }
    }

    </style>

</head>

<body>

<div class="app">

    <?php require_once '../../includes/sidebar.php'; ?>

    <main class="main">

        <header class="topbar">

            <div class="topbar-title">
                Daily Record
            </div>

            <div class="topbar-user">
                👤
                <?= DailyRecordFormatter::escape(
                    $_SESSION['full_name'] ?? 'Admin'
                ) ?>
            </div>

        </header>


        <div class="record-page">

            <!-- =====================================================
                 TOOLBAR
                 ===================================================== -->

            <div class="record-toolbar">

                <div class="record-toolbar-left">

                    <h2>
                        Daily Record
                    </h2>

                    <p>
                        Detailed view of saved daily operations.
                    </p>

                </div>

                <div class="record-toolbar-actions">

                    <a
                        href="../daily-records.php"
                        class="btn btn-outline"
                    >
                        Back to Daily Records
                    </a>

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()"
                    >
                        Print
                    </button>

                </div>

            </div>


            <!-- =====================================================
                 REPORT PAPER
                 ===================================================== -->

            <div class="daily-record-paper">

                <!-- =================================================
                     REPORT HEADER
                     ================================================= -->

                <header class="report-header">

                    <div class="report-brand">

                        <div class="report-brand-left">

                            <img
                                src="../../assets/images/mb-logo.png"
                                alt="Marcid Blue Logo"
                                class="report-logo"
                            >

                            <div class="report-brand-text">

                                <div class="report-brand-name">
                                    Marcid Blue Binangonan
                                </div>

                                <div class="report-brand-subtitle">
                                    Water Station
                                </div>

                                <div class="report-brand-date">
                                    <?= DailyRecordFormatter::escape(
                                        DailyRecordFormatter::date(
                                            $daily['business_date']
                                        )
                                    ) ?>
                                </div>

                            </div>

                        </div>

                        <div class="report-document-title">

                            <h1>
                                Daily Record
                            </h1>

                            <p>
                                Official Daily Operations Record
                            </p>

                        </div>

                    </div>

                </header>


                <!-- =================================================
                     REPORT BODY
                     ================================================= -->

                <div class="report-body">


                    <!-- =================================================
                         SUMMARY
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Daily Summary
                            </h3>

                        </div>

                        <div class="walkin-grid">

                            <div class="walkin-item">

                                <span class="walkin-label">
                                    Walk-In Gallons
                                </span>

                                <span class="walkin-value">
                                    <?= number_format(
                                        $walkInGallons
                                    ) ?>
                                </span>

                            </div>

                            <div class="walkin-item">

                                <span class="walkin-label">
                                    Deliveries
                                </span>

                                <span class="walkin-value">
                                    <?= number_format(
                                        $deliveryTotalQuantity
                                    ) ?>
                                </span>

                            </div>

                            <div class="walkin-item">

                                <span class="walkin-label">
                                    Net Profit
                                </span>

                                <span class="walkin-value">
                                    <?= DailyRecordFormatter::money(
                                        $netProfit
                                    ) ?>
                                </span>

                            </div>

                                <div class="walkin-item">

                                <span class="walkin-label">
                                    Driver Remittance
                                </span>

                                <span
                                    class="walkin-value driver-remittance-value
                                    <?php
                                        if (
                                            ($daily['driver_remittance_status'] ?? '')
                                            === 'Balanced'
                                        ) {
                                            echo ' driver-remittance-balanced';
                                        } elseif (
                                            ($daily['driver_remittance_status'] ?? '')
                                            === 'Short'
                                        ) {
                                            echo ' driver-remittance-short';
                                        } elseif (
                                            ($daily['driver_remittance_status'] ?? '')
                                            === 'Over'
                                        ) {
                                            echo ' driver-remittance-over';
                                        }
                                    ?>"
                                >
                                    <?= DailyRecordFormatter::driverRemittance(
                                        $daily['driver_remittance_status'] ?? null,
                                        $daily['driver_remittance_difference'] ?? null
                                    ) ?>
                                </span>

                            </div>

                        </div>

                    </section>


                    <!-- =================================================
                         DELIVERIES & PAYMENTS
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Deliveries &amp; Payments
                            </h3>

                            <span>
                                <?= DailyRecordFormatter::recordLabel(
                                    count($deliveries)
                                ) ?>
                            </span>

                        </div>


                        <?php if (empty($deliveries)): ?>

                            <div class="report-empty">
                                No deliveries recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table delivery-payment-table">

                                <thead>

                                    <tr>
                                        <th>Customer</th>
                                        <th class="center">Slim</th>
                                        <th class="center">Round</th>
                                        <th class="number">Price/Gal</th>
                                        <th class="number">Amount</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($deliveries as $delivery): ?>

                                        <tr>

                                            <td class="strong">
                                                <?= DailyRecordFormatter::escape(
                                                    $delivery['customer_name']
                                                        ?? 'Unknown Customer'
                                                ) ?>
                                            </td>

                                            <td class="center">
                                                <?= (int) (
                                                    $delivery['slim_quantity']
                                                    ?? 0
                                                ) ?>
                                            </td>

                                            <td class="center">
                                                <?= (int) (
                                                    $delivery['round_quantity']
                                                    ?? 0
                                                ) ?>
                                            </td>

                                            <td class="number">
                                                <?= DailyRecordFormatter::money(
                                                    $delivery['price_per_gallon']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                            <td class="number strong">
                                                <?= DailyRecordFormatter::money(
                                                    $delivery['amount_received']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>


                            <div class="report-total-row">

                                <div class="report-total">

                                    <span class="report-total-label">
                                        Total Received
                                    </span>

                                    <span class="report-total-value">
                                        <?= DailyRecordFormatter::money(
                                            $paymentTotal
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>


                    <!-- =================================================
                         DEBT CREATED / CREDIT
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Debt Created / Credit
                            </h3>

                            <span>
                                <?= DailyRecordFormatter::recordLabel(
                                    count($debts) + count($credits)
                                ) ?>
                            </span>

                        </div>


                        <?php if (
                            empty($debts) &&
                            empty($credits)
                        ): ?>

                            <div class="report-empty">
                                No debt or credit recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table debt-credit-table">

                                <thead>

                                    <tr>
                                        <th>Customer</th>
                                        <th>Delivery</th>
                                        <th class="number">Amount Due</th>
                                        <th class="number">Paid</th>
                                        <th class="number">Remaining</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($debts as $debt): ?>

                                        <tr>

                                            <td class="strong">
                                                <?= DailyRecordFormatter::escape(
                                                    $debt['customer_name']
                                                        ?? 'Unknown Customer'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= DailyRecordFormatter::escape(
                                                    DailyRecordFormatter::deliveryDescription(
                                                        (int) (
                                                            $debt['slim_quantity']
                                                            ?? 0
                                                        ),
                                                        (int) (
                                                            $debt['round_quantity']
                                                            ?? 0
                                                        )
                                                    )
                                                ) ?>
                                            </td>

                                            <td class="number">
                                                <?= DailyRecordFormatter::money(
                                                    $debt['amount_due']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                            <td class="number">
                                                <?= DailyRecordFormatter::money(
                                                    $debt['amount_paid']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                            <td class="number strong">
                                                <?= DailyRecordFormatter::money(
                                                    $debt['remaining_balance']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>


                                    <?php foreach ($credits as $credit): ?>

                                        <tr>

                                            <td class="strong">
                                                <?= DailyRecordFormatter::escape(
                                                    $credit['customer_name']
                                                        ?? 'Unknown Customer'
                                                ) ?>
                                            </td>

                                            <td class="strong">
                                                Credit
                                            </td>

                                            <td class="number">
                                                —
                                            </td>

                                            <td class="number">
                                                —
                                            </td>

                                            <td class="number strong">
                                                <?= DailyRecordFormatter::money(
                                                    $credit['credit_amount']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>


                            <?php if ($debtCreatedTotal > 0): ?>

                                <div class="report-total-row">

                                    <div class="report-total debt-total">

                                        <span class="report-total-label">
                                            Total Debt
                                        </span>

                                        <span class="report-total-value">
                                            <?= DailyRecordFormatter::money(
                                                $debtCreatedTotal
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if ($creditTotal > 0): ?>

                                <div class="report-total-row">

                                    <div class="report-total credit-total">

                                        <span class="report-total-label">
                                            Total Credit
                                        </span>

                                        <span class="report-total-value">
                                            <?= DailyRecordFormatter::money(
                                                $creditTotal
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                            <?php endif; ?>

                        <?php endif; ?>

                    </section>


                    <!-- =================================================
                         EXPENSES
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Expenses
                            </h3>

                            <span>
                                <?= DailyRecordFormatter::recordLabel(
                                    count($expenses)
                                ) ?>
                            </span>

                        </div>


                        <?php if (empty($expenses)): ?>

                            <div class="report-empty">
                                No expenses recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table expenses-table">

                                <thead>

                                    <tr>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Location</th>
                                        <th class="number">Amount</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($expenses as $expense): ?>

                                        <tr>

                                            <td class="strong">
                                                <?= DailyRecordFormatter::escape(
                                                    $expense['category']
                                                        ?? '—'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= DailyRecordFormatter::escape(
                                                    $expense['description']
                                                        ?? '—'
                                                ) ?>
                                            </td>

                                            <td>
                                                <?= DailyRecordFormatter::escape(
                                                    $expense['expense_location']
                                                        ?? '—'
                                                ) ?>
                                            </td>

                                            <td class="number strong">
                                                <?= DailyRecordFormatter::money(
                                                    $expense['amount']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>


                            <div class="report-total-row">

                                <div class="report-total">

                                    <span class="report-total-label">
                                        Total Expenses
                                    </span>

                                    <span class="report-total-value">
                                        <?= DailyRecordFormatter::money(
                                            $expenseTotal
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>
                    
                     <!-- =================================================
                         PAYROLL
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Payroll
                            </h3>

                            <span>
                                <?= DailyRecordFormatter::recordLabel(
                                    count($payroll)
                                ) ?>
                            </span>

                        </div>


                        <?php if (empty($payroll)): ?>

                            <div class="report-empty">
                                No payroll recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table payroll-table">

                                <thead>

                                    <tr>
                                        <th>Employee</th>
                                        <th class="number">Cash Advance</th>
                                        <th class="number">Remaining Payroll</th>
                                        <th class="number">Total Payroll</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($payroll as $row): ?>

                                        <tr>

                                            <td class="strong">
                                                <?= DailyRecordFormatter::escape(
                                                    $row['employee_name']
                                                        ?? 'Unknown Employee'
                                                ) ?>
                                            </td>

                                            <td class="number">
                                                <?= DailyRecordFormatter::money(
                                                    $row['cash_advance']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                            <td class="number">
                                                <?= DailyRecordFormatter::money(
                                                    $row['remaining_payroll']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                            <td class="number strong">
                                                <?= DailyRecordFormatter::money(
                                                    $row['total_payroll']
                                                        ?? 0
                                                ) ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                            <div class="report-total-row">

                                <div class="report-total">

                                    <span class="report-total-label">
                                        Total Payroll
                                    </span>

                                    <span class="report-total-value">
                                        <?= DailyRecordFormatter::money(
                                            $payrollTotal
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>

                </div>


                <!-- =====================================================
                     REPORT FOOTER
                     ===================================================== -->

                <footer class="report-footer">

                    <div class="report-footer-note">

                        This document is a read-only record of the
                        saved daily operations data.

                        <br>

                        Generated from Marcid Blue Water Station.

                    </div>

                </footer>

            </div>

        </div>

    </main>

</div>

</body>

</html>