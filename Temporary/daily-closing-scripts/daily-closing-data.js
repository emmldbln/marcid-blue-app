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
        'daily-closing-scripts/daily-closing-backend.php';

    const AUTOSAVE_DELAY = 700;

    let dailyId = 0;

    let autosaveTimer = null;

    let isRestoring = false;

    let isSaving = false;

    let saveQueued = false;


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

                setInputValue(
                    element,
                    data[key]
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
                            'Accept':
                                'application/json'
                        }
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

                        select.value = '';

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

        reset:
            resetDailyClosing,

        getDailyId:
            () => dailyId

    };

})();