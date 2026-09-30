<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';

requireAdmin();

$currentPage = 'home';

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
           DASHBOARD
        ========================================================= */

        .dashboard-page {
            padding: 30px;
        }

        .dashboard-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 24px;
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
            flex-wrap: wrap;
        }

        .dashboard-period-select {
            min-width: 310px;
            height: 42px;
            padding: 0 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 14px;
            outline: none;
        }

        .dashboard-period-select:focus,
        .period-date-input:focus,
        .expense-input:focus,
        .expense-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(22, 135, 201, .10);
        }

        .dashboard-button {
            height: 42px;
            padding: 0 16px;
            border: 0;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .dashboard-button.primary {
            background: var(--primary);
            color: #ffffff;
        }

        .dashboard-button.primary:hover {
            background: var(--primary-dark);
        }

        .dashboard-button.secondary {
            background: var(--background);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .dashboard-button.secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .dashboard-button.danger {
            color: var(--danger);
            background: transparent;
            border: 1px solid var(--border);
        }

        .dashboard-button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        /* =========================================================
           ERROR
        ========================================================= */

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
           SUMMARY CARDS
        ========================================================= */

        .dashboard-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
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

        .dashboard-stat-card.revenue .dashboard-stat-value {
            color: var(--primary);
        }

        .dashboard-stat-card.net .dashboard-stat-value {
            color: var(--secondary);
        }

        .dashboard-stat-card.expenses .dashboard-stat-value {
            color: var(--danger);
        }

        /* =========================================================
           MAIN ANALYTICS
        ========================================================= */

        .dashboard-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
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
            height: 340px;
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
           HISTORICAL DAILY CLOSING
        ========================================================= */

        .historical-closing-section {
            margin-bottom: 22px;
        }

        .historical-closing-summary {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .historical-summary-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .historical-summary-label {
            font-size: 11px;
            color: var(--text-muted);
        }

        .historical-summary-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .historical-summary-value.revenue {
            color: var(--primary);
        }

        .historical-summary-value.expenses {
            color: var(--danger);
        }

        .historical-summary-value.profit {
            color: var(--secondary);
        }

        .historical-table-wrapper {
            overflow-x: auto;
        }

        .historical-table {
            width: 100%;
            border-collapse: collapse;
        }

        .historical-table th {
            padding: 11px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .historical-table td {
            padding: 12px;
            font-size: 13px;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .historical-table tr:last-child td {
            border-bottom: 0;
        }

        .historical-table .amount {
            text-align: right;
            font-weight: 700;
        }

        .historical-date {
            font-weight: 600;
        }

        .historical-profit {
            color: var(--secondary);
        }

        .historical-loss {
            color: var(--danger);
        }

        .closing-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .closing-status.recorded {
            background: rgba(25, 167, 160, .10);
            color: var(--secondary);
        }

        .closing-status.no-record {
            background: rgba(108, 117, 125, .10);
            color: var(--text-muted);
        }

        /* =========================================================
           PERIOD EDITOR
        ========================================================= */

        .period-editor {
            display: none;
            margin-bottom: 22px;
        }

        .period-editor.open {
            display: block;
        }

        .period-editor-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 1fr)
                auto;
            align-items: start;
            gap: 14px;
        }

        .period-editor-action {
            display: flex;
            align-items: flex-start;
            padding-top: 29px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text);
        }

        .form-help {
            font-size: 11px;
            color: var(--text-muted);
        }

        .period-date-input,
        .expense-input,
        .expense-select {
            width: 100%;
            box-sizing: border-box;
            height: 42px;
            padding: 0 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
            outline: none;
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

        /* =========================================================
           TABLES
        ========================================================= */

        .table-wrapper {
            overflow-x: auto;
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
            white-space: nowrap;
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
           MANUAL EXPENSES
        ========================================================= */

        .manual-expenses-section {
            margin-bottom: 22px;
        }

        .manual-expense-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .manual-expense-form {
            display: none;
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background: var(--background);
        }

        .manual-expense-form.open {
            display: block;
        }

        .expense-form-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .expense-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 16px;
        }

        .expense-period-note {
            padding: 10px 12px;
            border-radius: 7px;
            background: rgba(22, 135, 201, .07);
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .expense-action {
            padding: 6px 9px;
            border: 0;
            background: transparent;
            color: var(--danger);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .expense-action:hover {
            text-decoration: underline;
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
            opacity: .65;
            pointer-events: none;
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

            .expense-form-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
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

            .dashboard-period-select {
                flex: 1;
                min-width: 0;
            }

            .dashboard-two-column {
                grid-template-columns: 1fr;
            }

            .period-editor-grid {
                grid-template-columns: 1fr;
            }

            .period-editor-action {
                padding-top: 0;
            }

            .historical-closing-summary {
                gap: 16px;
            }

        }

        @media (max-width: 560px) {

            .dashboard-summary-grid {
                grid-template-columns: 1fr;
            }

            .recent-customers-grid {
                grid-template-columns: 1fr;
            }

            .expense-form-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-heading h1 {
                font-size: 24px;
            }

            .dashboard-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .dashboard-period-select,
            .dashboard-button {
                width: 100%;
            }

            .manual-expense-toolbar {
                align-items: stretch;
                flex-direction: column;
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

        <div class="topbar-user">

            👤

            <?= htmlspecialchars(
                $_SESSION['full_name'] ?? 'Marcid Blue Admin'
            ) ?>

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
                    Accounting period performance and analytics
                </p>

            </div>

            <div class="dashboard-controls">

                <select
                    id="dashboardPeriod"
                    class="dashboard-period-select"
                >
                    <option value="">
                        Loading accounting periods...
                    </option>
                </select>

                <button
                    type="button"
                    id="editPeriodButton"
                    class="dashboard-button secondary"
                >
                    Edit Period
                </button>

                <button
                    type="button"
                    id="dashboardRefresh"
                    class="dashboard-button primary"
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
             PERIOD EDITOR
        ====================================================== -->

        <section
            id="periodEditor"
            class="dashboard-card period-editor"
        >

            <div class="dashboard-card-header">

                <div>

                    <h3 class="dashboard-card-title">
                        Accounting Period
                    </h3>

                    <p class="dashboard-card-subtitle">
                        Set the dates used to measure this accounting period.
                    </p>

                </div>

            </div>

            <div class="dashboard-card-body">

                <div class="period-editor-grid">

                    <div class="form-field">

                        <label
                            class="form-label"
                            for="periodStart"
                        >
                            Start Date
                        </label>

                        <input
                            type="date"
                            id="periodStart"
                            class="period-date-input"
                        >

                        <span class="form-help">
                            First date included in the period.
                        </span>

                    </div>

                    <div class="form-field">

                        <label
                            class="form-label"
                            for="periodEnd"
                        >
                            End Date
                        </label>

                        <input
                            type="date"
                            id="periodEnd"
                            class="period-date-input"
                        >

                        <span class="form-help">
                            Last date included in the period.
                        </span>

                    </div>

                    <div class="period-editor-action">

                        <button
                            type="button"
                            id="savePeriodButton"
                            class="dashboard-button primary"
                        >
                            Save Period
                        </button>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SUMMARY CARDS
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
                    Revenue after total expenses
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
                    Daily Closing + manual expenses
                </div>

            </article>


            <article class="dashboard-stat-card">

                <div class="dashboard-stat-label">
                    Manual Analytics Expenses
                </div>

                <div
                    class="dashboard-stat-value"
                    id="manualExpenses"
                >
                    ₱0.00
                </div>

                <div class="dashboard-stat-meta">
                    Water, electricity, rent and other bills
                </div>

            </article>

        </section>


        <!-- =====================================================
             REVENUE VS EXPENSES
        ====================================================== -->

        <section class="dashboard-chart-grid">

            <article class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>

                        <h3 class="dashboard-card-title">
                            Revenue vs Expenses
                        </h3>

                        <p
                            class="dashboard-card-subtitle"
                            id="revenueChartSubtitle"
                        >
                            Daily financial performance for the selected accounting period
                        </p>

                    </div>

                </div>

                <div class="dashboard-card-body">

                    <div class="chart-wrapper">

                        <canvas id="revenueChart"></canvas>

                    </div>

                </div>

            </article>

        </section>


        <!-- =====================================================
             HISTORICAL DAILY CLOSING
        ====================================================== -->

        <section class="dashboard-card historical-closing-section">

            <div class="dashboard-card-header">

                <div>

                    <h3 class="dashboard-card-title">
                        Historical Daily Closing
                    </h3>

                    <p
                        class="dashboard-card-subtitle"
                        id="historicalClosingSubtitle"
                    >
                        Day-to-day Daily Closing records for the selected accounting period
                    </p>

                </div>

                <div
                    id="historicalClosingSummary"
                    class="historical-closing-summary"
                ></div>

            </div>

            <div class="dashboard-card-body">

                <div
                    id="historicalClosingContainer"
                    class="historical-table-wrapper"
                >

                    <div class="empty-state">
                        Loading...
                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             MANUAL ANALYTICS EXPENSES
        ====================================================== -->

        <section class="dashboard-card manual-expenses-section">

            <div class="dashboard-card-header">

                <div>

                    <h3 class="dashboard-card-title">
                        Manual Analytics Expenses
                    </h3>

                    <p class="dashboard-card-subtitle">
                        Expenses outside Daily Closing, such as water, electricity, rent and other bills.
                    </p>

                </div>

                <div class="manual-expense-toolbar">

                    <button
                        type="button"
                        id="addExpenseButton"
                        class="dashboard-button primary"
                    >
                        + Add Expense
                    </button>

                </div>

            </div>


            <!-- FORM -->

            <form
                id="manualExpenseForm"
                class="manual-expense-form"
            >

                <div class="expense-form-grid">

                    <div class="form-field">

                        <label
                            class="form-label"
                            for="expenseDate"
                        >
                            Expense Date
                        </label>

                        <input
                            type="date"
                            id="expenseDate"
                            name="expense_date"
                            class="expense-input"
                            required
                        >

                        <span class="form-help">
                            The exact date this expense belongs to.
                        </span>

                    </div>


                    <div class="form-field">

                        <label
                            class="form-label"
                            for="expenseCategory"
                        >
                            Category
                        </label>

                        <select
                            id="expenseCategory"
                            name="category"
                            class="expense-select"
                            required
                        >

                            <option value="">
                                Select category
                            </option>

                            <option value="Water Bill">
                                Water Bill
                            </option>

                            <option value="Electricity Bill">
                                Electricity Bill
                            </option>

                            <option value="Rent">
                                Rent
                            </option>

                            <option value="Permits">
                                Permits
                            </option>

                            <option value="Maintenance">
                                Maintenance
                            </option>

                            <option value="Other">
                                Other
                            </option>

                        </select>

                    </div>


                    <div class="form-field">

                        <label
                            class="form-label"
                            for="expenseAmount"
                        >
                            Amount
                        </label>

                        <input
                            type="number"
                            id="expenseAmount"
                            name="amount"
                            class="expense-input"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            required
                        >

                    </div>


                    <div
                        class="form-field"
                        style="grid-column: 1 / -1;"
                    >

                        <label
                            class="form-label"
                            for="expenseDescription"
                        >
                            Description
                        </label>

                        <input
                            type="text"
                            id="expenseDescription"
                            name="description"
                            class="expense-input"
                            maxlength="255"
                            placeholder="e.g. August water bill"
                            required
                        >

                    </div>


                    <div
                        class="form-field"
                        style="grid-column: 1 / -1;"
                    >

                        <label
                            class="form-label"
                            for="expensePeriod1"
                        >
                            Accounting Period
                        </label>

                        <select
                            id="expensePeriod1"
                            name="period_id"
                            class="expense-select"
                            required
                        >

                            <option value="">
                                Select period
                            </option>

                        </select>

                        <span class="form-help">
                            The expense will be recorded on the exact expense date within this accounting period.
                        </span>

                    </div>


                    <div class="expense-form-full">

                        <div class="expense-period-note">
                            Manual expenses are recorded on their exact expense date.
                            They are included in the selected accounting period without shifting the date.
                        </div>

                    </div>

                </div>


                <div class="expense-form-actions">

                    <button
                        type="button"
                        id="cancelExpenseButton"
                        class="dashboard-button secondary"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        id="saveExpenseButton"
                        class="dashboard-button primary"
                    >
                        Save Expense
                    </button>

                </div>

            </form>


            <!-- TABLE -->

            <div class="dashboard-card-body">

                <div id="manualExpensesContainer">

                    <div class="empty-state">
                        Loading...
                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             TOP CUSTOMERS / OUTSTANDING BALANCES
        ====================================================== -->

        <section class="dashboard-two-column">

            <article class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>

                        <h3 class="dashboard-card-title">
                            Top Customers
                        </h3>

                        <p class="dashboard-card-subtitle">
                            Highest delivery value for this accounting period
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
             RECENT CUSTOMERS
        ====================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h3 class="dashboard-card-title">
                        Recent Customers
                    </h3>

                    <p class="dashboard-card-subtitle">
                        Recently added customer records
                    </p>

                </div>

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

/* =========================================================
   CONFIG
========================================================= */

const ANALYTICS_URL =
    'backend/home-analytics-backend.php';

let revenueChart = null;

let accountingPeriods = [];
let selectedPeriodId = null;


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


function formatDate(dateString) {

    if (!dateString) {
        return '';
    }

    const parts =
        String(dateString).split('-');

    if (parts.length !== 3) {
        return String(dateString);
    }

    const date =
        new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        );

    if (Number.isNaN(date.getTime())) {
        return String(dateString);
    }

    return date.toLocaleDateString(
        'en-PH',
        {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }
    );
}


function getSelectedPeriod() {

    return accountingPeriods.find(
        period =>
            Number(period.period_id) ===
            Number(selectedPeriodId)
    ) || null;
}


function showError(message) {

    const box =
        document.getElementById(
            'dashboardError'
        );

    box.textContent =
        message || 'Something went wrong.';

    box.style.display =
        'block';
}


function hideError() {

    document.getElementById(
        'dashboardError'
    ).style.display =
        'none';
}


/* =========================================================
   PERIOD HELPERS
========================================================= */

function getPeriodYear(period) {

    if (
        period.analytics_year !== undefined &&
        period.analytics_year !== null &&
        period.analytics_year !== ''
    ) {
        return Number(
            period.analytics_year
        );
    }

    if (period.period_end) {

        return Number(
            String(
                period.period_end
            ).substring(0, 4)
        );
    }

    if (period.period_start) {

        return Number(
            String(
                period.period_start
            ).substring(0, 4)
        );
    }

    return null;
}


function getPeriodMonth(period) {

    if (
        period.analytics_month !== undefined &&
        period.analytics_month !== null &&
        period.analytics_month !== ''
    ) {
        return Number(
            period.analytics_month
        );
    }

    if (period.period_end) {

        return Number(
            String(
                period.period_end
            ).substring(5, 7)
        );
    }

    if (period.period_start) {

        return Number(
            String(
                period.period_start
            ).substring(5, 7)
        );
    }

    return null;
}


function buildPeriodLabel(period) {

    if (!period) {
        return 'Accounting Period';
    }

    const start =
        formatDate(
            period.period_start ||
            period.start
        );

    const end =
        formatDate(
            period.period_end ||
            period.end
        );

    if (start && end) {

        return `${start} – ${end}`;

    }

    return 'Accounting Period';
}


function setPeriodEditor(period) {

    if (!period) {
        return;
    }

    document.getElementById(
        'periodStart'
    ).value =
        period.period_start || '';


    document.getElementById(
        'periodEnd'
    ).value =
        period.period_end || '';
}


function updatePeriodSubtitle(period) {

    if (!period) {
        return;
    }

    const range =
        formatDate(
            period.period_start ||
            period.start
        ) +
        ' – ' +
        formatDate(
            period.period_end ||
            period.end
        );


    document.getElementById(
        'dashboardSubtitle'
    ).textContent =
        range;


    document.getElementById(
        'revenueChartSubtitle'
    ).textContent =
        `Daily financial performance from ${range}`;


    document.getElementById(
        'historicalClosingSubtitle'
    ).textContent =
        `Day-to-day Daily Closing records from ${range}`;
}


/* =========================================================
   API
========================================================= */

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

            if (
                value !== null &&
                value !== undefined
            ) {

                data.append(
                    key,
                    String(value)
                );

            }

        }
    );


    let response;


    try {

        response =
            await fetch(
                ANALYTICS_URL,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body:
                        data.toString()
                }
            );

    } catch (error) {

        throw new Error(
            'Unable to connect to the analytics backend.'
        );

    }


    const text =
        await response.text();


    let json;


    try {

        json =
            JSON.parse(text);

    } catch (error) {

        console.error(
            'Analytics backend response:',
            text
        );

        throw new Error(
            'The analytics backend returned an invalid response.'
        );

    }


    if (
        !response.ok ||
        !json.success
    ) {

        throw new Error(
            json.message ||
            'Unable to load analytics.'
        );

    }


    return json;
}


/* =========================================================
   ACCOUNTING PERIODS
========================================================= */

async function loadPeriods() {

    const response =
        await requestAnalytics(
            'periods'
        );


    accountingPeriods =
        Array.isArray(
            response.periods
        )
            ? response.periods
            : [];


    /*
     * Only show September 2026 onward.
     */

    accountingPeriods =
        accountingPeriods.filter(
            period => {

                const year =
                    getPeriodYear(
                        period
                    );

                const month =
                    getPeriodMonth(
                        period
                    );


                if (
                    !year ||
                    !month
                ) {

                    return false;

                }


                return (
                    year > 2026 ||
                    (
                        year === 2026 &&
                        month >= 9
                    )
                );

            }
        );


    const select =
        document.getElementById(
            'dashboardPeriod'
        );


    const expensePeriod1 =
        document.getElementById(
            'expensePeriod1'
        );


    select.innerHTML =
        '';


    expensePeriod1.innerHTML =
        '<option value="">Select period</option>';


    accountingPeriods.forEach(
        period => {

            const label =
                buildPeriodLabel(
                    period
                );


            const dashboardOption =
                document.createElement(
                    'option'
                );


            dashboardOption.value =
                period.period_id;


            dashboardOption.textContent =
                label;


            select.appendChild(
                dashboardOption
            );


            const expenseOption =
                document.createElement(
                    'option'
                );


            expenseOption.value =
                period.period_id;


            expenseOption.textContent =
                label;


            expensePeriod1.appendChild(
                expenseOption
            );

        }
    );


    if (!accountingPeriods.length) {

        select.innerHTML =
            '<option value="">No accounting periods available</option>';


        document.getElementById(
            'dashboardSubtitle'
        ).textContent =
            'No accounting periods available.';


        return;

    }


    /*
     * Prefer September 2026.
     */

    let currentPeriod =
        accountingPeriods.find(
            period =>
                Number(
                    getPeriodYear(
                        period
                    )
                ) === 2026 &&
                Number(
                    getPeriodMonth(
                        period
                    )
                ) === 9
        );


    if (!currentPeriod) {

        currentPeriod =
            accountingPeriods.find(
                period =>
                    String(
                        period.period_end
                    ) === '2026-09-15'
            );

    }


    const period =
        currentPeriod ||
        accountingPeriods[0];


    selectedPeriodId =
        Number(
            period.period_id
        );


    select.value =
        selectedPeriodId;


    expensePeriod1.value =
        selectedPeriodId;


    setPeriodEditor(
        period
    );


    updatePeriodSubtitle(
        period
    );
}


/* =========================================================
   SUMMARY
========================================================= */

function renderSummary(
    analytics
) {

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
            analytics.net_profit ??
            analytics.net_revenue
        );


    document.getElementById(
        'totalExpenses'
    ).textContent =
        formatMoney(
            analytics.total_expenses
        );


    document.getElementById(
        'manualExpenses'
    ).textContent =
        formatMoney(
            analytics.manual_expenses
        );
}


/* =========================================================
   REVENUE VS EXPENSES
========================================================= */

function renderRevenueChart(
    days
) {

    if (revenueChart) {

        revenueChart.destroy();

        revenueChart = null;

    }


    const canvas =
        document.getElementById(
            'revenueChart'
        );


    if (
        !days ||
        !days.length
    ) {

        canvas.style.display =
            'none';

        return;

    }


    canvas.style.display =
        'block';


    const labels =
        days.map(
            day =>
                day.label ||
                formatDate(
                    day.date
                )
        );


    const revenue =
        days.map(
            day =>
                Number(
                    day.revenue || 0
                )
        );


    const expenses =
        days.map(
            day =>
                Number(
                    day.expenses ||
                    day.total_expenses ||
                    0
                )
        );


    const netProfit =
        days.map(
            day =>
                Number(
                    day.net_profit ??
                    day.net_revenue ??
                    (
                        Number(
                            day.revenue || 0
                        ) -
                        Number(
                            day.expenses ||
                            day.total_expenses ||
                            0
                        )
                    )
                )
        );


    revenueChart =
        new Chart(
            canvas,
            {
                data: {

                    labels,

                    datasets: [

                        {
                            type: 'bar',

                            label: 'Revenue',

                            data: revenue,

                            borderWidth: 0,

                            borderRadius: 4
                        },

                        {
                            type: 'bar',

                            label: 'Expenses',

                            data: expenses,

                            borderWidth: 0,

                            borderRadius: 4
                        },

                        {
                            type: 'line',

                            label: 'Net Profit',

                            data: netProfit,

                            tension: .3,

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
                                        Number(
                                            value
                                        ).toLocaleString(
                                            'en-PH'
                                        );

                                }

                            }

                        },

                        x: {

                            grid: {

                                display: false

                            },

                            ticks: {

                                maxRotation: 45,

                                minRotation: 0

                            }

                        }

                    }

                }

            }
        );

}


/* =========================================================
   HISTORICAL DAILY CLOSING
========================================================= */

function renderHistoricalClosing(
    days
) {

    const container =
        document.getElementById(
            'historicalClosingContainer'
        );


    const summary =
        document.getElementById(
            'historicalClosingSummary'
        );


    if (
        !days ||
        !days.length
    ) {

        summary.innerHTML =
            '';

        container.innerHTML =
            '<div class="empty-state">' +
            'No Daily Closing records for this accounting period.' +
            '</div>';

        return;

    }


    let totalRevenue = 0;
    let totalExpenses = 0;
    let totalProfit = 0;


    days.forEach(
        day => {

            const revenue =
                Number(
                    day.revenue || 0
                );


            const expenses =
                Number(
                    day.expenses ||
                    day.total_expenses ||
                    0
                );


            const profit =
                Number(
                    day.net_profit ??
                    day.net_revenue ??
                    (
                        revenue -
                        expenses
                    )
                );


            totalRevenue +=
                revenue;


            totalExpenses +=
                expenses;


            totalProfit +=
                profit;

        }
    );


    summary.innerHTML =

        '<div class="historical-summary-item">' +

            '<span class="historical-summary-label">' +
            'Revenue' +
            '</span>' +

            '<span class="historical-summary-value revenue">' +
            formatMoney(
                totalRevenue
            ) +
            '</span>' +

        '</div>' +


        '<div class="historical-summary-item">' +

            '<span class="historical-summary-label">' +
            'Expenses' +
            '</span>' +

            '<span class="historical-summary-value expenses">' +
            formatMoney(
                totalExpenses
            ) +
            '</span>' +

        '</div>' +


        '<div class="historical-summary-item">' +

            '<span class="historical-summary-label">' +
            'Net Profit' +
            '</span>' +

            '<span class="historical-summary-value profit">' +
            formatMoney(
                totalProfit
            ) +
            '</span>' +

        '</div>';


    let html =

        '<table class="historical-table">' +

            '<thead>' +

                '<tr>' +

                    '<th>Date</th>' +

                    '<th>Status</th>' +

                    '<th class="amount">Revenue</th>' +

                    '<th class="amount">Expenses</th>' +

                    '<th class="amount">Net Profit</th>' +

                '</tr>' +

            '</thead>' +

            '<tbody>';


    days.forEach(
        day => {

            const revenue =
                Number(
                    day.revenue || 0
                );


            const expenses =
                Number(
                    day.expenses ||
                    day.total_expenses ||
                    0
                );


            const profit =
                Number(
                    day.net_profit ??
                    day.net_revenue ??
                    (
                        revenue -
                        expenses
                    )
                );


            const hasRecord =
                Boolean(
                    day.has_daily_closing ||
                    day.daily_id ||
                    day.status === 'Saved' ||
                    day.status === 'Reopened' ||
                    revenue !== 0 ||
                    expenses !== 0
                );


            const statusClass =
                hasRecord
                    ? 'recorded'
                    : 'no-record';


            const statusText =
                hasRecord
                    ? 'Recorded'
                    : 'No Record';


            html +=

                '<tr>' +

                    '<td>' +

                        '<span class="historical-date">' +
                        escapeHtml(
                            formatDate(
                                day.date
                            )
                        ) +
                        '</span>' +

                    '</td>' +

                    '<td>' +

                        '<span class="closing-status ' +
                        statusClass +
                        '">' +
                        statusText +
                        '</span>' +

                    '</td>' +

                    '<td class="amount">' +
                    formatMoney(
                        revenue
                    ) +
                    '</td>' +

                    '<td class="amount">' +
                    formatMoney(
                        expenses
                    ) +
                    '</td>' +

                    '<td class="amount ' +
                    (
                        profit >= 0
                            ? 'historical-profit'
                            : 'historical-loss'
                    ) +
                    '">' +
                    formatMoney(
                        profit
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
   TOP CUSTOMERS
========================================================= */

function renderTopCustomers(
    customers
) {

    const container =
        document.getElementById(
            'topCustomersContainer'
        );


    if (
        !customers ||
        !customers.length
    ) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No delivery customers for this accounting period.' +
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


    if (
        !balances ||
        !balances.length
    ) {

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
   MANUAL EXPENSES
========================================================= */

function renderManualExpenses(
    expenses
) {

    const container =
        document.getElementById(
            'manualExpensesContainer'
        );


    if (
        !expenses ||
        !expenses.length
    ) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No manual analytics expenses have been recorded.' +
            '</div>';

        return;

    }


    let html =
        '<div class="table-wrapper">' +
        '<table class="dashboard-table">' +
        '<thead>' +
        '<tr>' +
        '<th>Date</th>' +
        '<th>Category</th>' +
        '<th>Description</th>' +
        '<th>Period</th>' +
        '<th class="amount">Amount</th>' +
        '<th></th>' +
        '</tr>' +
        '</thead>' +
        '<tbody>';


    expenses.forEach(
        expense => {

            html +=

                '<tr>' +

                    '<td>' +

                        escapeHtml(
                            formatDate(
                                expense.expense_date
                            )
                        ) +

                    '</td>' +

                    '<td>' +

                        escapeHtml(
                            expense.category
                        ) +

                    '</td>' +

                    '<td>' +

                        '<span class="customer-name">' +
                        escapeHtml(
                            expense.description
                        ) +
                        '</span>' +

                    '</td>' +

                    '<td>' +

                        escapeHtml(
                            expense.period_label ||
                            ''
                        ) +

                    '</td>' +

                    '<td class="amount">' +

                        formatMoney(
                            expense.amount
                        ) +

                    '</td>' +

                    '<td>' +

                        '<button' +
                        ' type="button"' +
                        ' class="expense-action"' +
                        ' data-expense-id="' +
                        escapeHtml(
                            expense.expense_id
                        ) +
                        '"' +
                        '>' +
                        'Delete' +
                        '</button>' +

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


    container
        .querySelectorAll(
            '.expense-action'
        )
        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => {

                        deleteManualExpense(
                            button.dataset.expenseId
                        );

                    }
                );

            }
        );
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


    if (
        !customers ||
        !customers.length
    ) {

        container.innerHTML =
            '<div class="empty-state">' +
            'No customers found.' +
            '</div>';

        return;

    }


    container.innerHTML =
        customers
            .map(
                customer => {

                    let dateText =
                        'Customer record';


                    if (customer.created_at) {

                        const date =
                            new Date(
                                String(
                                    customer.created_at
                                )
                                .replace(
                                    ' ',
                                    'T'
                                )
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

    if (!selectedPeriodId) {
        return;
    }


    const refreshButton =
        document.getElementById(
            'dashboardRefresh'
        );


    refreshButton.disabled =
        true;


    refreshButton.textContent =
        'Loading...';


    document
        .querySelector(
            '.dashboard-page'
        )
        .classList.add(
            'dashboard-loading'
        );


    hideError();


    try {

        const dashboard =
            await requestAnalytics(
                'dashboard',
                {
                    period_id:
                        selectedPeriodId
                }
            );


        const daily =
            await requestAnalytics(
                'daily_chart',
                {
                    period_id:
                        selectedPeriodId
                }
            );


        const manualExpenses =
            await requestAnalytics(
                'manual_expenses',
                {
                    period_id:
                        selectedPeriodId
                }
            );


        renderSummary(
            dashboard.analytics
        );


        renderRevenueChart(
            daily.days || []
        );


        renderHistoricalClosing(
            daily.days || []
        );


        renderManualExpenses(
            manualExpenses.expenses || []
        );


        renderTopCustomers(
            dashboard.top_customers || []
        );


        renderOutstandingBalances(
            dashboard.outstanding_balances || []
        );


        renderRecentCustomers(
            dashboard.recent_customers || []
        );


        const period =
            getSelectedPeriod();


        updatePeriodSubtitle(
            period
        );


        document.getElementById(
            'expensePeriod1'
        ).value =
            selectedPeriodId;


    } catch (error) {

        console.error(error);


        showError(
            error.message ||
            'Unable to load dashboard analytics.'
        );

    } finally {

        refreshButton.disabled =
            false;


        refreshButton.textContent =
            'Refresh';


        document
            .querySelector(
                '.dashboard-page'
            )
            .classList.remove(
                'dashboard-loading'
            );

    }
}


/* =========================================================
   SAVE ACCOUNTING PERIOD
========================================================= */

async function saveAccountingPeriod() {

    const period =
        getSelectedPeriod();


    if (!period) {
        return;
    }


    const start =
        document.getElementById(
            'periodStart'
        ).value;


    const end =
        document.getElementById(
            'periodEnd'
        ).value;


    if (!start || !end) {

        showError(
            'Please enter both the start date and end date.'
        );

        return;

    }


    if (start > end) {

        showError(
            'The start date cannot be later than the end date.'
        );

        return;

    }


    const button =
        document.getElementById(
            'savePeriodButton'
        );


    button.disabled =
        true;


    button.textContent =
        'Saving...';


    hideError();


    try {

        await requestAnalytics(
            'save_period',
            {
                period_id:
                    selectedPeriodId,

                period_start:
                    start,

                period_end:
                    end
            }
        );


        await loadPeriods();


        document.getElementById(
            'periodEditor'
        ).classList.remove(
            'open'
        );


        await loadDashboard();


    } catch (error) {

        showError(
            error.message
        );

    } finally {

        button.disabled =
            false;


        button.textContent =
            'Save Period';

    }
}


/* =========================================================
   MANUAL EXPENSE FORM
========================================================= */

function openExpenseForm() {

    const form =
        document.getElementById(
            'manualExpenseForm'
        );


    form.classList.add(
        'open'
    );


    const period =
        getSelectedPeriod();


    if (period) {

        document.getElementById(
            'expensePeriod1'
        ).value =
            period.period_id;


        const expenseDate =
            document.getElementById(
                'expenseDate'
            );


        /*
         * Default to the selected accounting period's
         * end date only when there is no existing value.
         */

        if (!expenseDate.value) {

            expenseDate.value =
                period.period_end;

        }

    }


    document.getElementById(
        'expenseDate'
    ).focus();
}


function closeExpenseForm() {

    const form =
        document.getElementById(
            'manualExpenseForm'
        );


    form.classList.remove(
        'open'
    );


    form.reset();


    const period =
        getSelectedPeriod();


    if (period) {

        document.getElementById(
            'expensePeriod1'
        ).value =
            period.period_id;

    }
}


async function saveManualExpense(
    event
) {

    event.preventDefault();


    const saveButton =
        document.getElementById(
            'saveExpenseButton'
        );


    const expenseDate =
        document.getElementById(
            'expenseDate'
        ).value;


    const category =
        document.getElementById(
            'expenseCategory'
        ).value;


    const description =
        document.getElementById(
            'expenseDescription'
        ).value.trim();


    const amount =
        document.getElementById(
            'expenseAmount'
        ).value;


    const periodId =
        document.getElementById(
            'expensePeriod1'
        ).value;


    if (
        !expenseDate ||
        !category ||
        !description ||
        !amount ||
        !periodId
    ) {

        showError(
            'Please complete all required expense fields.'
        );

        return;

    }


    const period =
        accountingPeriods.find(
            item =>
                Number(
                    item.period_id
                ) ===
                Number(
                    periodId
                )
        );


    if (!period) {

        showError(
            'The selected accounting period could not be found.'
        );

        return;

    }


    /*
     * The date is validated against the selected
     * accounting period before saving.
     */

    if (
        expenseDate <
        period.period_start ||
        expenseDate >
        period.period_end
    ) {

        showError(
            'The expense date must be within the selected accounting period.'
        );

        return;

    }


    saveButton.disabled =
        true;


    saveButton.textContent =
        'Saving...';


    hideError();


    try {

        await requestAnalytics(
            'save_manual_expense',
            {
                period_id:
                    periodId,

                category:
                    category,

                description:
                    description,

                amount:
                    amount,

                expense_date:
                    expenseDate
            }
        );


        closeExpenseForm();


        await loadDashboard();


    } catch (error) {

        showError(
            error.message
        );

    } finally {

        saveButton.disabled =
            false;


        saveButton.textContent =
            'Save Expense';

    }
}


/* =========================================================
   DELETE MANUAL EXPENSE
========================================================= */

async function deleteManualExpense(
    expenseId
) {

    if (!expenseId) {
        return;
    }


    const confirmed =
        window.confirm(
            'Delete this manual analytics expense?'
        );


    if (!confirmed) {
        return;
    }


    hideError();


    try {

        await requestAnalytics(
            'delete_manual_expense',
            {
                expense_id:
                    expenseId
            }
        );


        await loadDashboard();


    } catch (error) {

        showError(
            error.message
        );

    }
}


/* =========================================================
   EVENTS
========================================================= */

document.getElementById(
    'dashboardPeriod'
).addEventListener(
    'change',
    async function () {

        selectedPeriodId =
            Number(
                this.value
            );


        const period =
            getSelectedPeriod();


        if (period) {

            setPeriodEditor(
                period
            );


            updatePeriodSubtitle(
                period
            );


            /*
             * Change the accounting period selection
             * only. Do not modify an already entered
             * expense date.
             */

            document.getElementById(
                'expensePeriod1'
            ).value =
                selectedPeriodId;

        }


        await loadDashboard();

    }
);


document.getElementById(
    'dashboardRefresh'
).addEventListener(
    'click',
    loadDashboard
);


document.getElementById(
    'editPeriodButton'
).addEventListener(
    'click',
    function () {

        const editor =
            document.getElementById(
                'periodEditor'
            );


        editor.classList.toggle(
            'open'
        );


        const period =
            getSelectedPeriod();


        if (period) {

            setPeriodEditor(
                period
            );

        }

    }
);


document.getElementById(
    'savePeriodButton'
).addEventListener(
    'click',
    saveAccountingPeriod
);


document.getElementById(
    'addExpenseButton'
).addEventListener(
    'click',
    openExpenseForm
);


document.getElementById(
    'cancelExpenseButton'
).addEventListener(
    'click',
    closeExpenseForm
);


document.getElementById(
    'manualExpenseForm'
).addEventListener(
    'submit',
    saveManualExpense
);


/* =========================================================
   INITIAL LOAD
========================================================= */

(async function initDashboard() {

    try {

        hideError();


        await loadPeriods();


        if (selectedPeriodId) {

            await loadDashboard();

        }

    } catch (error) {

        console.error(error);


        showError(
            error.message ||
            'Unable to initialize the dashboard.'
        );

    }

})();

</script>

</body>

</html>