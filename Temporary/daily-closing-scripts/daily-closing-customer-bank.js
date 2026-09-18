(function () {
    'use strict';

    /*
     * The page is:
     * Temporary/daily-closing-redesign.php
     *
     * Therefore all fetch URLs are resolved from
     * the Temporary directory.
     */
    const BACKEND_URL =
        'daily-closing-scripts/daily-closing-backend.php';

    const SAVE_DELAY = 15000;

    const CUSTOMER_SELECTOR =
        '.delivery-customer, .driver-delivery-customer';

    const customerTimers = new WeakMap();

    let customers = [];
    let dropdown = null;
    let activeInput = null;


    /*
     * ---------------------------------------------------------
     * GENERAL HELPERS
     * ---------------------------------------------------------
     */

    function normalize(value) {
        return String(value ?? '')
            .trim()
            .toLowerCase();
    }


    function isValidPrice(value) {
        const price = Number(value);

        return Number.isFinite(price) &&
            price > 0;
    }


    function getRow(input) {
        return input.closest(
            '.delivery-payment-row, .driver-delivery-row'
        );
    }


    function getPriceInput(input) {
        const row = getRow(input);

        if (!row) {
            return null;
        }

        if (
            input.classList.contains(
                'delivery-customer'
            )
        ) {
            return row.querySelector(
                '.delivery-price-input'
            );
        }

        if (
            input.classList.contains(
                'driver-delivery-customer'
            )
        ) {
            return row.querySelector(
                '.driver-delivery-price'
            );
        }

        return null;
    }


    function clearTimer(input) {
        const timer =
            customerTimers.get(input);

        if (!timer) {
            return;
        }

        clearTimeout(timer);

        customerTimers.delete(input);
    }


    /*
     * ---------------------------------------------------------
     * CUSTOMER LOADING
     * ---------------------------------------------------------
     */

    async function loadCustomers() {
        try {
            const url =
                new URL(
                    BACKEND_URL,
                    window.location.href
                );

            url.searchParams.set(
                'action',
                'get_customers'
            );

            url.searchParams.set(
                '_',
                Date.now().toString()
            );

            const response =
                await fetch(
                    url.toString(),
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
                    `Customer request failed: HTTP ${response.status}`
                );
            }

            const data =
                await response.json();

            if (
                !data.success ||
                !Array.isArray(
                    data.customers
                )
            ) {
                throw new Error(
                    data.message ||
                    'Invalid customer response.'
                );
            }

            customers =
                data.customers
                    .map(
                        customer => ({
                            id:
                                Number(
                                    customer.customer_id
                                ),

                            name:
                                String(
                                    customer.customer_name ??
                                    ''
                                ).trim(),

                            price:
                                Number(
                                    customer.gallon_price
                                )
                        })
                    )
                    .filter(
                        customer =>
                            customer.name !== ''
                    );

            console.info(
                `Marcid Blue Customer Bank: ${customers.length} customers loaded.`
            );

        } catch (error) {
            customers = [];

            console.error(
                'Marcid Blue Customer Bank: unable to load customers.',
                error
            );
        }
    }


    /*
     * ---------------------------------------------------------
     * DROPDOWN
     * ---------------------------------------------------------
     */

    function createDropdown() {
        if (dropdown) {
            return dropdown;
        }

        dropdown =
            document.createElement('div');

        dropdown.id =
            'marcidBlueCustomerDropdown';

        dropdown.setAttribute(
            'role',
            'listbox'
        );

        Object.assign(
            dropdown.style,
            {
                position: 'fixed',
                zIndex: '999999',
                display: 'none',
                boxSizing: 'border-box',
                maxHeight: '280px',
                overflowY: 'auto',
                background: '#ffffff',
                border: '1px solid #d9dee7',
                borderRadius: '8px',
                boxShadow:
                    '0 8px 24px rgba(0,0,0,.12)',
                padding: '4px 0'
            }
        );

        document.body.appendChild(
            dropdown
        );

        return dropdown;
    }


    function positionDropdown(input) {
        if (!dropdown) {
            return;
        }

        const rect =
            input.getBoundingClientRect();

        dropdown.style.left =
            `${rect.left}px`;

        dropdown.style.top =
            `${rect.bottom + 4}px`;

        dropdown.style.width =
            `${Math.max(
                rect.width,
                240
            )}px`;
    }


    function closeDropdown() {
        if (!dropdown) {
            return;
        }

        dropdown.style.display =
            'none';

        activeInput = null;
    }


    function openDropdown(input) {
        const menu =
            createDropdown();

        activeInput = input;

        renderDropdown(input);

        positionDropdown(input);

        menu.style.display =
            'block';
    }


    function renderDropdown(input) {
        const menu =
            createDropdown();

        const search =
            normalize(input.value);

        menu.innerHTML = '';

        const filtered =
            customers.filter(
                customer => {
                    if (!search) {
                        return true;
                    }

                    return normalize(
                        customer.name
                    ).includes(search);
                }
            );

        if (filtered.length === 0) {
            const empty =
                document.createElement('div');

            empty.textContent =
                customers.length === 0
                    ? 'Unable to load customers'
                    : 'No matching customer';

            Object.assign(
                empty.style,
                {
                    padding: '11px 13px',
                    fontSize: '13px',
                    color: '#6b7280'
                }
            );

            menu.appendChild(empty);

            return;
        }

        filtered.forEach(
            customer => {
                const option =
                    document.createElement(
                        'button'
                    );

                option.type =
                    'button';

                option.setAttribute(
                    'role',
                    'option'
                );

                Object.assign(
                    option.style,
                    {
                        display: 'block',
                        width: '100%',
                        border: '0',
                        background:
                            'transparent',
                        padding:
                            '10px 13px',
                        textAlign:
                            'left',
                        cursor:
                            'pointer',
                        fontFamily:
                            'inherit'
                    }
                );

                const name =
                    document.createElement(
                        'div'
                    );

                name.textContent =
                    customer.name;

                Object.assign(
                    name.style,
                    {
                        fontSize: '14px',
                        fontWeight: '600',
                        color: '#1f2937'
                    }
                );

                const price =
                    document.createElement(
                        'div'
                    );

                price.textContent =
                    isValidPrice(
                        customer.price
                    )
                        ? `₱${Number(
                              customer.price
                          ).toFixed(2)} / gal`
                        : 'No price';

                Object.assign(
                    price.style,
                    {
                        marginTop: '2px',
                        fontSize: '12px',
                        color: '#6b7280'
                    }
                );

                option.appendChild(name);
                option.appendChild(price);

                option.addEventListener(
                    'mouseenter',
                    function () {
                        this.style.background =
                            '#f5f7fa';
                    }
                );

                option.addEventListener(
                    'mouseleave',
                    function () {
                        this.style.background =
                            'transparent';
                    }
                );

                option.addEventListener(
                    'mousedown',
                    function (event) {
                        event.preventDefault();

                        selectCustomer(
                            input,
                            customer
                        );
                    }
                );

                menu.appendChild(option);
            }
        );
    }


    /*
     * ---------------------------------------------------------
     * CUSTOMER SELECTION
     * ---------------------------------------------------------
     */

    function selectCustomer(
        input,
        customer
    ) {
        clearTimer(input);

        input.value =
            customer.name;

        const priceInput =
            getPriceInput(input);

        if (
            priceInput &&
            isValidPrice(
                customer.price
            )
        ) {
            priceInput.value =
                Number(
                    customer.price
                ).toString();

            triggerChange(
                priceInput
            );
        }

        triggerChange(input);

        closeDropdown();
    }


    function triggerChange(input) {
        input.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles: true
                }
            )
        );

        input.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles: true
                }
            )
        );
    }


    /*
     * ---------------------------------------------------------
     * CUSTOMER PRICE AUTOSAVE
     * ---------------------------------------------------------
     */

    function scheduleSave(input) {
        clearTimer(input);

        const priceInput =
            getPriceInput(input);

        if (!priceInput) {
            return;
        }

        const customerName =
            input.value.trim();

        const price =
            priceInput.value.trim();

        if (
            customerName === '' ||
            !isValidPrice(price)
        ) {
            return;
        }

        const expectedName =
            customerName;

        const expectedPrice =
            price;

        const timer =
            setTimeout(
                function () {
                    saveCustomerPrice(
                        input,
                        expectedName,
                        expectedPrice
                    );
                },
                SAVE_DELAY
            );

        customerTimers.set(
            input,
            timer
        );
    }


    async function saveCustomerPrice(
        input,
        expectedName,
        expectedPrice
    ) {
        customerTimers.delete(input);

        const priceInput =
            getPriceInput(input);

        if (!priceInput) {
            return;
        }

        const currentName =
            input.value.trim();

        const currentPrice =
            priceInput.value.trim();

        if (
            currentName !== expectedName ||
            currentPrice !== expectedPrice
        ) {
            return;
        }

        if (
            currentName === '' ||
            !isValidPrice(currentPrice)
        ) {
            return;
        }

        const body =
            new URLSearchParams();

        body.set(
            'action',
            'save_customer_price'
        );

        body.set(
            'customer_name',
            currentName
        );

        body.set(
            'gallon_price',
            currentPrice
        );

        try {
            const response =
                await fetch(
                    BACKEND_URL,
                    {
                        method: 'POST',
                        credentials:
                            'same-origin',
                        headers: {
                            'Content-Type':
                                'application/x-www-form-urlencoded; charset=UTF-8',

                            Accept:
                                'application/json'
                        },
                        body:
                            body.toString()
                    }
                );

            if (!response.ok) {
                throw new Error(
                    `Customer save failed: HTTP ${response.status}`
                );
            }

            const data =
                await response.json();

            if (!data.success) {
                throw new Error(
                    data.message ||
                    'Customer price save failed.'
                );
            }

            updateLocalCustomer(
                currentName,
                Number(currentPrice)
            );

            console.info(
                'Marcid Blue Customer Bank: customer price updated.'
            );

        } catch (error) {
            console.error(
                'Marcid Blue Customer Bank: price save failed.',
                error
            );
        }
    }


    function updateLocalCustomer(
        name,
        price
    ) {
        const normalized =
            normalize(name);

        const existing =
            customers.find(
                customer =>
                    normalize(
                        customer.name
                    ) === normalized
            );

        if (existing) {
            existing.price =
                price;

            return;
        }

        customers.push({
            id: 0,
            name,
            price
        });

        customers.sort(
            (a, b) =>
                a.name.localeCompare(
                    b.name
                )
        );
    }


    /*
     * ---------------------------------------------------------
     * INPUT HANDLERS
     * ---------------------------------------------------------
     */

    function handleCustomerFocus(
        event
    ) {
        openDropdown(
            event.currentTarget
        );
    }


    function handleCustomerInput(
        event
    ) {
        const input =
            event.currentTarget;

        clearTimer(input);

        renderDropdown(input);

        scheduleSave(input);
    }


    function handlePriceInput(
        event
    ) {
        const priceInput =
            event.currentTarget;

        const row =
            priceInput.closest(
                '.delivery-payment-row, .driver-delivery-row'
            );

        if (!row) {
            return;
        }

        const customerInput =
            row.querySelector(
                '.delivery-customer, .driver-delivery-customer'
            );

        if (!customerInput) {
            return;
        }

        scheduleSave(
            customerInput
        );
    }


    function handleCustomerKeydown(
        event
    ) {
        if (
            event.key === 'Escape'
        ) {
            closeDropdown();
        }
    }


    function handleDocumentPointerDown(
        event
    ) {
        if (!dropdown) {
            return;
        }

        if (
            dropdown.contains(
                event.target
            )
        ) {
            return;
        }

        if (
            event.target.matches &&
            event.target.matches(
                CUSTOMER_SELECTOR
            )
        ) {
            return;
        }

        closeDropdown();
    }


    /*
     * ---------------------------------------------------------
     * DYNAMIC ROW SUPPORT
     * ---------------------------------------------------------
     */

    function attachCustomerInput(
        input
    ) {
        if (
            input.dataset
                .marcidCustomerBankAttached ===
            'true'
        ) {
            return;
        }

        input.dataset
            .marcidCustomerBankAttached =
            'true';

        input.addEventListener(
            'focus',
            handleCustomerFocus
        );

        input.addEventListener(
            'click',
            handleCustomerFocus
        );

        input.addEventListener(
            'input',
            handleCustomerInput
        );

        input.addEventListener(
            'keydown',
            handleCustomerKeydown
        );

        const priceInput =
            getPriceInput(input);

        if (
            priceInput &&
            priceInput.dataset
                .marcidCustomerPriceAttached !==
                'true'
        ) {
            priceInput.dataset
                .marcidCustomerPriceAttached =
                'true';

            priceInput.addEventListener(
                'input',
                handlePriceInput
            );

            priceInput.addEventListener(
                'change',
                handlePriceInput
            );
        }
    }


    function scanCustomerInputs() {
        document
            .querySelectorAll(
                CUSTOMER_SELECTOR
            )
            .forEach(
                attachCustomerInput
            );
    }


    function observeDynamicRows() {
        const observer =
            new MutationObserver(
                function () {
                    scanCustomerInputs();
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
     * ---------------------------------------------------------
     * POSITIONING
     * ---------------------------------------------------------
     */

    function repositionDropdown() {
        if (
            !dropdown ||
            !activeInput ||
            dropdown.style.display ===
                'none'
        ) {
            return;
        }

        positionDropdown(
            activeInput
        );
    }


    /*
     * ---------------------------------------------------------
     * INITIALIZATION
     * ---------------------------------------------------------
     */

    async function initialize() {
        createDropdown();

        await loadCustomers();

        scanCustomerInputs();

        observeDynamicRows();

        document.addEventListener(
            'mousedown',
            handleDocumentPointerDown
        );

        window.addEventListener(
            'resize',
            repositionDropdown
        );

        window.addEventListener(
            'scroll',
            repositionDropdown,
            true
        );

        console.info(
            'Marcid Blue Customer Bank: initialized.'
        );
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
})();