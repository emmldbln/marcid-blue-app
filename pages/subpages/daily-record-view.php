<?php

require_once '../../auth/auth.php';
requireAdmin();

require_once '../../config/database.php';


/*
 * =========================================================
 * DAILY RECORD ID
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


/*
 * =========================================================
 * HELPERS
 * =========================================================
 */

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function money($value): string
{
    return '₱' . number_format(
        (float) ($value ?? 0),
        2
    );
}


function formatDate($date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return escapeHtml($date);
    }

    return date('F j, Y', $timestamp);
}


function deliveryDescription(
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

    if (!$parts) {
        return '—';
    }

    return implode(' + ', $parts);
}


/*
 * =========================================================
 * DAILY RECORD
 * =========================================================
 */

$stmt = $pdo->prepare(
    "SELECT
        daily_id,
        business_date,
        status,
        closing_result,
        actual_station_cash,
        created_at,
        updated_at,
        saved_at,
        saved_by
     FROM daily_records
     WHERE daily_id = ?
     LIMIT 1"
);

$stmt->execute([
    $dailyId
]);

$daily = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$daily) {

    header('Location: ../daily-records.php');
    exit;

}


/*
 * =========================================================
 * WALK-IN SALES
 * =========================================================
 */

$stmt = $pdo->prepare(
    "SELECT
        COALESCE(SUM(walk_in_customers), 0) AS walk_in_customers,
        COALESCE(SUM(walk_in_customers * walk_in_price), 0) AS walk_in_total,
        COALESCE(SUM(other_shop_payment), 0) AS other_shop_payment,
        COALESCE(SUM(other_sales), 0) AS other_sales
     FROM daily_sales
     WHERE sales_date = ?
       AND daily_id = ?"
);

$stmt->execute([
    $daily['business_date'],
    $dailyId
]);

$sales = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sales) {

    $sales = [
        'walk_in_customers' => 0,
        'walk_in_total' => 0,
        'other_shop_payment' => 0,
        'other_sales' => 0
    ];

}


/*
 * =========================================================
 * DELIVERIES
 * =========================================================
 */

$stmt = $pdo->prepare(
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
        d.created_at
     FROM deliveries d
     LEFT JOIN customers c
        ON c.customer_id = d.customer_id
     WHERE d.daily_id = ?
     ORDER BY d.delivery_id ASC"
);

$stmt->execute([
    $dailyId
]);

$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * =========================================================
 * PAYMENTS
 * =========================================================
 */

$stmt = $pdo->prepare(
    "SELECT
        p.payment_id,
        p.delivery_id,
        p.customer_id,
        c.customer_name,
        p.payment_date,
        p.amount,
        p.payment_method,
        p.collection_location,
        p.notes,
        p.created_at
     FROM payments p
     LEFT JOIN customers c
        ON c.customer_id = p.customer_id
     WHERE p.daily_id = ?
     ORDER BY p.payment_id ASC"
);

$stmt->execute([
    $dailyId
]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * =========================================================
 * EXPENSES
 * =========================================================
 */

$stmt = $pdo->prepare(
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

$stmt->execute([
    $dailyId
]);

$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * =========================================================
 * DELIVERY TOTAL
 * =========================================================
 */

$deliveryTotal = 0;

foreach ($deliveries as $delivery) {

    $deliveryTotal += (float) (
        $delivery['amount_due'] ?? 0
    );

}


/*
 * =========================================================
 * PAYMENT TOTAL
 * =========================================================
 */

$paymentTotal = 0;

foreach ($payments as $payment) {

    $paymentTotal += (float) (
        $payment['amount'] ?? 0
    );

}


/*
 * =========================================================
 * EXPENSE TOTAL
 * =========================================================
 */

$expenseTotal = 0;

foreach ($expenses as $expense) {

    $expenseTotal += (float) (
        $expense['amount'] ?? 0
    );

}


/*
 * =========================================================
 * DEBT CREATED ON THIS DAY
 *
 * Important:
 *
 * We only consider payments that belong to THIS daily
 * record when determining how much debt was created
 * on this day.
 *
 * A later payment on another day does not erase the
 * debt from this historical Daily Record.
 * =========================================================
 */

$stmt = $pdo->prepare(
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

$stmt->execute([
    $dailyId
]);

$debts = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * =========================================================
 * DEBT TOTAL
 * =========================================================
 */

$debtCreatedTotal = 0;

foreach ($debts as $debt) {

    $debtCreatedTotal += (float) (
        $debt['remaining_balance'] ?? 0
    );

}


/*
 * =========================================================
 * DISPLAY VALUES
 * =========================================================
 */

$walkInCustomers = (int) (
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

$closingResult = trim(
    (string) (
        $daily['closing_result'] ?? ''
    )
);

if ($closingResult === '') {
    $closingResult = 'Pending';
}

$closingResultUpper = strtoupper(
    $closingResult
);

$status = strtoupper(
    (string) (
        $daily['status'] ?? ''
    )
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
        Daily Record #<?= (int) $dailyId ?> | Marcid Blue
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
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }


        /* =========================================================
           REPORT HEADER
           ========================================================= */

        .report-header {
            padding: 34px 40px 28px;
            border-bottom: 3px solid #1687c9;
        }

        .report-brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .report-brand-name {
            font-size: 24px;
            font-weight: 700;
            color: #1687c9;
            letter-spacing: 0.2px;
        }

        .report-brand-subtitle {
            margin-top: 3px;
            font-size: 12px;
            color: #6b7280;
        }

        .report-document-title {
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
           REPORT META
           ========================================================= */

        .report-meta {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            border-bottom: 1px solid #e5e7eb;
        }

        .report-meta-item {
            padding: 18px 24px;
            border-right: 1px solid #e5e7eb;
        }

        .report-meta-item:last-child {
            border-right: 0;
        }

        .report-meta-label {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #6b7280;
        }

        .report-meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }


        /* =========================================================
           STATUS
           ========================================================= */

        .report-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #047857;
            font-size: 11px;
            font-weight: 700;
        }

        .report-result {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
        }


        /* =========================================================
           REPORT BODY
           ========================================================= */

        .report-body {
            padding: 32px 40px 40px;
        }

        .report-section {
            margin-bottom: 32px;
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
           WALK-IN SUMMARY
           ========================================================= */

        .walkin-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
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


        /* =========================================================
           TABLE
           ========================================================= */

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .report-table th {
            padding: 10px 12px;
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
        }

        .report-table td {
            padding: 12px;
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
           TOTAL ROW
           ========================================================= */

        .report-total-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 12px;
        }

        .report-total {
            display: flex;
            align-items: center;
            gap: 30px;
            min-width: 230px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        .report-total-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .report-total-value {
            margin-left: auto;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }


        /* =========================================================
           DEBT
           ========================================================= */

        .debt-total {
            background: #fff7ed;
            border-color: #fed7aa;
        }

        .debt-total .report-total-label {
            color: #9a3412;
        }

        .debt-total .report-total-value {
            color: #c2410c;
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

        .report-footer-id {
            text-align: right;
            font-size: 10px;
            color: #9ca3af;
        }


        /* =========================================================
           RESPONSIVE
           ========================================================= */

        @media (max-width: 800px) {

            .record-page {
                padding: 20px;
            }

            .report-header {
                padding: 26px 24px 22px;
            }

            .report-brand {
                flex-direction: column;
                align-items: flex-start;
            }

            .report-document-title {
                text-align: left;
            }

            .report-meta {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .report-meta-item:nth-child(2) {
                border-right: 0;
            }

            .report-body {
                padding: 24px;
            }

            .walkin-grid {
                grid-template-columns: 1fr;
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

            .record-toolbar {
                align-items: flex-start;
                flex-direction: column;
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


        /* =========================================================
        BASIC PRINT RESET
        ========================================================= */

        body {
            background: #ffffff !important;
        }

        .sidebar,
        .topbar,
        .record-toolbar,
        .no-print {
            display: none !important;
        }

        .main {
            margin-left: 0 !important;
            width: 100% !important;
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
            padding-top: 0;
        }


        /* =========================================================
        MULTI-PAGE REPORT FLOW
        ========================================================= */

        .report-body {
            break-inside: auto;
            page-break-inside: auto;
        }

        .report-section {
            /*
            * Allow long sections to continue naturally
            * across multiple A4 pages.
            */
            break-inside: auto;
            page-break-inside: auto;
        }


        /* =========================================================
        KEEP SECTION HEADERS WITH THEIR CONTENT
        ========================================================= */

        .report-section-header {
            break-after: avoid;
            page-break-after: avoid;
        }


        /* =========================================================
        TABLES
        ========================================================= */

        .report-table {
            width: 100%;
            break-inside: auto;
            page-break-inside: auto;
        }


        /*
        * Repeat the table header whenever the table
        * continues onto another printed page.
        */
        .report-table thead {
            display: table-header-group;
        }


        /*
        * Keep the table body as a normal table body.
        */
        .report-table tbody {
            display: table-row-group;
        }


        /*
        * Never split an individual customer/payment/
        * debt/expense row across two pages.
        */
        .report-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }


        /*
        * Keep individual cells together as well.
        */
        .report-table td,
        .report-table th {
            break-inside: avoid;
            page-break-inside: avoid;
        }


        /* =========================================================
        TOTALS
        ========================================================= */

        .report-total-row {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .report-total {
            break-inside: avoid;
            page-break-inside: avoid;
        }


        /* =========================================================
        WALK-IN SUMMARY
        ========================================================= */

        .walkin-grid {
            break-inside: avoid;
            page-break-inside: avoid;
        }


        /* =========================================================
        FOOTER
        ========================================================= */

        .report-footer {
            break-inside: avoid;
            page-break-inside: avoid;
        }

    }

    </style>

</head>


<body>

<div class="app">

    <?php require_once '../../includes/sidebar.php'; ?>


    <main class="main">

        <div class="record-page">


            <!-- =====================================================
                 TOOLBAR
                 ===================================================== -->

            <div class="record-toolbar no-print">

                <div class="record-toolbar-left">

                    <h2>
                        Daily Record
                    </h2>

                    <p>
                        Read-only record for Daily ID
                        #<?= (int) $dailyId ?>
                    </p>

                </div>


                <div class="record-toolbar-actions">

                    <a
                        href="../daily-records.php"
                        class="btn btn-outline"
                    >
                        Back
                    </a>

                    <button
                        type="button"
                        class="btn btn-primary"
                        onclick="window.print()"
                    >
                        Print Record
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

                        <div>

                            <div class="report-brand-name">
                                MARCID BLUE
                            </div>

                            <div class="report-brand-subtitle">
                                Water Station
                            </div>

                        </div>


                        <div class="report-document-title">

                            <h1>
                                DAILY RECORD
                            </h1>

                            <p>
                                Daily Operations Report
                            </p>

                        </div>

                    </div>

                </header>


                <!-- =================================================
                     REPORT META
                     ================================================= -->

                <div class="report-meta">


                    <div class="report-meta-item">

                        <span class="report-meta-label">
                            Date
                        </span>

                        <span class="report-meta-value">
                            <?= escapeHtml(
                                formatDate(
                                    $daily['business_date']
                                )
                            ) ?>
                        </span>

                    </div>


                    <div class="report-meta-item">

                        <span class="report-meta-label">
                            Daily ID
                        </span>

                        <span class="report-meta-value">
                            #<?= (int) $dailyId ?>
                        </span>

                    </div>


                    <div class="report-meta-item">

                        <span class="report-meta-label">
                            Status
                        </span>

                        <span class="report-status">
                            <?= escapeHtml($status) ?>
                        </span>

                    </div>


                    <div class="report-meta-item">

                        <span class="report-meta-label">
                            Closing Result
                        </span>

                        <span class="report-result">
                            <?= escapeHtml($closingResultUpper) ?>
                        </span>

                    </div>

                </div>


                <!-- =================================================
                     REPORT BODY
                     ================================================= -->

                <div class="report-body">


                    <!-- =================================================
                         WALK-IN SALES
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Walk-in Sales
                            </h3>

                            <span>
                                Station
                            </span>

                        </div>


                        <div class="walkin-grid">


                            <div class="walkin-item">

                                <span class="walkin-label">
                                    Customers
                                </span>

                                <span class="walkin-value">
                                    <?= number_format(
                                        $walkInCustomers
                                    ) ?>
                                </span>

                            </div>


                            <div class="walkin-item">

                                <span class="walkin-label">
                                    Price per Customer
                                </span>

                                <span class="walkin-value">
                                    <?= money(
                                        $walkInCustomers > 0
                                            ? (
                                                $walkInTotal /
                                                $walkInCustomers
                                            )
                                            : 30
                                    ) ?>
                                </span>

                            </div>


                            <div class="walkin-item">

                                <span class="walkin-label">
                                    Total Sales
                                </span>

                                <span class="walkin-value">
                                    <?= money($walkInTotal) ?>
                                </span>

                            </div>


                        </div>


                        <?php if (
                            $otherShopPayment > 0 ||
                            $otherSales > 0
                        ): ?>

                            <div
                                style="
                                    margin-top:12px;
                                    font-size:12px;
                                    color:#6b7280;
                                "
                            >

                                <?php if ($otherShopPayment > 0): ?>

                                    Other Shop Payment:
                                    <strong>
                                        <?= money(
                                            $otherShopPayment
                                        ) ?>
                                    </strong>

                                <?php endif; ?>


                                <?php if (
                                    $otherShopPayment > 0 &&
                                    $otherSales > 0
                                ): ?>

                                    &nbsp;•&nbsp;

                                <?php endif; ?>


                                <?php if ($otherSales > 0): ?>

                                    Other Sales:
                                    <strong>
                                        <?= money($otherSales) ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    </section>


                    <!-- =================================================
                         DELIVERIES
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Deliveries
                            </h3>

                            <span>
                                <?= count($deliveries) ?>
                                <?= count($deliveries) === 1
                                    ? 'record'
                                    : 'records'
                                ?>
                            </span>

                        </div>


                        <?php if (empty($deliveries)): ?>

                            <div class="report-empty">
                                No deliveries recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th class="center">
                                            Slim
                                        </th>

                                        <th class="center">
                                            Round
                                        </th>

                                        <th class="number">
                                            Price / Gallon
                                        </th>

                                        <th class="number">
                                            Amount Due
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $deliveries
                                        as $delivery
                                    ): ?>

                                        <tr>

                                            <td class="strong">

                                                <?= escapeHtml(
                                                    $delivery[
                                                        'customer_name'
                                                    ] ?? 'Unknown Customer'
                                                ) ?>

                                            </td>

                                            <td class="center">

                                                <?= (int) (
                                                    $delivery[
                                                        'slim_quantity'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                            <td class="center">

                                                <?= (int) (
                                                    $delivery[
                                                        'round_quantity'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                            <td class="number">

                                                <?= money(
                                                    $delivery[
                                                        'price_per_gallon'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                            <td class="number strong">

                                                <?= money(
                                                    $delivery[
                                                        'amount_due'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>


                            <div class="report-total-row">

                                <div class="report-total">

                                    <span class="report-total-label">
                                        Total Due
                                    </span>

                                    <span class="report-total-value">
                                        <?= money(
                                            $deliveryTotal
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>


                    <!-- =================================================
                         PAYMENTS
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Payments Received
                            </h3>

                            <span>
                                <?= count($payments) ?>
                                <?= count($payments) === 1
                                    ? 'record'
                                    : 'records'
                                ?>
                            </span>

                        </div>


                        <?php if (empty($payments)): ?>

                            <div class="report-empty">
                                No payments recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th class="number">
                                            Amount
                                        </th>

                                        <th>
                                            Method
                                        </th>

                                        <th>
                                            Location
                                        </th>

                                        <th>
                                            Payment Date
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $payments
                                        as $payment
                                    ): ?>

                                        <tr>

                                            <td class="strong">

                                                <?= escapeHtml(
                                                    $payment[
                                                        'customer_name'
                                                    ] ?? 'Unknown Customer'
                                                ) ?>

                                            </td>

                                            <td class="number strong">

                                                <?= money(
                                                    $payment[
                                                        'amount'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= escapeHtml(
                                                    $payment[
                                                        'payment_method'
                                                    ] ?? '—'
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= escapeHtml(
                                                    $payment[
                                                        'collection_location'
                                                    ] ?? '—'
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= escapeHtml(
                                                    formatDate(
                                                        $payment[
                                                            'payment_date'
                                                        ] ?? null
                                                    )
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
                                        <?= money(
                                            $paymentTotal
                                        ) ?>
                                    </span>

                                </div>

                            </div>

                        <?php endif; ?>

                    </section>


                    <!-- =================================================
                         DEBT CREATED
                         ================================================= -->

                    <section class="report-section">

                        <div class="report-section-header">

                            <h3>
                                Debt Created
                            </h3>

                            <span>
                                <?= count($debts) ?>
                                <?= count($debts) === 1
                                    ? 'record'
                                    : 'records'
                                ?>
                            </span>

                        </div>


                        <?php if (empty($debts)): ?>

                            <div class="report-empty">
                                No debt created from deliveries on this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Delivery
                                        </th>

                                        <th class="number">
                                            Amount Due
                                        </th>

                                        <th class="number">
                                            Paid
                                        </th>

                                        <th class="number">
                                            Remaining
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $debts
                                        as $debt
                                    ): ?>

                                        <tr>

                                            <td class="strong">

                                                <?= escapeHtml(
                                                    $debt[
                                                        'customer_name'
                                                    ] ?? 'Unknown Customer'
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= escapeHtml(
                                                    deliveryDescription(
                                                        (int) (
                                                            $debt[
                                                                'slim_quantity'
                                                            ] ?? 0
                                                        ),
                                                        (int) (
                                                            $debt[
                                                                'round_quantity'
                                                            ] ?? 0
                                                        )
                                                    )
                                                ) ?>

                                            </td>

                                            <td class="number">

                                                <?= money(
                                                    $debt[
                                                        'amount_due'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                            <td class="number">

                                                <?= money(
                                                    $debt[
                                                        'amount_paid'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                            <td class="number strong">

                                                <?= money(
                                                    $debt[
                                                        'remaining_balance'
                                                    ] ?? 0
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>


                            <div class="report-total-row">

                                <div class="report-total debt-total">

                                    <span class="report-total-label">
                                        Debt Created
                                    </span>

                                    <span class="report-total-value">
                                        <?= money(
                                            $debtCreatedTotal
                                        ) ?>
                                    </span>

                                </div>

                            </div>

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
                                <?= count($expenses) ?>
                                <?= count($expenses) === 1
                                    ? 'record'
                                    : 'records'
                                ?>
                            </span>

                        </div>


                        <?php if (empty($expenses)): ?>

                            <div class="report-empty">
                                No expenses recorded for this day.
                            </div>

                        <?php else: ?>

                            <table class="report-table">

                                <thead>

                                    <tr>

                                        <th>
                                            Category
                                        </th>

                                        <th>
                                            Description
                                        </th>

                                        <th>
                                            Location
                                        </th>

                                        <th class="number">
                                            Amount
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $expenses
                                        as $expense
                                    ): ?>

                                        <tr>

                                            <td class="strong">

                                                <?= escapeHtml(
                                                    $expense[
                                                        'category'
                                                    ] ?? '—'
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= escapeHtml(
                                                    $expense[
                                                        'description'
                                                    ] ?? '—'
                                                ) ?>

                                            </td>

                                            <td>

                                                <?= escapeHtml(
                                                    $expense[
                                                        'expense_location'
                                                    ] ?? '—'
                                                ) ?>

                                            </td>

                                            <td class="number strong">

                                                <?= money(
                                                    $expense[
                                                        'amount'
                                                    ] ?? 0
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
                                        <?= money(
                                            $expenseTotal
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


                    <div class="report-footer-id">

                        Daily Record #<?= (int) $dailyId ?>

                        <br>

                        <?= escapeHtml(
                            formatDate(
                                $daily['business_date']
                            )
                        ) ?>

                    </div>

                </footer>


            </div>

        </div>

    </main>

</div>

</body>

</html>