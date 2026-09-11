/*
=========================================================
MARCID BLUE
Customer Price / Gallon Autosave
=========================================================
*/

(function () {
    function initCustomerAutosave() {
        const deliveryRows = document.getElementById('deliveryPaymentRows');
        if (!deliveryRows || deliveryRows.dataset.customerAutosaveReady === '1') return;
        deliveryRows.dataset.customerAutosaveReady = '1';

        const saveTimers = new WeakMap();
        const savingRows = new WeakSet();

        function getFields(row) {
            return {
                customer: row.querySelector('.delivery-customer'),
                price: row.querySelector('.delivery-price-input')
            };
        }

        function unlockPriceFields() {
            deliveryRows.querySelectorAll('.delivery-price-input').forEach(price => {
                price.readOnly = false;
                price.removeAttribute('readonly');
            });
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

        async function saveCustomer(row) {
            if (!row || savingRows.has(row)) return;
            const { customer, price } = getFields(row);
            if (!customer || !price) return;

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
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to save customer price.');
                }

                price.value = Number(result.gallon_price).toFixed(2).replace(/\.00$/, '');
                price.readOnly = false;
                price.removeAttribute('readonly');
                price.dataset.saved = '1';
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

            // Give the user enough time to finish typing a multi-digit Price/Gal.
            const timer = setTimeout(() => {
                saveTimers.delete(row);
                saveCustomer(row);
            }, 1500);
            saveTimers.set(row, timer);
        }

        // Existing customers may have been marked readonly by the page's
        // customer-selection logic. Always keep Price/Gal editable.
        unlockPriceFields();

        deliveryRows.addEventListener('input', event => {
            const row = event.target.closest('.delivery-payment-row');
            if (!row) return;

            if (event.target.classList.contains('delivery-customer') || event.target.classList.contains('delivery-price-input')) {
                const price = row.querySelector('.delivery-price-input');
                if (price) {
                    price.readOnly = false;
                    price.removeAttribute('readonly');
                }
                scheduleSave(row);
            }
        });

        deliveryRows.addEventListener('change', event => {
            const row = event.target.closest('.delivery-payment-row');
            if (!row) return;

            if (event.target.classList.contains('delivery-customer') || event.target.classList.contains('delivery-price-input')) {
                const price = row.querySelector('.delivery-price-input');
                if (price) {
                    price.readOnly = false;
                    price.removeAttribute('readonly');
                }
                scheduleSave(row);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCustomerAutosave, { once: true });
    } else {
        initCustomerAutosave();
    }
})();
