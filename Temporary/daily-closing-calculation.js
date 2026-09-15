/*
 * =========================================================
 * MARCID BLUE - DAILY CLOSING CALCULATIONS
 * =========================================================
 *
 * PURPOSE:
 * - Handle calculations for the Temporary Daily Closing page.
 * - Keep calculation logic separate from the PHP/UI file.
 *
 * THIS FILE DOES NOT:
 * - Save anything to MySQL
 * - Autosave anything
 * - Load customers from the database
 * - Load customer prices from the database
 * - Reset the page
 * - Finalize or close the day
 * - Create UI rows
 *
 * UI row creation/removal remains inside:
 * Temporary/daily-closing.php
 *
 * =========================================================
 */

(function () {

    'use strict';


    /*
     * =========================================================
     * INITIALIZATION
     * =========================================================
     */

    function initializeDailyClosingCalculations() {

        /*
         * Prevent this calculation module from being
         * initialized more than once.
         */
        if (window.marcidBlueDailyClosingCalculationsInitialized) {
            return;
        }

        window.marcidBlueDailyClosingCalculationsInitialized = true;


        /*
         * =====================================================
         * CONSTANTS
         * =====================================================
         */

        const WALK_IN_PRICE = 30.00;

        /*
         * Small tolerance used when comparing money values.
         */
        const MONEY_TOLERANCE = 0.005;


        /*
         * =====================================================
         * GENERAL HELPERS
         * =====================================================
         */

        function getElement(id) {

            return document.getElementById(id);

        }


        function getNumber(value) {

            const number = parseFloat(value);

            if (!Number.isFinite(number)) {
                return 0;
            }

            return number;

        }


        function getInputNumber(element) {

            if (!element) {
                return 0;
            }

            return getNumber(element.value);

        }


        function roundMoney(value) {

            return Math.round(
                (getNumber(value) + Number.EPSILON) * 100
            ) / 100;

        }


        function formatMoney(value) {

            return '₱' + roundMoney(value).toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        }


        function setText(id, value) {

            const element = getElement(id);

            if (!element) {
                return;
            }

            element.textContent = value;

        }


        function setValue(id, value) {

            const element = getElement(id);

            if (!element) {
                return;
            }

            element.value = value;

        }


        function getRows(selector) {

            return Array.from(
                document.querySelectorAll(selector)
            );

        }


        /*
         * =========================================================
         * SHOP EXPENSES
         * =========================================================
         */

        function getShopExpenses() {

            let total = 0;

            getRows('.expense-row').forEach(function (row) {

                const amountInput =
                    row.querySelector('.expense-amount');

                total += getInputNumber(amountInput);

            });

            return roundMoney(total);

        }


        /*
         * =========================================================
         * SHOP DELIVERY PAYMENTS
         * =========================================================
         *
         * These are actual payments already received for
         * shop delivery transactions.
         *
         * They are subtracted from the money entered in the
         * Shop / Walk-in section when determining the amount
         * attributable to walk-in sales.
         * =========================================================
         */

        function getShopDeliveryPayments() {

            let total = 0;

            getRows('.delivery-payment-row').forEach(function (row) {

                const paymentInput =
                    row.querySelector('.delivery-payment');

                total += getInputNumber(paymentInput);

            });

            return roundMoney(total);

        }


        /*
         * =========================================================
         * SHOP DELIVERY ROW CALCULATION
         * =========================================================
         *
         * Expected:
         *
         *     (Slim + Round) × Price
         *
         * Balance:
         *
         *     Expected - Payment
         *
         * Status:
         *
         *     No payment       = Unpaid
         *     Equal            = Paid
         *     Expected > paid  = Due
         *     Paid > expected  = Overpaid
         * =========================================================
         */

        function updateShopDeliveryBalance(row) {

            if (!row) {
                return;
            }

            const slimInput =
                row.querySelector('.delivery-slim');

            const roundInput =
                row.querySelector('.delivery-round');

            const priceInput =
                row.querySelector('.delivery-price-input');

            const paymentInput =
                row.querySelector('.delivery-payment');

            const balanceElement =
                row.querySelector('.delivery-balance');


            if (!balanceElement) {
                return;
            }


            const slim =
                getInputNumber(slimInput);

            const round =
                getInputNumber(roundInput);

            const price =
                getInputNumber(priceInput);

            const payment =
                getInputNumber(paymentInput);


            const gallons =
                slim + round;

            const expected =
                roundMoney(gallons * price);


            const difference =
                roundMoney(expected - payment);


            /*
             * No quantity entered.
             */
            if (gallons <= 0) {

                balanceElement.textContent = '—';

                balanceElement.className =
                    'delivery-balance delivery-balance-neutral';

                return;

            }


            /*
             * No payment.
             */
            if (payment <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Unpaid';

                balanceElement.className =
                    'delivery-balance delivery-balance-unpaid';

                return;

            }


            /*
             * Payment matches expected amount.
             */
            if (Math.abs(difference) <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Paid';

                balanceElement.className =
                    'delivery-balance delivery-balance-paid';

                return;

            }


            /*
             * Customer still owes money.
             */
            if (difference > 0) {

                balanceElement.textContent =
                    'Due ' + formatMoney(difference);

                balanceElement.className =
                    'delivery-balance delivery-balance-due';

                return;

            }


            /*
             * Customer paid more than expected.
             */
            balanceElement.textContent =
                'Over ' + formatMoney(Math.abs(difference));

            balanceElement.className =
                'delivery-balance delivery-balance-overpaid';

        }


        function refreshShopDeliveryBalances() {

            getRows('.delivery-payment-row').forEach(
                updateShopDeliveryBalance
            );

        }


        /*
         * =========================================================
         * SHOP / WALK-IN CALCULATION
         * =========================================================
         *
         * Business rule:
         *
         * total balance =
         *
         *     money received
         *     + station expenses
         *     - shop delivery payments
         *
         * The resulting amount is divided into:
         *
         *     Whole walk-in gallons × ₱30
         *
         * and:
         *
         *     Remaining amount below ₱30 = Other Sales
         *
         * Example:
         *
         *     ₱5,510 total balance
         *
         *     5,510 / 30
         *
         *     183 gallons = ₱5,490
         *
         *     remaining ₱20 = Other Sales
         *
         * This prevents decimal gallon quantities.
         * =========================================================
         */

        function calculateShop() {

            const moneyReceivedInput =
                getElement('walk_in_money');

            const customersInput =
                getElement('walk_in_customers');


            const moneyReceived =
                getInputNumber(moneyReceivedInput);

            const expenses =
                getShopExpenses();

            const deliveryPayments =
                getShopDeliveryPayments();


            /*
             * Calculate the amount that belongs to the
             * Shop / Walk-in calculation.
             */
            const totalBalance =
                roundMoney(
                    moneyReceived
                    + expenses
                    - deliveryPayments
                );


            /*
             * Prevent negative values.
             */
            if (totalBalance < -MONEY_TOLERANCE) {

                setText(
                    'shopComputedSales',
                    'Invalid'
                );

                setText(
                    'shopOtherSales',
                    'Invalid'
                );

                setValue(
                    'walk_in_customers',
                    0
                );

                setText(
                    'dashboardShopCustomers',
                    '0'
                );

                return {
                    valid: false,
                    totalBalance: totalBalance,
                    customers: 0,
                    walkInSales: 0,
                    otherSales: 0,
                    expenses: expenses,
                    deliveryPayments: deliveryPayments,
                    moneyReceived: moneyReceived
                };

            }


            /*
             * Do not allow floating-point noise.
             */
            const safeBalance =
                Math.max(
                    0,
                    roundMoney(totalBalance)
                );


            /*
             * Walk-in quantity must ALWAYS be a whole number.
             *
             * Example:
             *
             * ₱5510 / ₱30 = 183.666...
             *
             * Therefore:
             *
             * 183 gallons
             */
            const customers =
                Math.floor(
                    safeBalance / WALK_IN_PRICE
                );


            /*
             * Calculate actual walk-in sales.
             */
            const walkInSales =
                roundMoney(
                    customers * WALK_IN_PRICE
                );


            /*
             * Whatever remains below ₱30 is treated as
             * Other / Additional Sales.
             *
             * This handles things such as:
             *
             * - Bottle filling
             * - Small additional charges
             * - Other random sales
             */
            const otherSales =
                roundMoney(
                    safeBalance - walkInSales
                );


            /*
             * Update Walk-in quantity field.
             */
            setValue(
                'walk_in_customers',
                customers
            );


            /*
             * Update Shop calculated sales.
             */
            setText(
                'shopComputedSales',
                formatMoney(walkInSales)
            );


            /*
             * Update Other Sales.
             */
            setText(
                'shopOtherSales',
                formatMoney(otherSales)
            );


            /*
             * Update top summary card.
             */
            setText(
                'dashboardShopCustomers',
                customers.toLocaleString('en-PH')
            );


            return {
                valid: true,
                totalBalance: safeBalance,
                customers: customers,
                walkInSales: walkInSales,
                otherSales: otherSales,
                expenses: expenses,
                deliveryPayments: deliveryPayments,
                moneyReceived: moneyReceived
            };

        }


        /*
         * =========================================================
         * DRIVER EXPENSES
         * =========================================================
         */

        function getDriverExpenses() {

            let total = 0;

            getRows('.driver-expense-row').forEach(
                function (row) {

                    const amountInput =
                        row.querySelector(
                            '.driver-expense-amount'
                        );

                    total += getInputNumber(amountInput);

                }
            );

            return roundMoney(total);

        }


        /*
         * =========================================================
         * DRIVER DELIVERY CALCULATION
         * =========================================================
         */

        function updateDriverDeliveryBalance(row) {

            if (!row) {
                return;
            }


            const slimInput =
                row.querySelector(
                    '.driver-delivery-slim'
                );

            const roundInput =
                row.querySelector(
                    '.driver-delivery-round'
                );

            const priceInput =
                row.querySelector(
                    '.driver-delivery-price'
                );

            const paymentInput =
                row.querySelector(
                    '.driver-delivery-payment'
                );

            const balanceElement =
                row.querySelector(
                    '.driver-delivery-balance'
                );


            if (!balanceElement) {
                return;
            }


            const slim =
                getInputNumber(slimInput);

            const round =
                getInputNumber(roundInput);

            const price =
                getInputNumber(priceInput);

            const payment =
                getInputNumber(paymentInput);


            const gallons =
                slim + round;

            const expected =
                roundMoney(
                    gallons * price
                );


            const difference =
                roundMoney(
                    expected - payment
                );


            /*
             * No quantity.
             */
            if (gallons <= 0) {

                balanceElement.textContent = '—';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-neutral';

                return;

            }

            /*
            * Price per gallon is required before
            * calculating the payment status.
            *
            * Until the price is entered, the row
            * remains neutral.
            */
            if (price <= MONEY_TOLERANCE) {

                balanceElement.textContent = '—';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-neutral';

                return;
            }


            /*
             * No payment.
             */
            if (payment <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Unpaid';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-unpaid';

                return;

            }


            /*
             * Fully paid.
             */
            if (Math.abs(difference) <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Paid';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-paid';

                return;

            }


            /*
             * Customer still owes money.
             */
            if (difference > 0) {

                balanceElement.textContent =
                    'Due ' + formatMoney(difference);

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-due';

                return;

            }


            /*
             * Customer overpaid.
             */
            balanceElement.textContent =
                'Over ' + formatMoney(Math.abs(difference));

            balanceElement.className =
                'driver-delivery-balance ' +
                'driver-delivery-balance-overpaid';

        }


        function calculateDriverDeliveries() {

            let totalQuantity = 0;

            let totalExpectedMoney = 0;

            let deliveryRows = 0;


            getRows('.driver-delivery-row').forEach(
                function (row) {

                    const slim =
                        getInputNumber(
                            row.querySelector(
                                '.driver-delivery-slim'
                            )
                        );

                    const round =
                        getInputNumber(
                            row.querySelector(
                                '.driver-delivery-round'
                            )
                        );

                    const price =
                        getInputNumber(
                            row.querySelector(
                                '.driver-delivery-price'
                            )
                        );


                    const gallons =
                        slim + round;

                    const expected =
                        roundMoney(
                            gallons * price
                        );


                    /*
                     * Only count a row as a delivery when
                     * it contains an actual quantity.
                     */
                    if (gallons > 0) {

                        deliveryRows++;

                    }


                    totalQuantity += gallons;

                    totalExpectedMoney += expected;


                    updateDriverDeliveryBalance(row);

                }
            );


            totalQuantity =
                roundMoney(totalQuantity);

            totalExpectedMoney =
                roundMoney(totalExpectedMoney);


            return {
                totalQuantity: totalQuantity,
                totalExpectedMoney: totalExpectedMoney,
                deliveryRows: deliveryRows
            };

        }


        /*
         * =========================================================
         * DRIVER PAYMENT TOTAL
         * =========================================================
         */

        function getDriverDeliveryPayments() {

            let total = 0;


            getRows(
                '.driver-delivery-payment'
            ).forEach(
                function (input) {

                    total += getInputNumber(input);

                }
            );


            return roundMoney(total);

        }


        /*
         * =========================================================
         * DRIVER REMITTANCE
         * =========================================================
         *
         * Reference logic:
         *
         * Effective received =
         *
         *     Driver Money Received
         *     + Driver Expenses
         *     + Shop Delivery Payments
         *
         * Expected =
         *
         *     Shop Delivery Payments
         *     + Driver Delivery Payments
         *
         * Difference =
         *
         *     Effective Received - Expected
         *
         * This preserves the existing Marcid Blue remittance
         * accounting logic.
         * =========================================================
         */

        function updateDriverRemittanceStatus() {

            const driverMoneyInput =
                getElement('driver_money_received');


            const driverMoney =
                getInputNumber(
                    driverMoneyInput
                );

            const driverExpenses =
                getDriverExpenses();

            const shopDeliveryPayments =
                getShopDeliveryPayments();

            const driverDeliveryPayments =
                getDriverDeliveryPayments();


            const effectiveReceived =
                roundMoney(
                    driverMoney
                    + driverExpenses
                    + shopDeliveryPayments
                );


            const expected =
                roundMoney(
                    shopDeliveryPayments
                    + driverDeliveryPayments
                );


            const difference =
                roundMoney(
                    effectiveReceived - expected
                );


            const statusElement =
                getElement(
                    'driverRemittanceStatus'
                );


            if (!statusElement) {
                return;
            }


            /*
             * No driver activity.
             */
            if (
                Math.abs(effectiveReceived)
                    <= MONEY_TOLERANCE
                &&
                Math.abs(expected)
                    <= MONEY_TOLERANCE
            ) {

                statusElement.textContent =
                    '—';

                statusElement.className =
                    'summary-value driver-remittance-status';

                return;

            }


            /*
             * Exactly balanced.
             */
            if (
                Math.abs(difference)
                    <= MONEY_TOLERANCE
            ) {

                statusElement.textContent =
                    'Balanced';

                statusElement.className =
                    'summary-value ' +
                    'driver-remittance-status ' +
                    'driver-remittance-balanced';

                return;

            }


            /*
             * Driver is short.
             */
            if (difference < 0) {

                statusElement.textContent =
                    'Short of ' +
                    formatMoney(
                        Math.abs(difference)
                    );

                statusElement.className =
                    'summary-value ' +
                    'driver-remittance-status ' +
                    'driver-remittance-short';

                return;

            }


            /*
             * Driver has more money than expected.
             */
            statusElement.textContent =
                'Over of ' +
                formatMoney(difference);

            statusElement.className =
                'summary-value ' +
                'driver-remittance-status ' +
                'driver-remittance-over';

        }


        /*
         * =========================================================
         * DRIVER SUMMARY
         * =========================================================
         */

        function calculateDriver() {

            const deliveryTotals =
                calculateDriverDeliveries();


            /*
             * Total quantity.
             */
            setText(
                'driver_total_delivery_quantity',
                deliveryTotals.totalQuantity
                    .toLocaleString('en-PH')
            );


            /*
             * Total expected delivery sales.
             */
            setText(
                'driverTotalDeliverySales',
                formatMoney(
                    deliveryTotals.totalExpectedMoney
                )
            );


            /*
             * Total expected money.
             */
            setText(
                'driverTotalExpectedMoney',
                formatMoney(
                    deliveryTotals.totalExpectedMoney
                )
            );


            /*
             * Driver remittance.
             */
            updateDriverRemittanceStatus();


            return deliveryTotals;

        }


        /*
         * =========================================================
         * TOTAL DELIVERY CARD
         * =========================================================
         *
         * Counts delivery rows containing at least one gallon.
         *
         * Shop delivery rows
         * +
         * Driver delivery rows
         * =========================================================
         */

        function updateTotalDeliveriesCard() {

            let totalDeliveries = 0;


            /*
             * Shop deliveries.
             */
            getRows(
                '.delivery-payment-row'
            ).forEach(
                function (row) {

                    const slim =
                        getInputNumber(
                            row.querySelector(
                                '.delivery-slim'
                            )
                        );

                    const round =
                        getInputNumber(
                            row.querySelector(
                                '.delivery-round'
                            )
                        );


                    if ((slim + round) > 0) {
                        totalDeliveries++;
                    }

                }
            );


            /*
             * Driver deliveries.
             */
            getRows(
                '.driver-delivery-row'
            ).forEach(
                function (row) {

                    const slim =
                        getInputNumber(
                            row.querySelector(
                                '.driver-delivery-slim'
                            )
                        );

                    const round =
                        getInputNumber(
                            row.querySelector(
                                '.driver-delivery-round'
                            )
                        );


                    if ((slim + round) > 0) {
                        totalDeliveries++;
                    }

                }
            );


            setText(
                'dashboardTotalDeliveries',
                totalDeliveries.toLocaleString('en-PH')
            );


            return totalDeliveries;

        }


        /*
         * =========================================================
         * NET PROFIT / TOTAL MONEY RECEIVED
         * =========================================================
         *
         * The card currently describes this figure as:
         *
         * "Total money received from Shop and Driver"
         *
         * Therefore the displayed value follows the money
         * actually entered as received:
         *
         *     Shop Money Received
         *     +
         *     Driver Money Received
         *
         * Expenses are not subtracted here because the card
         * represents received money, not the final accounting
         * profit calculation.
         * =========================================================
         */

        function updateNetProfit() {

            const shopMoneyReceived =
                getInputNumber(
                    getElement('walk_in_money')
                );


            const driverMoneyReceived =
                getInputNumber(
                    getElement('driver_money_received')
                );


            const totalReceived =
                roundMoney(
                    shopMoneyReceived
                    + driverMoneyReceived
                );


            const netProfitElement =
                document.querySelector(
                    '.net-profit-visible'
                );


            if (netProfitElement) {

                netProfitElement.textContent =
                    formatMoney(totalReceived);

            }


            return totalReceived;

        }


        /*
         * =========================================================
         * CALCULATE EVERYTHING
         * =========================================================
         */

        function calculateEverything() {

            /*
             * Shop calculation.
             */
            const shopResult =
                calculateShop();


            /*
             * Update every shop delivery balance.
             */
            refreshShopDeliveryBalances();


            /*
             * Driver calculation.
             */
            const driverResult =
                calculateDriver();


            /*
             * Total delivery count.
             */
            const totalDeliveries =
                updateTotalDeliveriesCard();


            /*
             * Top money-received card.
             */
            const totalReceived =
                updateNetProfit();


            return {
                shop: shopResult,
                driver: driverResult,
                totalDeliveries: totalDeliveries,
                totalReceived: totalReceived
            };

        }


        /*
         * =========================================================
         * SHOP COMPUTE BUTTON
         * =========================================================
         */

        const shopComputeButton =
            getElement('shopComputeButton');


        if (shopComputeButton) {

            shopComputeButton.addEventListener(
                'click',
                function () {

                    calculateEverything();

                }
            );

        }


        /*
         * =========================================================
         * DRIVER BALANCE BUTTON
         * =========================================================
         */

        const driverBalanceButton =
            getElement('driverBalanceButton');


        if (driverBalanceButton) {

            driverBalanceButton.addEventListener(
                'click',
                function () {

                    calculateDriver();

                    updateTotalDeliveriesCard();

                    updateNetProfit();

                }
            );

        }


        /*
         * =========================================================
         * LIVE CALCULATIONS
         * =========================================================
         *
         * Calculations are refreshed while the user edits
         * the relevant fields.
         *
         * This does NOT save anything.
         * =========================================================
         */

        document.addEventListener(
            'input',
            function (event) {

                const target =
                    event.target;


                if (!target) {
                    return;
                }


                /*
                 * Shop-related fields.
                 */
                if (
                    target.id === 'walk_in_money'
                    ||
                    target.classList.contains(
                        'expense-amount'
                    )
                    ||
                    target.classList.contains(
                        'delivery-slim'
                    )
                    ||
                    target.classList.contains(
                        'delivery-round'
                    )
                    ||
                    target.classList.contains(
                        'delivery-price-input'
                    )
                    ||
                    target.classList.contains(
                        'delivery-payment'
                    )
                ) {

                    calculateShop();

                    refreshShopDeliveryBalances();

                    updateTotalDeliveriesCard();

                    updateNetProfit();

                }


                /*
                 * Driver-related fields.
                 */
                if (
                    target.id === 'driver_money_received'
                    ||
                    target.classList.contains(
                        'driver-expense-amount'
                    )
                    ||
                    target.classList.contains(
                        'driver-delivery-slim'
                    )
                    ||
                    target.classList.contains(
                        'driver-delivery-round'
                    )
                    ||
                    target.classList.contains(
                        'driver-delivery-price'
                    )
                    ||
                    target.classList.contains(
                        'driver-delivery-payment'
                    )
                ) {

                    calculateDriver();

                    updateTotalDeliveriesCard();

                    updateNetProfit();

                }

            }
        );


        /*
         * =========================================================
         * CHANGE EVENTS
         * =========================================================
         *
         * Handles select elements such as:
         *
         * - Expense category
         * - Payment method
         *
         * It also refreshes delivery calculations when a
         * quantity/price/payment field is changed through a
         * non-keyboard interaction.
         * =========================================================
         */

        document.addEventListener(
            'change',
            function (event) {

                const target =
                    event.target;


                if (!target) {
                    return;
                }


                if (
                    target.closest(
                        '#shopWalkInForm'
                    )
                    ||
                    target.closest(
                        '#driverDeliveriesPanel'
                    )
                ) {

                    calculateEverything();

                }

            }
        );


        /*
         * =========================================================
         * MUTATION OBSERVER
         * =========================================================
         *
         * The PHP page contains buttons that add/remove rows.
         *
         * The UI itself remains responsible for creating/removing
         * rows.
         *
         * This observer only notices that the rows changed and
         * refreshes calculations.
         * =========================================================
         */

        let calculationRefreshTimer = null;


        function scheduleCalculationRefresh() {

            if (calculationRefreshTimer !== null) {
                clearTimeout(
                    calculationRefreshTimer
                );
            }


            calculationRefreshTimer =
                setTimeout(
                    function () {

                        calculationRefreshTimer = null;

                        calculateEverything();

                    },
                    0
                );

        }


        const calculationObserver =
            new MutationObserver(
                function () {

                    scheduleCalculationRefresh();

                }
            );


        const shopExpenseRows =
            getElement('expenseRows');

        const shopDeliveryRows =
            getElement('deliveryPaymentRows');

        const driverPanel =
            getElement('driverDeliveriesPanel');


        if (shopExpenseRows) {

            calculationObserver.observe(
                shopExpenseRows,
                {
                    childList: true,
                    subtree: true
                }
            );

        }


        if (shopDeliveryRows) {

            calculationObserver.observe(
                shopDeliveryRows,
                {
                    childList: true,
                    subtree: true
                }
            );

        }


        if (driverPanel) {

            calculationObserver.observe(
                driverPanel,
                {
                    childList: true,
                    subtree: true
                }
            );

        }


        /*
         * =========================================================
         * INITIAL CALCULATION
         * =========================================================
         */

        calculateEverything();


        /*
         * =========================================================
         * PUBLIC API
         * =========================================================
         *
         * Makes the calculation functions available for
         * debugging/testing from the browser console without
         * requiring any other script.
         * =========================================================
         */

        window.marcidBlueDailyClosing = {

            calculateEverything:
                calculateEverything,

            calculateShop:
                calculateShop,

            calculateDriver:
                calculateDriver,

            updateShopDeliveryBalance:
                updateShopDeliveryBalance,

            updateDriverDeliveryBalance:
                updateDriverDeliveryBalance,

            updateDriverRemittanceStatus:
                updateDriverRemittanceStatus,

            updateTotalDeliveriesCard:
                updateTotalDeliveriesCard,

            updateNetProfit:
                updateNetProfit

        };

    }


    /*
     * =========================================================
     * START
     * =========================================================
     */

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initializeDailyClosingCalculations
        );

    } else {

        initializeDailyClosingCalculations();

    }

})();