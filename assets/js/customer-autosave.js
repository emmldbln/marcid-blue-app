/*
=========================================================
MARCID BLUE
Customer Price / Gallon Autosave

Applies to:
- Shop Delivery Payments
- Driver's Delivery Payments

Customer Price/Gal is always editable.
=========================================================
*/

(function () {
    const SAVE_DELAY = 1500;
    const saveTimers = new WeakMap();
    const savingRows = new WeakSet();

    function getFields(row) {
        const isDriver = row.classList.contains('driver-delivery-row');

        return {
            customer: row.querySelector(isDriver ? '.driver-delivery-customer' : '.delivery-customer'),
            price: row.querySelector(isDriver ? '.driver-delivery-price' : '.delivery-price-input')
        };
    }

    function findCustomerOption(name) {
        const list = document.getElementById('shopDeliveryCustomerList');
        if (!list) return null;

        const normalized = name.trim().toLowerCase();
        return Array.from(list.options).find(option =>
            option.value.trim().toLowerCase() === normalized
        ) || null;
    }

    function addCustomerToList(name) {
        const list = document.getElementById('shopDeliveryCustomerList');
        if (!list || !name || findCustomerOption(name)) return;

        const option = document.createElement('option');
        option.value = name;
        list.appendChild(option);
    }

    function keepPriceEditable(row) {
        const { price } = getFields(row);
        if (!price) return;

        // The customer-selection code may mark an existing customer's
        // Price/Gal as readonly. It must remain editable so the saved
        // customer price can be changed later.
        if (price.readOnly) {
            price.readOnly = false;
            price.removeAttribute('readonly');
        }
    }

    async function saveCustomer(row) {
        if (!row || savingRows.has(row)) return;

        const { customer, price } = getFields(row);
        if (!customer || !price) return;

        keepPriceEditable(row);

        const customerName = customer.value.trim();
        const priceValue = price.value.trim();

        if (!customerName || priceValue === '') return;

        const numericPrice = Number(priceValue);
        if (!Number.isFinite(numericPrice) || numericPrice <= 0) return;

        savingRows.add(row);

        try {
            const body = new URLSearchParams();
            body.set('customer_name', customerName);
            body.set('gallon_price', numericPrice.toFixed(2));

            const response = await fetch('save-customer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to save customer price.');
            }

            // Keep the field editable after MySQL has been updated.
            price.value = Number(result.gallon_price)
                .toFixed(2)
                .replace(/\.00$/, '');
            price.dataset.saved = '1';
            price.readOnly = false;
            price.removeAttribute('readonly');

            addCustomerToList(result.customer_name);
        } catch (error) {
            console.error('Customer autosave failed:', error);
        } finally {
            savingRows.delete(row);
        }
    }

    function scheduleSave(row) {
        if (!row) return;

        const existingTimer = saveTimers.get(row);
        if (existingTimer) clearTimeout(existingTimer);

        const timer = setTimeout(() => {
            saveTimers.delete(row);
            saveCustomer(row);
        }, SAVE_DELAY);

        saveTimers.set(row, timer);
    }

    function handleEditablePrice(row, target) {
        if (!row || !target) return;

        const isPriceField =
            target.classList.contains('delivery-price-input') ||
            target.classList.contains('driver-delivery-price');

        const isCustomerField =
            target.classList.contains('delivery-customer') ||
            target.classList.contains('driver-delivery-customer');

        if (!isPriceField && !isCustomerField) return;

        // Always undo any readonly state immediately.
        keepPriceEditable(row);
        scheduleSave(row);
    }

    function init() {
        // Make every current and future Price/Gal field editable.
        document.querySelectorAll(
            '.delivery-price-input, .driver-delivery-price'
        ).forEach(input => {
            input.readOnly = false;
            input.removeAttribute('readonly');
        });

        document.addEventListener('input', event => {
            const target = event.target;
            if (!(target instanceof Element)) return;

            const row = target.closest(
                '.delivery-payment-row, .driver-delivery-row'
            );
            if (!row) return;

            handleEditablePrice(row, target);
        });

        document.addEventListener('change', event => {
            const target = event.target;
            if (!(target instanceof Element)) return;

            const row = target.closest(
                '.delivery-payment-row, .driver-delivery-row'
            );
            if (!row) return;

            handleEditablePrice(row, target);
        });

        // Watch dynamically-created delivery rows and any script that tries
        // to add readonly back to an existing customer's Price/Gal field.
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.type === 'attributes' && mutation.target instanceof Element) {
                    const target = mutation.target;
                    if (
                        target.classList.contains('delivery-price-input') ||
                        target.classList.contains('driver-delivery-price')
                    ) {
                        keepPriceEditable(target.closest(
                            '.delivery-payment-row, .driver-delivery-row'
                        ));
                    }
                }

                mutation.addedNodes.forEach(node => {
                    if (!(node instanceof Element)) return;

                    const rows = [];

                    if (node.matches('.delivery-payment-row, .driver-delivery-row')) {
                        rows.push(node);
                    }

                    node.querySelectorAll?.(
                        '.delivery-payment-row, .driver-delivery-row'
                    ).forEach(row => rows.push(row));

                    rows.forEach(row => keepPriceEditable(row));
                });
            });
        });

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['readonly']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
