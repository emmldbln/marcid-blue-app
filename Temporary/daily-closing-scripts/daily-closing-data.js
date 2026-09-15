/*
 * =========================================================
 * MARCID BLUE
 * TEMPORARY DAILY CLOSING DATA CONTROLLER
 *
 * File:
 *     Temporary/daily-closing-scripts/daily-closing-data.js
 *
 * Responsibilities:
 *
 *     - Load daily context
 *     - Load customer list
 *     - Customer autocomplete
 *     - Load saved customer price
 *     - Save new customers
 *     - Collect current form data
 *     - Autosave draft
 *     - Restore saved draft
 *     - Reset daily closing
 *     - Finalize and close daily record
 *
 * This file does NOT:
 *
 *     - Create delivery rows
 *     - Remove delivery rows
 *     - Create expense rows
 *     - Remove expense rows
 *     - Perform calculations
 *     - Replace daily-closing-calculation.js
 *
 * =========================================================
 */

(function () {

    'use strict';


    /*
     * =====================================================
     * INITIALIZATION GUARD
     * =====================================================
     */

    if (
        window.marcidBlueDailyClosingDataInitialized
    ) {
        return;
    }

    window.marcidBlueDailyClosingDataInitialized = true;


    /*
     * =====================================================
     * CONFIGURATION
     * =====================================================
     */

    const BACKEND_URL =
        'daily-closing-scripts/daily-closing-backend.php';

    const AUTOSAVE_DELAY = 700;


    /*
     * =====================================================
     * STATE
     * =====================================================
     */

    const state = {

        dailyId: null,

        businessDate: null,

        status: null,

        customers: [],

        restoring: false,

        autosaveTimer: null,

        autosaveInProgress: false,

        autosaveQueued: false,

        initialized: false

    };


    /*
     * =====================================================
     * BASIC HELPERS
     * =====================================================
     */

    function getElement(selector) {

        return document.querySelector(selector);

    }


    function getElements(selector) {

        return Array.from(
            document.querySelectorAll(selector)
        );

    }


    function getValue(element) {

        if (!element) {
            return '';
        }

        return String(
            element.value ?? ''
        ).trim();

    }


    function setValue(element, value) {

        if (!element) {
            return;
        }

        element.value =
            value ?? '';

    }


    function numberValue(element) {

        if (!element) {
            return 0;
        }

        const value =
            parseFloat(element.value);

        if (
            Number.isNaN(value)
        ) {
            return 0;
        }

        return value;

    }


    function dispatchInput(element) {

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


    function dispatchChange(element) {

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


    function escapeHtml(value) {

        return String(
            value ?? ''
        )
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    }


    /*
     * =====================================================
     * BACKEND REQUEST
     * =====================================================
     */

    async function request(
        action,
        options = {}
    ) {

        const method =
            options.method || 'GET';

        const url =
            `${BACKEND_URL}?action=${encodeURIComponent(action)}`;


        const fetchOptions = {

            method,

            credentials: 'same-origin',

            headers: {

                'Accept':
                    'application/json'

            }

        };


        if (
            method !== 'GET'
        ) {

            fetchOptions.headers[
                'Content-Type'
            ] =
                'application/json';


            fetchOptions.body =
                JSON.stringify(
                    options.body || {}
                );

        }


        const response =
            await fetch(
                url,
                fetchOptions
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


    /*
     * =====================================================
     * CUSTOMER DATA
     * =====================================================
     */

    function customerKey(name) {

        return String(
            name ?? ''
        )
            .trim()
            .toLowerCase();

    }


    function findCustomer(name) {

        const key =
            customerKey(name);


        if (!key) {
            return null;
        }


        return (
            state.customers.find(
                customer =>
                    customerKey(
                        customer.customer_name
                    ) === key
            ) ||
            null
        );

    }


    function updateCustomerDatalist() {

        const lists =
            getElements(
                '#shopDeliveryCustomerList, #driverDeliveryCustomerList'
            );


        lists.forEach(
            list => {

                list.innerHTML = '';


                state.customers.forEach(
                    customer => {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            customer.customer_name;


                        /*
                         * Price is intentionally not displayed
                         * inside the datalist.
                         *
                         * The price field is populated separately.
                         */

                        list.appendChild(
                            option
                        );

                    }
                );

            }
        );

    }


    function loadCustomerPrice(
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
            getValue(
                customerInput
            );


        if (!name) {
            return;
        }


        const customer =
            findCustomer(name);


        if (!customer) {

            /*
             * New customer.
             *
             * Do NOT erase the manually entered price.
             *
             * This is important because the user must be
             * able to enter a price for a new customer.
             */

            return;

        }


        setValue(
            priceInput,
            Number(
                customer.gallon_price
            ).toFixed(2)
        );


        dispatchInput(
            priceInput
        );


        dispatchChange(
            priceInput
        );

    }


    function handleCustomerInput(
        event
    ) {

        const customerInput =
            event.target;


        if (
            !customerInput
        ) {
            return;
        }


        const row =
            customerInput.closest(
                '.delivery-payment-row, .driver-delivery-row, .driver-delivery-entry'
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


        loadCustomerPrice(
            customerInput,
            priceInput
        );


        scheduleAutosave();

    }


    function bindCustomerInputs() {

        const customerInputs =
            getElements(
                'input[name="delivery_customer[]"], ' +
                'input[name="driver_delivery_customer[]"], ' +
                '.driver-delivery-customer'
            );


        customerInputs.forEach(
            input => {

                if (
                    input.dataset.dataControllerBound === '1'
                ) {
                    return;
                }


                input.dataset.dataControllerBound =
                    '1';


                input.addEventListener(
                    'input',
                    handleCustomerInput
                );


                input.addEventListener(
                    'change',
                    handleCustomerInput
                );


                input.addEventListener(
                    'blur',
                    handleCustomerInput
                );

            }
        );

    }


    /*
     * =====================================================
     * SAVE NEW CUSTOMER
     * =====================================================
     *
     * We only save a customer when:
     *
     *     - the name is entered
     *     - a valid price is entered
     *     - the name does not already exist
     *
     * Existing customer prices are never overwritten here.
     * =====================================================
     */

    async function saveCustomerIfNeeded(
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
            getValue(
                customerInput
            );


        const price =
            getValue(
                priceInput
            );


        if (
            !name ||
            !price
        ) {
            return null;
        }


        if (
            !Number.isFinite(
                Number(price)
            ) ||
            Number(price) <= 0
        ) {
            return null;
        }


        const existing =
            findCustomer(name);


        if (existing) {

            /*
             * Existing customer.
             *
             * Never overwrite the saved price.
             */

            return existing;

        }


        try {

            const response =
                await request(
                    'save_customer',
                    {

                        method: 'POST',

                        body: {

                            customer_name:
                                name,

                            gallon_price:
                                price

                        }

                    }
                );


            if (
                response.customer
            ) {

                const customer =
                    response.customer;


                state.customers =
                    state.customers.filter(
                        item =>
                            customerKey(
                                item.customer_name
                            ) !==
                            customerKey(
                                customer.customer_name
                            )
                    );


                state.customers.push(
                    customer
                );


                state.customers.sort(
                    (
                        a,
                        b
                    ) =>
                        String(
                            a.customer_name
                        ).localeCompare(
                            String(
                                b.customer_name
                            )
                        )
                );


                updateCustomerDatalist();


                return customer;

            }

        } catch (error) {

            console.error(
                'Customer save failed:',
                error
            );

            /*
             * Do not interrupt normal data entry.
             *
             * Finalization will still validate the customer.
             */

        }


        return null;

    }


    function bindNewCustomerSaving() {

        const inputs =
            getElements(
                'input[name="delivery_customer[]"], ' +
                'input[name="driver_delivery_customer[]"], ' +
                '.driver-delivery-customer'
            );


        inputs.forEach(
            customerInput => {

                if (
                    customerInput.dataset.customerSaveBound === '1'
                ) {
                    return;
                }


                customerInput.dataset.customerSaveBound =
                    '1';


                customerInput.addEventListener(
                    'blur',
                    async () => {

                        const row =
                            customerInput.closest(
                                '.delivery-payment-row, .driver-delivery-row, .driver-delivery-entry'
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


                        await saveCustomerIfNeeded(
                            customerInput,
                            priceInput
                        );

                    }
                );

            }
        );

    }


    /*
     * =====================================================
     * ROW EVENT BINDING
     * =====================================================
     *
     * Existing PHP controllers create rows dynamically.
     *
     * Therefore this function can be called repeatedly
     * after a row is added without duplicating listeners.
     * =====================================================
     */

    function bindDynamicRows() {

        bindCustomerInputs();

        bindNewCustomerSaving();

    }


    /*
     * =====================================================
     * COLLECT SHOP EXPENSES
     * =====================================================
     */

    function collectExpenses() {

        const expenses = [];


        getElements(
            '#expenseRows .expense-row'
        ).forEach(
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
                        getValue(category),

                    name:
                        getValue(name),

                    amount:
                        getValue(amount)

                });

            }
        );


        return expenses;

    }


    /*
     * =====================================================
     * COLLECT SHOP DELIVERIES
     * =====================================================
     */

    function collectShopDeliveries() {

        const deliveries = [];


        getElements(
            '#deliveryPaymentRows .delivery-payment-row'
        ).forEach(
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
                        getValue(customer),

                    slim:
                        getValue(slim),

                    round:
                        getValue(round),

                    payment:
                        getValue(payment),

                    price:
                        getValue(price),

                    method:
                        getValue(method) ||
                        'Cash'

                });

            }
        );


        return deliveries;

    }


    /*
     * =====================================================
     * COLLECT DRIVER EXPENSES
     * =====================================================
     */

    function collectDriverExpenses() {

        const expenses = [];


        getElements(
            '#driverExpenseRows .driver-expense-row'
        ).forEach(
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
                        getValue(category),

                    name:
                        getValue(name),

                    amount:
                        getValue(amount)

                });

            }
        );


        return expenses;

    }


    /*
     * =====================================================
     * COLLECT DRIVER DELIVERIES
     * =====================================================
     */

    function collectDriverDeliveries() {

        const deliveries = [];


        getElements(
            '#driverDeliveryPaymentRows .driver-delivery-entry, ' +
            '#driverDeliveryPaymentRows .driver-delivery-row'
        ).forEach(
            row => {

                const customer =
                    row.querySelector(
                        'input[name="driver_delivery_customer[]"]'
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
                        'input[name="driver_delivery_price_per_gallon[]"]'
                    );


                const method =
                    row.querySelector(
                        'select[name="driver_delivery_method[]"]'
                    );


                deliveries.push({

                    customer:
                        getValue(customer),

                    slim:
                        getValue(slim),

                    round:
                        getValue(round),

                    payment:
                        getValue(payment),

                    price:
                        getValue(price),

                    method:
                        getValue(method) ||
                        'Cash'

                });

            }
        );


        return deliveries;

    }


    /*
     * =====================================================
     * COLLECT COMPLETE DRAFT
     * =====================================================
     */

    function collectDraft() {

        const walkInMoney =
            getElement(
                '#walk_in_money'
            );


        const walkInCustomers =
            getElement(
                '#walk_in_customers'
            );


        const driverMoney =
            getElement(
                '#driver_money_received'
            );


        return {

            walk_in_money:
                getValue(
                    walkInMoney
                ),

            walk_in_customers:
                getValue(
                    walkInCustomers
                ),

            expenses:
                collectExpenses(),

            deliveries:
                collectShopDeliveries(),

            driver: {

                money_received:
                    getValue(
                        driverMoney
                    ),

                expenses:
                    collectDriverExpenses(),

                deliveries:
                    collectDriverDeliveries()

            }

        };

    }


    /*
     * =====================================================
     * AUTOSAVE
     * =====================================================
     */

    function scheduleAutosave() {

        if (
            state.restoring
        ) {
            return;
        }


        if (
            !state.dailyId
        ) {
            return;
        }


        clearTimeout(
            state.autosaveTimer
        );


        state.autosaveTimer =
            setTimeout(
                () => {

                    saveDraft();

                },
                AUTOSAVE_DELAY
            );

    }


    async function saveDraft() {

        if (
            state.restoring ||
            !state.dailyId
        ) {
            return;
        }


        if (
            state.autosaveInProgress
        ) {

            state.autosaveQueued =
                true;

            return;

        }


        state.autosaveInProgress =
            true;


        try {

            await request(
                'save_draft',
                {

                    method: 'POST',

                    body: {

                        daily_id:
                            state.dailyId,

                        draft:
                            collectDraft()

                    }

                }
            );

        } catch (error) {

            console.error(
                'Daily draft autosave failed:',
                error
            );

        } finally {

            state.autosaveInProgress =
                false;


            if (
                state.autosaveQueued
            ) {

                state.autosaveQueued =
                    false;

                scheduleAutosave();

            }

        }

    }


    /*
     * =====================================================
     * AUTOSAVE EVENT MONITOR
     * =====================================================
     */

    function bindAutosaveEvents() {

        const container =
            document.querySelector(
                '.daily-closing-page, main, body'
            );


        if (!container) {
            return;
        }


        if (
            container.dataset.autosaveBound === '1'
        ) {
            return;
        }


        container.dataset.autosaveBound =
            '1';


        container.addEventListener(
            'input',
            event => {

                if (
                    event.target.matches(
                        'input, textarea'
                    )
                ) {

                    scheduleAutosave();

                }

            }
        );


        container.addEventListener(
            'change',
            event => {

                if (
                    event.target.matches(
                        'input, select, textarea'
                    )
                ) {

                    scheduleAutosave();

                }

            }
        );


        /*
         * Existing UI uses dynamically created rows.
         *
         * Event delegation means newly created rows are
         * automatically covered.
         */

    }


    /*
     * =====================================================
     * CREATE ROWS DURING RESTORE
     * =====================================================
     *
     * We deliberately use the existing Add buttons.
     *
     * This means row creation remains owned by the existing
     * PHP/UI controllers.
     * =====================================================
     */

    function ensureRowCount(
        containerSelector,
        rowSelector,
        buttonSelector,
        desiredCount
    ) {

        const container =
            getElement(
                containerSelector
            );


        if (!container) {
            return;
        }


        let rows =
            container.querySelectorAll(
                rowSelector
            );


        while (
            rows.length <
            desiredCount
        ) {

            const button =
                getElement(
                    buttonSelector
                );


            if (!button) {
                break;
            }


            button.click();


            rows =
                container.querySelectorAll(
                    rowSelector
                );

        }

    }


    /*
     * =====================================================
     * RESTORE EXPENSES
     * =====================================================
     */

    function restoreExpenses(
        expenses
    ) {

        if (
            !Array.isArray(expenses)
        ) {
            return;
        }


        if (
            expenses.length === 0
        ) {
            return;
        }


        ensureRowCount(
            '#expenseRows',
            '.expense-row',
            '#addExpenseButton',
            expenses.length
        );


        const rows =
            getElements(
                '#expenseRows .expense-row'
            );


        expenses.forEach(
            (
                expense,
                index
            ) => {

                const row =
                    rows[index];


                if (!row) {
                    return;
                }


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


                setValue(
                    category,
                    expense.category
                );


                setValue(
                    name,
                    expense.name
                );


                setValue(
                    amount,
                    expense.amount
                );


                dispatchChange(
                    category
                );

            }
        );

    }


    /*
     * =====================================================
     * RESTORE SHOP DELIVERIES
     * =====================================================
     */

    function restoreShopDeliveries(
        deliveries
    ) {

        if (
            !Array.isArray(deliveries)
        ) {
            return;
        }


        if (
            deliveries.length === 0
        ) {
            return;
        }


        ensureRowCount(
            '#deliveryPaymentRows',
            '.delivery-payment-row',
            '#addDeliveryPaymentButton',
            deliveries.length
        );


        const rows =
            getElements(
                '#deliveryPaymentRows .delivery-payment-row'
            );


        deliveries.forEach(
            (
                delivery,
                index
            ) => {

                const row =
                    rows[index];


                if (!row) {
                    return;
                }


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


                setValue(
                    customer,
                    delivery.customer
                );


                setValue(
                    slim,
                    delivery.slim
                );


                setValue(
                    round,
                    delivery.round
                );


                setValue(
                    payment,
                    delivery.payment
                );


                setValue(
                    price,
                    delivery.price
                );


                setValue(
                    method,
                    delivery.method ||
                    'Cash'
                );


                dispatchInput(
                    customer
                );


                dispatchInput(
                    slim
                );


                dispatchInput(
                    round
                );


                dispatchInput(
                    payment
                );


                dispatchInput(
                    price
                );


                dispatchChange(
                    method
                );

            }
        );

    }


    /*
     * =====================================================
     * RESTORE DRIVER EXPENSES
     * =====================================================
     */

    function restoreDriverExpenses(
        expenses
    ) {

        if (
            !Array.isArray(expenses)
        ) {
            return;
        }


        if (
            expenses.length === 0
        ) {
            return;
        }


        ensureRowCount(
            '#driverExpenseRows',
            '.driver-expense-row',
            '#addDriverExpenseButton',
            expenses.length
        );


        const rows =
            getElements(
                '#driverExpenseRows .driver-expense-row'
            );


        expenses.forEach(
            (
                expense,
                index
            ) => {

                const row =
                    rows[index];


                if (!row) {
                    return;
                }


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


                setValue(
                    category,
                    expense.category
                );


                setValue(
                    name,
                    expense.name
                );


                setValue(
                    amount,
                    expense.amount
                );


                dispatchChange(
                    category
                );

            }
        );

    }


    /*
     * =====================================================
     * RESTORE DRIVER DELIVERIES
     * =====================================================
     */

    function restoreDriverDeliveries(
        deliveries
    ) {

        if (
            !Array.isArray(deliveries)
        ) {
            return;
        }


        if (
            deliveries.length === 0
        ) {
            return;
        }


        ensureRowCount(
            '#driverDeliveryPaymentRows',
            '.driver-delivery-entry, .driver-delivery-row',
            '#addDriverDeliveryButton, #addDriverDeliveryPaymentButton',
            deliveries.length
        );


        const rows =
            getElements(
                '#driverDeliveryPaymentRows .driver-delivery-entry, ' +
                '#driverDeliveryPaymentRows .driver-delivery-row'
            );


        deliveries.forEach(
            (
                delivery,
                index
            ) => {

                const row =
                    rows[index];


                if (!row) {
                    return;
                }


                const customer =
                    row.querySelector(
                        'input[name="driver_delivery_customer[]"]'
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
                        'input[name="driver_delivery_price_per_gallon[]"]'
                    );


                const method =
                    row.querySelector(
                        'select[name="driver_delivery_method[]"]'
                    );


                setValue(
                    customer,
                    delivery.customer
                );


                setValue(
                    slim,
                    delivery.slim
                );


                setValue(
                    round,
                    delivery.round
                );


                setValue(
                    payment,
                    delivery.payment
                );


                setValue(
                    price,
                    delivery.price
                );


                setValue(
                    method,
                    delivery.method ||
                    'Cash'
                );


                dispatchInput(
                    customer
                );


                dispatchInput(
                    slim
                );


                dispatchInput(
                    round
                );


                dispatchInput(
                    payment
                );


                dispatchInput(
                    price
                );


                dispatchChange(
                    method
                );

            }
        );

    }


    /*
     * =====================================================
     * RESTORE COMPLETE DRAFT
     * =====================================================
     */

    function restoreDraft(
        draft
    ) {

        if (
            !draft ||
            typeof draft !== 'object'
        ) {
            return;
        }


        state.restoring =
            true;


        try {

            /*
             * -------------------------------------------------
             * SHOP
             * -------------------------------------------------
             */

            setValue(
                getElement(
                    '#walk_in_money'
                ),
                draft.walk_in_money
            );


            setValue(
                getElement(
                    '#walk_in_customers'
                ),
                draft.walk_in_customers
            );


            /*
             * -------------------------------------------------
             * SHOP EXPENSES
             * -------------------------------------------------
             */

            restoreExpenses(
                draft.expenses
            );


            /*
             * -------------------------------------------------
             * SHOP DELIVERIES
             * -------------------------------------------------
             */

            restoreShopDeliveries(
                draft.deliveries
            );


            /*
             * -------------------------------------------------
             * DRIVER
             * -------------------------------------------------
             */

            const driver =
                draft.driver || {};


            setValue(
                getElement(
                    '#driver_money_received'
                ),
                driver.money_received
            );


            restoreDriverExpenses(
                driver.expenses
            );


            restoreDriverDeliveries(
                driver.deliveries
            );


            /*
             * -------------------------------------------------
             * Rebind dynamically created customer fields.
             * -------------------------------------------------
             */

            bindDynamicRows();


            /*
             * -------------------------------------------------
             * Recalculate after restoring values.
             * -------------------------------------------------
             */

            if (
                window.marcidBlueDailyClosing &&
                typeof window
                    .marcidBlueDailyClosing
                    .calculateEverything ===
                    'function'
            ) {

                window
                    .marcidBlueDailyClosing
                    .calculateEverything();

            }

        } finally {

            state.restoring =
                false;

        }

    }


    /*
     * =====================================================
     * LOAD CONTEXT
     * =====================================================
     */

    async function loadContext() {

        const response =
            await request(
                'context'
            );


        state.dailyId =
            Number(
                response.daily_id
            );


        state.businessDate =
            response.business_date;


        state.status =
            response.status;


        state.customers =
            Array.isArray(
                response.customers
            )
                ? response.customers
                : [];


        updateCustomerDatalist();


        bindDynamicRows();


        updateDailyInformation();


        return response;

    }


    /*
     * =====================================================
     * UPDATE DAILY INFORMATION
     * =====================================================
     *
     * This intentionally updates only elements that already
     * exist. It does not create new UI.
     * =====================================================
     */

    function updateDailyInformation() {

        const dateElements =
            getElements(
                '[data-daily-business-date]'
            );


        dateElements.forEach(
            element => {

                if (
                    state.businessDate
                ) {

                    element.textContent =
                        state.businessDate;

                }

            }
        );


        const idElements =
            getElements(
                '[data-daily-id]'
            );


        idElements.forEach(
            element => {

                if (
                    state.dailyId
                ) {

                    element.textContent =
                        state.dailyId;

                }

            }
        );


        const statusElements =
            getElements(
                '[data-daily-status]'
            );


        statusElements.forEach(
            element => {

                if (
                    state.status
                ) {

                    element.textContent =
                        state.status;

                }

            }
        );

    }


    /*
     * =====================================================
     * LOAD SAVED DRAFT
     * =====================================================
     */

    async function loadDraft() {

        const response =
            await request(
                'load_draft'
            );


        /*
         * Backend is authoritative about the current
         * open daily record.
         */

        state.dailyId =
            Number(
                response.daily_id
            );


        state.businessDate =
            response.business_date;


        updateDailyInformation();


        if (
            response.has_draft &&
            response.draft
        ) {

            restoreDraft(
                response.draft
            );

        }

    }


    /*
     * =====================================================
     * RESET
     * =====================================================
     */

    async function handleReset() {

        const confirmed =
            window.confirm(
                'Are you sure you want to reset this daily closing?\n\n' +
                'All current daily entries and saved draft data will be cleared.'
            );


        if (!confirmed) {
            return;
        }


        const button =
            getElement(
                '#resetDailyClosingButton'
            );


        if (button) {
            button.disabled = true;
        }


        try {

            const response =
                await request(
                    'reset',
                    {

                        method: 'POST',

                        body: {}

                    }
                );


            /*
             * Clear the current browser form.
             *
             * We do this through the existing Reset/UI
             * controls where possible.
             */

            clearCurrentForm();


            state.dailyId =
                Number(
                    response.daily_id
                );


            state.businessDate =
                response.business_date;


            state.status =
                'Open';


            updateDailyInformation();


            if (
                window.marcidBlueDailyClosing &&
                typeof window
                    .marcidBlueDailyClosing
                    .calculateEverything ===
                    'function'
            ) {

                window
                    .marcidBlueDailyClosing
                    .calculateEverything();

            }


            window.alert(
                'Daily closing has been reset.'
            );

        } catch (error) {

            console.error(
                'Daily reset failed:',
                error
            );


            window.alert(
                error.message ||
                'Unable to reset the daily closing.'
            );

        } finally {

            if (button) {
                button.disabled = false;
            }

        }

    }


    /*
     * =====================================================
     * CLEAR CURRENT FORM
     * =====================================================
     *
     * We use existing clear/remove controls instead of
     * duplicating row-management logic.
     * =====================================================
     */

    function clickRemoveButtonsUntilMinimum(
        containerSelector,
        rowSelector
    ) {

        const container =
            getElement(
                containerSelector
            );


        if (!container) {
            return;
        }


        let rows =
            container.querySelectorAll(
                rowSelector
            );


        /*
         * Keep one row if the existing UI requires a minimum
         * row. The existing controller decides whether the
         * row can actually be removed.
         */

        while (
            rows.length > 1
        ) {

            const row =
                rows[
                    rows.length - 1
                ];


            const removeButton =
                row.querySelector(
                    '[data-remove-row], ' +
                    '.remove-row, ' +
                    '.remove-expense, ' +
                    '.remove-delivery, ' +
                    'button[type="button"].remove'
                );


            if (!removeButton) {
                break;
            }


            removeButton.click();


            rows =
                container.querySelectorAll(
                    rowSelector
                );

        }

    }


    function clearCurrentForm() {

        /*
         * Simple scalar fields.
         */

        const scalarSelectors = [

            '#walk_in_money',

            '#walk_in_customers',

            '#driver_money_received'

        ];


        scalarSelectors.forEach(
            selector => {

                const element =
                    getElement(
                        selector
                    );


                if (element) {

                    if (
                        element.hasAttribute(
                            'readonly'
                        )
                    ) {

                        /*
                         * readonly values are recalculated
                         * by the calculation controller.
                         */

                        return;

                    }


                    element.value = '';

                }

            }
        );


        /*
         * Existing row controllers generally keep one
         * blank row. Clear values from all remaining rows.
         */

        getElements(
            '#expenseRows .expense-row'
        ).forEach(
            row => {

                row.querySelectorAll(
                    'input, select, textarea'
                ).forEach(
                    element => {

                        if (
                            !element.disabled
                        ) {

                            element.value =
                                '';

                        }

                    }
                );

            }
        );


        getElements(
            '#deliveryPaymentRows .delivery-payment-row'
        ).forEach(
            row => {

                row.querySelectorAll(
                    'input, select, textarea'
                ).forEach(
                    element => {

                        if (
                            !element.disabled
                        ) {

                            element.value =
                                '';

                        }

                    }
                );

            }
        );


        getElements(
            '#driverExpenseRows .driver-expense-row'
        ).forEach(
            row => {

                row.querySelectorAll(
                    'input, select, textarea'
                ).forEach(
                    element => {

                        if (
                            !element.disabled
                        ) {

                            element.value =
                                '';

                        }

                    }
                );

            }
        );


        getElements(
            '#driverDeliveryPaymentRows .driver-delivery-entry, ' +
            '#driverDeliveryPaymentRows .driver-delivery-row'
        ).forEach(
            row => {

                row.querySelectorAll(
                    'input, select, textarea'
                ).forEach(
                    element => {

                        if (
                            !element.disabled
                        ) {

                            element.value =
                                '';

                        }

                    }
                );

            }
        );


        /*
         * Trigger existing calculations.
         */

        getElements(
            'input, select'
        ).forEach(
            element => {

                dispatchInput(
                    element
                );

                dispatchChange(
                    element
                );

            }
        );

    }


    /*
     * =====================================================
     * FINALIZE
     * =====================================================
     */

    async function handleFinalize() {

        /*
         * Make sure the most recent values are saved before
         * asking the backend to finalize.
         */

        clearTimeout(
            state.autosaveTimer
        );


        await saveDraft();


        const confirmed =
            window.confirm(
                'Finalize and close this daily record?\n\n' +
                'After closing, the daily record will no longer be editable.'
            );


        if (!confirmed) {
            return;
        }


        const button =
            getElement(
                '#finalizeDailyClosingButton'
            );


        if (button) {
            button.disabled = true;
        }


        try {

            const response =
                await request(
                    'finalize',
                    {

                        method: 'POST',

                        body: {

                            daily_id:
                                state.dailyId

                        }

                    }
                );


            state.status =
                'Closed';


            updateDailyInformation();


            window.alert(
                response.message ||
                'Daily record finalized successfully.'
            );


            /*
             * Reload the page so the existing authentication
             * and daily-record UI can determine what should
             * happen next.
             */

            window.location.reload();

        } catch (error) {

            console.error(
                'Daily finalization failed:',
                error
            );


            window.alert(
                error.message ||
                'Unable to finalize the daily record.'
            );

        } finally {

            if (button) {
                button.disabled = false;
            }

        }

    }


    /*
     * =====================================================
     * BIND RESET / FINALIZE
     * =====================================================
     *
     * These are the only two action buttons handled here.
     *
     * The old DailyClosingActionController will be removed
     * from the PHP file before this script is activated,
     * preventing duplicate click handlers.
     * =====================================================
     */

    function bindActionButtons() {

        const resetButton =
            getElement(
                '#resetDailyClosingButton'
            );


        if (
            resetButton &&
            resetButton.dataset.dataControllerBound !== '1'
        ) {

            resetButton.dataset.dataControllerBound =
                '1';


            resetButton.addEventListener(
                'click',
                handleReset
            );

        }


        const finalizeButton =
            getElement(
                '#finalizeDailyClosingButton'
            );


        if (
            finalizeButton &&
            finalizeButton.dataset.dataControllerBound !== '1'
        ) {

            finalizeButton.dataset.dataControllerBound =
                '1';


            finalizeButton.addEventListener(
                'click',
                handleFinalize
            );

        }

    }


    /*
     * =====================================================
     * MUTATION OBSERVER
     * =====================================================
     *
     * Existing UI controllers dynamically add rows.
     *
     * We watch for those rows so customer data listeners
     * are automatically attached.
     * =====================================================
     */

    function observeDynamicRows() {

        const observer =
            new MutationObserver(
                () => {

                    bindDynamicRows();

                }
            );


        observer.observe(
            document.body,
            {

                childList: true,

                subtree: true

            }
        );

    }


    /*
     * =====================================================
     * INITIALIZATION
     * =====================================================
     */

    async function initialize() {

        if (
            state.initialized
        ) {
            return;
        }


        state.initialized =
            true;


        bindActionButtons();

        bindAutosaveEvents();

        observeDynamicRows();


        try {

            /*
             * Load the current daily record and customer list.
             */

            await loadContext();


            /*
             * Then restore the autosaved draft.
             */

            await loadDraft();


            /*
             * Make sure dynamically created rows are bound.
             */

            bindDynamicRows();


            /*
             * Final calculation after all data has been
             * restored.
             */

            if (
                window.marcidBlueDailyClosing &&
                typeof window
                    .marcidBlueDailyClosing
                    .calculateEverything ===
                    'function'
            ) {

                window
                    .marcidBlueDailyClosing
                    .calculateEverything();

            }

        } catch (error) {

            console.error(
                'Daily closing data initialization failed:',
                error
            );


            window.alert(
                error.message ||
                'Unable to load the daily closing data.'
            );

        }

    }


    /*
     * =====================================================
     * PUBLIC API
     * =====================================================
     */

    window.marcidBlueDailyClosingData = {

        getState() {

            return {
                ...state,
                customers:
                    [...state.customers]
            };

        },

        collectDraft,

        saveDraft,

        loadContext,

        loadDraft,

        scheduleAutosave,

        saveCustomerIfNeeded,

        handleReset,

        handleFinalize

    };


    /*
     * =====================================================
     * START
     * =====================================================
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initialize
        );

    } else {

        initialize();

    }

})();