<?php

/*
 * =========================================================
 * TEMPORARY DAILY CLOSING UI
 *
 * UI REBUILD ONLY
 *
 * This page intentionally does NOT use:
 *
 * deliveries-panel.js
 * deliveries-panel-layout.js
 * delivery-ui-fixes.js
 * daily-remittance-status-fix.js
 * daily-closing-reset.js
 * daily-closing-input-fix.js
 *
 * The final visual structure of those scripts has been
 * reproduced directly in this file so that there is only
 * one source of truth for the Temporary UI.
 *
 * Calculations, database saving, autosave, customer price
 * recall, remittance calculations, reset processing, and
 * finalization will be rebuilt later.
 * =========================================================
 */

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';

requireAdmin();

/*
 * =========================================================
 * TEMPORARY DISPLAY VALUES
 * =========================================================
 */

$businessDate = date('Y-m-d');

$walkInCustomers = 0;

$walkInPrice = 30.00;

$walkInSales = 0.00;

$walkInOtherSales = 0.00;

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Daily Closing - Marcid Blue</title>

    <link
        rel="stylesheet"
        href="../assets/css/app.css"
    >

    <style>

    /*
    * =========================================================
    * PAGE HEADER + DAILY CLOSING ACTIONS
    * =========================================================
    */

    .page-header {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        width: 100%;
    }

    .daily-closing-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 12px;
        margin-left: auto;
        flex-shrink: 0;
    }

    .daily-closing-actions .btn {
        min-width: 150px;
    }


    /*
     * =========================================================
     * NET PROFIT VISIBILITY
     * =========================================================
     */

    .net-profit-value {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .net-profit-toggle {
        width: 34px;
        height: 34px;
        min-width: 34px;
        padding: 0;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        border: 1px solid var(--border);
        border-radius: var(--radius-sm);

        background: var(--surface);
        color: var(--text-muted);

        cursor: pointer;

        font-size: 15px;
        line-height: 1;

        transition:
            background 0.15s ease,
            border-color 0.15s ease,
            color 0.15s ease;
    }

    .eye-icon {
    width: 18px;
    height: 18px;
    display: block;
    }

    .net-profit-toggle:hover {
        background: var(--background);
        border-color: var(--primary);
        color: var(--primary);
    }

    /*
    * =========================================================
    * DAILY STATUS
    * =========================================================
    */

    .daily-status-card .summary-label {
        margin-bottom: 0 !important;
    }

    .daily-status-card .summary-value {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .daily-status-card .summary-value .badge {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }


    /*
     * =========================================================
     * SHOP / WALK-IN
     * =========================================================
     */

    .shop-walkin-layout {
        display: grid;
        grid-template-columns:
            minmax(110px, 0.65fr)
            minmax(190px, 1fr)
            minmax(180px, 1fr);
        gap: 18px;
        align-items: end;
    }

    .shop-sales-summary-group {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 18px;
        align-items: end;
    }

    .shop-computed-sales,
    .shop-other-sales {
        padding: 8px 0 4px 8px;
    }

    .shop-computed-sales .summary-value,
    .shop-other-sales .summary-value {
        font-size: 18px;
        font-weight: 700;
        line-height: 1.4;
    }

    .section-description {
        margin-top: 4px;
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.5;
        font-weight: 400;
    }

    .shop-customers-field {
        max-width: 180px;
    }

    .shop-money-field {
        max-width: 230px;
    }

    /*
     * =========================================================
     * EXPENSES / SHOP DELIVERY PANELS
     * =========================================================
     */

    .expenses-panel,
    .delivery-payments-panel {
        margin-top: 28px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }

    .expenses-panel-header,
    .delivery-payments-panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 16px;
    }

    .expenses-title,
    .delivery-payments-title {
        color: var(--text);
        font-size: 16px;
        font-weight: 700;
        line-height: 1.4;
    }

    .expenses-subtitle,
    .delivery-payments-subtitle {
        margin-top: 4px;
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .expense-rows,
    .delivery-payment-rows {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 0 16px;
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
    }

    /*
     * =========================================================
     * EXPENSE ROW
     * =========================================================
     */

    .expense-row {
        display: grid;
        grid-template-columns:
            minmax(180px, 1fr)
            minmax(160px, 0.8fr)
            minmax(220px, 1.2fr)
            38px;
        gap: 14px;
        align-items: end;
        padding: 16px 0;
        background: transparent;
        border: 0;
        border-radius: 0;
    }

    .expense-row + .expense-row,
    .delivery-payment-row + .delivery-payment-row {
        border-top: 1px solid var(--border);
    }

    /*
     * =========================================================
     * SHOP DELIVERY PAYMENT ROW
     * =========================================================
     */

    .delivery-payment-row {
        display: grid;
        grid-template-columns:
            minmax(200px, 1.6fr)
            minmax(70px, 0.55fr)
            minmax(70px, 0.55fr)
            minmax(120px, 1fr)
            minmax(90px, 0.7fr)
            minmax(120px, 0.9fr)
            minmax(145px, 1fr)
            38px;
        gap: 12px;
        align-items: end;
        padding: 16px 0;
        background: transparent;
        border: 0;
        border-radius: 0;
    }

    /*
     * =========================================================
     * SHOP / DRIVER FORM CONTROLS
     * =========================================================
     */

    .expense-row .form-group,
    .delivery-payment-row .form-group,
    .driver-expense-row .form-group,
    .driver-delivery-row .form-group {
        min-width: 0;
        margin: 0;
    }

    .expense-row .form-input,
    .delivery-payment-row .form-input,
    .delivery-balance,
    .driver-expense-row .form-input,
    .driver-delivery-row .form-input,
    .driver-delivery-balance {
        width: 100%;
        height: 38px;
        min-height: 38px;
        box-sizing: border-box;
    }

    .expense-row select.form-input,
    .delivery-payment-row select.form-input,
    .driver-expense-row select.form-input,
    .driver-delivery-row select.form-input {
        padding-top: 0;
        padding-bottom: 0;
        padding-left: 12px;
        padding-right: 32px;
        line-height: normal;
    }

    /*
     * =========================================================
     * REMOVE BUTTONS
     * =========================================================
     */

    .expense-remove,
    .delivery-payment-remove,
    .driver-expense-remove,
    .driver-delivery-remove {
        width: 38px;
        height: 38px;
        min-width: 38px;
        min-height: 38px;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        align-self: end;
        justify-self: center;
        box-sizing: border-box;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: var(--danger);
        font-size: 19px;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        transition:
            background 0.15s ease,
            border-color 0.15s ease;
    }

    .expense-remove:hover,
    .delivery-payment-remove:hover,
    .driver-expense-remove:hover,
    .driver-delivery-remove:hover {
        background: var(--danger-light);
        border-color: var(--danger);
    }

    /*
     * =========================================================
     * SHOP DELIVERY BALANCE
     * =========================================================
     */

    .delivery-balance {
        padding: 0 10px;
        display: flex;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }

    .delivery-balance-neutral {
        color: var(--text-muted);
        font-weight: 500;
    }

    .delivery-balance-paid {
        color: var(--success);
        background: var(--success-light);
        border-color: rgba(46, 155, 91, .18);
    }

    .delivery-balance-due {
        color: var(--warning);
        background: var(--warning-light);
        border-color: rgba(229, 154, 36, .18);
    }

    .delivery-balance-unpaid {
        color: var(--danger);
        background: var(--danger-light);
        border-color: rgba(217, 83, 79, .18);
    }

    .delivery-balance-overpaid {
        color: var(--primary-dark);
        background: var(--primary-light);
        border-color: rgba(22, 135, 201, .18);
    }

    /*
     * =========================================================
     * DELIVERY STATUS LEGEND
     * =========================================================
     */

    .delivery-status-legend {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-top: 12px;
        color: var(--text-muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .delivery-status-legend .legend-label,
    .driver-delivery-legend-copy .legend-label {
        color: var(--text);
        font-weight: 700;
    }

    .delivery-status-item {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        font-weight: 600;
    }

    .delivery-status-paid {
        color: var(--success);
    }

    .delivery-status-due {
        color: var(--warning);
    }

    .delivery-status-unpaid {
        color: var(--danger);
    }

    .delivery-status-overpaid {
        color: var(--primary-dark);
    }

    /*
     * =========================================================
     * DRIVER / DELIVERIES CARD
     *
     * This is the FINAL visual structure produced by:
     *
     * deliveries-panel.js
     * deliveries-panel-layout.js
     * delivery-ui-fixes.js
     *
     * but reproduced directly in this file.
     * =========================================================
     */

    .driver-deliveries-card {
        margin-top: 24px;
    }

    /*
     * =========================================================
     * DRIVER TOP SUMMARY
     * =========================================================
     */

    .driver-deliveries-layout {
        display: grid;
        grid-template-columns:
            repeat(5, minmax(0, 1fr));
        gap: 18px;
        align-items: end;
    }

    .driver-money-field,
    .driver-total-quantity,
    .driver-summary-field {
        min-width: 0;
    }

    .driver-money-field {
        max-width: 280px;
    }

    .driver-total-quantity,
    .driver-summary-field {
        padding: 8px 0 4px 8px;
    }

    .driver-total-quantity .summary-value,
    .driver-summary-field .summary-value {
        font-size: 18px;
        font-weight: 700;
        line-height: 1.4;
    }

    .driver-remittance-status {
        white-space: nowrap;
    }

    .driver-remittance-balanced {
        color: var(--success);
    }

    .driver-remittance-short {
        color: var(--danger);
    }

    .driver-remittance-over {
        color: var(--primary-dark);
    }

    /*
     * =========================================================
     * DRIVER QUANTITY NOTE
     * =========================================================
     *
     * The layout script in the working page removes this note.
     * Therefore it is intentionally NOT displayed here.
     * =========================================================
     */

    /*
     * =========================================================
     * DRIVER SECTIONS
     * =========================================================
     */

    .driver-panel-section {
        margin-top: 28px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }

    .driver-panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 16px;
    }

    .driver-panel-title {
        color: var(--text);
        font-size: 16px;
        font-weight: 700;
        line-height: 1.4;
    }

    .driver-panel-subtitle {
        margin-top: 4px;
        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    /*
     * =========================================================
     * DRIVER LEGEND
     *
     * This reproduces delivery-ui-fixes.js.
     * =========================================================
     */

    .driver-delivery-legend-copy {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-top: 8px;
        margin-left: 0;
        color: var(--text-muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .driver-delivery-legend-copy span {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }

    /*
     * =========================================================
     * DRIVER ROW CONTAINERS
     * =========================================================
     */

    .driver-expense-rows,
    .driver-delivery-payment-rows {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 0 16px;
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
    }

    /*
     * =========================================================
     * DRIVER EXPENSE ROW
     * =========================================================
     */

    .driver-expense-row {
        display: grid;
        grid-template-columns:
            minmax(180px, 1fr)
            minmax(160px, 0.8fr)
            minmax(220px, 1.2fr)
            38px;
        gap: 14px;
        align-items: end;
        padding: 16px 0;
    }

    .driver-expense-row + .driver-expense-row,
    .driver-delivery-row + .driver-delivery-row {
        border-top: 1px solid var(--border);
    }

    /*
     * =========================================================
     * DRIVER DELIVERY ROW
     * =========================================================
     */

    .driver-delivery-row {
        display: grid;
        grid-template-columns:
            minmax(200px, 1.6fr)
            minmax(70px, 0.55fr)
            minmax(70px, 0.55fr)
            minmax(120px, 1fr)
            minmax(90px, 0.7fr)
            minmax(120px, 0.9fr)
            minmax(145px, 1fr)
            38px;
        gap: 12px;
        align-items: end;
        padding: 16px 0;
    }

    /*
     * =========================================================
     * DRIVER PAYMENT CONTROLS
     * =========================================================
     */

    .driver-payment-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .driver-payment-controls .btn {
        min-width: 48px;
        height: 38px;
        padding: 0 12px;
    }

    /*
     * =========================================================
     * DRIVER BALANCE
     * =========================================================
     */

    .driver-delivery-balance {
        padding: 0 10px;
        display: flex;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }

    .driver-delivery-balance-neutral {
        color: var(--text-muted);
        font-weight: 500;
    }

    .driver-delivery-balance-paid {
        color: var(--success);
        background: var(--success-light);
        border-color: rgba(46, 155, 91, .18);
    }

    .driver-delivery-balance-due {
        color: var(--warning);
        background: var(--warning-light);
        border-color: rgba(229, 154, 36, .18);
    }

    .driver-delivery-balance-unpaid {
        color: var(--danger);
        background: var(--danger-light);
        border-color: rgba(217, 83, 79, .18);
    }

    .driver-delivery-balance-overpaid {
        color: var(--primary-dark);
        background: var(--primary-light);
        border-color: rgba(22, 135, 201, .18);
    }

    /*
     * =========================================================
     * BUTTON INTERACTION
     *
     * Reproduces delivery-ui-fixes.js visually.
     * =========================================================
     */

    #shopWalkInForm .btn-primary:hover,
    #driverDeliveriesPanel .btn-primary:hover {
        background: var(--primary-dark) !important;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    #shopWalkInForm .btn-secondary:hover,
    #driverDeliveriesPanel .btn-secondary:hover {
        background: var(--secondary-dark, var(--success-dark, #247d49)) !important;
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    #shopWalkInForm .btn-primary,
    #driverDeliveriesPanel .btn-primary,
    #shopWalkInForm .btn-secondary,
    #driverDeliveriesPanel .btn-secondary {
        transition:
            background-color 0.2s ease,
            transform 0.1s ease,
            box-shadow 0.2s ease;
    }

    #shopWalkInForm .btn-primary:active,
    #driverDeliveriesPanel .btn-primary:active,
    #shopWalkInForm .btn-secondary:active,
    #driverDeliveriesPanel .btn-secondary:active {
        transform: translateY(0);
        box-shadow: none;
    }

    #shopWalkInForm .btn:disabled,
    #driverDeliveriesPanel .btn:disabled {
        transform: none;
        box-shadow: none;
    }

    /*
     * =========================================================
     * RESPONSIVE - DESKTOP LABELS
     * =========================================================
     */

    @media (min-width: 901px) {

        .expense-row:not(:first-child) .form-label,
        .delivery-payment-row:not(:first-child) .form-label,
        .driver-expense-row:not(:first-child) .form-label,
        .driver-delivery-row:not(:first-child) .form-label {
            display: none;
        }

    }

    /*
     * =========================================================
     * RESPONSIVE - 1200PX
     * =========================================================
     */

    @media (max-width: 1200px) {

        .delivery-payment-row,
        .driver-delivery-row {
            grid-template-columns:
                minmax(180px, 1.4fr)
                minmax(65px, .55fr)
                minmax(65px, .55fr)
                minmax(115px, 1fr)
                minmax(85px, .7fr)
                minmax(115px, .9fr)
                minmax(135px, 1fr)
                38px;

            gap: 10px;
        }

    }

    /*
     * =========================================================
     * RESPONSIVE - 900PX
     * =========================================================
     */

    @media (max-width: 900px) {

        .shop-walkin-layout {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .shop-customers-field,
        .shop-money-field {
            max-width: none;
        }

        /*
         * DRIVER TOP AREA
         */

        .driver-deliveries-layout {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .driver-money-field {
            max-width: none;
        }

        /*
         * DRIVER EXPENSE
         */

        .driver-expense-row {
            grid-template-columns:
                1fr
                1fr;
        }

        .driver-expense-row .driver-expense-name-group {
            grid-column: 1 / -1;
        }

        .driver-expense-remove {
            grid-column: 2;
            justify-self: end;
        }

        /*
         * DRIVER DELIVERY
         */

        .driver-delivery-row {
            grid-template-columns:
                1fr
                1fr
                1fr;

            gap: 12px;
        }

        .driver-delivery-row
        .driver-delivery-customer-group {
            grid-column: 1 / -1;
        }

        .driver-delivery-row
        .driver-delivery-balance-group {
            grid-column: span 2;
        }

        .driver-delivery-remove {
            grid-column: 3;
            justify-self: end;
        }

        .driver-panel-header {
            flex-direction: column;
            align-items: stretch;
        }

        /*
         * DRIVER CONTROLS
         */

        .driver-payment-controls {
            width: 100%;
        }

        .driver-payment-controls .btn {
            flex: 1;
        }

    }

    /*
    * =========================================================
    * RESPONSIVE - 650PX
    * =========================================================
    */

    @media (max-width: 650px) {

        .page-header {
            flex-direction: column;
            align-items: stretch;
        }

        .daily-closing-actions {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            margin-left: 0;
        }

        .daily-closing-actions .btn {
            width: 100%;
        }

        .shop-walkin-layout {
            grid-template-columns: 1fr;
        }

        /*
        * DRIVER TOP
        */

        .driver-deliveries-layout {
            grid-template-columns: 1fr;
        }

        /*
        * ALL ROWS
        */

        .expense-row,
        .delivery-payment-row,
        .driver-expense-row,
        .driver-delivery-row {
            grid-template-columns: 1fr;
        }

        /*
        * SHOP DELIVERY
        */

        .delivery-payment-row
        .delivery-customer-group {
            grid-column: auto;
        }

        .delivery-payment-row
        .delivery-balance-group {
            grid-column: auto;
        }

        /*
        * DRIVER
        */

        .driver-expense-row
        .driver-expense-name-group,

        .driver-delivery-row
        .driver-delivery-customer-group,

        .driver-delivery-row
        .driver-delivery-balance-group {
            grid-column: auto;
        }

        /*
        * REMOVE BUTTONS
        */

        .expense-remove,
        .delivery-payment-remove,
        .driver-expense-remove,
        .driver-delivery-remove {
            grid-column: auto;
            justify-self: start;
        }

        /*
        * DRIVER CONTROLS
        */

        .driver-payment-controls {
            width: 100%;
        }

        .driver-payment-controls .btn {
            flex: 1;
        }

    }

    </style>

</head>

<body>

<div class="app">

    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <img
                src="../assets/images/mb-logo.png"
                alt="Marcid Blue Logo"
            >

        </div>

        <nav class="sidebar-nav">

            <div class="nav-section-title">
                Main
            </div>

            <a
                href="../pages/home.php"
                class="nav-item"
            >
                🏠
                <span>Home</span>
            </a>

            <a
                href="#"
                class="nav-item"
            >
                👥
                <span>Customers</span>
            </a>

            <a
                href="#"
                class="nav-item"
            >
                📅
                <span>Daily Records</span>
            </a>

            <a
                href="#"
                class="nav-item active"
            >
                🧾
                <span>Daily Closing</span>
            </a>

            <div
                class="nav-section-title"
                style="margin-top: 25px;"
            >
                System
            </div>

            <a
                href="#"
                class="nav-item"
            >
                ⚙️
                <span>Settings</span>
            </a>

            <a
                href="#"
                class="nav-item"
            >
                🚪
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="main">

        <!-- =================================================
             TOP BAR
             ================================================= -->

        <header class="topbar">

            <div class="topbar-title">
                Daily Closing
            </div>

            <div class="topbar-user">

                👤

                <?= htmlspecialchars(
                    $_SESSION['full_name'] ?? 'Admin'
                ) ?>

            </div>

        </header>


        <!-- =================================================
             PAGE
             ================================================= -->

        <section class="page">

            <!-- =================================================
                 PAGE HEADER
                 ================================================= -->

            <!-- =================================================
     PAGE HEADER
     ================================================= -->

    <div class="page-header">

    <div>

        <h1 class="page-title">
            Daily Closing
        </h1>

        <p class="page-subtitle">

            <?= date(
                'l, F j, Y',
                strtotime($businessDate)
            ) ?>

        </p>

    </div>


    <!-- =================================================
         DAILY CLOSING ACTIONS
         ================================================= -->

    <div class="daily-closing-actions">

        <button
            type="button"
            class="btn btn-secondary"
            id="resetDailyClosingButton"
        >
            Reset
        </button>

        <button
            type="button"
            class="btn btn-primary"
            id="finalizeDailyClosingButton"
        >
            Finalize &amp; Close Day
        </button>

    </div>

    </div>


            <!-- =================================================
                 SUMMARY CARDS
                 ================================================= -->

            <div class="summary-grid">

                <!-- =================================================
                     SUMMARY CARD 1 - NET PROFIT
                     ================================================= -->

                <div class="card summary-card">

                    <div class="summary-label">
                        Net Profit for Today
                    </div>

                    <div class="summary-value net-profit-value">

                        <span class="net-profit-hidden">
                            ₱••••••
                        </span>

                        <span
                            class="net-profit-visible"
                            style="display:none;"
                        >
                            ₱0.00
                        </span>

                        <button
                        type="button"
                        class="net-profit-toggle"
                        aria-label="Show net profit"
                        title="Show net profit"
                    >

                        <!-- OPEN EYE
                            Hidden initially because the value is hidden. -->

                        <svg
                            class="eye-icon eye-icon-open"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            style="display:none;"
                        >

                            <path
                                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="3"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            />

                        </svg>


                        <!-- CLOSED EYE
                            Visible initially because the value is hidden. -->

                        <svg
                            class="eye-icon eye-icon-closed"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >

                            <path
                                d="M3 3l18 18"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />

                            <path
                                d="M10.6 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-3.2 4.1M6.2 6.2C3.5 8.2 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.4-.6"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <path
                                d="M9.9 9.9a3 3 0 0 0 4.2 4.2"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />

                        </svg>

                    </button>

                    </div>

                    <div class="summary-description">
                        Total money received from Shop and Driver
                    </div>

                </div>


                <!-- =================================================
                     SUMMARY CARD 2 - WALK-IN
                     ================================================= -->

                <div class="card summary-card">

                    <div class="summary-label">
                        Total Walk-in For Today
                    </div>

                    <div
                        class="summary-value"
                        id="dashboardShopCustomers"
                    >
                        0
                    </div>

                    <div class="summary-description">
                        Walk-in customers served
                    </div>

                </div>


                <!-- =================================================
                     SUMMARY CARD 3 - DELIVERIES
                     ================================================= -->

                <div class="card summary-card">

                    <div class="summary-label">
                        Total Deliveries for Today
                    </div>

                    <div
                        class="summary-value"
                        id="dashboardTotalDeliveries"
                    >
                        0
                    </div>

                    <div class="summary-description">
                        Total delivery transactions
                    </div>

                </div>


                <!-- =================================================
                     SUMMARY CARD 4 - DAILY STATUS
                     ================================================= -->

                <div class="card summary-card daily-status-card">

                    <div class="summary-label">
                        Daily Status
                    </div>

                    <div class="summary-value">

                        <span class="badge badge-warning">
                            Open
                        </span>

                    </div>

                    <div class="summary-description">
                        <?= htmlspecialchars($businessDate) ?>
                    </div>

                </div>

            </div>


            <br>


            <!-- =================================================
                 SHOP / WALK-IN PANEL
                 ================================================= -->

            <div class="card">

                <div class="card-header">

                    <div>

                        <div class="card-title">
                            Shop / Walk-in
                        </div>

                        <div class="section-description">
                            Record regular customers, money received,
                            station expenses, and delivery payments
                            received at the shop.
                        </div>

                    </div>

                </div>


                <div class="card-body">

                    <form
                        method="POST"
                        id="shopWalkInForm"
                        onsubmit="return false;"
                    >

                        <!-- =================================================
                             SHOP MAIN INPUTS
                             ================================================= -->

                        <div class="shop-walkin-layout">

                            <div class="shop-money-field">

                                <label
                                    for="walk_in_money"
                                    class="form-label"
                                >
                                    Total Money Received (Shop only)
                                </label>

                                <input
                                    type="number"
                                    id="walk_in_money"
                                    name="walk_in_money"
                                    class="form-input"
                                    min="0"
                                    step="0.01"
                                    placeholder="Enter amount"
                                >

                            </div>


                            <div class="shop-customers-field">

                                <label
                                    for="walk_in_customers"
                                    class="form-label"
                                >
                                    Total Quantity of Walk-in
                                </label>

                                <input
                                    type="number"
                                    id="walk_in_customers"
                                    name="walk_in_customers"
                                    class="form-input"
                                    min="0"
                                    step="1"
                                    value="0"
                                    placeholder="Calculated"
                                    readonly
                                >

                            </div>


                            <div class="shop-sales-summary-group">

                                <div class="shop-computed-sales">

                                    <div class="summary-label">
                                        Total Sales for Walk-in
                                    </div>

                                    <div
                                        class="summary-value"
                                        id="shopComputedSales"
                                    >
                                        ₱0.00
                                    </div>

                                </div>


                                <div class="shop-other-sales">

                                    <div class="summary-label">
                                        Other / Additional Sales
                                    </div>

                                    <div
                                        class="summary-value"
                                        id="shopOtherSales"
                                    >
                                        ₱0.00
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- =================================================
                             STATION EXPENSES
                             ================================================= -->

                        <div class="expenses-panel">

                            <div class="expenses-panel-header">

                                <div>

                                    <div class="expenses-title">
                                        Station Expenses
                                    </div>

                                    <div class="expenses-subtitle">
                                        Add one or more expenses for
                                        today's station operation.
                                    </div>

                                </div>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    id="addExpenseButton"
                                >
                                    + Expenses
                                </button>

                            </div>


                            <div
                                id="expenseRows"
                                class="expense-rows"
                            >

                                <!-- STATION EXPENSE ROW -->

                                <div class="expense-row">

                                    <div class="form-group">

                                        <label class="form-label">
                                            Expense
                                        </label>

                                        <select
                                            name="expense_category[]"
                                            class="form-input expense-category"
                                        >

                                            <option value="">
                                                No Expense
                                            </option>

                                            <option value="Food">
                                                Food
                                            </option>

                                            <option value="Gas">
                                                Gas
                                            </option>

                                            <option value="Cash Advance">
                                                Cash Advance
                                            </option>

                                            <option value="Others">
                                                Others
                                            </option>

                                        </select>

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Amount
                                        </label>

                                        <input
                                            type="number"
                                            name="expense_amount[]"
                                            class="form-input expense-amount"
                                            min="0"
                                            step="0.01"
                                            placeholder="Enter amount"
                                        >

                                    </div>


                                    <div class="form-group expense-name-group">

                                        <label
                                            class="form-label expense-name-label"
                                        >
                                            Name / Description
                                        </label>

                                        <input
                                            type="text"
                                            name="expense_name[]"
                                            class="form-input expense-name"
                                            maxlength="255"
                                            placeholder="Only needed for Cash Advance / Others"
                                            disabled
                                        >

                                    </div>


                                    <button
                                        type="button"
                                        class="expense-remove"
                                        title="Remove expense"
                                        aria-label="Remove expense"
                                    >
                                        ×
                                    </button>

                                </div>

                            </div>

                        </div>


                        <!-- =================================================
                             SHOP DELIVERY PAYMENTS
                             ================================================= -->

                        <div class="delivery-payments-panel">

                            <div class="delivery-payments-panel-header">

                                <div>

                                    <div class="delivery-payments-title">
                                        Shop Delivery Payments
                                    </div>

                                    <div class="delivery-payments-subtitle">
                                        Price/Gal is entered once for a
                                        new customer and saved for
                                        future transactions.
                                    </div>


                                    <div
                                        class="delivery-status-legend"
                                        aria-label="Delivery payment status legend"
                                    >

                                        <span class="legend-label">
                                            Status:
                                        </span>

                                        <span class="delivery-status-item delivery-status-paid">
                                            ● Paid
                                        </span>

                                        <span class="delivery-status-item delivery-status-due">
                                            ● Due
                                        </span>

                                        <span class="delivery-status-item delivery-status-unpaid">
                                            ● Unpaid
                                        </span>

                                        <span class="delivery-status-item delivery-status-overpaid">
                                            ● Overpaid
                                        </span>

                                    </div>

                                </div>


                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    id="addDeliveryPaymentButton"
                                >
                                    + Payment
                                </button>

                            </div>


                            <div
                                id="deliveryPaymentRows"
                                class="delivery-payment-rows"
                            >

                                <!-- SHOP DELIVERY PAYMENT ROW -->

                                <div class="delivery-payment-row">

                                    <div class="form-group delivery-customer-group">

                                        <label class="form-label">
                                            Customer
                                        </label>

                                        <input
                                            type="text"
                                            name="delivery_customer[]"
                                            class="form-input delivery-customer"
                                            list="shopDeliveryCustomerList"
                                            maxlength="100"
                                            placeholder="Select or enter customer"
                                            autocomplete="off"
                                        >

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Slim
                                        </label>

                                        <input
                                            type="number"
                                            name="delivery_slim[]"
                                            class="form-input delivery-slim"
                                            min="0"
                                            step="1"
                                            placeholder="0"
                                        >

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Round
                                        </label>

                                        <input
                                            type="number"
                                            name="delivery_round[]"
                                            class="form-input delivery-round"
                                            min="0"
                                            step="1"
                                            placeholder="0"
                                        >

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Payment
                                        </label>

                                        <input
                                            type="number"
                                            name="delivery_payment[]"
                                            class="form-input delivery-payment"
                                            min="0"
                                            step="0.01"
                                            placeholder="0"
                                        >

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Price/Gal
                                        </label>

                                        <input
                                            type="number"
                                            name="delivery_price_per_gallon[]"
                                            class="form-input delivery-price-input"
                                            min="0"
                                            step="5"
                                            placeholder="0"
                                        >

                                    </div>


                                    <div class="form-group">

                                        <label class="form-label">
                                            Method
                                        </label>

                                        <select
                                            name="delivery_method[]"
                                            class="form-input delivery-method"
                                        >

                                            <option
                                                value="Cash"
                                                selected
                                            >
                                                Cash
                                            </option>

                                            <option value="GCash">
                                                GCash
                                            </option>

                                            <option value="Bank Transfer">
                                                Bank Transfer
                                            </option>

                                            <option value="Other">
                                                Other
                                            </option>

                                        </select>

                                    </div>


                                    <div class="form-group delivery-balance-group">

                                        <label class="form-label">
                                            Balance
                                        </label>

                                        <div
                                            class="delivery-balance delivery-balance-neutral"
                                            aria-live="polite"
                                        >
                                            —
                                        </div>

                                    </div>


                                    <button
                                        type="button"
                                        class="delivery-payment-remove"
                                        title="Remove payment"
                                        aria-label="Remove payment"
                                    >
                                        ×
                                    </button>

                                </div>

                            </div>

                        </div>


                        <!-- =================================================
                             CUSTOMER DATAlIST
                             ================================================= -->

                        <datalist id="shopDeliveryCustomerList">
                        </datalist>

                    </form>

                </div>

            </div>


            <!-- =================================================
                 DELIVERIES / DRIVER PANEL
                 ================================================= -->

            <div
                id="driverDeliveriesPanel"
                class="card driver-deliveries-card"
            >

                <!-- =================================================
                     DRIVER CARD HEADER
                     ================================================= -->

                <div class="card-header">

                    <div>

                        <div class="card-title">
                            Deliveries
                        </div>

                        <div class="section-description">
                            Record the driver's remittance, driver's
                            expenses, and delivery payments separately
                            from the Station / Shop records.
                        </div>

                    </div>

                </div>


                <div class="card-body">

                    <!-- =================================================
                         DRIVER TOP SUMMARY
                         ================================================= -->

                    <div class="driver-deliveries-layout">

                        <!-- DRIVER MONEY -->

                        <div class="driver-money-field">

                            <label
                                for="driver_money_received"
                                class="form-label"
                            >
                                Total Money Received (Driver Only)
                            </label>

                            <input
                                type="number"
                                id="driver_money_received"
                                name="driver_money_received"
                                class="form-input"
                                min="0"
                                step="0.01"
                                placeholder="Enter amount"
                            >

                        </div>


                        <!-- DRIVER QUANTITY -->

                        <div class="driver-total-quantity">

                            <div class="summary-label">
                                Total Quantity of Deliveries
                            </div>

                            <div
                                class="summary-value"
                                id="driver_total_delivery_quantity"
                            >
                                0
                            </div>

                        </div>


                        <!-- TOTAL SALES -->

                        <div class="driver-summary-field">

                            <div class="summary-label">
                                Total Sales of Delivery
                            </div>

                            <div
                                class="summary-value"
                                id="driverTotalDeliverySales"
                            >
                                ₱0.00
                            </div>

                        </div>


                        <!-- EXPECTED MONEY -->

                        <div class="driver-summary-field">

                            <div class="summary-label">
                                Total Expected Money of Delivery
                            </div>

                            <div
                                class="summary-value"
                                id="driverTotalExpectedMoney"
                            >
                                ₱0.00
                            </div>

                        </div>


                        <!-- REMITTANCE STATUS -->

                        <div class="driver-summary-field">

                            <div class="summary-label">
                                Driver Remittance Status
                            </div>

                            <div
                                class="summary-value driver-remittance-status"
                                id="driverRemittanceStatus"
                            >
                                —
                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         DRIVER EXPENSES
                         ================================================= -->

                    <div class="driver-panel-section">

                        <div class="driver-panel-header">

                            <div>

                                <div class="driver-panel-title">
                                    Driver's Expenses
                                </div>

                                <div class="driver-panel-subtitle">
                                    Add one or more expenses made by the
                                    driver. These remain separate from
                                    Station Expenses.
                                </div>

                            </div>


                            <button
                                type="button"
                                class="btn btn-secondary"
                                id="addDriverExpenseButton"
                            >
                                + Expenses
                            </button>

                        </div>


                        <div
                            id="driverExpenseRows"
                            class="driver-expense-rows"
                        >

                            <!-- DRIVER EXPENSE ROW -->

                            <div class="driver-expense-row">

                                <div class="form-group">

                                    <label class="form-label">
                                        Expense
                                    </label>

                                    <select
                                        name="driver_expense_category[]"
                                        class="form-input driver-expense-category"
                                    >

                                        <option value="">
                                            No Expense
                                        </option>

                                        <option value="Food">
                                            Food
                                        </option>

                                        <option value="Gas">
                                            Gas
                                        </option>

                                        <option value="Cash Advance">
                                            Cash Advance
                                        </option>

                                        <option value="Others">
                                            Others
                                        </option>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label class="form-label">
                                        Amount
                                    </label>

                                    <input
                                        type="number"
                                        name="driver_expense_amount[]"
                                        class="form-input driver-expense-amount"
                                        min="0"
                                        step="0.01"
                                        placeholder="Enter amount"
                                    >

                                </div>


                                <div class="form-group driver-expense-name-group">

                                    <label
                                        class="form-label driver-expense-name-label"
                                    >
                                        Name / Description
                                    </label>

                                    <input
                                        type="text"
                                        name="driver_expense_name[]"
                                        class="form-input driver-expense-name"
                                        maxlength="255"
                                        placeholder="Only needed for Cash Advance / Others"
                                        disabled
                                    >

                                </div>


                                <button
                                    type="button"
                                    class="driver-expense-remove"
                                    title="Remove driver expense"
                                    aria-label="Remove driver expense"
                                >
                                    ×
                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         DRIVER DELIVERY PAYMENTS
                         ================================================= -->

                    <div class="driver-panel-section">

                        <div class="driver-panel-header">

                            <div>

                                <div class="driver-panel-title">
                                    Driver's Delivery Payments
                                </div>

                                <div class="driver-panel-subtitle">
                                    Same customer, quantity, Price/Gal,
                                    payment, method, and balance
                                    functionality as Shop Delivery Payments.
                                </div>


                                <!-- DRIVER LEGEND -->

                                <div
                                    class="driver-delivery-legend-copy"
                                    aria-label="Driver delivery payment status legend"
                                >

                                    <span class="legend-label">
                                            Status:
                                        </span>

                                        <span class="delivery-status-item delivery-status-paid">
                                            ● Paid
                                        </span>

                                        <span class="delivery-status-item delivery-status-due">
                                            ● Due
                                        </span>

                                        <span class="delivery-status-item delivery-status-unpaid">
                                            ● Unpaid
                                        </span>

                                        <span class="delivery-status-item delivery-status-overpaid">
                                            ● Overpaid
                                        </span>

                                </div>

                            </div>


                            <!-- DRIVER +/- CONTROLS -->

                            <div
                                class="driver-payment-controls"
                                aria-label="Add or remove driver delivery payment rows"
                            >

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-driver-payment-adjust="1"
                                >
                                    +1
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-driver-payment-adjust="3"
                                >
                                    +3
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-driver-payment-adjust="10"
                                >
                                    +10
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-driver-payment-adjust="-1"
                                >
                                    −1
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-driver-payment-adjust="-3"
                                >
                                    −3
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-driver-payment-adjust="-10"
                                >
                                    −10
                                </button>

                            </div>

                        </div>


                        <div
                            id="driverDeliveryPaymentRows"
                            class="driver-delivery-payment-rows"
                        >

                            <!-- DRIVER DELIVERY PAYMENT ROW -->

                            <div class="driver-delivery-row">

                                <!-- CUSTOMER -->

                                <div class="form-group driver-delivery-customer-group">

                                    <label class="form-label">
                                        Customer
                                    </label>

                                    <input
                                        type="text"
                                        name="driver_delivery_customer[]"
                                        class="form-input driver-delivery-customer"
                                        list="shopDeliveryCustomerList"
                                        maxlength="100"
                                        placeholder="Select or enter customer"
                                        autocomplete="off"
                                    >

                                </div>


                                <!-- SLIM -->

                                <div class="form-group">

                                    <label class="form-label">
                                        Slim
                                    </label>

                                    <input
                                        type="number"
                                        name="driver_delivery_slim[]"
                                        class="form-input driver-delivery-slim"
                                        min="0"
                                        step="1"
                                        placeholder="0"
                                    >

                                </div>


                                <!-- ROUND -->

                                <div class="form-group">

                                    <label class="form-label">
                                        Round
                                    </label>

                                    <input
                                        type="number"
                                        name="driver_delivery_round[]"
                                        class="form-input driver-delivery-round"
                                        min="0"
                                        step="1"
                                        placeholder="0"
                                    >

                                </div>


                                <!-- PAYMENT -->

                                <div class="form-group">

                                    <label class="form-label">
                                        Payment
                                    </label>

                                    <input
                                        type="number"
                                        name="driver_delivery_payment[]"
                                        class="form-input driver-delivery-payment"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                    >

                                </div>


                                <!-- PRICE -->

                                <div class="form-group">

                                    <label class="form-label">
                                        Price/Gal
                                    </label>

                                    <input
                                        type="number"
                                        name="driver_delivery_price_per_gallon[]"
                                        class="form-input driver-delivery-price"
                                        min="0"
                                        step="5"
                                        placeholder="0"
                                    >

                                </div>


                                <!-- METHOD -->

                                <div class="form-group">

                                    <label class="form-label">
                                        Method
                                    </label>

                                    <select
                                        name="driver_delivery_method[]"
                                        class="form-input driver-delivery-method"
                                    >

                                        <option
                                            value="Cash"
                                            selected
                                        >
                                            Cash
                                        </option>

                                        <option value="GCash">
                                            GCash
                                        </option>

                                        <option value="Bank Transfer">
                                            Bank Transfer
                                        </option>

                                        <option value="Other">
                                            Other
                                        </option>

                                    </select>

                                </div>


                                <!-- BALANCE -->

                                <div class="form-group driver-delivery-balance-group">

                                    <label class="form-label">
                                        Balance
                                    </label>

                                    <div
                                        class="driver-delivery-balance driver-delivery-balance-neutral"
                                        aria-live="polite"
                                    >
                                        —
                                    </div>

                                </div>


                                <!-- REMOVE -->

                                <button
                                    type="button"
                                    class="driver-delivery-remove"
                                    title="Remove payment"
                                    aria-label="Remove payment"
                                >
                                    ×
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>


<!-- =========================================================
     UI-ONLY JAVASCRIPT

     IMPORTANT:

     These scripts ONLY reproduce the row-management behavior.

     They do NOT:
       - calculate sales
       - calculate balances
       - save to MySQL
       - autosave
       - load customers
       - finalize the day
       - reset the day
       - calculate remittance status
     ========================================================= -->

<script>

(function () {

    'use strict';


    /*
 * =========================================================
 * NET PROFIT VISIBILITY
 * =========================================================
 */

const netProfitToggle =
    document.querySelector(
        '.net-profit-toggle'
    );

const netProfitHidden =
    document.querySelector(
        '.net-profit-hidden'
    );

const netProfitVisible =
    document.querySelector(
        '.net-profit-visible'
    );

const eyeIconOpen =
    document.querySelector(
        '.eye-icon-open'
    );

const eyeIconClosed =
    document.querySelector(
        '.eye-icon-closed'
    );


if (
    netProfitToggle &&
    netProfitHidden &&
    netProfitVisible &&
    eyeIconOpen &&
    eyeIconClosed
) {

    netProfitToggle.addEventListener(
        'click',
        function () {

            const isHidden =
                netProfitHidden.style.display !== 'none';


            if (isHidden) {

                /*
                 * Show the actual value.
                 */

                netProfitHidden.style.display =
                    'none';

                netProfitVisible.style.display =
                    'inline';


                /*
                 * Open eye = value is visible.
                 */

                eyeIconOpen.style.display =
                    'block';

                eyeIconClosed.style.display =
                    'none';


                netProfitToggle.setAttribute(
                    'aria-label',
                    'Hide net profit'
                );

                netProfitToggle.setAttribute(
                    'title',
                    'Hide net profit'
                );

            } else {

                /*
                 * Hide the actual value.
                 */

                netProfitHidden.style.display =
                    'inline';

                netProfitVisible.style.display =
                    'none';


                /*
                 * Closed eye = value is hidden.
                 */

                eyeIconOpen.style.display =
                    'none';

                eyeIconClosed.style.display =
                    'block';


                netProfitToggle.setAttribute(
                    'aria-label',
                    'Show net profit'
                );

                netProfitToggle.setAttribute(
                    'title',
                    'Show net profit'
                );

            }

        }
    );

    }


    /*
     * =========================================================
     * STATION EXPENSES
     * =========================================================
     */

    const expenseRows =
        document.getElementById('expenseRows');

    const addExpenseButton =
        document.getElementById('addExpenseButton');


    function updateExpenseRow(row) {

        if (!row) return;

        const category =
            row.querySelector('.expense-category');

        const name =
            row.querySelector('.expense-name');

        const label =
            row.querySelector('.expense-name-label');

        if (!category || !name || !label) return;

        const needsName =
            category.value === 'Cash Advance' ||
            category.value === 'Others';

        name.disabled = !needsName;

        name.required = needsName;

        label.textContent =
            needsName
                ? 'Name / Description *'
                : 'Name / Description';

        if (needsName) {

            name.placeholder =
                category.value === 'Cash Advance'
                    ? 'Enter recipient name'
                    : 'Enter expense description';

        } else {

            name.value = '';

            name.placeholder =
                'Not required for Food / Gas';

        }

    }


    function createExpenseRow() {

        const row =
            document.createElement('div');

        row.className =
            'expense-row';

        row.innerHTML = `

            <div class="form-group">

                <label class="form-label">
                    Expense
                </label>

                <select
                    name="expense_category[]"
                    class="form-input expense-category"
                >

                    <option value="">
                        No Expense
                    </option>

                    <option value="Food">
                        Food
                    </option>

                    <option value="Gas">
                        Gas
                    </option>

                    <option value="Cash Advance">
                        Cash Advance
                    </option>

                    <option value="Others">
                        Others
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Amount
                </label>

                <input
                    type="number"
                    name="expense_amount[]"
                    class="form-input expense-amount"
                    min="0"
                    step="0.01"
                    placeholder="Enter amount"
                >

            </div>


            <div class="form-group expense-name-group">

                <label class="form-label expense-name-label">
                    Name / Description
                </label>

                <input
                    type="text"
                    name="expense_name[]"
                    class="form-input expense-name"
                    maxlength="255"
                    placeholder="Not required for Food / Gas"
                    disabled
                >

            </div>


            <button
                type="button"
                class="expense-remove"
                title="Remove expense"
                aria-label="Remove expense"
            >
                ×
            </button>

        `;

        expenseRows.appendChild(row);

        updateExpenseRow(row);

    }


    if (addExpenseButton) {

        addExpenseButton.addEventListener(
            'click',
            createExpenseRow
        );

    }


    expenseRows.addEventListener(
        'change',
        function (event) {

            if (
                event.target.classList.contains(
                    'expense-category'
                )
            ) {

                updateExpenseRow(
                    event.target.closest(
                        '.expense-row'
                    )
                );

            }

        }
    );


    expenseRows.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.expense-remove'
                );

            if (!button) return;

            const rows =
                expenseRows.querySelectorAll(
                    '.expense-row'
                );

            const row =
                button.closest(
                    '.expense-row'
                );

            if (!row) return;

            if (rows.length === 1) {

                const category =
                    row.querySelector(
                        '.expense-category'
                    );

                const amount =
                    row.querySelector(
                        '.expense-amount'
                    );

                const name =
                    row.querySelector(
                        '.expense-name'
                    );

                if (category) {
                    category.value = '';
                }

                if (amount) {
                    amount.value = '';
                }

                if (name) {
                    name.value = '';
                }

                updateExpenseRow(row);

            } else {

                row.remove();

            }

        }
    );


    /*
     * =========================================================
     * SHOP DELIVERY PAYMENTS
     * =========================================================
     */

    const deliveryPaymentRows =
        document.getElementById(
            'deliveryPaymentRows'
        );

    const addDeliveryPaymentButton =
        document.getElementById(
            'addDeliveryPaymentButton'
        );


    function createDeliveryPaymentRow() {

        const row =
            document.createElement('div');

        row.className =
            'delivery-payment-row';

        row.innerHTML = `

            <div class="form-group delivery-customer-group">

                <label class="form-label">
                    Customer
                </label>

                <input
                    type="text"
                    name="delivery_customer[]"
                    class="form-input delivery-customer"
                    list="shopDeliveryCustomerList"
                    maxlength="100"
                    placeholder="Select or enter customer"
                    autocomplete="off"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Slim
                </label>

                <input
                    type="number"
                    name="delivery_slim[]"
                    class="form-input delivery-slim"
                    min="0"
                    step="1"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Round
                </label>

                <input
                    type="number"
                    name="delivery_round[]"
                    class="form-input delivery-round"
                    min="0"
                    step="1"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Payment
                </label>

                <input
                    type="number"
                    name="delivery_payment[]"
                    class="form-input delivery-payment"
                    min="0"
                    step="0.01"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Price/Gal
                </label>

                <input
                    type="number"
                    name="delivery_price_per_gallon[]"
                    class="form-input delivery-price-input"
                    min="0"
                    step="5"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Method
                </label>

                <select
                    name="delivery_method[]"
                    class="form-input delivery-method"
                >

                    <option
                        value="Cash"
                        selected
                    >
                        Cash
                    </option>

                    <option value="GCash">
                        GCash
                    </option>

                    <option value="Bank Transfer">
                        Bank Transfer
                    </option>

                    <option value="Other">
                        Other
                    </option>

                </select>

            </div>


            <div class="form-group delivery-balance-group">

                <label class="form-label">
                    Balance
                </label>

                <div
                    class="delivery-balance delivery-balance-neutral"
                    aria-live="polite"
                >
                    —
                </div>

            </div>


            <button
                type="button"
                class="delivery-payment-remove"
                title="Remove payment"
                aria-label="Remove payment"
            >
                ×
            </button>

        `;

        deliveryPaymentRows.appendChild(row);

    }


    if (addDeliveryPaymentButton) {

        addDeliveryPaymentButton.addEventListener(
            'click',
            createDeliveryPaymentRow
        );

    }


    deliveryPaymentRows.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.delivery-payment-remove'
                );

            if (!button) return;

            const rows =
                deliveryPaymentRows.querySelectorAll(
                    '.delivery-payment-row'
                );

            const row =
                button.closest(
                    '.delivery-payment-row'
                );

            if (!row) return;

            if (rows.length === 1) {

                row.querySelectorAll(
                    'input'
                ).forEach(function (input) {

                    input.value = '';

                });

                const method =
                    row.querySelector(
                        '.delivery-method'
                    );

                if (method) {
                    method.value = 'Cash';
                }

                const balance =
                    row.querySelector(
                        '.delivery-balance'
                    );

                if (balance) {

                    balance.className =
                        'delivery-balance delivery-balance-neutral';

                    balance.textContent = '—';

                }

            } else {

                row.remove();

            }

        }
    );


    /*
     * =========================================================
     * DRIVER EXPENSES
     * =========================================================
     */

    const driverExpenseRows =
        document.getElementById(
            'driverExpenseRows'
        );

    const addDriverExpenseButton =
        document.getElementById(
            'addDriverExpenseButton'
        );


    function updateDriverExpenseRow(row) {

        if (!row) return;

        const category =
            row.querySelector(
                '.driver-expense-category'
            );

        const name =
            row.querySelector(
                '.driver-expense-name'
            );

        const label =
            row.querySelector(
                '.driver-expense-name-label'
            );

        if (!category || !name || !label) {
            return;
        }

        const needsName =
            category.value === 'Cash Advance' ||
            category.value === 'Others';

        name.disabled = !needsName;

        name.required = needsName;

        label.textContent =
            needsName
                ? 'Name / Description *'
                : 'Name / Description';

        if (needsName) {

            name.placeholder =
                category.value === 'Cash Advance'
                    ? 'Enter recipient name'
                    : 'Enter expense description';

        } else {

            name.value = '';

            name.placeholder =
                'Not required for Food / Gas';

        }

    }


    function createDriverExpenseRow() {

        const row =
            document.createElement('div');

        row.className =
            'driver-expense-row';

        row.innerHTML = `

            <div class="form-group">

                <label class="form-label">
                    Expense
                </label>

                <select
                    name="driver_expense_category[]"
                    class="form-input driver-expense-category"
                >

                    <option value="">
                        No Expense
                    </option>

                    <option value="Food">
                        Food
                    </option>

                    <option value="Gas">
                        Gas
                    </option>

                    <option value="Cash Advance">
                        Cash Advance
                    </option>

                    <option value="Others">
                        Others
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Amount
                </label>

                <input
                    type="number"
                    name="driver_expense_amount[]"
                    class="form-input driver-expense-amount"
                    min="0"
                    step="0.01"
                    placeholder="Enter amount"
                >

            </div>


            <div class="form-group driver-expense-name-group">

                <label class="form-label driver-expense-name-label">
                    Name / Description
                </label>

                <input
                    type="text"
                    name="driver_expense_name[]"
                    class="form-input driver-expense-name"
                    maxlength="255"
                    placeholder="Not required for Food / Gas"
                    disabled
                >

            </div>


            <button
                type="button"
                class="driver-expense-remove"
                title="Remove driver expense"
                aria-label="Remove driver expense"
            >
                ×
            </button>

        `;

        driverExpenseRows.appendChild(row);

        updateDriverExpenseRow(row);

    }


    addDriverExpenseButton.addEventListener(
        'click',
        createDriverExpenseRow
    );


    driverExpenseRows.addEventListener(
        'change',
        function (event) {

            if (
                event.target.classList.contains(
                    'driver-expense-category'
                )
            ) {

                updateDriverExpenseRow(
                    event.target.closest(
                        '.driver-expense-row'
                    )
                );

            }

        }
    );


    driverExpenseRows.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.driver-expense-remove'
                );

            if (!button) return;

            const rows =
                driverExpenseRows.querySelectorAll(
                    '.driver-expense-row'
                );

            const row =
                button.closest(
                    '.driver-expense-row'
                );

            if (!row) return;

            if (rows.length === 1) {

                row.querySelector(
                    '.driver-expense-category'
                ).value = '';

                row.querySelector(
                    '.driver-expense-amount'
                ).value = '';

                row.querySelector(
                    '.driver-expense-name'
                ).value = '';

                updateDriverExpenseRow(row);

            } else {

                row.remove();

            }

        }
    );


    /*
     * =========================================================
     * DRIVER DELIVERY PAYMENTS
     * =========================================================
     */

    const driverDeliveryPaymentRows =
        document.getElementById(
            'driverDeliveryPaymentRows'
        );


    function createDriverDeliveryRow() {

        const row =
            document.createElement('div');

        row.className =
            'driver-delivery-row';

        row.innerHTML = `

            <div class="form-group driver-delivery-customer-group">

                <label class="form-label">
                    Customer
                </label>

                <input
                    type="text"
                    name="driver_delivery_customer[]"
                    class="form-input driver-delivery-customer"
                    list="shopDeliveryCustomerList"
                    maxlength="100"
                    placeholder="Select or enter customer"
                    autocomplete="off"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Slim
                </label>

                <input
                    type="number"
                    name="driver_delivery_slim[]"
                    class="form-input driver-delivery-slim"
                    min="0"
                    step="1"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Round
                </label>

                <input
                    type="number"
                    name="driver_delivery_round[]"
                    class="form-input driver-delivery-round"
                    min="0"
                    step="1"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Payment
                </label>

                <input
                    type="number"
                    name="driver_delivery_payment[]"
                    class="form-input driver-delivery-payment"
                    min="0"
                    step="0.01"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Price/Gal
                </label>

                <input
                    type="number"
                    name="driver_delivery_price_per_gallon[]"
                    class="form-input driver-delivery-price"
                    min="0"
                    step="5"
                    placeholder="0"
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Method
                </label>

                <select
                    name="driver_delivery_method[]"
                    class="form-input driver-delivery-method"
                >

                    <option
                        value="Cash"
                        selected
                    >
                        Cash
                    </option>

                    <option value="GCash">
                        GCash
                    </option>

                    <option value="Bank Transfer">
                        Bank Transfer
                    </option>

                    <option value="Other">
                        Other
                    </option>

                </select>

            </div>


            <div class="form-group driver-delivery-balance-group">

                <label class="form-label">
                    Balance
                </label>

                <div
                    class="driver-delivery-balance driver-delivery-balance-neutral"
                    aria-live="polite"
                >
                    —
                </div>

            </div>


            <button
                type="button"
                class="driver-delivery-remove"
                title="Remove payment"
                aria-label="Remove payment"
            >
                ×
            </button>

        `;

        driverDeliveryPaymentRows.appendChild(row);

    }


    function addDriverDeliveryRows(count) {

        for (
            let i = 0;
            i < count;
            i++
        ) {

            createDriverDeliveryRow();

        }

    }


    function clearDriverDeliveryRow(row) {

        if (!row) return;

        row.querySelectorAll(
            'input'
        ).forEach(function (input) {

            input.value = '';

        });

        const method =
            row.querySelector(
                '.driver-delivery-method'
            );

        if (method) {
            method.value = 'Cash';
        }

        const balance =
            row.querySelector(
                '.driver-delivery-balance'
            );

        if (balance) {

            balance.className =
                'driver-delivery-balance driver-delivery-balance-neutral';

            balance.textContent =
                '—';

        }

    }


    function removeDriverDeliveryRows(count) {

        for (
            let i = 0;
            i < count;
            i++
        ) {

            const rows =
                driverDeliveryPaymentRows.querySelectorAll(
                    '.driver-delivery-row'
                );

            if (rows.length <= 1) {

                clearDriverDeliveryRow(
                    rows[0]
                );

                return;

            }

            rows[rows.length - 1].remove();

        }

    }


    document
        .querySelectorAll(
            '[data-driver-payment-adjust]'
        )
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const amount =
                        parseInt(
                            button.getAttribute(
                                'data-driver-payment-adjust'
                            ),
                            10
                        );

                    if (
                        Number.isNaN(
                            amount
                        )
                    ) {
                        return;
                    }

                    if (amount > 0) {

                        addDriverDeliveryRows(
                            amount
                        );

                    } else if (amount < 0) {

                        removeDriverDeliveryRows(
                            Math.abs(amount)
                        );

                    }

                }
            );

        });


    driverDeliveryPaymentRows.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.driver-delivery-remove'
                );

            if (!button) return;

            const rows =
                driverDeliveryPaymentRows.querySelectorAll(
                    '.driver-delivery-row'
                );

            const row =
                button.closest(
                    '.driver-delivery-row'
                );

            if (!row) return;

            if (rows.length <= 1) {

                clearDriverDeliveryRow(row);

            } else {

                row.remove();

            }

        }
    );


    /*
     * =========================================================
     * INITIAL UI STATE
     * =========================================================
     */

    expenseRows
        .querySelectorAll(
            '.expense-row'
        )
        .forEach(function (row) {

            updateExpenseRow(row);

        });


    driverExpenseRows
        .querySelectorAll(
            '.driver-expense-row'
        )
        .forEach(function (row) {

            updateDriverExpenseRow(row);

        });


    /*
     * =========================================================
     * RESET
     *
     * Intentionally inactive for this UI rebuild.
     * =========================================================
     */

    const resetDailyClosingButton =
        document.getElementById(
            'resetDailyClosingButton'
        );


    if (resetDailyClosingButton) {

        resetDailyClosingButton.addEventListener(
            'click',
            function () {

                /*
                 * Reset processing will be rebuilt later.
                 */

            }
        );

    }


    /*
     * =========================================================
     * FINALIZE & CLOSE DAY
     *
     * Intentionally inactive for this UI rebuild.
     * =========================================================
     */

    const finalizeDailyClosingButton =
        document.getElementById(
            'finalizeDailyClosingButton'
        );


    if (finalizeDailyClosingButton) {

        finalizeDailyClosingButton.addEventListener(
            'click',
            function () {

                /*
                 * Finalization processing will be rebuilt later.
                 */

            }
        );

    }

})();

</script>

<script src="daily-closing-calculation.js"></script>

</body>

</html>