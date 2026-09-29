<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';

requireAdmin();

$currentPage = 'home';

$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

$monthName = date('F Y');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | Marcid Blue</title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

    <style>

        /* =========================================================
           HOME DASHBOARD
        ========================================================= */

        .dashboard-page {
            padding: 30px;
        }

        .dashboard-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }

        .dashboard-heading h1 {
            margin: 0 0 6px;
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .dashboard-heading p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .dashboard-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-month-select {
            min-width: 180px;
            height: 42px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 14px;
            outline: none;
        }

        .dashboard-month-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 135, 201, .10);
        }

        .dashboard-refresh {
            height: 42px;
            padding: 0 16px;
            border: 0;
            border-radius: 8px;
            background: var(--primary);
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .dashboard-refresh:hover {
            background: var(--primary-dark);
        }

        .dashboard-refresh:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        /* =========================================================
           SUMMARY CARDS
        ========================================================= */

        .dashboard-summary-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .dashboard-stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            min-height: 128px;
            box-shadow: var(--shadow-sm);
        }

        .dashboard-stat-label {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .dashboard-stat-value {
            font-size: 26px;
            line-height: 1.2;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .dashboard-stat-meta {
            font-size: 12px;
            color: var(--text-muted);
        }

        .dashboard-stat-card.revenue
        .dashboard-stat-value {
            color: var(--primary);
        }

        .dashboard-stat-card.net
        .dashboard-stat-value {
            color: var(--secondary);
        }

        .dashboard-stat-card.expenses
        .dashboard-stat-value {
            color: var(--danger);
        }

        .dashboard-stat-card.gallons
        .dashboard-stat-value {
            color: var(--text);
        }

        /* =========================================================
           CHART GRID
        ========================================================= */

        .dashboard-chart-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 2fr)
                minmax(300px, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .dashboard-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .dashboard-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .dashboard-card-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .dashboard-card-subtitle {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--text-muted);
        }

        .dashboard-card-body {
            padding: 20px;
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 310px;
        }

        .chart-wrapper.small {
            height: 310px;
        }

        .chart-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            font-size: 13px;
        }

        /* =========================================================
           LOWER GRID
        ========================================================= */

        .dashboard-two-column {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
        }

        .dashboard-table th {
            padding: 11px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        .dashboard-table td {
            padding: 13px 12px;
            font-size: 13px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
        }

        .dashboard-table tr:last-child td {
            border-bottom: 0;
        }

        .dashboard-table .amount {
            text-align: right;
            font-weight: 700;
        }

        .customer-name {
            font-weight: 600;
        }

        .customer-secondary {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            color: var(--text-muted);
        }

        .balance-amount {
            color: var(--danger);
            font-weight: 700;
        }

        .empty-state {
            padding: 35px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }

        /* =========================================================
           HISTORY
        ========================================================= */

        .dashboard-history {
            margin-bottom: 22px;
        }

        .history-table-wrapper {
            overflow-x: auto;
        }

        .trend-positive {
            color: var(--secondary);
            font-weight: 700;
        }

        .trend-negative {
            color: var(--danger);
            font-weight: 700;
        }

        /* =========================================================
           RECENT CUSTOMERS
        ========================================================= */

        .recent-customers-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .recent-customer {
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--background);
        }

        .recent-customer-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .recent-customer-date {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* =========================================================
           LOADING
        ========================================================= */

        .dashboard-loading {
            position: relative;
        }

        .dashboard-loading::after {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, .55);
            pointer-events: none;
        }

        .dashboard-error {
            display: none;
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #f1c2c0;
            border-radius: 8px;
            background: #fff4f3;
            color: var(--danger);
            font-size: 13px;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1100px) {

            .dashboard-summary-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .recent-customers-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .dashboard-chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 800px) {

            .dashboard-page {
                padding: 20px;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }

            .dashboard-controls {
                width: 100%;
            }

            .dashboard-month-select {
                flex: 1;
            }

            .dashboard-two-column {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {

            .dashboard-summary-grid {
                grid-template-columns: 1fr;
            }

            .recent-customers-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-heading h1 {
                font-size: 24px;
            }

            .dashboard-controls {
                flex-wrap: wrap;
            }

            .dashboard-month-select {
                width: 100%;
            }

            .dashboard-refresh {
                width: 100%;
            }
        }

    </style>
</head>

<body>

<?php require_once '../includes/sidebar.php'; ?>

<div class="main">

    <header class="topbar">

        <div class="topbar-left">
            <h2>Dashboard</h2>
        </div>

    </header>

    <main class="dashboard-page">

        <!-- =====================================================
             HEADER
        ====================================================== -->

        <section class="dashboard-header">

            <div class="dashboard-heading">

                <h1>Business Overview</h1>

                <p id="dashboardSubtitle">
                    Monthly performance and business analytics
                </p>

            </div>

            <div class="dashboard-controls">

                <select
                    id="dashboardMonth"
                    class="dashboard-month-select"
                >
                    <?php
                    for ($i = 0; $i < 24; $i++) {

                        $date = new DateTimeImmutable(
                            'first day of this month'
                        );

                        $date = $date->modify("-{$i} months");

                        $year =
                            (int)$date->format('Y');

                        $month =
                            (int)$date->format('n');

                        $selected =
                            (
                                $year === $currentYear &&
                                $month === $currentMonth
                            )
                                ? 'selected'
                                : '';

                        echo '<option
                                value="' .
                                $year .
                                '-' .
                                str_pad(
                                    (string)$month,
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) .
                                '" ' .
                                $selected .
                                '>' .
                                htmlspecialchars(
                                    $date->format('F Y')
                                ) .
                                '</option>';
                    }
                    ?>
                </select>

                <button
                    type="button"
                    id="dashboardRefresh"
                    class="dashboard-refresh"
                >
                    Refresh
                </button>

            </div>

        </section>

        <div
            id="dashboardError"
            class="dashboard-error"
        ></div>

        <!-- =====================================================
             SUMMARY
        ====================================================== -->

        <section class="dashboard-summary-grid">

            <article class="dashboard-stat-card revenue">

                <div class="dashboard-stat-label">
                    Total Revenue
                </div>

                <div
                    class="dashboard-stat-value"
                    id="totalRevenue"
                >
                    ₱0.00
                </div>

                <div class="dashboard-stat-meta">
                    Walk-in + delivery revenue
                </div>

            </article>

            <article class="dashboard-stat-card net">

                <div class="dashboard-stat-label">
                    Net Revenue
                </div>

                <div
                    class="dashboard-stat-value"
                    id="netRevenue"
                >
                    ₱0.00
                </div>

                <div class="dashboard-stat-meta">
                    Revenue after expenses
                </div>

            </article>

            <article class="dashboard-stat-card expenses">

                <div class="dashboard-stat-label">
                    Total Expenses
                </div>

                <div
                    class="dashboard-stat-value"
                    id="totalExpenses"
                >
                    ₱0.00
                </div>

                <div class="dashboard-stat-meta">
                    Recorded business expenses
                </div>

            </article>

            <article class="dashboard-stat-card gallons">

                <div class="dashboard-stat-label">
                    Gallons Sold
                </div>

                <div
                    class="dashboard-stat-value"
                    id="totalGallons"
                >
                    0
                </div>

                <div
                    class="dashboard-stat-meta"
                    id="gallonsBreakdown"
                >
                    Slim 0 · Round 0
                </div>

            </article>

        </section>

        <!-- =====================================================
             CHARTS
        ====================================================== -->

        <section class="dashboard-chart-grid">

            <article class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>
                        <h3 class="dashboard-card-title">
                            Revenue vs Expenses
                        </h3>

                        <p class="dashboard-card-subtitle">
                            Daily performance for the selected month
                        </p>
                    </div>

                </div>

                <div class="dashboard-card-body">

                    <div class="chart-wrapper">

                        <canvas
                            id="revenueChart"
                        ></canvas>

                    </div>

                </div>

            </article>

            <article class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>
                        <h3 class="dashboard-card-title">
                            Gallons Sold
                        </h3>

                        <p class="dashboard-card-subtitle">
                            Slim vs Round
                        </p>
                    </div>

                </div>

                <div class="dashboard-card-body">

                    <div class="chart-wrapper small">

                        <canvas
                            id="gallonsChart"
                        ></canvas>

                    </div>

                </div>

            </article>

        </section>

        <!-- =====================================================
             TOP CUSTOMERS / BALANCES
        ====================================================== -->

        <section class="dashboard-two-column">

            <article class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>
                        <h3 class="dashboard-card-title">
                            Top Customers
                        </h3>

                        <p class="dashboard-card-subtitle">
                            Highest delivery value for this month
                        </p>
                    </div>

                </div>

                <div class="dashboard-card-body">

                    <div id="topCustomersContainer">
                        <div class="empty-state">
                            Loading...
                        </div>
                    </div>

                </div>

            </article>

            <article class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>
                        <h3 class="dashboard-card-title">
                            Outstanding Balances
                        </h3>

                        <p class="dashboard-card-subtitle">
                            Customers with unpaid delivery balances
                        </p>
                    </div>

                </div>

                <div class="dashboard-card-body">

                    <div id="outstandingContainer">
                        <div class="empty-state">
                            Loading...
                        </div>
                    </div>

                </div>

            </article>

        </section>

        <!-- =====================================================
             MONTHLY HISTORY
        ====================================================== -->

        <section class="dashboard-card dashboard-history">

            <div class="dashboard-card-header">

                <div>
                    <h3 class="dashboard-card-title">
                        Historical Monthly Trend
                    </h3>

                    <p class="dashboard-card-subtitle">
                        Saved monthly analytics for comparison
                    </p>
                </div>

            </div>

            <div class="dashboard-card-body">

                <div
                    class="history-table-wrapper"
                    id="historyContainer"
                >
                    <div class="empty-state">
                        Loading...
                    </div>
                </div>

            </div>

        </section>

        <!-- =====================================================
             RECENT CUSTOMERS
        ====================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>
                    <h3 class="dashboard-card-title">
                        Recent Customers
                    </h3>

                    <p class="dashboard-card-subtitle">
                        Most recently added customers
                    </p>
                </div>

                <a
                    href="customers.php"
                    class="btn btn-secondary"
                >
                    View Customers
                </a>

            </div>

            <div class="dashboard-card-body">

                <div
                    id="recentCustomersContainer"
                    class="recent-customers-grid"
                >
                    <div class="empty-state">
                        Loading...
                    </div>
                </div>

            </div>

        </section>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ANALYTICS_URL =
    'backend/home-analytics-backend.php';

let revenueChart = null;
let gallonsChart = null;


/* =========================================================
   HELPERS
========================================================= */

function formatMoney(value) {

    return '₱' +
        Number(value || 0).toLocaleString(
            'en-PH',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
}


function formatNumber(value) {

    return Number(value || 0).toLocaleString(
        'en-PH',
        {
            maximumFractionDigits: 2
        }
    );
}


function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value ?? '';

    return div.innerHTML;
}


function getSelectedMonth() {

    const value =
        document.getElementById(
            'dashboardMonth'
        ).value;

    const parts =
        value.split('-');

    return {
        year: Number(parts[0]),
        month: Number(parts[1])
    };
}


async function requestAnalytics(
    action,
    extra = {}
) {

    const data =
        new URLSearchParams();

    data.append(
        'action',
        action
    );

    Object.entries(extra).forEach(
        ([key, value]) => {

            data.append(
                key,
                value
            );
        }
    );

    const response =
        await fetch(
            ANALYTICS_URL,
            {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },
                body: data
            }
        );

    const json =
        await response.json();

    if (!json.success) {

        throw new Error(
            json.message ||
            'Unable to load analytics.'
        );
    }

    return json;
}


/* =========================================================
   SUMMARY
========================================================= */

function renderSummary(analytics) {

    document.getElementById(
        'totalRevenue'
    ).textContent =
        formatMoney(
            analytics.total_revenue
        );

    document.getElementById(
        'netRevenue'
    ).textContent =
        formatMoney(
            analytics.net_revenue
        );

    document.getElementById(
        'totalExpenses'
    ).textContent =
        formatMoney(
            analytics.total_expenses
        );

    document.getElementById(
        'totalGallons'
    ).textContent =
        formatNumber(
            analytics.total_gallons
        );

    document.getElementById(
        'gallonsBreakdown'
    ).textContent =
        'Slim ' +
        formatNumber(
            analytics.slim_gallons
        ) +
        ' · Round ' +
        formatNumber(
            analytics.round_gallons
        );
}


/* =========================================================
   REVENUE CHART
========================================================= */

function renderRevenueChart(days) {

    const labels =
        days.map(
            day => day.day
        );

    const revenue =
        days.map(
            day => day.revenue
        );

    const expenses =
        days.map(
            day => day.expenses
        );

    if (revenueChart) {
        revenueChart.destroy();
    }

    const canvas =
        document.getElementById(
            'revenueChart'
        );

    revenueChart =
        new Chart(
            canvas,
            {
                type: 'line',

                data: {
                    labels,

                    datasets: [

                        {
                            label: 'Revenue',

                            data: revenue,

                            tension: .35,

                            borderWidth: 2,

                            fill: false,

                            pointRadius: 2,

                            pointHoverRadius: 5
                        },

                        {
                            label: 'Expenses',

                            data: expenses,

                            tension: .35,

                            borderWidth: 2,

                            fill: false,

                            pointRadius: 2,

                            pointHoverRadius: 5
                        }

                    ]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    interaction: {
                        mode: 'index',
                        intersect: false
                    },

                    plugins: {

                        legend: {
                            position: 'top',

                            labels: {
                                boxWidth: 10,
                                usePointStyle: true
                            }
                        },

                        tooltip: {

                            callbacks: {

                                label(context) {

                                    return (
                                        context.dataset.label +
                                        ': ' +
                                        formatMoney(
                                            context.raw
                                        )
                                    );
                                }
                            }
                        }
                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            ticks: {

                                callback(value) {

                                    return '₱' +
                                        Number(value)
                                            .toLocaleString(
                                                'en-PH'
                                            );
                                }
                            }
                        },

                        x: {

                            grid: {
                                display: false
                            }
                        }
                    }
                }
            }
        );
}


/* =========================================================
   GALLONS CHART
========================================================= */

function renderGallonsChart(analytics) {

    if (gallonsChart) {
        gallonsChart.destroy();
    }

    const canvas =
        document.getElementById(
            'gallonsChart'
        );

    gallonsChart =
        new Chart(
            canvas,
            {
                type: 'doughnut',

                data: {

                    labels: [
                        'Slim',
                        'Round'
                    ],

                    datasets: [
                        {
                            data: [
                                Number(
                                    analytics.slim_gallons
                                ),
                                Number(
                                    analytics.round_gallons
                                )
                            ],

                            borderWidth: 0
                        }
                    ]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    cutout: '68%',

                    plugins: {

                        legend: {
                            position: 'bottom',

                            labels: {
                                boxWidth: 10,
                                usePointStyle: true,
                                padding: 18
                            }
                        },

                        tooltip: {

                            callbacks: {

                                label(context) {

                                    return (
                                        context.label +
                                        ': ' +
                                        formatNumber(
                                            context.raw
                                        ) +
                                        ' gal'
                                    );
                                }
                            }
                        }
                    }
                }
            }
        );
}


/* =========================================================
   TOP CUSTOMERS
========================================================= */

function renderTopCustomers(customers) {

    const container =
        document.getElementById(
            'topCustomersContainer'
        );

    if (!customers.length) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No delivery customers for this month.' +
            '</div>';

        return;
    }

    let html =
        '<div class="table-wrapper">' +
        '<table class="dashboard-table">' +
        '<thead>' +
        '<tr>' +
        '<th>Customer</th>' +
        '<th>Deliveries</th>' +
        '<th class="amount">Revenue</th>' +
        '</tr>' +
        '</thead>' +
        '<tbody>';

    customers.forEach(
        customer => {

            html +=
                '<tr>' +

                '<td>' +
                '<span class="customer-name">' +
                escapeHtml(
                    customer.customer_name
                ) +
                '</span>' +
                '<span class="customer-secondary">' +
                formatNumber(
                    customer.total_gallons
                ) +
                ' gallons' +
                '</span>' +
                '</td>' +

                '<td>' +
                formatNumber(
                    customer.deliveries
                ) +
                '</td>' +

                '<td class="amount">' +
                formatMoney(
                    customer.revenue
                ) +
                '</td>' +

                '</tr>';
        }
    );

    html +=
        '</tbody>' +
        '</table>' +
        '</div>';

    container.innerHTML =
        html;
}


/* =========================================================
   OUTSTANDING BALANCES
========================================================= */

function renderOutstandingBalances(
    balances
) {

    const container =
        document.getElementById(
            'outstandingContainer'
        );

    if (!balances.length) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No outstanding customer balances.' +
            '</div>';

        return;
    }

    let html =
        '<table class="dashboard-table">' +
        '<thead>' +
        '<tr>' +
        '<th>Customer</th>' +
        '<th class="amount">Balance</th>' +
        '</tr>' +
        '</thead>' +
        '<tbody>';

    balances.forEach(
        customer => {

            html +=
                '<tr>' +

                '<td>' +
                '<span class="customer-name">' +
                escapeHtml(
                    customer.customer_name
                ) +
                '</span>' +
                '</td>' +

                '<td class="amount">' +
                '<span class="balance-amount">' +
                formatMoney(
                    customer.balance
                ) +
                '</span>' +
                '</td>' +

                '</tr>';
        }
    );

    html +=
        '</tbody>' +
        '</table>';

    container.innerHTML =
        html;
}


/* =========================================================
   HISTORY
========================================================= */

function renderHistory(rows) {

    const container =
        document.getElementById(
            'historyContainer'
        );

    if (!rows.length) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No monthly analytics have been saved yet.' +
            '</div>';

        return;
    }

    let html =
        '<table class="dashboard-table">' +
        '<thead>' +
        '<tr>' +
        '<th>Month</th>' +
        '<th class="amount">Revenue</th>' +
        '<th class="amount">Expenses</th>' +
        '<th class="amount">Net</th>' +
        '<th class="amount">Gallons</th>' +
        '</tr>' +
        '</thead>' +
        '<tbody>';

    rows.forEach(
        row => {

            const monthDate =
                new Date(
                    Number(
                        row.analytics_year
                    ),
                    Number(
                        row.analytics_month
                    ) - 1,
                    1
                );

            const monthLabel =
                monthDate.toLocaleDateString(
                    'en-US',
                    {
                        month: 'long',
                        year: 'numeric'
                    }
                );

            const net =
                Number(
                    row.net_revenue || 0
                );

            const netClass =
                net >= 0
                    ? 'trend-positive'
                    : 'trend-negative';

            html +=
                '<tr>' +

                '<td>' +
                '<span class="customer-name">' +
                monthLabel +
                '</span>' +
                '</td>' +

                '<td class="amount">' +
                formatMoney(
                    row.total_revenue
                ) +
                '</td>' +

                '<td class="amount">' +
                formatMoney(
                    row.total_expenses
                ) +
                '</td>' +

                '<td class="amount ' +
                netClass +
                '">' +
                formatMoney(
                    net
                ) +
                '</td>' +

                '<td class="amount">' +
                formatNumber(
                    row.total_gallons
                ) +
                '</td>' +

                '</tr>';
        }
    );

    html +=
        '</tbody>' +
        '</table>';

    container.innerHTML =
        html;
}


/* =========================================================
   RECENT CUSTOMERS
========================================================= */

function renderRecentCustomers(
    customers
) {

    const container =
        document.getElementById(
            'recentCustomersContainer'
        );

    if (!customers.length) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No customers found.' +
            '</div>';

        return;
    }

    container.innerHTML =
        customers.map(
            customer => {

                let dateText =
                    'Customer record';

                if (customer.created_at) {

                    const date =
                        new Date(
                            customer.created_at
                                .replace(' ', 'T')
                        );

                    if (
                        !Number.isNaN(
                            date.getTime()
                        )
                    ) {

                        dateText =
                            'Added ' +
                            date.toLocaleDateString(
                                'en-PH',
                                {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric'
                                }
                            );
                    }
                }

                return (
                    '<div class="recent-customer">' +

                    '<div class="recent-customer-name">' +
                    escapeHtml(
                        customer.customer_name
                    ) +
                    '</div>' +

                    '<div class="recent-customer-date">' +
                    dateText +
                    '</div>' +

                    '</div>'
                );
            }
        )
        .join('');
}


/* =========================================================
   LOAD DASHBOARD
========================================================= */

async function loadDashboard() {

    const month =
        getSelectedMonth();

    const refreshButton =
        document.getElementById(
            'dashboardRefresh'
        );

    const errorBox =
        document.getElementById(
            'dashboardError'
        );

    refreshButton.disabled =
        true;

    refreshButton.textContent =
        'Loading...';

    errorBox.style.display =
        'none';

    try {

        const dashboard =
            await requestAnalytics(
                'dashboard',
                {
                    year: month.year,
                    month: month.month
                }
            );

        const daily =
            await requestAnalytics(
                'daily_chart',
                {
                    year: month.year,
                    month: month.month
                }
            );

        const history =
            await requestAnalytics(
                'history',
                {
                    limit: 24
                }
            );

        renderSummary(
            dashboard.analytics
        );

        renderRevenueChart(
            daily.days
        );

        renderGallonsChart(
            dashboard.analytics
        );

        renderTopCustomers(
            dashboard.top_customers
        );

        renderOutstandingBalances(
            dashboard.outstanding_balances
        );

        renderHistory(
            history.analytics
        );

        renderRecentCustomers(
            dashboard.recent_customers
        );

        const selectedDate =
            new Date(
                month.year,
                month.month - 1,
                1
            );

        document.getElementById(
            'dashboardSubtitle'
        ).textContent =
            selectedDate.toLocaleDateString(
                'en-US',
                {
                    month: 'long',
                    year: 'numeric'
                }
            ) +
            ' business performance and analytics';

    } catch (error) {

        console.error(error);

        errorBox.textContent =
            error.message ||
            'Unable to load dashboard analytics.';

        errorBox.style.display =
            'block';

    } finally {

        refreshButton.disabled =
            false;

        refreshButton.textContent =
            'Refresh';
    }
}


/* =========================================================
   EVENTS
========================================================= */

document.getElementById(
    'dashboardMonth'
).addEventListener(
    'change',
    loadDashboard
);

document.getElementById(
    'dashboardRefresh'
).addEventListener(
    'click',
    loadDashboard
);


/* =========================================================
   INITIAL LOAD
========================================================= */

loadDashboard();

</script>

</body>
</html>