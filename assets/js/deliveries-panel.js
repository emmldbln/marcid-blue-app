(function () {
    function init() {
        const shopForm = document.getElementById('shopWalkInForm');
        if (!shopForm || document.getElementById('driverDeliveriesPanel')) return;

        const shopCard = shopForm.closest('.card');
        if (!shopCard || !shopCard.parentNode) return;

        const style = document.createElement('style');
        style.id = 'marcid-blue-driver-deliveries-style';
        style.textContent = `
            .driver-deliveries-card {
                margin-top: 24px;
            }

            .driver-deliveries-layout {
                display: grid;
                grid-template-columns: minmax(220px, 0.8fr) minmax(220px, 1fr);
                gap: 18px;
                align-items: end;
            }

            .driver-money-field {
                max-width: 280px;
            }

            .driver-total-quantity {
                padding: 8px 0 4px 8px;
            }

            .driver-total-quantity .summary-value {
                font-size: 18px;
                font-weight: 700;
                line-height: 1.4;
            }

            .driver-panel-section {
                margin-top: 28px;
                padding-top: 24px;
                border-top: 1px solid var(--border);
            }

            .driver-panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
                margin-bottom: 16px;
            }

            .driver-panel-title {
                color: var(--text);
                font-size: 16px;
                font-weight: 700;
                line-height: 1.4;
            }

            .driver-panel-subtitle {
                margin-top: 4px;
                color: var(--text-muted);
                font-size: 13px;
                line-height: 1.5;
            }

            .driver-expense-rows,
            .driver-delivery-payment-rows {
                display: flex;
                flex-direction: column;
                gap: 0;
                padding: 0 16px;
                background: var(--background);
                border: 1px solid var(--border);
                border-radius: var(--radius-md);
            }

            .driver-expense-row {
                display: grid;
                grid-template-columns: minmax(180px, 1fr) minmax(160px, 0.8fr) minmax(220px, 1.2fr) 38px;
                gap: 14px;
                align-items: end;
                padding: 16px 0;
            }

            .driver-delivery-row {
                display: grid;
                grid-template-columns: minmax(200px, 1.6fr) minmax(70px, 0.55fr) minmax(70px, 0.55fr) minmax(120px, 1fr) minmax(90px, 0.7fr) minmax(120px, 0.9fr) minmax(145px, 1fr) 38px;
                gap: 12px;
                align-items: end;
                padding: 16px 0;
            }

            .driver-expense-row + .driver-expense-row,
            .driver-delivery-row + .driver-delivery-row {
                border-top: 1px solid var(--border);
            }

            .driver-expense-row .form-group,
            .driver-delivery-row .form-group {
                min-width: 0;
                margin: 0;
            }

            .driver-expense-row .form-input,
            .driver-delivery-row .form-input,
            .driver-delivery-balance {
                width: 100%;
                height: 38px;
                min-height: 38px;
                box-sizing: border-box;
            }

            .driver-expense-row select.form-input,
            .driver-delivery-row select.form-input {
                padding-top: 0;
                padding-bottom: 0;
                padding-left: 12px;
                padding-right: 32px;
                line-height: normal;
            }

            .driver-expense-remove,
            .driver-delivery-remove {
                width: 38px;
                height: 38px;
                min-width: 38px;
                min-height: 38px;
                margin: 0;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                align-self: end;
                justify-self: center;
                box-sizing: border-box;
                border: 1px solid var(--border);
                border-radius: var(--radius-sm);
                background: var(--surface);
                color: var(--danger);
                font-size: 19px;
                font-weight: 600;
                line-height: 1;
                cursor: pointer;
            }

            .driver-expense-remove:hover,
            .driver-delivery-remove:hover {
                background: var(--danger-light);
                border-color: var(--danger);
            }

            .driver-payment-controls {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }

            .driver-payment-controls .btn {
                min-width: 48px;
                height: 38px;
                padding: 0 12px;
            }

            .driver-delivery-balance {
                padding: 0 10px;
                display: flex;
                align-items: center;
                border: 1px solid var(--border);
                border-radius: var(--radius-sm);
                background: var(--surface);
                font-size: 13px;
                font-weight: 700;
                line-height: 1.2;
                white-space: nowrap;
            }

            .driver-delivery-balance-neutral { color: var(--text-muted); font-weight: 500; }
            .driver-delivery-balance-paid { color: var(--success); background: var(--success-light); border-color: rgba(46,155,91,.18); }
            .driver-delivery-balance-due { color: var(--warning); background: var(--warning-light); border-color: rgba(229,154,36,.18); }
            .driver-delivery-balance-unpaid { color: var(--danger); background: var(--danger-light); border-color: rgba(217,83,79,.18); }
            .driver-delivery-balance-overpaid { color: var(--primary-dark); background: var(--primary-light); border-color: rgba(22,135,201,.18); }

            .driver-delivery-note {
                display: block;
                margin-top: 4px;
                color: var(--text-muted);
                font-size: 11px;
                line-height: 1.3;
            }

            .driver-delivery-quantity-note {
                margin-top: 8px;
                color: var(--text-muted);
                font-size: 12px;
            }

            @media (min-width: 901px) {
                .driver-expense-row:not(:first-child) .form-label,
                .driver-delivery-row:not(:first-child) .form-label {
                    display: none;
                }
            }

            @media (max-width: 1200px) {
                .driver-delivery-row {
                    grid-template-columns: minmax(180px,1.4fr) minmax(65px,.55fr) minmax(65px,.55fr) minmax(115px,1fr) minmax(85px,.7fr) minmax(115px,.9fr) minmax(135px,1fr) 38px;
                    gap: 10px;
                }
            }

            @media (max-width: 900px) {
                .driver-deliveries-layout { grid-template-columns: repeat(2,minmax(0,1fr)); }
                .driver-money-field { max-width: none; }
                .driver-expense-row { grid-template-columns: 1fr 1fr; }
                .driver-expense-row .driver-expense-name-group { grid-column: 1 / -1; }
                .driver-expense-remove { grid-column: 2; justify-self: end; }
                .driver-delivery-row { grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
                .driver-delivery-row .driver-delivery-customer-group { grid-column: 1 / -1; }
                .driver-delivery-row .driver-delivery-balance-group { grid-column: span 2; }
                .driver-delivery-remove { grid-column: 3; justify-self: end; }
                .driver-panel-header { flex-direction: column; align-items: stretch; }
            }

            @media (max-width: 650px) {
                .driver-deliveries-layout { grid-template-columns: 1fr; }
                .driver-expense-row,
                .driver-delivery-row { grid-template-columns: 1fr; }
                .driver-expense-row .driver-expense-name-group,
                .driver-delivery-row .driver-delivery-customer-group,
                .driver-delivery-row .driver-delivery-balance-group { grid-column: auto; }
                .driver-expense-remove,
                .driver-delivery-remove { grid-column: auto; justify-self: start; }
                .driver-payment-controls { width: 100%; }
                .driver-payment-controls .btn { flex: 1; }
            }
        `;
        document.head.appendChild(style);

        const card = document.createElement('div');
        card.id = 'driverDeliveriesPanel';
        card.className = 'card driver-deliveries-card';
        card.innerHTML = `
            <div class="card-header">
                <div>
                    <div class="card-title">Deliveries</div>
                    <div class="section-description">
                        Record the driver's remittance, driver's expenses, and delivery payments separately from the Station / Shop records.
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="driver-deliveries-layout">
                    <div class="driver-money-field">
                        <label for="driver_money_received" class="form-label">
                            Total Money Received (Driver Only)
                        </label>
                        <input
                            type="number"
                            id="driver_money_received"
                            name="driver_money_received"
                            class="form-input"
                            min="0"
                            step="0.01"
                            placeholder="Enter amount"
                        >
                    </div>
                    <div class="driver-total-quantity">
                        <div class="summary-label">Total Quantity of Deliveries</div>
                        <div class="summary-value" id="driver_total_delivery_quantity">0</div>
                        <div class="driver-delivery-quantity-note">
                            Includes all Slim + Round quantities from Shop Delivery Payments and Driver's Delivery Payments.
                        </div>
                    </div>
                </div>

                <div class="driver-panel-section">
                    <div class="driver-panel-header">
                        <div>
                            <div class="driver-panel-title">Driver's Expenses</div>
                            <div class="driver-panel-subtitle">
                                Add one or more expenses made by the driver. These remain separate from Station Expenses.
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="addDriverExpenseButton">+ Expenses</button>
                    </div>
                    <div id="driverExpenseRows" class="driver-expense-rows"></div>
                </div>

                <div class="driver-panel-section">
                    <div class="driver-panel-header">
                        <div>
                            <div class="driver-panel-title">Driver's Delivery Payments</div>
                            <div class="driver-panel-subtitle">
                                Same customer, quantity, Price/Gal, payment, method, and balance functionality as Shop Delivery Payments.
                            </div>
                        </div>
                        <div class="driver-payment-controls" aria-label="Add or remove driver delivery payment rows">
                            <button type="button" class="btn btn-secondary" data-driver-payment-adjust="1">+1</button>
                            <button type="button" class="btn btn-secondary" data-driver-payment-adjust="3">+3</button>
                            <button type="button" class="btn btn-secondary" data-driver-payment-adjust="10">+10</button>
                            <button type="button" class="btn btn-secondary" data-driver-payment-adjust="-1">−1</button>
                            <button type="button" class="btn btn-secondary" data-driver-payment-adjust="-3">−3</button>
                            <button type="button" class="btn btn-secondary" data-driver-payment-adjust="-10">−10</button>
                        </div>
                    </div>
                    <div id="driverDeliveryPaymentRows" class="driver-delivery-payment-rows"></div>
                </div>
            </div>
        `;

        shopCard.parentNode.insertBefore(card, shopCard.nextSibling);

        const expenseRows = document.getElementById('driverExpenseRows');
        const deliveryRows = document.getElementById('driverDeliveryPaymentRows');
        const quantityOutput = document.getElementById('driver_total_delivery_quantity');

        function formatCurrency(value) {
            return '₱' + Number(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function createExpenseRow() {
            const row = document.createElement('div');
            row.className = 'driver-expense-row';
            row.innerHTML = `
                <div class="form-group">
                    <label class="form-label">Expense</label>
                    <select name="driver_expense_category[]" class="form-input driver-expense-category">
                        <option value="">No Expense</option>
                        <option value="Food">Food</option>
                        <option value="Gas">Gas</option>
                        <option value="Cash Advance">Cash Advance</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <input type="number" name="driver_expense_amount[]" class="form-input driver-expense-amount" min="0" step="0.01" placeholder="Enter amount">
                </div>
                <div class="form-group driver-expense-name-group">
                    <label class="form-label driver-expense-name-label">Name / Description</label>
                    <input type="text" name="driver_expense_name[]" class="form-input driver-expense-name" maxlength="255" placeholder="Only needed for Cash Advance / Others" disabled>
                </div>
                <button type="button" class="driver-expense-remove" title="Remove driver expense" aria-label="Remove driver expense">×</button>
            `;
            updateExpenseRow(row);
            return row;
        }

        function updateExpenseRow(row) {
            const category = row.querySelector('.driver-expense-category');
            const name = row.querySelector('.driver-expense-name');
            const label = row.querySelector('.driver-expense-name-label');
            if (!category || !name || !label) return;
            const needsName = category.value === 'Cash Advance' || category.value === 'Others';
            name.disabled = !needsName;
            name.required = needsName;
            label.textContent = needsName ? 'Name / Description *' : 'Name / Description';
            if (needsName) {
                name.placeholder = category.value === 'Cash Advance' ? 'Enter recipient name' : 'Enter expense description';
            } else {
                name.value = '';
                name.placeholder = 'Not required for Food / Gas';
            }
        }

        function createDeliveryRow() {
            const row = document.createElement('div');
            row.className = 'driver-delivery-row';
            row.innerHTML = `
                <div class="form-group driver-delivery-customer-group">
                    <label class="form-label">Customer</label>
                    <input type="text" name="driver_delivery_customer[]" class="form-input driver-delivery-customer" list="shopDeliveryCustomerList" maxlength="100" placeholder="Select or enter customer" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Slim</label>
                    <input type="number" name="driver_delivery_slim[]" class="form-input driver-delivery-slim" min="0" step="1" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Round</label>
                    <input type="number" name="driver_delivery_round[]" class="form-input driver-delivery-round" min="0" step="1" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Payment</label>
                    <input type="number" name="driver_delivery_payment[]" class="form-input driver-delivery-payment" min="0" step="0.01" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label class="form-label">Price/Gal</label>
                    <input type="number" name="driver_delivery_price_per_gallon[]" class="form-input driver-delivery-price" min="0" step="0.01" placeholder="Required for new customer">
                    <span class="driver-delivery-note">Customer price will be used when available.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Method</label>
                    <select name="driver_delivery_method[]" class="form-input driver-delivery-method">
                        <option value="Cash" selected>Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group driver-delivery-balance-group">
                    <label class="form-label">Balance</label>
                    <div class="driver-delivery-balance driver-delivery-balance-neutral" aria-live="polite">—</div>
                </div>
                <button type="button" class="driver-delivery-remove" title="Remove payment" aria-label="Remove payment">×</button>
            `;
            updateDeliveryStatus(row);
            return row;
        }

        function updateDeliveryStatus(row) {
            const slim = parseInt(row.querySelector('.driver-delivery-slim')?.value || '0', 10) || 0;
            const round = parseInt(row.querySelector('.driver-delivery-round')?.value || '0', 10) || 0;
            const payment = parseFloat(row.querySelector('.driver-delivery-payment')?.value || '0') || 0;
            const price = parseFloat(row.querySelector('.driver-delivery-price')?.value || '0') || 0;
            const balance = row.querySelector('.driver-delivery-balance');
            if (!balance) return;

            balance.className = 'driver-delivery-balance driver-delivery-balance-neutral';
            const gallons = slim + round;
            if (gallons <= 0 || price <= 0) {
                balance.textContent = '—';
                return;
            }

            const difference = gallons * price - payment;
            if (payment <= 0.005) {
                balance.className = 'driver-delivery-balance driver-delivery-balance-unpaid';
                balance.textContent = formatCurrency(gallons * price) + ' Unpaid';
            } else if (Math.abs(difference) <= 0.005) {
                balance.className = 'driver-delivery-balance driver-delivery-balance-paid';
                balance.textContent = '✓ Paid';
            } else if (difference > 0) {
                balance.className = 'driver-delivery-balance driver-delivery-balance-due';
                balance.textContent = formatCurrency(difference) + ' Due';
            } else {
                balance.className = 'driver-delivery-balance driver-delivery-balance-overpaid';
                balance.textContent = formatCurrency(Math.abs(difference)) + ' Overpaid';
            }
        }

        function addDeliveryRows(count) {
            for (let i = 0; i < count; i++) deliveryRows.appendChild(createDeliveryRow());
            updateTotalQuantity();
        }

        function removeDeliveryRows(count) {
            const rows = Array.from(deliveryRows.querySelectorAll('.driver-delivery-row'));
            const removeCount = Math.min(count, Math.max(0, rows.length - 1));
            for (let i = 0; i < removeCount; i++) rows[rows.length - 1 - i].remove();
            updateTotalQuantity();
        }

        function getShopDeliveryQuantity() {
            let total = 0;
            document.querySelectorAll('#deliveryPaymentRows .delivery-payment-row').forEach(row => {
                total += parseInt(row.querySelector('.delivery-slim')?.value || '0', 10) || 0;
                total += parseInt(row.querySelector('.delivery-round')?.value || '0', 10) || 0;
            });
            return total;
        }

        function getDriverDeliveryQuantity() {
            let total = 0;
            deliveryRows.querySelectorAll('.driver-delivery-row').forEach(row => {
                total += parseInt(row.querySelector('.driver-delivery-slim')?.value || '0', 10) || 0;
                total += parseInt(row.querySelector('.driver-delivery-round')?.value || '0', 10) || 0;
            });
            return total;
        }

        function updateTotalQuantity() {
            quantityOutput.textContent = (getShopDeliveryQuantity() + getDriverDeliveryQuantity()).toLocaleString('en-PH');
        }

        expenseRows.appendChild(createExpenseRow());
        deliveryRows.appendChild(createDeliveryRow());

        document.getElementById('addDriverExpenseButton').addEventListener('click', function () {
            expenseRows.appendChild(createExpenseRow());
        });

        expenseRows.addEventListener('change', function (event) {
            if (event.target.classList.contains('driver-expense-category')) {
                updateExpenseRow(event.target.closest('.driver-expense-row'));
            }
        });

        expenseRows.addEventListener('click', function (event) {
            const button = event.target.closest('.driver-expense-remove');
            if (!button) return;
            const rows = expenseRows.querySelectorAll('.driver-expense-row');
            const row = button.closest('.driver-expense-row');
            if (!row) return;
            if (rows.length === 1) {
                row.querySelector('.driver-expense-category').value = '';
                row.querySelector('.driver-expense-amount').value = '';
                row.querySelector('.driver-expense-name').value = '';
                updateExpenseRow(row);
            } else {
                row.remove();
            }
        });

        card.querySelectorAll('[data-driver-payment-adjust]').forEach(button => {
            button.addEventListener('click', function () {
                const amount = Number(button.dataset.driverPaymentAdjust || 0);
                if (amount > 0) addDeliveryRows(amount);
                if (amount < 0) removeDeliveryRows(Math.abs(amount));
            });
        });

        deliveryRows.addEventListener('input', function (event) {
            const row = event.target.closest('.driver-delivery-row');
            if (!row) return;
            updateDeliveryStatus(row);
            if (event.target.matches('.driver-delivery-slim, .driver-delivery-round')) updateTotalQuantity();
        });

        deliveryRows.addEventListener('change', function (event) {
            const row = event.target.closest('.driver-delivery-row');
            if (row) updateDeliveryStatus(row);
        });

        deliveryRows.addEventListener('click', function (event) {
            const button = event.target.closest('.driver-delivery-remove');
            if (!button) return;
            const rows = deliveryRows.querySelectorAll('.driver-delivery-row');
            const row = button.closest('.driver-delivery-row');
            if (!row) return;
            if (rows.length === 1) {
                row.querySelector('.driver-delivery-customer').value = '';
                row.querySelector('.driver-delivery-slim').value = '';
                row.querySelector('.driver-delivery-round').value = '';
                row.querySelector('.driver-delivery-payment').value = '';
                row.querySelector('.driver-delivery-price').value = '';
                row.querySelector('.driver-delivery-method').value = 'Cash';
                updateDeliveryStatus(row);
            } else {
                row.remove();
            }
            updateTotalQuantity();
        });

        const shopRows = document.getElementById('deliveryPaymentRows');
        if (shopRows) {
            shopRows.addEventListener('input', updateTotalQuantity);
            shopRows.addEventListener('change', updateTotalQuantity);
            const observer = new MutationObserver(updateTotalQuantity);
            observer.observe(shopRows, { childList: true, subtree: true });
        }

        updateTotalQuantity();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
