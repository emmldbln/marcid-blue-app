<?php

date_default_timezone_set('Asia/Manila');
require_once '../auth/auth.php';
requireAdmin();

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Driver's Delivery Payments - 2 Column Test</title>

<link rel="stylesheet" href="../assets/css/app.css">

<style>

/* =========================================================
   DRIVER DELIVERY PAYMENTS - 2 COLUMN TEST
   ========================================================= */

.driver-deliveries-card{
    margin-top:24px
}

.driver-panel-section{
    margin-top:28px;
    padding-top:24px;
    border-top:1px solid var(--border)
}

.driver-panel-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:20px;
    margin-bottom:16px
}

.driver-panel-title{
    color:var(--text);
    font-size:16px;
    font-weight:700;
    line-height:1.4
}

.driver-panel-subtitle{
    margin-top:4px;
    color:var(--text-muted);
    font-size:13px;
    line-height:1.5
}


/* =========================================================
   LEGEND / CONTROLS
   ========================================================= */

.driver-delivery-legend-copy{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:10px;
    margin-top:8px;
    color:var(--text-muted);
    font-size:11px;
    line-height:1.4
}

.driver-delivery-legend-copy span{
    display:inline-flex;
    align-items:center;
    white-space:nowrap
}

.driver-delivery-legend-copy .legend-label{
    color:var(--text);
    font-weight:700
}

.delivery-status-item{
    font-weight:600
}

.delivery-status-paid{
    color:var(--success)
}

.delivery-status-due{
    color:var(--warning)
}

.delivery-status-unpaid{
    color:var(--danger)
}

.delivery-status-overpaid{
    color:var(--primary-dark)
}

.driver-payment-controls{
    display:flex;
    flex-wrap:wrap;
    gap:8px
}

.driver-payment-controls .btn{
    min-width:48px;
    height:38px;
    padding:0 12px
}


/* =========================================================
   TWO CUSTOMERS PER ROW
   ========================================================= */

.driver-delivery-payment-rows{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));

    /*
     * KEEP THE MIDDLE GAP AT 22PX
     */
    column-gap:22px;

    padding:0 18px;
    background:var(--background);
    border:1px solid var(--border);
    border-radius:var(--radius-md)
}

.driver-delivery-entry{
    display:grid;

    /*
     * Customer
     * Slim
     * Round
     * Payment
     * Price/Gal
     * Method
     * Balance
     * Remove
     */
    grid-template-columns:
        minmax(0,1fr)
        44px
        44px
        84px
        48px
        76px
        78px
        30px;

    /*
     * Internal field spacing.
     */
    gap:9px;

    align-items:end;
    min-width:0;

    /*
     * Keep rectangle dimensions visually the same.
     */
    padding:14px 12px 12px;
    margin:10px 0;

    background:var(--surface);
    border:2px solid var(--border);
    border-radius:var(--radius-md);
    box-sizing:border-box
}

.driver-delivery-entry:nth-child(n+3){
    border-top:2px solid var(--border)
}

.driver-delivery-entry:nth-child(even){
    padding-left:12px;
    border-left:2px solid var(--border)
}

.driver-delivery-entry .form-group{
    min-width:0;
    margin:0
}


/* =========================================================
   FIELD HEIGHT / SCALE
   ========================================================= */

.driver-delivery-entry .form-input,
.driver-delivery-entry .driver-delivery-balance{
    width:100%;
    height:38px;
    min-height:38px;
    box-sizing:border-box
}


/* =========================================================
   FIELD LABELS / PLACEHOLDERS
   ========================================================= */

.driver-delivery-entry .form-label{
    font-size:12px;
    line-height:1.2;
    margin-bottom:4px;
    white-space:nowrap
}

.driver-delivery-entry .form-input{
    font-size:13px
}

/*
 * Suggestions remain visible on every row.
 */
.driver-delivery-entry input::placeholder{
    font-size:12px;
    color:var(--text-muted);
    opacity:1
}


/* =========================================================
   CUSTOMER
   ========================================================= */

.driver-delivery-entry .driver-delivery-customer{
    font-size:13px;
    padding:0 7px;
    text-align:center
}


/* =========================================================
   SLIM / ROUND
   ========================================================= */

.driver-delivery-entry .driver-delivery-slim,
.driver-delivery-entry .driver-delivery-round{
    width:44px;
    max-width:44px;
    padding:0 4px;
    text-align:center
}


/* =========================================================
   PAYMENT
   ========================================================= */

.driver-delivery-entry .driver-delivery-payment{
    width:84px;
    max-width:84px;
    padding:0 6px;
    text-align:center
}


/* =========================================================
   PRICE / GAL
   ========================================================= */

.driver-delivery-entry .driver-delivery-price{
    width:48px;
    max-width:48px;
    padding:0 5px;
    font-size:13px;
    text-align:center
}


/* =========================================================
   METHOD
   ========================================================= */

.driver-delivery-entry .driver-delivery-method{
    width:76px;
    max-width:76px;
    padding:0 10px 0 5px;
    font-size:13px;
    text-align:center
}

.driver-delivery-entry select.form-input{
    line-height:normal;
    text-align:center
}


/* =========================================================
   BALANCE / REMOVE
   ========================================================= */

.driver-delivery-balance{
    width:78px!important;
    max-width:78px!important;
    padding:0 4px;

    display:flex;
    align-items:center;
    justify-content:center;

    border:1px solid var(--border);
    border-radius:var(--radius-sm);
    background:var(--surface);

    font-size:11px;
    font-weight:700;
    line-height:1.2;

    white-space:nowrap;
    overflow:hidden;
    text-align:center
}

.driver-delivery-balance-neutral{
    color:var(--text-muted);
    font-weight:500
}

.driver-delivery-balance-paid{
    color:var(--success);
    background:var(--success-light);
    border-color:rgba(46,155,91,.18)
}

.driver-delivery-balance-due{
    color:var(--warning);
    background:var(--warning-light);
    border-color:rgba(229,154,36,.18)
}

.driver-delivery-balance-unpaid{
    color:var(--danger);
    background:var(--danger-light);
    border-color:rgba(217,83,79,.18)
}

.driver-delivery-balance-overpaid{
    color:var(--primary-dark);
    background:var(--primary-light);
    border-color:rgba(22,135,201,.18)
}

.driver-delivery-remove{
    width:30px;
    height:38px;
    min-width:30px;

    margin:0;
    padding:0;

    display:flex;
    align-items:center;
    justify-content:center;
    align-self:end;
    justify-self:start;

    box-sizing:border-box;

    border:1px solid var(--border);
    border-radius:var(--radius-sm);

    background:var(--surface);
    color:var(--danger);

    font-size:18px;
    font-weight:600;
    line-height:1;

    cursor:pointer
}

.driver-delivery-remove:hover{
    background:var(--danger-light);
    border-color:var(--danger)
}


/* =========================================================
   CUSTOMER STATUS OUTLINE
   ========================================================= */

.driver-delivery-entry.status-paid{
    border:2px solid var(--success)
}

.driver-delivery-entry.status-due{
    border:2px solid var(--warning)
}

.driver-delivery-entry.status-unpaid{
    border:2px solid var(--danger)
}

.driver-delivery-entry.status-overpaid{
    border:2px solid var(--primary-dark)
}


/* =========================================================
   ADDED CUSTOMER ROWS
   ========================================================= */

/*
 * Added rows do not repeat the field titles,
 * but their placeholders/suggestions remain visible.
 */

.driver-delivery-entry-added .form-label{
    display:none
}

.driver-delivery-entry-added{
    padding-top:9px;
    padding-bottom:9px
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1200px){

    .driver-delivery-payment-rows{
        grid-template-columns:1fr;
        row-gap:0
    }

    .driver-delivery-entry:nth-child(even){
        padding-left:12px;
        border-left:2px solid var(--border)
    }

    .driver-delivery-entry:nth-child(n+2){
        border-top:2px solid var(--border)
    }
}

@media(max-width:850px){

    .driver-panel-header{
        flex-direction:column;
        align-items:stretch
    }

    .driver-payment-controls{
        width:100%
    }

    .driver-payment-controls .btn{
        flex:1
    }

    .driver-delivery-entry{
        grid-template-columns:repeat(4,minmax(0,1fr))
    }

    .driver-delivery-entry .driver-delivery-customer-group{
        grid-column:1/-1
    }

    .driver-delivery-entry .driver-delivery-balance-group{
        grid-column:span 2
    }

    .driver-delivery-remove{
        grid-column:4
    }
}

@media(max-width:550px){

    .driver-delivery-entry{
        grid-template-columns:1fr 1fr
    }

    .driver-delivery-entry .driver-delivery-customer-group,
    .driver-delivery-entry .driver-delivery-balance-group{
        grid-column:auto
    }

    .driver-delivery-remove{
        grid-column:auto;
        justify-self:start
    }
}

</style>

</head>

<body>

<div class="app">

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

        <a href="../pages/home.php" class="nav-item">
            🏠 <span>Home</span>
        </a>

        <a href="#" class="nav-item">
            👥 <span>Customers</span>
        </a>

        <a href="#" class="nav-item">
            📅 <span>Daily Records</span>
        </a>

        <a href="daily-closing.php" class="nav-item active">
            🧾 <span>Daily Closing</span>
        </a>

        <div
            class="nav-section-title"
            style="margin-top:25px"
        >
            System
        </div>

        <a href="#" class="nav-item">
            ⚙️ <span>Settings</span>
        </a>

        <a href="#" class="nav-item">
            🚪 <span>Logout</span>
        </a>

    </nav>

</aside>


<main class="main">

<section class="page-content">

<div class="page-header">

    <div>

        <h1 class="page-title">
            Driver's Delivery Payments
        </h1>

        <p class="page-description">
            Two-customer-per-row UI test based on the current Temporary Daily Closing design.
        </p>

    </div>

</div>


<div class="card driver-deliveries-card">

<div class="card-body">

<div
    class="driver-panel-section"
    style="margin-top:0;padding-top:0;border-top:0"
>


<div class="driver-panel-header">

<div>

<div class="driver-panel-title">
    Driver's Delivery Payments
</div>

<div class="driver-panel-subtitle">
    Same customer, quantity, Price/Gal, payment, method, and balance functionality as Shop Delivery Payments.
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


<div
    id="driverDeliveryPaymentRows"
    class="driver-delivery-payment-rows"
>


<!-- =====================================================
     FIRST CUSTOMER
     ===================================================== -->

<div class="driver-delivery-entry">

    <div class="form-group driver-delivery-customer-group">

        <label class="form-label">
            Customer
        </label>

        <input
            type="text"
            class="form-input driver-delivery-customer"
            value=""
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
            class="form-input driver-delivery-slim"
            value=""
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
            class="form-input driver-delivery-round"
            value=""
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
            class="form-input driver-delivery-payment"
            value=""
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
            class="form-input driver-delivery-price"
            value=""
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

        <select class="form-input driver-delivery-method">

            <option value="" selected>
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

        <div class="driver-delivery-balance driver-delivery-balance-neutral">
            —
        </div>

    </div>


    <button
        type="button"
        class="driver-delivery-remove"
        title="Remove customer"
    >
        ×
    </button>

</div>


<!-- =====================================================
     SECOND CUSTOMER
     ===================================================== -->

<div class="driver-delivery-entry">

    <div class="form-group driver-delivery-customer-group">

        <label class="form-label">
            Customer
        </label>

        <input
            type="text"
            class="form-input driver-delivery-customer"
            value=""
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
            class="form-input driver-delivery-slim"
            value=""
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
            class="form-input driver-delivery-round"
            value=""
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
            class="form-input driver-delivery-payment"
            value=""
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
            class="form-input driver-delivery-price"
            value=""
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

        <select class="form-input driver-delivery-method">

            <option value="" selected>
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

        <div class="driver-delivery-balance driver-delivery-balance-neutral">
            —
        </div>

    </div>


    <button
        type="button"
        class="driver-delivery-remove"
        title="Remove customer"
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


<script>

/* =========================================================
   DRIVER DELIVERY PAYMENT APPLICATION
   Object-Oriented UI + Calculation Controller
   ========================================================= */

class DriverDeliveryPaymentApp {


    /* =====================================================
       CONSTRUCTOR
       ===================================================== */

    constructor(container){

        this.container = container;

        this.adjustButtons =
            document.querySelectorAll(
                '[data-driver-payment-adjust]'
            );

        this.init();

    }


    /* =====================================================
       INITIALIZATION
       ===================================================== */

    init(){

        this.bindEvents();

        this.refreshBalances();

    }


    /* =====================================================
       EVENT BINDING
       ===================================================== */

    bindEvents(){

        /*
         * Live input calculations.
         */
        this.container.addEventListener(
            'input',
            (event) => this.handleInput(event)
        );


        /*
         * Select / change calculations.
         */
        this.container.addEventListener(
            'change',
            (event) => this.handleChange(event)
        );


        /*
         * Individual X remove buttons.
         */
        this.container.addEventListener(
            'click',
            (event) => this.handleRemoveClick(event)
        );


        /*
         * + / - row controls.
         */
        this.adjustButtons.forEach(
            (button) => {

                button.addEventListener(
                    'click',
                    () => this.handleRowAdjustment(button)
                );

            }
        );

    }


    /* =====================================================
       ENTRY CREATION
       ===================================================== */

    createEntry(){

        const entry =
            document.createElement('div');

        entry.className =
            'driver-delivery-entry driver-delivery-entry-added';


        entry.innerHTML = `

            <div class="form-group driver-delivery-customer-group">

                <label class="form-label">
                    Customer
                </label>

                <input
                    type="text"
                    class="form-input driver-delivery-customer"
                    value=""
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
                    class="form-input driver-delivery-slim"
                    value=""
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
                    class="form-input driver-delivery-round"
                    value=""
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
                    class="form-input driver-delivery-payment"
                    value=""
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
                    class="form-input driver-delivery-price"
                    value=""
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

                <select class="form-input driver-delivery-method">

                    <option value="" selected>
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

                <div class="driver-delivery-balance driver-delivery-balance-neutral">
                    —
                </div>

            </div>


            <button
                type="button"
                class="driver-delivery-remove"
                title="Remove customer"
            >
                ×
            </button>

        `;


        return entry;

    }


    /* =====================================================
       ENTRY VALUE HELPERS
       ===================================================== */

    getNumber(entry, selector){

        const field =
            entry.querySelector(selector);


        return parseFloat(
            field?.value
        ) || 0;

    }


    getEntryValues(entry){

        return {

            slim:
                this.getNumber(
                    entry,
                    '.driver-delivery-slim'
                ),

            round:
                this.getNumber(
                    entry,
                    '.driver-delivery-round'
                ),

            payment:
                this.getNumber(
                    entry,
                    '.driver-delivery-payment'
                ),

            price:
                this.getNumber(
                    entry,
                    '.driver-delivery-price'
                )

        };

    }


    /* =====================================================
       BALANCE CALCULATION
       ===================================================== */

    updateBalance(entry){

        const values =
            this.getEntryValues(entry);


        const balance =
            entry.querySelector(
                '.driver-delivery-balance'
            );


        if(!balance){

            return;

        }


        const gallons =
            values.slim +
            values.round;


        const expected =
            gallons *
            values.price;


        const difference =
            expected -
            values.payment;


        this.resetStatus(
            entry,
            balance
        );


        /*
         * No quantity or price entered.
         */
        if(
            gallons <= 0 ||
            values.price <= 0
        ){

            balance.classList.add(
                'driver-delivery-balance-neutral'
            );

            balance.textContent =
                '—';

            return;

        }


        /*
         * No payment.
         */
        if(values.payment <= 0){

            this.setStatus(
                entry,
                balance,
                'unpaid',
                'Unpaid'
            );

            return;

        }


        /*
         * Fully paid.
         */
        if(
            Math.abs(difference) <= 0.005
        ){

            this.setStatus(
                entry,
                balance,
                'paid',
                'Paid'
            );

            return;

        }


        /*
         * Customer still owes money.
         */
        if(difference > 0){

            this.setStatus(
                entry,
                balance,
                'due',
                `Due ₱${difference.toFixed(2)}`
            );

            return;

        }


        /*
         * Customer overpaid.
         */
        this.setStatus(
            entry,
            balance,
            'overpaid',
            `Over ₱${Math.abs(difference).toFixed(2)}`
        );

    }


    /* =====================================================
       STATUS MANAGEMENT
       ===================================================== */

    resetStatus(entry, balance){

        entry.classList.remove(
            'status-paid',
            'status-due',
            'status-unpaid',
            'status-overpaid'
        );


        balance.className =
            'driver-delivery-balance';

    }


    setStatus(
        entry,
        balance,
        status,
        text
    ){

        entry.classList.add(
            `status-${status}`
        );


        balance.classList.add(
            `driver-delivery-balance-${status}`
        );


        balance.textContent =
            text;

    }


    /* =====================================================
       REFRESH ALL BALANCES
       ===================================================== */

    refreshBalances(){

        this.getEntries().forEach(
            (entry) => this.updateBalance(entry)
        );

    }


    getEntries(){

        return Array.from(
            this.container.querySelectorAll(
                '.driver-delivery-entry'
            )
        );

    }


    /* =====================================================
       INPUT HANDLING
       ===================================================== */

    handleInput(event){

        const target =
            event.target;


        /*
         * Price/Gal is restricted
         * to numeric values with
         * a maximum of two digits.
         */
        if(
            target.classList.contains(
                'driver-delivery-price'
            )
        ){

            const sanitized =
                target.value
                    .replace(/\D/g, '')
                    .slice(0, 2);


            if(
                target.value !==
                sanitized
            ){

                target.value =
                    sanitized;

            }

        }


        const entry =
            target.closest(
                '.driver-delivery-entry'
            );


        if(entry){

            this.updateBalance(entry);

        }

    }


    /* =====================================================
       CHANGE HANDLING
       ===================================================== */

    handleChange(event){

        const entry =
            event.target.closest(
                '.driver-delivery-entry'
            );


        if(entry){

            this.updateBalance(entry);

        }

    }


    /* =====================================================
       + / - ROW CONTROLS
       ===================================================== */

    handleRowAdjustment(button){

        const amount =
            parseInt(
                button.dataset.driverPaymentAdjust,
                10
            ) || 0;


        /*
         * Add rows.
         */
        if(amount > 0){

            this.addEntries(
                amount
            );

            return;

        }


        /*
         * Remove rows.
         */
        if(amount < 0){

            this.removeEntries(
                Math.abs(amount)
            );

        }

    }


    /* =====================================================
       ADD ENTRIES
       ===================================================== */

    addEntries(count){

        /*
         * Build all new entries first,
         * then insert them into the DOM
         * in one operation.
         */
        const fragment =
            document.createDocumentFragment();


        for(
            let i = 0;
            i < count;
            i++
        ){

            fragment.appendChild(
                this.createEntry()
            );

        }


        this.container.appendChild(
            fragment
        );


        this.refreshBalances();

    }


    /* =====================================================
       REMOVE ENTRIES
       ===================================================== */

    removeEntries(count){

        const currentCount =
            this.getEntries().length;


        /*
         * Always keep at least
         * one customer row.
         */
        const removable =
            Math.min(
                count,
                Math.max(
                    0,
                    currentCount - 1
                )
            );


        for(
            let i = 0;
            i < removable;
            i++
        ){

            this.container
                .lastElementChild
                ?.remove();

        }


        this.refreshBalances();

    }


    /* =====================================================
       INDIVIDUAL X REMOVE
       ===================================================== */

    handleRemoveClick(event){

        const button =
            event.target.closest(
                '.driver-delivery-remove'
            );


        if(!button){

            return;

        }


        const entries =
            this.getEntries();


        /*
         * Never allow the final
         * customer row to be removed.
         */
        if(entries.length <= 1){

            return;

        }


        const entry =
            button.closest(
                '.driver-delivery-entry'
            );


        if(entry){

            entry.remove();

        }


        this.refreshBalances();

    }

}


/* =========================================================
   APPLICATION STARTUP
   ========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function(){

        const container =
            document.getElementById(
                'driverDeliveryPaymentRows'
            );


        if(!container){

            return;

        }


        new DriverDeliveryPaymentApp(
            container
        );

    }
);

</script>

</body>
</html>