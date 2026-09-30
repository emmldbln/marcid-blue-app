(function () {

    'use strict';

    /*
     * ---------------------------------------------------------
     * CONFIGURATION
     * ---------------------------------------------------------
     */

    const BACKEND_URL =
        'backend/daily-closing-backend.php';

    const PAYROLL_BACKEND_URL =
        'backend/payroll-backend.php';

    const AUTOSAVE_DELAY = 700;

    const CURRENT_DEBT_REFRESH_DELAY = 350;


    /*
     * ---------------------------------------------------------
     * STATE
     * ---------------------------------------------------------
     */

    let dailyId = 0;

    let autosaveTimer = null;

    let isRestoring = false;

    let isSaving = false;

    let saveQueued = false;

    let savePromise = null;

    let currentDebtRefreshTimer = null;

    let currentDebtRequestId = 0;

    let payrollEmployees = [];


    /*
     * ---------------------------------------------------------
     * GENERAL HELPERS
     * ---------------------------------------------------------
     */

    function getElement(id) {

        return document.getElementById(id);

    }


    function getInputValue(element) {

        if (!element) {
            return '';
        }

        return element.value ?? '';

    }


    function setInputValue(element, value) {

        if (!element) {
            return;
        }

        element.value =
            value === null ||
            value === undefined
                ? ''
                : value;

    }


    function getRows(selector) {

        return Array.from(
            document.querySelectorAll(selector)
        );

    }


    function getRowData(row, selectors) {

        const data = {};

        if (!row) {
            return data;
        }

        Object.keys(selectors).forEach(
            key => {

                const element =
                    row.querySelector(
                        selectors[key]
                    );

                data[key] =
                    getInputValue(element);

            }
        );

        return data;

    }


    function setRowData(row, selectors, data) {

        if (!row || !data) {
            return;
        }

        Object.keys(selectors).forEach(
            key => {

                if (
                    !Object.prototype.hasOwnProperty.call(
                        data,
                        key
                    )
                ) {
                    return;
                }

                const element =
                    row.querySelector(
                        selectors[key]
                    );

                let value = data[key];

                if (
                    key === 'method' &&
                    (
                        value === null ||
                        value === undefined ||
                        String(value).trim() === ''
                    )
                ) {

                    value =
                        window.MARCID_BLUE_DEFAULT_PAYMENT_METHOD ||
                        'Cash';

                }

                setInputValue(
                    element,
                    value
                );

            }
        );

    }


    /*
     * ---------------------------------------------------------
     * MONEY HELPERS
     * ---------------------------------------------------------
     */

    function formatDebtMoney(value) {

        const amount =
            Number(value) || 0;

        return '₱' +
            amount.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }


    function formatPayrollMoney(value) {

        const amount =
            Number(value) || 0;

        return '₱' +
            amount.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }


    /*
     * ---------------------------------------------------------
     * SHOP STATE
     * ---------------------------------------------------------
     */

    function collectShopState() {

        const deliveries =
            getRows(
                '.delivery-payment-row'
            ).map(
                row => getRowData(
                    row,
                    {
                        customer:
                            '.delivery-customer',

                        slim:
                            '.delivery-slim',

                        round:
                            '.delivery-round',

                        payment:
                            '.delivery-payment',

                        price:
                            '.delivery-price-input',

                        method:
                            '.delivery-method'
                    }
                )
            );


        const expenses =
            getRows(
                '.expense-row'
            ).map(
                row => {

                    const category =
                        row.querySelector(
                            '.expense-category'
                        );

                    const nameField =
                        row.querySelector(
                            '.expense-name'
                        );

                    const amount =
                        row.querySelector(
                            '.expense-amount'
                        );

                    const isCashAdvance =
                        category &&
                        category.value ===
                            'Cash Advance';

                    let name = '';
                    let employeeId = '';

                    if (isCashAdvance) {

                        employeeId =
                            nameField
                                ? String(
                                    nameField.value || ''
                                )
                                : '';

                        const selectedOption =
                            nameField &&
                            nameField.options
                                ? nameField.options[
                                    nameField.selectedIndex
                                ]
                                : null;

                        name =
                            selectedOption &&
                            selectedOption.value
                                ? selectedOption.textContent.trim()
                                : '';

                    } else {

                        name =
                            nameField
                                ? String(
                                    nameField.value || ''
                                )
                                : '';

                    }

                    return {
                        category:
                            category
                                ? category.value
                                : '',

                        name,

                        employee_id:
                            employeeId,

                        amount:
                            amount
                                ? amount.value
                                : ''
                    };

                }
            );


        return {

            walk_in_money:
                getInputValue(
                    getElement(
                        'walk_in_money'
                    )
                ),

            walk_in_customers:
                getInputValue(
                    getElement(
                        'walk_in_customers'
                    )
                ),

            deliveries,

            expenses

        };

    }


    /*
     * ---------------------------------------------------------
     * DRIVER STATE
     * ---------------------------------------------------------
     */

    function collectDriverState() {

        const deliveries =
            getRows(
                '.driver-delivery-entry'
            ).map(
                row => getRowData(
                    row,
                    {
                        customer:
                            '.driver-delivery-customer',

                        slim:
                            '.driver-delivery-slim',

                        round:
                            '.driver-delivery-round',

                        payment:
                            '.driver-delivery-payment',

                        price:
                            '.driver-delivery-price',

                        method:
                            '.driver-delivery-method'
                    }
                )
            );


        const expenses =
            getRows(
                '.driver-expense-row'
            ).map(
                row => getRowData(
                    row,
                    {
                        category:
                            '.driver-expense-category',

                        name:
                            '.driver-expense-name',

                        amount:
                            '.driver-expense-amount'
                    }
                )
            );


        return {

            money_received:
                getInputValue(
                    getElement(
                        'driver_money_received'
                    )
                ),

            deliveries,

            expenses

        };

    }


/*
 * ---------------------------------------------------------
 * PAYROLL STATE
 * ---------------------------------------------------------
 */

    function getPayrollAmountInput(employeeId) {

        return document.querySelector(
            '[data-payroll-amount="' +
            String(employeeId) +
            '"]'
        );

    }


    function getPayrollState() {

        return payrollEmployees.map(
            employee => {

                const employeeId =
                    Number(
                        employee.employee_id
                    ) || 0;


                const input =
                    getPayrollAmountInput(
                        employeeId
                    );


                /*
                * Daily Rate is only the employee's reference rate.
                *
                * It must NOT become the Payroll Amount automatically.
                *
                * If the input exists, use exactly what is currently
                * entered in the Payroll Amount field.
                *
                * If the input does not exist, use 0 instead of the
                * employee's Daily Rate.
                */

                const value =
                    input
                        ? input.value
                        : '0';


                return {

                    employee_id:
                        employeeId,

                    full_name:
                        employee.full_name || '',

                    position:
                        employee.position || '',

                    amount:
                        value

                };

            }
        );

    }


    function getEmployeeCashAdvance(employeeId) {

        const targetId =
            String(employeeId);

        let total = 0;

        getRows(
            '.expense-row'
        ).forEach(
            row => {

                const category =
                    row.querySelector(
                        '.expense-category'
                    );

                const employee =
                    row.querySelector(
                        '.expense-name'
                    );

                const amount =
                    row.querySelector(
                        '.expense-amount'
                    );

                if (
                    !category ||
                    category.value !==
                        'Cash Advance' ||
                    !employee ||
                    String(employee.value) !==
                        targetId
                ) {
                    return;
                }

                total +=
                    Number(
                        amount
                            ? amount.value
                            : 0
                    ) || 0;

            }
        );

        return total;

    }


    function updatePayrollCashAdvances() {

        document
            .querySelectorAll(
                '.payroll-cash-advance-value'
            )
            .forEach(
                element => {

                    const employeeId =
                        element.dataset.employeeId;

                    element.textContent =
                        formatPayrollMoney(
                            getEmployeeCashAdvance(
                                employeeId
                            )
                        );

                }
            );

    }


    window.marcidBlueUpdatePayrollCashAdvances =
        updatePayrollCashAdvances;


    function getPayrollTotal() {

        let total = 0;


        document
            .querySelectorAll(
                '.payroll-amount-input'
            )
            .forEach(
                input => {

                    const employeeId =
                        input.dataset.payrollAmount;

                    const payrollAmount =
                        Number(
                            input.value
                        ) || 0;

                    total +=
                        payrollAmount;

                }
            );


        return total;

    }





    /*
     * ---------------------------------------------------------
     * COMPLETE DRAFT
     * ---------------------------------------------------------
     */

    function collectDraft() {

        return {

            version: 1,

            daily_id: dailyId,

            saved_at:
                new Date().toISOString(),

            shop:
                collectShopState(),

            driver:
                collectDriverState(),

            payroll:
                getPayrollState()

        };

    }


    /*
     * ---------------------------------------------------------
     * ROW MANAGEMENT
     * ---------------------------------------------------------
     */

    function ensureRowCount(
        containerSelector,
        buttonId,
        desiredCount
    ) {

        const container =
            document.querySelector(
                containerSelector
            );

        const button =
            getElement(buttonId);

        if (!container || !button) {
            return;
        }

        let currentCount;

        if (
            containerSelector ===
            '#expenseRows'
        ) {

            currentCount =
                container.querySelectorAll(
                    '.expense-row'
                ).length;

        } else if (
            containerSelector ===
            '#deliveryPaymentRows'
        ) {

            currentCount =
                container.querySelectorAll(
                    '.delivery-payment-row'
                ).length;

        } else if (
            containerSelector ===
            '#driverExpenseRows'
        ) {

            currentCount =
                container.querySelectorAll(
                    '.driver-expense-row'
                ).length;

        } else {

            currentCount = 0;

        }


        while (
            currentCount <
            desiredCount
        ) {

            button.click();

            currentCount =
                container.querySelectorAll(
                    containerSelector ===
                    '#expenseRows'
                        ? '.expense-row'
                        :
                    containerSelector ===
                    '#deliveryPaymentRows'
                        ? '.delivery-payment-row'
                        :
                    '.driver-expense-row'
                ).length;

        }

    }


    function ensureShopRows(state) {

        if (!state) {
            return;
        }

        ensureRowCount(
            '#expenseRows',
            'addExpenseButton',
            Array.isArray(state.expenses)
                ? state.expenses.length
                : 0
        );


        ensureRowCount(
            '#deliveryPaymentRows',
            'addDeliveryPaymentButton',
            Array.isArray(state.deliveries)
                ? state.deliveries.length
                : 0
        );

    }


    function ensureDriverRows(state) {

        if (!state) {
            return;
        }

        ensureRowCount(
            '#driverExpenseRows',
            'addDriverExpenseButton',
            Array.isArray(state.expenses)
                ? state.expenses.length
                : 0
        );


        const container =
            document.getElementById(
                'driverDeliveryPaymentRows'
            );

        if (!container) {
            return;
        }


        let currentCount =
            container.querySelectorAll(
                '.driver-delivery-entry'
            ).length;


        const targetCount =
            Array.isArray(state.deliveries)
                ? state.deliveries.length
                : 0;


        const addOneButton =
            document.querySelector(
                '[data-driver-payment-adjust="1"]'
            );


        while (
            currentCount <
            targetCount
        ) {

            if (!addOneButton) {
                break;
            }

            addOneButton.click();

            currentCount =
                container.querySelectorAll(
                    '.driver-delivery-entry'
                ).length;

        }

    }


    /*
     * ---------------------------------------------------------
     * RESTORE SHOP
     * ---------------------------------------------------------
     */

    function restoreShopState(state) {

        if (!state) {
            return;
        }

        setInputValue(
            getElement('walk_in_money'),
            state.walk_in_money
        );


        setInputValue(
            getElement('walk_in_customers'),
            state.walk_in_customers
        );


        ensureShopRows(state);


        const expenses =
            Array.isArray(
                state.expenses
            )
                ? state.expenses
                : [];


        const deliveries =
            Array.isArray(
                state.deliveries
            )
                ? state.deliveries
                : [];


        const expenseRows =
            getRows(
                '.expense-row'
            );


        expenses.forEach(
            (data, index) => {

                const row =
                    expenseRows[index];

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

                if (category) {
                    category.value =
                        data.category || '';
                }

                /*
                 * Rebuild the Cash Advance / Others
                 * field before restoring its saved value.
                 */
                if (
                    typeof window.marcidBlueExpenseControllerUpdateRow ===
                    'function'
                ) {

                    const existingField =
                        row.querySelector(
                            '.expense-name'
                        );

                    if (existingField) {

                        existingField.dataset.selectedEmployeeId =
                            data.employee_id !==
                                undefined &&
                            data.employee_id !==
                                null
                                ? String(
                                    data.employee_id
                                )
                                : '';

                        existingField.dataset.description =
                            data.name || '';

                    }

                    window.marcidBlueExpenseControllerUpdateRow(
                        row,
                        true
                    );

                }

                if (amount) {
                    amount.value =
                        data.amount ?? '';
                }

                const nameField =
                    row.querySelector(
                        '.expense-name'
                    );

                if (!nameField) {
                    return;
                }

                if (
                    data.category ===
                    'Cash Advance'
                ) {

                    nameField.value =
                        data.employee_id !==
                            undefined &&
                        data.employee_id !==
                            null
                            ? String(
                                data.employee_id
                            )
                            : '';

                } else {

                    nameField.value =
                        data.name ?? '';

                }

            }
        );

        if (
            typeof window.marcidBlueUpdatePayrollCashAdvances ===
            'function'
        ) {

            window.marcidBlueUpdatePayrollCashAdvances();

        }


        const deliveryRows =
            getRows(
                '.delivery-payment-row'
            );


        deliveries.forEach(
            (data, index) => {

                setRowData(
                    deliveryRows[index],
                    {
                        customer:
                            '.delivery-customer',

                        slim:
                            '.delivery-slim',

                        round:
                            '.delivery-round',

                        payment:
                            '.delivery-payment',

                        price:
                            '.delivery-price-input',

                        method:
                            '.delivery-method'
                    },
                    data
                );

            }
        );

    }


    /*
     * ---------------------------------------------------------
     * RESTORE DRIVER
     * ---------------------------------------------------------
     */

    function restoreDriverState(state) {

        if (!state) {
            return;
        }

        setInputValue(
            getElement(
                'driver_money_received'
            ),
            state.money_received
        );


        ensureDriverRows(state);


        const expenses =
            Array.isArray(
                state.expenses
            )
                ? state.expenses
                : [];


        const deliveries =
            Array.isArray(
                state.deliveries
            )
                ? state.deliveries
                : [];


        const expenseRows =
            getRows(
                '.driver-expense-row'
            );


        expenses.forEach(
            (data, index) => {

                setRowData(
                    expenseRows[index],
                    {
                        category:
                            '.driver-expense-category',

                        name:
                            '.driver-expense-name',

                        amount:
                            '.driver-expense-amount'
                    },
                    data
                );

            }
        );


        const deliveryRows =
            getRows(
                '.driver-delivery-entry'
            );


        deliveries.forEach(
            (data, index) => {

                setRowData(
                    deliveryRows[index],
                    {
                        customer:
                            '.driver-delivery-customer',

                        slim:
                            '.driver-delivery-slim',

                        round:
                            '.driver-delivery-round',

                        payment:
                            '.driver-delivery-payment',

                        price:
                            '.driver-delivery-price',

                        method:
                            '.driver-delivery-method'
                    },
                    data
                );

            }
        );

    }


    /*
     * ---------------------------------------------------------
     * RESTORE PAYROLL
     * ---------------------------------------------------------
     */

    function restorePayrollState(state) {

        if (!Array.isArray(state)) {
            return;
        }


        state.forEach(
            payroll => {

                const employeeId =
                    Number(
                        payroll.employee_id
                    ) || 0;


                if (employeeId <= 0) {
                    return;
                }


                const input =
                    getPayrollAmountInput(
                        employeeId
                    );


                if (!input) {
                    return;
                }


                /*
                 * Payroll Amount is the actual amount saved
                 * for this Daily Closing.
                 *
                 * Daily Rate is never used as a fallback.
                 */
                const savedAmount =
                    payroll.amount !== undefined &&
                    payroll.amount !== null
                        ? payroll.amount
                        : '0.00';


                input.value =
                    String(
                        savedAmount
                    );


                /*
                 * Keep the large amount shown in the employee
                 * header synchronized with the restored input.
                 */
                const item =
                    input.closest(
                        '.payroll-item'
                    );


                const amountDisplay =
                    item
                        ? item.querySelector(
                            '.payroll-item-amount'
                        )
                        : null;


                if (amountDisplay) {

                    amountDisplay.textContent =
                        formatPayrollMoney(
                            savedAmount
                        );

                }

            }
        );


        updatePayrollTotal();

    }

    /*
     * ---------------------------------------------------------
     * RECALCULATE
     * ---------------------------------------------------------
     */

    function recalculate() {

        if (
            window.marcidBlueDailyClosing &&
            typeof
                window
                    .marcidBlueDailyClosing
                    .calculateEverything ===
                'function'
        ) {

            window
                .marcidBlueDailyClosing
                .calculateEverything();

        }

        updatePayrollTotal();

    }


    /*
     * ---------------------------------------------------------
     * PAYROLL
     * ---------------------------------------------------------
     */

    function getPayrollListElement() {

        return (
            getElement('payrollList') ||
            document.querySelector(
                '.payroll-list'
            ) ||
            document.querySelector(
                '[data-payroll-list]'
            )
        );

    }


    function getPayrollTotalElement() {

        return (
            getElement('payrollTotal') ||
            document.querySelector(
                '.payroll-total'
            ) ||
            document.querySelector(
                '[data-payroll-total]'
            )
        );

    }


    function updatePayrollTotal() {

        const total =
            getPayrollTotal();


        const totalElement =
            getPayrollTotalElement();


        if (totalElement) {

            totalElement.textContent =
                formatPayrollMoney(
                    total
                );

        }


        /*
        * Payroll is part of Daily Closing.
        * Recalculate the central Daily Closing
        * totals whenever Payroll changes.
        */
        if (
            window.marcidBlueDailyClosing &&
            typeof
                window
                    .marcidBlueDailyClosing
                    .calculateEverything ===
                'function'
        ) {

            window
                .marcidBlueDailyClosing
                .calculateEverything();

        }

    }


    /*
     * ---------------------------------------------------------
     * RENDER PAYROLL
     * ---------------------------------------------------------
     *
     * IMPORTANT:
     * Payroll intentionally uses the same visual structure
     * as Current Debt.
     *
     * There is NO TABLE here.
     * ---------------------------------------------------------
     */

    function renderPayrollEmployees() {

        const list =
            getPayrollListElement();


        if (!list) {

            console.warn(
                'Marcid Blue: payroll list element not found.'
            );

            return;

        }


        list.innerHTML = '';


        if (
            !Array.isArray(
                payrollEmployees
            ) ||
            payrollEmployees.length === 0
        ) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'payroll-empty';

            empty.textContent =
                'No active employees found.';

            list.appendChild(
                empty
            );

            updatePayrollTotal();

            return;

        }


        payrollEmployees.forEach(
            employee => {

                const item =
                    document.createElement(
                        'div'
                    );

                item.className =
                    'payroll-item';


                /*
                * -------------------------------------------------
                * EMPLOYEE HEADER
                * -------------------------------------------------
                */

                const header =
                    document.createElement(
                        'div'
                    );

                header.className =
                    'payroll-item-header';


                const identity =
                    document.createElement(
                        'div'
                    );


                const name =
                    document.createElement(
                        'div'
                    );

                name.className =
                    'payroll-employee-name';

                name.textContent =
                    employee.full_name ||
                    'Unnamed Employee';


                const position =
                    document.createElement(
                        'div'
                    );

                position.className =
                    'payroll-employee-position';

                position.textContent =
                    employee.position ||
                    '—';


                identity.appendChild(
                    name
                );

                identity.appendChild(
                    position
                );


                /*
                * -------------------------------------------------
                * PAYROLL HEADER AMOUNT
                * -------------------------------------------------
                *
                * IMPORTANT:
                *
                * This is the total Payroll Amount.
                *
                * It starts with the employee's Cash Advance.
                * The user then adds the remaining salary to this
                * same Payroll Amount.
                * -------------------------------------------------
                */

                const amountDisplay =
                    document.createElement(
                        'div'
                    );

                amountDisplay.className =
                    'payroll-item-amount';

                amountDisplay.textContent =
                    formatPayrollMoney(
                        getEmployeeCashAdvance(
                            employee.employee_id
                        )
                    );


                header.appendChild(
                    identity
                );

                header.appendChild(
                    amountDisplay
                );


                item.appendChild(
                    header
                );


                /*
                * -------------------------------------------------
                * DETAILS
                * -------------------------------------------------
                */

                const details =
                    document.createElement(
                        'div'
                    );

                details.className =
                    'payroll-item-details';


                /*
                * -------------------------------------------------
                * CASH ADVANCE
                * -------------------------------------------------
                *
                * This is shown separately from Payroll Amount.
                * It is sourced from the explicitly recorded
                * Station Expense -> Cash Advance entry.
                * -------------------------------------------------
                */

                const cashAdvanceDetail =
                    document.createElement(
                        'div'
                    );

                cashAdvanceDetail.className =
                    'payroll-detail';


                const cashAdvanceLabel =
                    document.createElement(
                        'div'
                    );

                cashAdvanceLabel.className =
                    'payroll-detail-label';

                cashAdvanceLabel.textContent =
                    'Cash Advance';


                const cashAdvanceValue =
                    document.createElement(
                        'div'
                    );

                cashAdvanceValue.className =
                    'payroll-detail-value payroll-cash-advance-value';

                cashAdvanceValue.dataset.employeeId =
                    String(
                        employee.employee_id
                    );

                cashAdvanceValue.textContent =
                    formatPayrollMoney(
                        getEmployeeCashAdvance(
                            employee.employee_id
                        )
                    );


                cashAdvanceDetail.appendChild(
                    cashAdvanceLabel
                );

                cashAdvanceDetail.appendChild(
                    cashAdvanceValue
                );


                /*
                * -------------------------------------------------
                * PAYROLL AMOUNT
                * -------------------------------------------------
                */

                const payrollDetail =
                    document.createElement(
                        'div'
                    );

                payrollDetail.className =
                    'payroll-detail';


                const payrollLabel =
                    document.createElement(
                        'div'
                    );

                payrollLabel.className =
                    'payroll-detail-label';

                payrollLabel.textContent =
                    'Payroll Amount (Remaining)';


                const amountInput =
                    document.createElement(
                        'input'
                    );

                amountInput.type =
                    'number';

                amountInput.min =
                    '0';

                amountInput.step =
                    '0.01';

                amountInput.inputMode =
                    'decimal';

                amountInput.className =
                    'payroll-amount-input';


                amountInput.dataset.payrollAmount =
                    String(
                        employee.employee_id
                    );


                /*
                * -------------------------------------------------
                * IMPORTANT
                * -------------------------------------------------
                *
                * New Payroll Amount starts at the Cash Advance.
                *
                * The user adds the remaining salary to this amount.
                *
                * A saved draft will overwrite this value later
                * through restorePayrollState().
                * -------------------------------------------------
                */

                amountInput.value =
                    formatPayrollMoney(
                        getEmployeeCashAdvance(
                            employee.employee_id
                        )
                    ).replace(
                        '₱',
                        ''
                    )
                    || '0.00';


                amountInput.style.width =
                    '100%';

                amountInput.style.boxSizing =
                    'border-box';

                amountInput.style.marginTop =
                    '3px';

                amountInput.style.padding =
                    '0';

                amountInput.style.border =
                    '0';

                amountInput.style.outline =
                    'none';

                amountInput.style.background =
                    'transparent';

                amountInput.style.color =
                    'var(--text)';

                amountInput.style.fontSize =
                    '13px';

                amountInput.style.fontWeight =
                    '700';


                /*
                * -------------------------------------------------
                * INPUT EVENT
                * -------------------------------------------------
                */

                amountInput.addEventListener(
                    'input',
                    () => {

                        const numericValue =
                            Number(
                                amountInput.value
                            ) || 0;


                        amountDisplay.textContent =
                            formatPayrollMoney(
                                numericValue
                            );


                        updatePayrollTotal();

                        scheduleAutosave();

                    }
                );


                /*
                * -------------------------------------------------
                * CHANGE EVENT
                * -------------------------------------------------
                */

                amountInput.addEventListener(
                    'change',
                    () => {

                        const numericValue =
                            Number(
                                amountInput.value
                            ) || 0;


                        amountDisplay.textContent =
                            formatPayrollMoney(
                                numericValue
                            );


                        updatePayrollTotal();

                        scheduleAutosave();

                    }
                );


                payrollDetail.appendChild(
                    payrollLabel
                );

                payrollDetail.appendChild(
                    amountInput
                );


                details.appendChild(
                    cashAdvanceDetail
                );

                details.appendChild(
                    payrollDetail
                );


                item.appendChild(
                    details
                );


                list.appendChild(
                    item
                );

            }
        );


        /*
        * Update the total after all employees have been
        * rendered.
        */

        updatePayrollTotal();

        updatePayrollCashAdvances();

    }




    /*
     * ---------------------------------------------------------
     * LOAD PAYROLL EMPLOYEES
     * ---------------------------------------------------------
     */

    async function loadPayrollEmployees() {

        const list =
            getPayrollListElement();


        if (list) {

            list.innerHTML = '';

            const loading =
                document.createElement(
                    'div'
                );

            loading.className =
                'payroll-loading';

            loading.textContent =
                'Loading payroll...';

            list.appendChild(
                loading
            );

        }


        try {

            const response =
                await fetch(
                    PAYROLL_BACKEND_URL +
                    '?action=get_employees&_=' +
                    Date.now(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            Accept:
                                'application/json'
                        }
                    }
                );


            if (!response.ok) {

                throw new Error(
                    'Payroll backend returned HTTP ' +
                    response.status
                );

            }


            const result =
                await response.json();


            if (!result.success) {

                throw new Error(
                    result.message ||
                    'Unable to load employees.'
                );

            }


            payrollEmployees =
                Array.isArray(
                    result.employees
                )
                    ? result.employees.filter(
                        employee =>
                            Number(
                                employee.is_active
                            ) === 1
                    )
                    : [];


            window.marcidBluePayrollEmployees =
                payrollEmployees;


            renderPayrollEmployees();

            document
                .querySelectorAll(
                    '.expense-row'
                )
                .forEach(
                    row => {

                        if (
                            typeof window.marcidBlueExpenseControllerUpdateRow ===
                            'function'
                        ) {

                            window.marcidBlueExpenseControllerUpdateRow(
                                row
                            );

                        }

                    }
                );

            updatePayrollCashAdvances();


            console.info(
                'Marcid Blue: payroll employees loaded.',
                payrollEmployees
            );


        } catch (error) {

            payrollEmployees = [];


            if (list) {

                list.innerHTML = '';

                const errorElement =
                    document.createElement(
                        'div'
                    );

                errorElement.className =
                    'payroll-empty payroll-error';

                errorElement.textContent =
                    'Unable to load payroll employees.';

                list.appendChild(
                    errorElement
                );

            }


            console.error(
                'Marcid Blue: unable to load payroll employees.',
                error
            );

        }

    }


    /*
     * ---------------------------------------------------------
     * CURRENT DEBT
     * ---------------------------------------------------------
     */

    function scheduleCurrentDebtRefresh() {

        clearTimeout(
            currentDebtRefreshTimer
        );


        currentDebtRefreshTimer =
            setTimeout(
                refreshCurrentDebt,
                CURRENT_DEBT_REFRESH_DELAY
            );

    }


    async function refreshCurrentDebt() {

        if (dailyId <= 0) {
            return;
        }


        const requestId =
            ++currentDebtRequestId;


        const draft =
            collectDraft();


        const params =
            new URLSearchParams();


        params.set(
            'action',
            'get_current_debt'
        );


        params.set(
            'daily_id',
            String(dailyId)
        );


        params.set(
            'draft',
            JSON.stringify(draft)
        );


        try {

            const response =
                await fetch(
                    BACKEND_URL +
                    '?' +
                    params.toString(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            Accept:
                                'application/json'
                        }
                    }
                );


            if (!response.ok) {

                throw new Error(
                    'Current debt request failed: HTTP ' +
                    response.status
                );

            }


            const result =
                await response.json();


            if (
                requestId !==
                currentDebtRequestId
            ) {

                return;

            }


            if (!result.success) {

                console.error(
                    'Marcid Blue: current debt request failed.',
                    result.message
                );

                return;

            }


            renderCurrentDebt(
                result
            );


        } catch (error) {

            console.error(
                'Marcid Blue: unable to load current debt.',
                error
            );

        }

    }


    function renderCurrentDebt(result) {

        const totalElement =
            getElement(
                'currentDebtTotal'
            );


        const list =
            getElement(
                'currentDebtList'
            );


        if (!totalElement || !list) {
            return;
        }


        /*
         * =========================================================
         * CURRENT DEBT
         * =========================================================
         */

        const totalDebt =
            Number(
                result.total_debt
            ) || 0;


        const groupedDebts =
            result.grouped_debts &&
            typeof result.grouped_debts === 'object'
                ? result.grouped_debts
                : {};


        totalElement.textContent =
            formatDebtMoney(
                totalDebt
            );


        list.innerHTML = '';


        const debtDates =
            Object.keys(
                groupedDebts
            );


        /*
         * =========================================================
         * NO CURRENT DEBT
         * =========================================================
         */

        if (debtDates.length === 0) {

            const empty =
                document.createElement(
                    'div'
                );

            empty.className =
                'current-debt-empty';

            empty.textContent =
                'No customers currently have an outstanding balance.';

            list.appendChild(
                empty
            );

        } else {

            debtDates.forEach(
                date => {

                    const debts =
                        Array.isArray(
                            groupedDebts[date]
                        )
                            ? groupedDebts[date]
                            : [];


                    if (debts.length === 0) {
                        return;
                    }


                    const dateHeading =
                        document.createElement(
                            'div'
                        );

                    dateHeading.style.padding =
                        '14px 0 6px';

                    dateHeading.style.color =
                        'var(--text-muted)';

                    dateHeading.style.fontSize =
                        '11px';

                    dateHeading.style.fontWeight =
                        '700';

                    dateHeading.style.textTransform =
                        'uppercase';

                    dateHeading.style.letterSpacing =
                        '0.04em';


                    const parsedDate =
                        new Date(
                            date + 'T00:00:00'
                        );


                    if (
                        !Number.isNaN(
                            parsedDate.getTime()
                        )
                    ) {

                        dateHeading.textContent =
                            parsedDate.toLocaleDateString(
                                'en-US',
                                {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric'
                                }
                            );

                    } else {

                        dateHeading.textContent =
                            date;

                    }


                    list.appendChild(
                        dateHeading
                    );


                    const customerGroups = {};


                    debts.forEach(
                        debt => {

                            const customerId =
                                debt.customer_id !== undefined &&
                                debt.customer_id !== null &&
                                String(
                                    debt.customer_id
                                ).trim() !== ''
                                    ? String(
                                        debt.customer_id
                                    )
                                    : (
                                        String(
                                            debt.customer_name ||
                                            'Unknown Customer'
                                        )
                                            .trim()
                                            .toLowerCase()
                                    );


                            if (
                                !customerGroups[
                                    customerId
                                ]
                            ) {

                                customerGroups[
                                    customerId
                                ] = {
                                    customer_id:
                                        debt.customer_id,

                                    customer_name:
                                        debt.customer_name ||
                                        'Unknown Customer',

                                    debts: []
                                };

                            }


                            customerGroups[
                                customerId
                            ].debts.push(
                                debt
                            );

                        }
                    );


                    Object.values(
                        customerGroups
                    ).forEach(
                        group => {

                            const customerDebts =
                                group.debts;


                            const totalOriginal =
                                customerDebts.reduce(
                                    (
                                        total,
                                        debt
                                    ) => {

                                        return total +
                                            (
                                                Number(
                                                    debt.original_amount
                                                ) || 0
                                            );

                                    },
                                    0
                                );


                            const totalRemaining =
                                customerDebts.reduce(
                                    (
                                        total,
                                        debt
                                    ) => {

                                        return total +
                                            (
                                                Number(
                                                    debt.remaining_amount
                                                ) || 0
                                            );

                                    },
                                    0
                                );


                            const totalDraftPayment =
                                customerDebts.reduce(
                                    (
                                        total,
                                        debt
                                    ) => {

                                        return total +
                                            (
                                                Number(
                                                    debt.draft_payment
                                                ) || 0
                                            );

                                    },
                                    0
                                );


                            const hasDraftPayment =
                                totalDraftPayment >
                                0.009;


                            const isDraftPaid =
                                totalRemaining <= 0.009 &&
                                hasDraftPayment;


                            const item =
                                document.createElement(
                                    'div'
                                );


                            item.className =
                                'current-debt-item' +
                                (
                                    isDraftPaid
                                        ? ' current-debt-item-paid'
                                        : ''
                                );


                            const header =
                                document.createElement(
                                    'div'
                                );


                            header.className =
                                'current-debt-item-header';


                            const name =
                                document.createElement(
                                    'div'
                                );


                            name.className =
                                'current-debt-customer';


                            name.textContent =
                                group.customer_name ||
                                'Unknown Customer';


                            const amount =
                                document.createElement(
                                    'div'
                                );


                            amount.className =
                                'current-debt-amount';


                            if (
                                hasDraftPayment
                            ) {

                                const originalSpan =
                                    document.createElement(
                                        'span'
                                    );


                                originalSpan.className =
                                    isDraftPaid
                                        ? 'current-debt-original-paid'
                                        : '';


                                originalSpan.textContent =
                                    formatDebtMoney(
                                        totalOriginal
                                    );


                                const arrowSpan =
                                    document.createElement(
                                        'span'
                                    );


                                arrowSpan.className =
                                    'current-debt-arrow';

                                arrowSpan.textContent =
                                    ' → ';


                                const remainingSpan =
                                    document.createElement(
                                        'span'
                                    );


                                remainingSpan.className =
                                    'current-debt-remaining';


                                remainingSpan.textContent =
                                    formatDebtMoney(
                                        totalRemaining
                                    );


                                amount.appendChild(
                                    originalSpan
                                );

                                amount.appendChild(
                                    arrowSpan
                                );

                                amount.appendChild(
                                    remainingSpan
                                );

                            } else {

                                amount.textContent =
                                    formatDebtMoney(
                                        totalRemaining
                                    );

                            }


                            header.appendChild(
                                name
                            );

                            header.appendChild(
                                amount
                            );


                            item.appendChild(
                                header
                            );


                            if (
                                customerDebts.length > 1
                            ) {

                                const details =
                                    document.createElement(
                                        'div'
                                    );


                                details.className =
                                    'current-debt-item-details';


                                const breakdownAmounts =
                                    customerDebts
                                        .map(
                                            debt =>
                                                formatDebtMoney(
                                                    Number(
                                                        debt.original_amount
                                                    ) || 0
                                                )
                                        )
                                        .join(
                                            ' + '
                                        );


                                details.textContent =
                                    breakdownAmounts;


                                item.appendChild(
                                    details
                                );

                            }


                            list.appendChild(
                                item
                            );

                        }
                    );

                }
            );

        }


        /*
         * =========================================================
         * CUSTOMER CREDIT
         * =========================================================
         */

        const totalCredit =
            Number(
                result.total_credit
            ) || 0;


        const credits =
            Array.isArray(
                result.credits
            )
                ? result.credits
                : [];


        if (
            totalCredit > 0 ||
            credits.length > 0
        ) {

            const creditHeading =
                document.createElement(
                    'div'
                );


            creditHeading.style.marginTop =
                '18px';

            creditHeading.style.padding =
                '14px 0 6px';

            creditHeading.style.borderTop =
                '1px solid var(--border)';

            creditHeading.style.color =
                'var(--text-muted)';

            creditHeading.style.fontSize =
                '11px';

            creditHeading.style.fontWeight =
                '700';

            creditHeading.style.textTransform =
                'uppercase';

            creditHeading.style.letterSpacing =
                '0.04em';

            creditHeading.textContent =
                'Customer Credit';


            list.appendChild(
                creditHeading
            );


            credits.forEach(
                credit => {

                    const item =
                        document.createElement(
                            'div'
                        );


                    const hasDraftCredit =
                        (
                            Number(
                                credit.draft_credit
                            ) || 0
                        ) > 0.009;


                    item.className =
                        'current-debt-item' +
                        (
                            hasDraftCredit
                                ? ' current-debt-item-credit-draft'
                                : ''
                        );


                    const header =
                        document.createElement(
                            'div'
                        );


                    header.className =
                        'current-debt-item-header';


                    const name =
                        document.createElement(
                            'div'
                        );


                    name.className =
                        'current-debt-customer';


                    name.textContent =
                        credit.customer_name ||
                        'Unknown Customer';


                    const amount =
                        document.createElement(
                            'div'
                        );


                    amount.className =
                        'current-debt-amount current-debt-credit';


                    const originalCredit =
                        Number(
                            credit.original_credit
                        ) || 0;


                    const totalCustomerCredit =
                        Number(
                            credit.total_credit
                        ) || 0;


                    if (
                        hasDraftCredit
                    ) {

                        const originalSpan =
                            document.createElement(
                                'span'
                            );


                        originalSpan.className =
                            'current-debt-credit-original';


                        originalSpan.textContent =
                            formatDebtMoney(
                                originalCredit
                            );


                        const arrowSpan =
                            document.createElement(
                                'span'
                            );


                        arrowSpan.className =
                            'current-debt-arrow';


                        arrowSpan.textContent =
                            ' → ';


                        const newCreditSpan =
                            document.createElement(
                                'span'
                            );


                        newCreditSpan.className =
                            'current-debt-credit-new';


                        newCreditSpan.textContent =
                            formatDebtMoney(
                                totalCustomerCredit
                            );


                        amount.appendChild(
                            originalSpan
                        );

                        amount.appendChild(
                            arrowSpan
                        );

                        amount.appendChild(
                            newCreditSpan
                        );

                    } else {

                        amount.textContent =
                            formatDebtMoney(
                                totalCustomerCredit
                            );

                    }


                    header.appendChild(
                        name
                    );

                    header.appendChild(
                        amount
                    );


                    item.appendChild(
                        header
                    );


                    list.appendChild(
                        item
                    );

                }
            );

        }

    }


    /*
     * ---------------------------------------------------------
     * LOAD DRAFT
     * ---------------------------------------------------------
     */

    async function loadDraft() {

        try {

            isRestoring = true;

            console.info(
                'Marcid Blue: loading daily draft...'
            );


            const response =
                await fetch(
                    BACKEND_URL +
                    '?action=load&_=' +
                    Date.now(),
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            Accept:
                                'application/json'
                        }
                    }
                );


            if (response.status === 404) {

                console.info(
                    'Marcid Blue: no open daily record. ' +
                    'Checking today\'s existing daily record...'
                );


                const contextResponse =
                    await fetch(
                        BACKEND_URL +
                        '?action=get_daily_context&_=' +
                        Date.now(),
                        {
                            method: 'GET',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                Accept:
                                    'application/json'
                            }
                        }
                    );


                if (!contextResponse.ok) {

                    throw new Error(
                        'Daily context request returned HTTP ' +
                        contextResponse.status
                    );

                }


                const contextResult =
                    await contextResponse.json();


                if (!contextResult.success) {

                    console.warn(
                        'Marcid Blue: daily context unavailable:',
                        contextResult.message
                    );

                    return;

                }


                dailyId =
                    Number(
                        contextResult.daily_id
                    ) || 0;


                console.info(
                    'Marcid Blue: existing daily ID =',
                    dailyId
                );


                console.info(
                    'Marcid Blue: existing daily status =',
                    contextResult.status
                );


                return;

            }


            if (!response.ok) {

                throw new Error(
                    'Backend returned HTTP ' +
                    response.status
                );

            }


            const result =
                await response.json();


            console.info(
                'Marcid Blue: load response:',
                result
            );


            if (!result.success) {

                console.warn(
                    'Marcid Blue: draft load failed:',
                    result.message
                );

                return;

            }


            dailyId =
                Number(
                    result.daily_id
                ) || 0;


            if (dailyId <= 0) {

                console.warn(
                    'Marcid Blue: no valid daily ID returned.'
                );

                return;

            }


            console.info(
                'Marcid Blue: daily ID =',
                dailyId
            );


            if (
                !result.has_draft ||
                !result.draft
            ) {

                console.info(
                    'Marcid Blue: no saved draft for today.'
                );

                return;

            }


            const draft =
                result.draft;


            restoreShopState(
                draft.shop
            );


            restoreDriverState(
                draft.driver
            );


            if (
                Array.isArray(
                    draft.payroll
                )
            ) {

                restorePayrollState(
                    draft.payroll
                );

            }


            requestAnimationFrame(
                () => {

                    recalculate();

                    scheduleCurrentDebtRefresh();

                    console.info(
                        'Marcid Blue: daily draft restored.'
                    );

                }
            );


        } catch (error) {

            console.error(
                'Marcid Blue: unable to load daily draft.',
                error
            );

        } finally {

            isRestoring = false;

        }

    }


/*
 * ---------------------------------------------------------
 * SAVE DRAFT
 * ---------------------------------------------------------
 */

    async function saveDraft() {

        if (
            isRestoring ||
            dailyId <= 0
        ) {

            return;

        }


        /*
        * -----------------------------------------------------
        * If another save is already running, do not start
        * another request.
        *
        * Simply mark that another save is needed.
        *
        * The active save will automatically save the newest
        * state after it finishes.
        * -----------------------------------------------------
        */

        if (isSaving) {

            saveQueued = true;

            if (savePromise) {

                await savePromise;

            }

            return;

        }


        isSaving = true;


        /*
        * -----------------------------------------------------
        * Capture the current state only when the save actually
        * begins.
        *
        * This means the latest Payroll / Shop / Driver values
        * are what get saved.
        * -----------------------------------------------------
        */

        const draft =
            collectDraft();


        const body =
            new URLSearchParams();


        body.set(
            'action',
            'save'
        );


        body.set(
            'daily_id',
            String(dailyId)
        );


        body.set(
            'draft',
            JSON.stringify(draft)
        );


        console.info(
            'Marcid Blue: saving daily draft...',
            draft
        );


        /*
        * -----------------------------------------------------
        * Store the active request Promise.
        *
        * Finalize can wait for this Promise instead of starting
        * another save request.
        * -----------------------------------------------------
        */

        savePromise =
            (async () => {

                try {

                    const response =
                        await fetch(
                            BACKEND_URL,
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                cache: 'no-store',
                                headers: {
                                    'Content-Type':
                                        'application/x-www-form-urlencoded; charset=UTF-8',

                                    Accept:
                                        'application/json'
                                },
                                body
                            }
                        );


                    if (!response.ok) {

                        throw new Error(
                            'Backend returned HTTP ' +
                            response.status
                        );

                    }


                    const result =
                        await response.json();


                    if (!result.success) {

                        throw new Error(
                            result.message ||
                            'Draft save failed.'
                        );

                    }


                    console.info(
                        'Marcid Blue: draft saved successfully.'
                    );


                } catch (error) {

                    console.error(
                        'Marcid Blue: autosave failed.',
                        error
                    );


                    /*
                    * Do not throw the error back into the UI.
                    *
                    * The user can continue editing and the next
                    * autosave can try again.
                    */

                }

            })();


        try {

            await savePromise;

        } finally {

            isSaving = false;

            savePromise = null;


            /*
            * -------------------------------------------------
            * A change happened while the previous request was
            * running.
            *
            * Save the newest state once.
            *
            * This does NOT use scheduleAutosave(), because the
            * change has already waited for the active request.
            * -------------------------------------------------
            */

            if (saveQueued) {

                saveQueued = false;

                await saveDraft();

            }

        }

    }


    /*
    * ---------------------------------------------------------
    * AUTOSAVE
    * ---------------------------------------------------------
    */

    /*
     * ---------------------------------------------------------
     * SAVE BEFORE PAGE REFRESH / NAVIGATION
     * ---------------------------------------------------------
     *
     * A normal debounce can still be cancelled by the browser
     * when the user refreshes immediately after editing.
     *
     * Keepalive allows the final draft request to continue while
     * the page is being unloaded.
     * ---------------------------------------------------------
     */
    function saveDraftBeforeUnload() {

        if (
            isRestoring ||
            dailyId <= 0
        ) {
            return;
        }


        clearTimeout(
            autosaveTimer
        );


        const draft =
            collectDraft();


        const body =
            new URLSearchParams();


        body.set(
            'action',
            'save'
        );


        body.set(
            'daily_id',
            String(dailyId)
        );


        body.set(
            'draft',
            JSON.stringify(draft)
        );


        try {

            fetch(
                BACKEND_URL,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    keepalive: true,
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8',

                        Accept:
                            'application/json'
                    },
                    body
                }
            );

        } catch (error) {

            console.error(
                'Marcid Blue: unable to save draft before page exit.',
                error
            );

        }

    }


    function scheduleAutosave() {

        if (
            isRestoring ||
            dailyId <= 0
        ) {

            return;

        }


        /*
        * -----------------------------------------------------
        * Reset the existing timer.
        *
        * Every new edit therefore restarts the countdown.
        *
        * Example:
        *
        * 18:00:00  edit
        * 18:00:01  edit
        * 18:00:02  edit
        * 18:00:03  edit
        *
        * Only one save happens after the user stops editing.
        * -----------------------------------------------------
        */

        clearTimeout(
            autosaveTimer
        );


        autosaveTimer =
            setTimeout(
                () => {

                    /*
                    * If a save is already running, do not start
                    * another request. Tell the active save that
                    * another save is needed afterward.
                    */

                    if (isSaving) {

                        saveQueued = true;

                        return;

                    }


                    saveDraft();

                },
                AUTOSAVE_DELAY
            );

    }




    /*
     * ---------------------------------------------------------
     * RESET
     * ---------------------------------------------------------
     */

    function clearInputFields() {

        document
            .querySelectorAll(
                'input'
            )
            .forEach(
                input => {
                        if (
                            input.matches(
                                '.payroll-amount-input'
                            )
                        ) {

                            /*
                            * Reset the actual Payroll Amount to zero.
                            *
                            * Daily Rate is NOT the Payroll Amount.
                            * It is only the employee's reference rate.
                            */

                            input.value =
                                '0.00';


                            return;

                        }


                    input.value = '';

                }
            );


        document
            .querySelectorAll(
                'select'
            )
            .forEach(
                select => {

                    if (
                        select.classList.contains(
                            'delivery-method'
                        )
                    ) {

                        select.value = 'Cash';

                        return;

                    }


                    if (
                        select.classList.contains(
                            'driver-delivery-method'
                        )
                    ) {

                        select.value = 'Cash';

                        return;

                    }


                    if (
                        select.options.length > 0
                    ) {

                        select.selectedIndex = 0;

                    }

                }
            );


        document
            .querySelectorAll(
                '.delivery-balance, ' +
                '.driver-delivery-balance'
            )
            .forEach(
                element => {

                    element.className =
                        element.classList.contains(
                            'driver-delivery-balance'
                        )
                            ?
                        'driver-delivery-balance driver-delivery-balance-neutral'
                            :
                        'delivery-balance delivery-balance-neutral';

                    element.textContent = '—';

                }
            );


        updatePayrollTotal();

        recalculate();

    }


    async function deleteSavedDraft() {

        if (dailyId <= 0) {
            return;
        }


        try {

            const body =
                new URLSearchParams();


            body.set(
                'action',
                'reset'
            );


            body.set(
                'daily_id',
                String(dailyId)
            );


            const response =
                await fetch(
                    BACKEND_URL,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded; charset=UTF-8',

                            Accept:
                                'application/json'
                        },
                        body
                    }
                );


            if (!response.ok) {

                throw new Error(
                    'Backend returned HTTP ' +
                    response.status
                );

            }


            const result =
                await response.json();


            if (!result.success) {

                throw new Error(
                    result.message ||
                    'Reset failed.'
                );

            }


        } catch (error) {

            console.error(
                'Marcid Blue: unable to reset saved draft.',
                error
            );

        }

    }


    async function resetDailyClosing() {

        const confirmed =
            window.confirm(
                'Reset today\'s Daily Closing?\n\n' +
                'This will clear the entered values, ' +
                'but it will keep all existing rows and boxes.'
            );


        if (!confirmed) {
            return;
        }


        clearTimeout(
            autosaveTimer
        );


        isRestoring = true;


        try {

            clearInputFields();

            await deleteSavedDraft();

            console.info(
                'Marcid Blue: Daily Closing reset.'
            );

        } finally {

            isRestoring = false;

        }

    }


    /*
     * ---------------------------------------------------------
     * DAILY CLOSING SIDE PANELS
     * ---------------------------------------------------------
     */

    function bindSidePanels() {

        const payrollTab =
            getElement(
                'payrollTab'
            );


        const payrollDrawer =
            getElement(
                'payrollDrawer'
            );


        const payrollClose =
            getElement(
                'payrollClose'
            );


        const currentDebtTab =
            getElement(
                'currentDebtTab'
            );


        const currentDebtDrawer =
            getElement(
                'currentDebtDrawer'
            );


        const currentDebtClose =
            getElement(
                'currentDebtClose'
            );


        const overlay =
            getElement(
                'currentDebtOverlay'
            );


        if (
            !payrollTab &&
            !currentDebtTab
        ) {

            return;

        }


        function hideBothTabs() {

            if (payrollTab) {

                payrollTab.classList.add(
                    'is-hidden'
                );

            }


            if (currentDebtTab) {

                currentDebtTab.classList.add(
                    'is-hidden'
                );

            }

        }


        function showBothTabs() {

            if (payrollTab) {

                payrollTab.classList.remove(
                    'is-hidden'
                );

            }


            if (currentDebtTab) {

                currentDebtTab.classList.remove(
                    'is-hidden'
                );

            }

        }


        function closeAllSideDrawers() {

            if (payrollDrawer) {

                payrollDrawer.classList.remove(
                    'open'
                );

                payrollDrawer.setAttribute(
                    'aria-hidden',
                    'true'
                );

            }


            if (currentDebtDrawer) {

                currentDebtDrawer.classList.remove(
                    'open'
                );

                currentDebtDrawer.setAttribute(
                    'aria-hidden',
                    'true'
                );

            }


            showBothTabs();


            if (overlay) {

                overlay.classList.remove(
                    'open'
                );

            }

        }


        function openCurrentDebt() {

            if (payrollDrawer) {

                payrollDrawer.classList.remove(
                    'open'
                );

                payrollDrawer.setAttribute(
                    'aria-hidden',
                    'true'
                );

            }


            if (!currentDebtDrawer) {

                return;

            }


            currentDebtDrawer.classList.add(
                'open'
            );

            currentDebtDrawer.setAttribute(
                'aria-hidden',
                'false'
            );


            hideBothTabs();


            if (overlay) {

                overlay.classList.add(
                    'open'
                );

            }


            refreshCurrentDebt();

        }


        async function openPayroll() {

            if (currentDebtDrawer) {

                currentDebtDrawer.classList.remove(
                    'open'
                );

                currentDebtDrawer.setAttribute(
                    'aria-hidden',
                    'true'
                );

            }


            if (!payrollDrawer) {

                return;

            }


            payrollDrawer.classList.add(
                'open'
            );

            payrollDrawer.setAttribute(
                'aria-hidden',
                'false'
            );


            hideBothTabs();


            if (overlay) {

                overlay.classList.add(
                    'open'
                );

            }


            /*
             * Employees are already loaded during startup.
             *
             * Do not render them again every time the Payroll
             * drawer is opened because rendering creates new
             * inputs initialized to 0.00.
             *
             * Re-render only if the employee list has not been
             * loaded yet.
             */
            if (
                !Array.isArray(payrollEmployees) ||
                payrollEmployees.length === 0
            ) {

                await loadPayrollEmployees();

            }

        }


        if (currentDebtTab) {

            currentDebtTab.addEventListener(
                'click',
                openCurrentDebt
            );

        }


        if (currentDebtClose) {

            currentDebtClose.addEventListener(
                'click',
                closeAllSideDrawers
            );

        }


        if (payrollTab) {

            payrollTab.addEventListener(
                'click',
                openPayroll
            );

        }


        if (payrollClose) {

            payrollClose.addEventListener(
                'click',
                closeAllSideDrawers
            );

        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                closeAllSideDrawers
            );

        }


        document.addEventListener(
            'keydown',
            event => {

                if (
                    event.key ===
                    'Escape'
                ) {

                    closeAllSideDrawers();

                }

            }
        );

    }


    /*
     * ---------------------------------------------------------
     * EVENT BINDING
     * ---------------------------------------------------------
     */

    function bindAutosaveEvents() {

        document.addEventListener(
            'input',
            event => {

                if (
                    !event.target.matches(
                        'input'
                    )
                ) {

                    return;

                }


                if (
                    event.target.matches(
                        '.payroll-amount-input'
                    )
                ) {

                    updatePayrollTotal();

                }


                scheduleAutosave();

                scheduleCurrentDebtRefresh();

            }
        );


        document.addEventListener(
            'change',
            event => {

                if (
                    !event.target.matches(
                        'input, select'
                    )
                ) {

                    return;

                }


                if (
                    event.target.matches(
                        '.payroll-amount-input'
                    )
                ) {

                    updatePayrollTotal();

                }


                scheduleAutosave();

                scheduleCurrentDebtRefresh();

            }
        );


        document.addEventListener(
            'click',
            event => {

                const target =
                    event.target.closest(
                        '#addExpenseButton, ' +
                        '#addDeliveryPaymentButton, ' +
                        '#addDriverExpenseButton, ' +
                        '.driver-payment-controls button, ' +
                        '.expense-remove, ' +
                        '.delivery-payment-remove, ' +
                        '.driver-expense-remove, ' +
                        '.driver-delivery-remove'
                    );


                if (!target) {

                    return;

                }


                setTimeout(
                    () => {

                        scheduleAutosave();

                        scheduleCurrentDebtRefresh();

                    },
                    50
                );

            }
        );


        window.addEventListener(
            'beforeunload',
            saveDraftBeforeUnload
        );


        const resetButton =
            getElement(
                'resetDailyClosingButton'
            );


        if (resetButton) {

            resetButton.addEventListener(
                'click',
                resetDailyClosing
            );

        }

    }


    /*
     * ---------------------------------------------------------
     * FINALIZE
     * ---------------------------------------------------------
     */

    async function finalizeDailyClosing() {

        const button =
            getElement(
                'finalizeDailyClosingButton'
            );


        if (!button) {

            console.error(
                'Finalize button not found.'
            );

            return;

        }


        const confirmed =
            window.confirm(
                'Are you sure you want to finalize and close this day?\n\n' +
                'Once finalized, the daily record will be saved and closed.'
            );


        if (!confirmed) {

            return;

        }


        button.disabled = true;


        const originalText =
            button.textContent;


        button.textContent =
            'Finalizing...';


        try {

            await saveDraft();


            const formData =
                new FormData();


            formData.append(
                'action',
                'finalize'
            );


            formData.append(
                'daily_id',
                String(dailyId)
            );


            const response =
                await fetch(
                    BACKEND_URL,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    }
                );


            const result =
                await response.json();


            if (
                !response.ok ||
                !result.success
            ) {

                throw new Error(
                    result.message ||
                    'Unable to finalize the daily closing.'
                );

            }


            window.alert(
                result.message ||
                'Daily closing finalized successfully.'
            );


            window.location.reload();


        } catch (error) {

            console.error(
                'Finalize daily closing error:',
                error
            );


            window.alert(
                error.message ||
                'Unable to finalize the daily closing.'
            );


            button.disabled = false;

            button.textContent =
                originalText;

        }

    }


    /*
     * ---------------------------------------------------------
     * STARTUP
     * ---------------------------------------------------------
     */

    async function initialize() {

        console.info(
            'Marcid Blue: Daily Closing autosave initializing...'
        );


        bindAutosaveEvents();

        bindSidePanels();


        await new Promise(
            resolve =>
                setTimeout(
                    resolve,
                    150
                )
        );


        /*
         * Load active employees before loading the draft.
         * This is important because payroll draft values need
         * actual employee inputs to restore into.
         */
        await loadPayrollEmployees();


        /*
         * Load today's Daily Closing draft.
         */
        await loadDraft();


        /*
         * Restore payroll one more time after the employees
         * have definitely been rendered.
         */
        try {

            if (dailyId > 0) {

                const draftResponse =
                    await fetch(
                        BACKEND_URL +
                        '?action=load&_=' +
                        Date.now(),
                        {
                            method: 'GET',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                Accept:
                                    'application/json'
                            }
                        }
                    );


                if (draftResponse.ok) {

                    const draftResult =
                        await draftResponse.json();


                    if (
                        draftResult.success &&
                        draftResult.has_draft &&
                        draftResult.draft &&
                        Array.isArray(
                            draftResult.draft.payroll
                        )
                    ) {

                        restorePayrollState(
                            draftResult.draft.payroll
                        );

                    }

                }

            }

        } catch (error) {

            console.error(
                'Marcid Blue: unable to restore payroll draft.',
                error
            );

        }


        await refreshCurrentDebt();

    }


    /*
     * ---------------------------------------------------------
     * PUBLIC API
     * ---------------------------------------------------------
     */

    window.marcidBlueDailyClosingData = {

        collectDraft,

        saveDraft,

        loadDraft,

        reset:
            resetDailyClosing,

        finalizeDailyClosing,

        getDailyId:
            () => dailyId,

        getPayrollTotal,

        getPayrollState

    };


    /*
     * ---------------------------------------------------------
     * DOM READY
     * ---------------------------------------------------------
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initialize,
            {
                once: true
            }
        );

    } else {

        initialize();

    }

})();