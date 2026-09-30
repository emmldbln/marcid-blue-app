(function () {

    'use strict';

    function initializeDailyClosingCalculations() {

        if (window.marcidBlueDailyClosingCalculationsInitialized) {
            return;
        }

        window.marcidBlueDailyClosingCalculationsInitialized = true;

       let WALK_IN_PRICE =
            Number(
                window.MARCID_BLUE_WALK_IN_PRICE
            );

        if (
            !Number.isFinite(WALK_IN_PRICE) ||
            WALK_IN_PRICE <= 0
        ) {
            WALK_IN_PRICE = 30.00;
        }
        const MONEY_TOLERANCE = 0.005;


        // =========================================================
        // GENERAL HELPERS
        // =========================================================

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


        // =========================================================
        // DELIVERY EXPECTED SALE
        // =========================================================
        //
        // Calculates the amount that should be represented in
        // "Expected Sale for Today".
        //
        // Base expected amount:
        //     gallons × price
        //
        // Unpaid:
        //     Expected amount remains due.
        //
        // Partial / Due:
        //     Expected amount remains the full expected sale.
        //
        // Paid:
        //     Expected amount is used.
        //
        // Overpaid:
        //     Expected amount + overpayment.
        //
        // Therefore:
        //
        //     expected sale = max(expected amount, payment)
        //
        // This uses the same expected/payment values that are
        // already used by the Balance field.
        // =========================================================

        function getExpectedSaleWithOverpayment(
            expected,
            payment
        ) {

            const expectedAmount =
                roundMoney(expected);

            const paymentAmount =
                roundMoney(payment);

            if (expectedAmount <= MONEY_TOLERANCE) {
                return 0;
            }

            const overpayment =
                Math.max(
                    0,
                    roundMoney(
                        paymentAmount - expectedAmount
                    )
                );

            return roundMoney(
                expectedAmount + overpayment
            );
        }


        // =========================================================
        // SHOP EXPENSES
        // =========================================================

        function getShopExpenses() {

            let total = 0;

            getRows('.expense-row').forEach(function (row) {

                const amountInput =
                    row.querySelector('.expense-amount');

                total += getInputNumber(amountInput);
            });

            return roundMoney(total);
        }


        // =========================================================
        // SHOP DELIVERY PAYMENTS
        // =========================================================
        //
        // Actual money received from Shop deliveries.
        //
        // Unpaid:
        //     ₱0
        //
        // Partial:
        //     Actual payment
        //
        // Paid:
        //     Actual payment
        //
        // Overpaid:
        //     Actual payment, including overpayment
        // =========================================================

        function getShopDeliveryPayments() {

            let total = 0;

            getRows('.delivery-payment-row').forEach(function (row) {

                const paymentInput =
                    row.querySelector('.delivery-payment');

                total += getInputNumber(paymentInput);
            });

            return roundMoney(total);
        }


        // =========================================================
        // SHOP EXPECTED DELIVERY SALES
        // =========================================================
        //
        // Uses the same payment and expected values as the
        // Shop Balance field.
        //
        // Overpayment is added to the expected sale.
        // =========================================================

        function getShopDeliveryExpectedSales() {

            let total = 0;

            getRows('.delivery-payment-row').forEach(function (row) {

                const slim =
                    getInputNumber(
                        row.querySelector('.delivery-slim')
                    );

                const round =
                    getInputNumber(
                        row.querySelector('.delivery-round')
                    );

                const price =
                    getInputNumber(
                        row.querySelector('.delivery-price-input')
                    );

                const payment =
                    getInputNumber(
                        row.querySelector('.delivery-payment')
                    );


                const gallons =
                    slim + round;

                const expected =
                    roundMoney(
                        gallons * price
                    );


                const expectedSale =
                    getExpectedSaleWithOverpayment(
                        expected,
                        payment
                    );


                total += expectedSale;
            });

            return roundMoney(total);
        }


        // =========================================================
        // DRIVER DELIVERY PAYMENTS
        // =========================================================
        //
        // Actual money received from Driver deliveries.
        // =========================================================

        function getDriverDeliveryPayments() {

            let total = 0;

            getRows('.driver-delivery-payment').forEach(
                function (input) {

                    total += getInputNumber(input);
                }
            );

            return roundMoney(total);
        }


        // =========================================================
        // TOTAL ACTUAL DELIVERY SALES
        // =========================================================
        //
        // Represents money actually received from all delivery
        // transactions.
        //
        // Overpayments are included because the actual payment
        // field is used directly.
        // =========================================================

        function getTotalDeliverySalesReceived() {

            const shopPayments =
                getShopDeliveryPayments();

            const driverPayments =
                getDriverDeliveryPayments();

            return roundMoney(
                shopPayments + driverPayments
            );
        }


        // =========================================================
        // SHOP DELIVERY ROW CALCULATION
        // =========================================================

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
                roundMoney(
                    gallons * price
                );

            const difference =
                roundMoney(
                    expected - payment
                );


            if (gallons <= 0) {

                balanceElement.textContent = '—';

                balanceElement.className =
                    'delivery-balance delivery-balance-neutral';

                return;
            }


            if (payment <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Unpaid';

                balanceElement.className =
                    'delivery-balance delivery-balance-unpaid';

                return;
            }


            if (Math.abs(difference) <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Paid';

                balanceElement.className =
                    'delivery-balance delivery-balance-paid';

                return;
            }


            if (difference > 0) {

                balanceElement.textContent =
                    'Due ' + formatMoney(difference);

                balanceElement.className =
                    'delivery-balance delivery-balance-due';

                return;
            }


            balanceElement.textContent =
                'Over ' + formatMoney(
                    Math.abs(difference)
                );

            balanceElement.className =
                'delivery-balance delivery-balance-overpaid';
        }


        function refreshShopDeliveryBalances() {

            getRows('.delivery-payment-row').forEach(
                updateShopDeliveryBalance
            );
        }


        // =========================================================
        // SHOP / WALK-IN CALCULATION
        // =========================================================

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


            const totalBalance =
                roundMoney(
                    moneyReceived
                    + expenses
                    - deliveryPayments
                );


            if (totalBalance < -MONEY_TOLERANCE) {

                setText(
                    'shopComputedSales',
                    '---'
                );

                setText(
                    'shopOtherSales',
                    '---'
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


            const safeBalance =
                Math.max(
                    0,
                    roundMoney(totalBalance)
                );


            const customers =
                Math.floor(
                    safeBalance / WALK_IN_PRICE
                );


            const walkInSales =
                roundMoney(
                    customers * WALK_IN_PRICE
                );


            const otherSales =
                roundMoney(
                    safeBalance - walkInSales
                );


            setValue(
                'walk_in_customers',
                customers
            );


            setText(
                'shopComputedSales',
                formatMoney(walkInSales)
            );


            setText(
                'shopOtherSales',
                formatMoney(otherSales)
            );


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


        // =========================================================
        // DRIVER EXPENSES
        // =========================================================

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


        // =========================================================
        // DRIVER DELIVERY CALCULATION
        // =========================================================

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


            if (gallons <= 0) {

                balanceElement.textContent = '—';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-neutral';

                return;
            }


            if (price <= MONEY_TOLERANCE) {

                balanceElement.textContent = '—';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-neutral';

                return;
            }


            if (payment <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Unpaid';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-unpaid';

                return;
            }


            if (Math.abs(difference) <= MONEY_TOLERANCE) {

                balanceElement.textContent =
                    'Paid';

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-paid';

                return;
            }


            if (difference > 0) {

                balanceElement.textContent =
                    'Due ' + formatMoney(difference);

                balanceElement.className =
                    'driver-delivery-balance ' +
                    'driver-delivery-balance-due';

                return;
            }


            balanceElement.textContent =
                'Over ' + formatMoney(
                    Math.abs(difference)
                );

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

                    const payment =
                        getInputNumber(
                            row.querySelector(
                                '.driver-delivery-payment'
                            )
                        );


                    const gallons =
                        slim + round;

                    const expected =
                        roundMoney(
                            gallons * price
                        );


                    const expectedSale =
                        getExpectedSaleWithOverpayment(
                            expected,
                            payment
                        );


                    if (gallons > 0) {
                        deliveryRows++;
                    }


                    totalQuantity += gallons;

                    totalExpectedMoney +=
                        expectedSale;


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


        // =========================================================
        // DRIVER REMITTANCE
        // =========================================================

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


            statusElement.textContent =
                'Over of ' +
                formatMoney(difference);

            statusElement.className =
                'summary-value ' +
                'driver-remittance-status ' +
                'driver-remittance-over';
        }


        // =========================================================
        // DRIVER SUMMARY
        // =========================================================

        function calculateDriver() {

            const deliveryTotals =
                calculateDriverDeliveries();


            const shopDeliveryPayments =
                getShopDeliveryPayments();

            const shopExpectedDeliverySales =
                getShopDeliveryExpectedSales();


            const expectedDeliveryMoneyToday =
                roundMoney(
                    deliveryTotals.totalExpectedMoney
                    + shopExpectedDeliverySales
                );


            setText(
                'driver_total_delivery_quantity',
                deliveryTotals.totalQuantity
                    .toLocaleString('en-PH')
            );


            /*
             * Actual Sales of Delivery represents actual
             * payments received from Shop + Driver deliveries.
             */
            setText(
                'driverTotalDeliverySales',
                formatMoney(
                    getTotalDeliverySalesReceived()
                )
            );


            /*
             * Expected Sale for Today represents the full
             * expected value of every delivery.
             *
             * If a customer overpays, the overpayment is added
             * to the expected sale.
             *
             * Example:
             *
             * Expected = ₱140
             * Payment  = ₱180
             * Balance  = Over ₱40
             *
             * Expected Sale = ₱180
             */
            setText(
                'driverTotalExpectedMoney',
                formatMoney(
                    expectedDeliveryMoneyToday
                )
            );


            updateDriverRemittanceStatus();


            return {
                ...deliveryTotals,
                shopDeliveryPayments:
                    shopDeliveryPayments,
                shopExpectedDeliverySales:
                    shopExpectedDeliverySales,
                expectedDeliveryMoneyToday:
                    expectedDeliveryMoneyToday
            };
        }


        // =========================================================
        // TOTAL DELIVERY GALLONS CARD
        // =========================================================

        function updateTotalDeliveriesCard() {

            let totalDeliveries = 0;


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

                    totalDeliveries +=
                        slim + round;
                }
            );


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

                    totalDeliveries +=
                        slim + round;
                }
            );


            totalDeliveries =
                roundMoney(totalDeliveries);


            setText(
                'dashboardTotalDeliveries',
                totalDeliveries.toLocaleString('en-PH')
            );


            return totalDeliveries;
        }


       function updateNetProfit() {

            const shopResult =
                calculateShop();


            const walkInSales =
                shopResult.valid
                    ? shopResult.walkInSales
                    : 0;


            const otherSales =
                shopResult.valid
                    ? shopResult.otherSales
                    : 0;


            const deliverySalesReceived =
                getTotalDeliverySalesReceived();


            /*
            * ---------------------------------------------------------
            * TOTAL REVENUE
            * ---------------------------------------------------------
            *
            * Based on actual money received:
            *
            * Walk-in Sales
            * + Other / Additional Sales
            * + Actual Delivery Payments
            */

            const totalRevenue =
                roundMoney(
                    walkInSales
                    + otherSales
                    + deliverySalesReceived
                );


            /*
            * ---------------------------------------------------------
            * PAYROLL
            * ---------------------------------------------------------
            *
            * Payroll is separate from Station / Driver Expenses.
            * It is deducted directly from Net Profit.
            */

            let payrollTotal = 0;


            if (
                window.marcidBlueDailyClosingData &&
                typeof
                    window.marcidBlueDailyClosingData
                        .getPayrollTotal ===
                    'function'
            ) {

                payrollTotal =
                    Number(
                        window.marcidBlueDailyClosingData
                            .getPayrollTotal()
                    ) || 0;

            }


            payrollTotal =
                roundMoney(
                    payrollTotal
                );


            /*
            * ---------------------------------------------------------
            * NET PROFIT
            * ---------------------------------------------------------
            */

            const netProfit =
                roundMoney(
                    totalRevenue
                    - payrollTotal
                );


            const netProfitElement =
                document.querySelector(
                    '.net-profit-visible'
                );


            if (netProfitElement) {

                netProfitElement.textContent =
                    formatMoney(
                        netProfit
                    );

            }


            return netProfit;
        }


        // =========================================================
        // CURRENT DRAFT DEBT
        // =========================================================

        function getCurrentDraftDebt() {

            const balances = new Map();


            function normalizeName(value) {

                return String(value ?? '')
                    .trim()
                    .toLowerCase();
            }


            function addCustomer(
                customerName,
                due,
                payment
            ) {

                const name =
                    String(customerName ?? '')
                        .trim();

                if (name === '') {
                    return;
                }


                const key =
                    normalizeName(name);


                if (!balances.has(key)) {

                    balances.set(
                        key,
                        {
                            customer_name: name,
                            today_due: 0,
                            today_payment: 0
                        }
                    );
                }


                const balance =
                    balances.get(key);


                balance.today_due =
                    roundMoney(
                        balance.today_due +
                        getNumber(due)
                    );


                balance.today_payment =
                    roundMoney(
                        balance.today_payment +
                        getNumber(payment)
                    );
            }


            getRows(
                '.delivery-payment-row'
            ).forEach(function (row) {

                const customer =
                    row.querySelector(
                        '.delivery-customer'
                    );

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

                const price =
                    getInputNumber(
                        row.querySelector(
                            '.delivery-price-input'
                        )
                    );

                const payment =
                    getInputNumber(
                        row.querySelector(
                            '.delivery-payment'
                        )
                    );


                addCustomer(
                    customer?.value,
                    roundMoney(
                        (slim + round) *
                        price
                    ),
                    payment
                );
            });


            getRows(
                '.driver-delivery-entry'
            ).forEach(function (row) {

                const customer =
                    row.querySelector(
                        '.driver-delivery-customer'
                    );

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

                const payment =
                    getInputNumber(
                        row.querySelector(
                            '.driver-delivery-payment'
                        )
                    );


                addCustomer(
                    customer?.value,
                    roundMoney(
                        (slim + round) *
                        price
                    ),
                    payment
                );
            });


            const customers = [];


            balances.forEach(function (balance) {

                const gross =
                    roundMoney(
                        balance.today_due -
                        balance.today_payment
                    );


                customers.push({

                    customer_name:
                        balance.customer_name,

                    today_due:
                        balance.today_due,

                    today_payment:
                        balance.today_payment,

                    today_balance:
                        Math.max(
                            0,
                            gross
                        ),

                    today_credit:
                        Math.max(
                            0,
                            -gross
                        )
                });
            });


            return {
                customers
            };
        }


        // =========================================================
        // CALCULATE EVERYTHING
        // =========================================================

        function calculateEverything() {

            const shopResult =
                calculateShop();

            refreshShopDeliveryBalances();

            const driverResult =
                calculateDriver();

            const totalDeliveries =
                updateTotalDeliveriesCard();

            const netProfit =
                updateNetProfit();

            return {
                shop: shopResult,
                driver: driverResult,
                totalDeliveries: totalDeliveries,
                netProfit: netProfit,
                debt: getCurrentDraftDebt()
            };

        }


        // =========================================================
        // SHOP COMPUTE BUTTON
        // =========================================================

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


        // =========================================================
        // DRIVER BALANCE BUTTON
        // =========================================================

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


        // =========================================================
        // LIVE CALCULATIONS
        // =========================================================

        document.addEventListener(
            'input',
            function (event) {

                const target =
                    event.target;


                if (!target) {
                    return;
                }


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
                    ||
                    target.classList.contains(
                        'payroll-amount-input'
                    )
                ) {

                    calculateShop();

                    refreshShopDeliveryBalances();

                    updateTotalDeliveriesCard();

                    updateNetProfit();
                }


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


        // =========================================================
        // CHANGE EVENTS
        // =========================================================

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


        // =========================================================
        // MUTATION OBSERVER
        // =========================================================

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


        // =========================================================
        // INITIAL CALCULATION
        // =========================================================

        calculateEverything();


        // =========================================================
        // PUBLIC API
        // =========================================================

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
                updateNetProfit,

            getTotalDeliverySalesReceived:
                getTotalDeliverySalesReceived

        };
    }


    // =============================================================
    // START
    // =============================================================

    if (document.readyState === 'loading') {

        document.addEventListener(
            'DOMContentLoaded',
            initializeDailyClosingCalculations
        );

    } else {

        initializeDailyClosingCalculations();
    }

})();