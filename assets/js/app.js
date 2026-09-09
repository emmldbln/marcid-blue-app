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

        // Amount due from agreed price.
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

        // If the user enters a payment while price is blank,
        // try to infer the price from payment / total gallons.
        if (
            values.quantity > 0
            && values.payment !== null
            && values.payment > 0
            && values.price === null
        ) {
            const impliedPrice = values.payment / values.quantity;
            const wholePrice = Math.round(impliedPrice);

            if (Math.abs(impliedPrice - wholePrice) < 0.000001) {
                if (priceInput) {
                    priceInput.value = wholePrice;
                    priceInput.dataset.autoPrice = 'true';
                }

                const newAmountDue = values.quantity * wholePrice;

                if (output) {
                    output.textContent = money(newAmountDue);
                }

                if (status) {
                    status.textContent = 'Price inferred: ' + money(wholePrice) + '/gal';
                    status.classList.add('is-valid');
                }
            } else if (status) {
                status.textContent =
                    '⚠ Payment does not divide evenly by ' + values.quantity + ' gallons.';
                status.classList.add('is-warning');
            }
        }

        // Compare payment against amount due whenever a valid price exists.
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

        // Payment cannot be greater than amount due when price is known
        // unless the user intentionally records an overpayment.
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

    // Remove the Driver Deliveries Notes column completely from the UI.
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

    // --------------------------------------------------
    // Intercept dynamically-added delivery rows so their
    // Notes cell is removed immediately.
    // --------------------------------------------------

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