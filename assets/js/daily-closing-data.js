(function () {

    'use strict';

    /*
     * The page is:
     * Temporary/daily-closing-redesign.php
     *
     * The backend is:
     * Temporary/daily-closing-scripts/daily-closing-backend.php
     */
    const BACKEND_URL =
          'backend/daily-closing-backend.php';

    const AUTOSAVE_DELAY = 700;

    const CURRENT_DEBT_REFRESH_DELAY = 350;

    let dailyId = 0;

    let autosaveTimer = null;

    let isRestoring = false;

    let isSaving = false;

    let saveQueued = false;

    let currentDebtRefreshTimer = null;
    let currentDebtRequestId = 0;


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

                // Payment methods default to Cash
                // when the saved draft has no method.
                if (
                    key === 'method' &&
                    (
                        value === null ||
                        value === undefined ||
                        String(value).trim() === ''
                    )
                ) {
                    value = 'Cash';
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
                row => getRowData(
                    row,
                    {
                        category:
                            '.expense-category',

                        name:
                            '.expense-name',

                        amount:
                            '.expense-amount'
                    }
                )
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
                collectDriverState()

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
            state.expenses.length
        );


        ensureRowCount(
            '#deliveryPaymentRows',
            'addDeliveryPaymentButton',
            state.deliveries.length
        );

    }


    function ensureDriverRows(state) {

        if (!state) {
            return;
        }

        ensureRowCount(
            '#driverExpenseRows',
            'addDriverExpenseButton',
            state.expenses.length
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
            state.deliveries.length;


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


        const expenseRows =
            getRows(
                '.expense-row'
            );


        state.expenses.forEach(
            (data, index) => {

                setRowData(
                    expenseRows[index],
                    {
                        category:
                            '.expense-category',

                        name:
                            '.expense-name',

                        amount:
                            '.expense-amount'
                    },
                    data
                );

            }
        );


        const deliveryRows =
            getRows(
                '.delivery-payment-row'
            );


        state.deliveries.forEach(
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


        const expenseRows =
            getRows(
                '.driver-expense-row'
            );


        state.expenses.forEach(
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


        state.deliveries.forEach(
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
     * RECALCULATE
     * ---------------------------------------------------------
     */

    function recalculate() {

        if (
            window.marcidBlueDailyClosing &&
            typeof
                window
                    .marcidBlueDailyClosing
                    .calculateAll ===
                'function'
        ) {

            window
                .marcidBlueDailyClosing
                .calculateAll();

            return;

        }


        document
            .querySelectorAll(
                'input, select'
            )
            .forEach(
                element => {

                    element.dispatchEvent(
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

    function renderCurrentDebt(
        result
    ) {

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

            /*
            * =====================================================
            * EACH DATE
            * =====================================================
            */

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


                    /*
                    * ---------------------------------------------
                    * DATE HEADING
                    * ---------------------------------------------
                    */

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


                    /*
                    * =================================================
                    * GROUP BY CUSTOMER WITHIN THIS DATE ONLY
                    * =================================================
                    *
                    * Same customer + same date:
                    *
                    *     Dennis ₱140
                    *     Dennis ₱140
                    *
                    * becomes:
                    *
                    *     Dennis ₱280
                    *             ₱140 + ₱140
                    *
                    *
                    * Same customer + different date:
                    *
                    *     Sep 10  Dennis ₱140
                    *     Sep 11  Dennis ₱140
                    *
                    * remains two separate entries.
                    */

                    const customerGroups = {};


                    debts.forEach(
                        debt => {

                            /*
                            * Prefer customer_id.
                            *
                            * The fallback customer name is only
                            * used if customer_id is unavailable.
                            */

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


                    /*
                    * =================================================
                    * RENDER EACH CUSTOMER
                    * =================================================
                    */

                    Object.values(
                        customerGroups
                    ).forEach(
                        group => {

                            const customerDebts =
                                group.debts;


                            /*
                            * -----------------------------------------
                            * TOTAL ORIGINAL AMOUNT
                            * -----------------------------------------
                            */

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


                            /*
                            * -----------------------------------------
                            * TOTAL REMAINING AMOUNT
                            * -----------------------------------------
                            */

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


                            /*
                            * -----------------------------------------
                            * TOTAL DRAFT PAYMENT
                            * -----------------------------------------
                            */

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


                            /*
                            * -----------------------------------------
                            * HAS DRAFT PAYMENT?
                            * -----------------------------------------
                            */

                            const hasDraftPayment =
                                totalDraftPayment >
                                0.009;


                            /*
                            * -----------------------------------------
                            * FULLY PAID IN CURRENT DRAFT?
                            * -----------------------------------------
                            */

                            const isDraftPaid =
                                totalRemaining <= 0.009 &&
                                hasDraftPayment;


                            /*
                            * -----------------------------------------
                            * MAIN ITEM
                            * -----------------------------------------
                            */

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


                            /*
                            * -----------------------------------------
                            * HEADER
                            * -----------------------------------------
                            */

                            const header =
                                document.createElement(
                                    'div'
                                );


                            header.className =
                                'current-debt-item-header';


                            /*
                            * -----------------------------------------
                            * CUSTOMER NAME
                            * -----------------------------------------
                            */

                            const name =
                                document.createElement(
                                    'div'
                                );


                            name.className =
                                'current-debt-customer';


                            name.textContent =
                                group.customer_name ||
                                'Unknown Customer';


                            /*
                            * -----------------------------------------
                            * AMOUNT
                            * -----------------------------------------
                            */

                            const amount =
                                document.createElement(
                                    'div'
                                );


                            amount.className =
                                'current-debt-amount';


                            /*
                            * If today's draft payment changed
                            * this customer's debt, show:
                            *
                            *     ₱280.00 → ₱180.00
                            *
                            * Otherwise:
                            *
                            *     ₱280.00
                            */

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


                            /*
                            * =================================================
                            * SAME-DAY TRANSACTION BREAKDOWN
                            * =================================================
                            *
                            * Only show this when the customer has
                            * multiple debt transactions on this date.
                            *
                            * Example:
                            *
                            * Dennis                 ₱280.00
                            *                       ₱140 + ₱140
                            *
                            * A single transaction has no subtext.
                            */

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
        *
        * Credit remains separate from debt.
        *
        * Existing credit:
        *
        *     ₱140.00
        *
        * Existing + draft credit:
        *
        *     ₱140.00 → ₱210.00
        *
        * Draft-only credit:
        *
        *     ₱0.00 → ₱70.00
        *
        * This function only renders the result.
        * It does NOT save anything to SQL.
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

            /*
            * ---------------------------------------------
            * CREDIT HEADING
            * ---------------------------------------------
            */

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


            /*
            * ---------------------------------------------
            * CREDIT CUSTOMERS
            * ---------------------------------------------
            */

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

    function formatDebtMoney(
        value
    ) {

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
                            'Accept': 'application/json'
                        }
                    }
                );


            /*
            * If there is no Open record, the backend returns 404.
            *
            * This can happen after the day has already been
            * finalized and its status is Saved.
            *
            * We must NOT reopen the saved record.
            * We only need its daily ID so Current Debt can load.
            */
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
                                'Accept': 'application/json'
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


                console.info(
                    'Marcid Blue: daily context response:',
                    contextResult
                );


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


                /*
                * The record is already Saved.
                *
                * Do not restore a draft.
                * Do not create a draft.
                * Do not reopen the daily record.
                *
                * We only keep the daily ID so Current Debt
                * can be loaded.
                */
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


            /*
            * The row controllers may need one browser frame
            * to finish creating/updating the rows.
            */
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


        if (isSaving) {

            saveQueued = true;

            return;

        }


        isSaving = true;


        try {

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
                            'Accept':
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

        } finally {

            isSaving = false;


            if (saveQueued) {

                saveQueued = false;

                scheduleAutosave();

            }

        }

    }


    /*
     * ---------------------------------------------------------
     * AUTOSAVE
     * ---------------------------------------------------------
     */

    function scheduleAutosave() {

        if (
            isRestoring ||
            dailyId <= 0
        ) {

            return;

        }


        clearTimeout(
            autosaveTimer
        );


        autosaveTimer =
            setTimeout(
                () => saveDraft(),
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
                            'Accept':
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


        /*
         * Prevent an autosave from running while the
         * reset operation is deleting the saved draft.
         */
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
    * CURRENT DEBT PANEL UI
    * ---------------------------------------------------------
    */

    function bindCurrentDebtPanel() {

        const tab =
            getElement(
                'currentDebtTab'
            );

        const drawer =
            getElement(
                'currentDebtDrawer'
            );

        const closeButton =
            getElement(
                'currentDebtClose'
            );

        const overlay =
            getElement(
                'currentDebtOverlay'
            );


        if (
            !tab ||
            !drawer ||
            !closeButton ||
            !overlay
        ) {
            return;
        }


        function openPanel() {

            drawer.classList.add(
                'open'
            );

            overlay.classList.add(
                'open'
            );

            tab.classList.add(
                'is-hidden'
            );

            drawer.setAttribute(
                'aria-hidden',
                'false'
            );

            refreshCurrentDebt();

        }


        function closePanel() {

            drawer.classList.remove(
                'open'
            );

            overlay.classList.remove(
                'open'
            );

            tab.classList.remove(
                'is-hidden'
            );

            drawer.setAttribute(
                'aria-hidden',
                'true'
            );

        }


        tab.addEventListener(
            'click',
            openPanel
        );


        closeButton.addEventListener(
            'click',
            closePanel
        );


        overlay.addEventListener(
            'click',
            closePanel
        );


        document.addEventListener(
            'keydown',
            event => {

                if (
                    event.key ===
                    'Escape'
                ) {
                    closePanel();
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
                    () => scheduleAutosave(),
                    50
                );

            }
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
     * STARTUP
     * ---------------------------------------------------------
     */

    async function initialize() {

        console.info(
            'Marcid Blue: Daily Closing autosave initializing...'
        );


        bindAutosaveEvents();
        bindCurrentDebtPanel();


        /*
         * Wait until the redesign's own JavaScript has
         * finished initializing its rows and controls.
         */
        await new Promise(
            resolve =>
                setTimeout(
                    resolve,
                    150
                )
        );


        await loadDraft();

        await refreshCurrentDebt();

    }


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


    /*
     * ---------------------------------------------------------
     * PUBLIC API
     * ---------------------------------------------------------
     */

        window.marcidBlueDailyClosingData = {
        collectDraft,
        saveDraft,
        loadDraft,
        reset: resetDailyClosing,
        finalizeDailyClosing,
        getDailyId: () => dailyId
    };


    const finalizeButton = document.getElementById(
        'finalizeDailyClosingButton'
    );

    if (finalizeButton) {
        finalizeButton.addEventListener(
            'click',
            finalizeDailyClosing
        );
    }

    async function finalizeDailyClosing() {
    const button = document.getElementById('finalizeDailyClosingButton');

    if (!button) {
        console.error('Finalize button not found.');
        return;
    }

    const confirmed = window.confirm(
        'Are you sure you want to finalize and close this day?\n\n' +
        'Once finalized, the daily record will be saved and closed.'
    );

    if (!confirmed) {
        return;
    }

    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = 'Finalizing...';

    try {
        // Make sure the latest values are saved into the draft first.
        const draft = collectDraft();
        await saveDraft(draft);

        const formData = new FormData();
        formData.append('action', 'finalize');
        formData.append('daily_id', String(dailyId));

        const response = await fetch(BACKEND_URL, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to finalize the daily closing.'
            );
        }

        alert(
            result.message ||
            'Daily closing finalized successfully.'
        );

        // Reload the page so the newly saved/closed state is displayed.
        window.location.reload();

    } catch (error) {
        console.error('Finalize daily closing error:', error);

        alert(
            error.message ||
            'Unable to finalize the daily closing.'
        );

        button.disabled = false;
        button.textContent = originalText;
    }
}

})();