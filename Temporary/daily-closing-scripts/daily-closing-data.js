/*
 * =========================================================
 * MARCID BLUE
 * TEMPORARY DAILY CLOSING DATA MANAGER
 *
 * File:
 *     Temporary/daily-closing-scripts/daily-closing-data.js
 *
 * PURPOSE
 * ---------------------------------------------------------
 * This file connects the redesigned Daily Closing UI
 * to the standalone PHP backend.
 *
 * It handles DATA and ACTIONS only.
 *
 * It does NOT:
 *
 * - Create delivery rows
 * - Remove delivery rows
 * - Create expense rows
 * - Remove expense rows
 * - Perform financial calculations
 * - Replace daily-closing-calculation.js
 *
 * Those responsibilities remain in their own controllers.
 *
 * =========================================================
 */

(function () {

    'use strict';


    /*
     * =====================================================
     * INITIALIZATION GUARD
     * =====================================================
     *
     * Prevents this controller from being initialized twice.
     */

    if (window.marcidBlueDailyClosingDataInitialized) {
        return;
    }

    window.marcidBlueDailyClosingDataInitialized = true;


    /*
     * =====================================================
     * CONFIGURATION
     * =====================================================
     */

    const CONFIG = {

        backendUrl:
            'daily-closing-scripts/daily-closing-backend.php',

        autosaveDelay:
            700,

        autosaveIndicatorDuration:
            1200

    };


    /*
     * =====================================================
     * API CLIENT
     * =====================================================
     *
     * Only this class communicates with PHP.
     *
     * This prevents fetch() code from being duplicated
     * throughout the application.
     */

    class ApiClient {

        constructor(url) {

            this.url = url;

        }


        async request(action, method = 'GET', body = null) {

            const url =
                `${this.url}?action=${encodeURIComponent(action)}`;

            const options = {

                method,

                credentials:
                    'same-origin',

                headers: {

                    'Accept':
                        'application/json'

                }

            };


            if (method !== 'GET') {

                options.headers['Content-Type'] =
                    'application/json';

                options.body =
                    JSON.stringify(body || {});

            }


            const response =
                await fetch(
                    url,
                    options
                );


            let data;

            try {

                data =
                    await response.json();

            } catch (error) {

                throw new Error(
                    'The server returned an invalid response.'
                );

            }


            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'The request could not be completed.'
                );

            }


            return data;

        }

    }


    /*
     * =====================================================
     * CUSTOMER MANAGER
     * =====================================================
     *
     * Responsibilities:
     *
     * - Store customer list
     * - Populate datalists
     * - Find customers
     * - Load saved prices
     * - Save new customers
     */

    class CustomerManager {

        constructor(api) {

            this.api =
                api;

            this.customers =
                [];

        }


        normalizeName(name) {

            return String(
                name ?? ''
            )
                .trim()
                .toLowerCase();

        }


        find(name) {

            const key =
                this.normalizeName(name);


            if (!key) {
                return null;
            }


            return (
                this.customers.find(
                    customer =>
                        this.normalizeName(
                            customer.customer_name
                        ) === key
                ) || null
            );

        }


        setCustomers(customers) {

            this.customers =
                Array.isArray(customers)
                    ? customers
                    : [];

            this.customers.sort(
                (a, b) =>
                    String(
                        a.customer_name
                    ).localeCompare(
                        String(
                            b.customer_name
                        )
                    )
            );

        }


        populateDatalists() {

            const lists =
                document.querySelectorAll(
                    '#shopDeliveryCustomerList, ' +
                    '#driverDeliveryCustomerList'
                );


            lists.forEach(
                list => {

                    list.innerHTML = '';


                    this.customers.forEach(
                        customer => {

                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                customer.customer_name;

                            list.appendChild(
                                option
                            );

                        }
                    );

                }
            );

        }


        loadPrice(
            customerInput,
            priceInput
        ) {

            if (
                !customerInput ||
                !priceInput
            ) {
                return;
            }


            const name =
                String(
                    customerInput.value || ''
                ).trim();


            if (!name) {
                return;
            }


            const customer =
                this.find(name);


            /*
             * If this is a new customer, do not erase
             * the manually entered price.
             */

            if (!customer) {
                return;
            }


            priceInput.value =
                Number(
                    customer.gallon_price
                ).toFixed(2);


            this.dispatchInput(
                priceInput
            );

            this.dispatchChange(
                priceInput
            );

        }


        async saveCustomer(
            customerInput,
            priceInput
        ) {

            if (
                !customerInput ||
                !priceInput
            ) {
                return null;
            }


            const name =
                String(
                    customerInput.value || ''
                ).trim();


            const price =
                String(
                    priceInput.value || ''
                ).trim();


            if (!name) {
                return null;
            }


            if (
                !price ||
                !Number.isFinite(
                    Number(price)
                ) ||
                Number(price) <= 0
            ) {

                return null;

            }


            /*
             * Existing customer:
             *
             * Do not create a duplicate.
             */

            const existing =
                this.find(name);


            if (existing) {

                return existing;

            }


            const response =
                await this.api.request(
                    'save_customer',
                    'POST',
                    {

                        customer_name:
                            name,

                        gallon_price:
                            price

                    }
                );


            if (!response.customer) {
                return null;
            }


            /*
             * Add the newly created customer
             * to our local list immediately.
             */

            this.customers =
                this.customers.filter(
                    customer =>
                        this.normalizeName(
                            customer.customer_name
                        ) !==
                        this.normalizeName(
                            response.customer.customer_name
                        )
                );


            this.customers.push(
                response.customer
            );


            this.customers.sort(
                (a, b) =>
                    String(
                        a.customer_name
                    ).localeCompare(
                        String(
                            b.customer_name
                        )
                    )
            );


            this.populateDatalists();


            return response.customer;

        }


        dispatchInput(element) {

            element.dispatchEvent(
                new Event(
                    'input',
                    {
                        bubbles: true
                    }
                )
            );

        }


        dispatchChange(element) {

            element.dispatchEvent(
                new Event(
                    'change',
                    {
                        bubbles: true
                    }
                )
            );

        }


        bind() {

            document.addEventListener(
                'input',
                event => {

                    const input =
                        event.target;


                    if (
                        !input.matches(
                            'input[name="delivery_customer[]"], ' +
                            'input[name="driver_delivery_customer[]"], ' +
                            '.driver-delivery-customer'
                        )
                    ) {
                        return;
                    }


                    this.handleCustomerInput(
                        input
                    );

                }
            );


            document.addEventListener(
                'change',
                event => {

                    const input =
                        event.target;


                    if (
                        !input.matches(
                            'input[name="delivery_customer[]"], ' +
                            'input[name="driver_delivery_customer[]"], ' +
                            '.driver-delivery-customer'
                        )
                    ) {
                        return;
                    }


                    this.handleCustomerInput(
                        input
                    );

                }
            );


            document.addEventListener(
                'blur',
                event => {

                    const input =
                        event.target;


                    if (
                        !input.matches(
                            'input[name="delivery_customer[]"], ' +
                            'input[name="driver_delivery_customer[]"], ' +
                            '.driver-delivery-customer'
                        )
                    ) {
                        return;
                    }


                    this.handleCustomerBlur(
                        input
                    );

                },
                true
            );

        }


        handleCustomerInput(
            customerInput
        ) {

            const row =
                customerInput.closest(
                    '.delivery-payment-row, ' +
                    '.driver-delivery-row, ' +
                    '.driver-delivery-entry'
                );


            if (!row) {
                return;
            }


            const priceInput =
                row.querySelector(
                    'input[name="delivery_price_per_gallon[]"], ' +
                    'input[name="driver_delivery_price_per_gallon[]"], ' +
                    '.delivery-price, ' +
                    '.driver-delivery-price'
                );


            this.loadPrice(
                customerInput,
                priceInput
            );


            window.marcidBlueDailyClosingData
                ?.scheduleAutosave();

        }


        async handleCustomerBlur(
            customerInput
        ) {

            const row =
                customerInput.closest(
                    '.delivery-payment-row, ' +
                    '.driver-delivery-row, ' +
                    '.driver-delivery-entry'
                );


            if (!row) {
                return;
            }


            const priceInput =
                row.querySelector(
                    'input[name="delivery_price_per_gallon[]"], ' +
                    'input[name="driver_delivery_price_per_gallon[]"], ' +
                    '.delivery-price, ' +
                    '.driver-delivery-price'
                );


            try {

                await this.saveCustomer(
                    customerInput,
                    priceInput
                );

            } catch (error) {

                console.error(
                    'Customer save failed:',
                    error
                );

            }


            window.marcidBlueDailyClosingData
                ?.scheduleAutosave();

        }

    }


    /*
     * =====================================================
     * FORM DATA COLLECTOR
     * =====================================================
     *
     * Converts the current DOM into the exact structure
     * expected by the backend.
     *
     * It does not calculate totals.
     */

    class FormDataCollector {

        getValue(element) {

            if (!element) {
                return '';
            }

            return String(
                element.value ?? ''
            ).trim();

        }


        collectExpenses() {

            const expenses = [];


            document
                .querySelectorAll(
                    '#expenseRows .expense-row'
                )
                .forEach(
                    row => {

                        const category =
                            row.querySelector(
                                'select[name="expense_category[]"]'
                            );

                        const name =
                            row.querySelector(
                                'input[name="expense_name[]"]'
                            );

                        const amount =
                            row.querySelector(
                                'input[name="expense_amount[]"]'
                            );


                        expenses.push({

                            category:
                                this.getValue(category),

                            name:
                                this.getValue(name),

                            amount:
                                this.getValue(amount)

                        });

                    }
                );


            return expenses;

        }


        collectShopDeliveries() {

            const deliveries = [];


            document
                .querySelectorAll(
                    '#deliveryPaymentRows .delivery-payment-row'
                )
                .forEach(
                    row => {

                        const customer =
                            row.querySelector(
                                'input[name="delivery_customer[]"]'
                            );

                        const slim =
                            row.querySelector(
                                'input[name="delivery_slim[]"]'
                            );

                        const round =
                            row.querySelector(
                                'input[name="delivery_round[]"]'
                            );

                        const payment =
                            row.querySelector(
                                'input[name="delivery_payment[]"]'
                            );

                        const price =
                            row.querySelector(
                                'input[name="delivery_price_per_gallon[]"]'
                            );

                        const method =
                            row.querySelector(
                                'select[name="delivery_method[]"]'
                            );


                        deliveries.push({

                            customer:
                                this.getValue(customer),

                            slim:
                                this.getValue(slim),

                            round:
                                this.getValue(round),

                            payment:
                                this.getValue(payment),

                            price:
                                this.getValue(price),

                            method:
                                this.getValue(method) ||
                                'Cash'

                        });

                    }
                );


            return deliveries;

        }


        collectDriverExpenses() {

            const expenses = [];


            document
                .querySelectorAll(
                    '#driverExpenseRows .driver-expense-row'
                )
                .forEach(
                    row => {

                        const category =
                            row.querySelector(
                                'select[name="driver_expense_category[]"]'
                            );

                        const name =
                            row.querySelector(
                                'input[name="driver_expense_name[]"]'
                            );

                        const amount =
                            row.querySelector(
                                'input[name="driver_expense_amount[]"]'
                            );


                        expenses.push({

                            category:
                                this.getValue(category),

                            name:
                                this.getValue(name),

                            amount:
                                this.getValue(amount)

                        });

                    }
                );


            return expenses;

        }


        collectDriverDeliveries() {

            const deliveries = [];


            document
                .querySelectorAll(
                    '#driverDeliveryPaymentRows .driver-delivery-entry, ' +
                    '#driverDeliveryPaymentRows .driver-delivery-row'
                )
                .forEach(
                    row => {

                        const customer =
                            row.querySelector(
                                'input[name="driver_delivery_customer[]"], ' +
                                '.driver-delivery-customer'
                            );

                        const slim =
                            row.querySelector(
                                'input[name="driver_delivery_slim[]"]'
                            );

                        const round =
                            row.querySelector(
                                'input[name="driver_delivery_round[]"]'
                            );

                        const payment =
                            row.querySelector(
                                'input[name="driver_delivery_payment[]"]'
                            );

                        const price =
                            row.querySelector(
                                'input[name="driver_delivery_price_per_gallon[]"], ' +
                                '.driver-delivery-price'
                            );

                        const method =
                            row.querySelector(
                                'select[name="driver_delivery_method[]"]'
                            );


                        deliveries.push({

                            customer:
                                this.getValue(customer),

                            slim:
                                this.getValue(slim),

                            round:
                                this.getValue(round),

                            payment:
                                this.getValue(payment),

                            price:
                                this.getValue(price),

                            method:
                                this.getValue(method) ||
                                'Cash'

                        });

                    }
                );


            return deliveries;

        }


        collect() {

            const walkInMoney =
                document.querySelector(
                    '#walk_in_money'
                );


            const walkInCustomers =
                document.querySelector(
                    '#walk_in_customers'
                );


            const driverMoney =
                document.querySelector(
                    '#driver_money_received'
                );


            return {

                walk_in_money:
                    this.getValue(
                        walkInMoney
                    ),

                walk_in_customers:
                    this.getValue(
                        walkInCustomers
                    ),

                expenses:
                    this.collectExpenses(),

                deliveries:
                    this.collectShopDeliveries(),

                driver: {

                    money_received:
                        this.getValue(
                            driverMoney
                        ),

                    expenses:
                        this.collectDriverExpenses(),

                    deliveries:
                        this.collectDriverDeliveries()

                }

            };

        }

    }


    /*
     * =====================================================
     * DRAFT MANAGER
     * =====================================================
     *
     * Responsible only for:
     *
     * - Autosave
     * - Load draft
     * - Restore draft
     *
     * It does not calculate anything.
     */

    class DraftManager {

        constructor(
            api,
            collector,
            customerManager
        ) {

            this.api =
                api;

            this.collector =
                collector;

            this.customerManager =
                customerManager;

            this.timer =
                null;

            this.inProgress =
                false;

            this.queued =
                false;

        }


        schedule() {

            clearTimeout(
                this.timer
            );


            this.timer =
                setTimeout(
                    () => {

                        this.save();

                    },
                    CONFIG.autosaveDelay
                );

        }


        async save() {

            if (
                this.inProgress
            ) {

                this.queued =
                    true;

                return;

            }


            const dailyId =
                window.marcidBlueDailyClosing
                    ?.dailyId ||
                window.marcidBlueDailyClosingData
                    ?.dailyId ||
                null;


            if (!dailyId) {
                return;
            }


            this.inProgress =
                true;


            try {

                const draft =
                    this.collector.collect();


                await this.api.request(
                    'save_draft',
                    'POST',
                    {

                        daily_id:
                            dailyId,

                        draft

                    }
                );


                this.showStatus(
                    'saved'
                );

            } catch (error) {

                console.error(
                    'Draft autosave failed:',
                    error
                );


                this.showStatus(
                    'error'
                );

            } finally {

                this.inProgress =
                    false;


                if (this.queued) {

                    this.queued =
                        false;

                    this.schedule();

                }

            }

        }


        async load() {

            const response =
                await this.api.request(
                    'load_draft'
                );


            if (
                !response.has_draft ||
                !response.draft
            ) {

                return null;

            }


            return response.draft;

        }


        async restore(
            draft
        ) {

            if (!draft) {
                return;
            }


            /*
             * -------------------------------------------------
             * SHOP
             * -------------------------------------------------
             */

            this.setInputValue(
                '#walk_in_money',
                draft.walk_in_money
            );


            /*
             * Walk-in customers are calculated by the
             * calculation controller.
             *
             * We intentionally do NOT manually calculate
             * them here.
             */


            /*
             * -------------------------------------------------
             * SHOP EXPENSES
             * -------------------------------------------------
             */

            this.restoreExpenses(
                draft.expenses || []
            );


            /*
             * -------------------------------------------------
             * SHOP DELIVERIES
             * -------------------------------------------------
             */

            await this.restoreShopDeliveries(
                draft.deliveries || []
            );


            /*
             * -------------------------------------------------
             * DRIVER
             * -------------------------------------------------
             */

            this.setInputValue(
                '#driver_money_received',
                draft.driver?.money_received ?? 0
            );


            this.restoreDriverExpenses(
                draft.driver?.expenses || []
            );


            await this.restoreDriverDeliveries(
                draft.driver?.deliveries || []
            );


            this.triggerCalculation();


            /*
             * Give the UI controllers a moment to finish
             * dynamically-created rows before binding data
             * listeners again.
             */

            setTimeout(
                () => {

                    this.customerManager
                        .populateDatalists();

                },
                50
            );

        }


        restoreExpenses(
            expenses
        ) {

            if (!Array.isArray(expenses)) {
                return;
            }


            const rows =
                document.querySelectorAll(
                    '#expenseRows .expense-row'
                );


            /*
             * The existing UI already creates the initial row.
             *
             * If more rows are needed, we use the existing
             * "Add Expense" button instead of creating our
             * own row HTML.
             */

            this.ensureRowCount(
                '#expenseRows .expense-row',
                expenses.length,
                [
                    '#addExpenseButton',
                    '[data-action="add-expense"]',
                    '.add-expense-button'
                ]
            );


            const restoredRows =
                document.querySelectorAll(
                    '#expenseRows .expense-row'
                );


            expenses.forEach(
                (expense, index) => {

                    const row =
                        restoredRows[index];


                    if (!row) {
                        return;
                    }


                    this.setInputValue(
                        row.querySelector(
                            'select[name="expense_category[]"]'
                        ),
                        expense.category
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="expense_name[]"]'
                        ),
                        expense.name
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="expense_amount[]"]'
                        ),
                        expense.amount
                    );

                }
            );

        }


        async restoreShopDeliveries(
            deliveries
        ) {

            if (!Array.isArray(deliveries)) {
                return;
            }


            this.ensureRowCount(
                '#deliveryPaymentRows .delivery-payment-row',
                deliveries.length,
                [
                    '#addDeliveryPaymentButton',
                    '#addDeliveryButton',
                    '[data-action="add-delivery"]',
                    '.add-delivery-payment-button'
                ]
            );


            const rows =
                document.querySelectorAll(
                    '#deliveryPaymentRows .delivery-payment-row'
                );


            deliveries.forEach(
                (delivery, index) => {

                    const row =
                        rows[index];


                    if (!row) {
                        return;
                    }


                    const customer =
                        row.querySelector(
                            'input[name="delivery_customer[]"]'
                        );


                    const price =
                        row.querySelector(
                            'input[name="delivery_price_per_gallon[]"]'
                        );


                    this.setInputValue(
                        customer,
                        delivery.customer
                    );


                    /*
                     * Set the saved customer price first.
                     *
                     * If this customer exists, the database
                     * value is authoritative.
                     */

                    this.customerManager.loadPrice(
                        customer,
                        price
                    );


                    /*
                     * If this is a new customer, restore the
                     * draft's manually entered price.
                     */

                    if (
                        !this.customerManager.find(
                            delivery.customer
                        )
                    ) {

                        this.setInputValue(
                            price,
                            delivery.price
                        );

                    }


                    this.setInputValue(
                        row.querySelector(
                            'input[name="delivery_slim[]"]'
                        ),
                        delivery.slim
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="delivery_round[]"]'
                        ),
                        delivery.round
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="delivery_payment[]"]'
                        ),
                        delivery.payment
                    );


                    this.setInputValue(
                        row.querySelector(
                            'select[name="delivery_method[]"]'
                        ),
                        delivery.method
                    );


                    this.dispatchInput(
                        customer
                    );

                    this.dispatchChange(
                        customer
                    );

                }
            );

        }


        restoreDriverExpenses(
            expenses
        ) {

            if (!Array.isArray(expenses)) {
                return;
            }


            this.ensureRowCount(
                '#driverExpenseRows .driver-expense-row',
                expenses.length,
                [
                    '#addDriverExpenseButton',
                    '[data-action="add-driver-expense"]',
                    '.add-driver-expense-button'
                ]
            );


            const rows =
                document.querySelectorAll(
                    '#driverExpenseRows .driver-expense-row'
                );


            expenses.forEach(
                (expense, index) => {

                    const row =
                        rows[index];


                    if (!row) {
                        return;
                    }


                    this.setInputValue(
                        row.querySelector(
                            'select[name="driver_expense_category[]"]'
                        ),
                        expense.category
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="driver_expense_name[]"]'
                        ),
                        expense.name
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="driver_expense_amount[]"]'
                        ),
                        expense.amount
                    );

                }
            );

        }


        async restoreDriverDeliveries(
            deliveries
        ) {

            if (!Array.isArray(deliveries)) {
                return;
            }


            this.ensureRowCount(
                '#driverDeliveryPaymentRows .driver-delivery-entry, ' +
                '#driverDeliveryPaymentRows .driver-delivery-row',
                deliveries.length,
                [
                    '#addDriverDeliveryButton',
                    '#addDriverDeliveryPaymentButton',
                    '[data-action="add-driver-delivery"]',
                    '.add-driver-delivery-button'
                ]
            );


            const rows =
                document.querySelectorAll(
                    '#driverDeliveryPaymentRows .driver-delivery-entry, ' +
                    '#driverDeliveryPaymentRows .driver-delivery-row'
                );


            deliveries.forEach(
                (delivery, index) => {

                    const row =
                        rows[index];


                    if (!row) {
                        return;
                    }


                    const customer =
                        row.querySelector(
                            'input[name="driver_delivery_customer[]"], ' +
                            '.driver-delivery-customer'
                        );


                    const price =
                        row.querySelector(
                            'input[name="driver_delivery_price_per_gallon[]"], ' +
                            '.driver-delivery-price'
                        );


                    this.setInputValue(
                        customer,
                        delivery.customer
                    );


                    this.customerManager.loadPrice(
                        customer,
                        price
                    );


                    if (
                        !this.customerManager.find(
                            delivery.customer
                        )
                    ) {

                        this.setInputValue(
                            price,
                            delivery.price
                        );

                    }


                    this.setInputValue(
                        row.querySelector(
                            'input[name="driver_delivery_slim[]"]'
                        ),
                        delivery.slim
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="driver_delivery_round[]"]'
                        ),
                        delivery.round
                    );


                    this.setInputValue(
                        row.querySelector(
                            'input[name="driver_delivery_payment[]"]'
                        ),
                        delivery.payment
                    );


                    this.setInputValue(
                        row.querySelector(
                            'select[name="driver_delivery_method[]"]'
                        ),
                        delivery.method
                    );


                    this.dispatchInput(
                        customer
                    );

                    this.dispatchChange(
                        customer
                    );

                }
            );

        }


        ensureRowCount(
            selector,
            requiredCount,
            buttonSelectors
        ) {

            if (
                requiredCount <= 0
            ) {
                return;
            }


            let rows =
                document.querySelectorAll(
                    selector
                );


            while (
                rows.length < requiredCount
            ) {

                const button =
                    this.findFirstButton(
                        buttonSelectors
                    );


                if (!button) {

                    console.warn(
                        'Unable to find existing row-add button for:',
                        selector
                    );

                    break;

                }


                button.click();


                rows =
                    document.querySelectorAll(
                        selector
                    );

            }

        }


        findFirstButton(
            selectors
        ) {

            for (
                const selector of selectors
            ) {

                const element =
                    document.querySelector(
                        selector
                    );


                if (element) {
                    return element;
                }

            }


            return null;

        }


        setInputValue(
            selectorOrElement,
            value
        ) {

            const element =
                typeof selectorOrElement === 'string'
                    ? document.querySelector(
                        selectorOrElement
                    )
                    : selectorOrElement;


            if (!element) {
                return;
            }


            element.value =
                value ?? '';

        }


        dispatchInput(element) {

            if (!element) {
                return;
            }


            element.dispatchEvent(
                new Event(
                    'input',
                    {
                        bubbles: true
                    }
                )
            );

        }


        dispatchChange(element) {

            if (!element) {
                return;
            }


            element.dispatchEvent(
                new Event(
                    'change',
                    {
                        bubbles: true
                    }
                )
            );

        }


        triggerCalculation() {

            /*
             * We do not perform calculations here.
             *
             * We simply notify the existing calculation
             * controller that data changed.
             */

            document.dispatchEvent(
                new Event(
                    'input',
                    {
                        bubbles: true
                    }
                )
            );


            document.dispatchEvent(
                new Event(
                    'change',
                    {
                        bubbles: true
                    }
                )
            );

        }


        showStatus(
            status
        ) {

            const element =
                document.querySelector(
                    '[data-daily-closing-autosave-status]'
                );


            if (!element) {
                return;
            }


            if (status === 'saved') {

                element.textContent =
                    'Draft saved';

                element.classList.remove(
                    'is-error'
                );

                element.classList.add(
                    'is-saved'
                );

            } else if (
                status === 'error'
            ) {

                element.textContent =
                    'Draft save failed';

                element.classList.remove(
                    'is-saved'
                );

                element.classList.add(
                    'is-error'
                );

            }

        }

    }


    /*
     * =====================================================
     * DAILY CLOSING ACTION MANAGER
     * =====================================================
     *
     * Handles:
     *
     * - Context loading
     * - Reset
     * - Finalize
     *
     * This class does not perform calculations.
     */

    class DailyClosingActionManager {

        constructor(
            api,
            customerManager,
            draftManager
        ) {

            this.api =
                api;

            this.customerManager =
                customerManager;

            this.draftManager =
                draftManager;

            this.dailyId =
                null;

            this.businessDate =
                null;

            this.status =
                null;

        }


        async initialize() {

            const context =
                await this.api.request(
                    'context'
                );


            if (
                !context.daily
            ) {

                throw new Error(
                    'Daily closing context is unavailable.'
                );

            }


            this.dailyId =
                Number(
                    context.daily.daily_id
                );


            this.businessDate =
                context.daily.business_date;


            this.status =
                context.daily.status;


            this.customerManager
                .setCustomers(
                    context.customers || []
                );


            this.customerManager
                .populateDatalists();


            this.updateGlobalState();

        }


        updateGlobalState() {

            window.marcidBlueDailyClosingData = {

                dailyId:
                    this.dailyId,

                businessDate:
                    this.businessDate,

                status:
                    this.status,

                scheduleAutosave:
                    () => {

                        window.marcidBlueDailyClosingDataManager
                            ?.scheduleAutosave();

                    }

            };

        }


        async reset() {

            const confirmed =
                window.confirm(
                    'Reset today’s Daily Closing?\n\n' +
                    'This will remove the current daily entries and saved draft for the open day.'
                );


            if (!confirmed) {
                return false;
            }


            await this.api.request(
                'reset',
                'POST',
                {

                    daily_id:
                        this.dailyId

                }
            );


            /*
             * Reloading is intentional.
             *
             * It returns the page to its original blank UI
             * without duplicating every row controller's
             * reset logic here.
             */

            window.location.reload();

            return true;

        }


        async finalize() {

            /*
             * IMPORTANT:
             *
             * Finalization uses the autosaved draft in MySQL.
             *
             * Therefore save the latest DOM state FIRST.
             */

            await this.draftManager.save();


            const response =
                await this.api.request(
                    'finalize',
                    'POST',
                    {

                        daily_id:
                            this.dailyId

                    }
                );


            return response;

        }

    }


    /*
     * =====================================================
     * UI ACTION BINDINGS
     * =====================================================
     *
     * Only the two page-level buttons are controlled here:
     *
     * - Reset
     * - Finalize & Close Day
     *
     * Existing row buttons remain owned by the existing
     * inline UI controllers.
     */

    class DailyClosingUI {

        constructor(
            actionManager
        ) {

            this.actionManager =
                actionManager;

        }


        bind() {

            const resetButton =
                document.querySelector(
                    '#resetDailyClosingButton'
                );


            if (resetButton) {

                resetButton.addEventListener(
                    'click',
                    async () => {

                        try {

                            resetButton.disabled =
                                true;

                            await this.actionManager
                                .reset();

                        } catch (error) {

                            console.error(
                                error
                            );

                            window.alert(
                                error.message ||
                                'Unable to reset the daily closing.'
                            );


                            resetButton.disabled =
                                false;

                        }

                    }
                );

            }


            const finalizeButton =
                document.querySelector(
                    '#finalizeDailyClosingButton'
                );


            if (finalizeButton) {

                finalizeButton.addEventListener(
                    'click',
                    async () => {

                        const confirmed =
                            window.confirm(
                                'Finalize and close this day?\n\n' +
                                'After finalization, the daily record will be closed.'
                            );


                        if (!confirmed) {
                            return;
                        }


                        try {

                            finalizeButton.disabled =
                                true;


                            const response =
                                await this.actionManager
                                    .finalize();


                            window.alert(
                                response.message ||
                                'Daily closing finalized successfully.'
                            );


                            window.location.reload();

                        } catch (error) {

                            console.error(
                                error
                            );


                            window.alert(
                                error.message ||
                                'Unable to finalize the daily closing.'
                            );


                            finalizeButton.disabled =
                                false;

                        }

                    }
                );

            }

        }

    }


    /*
     * =====================================================
     * DATA MANAGER
     * =====================================================
     *
     * Main coordinator.
     *
     * This class does not contain business calculations.
     * It simply coordinates:
     *
     * API
     * CustomerManager
     * FormDataCollector
     * DraftManager
     * ActionManager
     * UI
     */

    class DailyClosingDataManager {

        constructor() {

            this.api =
                new ApiClient(
                    CONFIG.backendUrl
                );


            this.customerManager =
                new CustomerManager(
                    this.api
                );


            this.collector =
                new FormDataCollector();


            this.draftManager =
                new DraftManager(
                    this.api,
                    this.collector,
                    this.customerManager
                );


            this.actionManager =
                new DailyClosingActionManager(
                    this.api,
                    this.customerManager,
                    this.draftManager
                );


            this.ui =
                new DailyClosingUI(
                    this.actionManager
                );


            this.initialized =
                false;

        }


        async initialize() {

            if (this.initialized) {
                return;
            }


            this.initialized =
                true;


            try {

                /*
                 * 1. Load daily context and customers.
                 */

                await this.actionManager
                    .initialize();


                /*
                 * 2. Bind customer autocomplete.
                 */

                this.customerManager
                    .bind();


                /*
                 * 3. Bind page-level buttons.
                 */

                this.ui
                    .bind();


                /*
                 * 4. Watch all editable fields for changes.
                 */

                this.bindAutosaveEvents();


                /*
                 * 5. Restore existing draft.
                 */

                const draft =
                    await this.draftManager
                        .load();


                if (draft) {

                    await this.draftManager
                        .restore(
                            draft
                        );

                }


                /*
                 * 6. Watch dynamically-created rows.
                 *
                 * We do not create the rows here.
                 * Existing UI controllers remain responsible
                 * for doing that.
                 */

                this.observeDynamicRows();


                /*
                 * 7. Final calculation refresh.
                 */

                this.triggerCalculation();


                console.info(
                    'Marcid Blue Daily Closing Data Manager initialized.'
                );

            } catch (error) {

                console.error(
                    'Daily Closing initialization failed:',
                    error
                );


                window.alert(
                    error.message ||
                    'Unable to initialize Daily Closing.'
                );

            }

        }


        bindAutosaveEvents() {

            document.addEventListener(
                'input',
                event => {

                    if (
                        this.isTrackedField(
                            event.target
                        )
                    ) {

                        this.scheduleAutosave();

                    }

                }
            );


            document.addEventListener(
                'change',
                event => {

                    if (
                        this.isTrackedField(
                            event.target
                        )
                    ) {

                        this.scheduleAutosave();

                    }

                }
            );

        }


        isTrackedField(
            element
        ) {

            if (!element) {
                return false;
            }


            return element.matches(
                '#walk_in_money, ' +

                '#driver_money_received, ' +

                '#expenseRows input, ' +
                '#expenseRows select, ' +

                '#deliveryPaymentRows input, ' +
                '#deliveryPaymentRows select, ' +

                '#driverExpenseRows input, ' +
                '#driverExpenseRows select, ' +

                '#driverDeliveryPaymentRows input, ' +
                '#driverDeliveryPaymentRows select'
            );

        }


        scheduleAutosave() {

            this.draftManager
                .schedule();

        }


        observeDynamicRows() {

            const observer =
                new MutationObserver(
                    () => {

                        this.customerManager
                            .populateDatalists();

                    }
                );


            observer.observe(
                document.body,
                {

                    childList:
                        true,

                    subtree:
                        true

                }
            );

        }


        triggerCalculation() {

            /*
             * daily-closing-calculation.js owns calculations.
             *
             * We simply trigger normal DOM events so its
             * existing listeners can recalculate.
             */

            const fields =
                document.querySelectorAll(
                    '#walk_in_money, ' +
                    '#driver_money_received, ' +
                    '#expenseRows input, ' +
                    '#expenseRows select, ' +
                    '#deliveryPaymentRows input, ' +
                    '#deliveryPaymentRows select, ' +
                    '#driverExpenseRows input, ' +
                    '#driverExpenseRows select, ' +
                    '#driverDeliveryPaymentRows input, ' +
                    '#driverDeliveryPaymentRows select'
                );


            fields.forEach(
                field => {

                    field.dispatchEvent(
                        new Event(
                            'input',
                            {
                                bubbles: true
                            }
                        )
                    );

                }
            );

        }

    }


    /*
     * =====================================================
     * APPLICATION STARTUP
     * =====================================================
     */

    const manager =
        new DailyClosingDataManager();


    /*
     * Public reference.
     *
     * Useful for debugging and for the other controllers
     * when they need to request an autosave.
     */

    window.marcidBlueDailyClosingDataManager =
        manager;


    window.marcidBlueDailyClosingData =
        {

            scheduleAutosave:
                () => {

                    manager.scheduleAutosave();

                },

            getDailyId:
                () => {

                    return manager.actionManager
                        .dailyId;

                },

            getCustomers:
                () => {

                    return manager.customerManager
                        .customers;

                }

        };


    /*
     * Start after the DOM is ready.
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            () => {

                manager.initialize();

            },
            {
                once: true
            }
        );

    } else {

        manager.initialize();

    }


})();