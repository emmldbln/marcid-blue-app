<?php

/*
 * =========================================================
 * MARCID BLUE DAILY CLOSING
 * =========================================================
 *
 * Main Daily Closing UI.
 *
 * Responsibilities:
 *
 * - Daily Closing page UI
 * - Shop and driver row management
 * - Expense field behavior
 * - Driver delivery management
 * - Net profit visibility
 *
 * Daily calculations are centralized in:
 *
 *     assets/js/daily-closing-calculation.js
 *
 * Data persistence and Daily Closing state are handled by:
 *
 *     assets/js/daily-closing-data.js
 *
 * Customer pricing is handled by:
 *
 *     assets/js/daily-closing-customer-bank.js
 *
 * =========================================================
 */

date_default_timezone_set('Asia/Manila');

require_once '../auth/auth.php';
require_once '../config/database.php';

requireAdmin();


/*
 * =========================================================
 * DISPLAY VALUES
 * =========================================================
 */

$businessDate = date('Y-m-d');

$currentPage = 'daily-closing';

$walkInCustomers = 0;

/*
 * =========================================================
 * WALK-IN PRICE
 * =========================================================
 *
 * The current walk-in price is managed from Settings.
 *
 * Settings source:
 *     app_settings.walk_in_price
 *
 * This keeps Daily Closing synchronized with the
 * configurable business price.
 */

$walkInPrice = 30.00;

    try {

        $stmt = $pdo->prepare(
            "SELECT setting_value
            FROM app_settings
            WHERE setting_key = 'walk_in_price'
            LIMIT 1"
        );

        $stmt->execute();

        $settingValue =
            $stmt->fetchColumn();

        if (
            $settingValue !== false &&
            is_numeric($settingValue) &&
            (float) $settingValue > 0
        ) {

            $walkInPrice =
                round(
                    (float) $settingValue,
                    2
                );
        }

    } catch (Throwable $e) {

        /*
        * Keep the page functional if the settings table
        * has not been created yet.
        *
        * The Settings page creates this table automatically.
        */

        $walkInPrice = 30.00;
    }
    
    /*
    * Make the current Settings price available to
    * the central Daily Closing calculation JavaScript.
    */

    $walkInPriceJs =
        json_encode(
            $walkInPrice,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
$walkInSales = 0.00;

$walkInOtherSales = 0.00;

    /*
    * =========================================================
    * PAYMENT METHODS
    * =========================================================
    *
    * Payment methods are managed from Settings.
    *
    * Only active payment methods are available for
    * new Daily Closing entries.
    *
    * If the Settings table is unavailable, fall back
    * to the original default methods so Daily Closing
    * remains functional.
    */

    $paymentMethods = [];

    try {

        $stmt = $pdo->query(
            "SELECT
                method_id,
                method_name,
                is_active,
                sort_order
            FROM payment_methods
            WHERE is_active = 1
            ORDER BY
                sort_order ASC,
                method_id ASC"
        );

        $paymentMethods =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $paymentMethods = [];
    }


    /*
    * Safety fallback.
    */

    if (empty($paymentMethods)) {

        $paymentMethods = [
            [
                'method_id' => 0,
                'method_name' => 'Cash',
                'is_active' => 1,
                'sort_order' => 1
            ]
        ];
    }


    /*
    * First active method becomes the default
    * for newly created payment rows.
    */

    $defaultPaymentMethod =
        (string) $paymentMethods[0]['method_name'];


    /*
    * Make the configured payment methods available
    * to the Daily Closing JavaScript.
    */

    $paymentMethodsJs =
        json_encode(
            $paymentMethods,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

    $defaultPaymentMethodJs =
        json_encode(
            $defaultPaymentMethod,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

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

    .topbar {
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

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
    .driver-delivery-entry .form-group {
        min-width: 0;
        margin: 0;
    }

    .expense-row .form-input,
    .delivery-payment-row .form-input,
    .delivery-balance,
    .driver-expense-row .form-input,
    .driver-delivery-entry .form-input,
    .driver-delivery-balance {
        width: 100%;
        height: 38px;
        min-height: 38px;
        box-sizing: border-box;
    }

    .expense-row select.form-input,
    .delivery-payment-row select.form-input,
    .driver-expense-row select.form-input,
    .driver-delivery-entry select.form-input {
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
    .driver-expense-remove {
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
    .driver-expense-remove:hover {
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
     * =========================================================
     */

    .driver-delivery-legend-copy {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
        color: var(--text-muted);
        font-size: 11px;
        line-height: 1.4;
    }

    .driver-delivery-legend-copy span {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
    }


    /*
     * =========================================================
     * DRIVER EXPENSE ROWS
     * =========================================================
     */

    .driver-expense-rows {
        display: flex;
        flex-direction: column;
        gap: 0;
        padding: 0 16px;

        background: var(--background);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
    }

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

    .driver-expense-row + .driver-expense-row {
        border-top: 1px solid var(--border);
    }


    /*
     * =========================================================
     * APPROVED DRIVER DELIVERY PAYMENTS UI
     *
     * IMPORTANT:
     *
     * This is the exact approved two-column layout.
     * Do not replace this with the old one-column layout.
     * =========================================================
     */

    .driver-delivery-payment-rows {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        column-gap: 22px;

        padding: 0 18px;

        background: var(--background);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
    }

   .driver-delivery-entry {
        display: grid;

        grid-template-columns:
            minmax(105px, 1.8fr)  /* Customer */
            minmax(32px, 0.45fr)  /* Slim */
            minmax(32px, 0.45fr)  /* Round */
            minmax(58px, 0.8fr)   /* Total */
            minmax(40px, 0.5fr)   /* Per/Gal */
            minmax(82px, 1fr)     /* Payment */
            minmax(72px, 0.8fr)   /* Method */
            30px;                 /* Remove */

        gap: 8px;

        align-items: end;
        min-width: 0;

        padding: 14px 12px 12px;
        margin: 10px 0;

        background: var(--surface);
        border: 2px solid var(--border);
        border-radius: var(--radius-md);

        box-sizing: border-box;
    }

    .driver-delivery-entry:nth-child(n+3) {
        border-top: 2px solid var(--border);
    }

    .driver-delivery-entry:nth-child(even) {
        padding-left: 12px;
        border-left: 2px solid var(--border);
    }

    .driver-delivery-entry .form-group {
        min-width: 0;
        margin: 0;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY FIELD HEIGHT / SCALE
     * =========================================================
     */

    .driver-delivery-entry .form-input,
    .driver-delivery-entry .driver-delivery-balance {
        width: 100%;
        height: 38px;
        min-height: 38px;
        box-sizing: border-box;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY LABELS / PLACEHOLDERS
     * =========================================================
     */

    .driver-delivery-entry .form-label {
        font-size: 12px;
        line-height: 1.2;
        margin-bottom: 4px;
        white-space: nowrap;
    }

    .driver-delivery-entry .form-input {
        width: 100%;
        min-width: 0;
        font-size: 13px;
        box-sizing: border-box;
    }

    .driver-delivery-entry input::placeholder {
        font-size: 12px;
        color: var(--text-muted);
        opacity: 1;
        white-space: nowrap;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY CUSTOMER
     * =========================================================
     */

    .driver-delivery-entry .driver-delivery-customer {
        width: 100%;
        min-width: 0;
        max-width: none;
        font-size: 13px;
        padding: 0 7px;
        text-align: center;
        box-sizing: border-box;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY SLIM / ROUND
     * =========================================================
     */

    .driver-delivery-entry .driver-delivery-slim,
    .driver-delivery-entry .driver-delivery-round {
        width: 100%;
        max-width: none;
        padding: 0 4px;
        text-align: center;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY PAYMENT
     * =========================================================
     */

    .driver-delivery-entry .driver-delivery-payment {
        width: 100%;
        max-width: none;
        padding: 0 6px;
        text-align: center;
        box-sizing: border-box;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY PRICE
     * =========================================================
     */

    .driver-delivery-entry .driver-delivery-price {
        width: 100%;
        max-width: none;
        padding: 0 5px;
        font-size: 13px;
        text-align: center;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY METHOD
     * =========================================================
     */

    .driver-delivery-entry .driver-delivery-method {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    height: 38px;
    box-sizing: border-box;

    font-size: 12px;
    padding: 0 6px;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    }

    .driver-delivery-entry select.form-input {
        line-height: normal;
        text-align: center;
    }


    /*
     * =========================================================
     * DRIVER DELIVERY BALANCE
     * =========================================================
     */

    .driver-delivery-balance {
        width: 100% !important;
        max-width: none !important;

        padding: 0 4px;

        display: flex;
        align-items: center;
        justify-content: center;

        border: 1px solid var(--border);
        border-radius: var(--radius-sm);

        background: var(--surface);

        font-size: 11px;
        font-weight: 700;
        line-height: 1.2;

        white-space: nowrap;
        overflow: hidden;

        text-align: center;
    }

    .driver-delivery-balance-neutral {
        color: var(--text-muted);
        font-weight: 500;
    }

    .driver-delivery-balance-paid {
        color: var(--success);
        background: var(--success-light);
        border-color: rgba(46,155,91,.18);
    }

    .driver-delivery-balance-due {
        color: var(--warning);
        background: var(--warning-light);
        border-color: rgba(229,154,36,.18);
    }

    .driver-delivery-balance-unpaid {
        color: var(--danger);
        background: var(--danger-light);
        border-color: rgba(217,83,79,.18);
    }

    .driver-delivery-balance-overpaid {
        color: var(--primary-dark);
        background: var(--primary-light);
        border-color: rgba(22,135,201,.18);
    }


    /*
     * =========================================================
     * DRIVER DELIVERY REMOVE BUTTON
     * =========================================================
     */

    .driver-delivery-remove {
        width: 30px;
        height: 38px;
        min-width: 30px;

        margin: 0;
        padding: 0;

        display: flex;
        align-items: center;
        justify-content: center;
        align-self: end;
        justify-self: start;

        box-sizing: border-box;

        border: 1px solid var(--border);
        border-radius: var(--radius-sm);

        background: var(--surface);
        color: var(--danger);

        font-size: 18px;
        font-weight: 600;
        line-height: 1;

        cursor: pointer;
    }

    .driver-delivery-remove:hover {
        background: var(--danger-light);
        border-color: var(--danger);
    }


   /* =========================================================
   DRIVER DELIVERY STATUS OUTLINE
   ========================================================= */

    .driver-delivery-entry:has(
        .driver-delivery-balance-paid
    ) {
        border: 2px solid var(--success);
    }

    .driver-delivery-entry:has(
        .driver-delivery-balance-due
    ) {
        border: 2px solid var(--warning);
    }

    .driver-delivery-entry:has(
        .driver-delivery-balance-unpaid
    ) {
        border: 2px solid var(--danger);
    }

    .driver-delivery-entry:has(
        .driver-delivery-balance-overpaid
    ) {
        border: 2px solid var(--primary-dark);
    }


    /*
     * =========================================================
     * DRIVER DELIVERY ADDED ROWS
     * =========================================================
     */

    .driver-delivery-entry-added .form-label {
        display: none;
    }

    .driver-delivery-entry-added {
        padding-top: 9px;
        padding-bottom: 9px;
    }


    /*
     * =========================================================
     * BUTTON INTERACTION
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
        background: var(
            --secondary-dark,
            var(--success-dark, #247d49)
        ) !important;

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
        .driver-expense-row:not(:first-child) .form-label {
            display: none;
        }

    }


    /*
     * =========================================================
     * RESPONSIVE - 1200PX
     * =========================================================
     */

    @media (max-width: 1200px) {

        .delivery-payment-row {
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

        .driver-delivery-entry:nth-child(even) {
            padding-left: 12px;
            border-left: 2px solid var(--border);
        }

        .driver-delivery-entry:nth-child(n+2) {
            border-top: 2px solid var(--border);
        }

    }

    @media (max-width: 1100px) and (min-width: 901px) {

    .driver-delivery-entry .form-label {
        font-size: 11px;
    }

    .driver-delivery-entry .form-input {
        font-size: 12px;
    }

    .driver-delivery-entry input::placeholder {
        font-size: 11px;
    }

    .driver-delivery-entry .driver-delivery-customer {
        font-size: 12px;
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
         * APPROVED DRIVER DELIVERY UI
         */

        .driver-panel-header {
            flex-direction: column;
            align-items: stretch;
        }

        .driver-payment-controls {
            width: 100%;
        }

        .driver-payment-controls .btn {
            flex: 1;
        }

        .driver-delivery-entry {
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
        }

        .driver-delivery-entry
        .driver-delivery-customer-group {
            grid-column: 1 / -1;
        }

        .driver-delivery-entry
        .driver-delivery-balance-group {
            grid-column: span 2;
        }

        .driver-delivery-remove {
            grid-column: 4;
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
         * OTHER ROWS
         */

        .expense-row,
        .delivery-payment-row,
        .driver-expense-row {
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
         * DRIVER EXPENSE
         */

        .driver-expense-row
        .driver-expense-name-group {
            grid-column: auto;
        }


        /*
         * APPROVED DRIVER DELIVERY
         */

        .driver-delivery-entry {
            grid-template-columns:
                1fr 1fr;
        }

        .driver-delivery-entry
        .driver-delivery-customer-group,
        .driver-delivery-entry
        .driver-delivery-balance-group {
            grid-column: auto;
        }

        .driver-delivery-remove {
            grid-column: auto;
            justify-self: start;
        }


        /*
         * REMOVE BUTTONS
         */

        .expense-remove,
        .delivery-payment-remove,
        .driver-expense-remove {
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


    /*
     * =========================================================
     * RESPONSIVE - 550PX
     * =========================================================
     */

    @media (max-width: 550px) {

        .driver-delivery-payment-rows {
            padding: 0 10px;
        }

        .driver-delivery-entry {
            grid-template-columns:
                1fr 1fr;
        }

        .driver-delivery-entry
        .driver-delivery-customer-group,
        .driver-delivery-entry
        .driver-delivery-balance-group {
            grid-column: auto;
        }

        .driver-delivery-remove {
            grid-column: auto;
            justify-self: start;
        }

    }

    /* =========================================================
   DRIVER DELIVERY PAYMENT OUTSIDE GLOW
   ========================================================= */

    .driver-delivery-entry {
        position: relative;
        z-index: 0;
    }

    .driver-delivery-entry::before {
        content: "";
        position: absolute;
        inset: -6px;

        border-radius: 14px;

        pointer-events: none;

        opacity: 0;

        z-index: -1;

        transition:
            opacity 0.2s ease,
            box-shadow 0.2s ease;
    }


    /* =========================================================
    PAID
    ========================================================= */

    .driver-delivery-entry:has(
        .driver-delivery-balance-paid
    )::before {
        opacity: 1;

        box-shadow:
            0 0 8px rgba(34, 197, 94, 0.45),
            0 0 18px rgba(34, 197, 94, 0.28);
    }


    /* =========================================================
    DUE
    ========================================================= */

    .driver-delivery-entry:has(
        .driver-delivery-balance-due
    )::before {
        opacity: 1;

        box-shadow:
            0 0 8px rgba(245, 158, 11, 0.45),
            0 0 18px rgba(245, 158, 11, 0.28);
    }


    /* =========================================================
    UNPAID
    ========================================================= */

    .driver-delivery-entry:has(
        .driver-delivery-balance-unpaid
    )::before {
        opacity: 1;

        box-shadow:
            0 0 8px rgba(239, 68, 68, 0.45),
            0 0 18px rgba(239, 68, 68, 0.28);
    }


    /* =========================================================
    OVERPAID
    ========================================================= */

    .driver-delivery-entry:has(
        .driver-delivery-balance-overpaid
    )::before {
        opacity: 1;

        box-shadow:
            0 0 8px rgba(59, 130, 246, 0.45),
            0 0 18px rgba(59, 130, 246, 0.28);
    }

    /* =========================================================
    CURRENT DEBT PANEL
    ========================================================= */

    .current-debt-tab {
        position: fixed;
        top: 50%;
        right: 0;
        z-index: 9990;

        transform: translateY(-50%);

        display: flex;
        align-items: center;
        justify-content: center;

        width: 42px;
        min-height: 170px;

        padding: 14px 8px;

        border: 1px solid #b91c1c;
        border-right: 0;
        border-radius: 12px 0 0 12px;

        background: #dc2626;
        color: #ffffff;

        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);

        cursor: pointer;

        transition:
            background 0.15s ease,
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }

    .current-debt-tab:hover {
        background: #b91c1c;
        border-color: #991b1b;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.24);
    }

    .current-debt-tab:active {
        background: #991b1b;
    }

    .current-debt-tab.is-hidden {
        display: none;
    }

    .current-debt-tab-content {
        display: flex;
        align-items: center;
        justify-content: center;

        writing-mode: vertical-rl;
        transform: rotate(180deg);

        white-space: nowrap;
    }

    .current-debt-tab-label {
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.04em;
    }


    /* =========================================================
    CURRENT DEBT DRAWER
    ========================================================= */

    .current-debt-drawer {
        position: fixed;
        top: 0;
        right: -430px;
        z-index: 9989;

        width: min(430px, 92vw);
        height: 100vh;

        display: flex;
        flex-direction: column;

        box-sizing: border-box;

        background: var(--surface);
        border-left: 1px solid var(--border);

        box-shadow: -8px 0 30px rgba(0, 0, 0, 0.12);

        transition: right 0.25s ease;
    }

    .current-debt-drawer.open {
        right: 0;
    }

    .current-debt-drawer-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;

        padding: 22px 20px;

        border-bottom: 1px solid var(--border);
    }

    .current-debt-drawer-title {
        color: var(--text);
        font-size: 18px;
        font-weight: 700;
        line-height: 1.3;
    }

    .current-debt-drawer-subtitle {
        margin-top: 4px;

        color: var(--text-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .current-debt-close {
        width: 34px;
        height: 34px;
        min-width: 34px;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 0;

        border: 1px solid var(--border);
        border-radius: var(--radius-sm);

        background: var(--surface);
        color: var(--text-muted);

        font-size: 20px;
        line-height: 1;

        cursor: pointer;
    }

    .current-debt-close:hover {
        color: var(--danger);
        border-color: var(--danger);
        background: var(--danger-light);
    }

    .current-debt-drawer-summary {
        padding: 18px 20px;

        border-bottom: 1px solid var(--border);
    }

    .current-debt-drawer-summary-label {
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .current-debt-drawer-summary-value {
        margin-top: 4px;

        color: var(--danger);
        font-size: 24px;
        font-weight: 800;
    }

    .current-debt-list {
        flex: 1;

        overflow-y: auto;

        padding: 12px 20px 24px;
    }

    .current-debt-empty {
        padding: 40px 10px;

        text-align: center;

        color: var(--text-muted);
        font-size: 13px;
        line-height: 1.6;
    }

    .current-debt-item {
        padding: 14px 0;

        border-bottom: 1px solid var(--border);
    }

    .current-debt-item:last-child {
        border-bottom: 0;
    }

    .current-debt-item-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .current-debt-credit-original {
        opacity: 0.6;
    }

    .current-debt-credit-new {
        opacity: 1;
    }

    .current-debt-credit-draft {
        opacity: 1;
    }

    .current-debt-customer {
        color: var(--text);
        font-size: 14px;
        font-weight: 700;
    }

    .current-debt-amount {
        color: var(--danger);
        font-size: 14px;
        font-weight: 800;
        white-space: nowrap;
    }

        .current-debt-item-paid .current-debt-customer,
    .current-debt-original-paid {
        text-decoration: line-through;
        opacity: 0.6;
    }

    .current-debt-item-details {
        margin-top: 5px;

        color: var(--text-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .current-debt-credit {
        color: var(--success);
    }

    .current-debt-loading {
        padding: 30px 10px;

        text-align: center;

        color: var(--text-muted);
        font-size: 13px;
    }


    /* =========================================================
    CURRENT DEBT OVERLAY
    ========================================================= */

    .current-debt-overlay {
        position: fixed;
        inset: 0;
        z-index: 9988;

        display: none;

        background: rgba(0, 0, 0, 0.18);
    }

    .current-debt-overlay.open {
        display: block;
    }


    @media (max-width: 650px) {

        .current-debt-tab {
            width: 38px;
            min-height: 145px;
        }

        .current-debt-drawer {
            width: 92vw;
            right: -92vw;
        }

        .current-debt-drawer.open {
            right: 0;
        }

    }

    </style>

</head>


<body>

<!-- =========================================================
     CURRENT DEBT
     ========================================================= -->

<div
    class="current-debt-overlay"
    id="currentDebtOverlay"
></div>


<button
    type="button"
    class="current-debt-tab"
    id="currentDebtTab"
    aria-label="Open Current Debt"
    title="Open Current Debt"
>
    <span class="current-debt-tab-content">

        <span class="current-debt-tab-label">
            CURRENT DEBT
        </span>

    </span>
</button>


<aside
    class="current-debt-drawer"
    id="currentDebtDrawer"
    aria-hidden="true"
>

    <div class="current-debt-drawer-header">

        <div>

            <div class="current-debt-drawer-title">
                Current Debt
            </div>

            <div class="current-debt-drawer-subtitle">
                Unsettled customer balances across previous
                and today's transactions.
            </div>

        </div>


        <button
            type="button"
            class="current-debt-close"
            id="currentDebtClose"
            aria-label="Close Current Debt"
        >
            ×
        </button>

    </div>


    <div class="current-debt-drawer-summary">

        <div class="current-debt-drawer-summary-label">
            Total Outstanding Debt
        </div>

        <div
            class="current-debt-drawer-summary-value"
            id="currentDebtTotal"
        >
            ₱0.00
        </div>

    </div>


    <div
        class="current-debt-list"
        id="currentDebtList"
    >

        <div class="current-debt-loading">
            Loading current debt...
        </div>

    </div>

</aside>

<div class="app">

    <?php require_once '../includes/sidebar.php'; ?>

    <!-- =====================================================
         MAIN CONTENT
         ===================================================== -->

    <main class="main">


        <!-- =================================================
             TOP BAR
             ================================================= -->

        <header class="topbar">

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


                <!-- DAILY CLOSING ACTIONS -->

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


                <!-- NET PROFIT -->

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


                <!-- WALK-IN -->

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


                <!-- DELIVERIES -->

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


                <!-- DAILY STATUS -->

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
                                        Per/Gal is entered once for a
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
                                            Per/Gal
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


                        <datalist id="shopDeliveryCustomerList"></datalist>

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


                        <div class="driver-summary-field">

                            <div class="summary-label">
                                Actual Sales of Delivery
                            </div>


                            <div
                                class="summary-value"
                                id="driverTotalDeliverySales"
                            >
                                ₱0.00
                            </div>

                        </div>


                        <div class="driver-summary-field">

                            <div class="summary-label">
                                Expected Sales for Today
                            </div>


                            <div
                                class="summary-value"
                                id="driverTotalExpectedMoney"
                            >
                                ₱0.00
                            </div>

                        </div>


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
                         APPROVED DRIVER DELIVERY PAYMENTS
                         ================================================= -->

                    <div class="driver-panel-section">

                        <div class="driver-panel-header">


                            <div>

                                <div class="driver-panel-title">
                                    Driver's Delivery Payments
                                </div>


                                <div class="driver-panel-subtitle">
                                    Same customer, quantity, Per/Gal,
                                    payment, method, and balance
                                    functionality as Shop Delivery Payments.
                                </div>


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


                            <!-- =================================================
                                 APPROVED CONTROLS
                                 ================================================= -->

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
                                    data-driver-payment-adjust="2"
                                >
                                    +2
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
                                    data-driver-payment-adjust="-2"
                                >
                                    −2
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


                        <!-- =================================================
                             TWO-COLUMN DRIVER DELIVERY ROWS
                             ================================================= -->

                        <div
                            id="driverDeliveryPaymentRows"
                            class="driver-delivery-payment-rows"
                        >


                            <!-- =================================================
                                 FIRST CUSTOMER
                                 ================================================= -->

                            <div class="driver-delivery-entry driver-delivery-row">


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
                                        Per/Gal
                                    </label>


                                    <input
                                        type="number"
                                        name="driver_delivery_price_per_gallon[]"
                                        class="form-input driver-delivery-price"
                                        min="0"
                                        max="99"
                                        step="5"
                                        placeholder="0"
                                        maxlength="2"
                                        inputmode="numeric"
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
                                    title="Remove customer"
                                    aria-label="Remove customer"
                                >
                                    ×
                                </button>

                            </div>


                            <!-- =================================================
                                 SECOND CUSTOMER
                                 ================================================= -->

                            <div class="driver-delivery-entry driver-delivery-row">


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
                                        Per/Gal
                                    </label>


                                    <input
                                        type="number"
                                        name="driver_delivery_price_per_gallon[]"
                                        class="form-input driver-delivery-price"
                                        min="0"
                                        max="99"
                                        step="5"
                                        placeholder="0"
                                        maxlength="2"
                                        inputmode="numeric"
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
                                    title="Remove customer"
                                    aria-label="Remove customer"
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
     OOP UI CONTROLLERS
     ========================================================= -->

<script>

'use strict';


/*
 * =========================================================
 * NET PROFIT VISIBILITY CONTROLLER
 * =========================================================
 */

class NetProfitController {

    constructor() {

        this.toggle =
            document.querySelector(
                '.net-profit-toggle'
            );

        this.hiddenValue =
            document.querySelector(
                '.net-profit-hidden'
            );

        this.visibleValue =
            document.querySelector(
                '.net-profit-visible'
            );

        this.openEye =
            document.querySelector(
                '.eye-icon-open'
            );

        this.closedEye =
            document.querySelector(
                '.eye-icon-closed'
            );

    }


    init() {

        if (
            !this.toggle ||
            !this.hiddenValue ||
            !this.visibleValue ||
            !this.openEye ||
            !this.closedEye
        ) {
            return;
        }


        this.toggle.addEventListener(
            'click',
            () => this.toggleVisibility()
        );

    }


    toggleVisibility() {

        const isHidden =
            this.hiddenValue.style.display !== 'none';


        if (isHidden) {

            this.show();

        } else {

            this.hide();

        }

    }


    show() {

        this.hiddenValue.style.display =
            'none';

        this.visibleValue.style.display =
            'inline';

        this.openEye.style.display =
            'block';

        this.closedEye.style.display =
            'none';

        this.toggle.setAttribute(
            'aria-label',
            'Hide net profit'
        );

        this.toggle.setAttribute(
            'title',
            'Hide net profit'
        );

    }


    hide() {

        this.hiddenValue.style.display =
            'inline';

        this.visibleValue.style.display =
            'none';

        this.openEye.style.display =
            'none';

        this.closedEye.style.display =
            'block';

        this.toggle.setAttribute(
            'aria-label',
            'Show net profit'
        );

        this.toggle.setAttribute(
            'title',
            'Show net profit'
        );

    }

}


/*
 * =========================================================
 * BASE ROW CONTROLLER
 * =========================================================
 */

class BaseRowController {

    constructor(container, rowSelector) {

        this.container =
            container;

        this.rowSelector =
            rowSelector;

    }


    getRows() {

        if (!this.container) {
            return [];
        }

        return Array.from(
            this.container.querySelectorAll(
                this.rowSelector
            )
        );

    }


    getRowCount() {

        return this.getRows().length;

    }


    getLastRow() {

        const rows =
            this.getRows();

        return rows[rows.length - 1] || null;

    }


    removeRow(row) {

        if (row) {
            row.remove();
        }

    }

}


/*
 * =========================================================
 * EXPENSE CONTROLLER
 * =========================================================
 */

class ExpenseController extends BaseRowController {

    constructor() {

        super(
            document.getElementById('expenseRows'),
            '.expense-row'
        );

        this.addButton =
            document.getElementById(
                'addExpenseButton'
            );

    }


    init() {

        if (!this.container) {
            return;
        }


        this.bindEvents();


        this.getRows().forEach(
            row => this.updateRow(row)
        );

    }


    bindEvents() {

        if (this.addButton) {

            this.addButton.addEventListener(
                'click',
                () => this.addRow()
            );

        }


        this.container.addEventListener(
            'change',
            event => {

                if (
                    event.target.classList.contains(
                        'expense-category'
                    )
                ) {

                    this.updateRow(
                        event.target.closest(
                            '.expense-row'
                        )
                    );

                }

            }
        );


        this.container.addEventListener(
            'click',
            event => {

                const button =
                    event.target.closest(
                        '.expense-remove'
                    );

                if (!button) {
                    return;
                }

                this.removeOrClear(
                    button.closest(
                        '.expense-row'
                    )
                );

            }
        );

    }


    updateRow(row) {

        if (!row) {
            return;
        }


        const category =
            row.querySelector(
                '.expense-category'
            );

        const name =
            row.querySelector(
                '.expense-name'
            );

        const label =
            row.querySelector(
                '.expense-name-label'
            );


        if (
            !category ||
            !name ||
            !label
        ) {
            return;
        }


        const needsName =
            category.value === 'Cash Advance' ||
            category.value === 'Others';


        name.disabled =
            !needsName;

        name.required =
            needsName;


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


    createRow() {

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


        return row;

    }


    addRow() {

        const row =
            this.createRow();

        this.container.appendChild(
            row
        );

        this.updateRow(row);

    }


    clearRow(row) {

        if (!row) {
            return;
        }


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


        this.updateRow(row);

    }


    removeOrClear(row) {

        if (!row) {
            return;
        }


        if (this.getRowCount() <= 1) {

            this.clearRow(row);

            return;

        }


        this.removeRow(row);

    }

}


/*
 * =========================================================
 * SHOP DELIVERY PAYMENT CONTROLLER
 * =========================================================
 */

class ShopDeliveryPaymentController
    extends BaseRowController {

    constructor() {

        super(
            document.getElementById(
                'deliveryPaymentRows'
            ),
            '.delivery-payment-row'
        );


        this.addButton =
            document.getElementById(
                'addDeliveryPaymentButton'
            );

    }


    init() {

        if (!this.container) {
            return;
        }


        this.bindEvents();

    }


    bindEvents() {

        if (this.addButton) {

            this.addButton.addEventListener(
                'click',
                () => this.addRow()
            );

        }


        this.container.addEventListener(
            'click',
            event => {

                const button =
                    event.target.closest(
                        '.delivery-payment-remove'
                    );

                if (!button) {
                    return;
                }


                this.removeOrClear(
                    button.closest(
                        '.delivery-payment-row'
                    )
                );

            }
        );

    }


    createRow() {

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
                    Per/Gal
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


        return row;

    }


    addRow() {

        this.container.appendChild(
            this.createRow()
        );

    }


    clearRow(row) {

        if (!row) {
            return;
        }


        row.querySelectorAll(
            'input'
        ).forEach(
            input => {
                input.value = '';
            }
        );


        const method =
            row.querySelector(
                '.delivery-method'
            );

        if (method) {
            method.value =
                window.MARCID_BLUE_DEFAULT_PAYMENT_METHOD ||
                'Cash';
        }


        const balance =
            row.querySelector(
                '.delivery-balance'
            );

        if (balance) {

            balance.className =
                'delivery-balance delivery-balance-neutral';

            balance.textContent =
                '—';

        }

    }


    removeOrClear(row) {

        if (!row) {
            return;
        }


        if (this.getRowCount() <= 1) {

            this.clearRow(row);

            return;

        }


        this.removeRow(row);

    }

}


/*
 * =========================================================
 * DRIVER EXPENSE CONTROLLER
 * =========================================================
 */

class DriverExpenseController
    extends BaseRowController {

    constructor() {

        super(
            document.getElementById(
                'driverExpenseRows'
            ),
            '.driver-expense-row'
        );


        this.addButton =
            document.getElementById(
                'addDriverExpenseButton'
            );

    }


    init() {

        if (!this.container) {
            return;
        }


        this.bindEvents();


        this.getRows().forEach(
            row => this.updateRow(row)
        );

    }


    bindEvents() {

        if (this.addButton) {

            this.addButton.addEventListener(
                'click',
                () => this.addRow()
            );

        }


        this.container.addEventListener(
            'change',
            event => {

                if (
                    event.target.classList.contains(
                        'driver-expense-category'
                    )
                ) {

                    this.updateRow(
                        event.target.closest(
                            '.driver-expense-row'
                        )
                    );

                }

            }
        );


        this.container.addEventListener(
            'click',
            event => {

                const button =
                    event.target.closest(
                        '.driver-expense-remove'
                    );

                if (!button) {
                    return;
                }


                this.removeOrClear(
                    button.closest(
                        '.driver-expense-row'
                    )
                );

            }
        );

    }


    updateRow(row) {

        if (!row) {
            return;
        }


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


        if (
            !category ||
            !name ||
            !label
        ) {
            return;
        }


        const needsName =
            category.value === 'Cash Advance' ||
            category.value === 'Others';


        name.disabled =
            !needsName;

        name.required =
            needsName;


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


    createRow() {

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


        return row;

    }


    addRow() {

        const row =
            this.createRow();

        this.container.appendChild(
            row
        );

        this.updateRow(row);

    }


    clearRow(row) {

        if (!row) {
            return;
        }


        const category =
            row.querySelector(
                '.driver-expense-category'
            );

        const amount =
            row.querySelector(
                '.driver-expense-amount'
            );

        const name =
            row.querySelector(
                '.driver-expense-name'
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


        this.updateRow(row);

    }


    removeOrClear(row) {

        if (!row) {
            return;
        }


        if (this.getRowCount() <= 1) {

            this.clearRow(row);

            return;

        }


        this.removeRow(row);

    }

}


/*
 * =========================================================
 * DRIVER DELIVERY PAYMENT CONTROLLER
 *
 * Exact approved UI behavior.
 * =========================================================
 */

class DriverDeliveryPaymentController
    extends BaseRowController {

    constructor() {

        super(
            document.getElementById(
                'driverDeliveryPaymentRows'
            ),
            '.driver-delivery-entry'
        );


        this.adjustButtons =
            document.querySelectorAll(
                '[data-driver-payment-adjust]'
            );

    }


    init() {

        if (!this.container) {
            return;
        }


        this.bindEvents();

    }


    bindEvents() {

        /*
         * + / - row controls.
         */

        this.adjustButtons.forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => this.handleAdjustment(
                        button
                    )
                );

            }
        );


        /*
         * Individual X buttons.
         */

        this.container.addEventListener(
            'click',
            event => {

                const button =
                    event.target.closest(
                        '.driver-delivery-remove'
                    );

                if (!button) {
                    return;
                }


                const row =
                    button.closest(
                        '.driver-delivery-entry'
                    );

                this.removeRowOrClear(
                    row
                );

            }
        );


        /*
         * Per/Gal input restriction.
         */

        this.container.addEventListener(
            'input',
            event => {

                if (
                    event.target.classList.contains(
                        'driver-delivery-price'
                    )
                ) {

                    this.sanitizePrice(
                        event.target
                    );

                }

            }
        );

    }


    createEntry() {

        const entry =
            document.createElement('div');

        entry.className =
            'driver-delivery-entry driver-delivery-row driver-delivery-entry-added';


        entry.innerHTML = `

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
                    Per/Gal
                </label>

                <input
                    type="number"
                    name="driver_delivery_price_per_gallon[]"
                    class="form-input driver-delivery-price"
                    min="0"
                    max="99"
                    step="5"
                    placeholder="0"
                    maxlength="2"
                    inputmode="numeric"
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
                title="Remove customer"
                aria-label="Remove customer"
            >
                ×
            </button>

        `;


        return entry;

    }


    handleAdjustment(button) {

        const amount =
            parseInt(
                button.dataset.driverPaymentAdjust,
                10
            ) || 0;


        if (amount > 0) {

            this.addEntries(
                amount
            );

            return;

        }


        if (amount < 0) {

            this.removeEntries(
                Math.abs(amount)
            );

        }

    }


    addEntries(count) {

        const fragment =
            document.createDocumentFragment();


        for (
            let i = 0;
            i < count;
            i++
        ) {

            fragment.appendChild(
                this.createEntry()
            );

        }


        this.container.appendChild(
            fragment
        );

    }


    removeEntries(count) {

        const currentCount =
            this.getRowCount();


        const removable =
            Math.min(
                count,
                Math.max(
                    0,
                    currentCount - 1
                )
            );


        for (
            let i = 0;
            i < removable;
            i++
        ) {

            const lastRow =
                this.getLastRow();


            if (!lastRow) {
                break;
            }


            this.removeRow(
                lastRow
            );

        }

    }


    removeRowOrClear(row) {

        if (!row) {
            return;
        }


        /*
         * Never remove the final customer row.
         */

        if (
            this.getRowCount() <= 1
        ) {

            this.clearEntry(
                row
            );

            return;

        }


        this.removeRow(
            row
        );

    }


    clearEntry(entry) {

        if (!entry) {
            return;
        }


        entry.querySelectorAll(
            'input'
        ).forEach(
            input => {
                input.value = '';
            }
        );


        const method =
            entry.querySelector(
                '.driver-delivery-method'
            );


        if (method) {
            method.value =
                window.MARCID_BLUE_DEFAULT_PAYMENT_METHOD ||
                'Cash';
        }


        const balance =
            entry.querySelector(
                '.driver-delivery-balance'
            );


        if (balance) {

            balance.className =
                'driver-delivery-balance driver-delivery-balance-neutral';

            balance.textContent =
                '—';

        }


        entry.classList.remove(
            'status-paid',
            'status-due',
            'status-unpaid',
            'status-overpaid'
        );

    }


    sanitizePrice(input) {

        if (!input) {
            return;
        }


        const sanitized =
            input.value
                .replace(/\D/g, '')
                .slice(0, 2);


        if (
            input.value !==
            sanitized
        ) {

            input.value =
                sanitized;

        }

    }

}


    /*
    * =========================================================
    * DAILY CLOSING UI APPLICATION
    * =========================================================
    *
    * Main OOP controller.
    *
    * This initializes every UI controller once.
    * =========================================================
    */
    /*
    * =========================================================
    * PAYMENT METHOD CONFIGURATION
    * =========================================================
    *
    * Populate every Daily Closing payment-method select
    * from the active methods configured in Settings.
    *
    * This runs before the Daily Closing controllers are
    * initialized, so newly created rows inherit the same
    * configured options.
    * =========================================================
    */

    function initializePaymentMethods() {

        const methods =
            Array.isArray(
                window.MARCID_BLUE_PAYMENT_METHODS
            )
                ? window.MARCID_BLUE_PAYMENT_METHODS
                : [];


        const defaultMethod =
            window.MARCID_BLUE_DEFAULT_PAYMENT_METHOD ||
            'Cash';


        const selects =
            document.querySelectorAll(
                '.delivery-method, .driver-delivery-method'
            );


        if (!selects.length) {
            return;
        }


        selects.forEach(
            select => {

                const currentValue =
                    select.value;


                select.innerHTML =
                    '';


                methods.forEach(
                    method => {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            method.method_name;


                        option.textContent =
                            method.method_name;


                        option.dataset.methodId =
                            method.method_id;


                        option.selected =
                            method.method_name ===
                            (
                                currentValue ||
                                defaultMethod
                            );


                        select.appendChild(
                            option
                        );

                    }
                );


                /*
                * If the current value no longer exists
                * in Settings, use the first active method.
                */

                if (
                    !Array.from(
                        select.options
                    ).some(
                        option =>
                            option.value ===
                            currentValue
                    )
                ) {

                    select.value =
                        defaultMethod;

                }

            }
        );
    }


class DailyClosingUI {

    constructor() {

        this.netProfit =
            new NetProfitController();


        this.expenses =
            new ExpenseController();


        this.shopDeliveryPayments =
            new ShopDeliveryPaymentController();


        this.driverExpenses =
            new DriverExpenseController();


        this.driverDeliveryPayments =
            new DriverDeliveryPaymentController();

    }


    init() {

        this.netProfit.init();

        this.expenses.init();

        this.shopDeliveryPayments.init();

        this.driverExpenses.init();

        this.driverDeliveryPayments.init();

    }

}


/*
 * =========================================================
 * APPLICATION STARTUP
 * =========================================================
 */

document.addEventListener(
    'DOMContentLoaded',
    () => {

        /*
         * Load payment methods from Settings
         * before initializing Daily Closing.
         */

        initializePaymentMethods();


        const app =
            new DailyClosingUI();

        app.init();

    }
);

</script>

<script>
    window.MARCID_BLUE_WALK_IN_PRICE =
        <?= $walkInPriceJs ?>;

    window.MARCID_BLUE_PAYMENT_METHODS =
        <?= $paymentMethodsJs ?>;

    window.MARCID_BLUE_DEFAULT_PAYMENT_METHOD =
        <?= $defaultPaymentMethodJs ?>;
</script>

<!-- =========================================================
     CENTRAL DAILY CLOSING CALCULATION SCRIPT
     
     This remains the source of truth for:
     
     - Shop calculation
     - Walk-in quantity
     - Additional sales
     - Shop delivery balances
     - Driver delivery balances
     - Driver quantity
     - Driver delivery sales
     - Driver expected money
     - Driver expenses
     - Driver remittance
     - Dashboard totals
     ========================================================= -->

<script src="../assets/js/daily-closing-calculation.js"></script>
<script src="../assets/js/daily-closing-data.js"></script>
<script src="../assets/js/daily-closing-customer-bank.js"></script>

</body>

</html>