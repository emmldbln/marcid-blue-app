/*
=========================================================
MARCID BLUE
Customer Price / Gallon Autosave
=========================================================

This script saves a customer's Price/Gal directly to MySQL
through pages/save-customer.php.

It intentionally does NOT infer Price/Gal from payment.
For a new customer, the user must enter the Price/Gal.
=========================================================
*/

document.addEventListener('DOMContentLoaded', () => {
    const deliveryRows = document.getElementById('deliveryPaymentRows');

    if (!deliveryRows) {
        return;
    }

    const saveTimers = new WeakMap();
    const savingRows = new WeakSet();

    function getFields(row) {
        return {
            customer: row.querySelector('.delivery-customer'),
            price: row.querySelector('.delivery-price-input')
        };
    }

    function findCustomerOption(name) {
        const list = document.getElementById('shopDeliveryCustomerList');

        if (!list) {
            return null;
        }

        const normalized = name.trim().toLowerCase();

        return Array.from(list.options).find(option =>
            option.value.trim().toLowerCase() === normalized
        ) || null;
    }

    function addCustomerToList(name) {
        const list = document.getElementById('shopDeliveryCustomerList');

        if (!list || !name) {
            return;
        }

        if (!findCustomerOption(name)) {
            const option = document.createElement('option');
            option.value = name;
            list.appendChild(option);
        }
    }

    function showStatus(row, message, success = true) {
        let status = row.querySelector('.customer-autosave-status');

        if (!status) {
            const priceGroup = row.querySelector('.delivery-price-group');

            if (!priceGroup) {
                return;
            }

            status = document.createElement('small');
            status.className = 'customer-autosave-status';
            status.style.display = 'block';
            status.style.marginTop = '6px';
            status.style.fontSize = '12px';
            priceGroup.appendChild(status);
        }

        status.textContent = message;
        status.classList.toggle('is-success', success);
        status.classList.toggle('is-error', !success);
    }

    async function saveCustomer(row) {
        if (!row || savingRows.has(row)) {
            return;
        }

        const { customer, price } = getFields(row);

        if (!customer || !price) {
            return;
        }

        const customerName = customer.value.trim();
        const priceValue = price.value.trim();

        if (!customerName || priceValue === '') {
            return;
        }

        const numericPrice = Number(priceValue);

        if (!Number.isFinite(numericPrice) || numericPrice <= 0) {
            return;
        }

        /*
         * Existing customers have their saved price locked by the
         * Daily Closing page. Only editable Price/Gal fields are
         * autosaved here, which prevents accidental price changes.
         */
        if (price.readOnly) {
            return;
        }

        savingRows.add(row);
        showStatus(row, 'Saving customer price…');

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

            price.value = Number(result.gallon_price)
                .toFixed(2)
                .replace(/\.00$/, '');
            price.readOnly = true;
            price.dataset.saved = '1';

            addCustomerToList(result.customer_name);
            showStatus(row, '✓ Customer price saved to MySQL');
        } catch (error) {
            console.error('Customer autosave failed:', error);
            showStatus(row, 'Unable to save customer price: ' + error.message, false);
        } finally {
            savingRows.delete(row);
        }
    }

    function scheduleSave(row) {
        if (!row) {
            return;
        }

        const existingTimer = saveTimers.get(row);

        if (existingTimer) {
            clearTimeout(existingTimer);
        }

        const timer = setTimeout(() => {
            saveTimers.delete(row);
            saveCustomer(row);
        }, 700);

        saveTimers.set(row, timer);
    }

    deliveryRows.addEventListener('input', event => {
        const row = event.target.closest('.delivery-payment-row');

        if (!row) {
            return;
        }

        if (
            event.target.classList.contains('delivery-customer')
            || event.target.classList.contains('delivery-price-input')
        ) {
            scheduleSave(row);
        }
    });

    deliveryRows.addEventListener('change', event => {
        const row = event.target.closest('.delivery-payment-row');

        if (!row) {
            return;
        }

        if (
            event.target.classList.contains('delivery-customer')
            || event.target.classList.contains('delivery-price-input')
        ) {
            scheduleSave(row);
        }
    });
});
