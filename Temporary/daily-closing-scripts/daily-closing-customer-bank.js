(function () {
    'use strict';

    const BACKEND_URL = 'daily-closing-backend.php';
    const SAVE_DELAY = 15000;

    const CUSTOMER_SELECTOR = [
        '.delivery-customer',
        '.driver-delivery-customer'
    ].join(',');

    const PRICE_SELECTORS = {
        '.delivery-customer': '.delivery-price-input',
        '.driver-delivery-customer': '.driver-delivery-price'
    };

    let customers = [];
    let activeDropdown = null;
    let saveTimers = new WeakMap();
    let rowStates = new WeakMap();

    function getCustomerFields() {
        return document.querySelectorAll(CUSTOMER_SELECTOR);
    }

    function getPriceField(customerInput) {
        const selector = Object.keys(PRICE_SELECTORS).find(
            key => customerInput.matches(key)
        );

        if (!selector) {
            return null;
        }

        const row = customerInput.closest(
            '.delivery-payment-row, .driver-delivery-row'
        );

        if (!row) {
            return null;
        }

        return row.querySelector(PRICE_SELECTORS[selector]);
    }

    function getRow(customerInput) {
        return customerInput.closest(
            '.delivery-payment-row, .driver-delivery-row'
        );
    }

    function normalize(value) {
        return String(value || '')
            .trim()
            .toLowerCase();
    }

    function isValidPrice(value) {
        const number = Number(value);

        return Number.isFinite(number) && number > 0;
    }

    function clearSaveTimer(customerInput) {
        const timer = saveTimers.get(customerInput);

        if (timer) {
            clearTimeout(timer);
            saveTimers.delete(customerInput);
        }
    }

    function saveState(customerInput, customerName, price) {
        rowStates.set(customerInput, {
            customerName,
            price
        });
    }

    function getState(customerInput) {
        return rowStates.get(customerInput) || {
            customerName: '',
            price: ''
        };
    }

    async function fetchCustomers() {
        try {
            const response = await fetch(
                `${BACKEND_URL}?action=get_customers`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Customer request failed: ${response.status}`
                );
            }

            const data = await response.json();

            if (!data.success || !Array.isArray(data.customers)) {
                throw new Error(
                    data.message || 'Unable to load customers.'
                );
            }

            customers = data.customers
                .map(customer => ({
                    id: Number(customer.customer_id),
                    name: String(customer.customer_name || '').trim(),
                    price: Number(customer.gallon_price)
                }))
                .filter(customer => customer.name !== '');

        } catch (error) {
            console.error(
                'Marcid Blue customer bank:',
                error
            );

            customers = [];
        }
    }

    function createDropdown() {
        const dropdown = document.createElement('div');

        dropdown.className = 'marcid-customer-dropdown';

        Object.assign(dropdown.style, {
            position: 'fixed',
            zIndex: '99999',
            display: 'none',
            maxHeight: '260px',
            overflowY: 'auto',
            background: '#ffffff',
            border: '1px solid #d9dee7',
            borderRadius: '8px',
            boxShadow: '0 8px 24px rgba(0, 0, 0, 0.12)',
            padding: '4px 0',
            boxSizing: 'border-box'
        });

        document.body.appendChild(dropdown);

        return dropdown;
    }

    function positionDropdown(dropdown, input) {
        const rect = input.getBoundingClientRect();

        dropdown.style.left = `${rect.left}px`;
        dropdown.style.top = `${rect.bottom + 4}px`;
        dropdown.style.width = `${Math.max(rect.width, 220)}px`;
    }

    function closeDropdown() {
        if (!activeDropdown) {
            return;
        }

        activeDropdown.style.display = 'none';
        activeDropdown = null;
    }

    function createCustomerOption(customer, customerInput) {
        const option = document.createElement('button');

        option.type = 'button';
        option.className = 'marcid-customer-option';

        Object.assign(option.style, {
            display: 'block',
            width: '100%',
            border: '0',
            background: 'transparent',
            padding: '10px 12px',
            textAlign: 'left',
            cursor: 'pointer',
            fontFamily: 'inherit',
            fontSize: '14px',
            color: '#1f2937'
        });

        const priceText = isValidPrice(customer.price)
            ? `₱${customer.price.toFixed(2)} / gal`
            : 'No price';

        option.innerHTML = `
            <div style="font-weight: 600;">
                ${escapeHtml(customer.name)}
            </div>
            <div style="
                margin-top: 2px;
                font-size: 12px;
                color: #6b7280;
            ">
                ${priceText}
            </div>
        `;

        option.addEventListener('mouseenter', function () {
            this.style.background = '#f5f7fa';
        });

        option.addEventListener('mouseleave', function () {
            this.style.background = 'transparent';
        });

        option.addEventListener('mousedown', function (event) {
            event.preventDefault();

            selectCustomer(
                customerInput,
                customer
            );
        });

        return option;
    }

    function createEmptyOption(message) {
        const option = document.createElement('div');

        option.textContent = message;

        Object.assign(option.style, {
            padding: '10px 12px',
            fontSize: '13px',
            color: '#6b7280'
        });

        return option;
    }

    function renderDropdown(customerInput) {
        if (!activeDropdown) {
            activeDropdown = createDropdown();
        }

        const dropdown = activeDropdown;
        const searchValue = normalize(customerInput.value);

        dropdown.innerHTML = '';

        const filteredCustomers = customers.filter(customer => {
            if (!searchValue) {
                return true;
            }

            return normalize(customer.name)
                .includes(searchValue);
        });

        if (filteredCustomers.length === 0) {
            dropdown.appendChild(
                createEmptyOption(
                    searchValue
                        ? 'No matching customer'
                        : 'No customers found'
                )
            );
        } else {
            filteredCustomers.forEach(customer => {
                dropdown.appendChild(
                    createCustomerOption(
                        customer,
                        customerInput
                    )
                );
            });
        }

        positionDropdown(
            dropdown,
            customerInput
        );

        dropdown.style.display = 'block';
    }

    function selectCustomer(customerInput, customer) {
        clearSaveTimer(customerInput);

        customerInput.value = customer.name;

        const priceInput = getPriceField(
            customerInput
        );

        if (
            priceInput &&
            isValidPrice(customer.price)
        ) {
            priceInput.value = customer.price;
        }

        saveState(
            customerInput,
            customer.name,
            priceInput ? priceInput.value : ''
        );

        closeDropdown();

        triggerInput(customerInput);

        if (priceInput) {
            triggerInput(priceInput);
        }
    }

    function triggerInput(element) {
        element.dispatchEvent(
            new Event('input', {
                bubbles: true
            })
        );

        element.dispatchEvent(
            new Event('change', {
                bubbles: true
            })
        );
    }

    function scheduleCustomerSave(customerInput) {
        clearSaveTimer(customerInput);

        const priceInput = getPriceField(
            customerInput
        );

        if (!priceInput) {
            return;
        }

        const customerName = customerInput.value.trim();
        const price = priceInput.value.trim();

        if (
            customerName === '' ||
            !isValidPrice(price)
        ) {
            return;
        }

        const timer = setTimeout(
            function () {
                saveCustomerPrice(
                    customerInput,
                    customerName,
                    price
                );
            },
            SAVE_DELAY
        );

        saveTimers.set(
            customerInput,
            timer
        );
    }

    async function saveCustomerPrice(
        customerInput,
        customerName,
        price
    ) {
        saveTimers.delete(customerInput);

        const currentName =
            customerInput.value.trim();

        const priceInput =
            getPriceField(customerInput);

        const currentPrice =
            priceInput
                ? priceInput.value.trim()
                : '';

        if (
            currentName !== customerName ||
            currentPrice !== String(price)
        ) {
            return;
        }

        if (
            currentName === '' ||
            !isValidPrice(currentPrice)
        ) {
            return;
        }

        const body = new URLSearchParams();

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
            const response = await fetch(
                BACKEND_URL,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                }
            );

            if (!response.ok) {
                throw new Error(
                    `Customer save failed: ${response.status}`
                );
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(
                    data.message ||
                    'Customer price could not be saved.'
                );
            }

            updateLocalCustomer(
                currentName,
                Number(currentPrice)
            );

            saveState(
                customerInput,
                currentName,
                currentPrice
            );

        } catch (error) {
            console.error(
                'Marcid Blue customer price save:',
                error
            );
        }
    }

    function updateLocalCustomer(
        customerName,
        price
    ) {
        const normalizedName =
            normalize(customerName);

        const existing =
            customers.find(
                customer =>
                    normalize(customer.name) ===
                    normalizedName
            );

        if (existing) {
            existing.price = price;
            return;
        }

        customers.push({
            id: 0,
            name: customerName,
            price
        });

        customers.sort(
            (a, b) =>
                a.name.localeCompare(
                    b.name
                )
        );
    }

    function handleCustomerFocus(event) {
        const input = event.currentTarget;

        renderDropdown(input);
    }

    function handleCustomerInput(event) {
        const input = event.currentTarget;

        clearSaveTimer(input);

        const typedName =
            input.value.trim();

        const existingCustomer =
            customers.find(
                customer =>
                    normalize(customer.name) ===
                    normalize(typedName)
            );

        if (existingCustomer) {
            const priceInput =
                getPriceField(input);

            if (
                priceInput &&
                isValidPrice(
                    existingCustomer.price
                )
            ) {
                priceInput.value =
                    existingCustomer.price;

                triggerInput(priceInput);
            }

            saveState(
                input,
                existingCustomer.name,
                priceInput
                    ? priceInput.value
                    : ''
            );

        } else {
            const previous =
                getState(input);

            if (
                previous.customerName !==
                typedName
            ) {
                const priceInput =
                    getPriceField(input);

                if (
                    priceInput &&
                    previous.customerName !==
                    typedName
                ) {
                    /*
                     * Keep the manually entered price
                     * when creating a new customer.
                     */
                }
            }
        }

        renderDropdown(input);

        scheduleCustomerSave(input);
    }

    function handlePriceInput(event) {
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

        clearSaveTimer(customerInput);

        scheduleCustomerSave(
            customerInput
        );
    }

    function handleCustomerKeydown(event) {
        if (event.key === 'Escape') {
            closeDropdown();
            return;
        }

        if (
            event.key === 'ArrowDown' &&
            activeDropdown &&
            activeDropdown.style.display !== 'none'
        ) {
            const firstOption =
                activeDropdown.querySelector(
                    '.marcid-customer-option'
                );

            if (firstOption) {
                event.preventDefault();
                firstOption.focus();
            }
        }
    }

    function handleDocumentPointerDown(event) {
        if (!activeDropdown) {
            return;
        }

        if (
            event.target.closest(
                '.marcid-customer-dropdown'
            )
        ) {
            return;
        }

        if (
            event.target.matches(
                CUSTOMER_SELECTOR
            )
        ) {
            return;
        }

        closeDropdown();
    }

    function handleWindowResize() {
        if (!activeDropdown) {
            return;
        }

        const input =
            document.activeElement;

        if (
            input &&
            input.matches(CUSTOMER_SELECTOR)
        ) {
            positionDropdown(
                activeDropdown,
                input
            );
        }
    }

    function attachCustomerInput(input) {
        if (
            input.dataset.customerBankAttached ===
            'true'
        ) {
            return;
        }

        input.dataset.customerBankAttached =
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
            getPriceField(input);

        if (
            priceInput &&
            priceInput.dataset.customerBankAttached !==
                'true'
        ) {
            priceInput.dataset.customerBankAttached =
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
        getCustomerFields().forEach(
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

    function cancelRemovedRowTimers() {
        /*
         * WeakMap references disappear naturally when
         * rows are removed from the DOM.
         */
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function initialize() {
        await fetchCustomers();

        scanCustomerInputs();
        observeDynamicRows();

        document.addEventListener(
            'pointerdown',
            handleDocumentPointerDown
        );

        window.addEventListener(
            'resize',
            handleWindowResize
        );

        window.addEventListener(
            'scroll',
            handleWindowResize,
            true
        );

        window.marcidBlueCustomerBank = {
            refreshCustomers: fetchCustomers,
            openDropdown: function (input) {
                if (
                    input instanceof HTMLInputElement &&
                    input.matches(CUSTOMER_SELECTOR)
                ) {
                    renderDropdown(input);
                }
            },
            closeDropdown
        };
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