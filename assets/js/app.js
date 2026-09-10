/*
=========================================================
MARCID BLUE
Global JavaScript
=========================================================
*/

document.addEventListener('DOMContentLoaded', () => {

    console.log('Marcid Blue application loaded.');

    // --------------------------------------------------
    // Daily Closing - Driver Deliveries
    // --------------------------------------------------

    const deliveryRows = document.getElementById('deliveryRows');

    if (!deliveryRows) {
        return;
    }

    function money(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getDeliveryValues(row) {
        const slim = parseInt(row.querySelector('.slim')?.value || '0', 10) || 0;
        const round = parseInt(row.querySelector('.round')?.value || '0', 10) || 0;
        const priceInput = row.querySelector('.delivery-price');
        const paymentInput = row.querySelector('input[name="delivery_payment[]"]');

        const price = priceInput && priceInput.value !== ''
            ? parseFloat(priceInput.value)
            : null;

        const payment = paymentInput && paymentInput.value !== ''
            ? parseFloat(paymentInput.value)
            : null;

        return {
            slim,
            round,
            quantity: slim + round,
            price: Number.isFinite(price) ? price : null,
            payment: Number.isFinite(payment) ? payment : null
        };
    }

    function ensureDeliveryStatus(row) {
        let status = row.querySelector('.delivery-payment-status');

        if (!status) {
            const paymentCell = row.querySelector('input[name="delivery_payment[]"]')?.closest('td');

            if (!paymentCell) {
                return null;
            }

            status = document.createElement('div');
            status.className = 'delivery-payment-status';
            paymentCell.appendChild(status);
        }

        return status;
    }

    function calculateDeliveryRow(row) {
        if (!row) {
            return;
        }

        const values = getDeliveryValues(row);
        const output = row.querySelector('.calculated-amount');
        const priceInput = row.querySelector('.delivery-price');
        const paymentInput = row.querySelector('input[name="delivery_payment[]"]');
        const status = ensureDeliveryStatus(row);

        const amountDue = values.quantity > 0 && values.price !== null
            ? values.quantity * values.price
            : 0;

        if (output) {
            output.textContent = money(amountDue);
        }

        if (status) {
            status.textContent = '';
            status.className = 'delivery-payment-status';
        }

        if (
            values.quantity > 0
            && values.payment !== null
            && values.payment > 0
            && values.price === null
        ) {
            if (status) {
                status.textContent = 'Please enter Price/Gal for this customer.';
                status.classList.add('is-warning');
            }
        }

        const currentPrice = priceInput && priceInput.value !== ''
            ? parseFloat(priceInput.value)
            : null;

        if (
            values.quantity > 0
            && values.payment !== null
            && values.payment > 0
            && Number.isFinite(currentPrice)
        ) {
            const currentAmountDue = values.quantity * currentPrice;
            const difference = values.payment - currentAmountDue;

            if (status) {
                if (Math.abs(difference) < 0.005) {
                    status.textContent = '✓ Fully paid';
                    status.classList.add('is-valid');
                } else if (difference < 0) {
                    status.textContent = 'Balance: ' + money(Math.abs(difference));
                    status.classList.add('is-balance');
                } else {
                    status.textContent = 'Overpayment: ' + money(difference);
                    status.classList.add('is-overpayment');
                }
            }
        }

        if (paymentInput && currentPrice !== null && values.quantity > 0) {
            const currentAmountDue = values.quantity * currentPrice;
            const payment = values.payment || 0;

            if (payment > currentAmountDue) {
                paymentInput.classList.add('payment-over');
            } else {
                paymentInput.classList.remove('payment-over');
            }
        }
    }

    function calculateAllDeliveryRows() {
        deliveryRows.querySelectorAll('tr').forEach(calculateDeliveryRow);
    }

    function removeDeliveryNotesColumn() {
        const table = deliveryRows.closest('table');

        if (!table) {
            return;
        }

        const headerCells = table.querySelectorAll('thead th');
        const notesIndex = Array.from(headerCells).findIndex(
            cell => cell.textContent.trim().toLowerCase() === 'notes'
        );

        if (notesIndex === -1) {
            return;
        }

        table.querySelectorAll('tr').forEach(row => {
            const cells = row.children;

            if (cells[notesIndex]) {
                cells[notesIndex].remove();
            }
        });
    }

    removeDeliveryNotesColumn();
    calculateAllDeliveryRows();

    deliveryRows.addEventListener('input', event => {
        const target = event.target;
        const row = target.closest('tr');

        if (!row) {
            return;
        }

        if (target.classList.contains('delivery-price')) {
            target.dataset.autoPrice = 'false';
        }

        if (
            target.classList.contains('delivery-qty')
            || target.classList.contains('delivery-price')
            || target.name === 'delivery_payment[]'
        ) {
            calculateDeliveryRow(row);
        }
    });

    const observer = new MutationObserver(mutations => {
        let added = false;

        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE && node.matches('tr')) {
                    const cells = node.children;

                    if (cells.length >= 10) {
                        cells[cells.length - 1].remove();
                    }

                    calculateDeliveryRow(node);
                    added = true;
                }
            });
        });

        if (added) {
            calculateAllDeliveryRows();
        }
    });

    observer.observe(deliveryRows, { childList: true });

});


// ==================================================
// DAILY CLOSING - WALK-IN SALES
// ==================================================
// This section intentionally handles ONLY the regular
// Station / Walk-in sales area. Driver deliveries,
// expenses, gallon sales and reconciliation are left
// untouched for now.
// ==================================================

document.addEventListener('DOMContentLoaded', () => {

    const customersInput = document.getElementById('walk_in_customers');
    const moneyInput = document.getElementById('walk_in_money');

    if (!customersInput || !moneyInput) {
        return;
    }

    const walkInCard = customersInput.closest('.card');

    if (!walkInCard) {
        return;
    }

    const walkInPrice = 30;

    let computedBox = walkInCard.querySelector('.walk-in-computed');

    if (!computedBox) {
        computedBox = document.createElement('div');
        computedBox.className = 'walk-in-computed';
        computedBox.style.marginTop = '16px';
        computedBox.style.padding = '14px 16px';
        computedBox.style.border = '1px solid var(--border)';
        computedBox.style.borderRadius = 'var(--radius-md)';
        computedBox.style.background = 'var(--primary-light)';
        computedBox.innerHTML = `
            <div class="summary-label">Computed Walk-in Sales</div>
            <div class="summary-value" data-walk-in-computed>₱0.00</div>
            <div class="summary-description" data-walk-in-computed-note>
                Enter customers or walk-in money, then press Compute.
            </div>
        `;

        walkInCard.querySelector('.card-body')?.appendChild(computedBox);
    }

    const computedValue = computedBox.querySelector('[data-walk-in-computed]');
    const computedNote = computedBox.querySelector('[data-walk-in-computed-note]');

    function formatMoney(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function computeWalkIn() {
        const customers = customersInput.value.trim();
        const money = moneyInput.value.trim();

        let customerCount = 0;
        let sales = 0;

        if (customers !== '') {
            customerCount = parseInt(customers, 10);

            if (!Number.isInteger(customerCount) || customerCount < 0) {
                computedValue.textContent = '—';
                computedNote.textContent = 'Customers must be a whole number.';
                return false;
            }

            sales = customerCount * walkInPrice;
            moneyInput.value = sales.toFixed(2);
        } else if (money !== '') {
            const moneyValue = parseFloat(money);

            if (!Number.isFinite(moneyValue) || moneyValue < 0) {
                computedValue.textContent = '—';
                computedNote.textContent = 'Walk-in money must be a valid amount.';
                return false;
            }

            const calculatedCustomers = moneyValue / walkInPrice;

            if (!Number.isInteger(calculatedCustomers)) {
                computedValue.textContent = '—';
                computedNote.textContent =
                    'Money must divide evenly by ' + formatMoney(walkInPrice) + ' per customer.';
                return false;
            }

            customerCount = calculatedCustomers;
            sales = moneyValue;
            customersInput.value = customerCount;
        }

        computedValue.textContent = formatMoney(sales);
        computedNote.textContent =
            customerCount.toLocaleString('en-PH')
            + ' customers × '
            + formatMoney(walkInPrice)
            + ' = '
            + formatMoney(sales);

        return true;
    }

    let actionBar = walkInCard.querySelector('.walk-in-actions');

    if (!actionBar) {
        actionBar = document.createElement('div');
        actionBar.className = 'walk-in-actions section-actions';
        actionBar.style.marginTop = '16px';
        actionBar.style.justifyContent = 'flex-end';

        const computeButton = document.createElement('button');
        computeButton.type = 'button';
        computeButton.className = 'btn btn-secondary';
        computeButton.textContent = 'Compute';
        computeButton.addEventListener('click', computeWalkIn);

        const saveButton = document.createElement('button');
        saveButton.type = 'submit';
        saveButton.className = 'btn btn-primary';
        saveButton.textContent = 'Save';
        saveButton.setAttribute('formaction', 'save-walkin.php');
        saveButton.setAttribute('formmethod', 'post');
        saveButton.setAttribute('formnovalidate', '');
        saveButton.title = 'Save only Station / Walk-in sales';

        actionBar.appendChild(computeButton);
        actionBar.appendChild(saveButton);

        walkInCard.querySelector('.card-body')?.appendChild(actionBar);
    }

    customersInput.addEventListener('input', () => {
        if (customersInput.value.trim() !== '') {
            moneyInput.value = '';
        }
        computeWalkIn();
    });

    moneyInput.addEventListener('input', () => {
        if (moneyInput.value.trim() !== '') {
            customersInput.value = '';
        }
        computeWalkIn();
    });

    computeWalkIn();

});


// ==================================================
// DAILY CLOSING - CUSTOMER PRICE MYSQL AUTOSAVE
// ==================================================
// Load the dedicated customer-price autosave module.
// The module writes customer_name + Price/Gal directly
// to MySQL and never derives a price from payment.
// ==================================================

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('deliveryPaymentRows')) {
        return;
    }

    const scriptId = 'marcid-blue-customer-autosave';

    if (document.getElementById(scriptId)) {
        return;
    }

    const script = document.createElement('script');
    script.id = scriptId;
    script.src = '../assets/js/customer-autosave.js';
    script.defer = true;
    document.head.appendChild(script);
});
